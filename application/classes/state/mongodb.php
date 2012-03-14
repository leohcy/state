<?php defined('SYSPATH') or die('No direct script access.');

class State_MongoDB {

    /**
     * 返回MongoDB主节点，允许读写操作
     * @param   $domain    领域对象
     * @return  DB或Collection
     */
    public static function primary($domain = NULL) {
        $server = Kohana::$config->load('mongodb.server');
        $mongo = new Mongo($server);
        $db = Kohana::$config->load('mongodb.db');
        $mongodb = $mongo->selectDB($db);
        if(!isset($domain))
            return $mongodb;
        return $mongodb->selectCollection($domain);
    }

    /**
     * 返回MongoDB从节点，允许只读操作
     * @param   $domain    领域对象
     * @return  DB或Collection
     */
    public static function secondary($domain = NULL) {
        $server = Kohana::$config->load('mongodb.server');
        $mongo = new Mongo($server);
        $mongo->setSlaveOkay();
        $db = Kohana::$config->load('mongodb.db');
        $mongodb = $mongo->selectDB($db);
        if(!isset($domain))
            return $mongodb;
        return $mongodb->selectCollection($domain);
    }

    /**
     * 原子修改
     * @param   $domain    领域对象
     * @param   $id    ID
     * @param   $path    路径
     * @param   $time    时间戳
     * @param   $value    数值
     * @return  返回值
     */
    public static function findAndModify($domain, $id, $path, $time, &$value) {
        $timepath = 'time.'.strtr($path, '.', '_');
        return State_MongoDB::primary()->command(array(
            'findAndModify' => $domain,
            'query' => array(
                '_id' => $id,
                '$or' => array(
                    array($timepath => array('$exists' => FALSE)),
                    array($timepath => array('$lte' => $time))
                )
            ),
            'update' => array('$set' => array(
                    $path => $value,
                    $timepath => $time
                )),
            'fields' => array($path => 1),
            'upsert' => TRUE
        ));
    }

    /**
     * 原子修改，不关心时间戳，不关心原值
     * @param   $domain    领域对象
     * @param   $id    ID
     * @param   $object    修改操作
     * @return  返回值
     */
    public static function update($domain, $id, array &$object) {
        return State_MongoDB::primary($domain)->update(array('_id' => $id), $object, array(
            'upsert' => TRUE,
            'safe' => TRUE
        ));
    }

}
