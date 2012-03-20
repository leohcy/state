<?php defined('SYSPATH') or die('No direct script access.');

class State_MongoDB {

    private static $_primary = NULL;
    /**
     * 返回MongoDB主节点，允许读写操作
     * @param   $domain    领域对象
     * @return  DB或Collection
     */
    public static function primary($domain = NULL) {
        if(!isset(State_MongoDB::$_primary)) {
            $server = Kohana::$config->load('mongodb.server');
            $mongo = new Mongo($server);
            $db = Kohana::$config->load('mongodb.db');
            State_MongoDB::$_primary = $mongo->selectDB($db);
        }
        if(!isset($domain))
            return State_MongoDB::$_primary;
        return State_MongoDB::$_primary->selectCollection($domain);
    }

    private static $_secondary = NULL;
    /**
     * 返回MongoDB从节点，允许只读操作
     * @param   $domain    领域对象
     * @return  DB或Collection
     */
    public static function secondary($domain = NULL) {
        if(!isset(State_MongoDB::$_secondary)) {
            $server = Kohana::$config->load('mongodb.server');
            $mongo = new Mongo($server);
            $mongo->setSlaveOkay();
            $db = Kohana::$config->load('mongodb.db');
            State_MongoDB::$_secondary = $mongo->selectDB($db);
        }
        if(!isset($domain))
            return State_MongoDB::$_secondary;
        return State_MongoDB::$_secondary->selectCollection($domain);
    }

    /**
     * 原子修改
     * @param   $domain    领域对象
     * @param   $id    ID
     * @param   $path    路径
     * @param   $time    时间戳
     * @param   $update    更新操作
     * @return  返回值
     */
    public static function findAndModify($domain, $id, $path, $time, array $update) {
        if(Kohana::$profiling)
            $benchmark = Profiler::start('MongoDB', 'findAndModify');
        $timepath = 'time.'.strtr($path, '.', '_');
        $result = State_MongoDB::primary()->command(array(
            'findAndModify' => $domain,
            'query' => array(
                '_id' => $id,
                '$or' => array(
                    array($timepath => array('$exists' => FALSE)),
                    array($timepath => array('$lte' => $time))
                )
            ),
            'update' => Arr::merge($update, array('$set' => array($timepath => $time))),
            'fields' => array($path => 1),
            'upsert' => TRUE
        ));
        if(isset($benchmark))
            Profiler::stop($benchmark);
        return $result;
    }

    /**
     * 原子修改，不关心时间戳，不关心原值
     * @param   $domain    领域对象
     * @param   $id    ID
     * @param   $update    更新操作
     * @return  返回值
     */
    public static function update($domain, $id, array $update) {
        if(Kohana::$profiling)
            $benchmark = Profiler::start('MongoDB', 'update');
        $result = State_MongoDB::primary($domain)->update(array('_id' => $id), $update, array(
            'upsert' => TRUE,
            'safe' => TRUE
        ));
        if(isset($benchmark))
            Profiler::stop($benchmark);
        return $result;
    }

}
