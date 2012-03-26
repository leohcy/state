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
     * @param   $new    是否返回新值
     * @return  返回值
     */
    public static function findAndModify($domain, $id, $path, $time, array $update, $new = FALSE) {
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
            'upsert' => TRUE,
            'new' => $new
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

    /**
     * 根据主键查询
     * @param   $domain    领域对象
     * @param   $ids    ID列表
     * @param   $path    限定路径
     * @param   $query    查询条件
     * @return  返回值/NULL
     */
    public static function byIdList($domain, array $ids, $path, array $query = array()) {
        if(Kohana::$profiling)
            $benchmark = Profiler::start('MongoDB', 'byIdList');
        $query = array('_id' => array('$in' => $ids)) + $query;
        $cursor = State_MongoDB::secondary($domain)->find($query, array($path => 1));
        $result = array();
        foreach($cursor as $doc)
            $result[$doc['_id']] = Arr::path($doc, $path);
        foreach($ids as $id)
            if(!array_key_exists($id, $result))
                $result[$id] = NULL;
        if(isset($benchmark))
            Profiler::stop($benchmark);
        return $result;
    }

    /**
     * 根据条件查询数量
     * @param   $domain    领域对象
     * @param   $query    查询条件
     * @return  数量
     */
    public static function count($domain, array $query) {
        if(Kohana::$profiling)
            $benchmark = Profiler::start('MongoDB', 'count');
        $count = State_MongoDB::secondary($domain)->find($query)->count();
        if(isset($benchmark))
            Profiler::stop($benchmark);
        return $count;
    }

    /**
     * 根据条件查询
     * @param   $domain    领域对象
     * @param   $query    查询条件
     * @param   $path    限定路径
     * @param   $skip    翻页参数
     * @param   $limit    翻页参数
     * @return  $total, $result     总数，结果数组
     */
    public static function query($domain, array $query, $path = NULL, $skip = NULL, $limit = NULL) {
        if(Kohana::$profiling)
            $benchmark = Profiler::start('MongoDB', 'query');
        $table = TRUE;
        if(!isset($path)) {
            $path = '_id';
            $table = FALSE;
        }
        $cursor = State_MongoDB::secondary($domain)->find($query, array($path => 1));
        if(isset($skip))
            $cursor->skip($skip);
        if(isset($limit))
            $cursor->limit($limit);
        $result = array();
        foreach($cursor as $doc) {
            if($table)
                $result[$doc['_id']] = Arr::path($doc, $path);
            else
                $result[] = $doc['_id'];
        }
        if(isset($skip) || isset($limit))
            $total = $cursor->count();
        else
            $total = count($result);
        if(isset($benchmark))
            Profiler::stop($benchmark);
        return array(
            $total,
            $result
        );
    }

}
