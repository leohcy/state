<?php defined('SYSPATH') or die('No direct script access.');

class State_MongoDB {

    /**
     * 返回MongoDB主节点，允许读写操作
     */
    public static function primary() {
        $server = Kohana::$config->load('mongodb.server');
        $mongo = new Mongo($server);
        $db = Kohana::$config->load('mongodb.db');
        return $mongo->selectDB($db);
    }

    /**
     * 返回MongoDB从节点，允许只读操作
     */
    public static function secondary() {
        $server = Kohana::$config->load('mongodb.server');
        $mongo = new Mongo($server);
        $mongo->setSlaveOkay();
        $db = Kohana::$config->load('mongodb.db');
        return $mongo->selectDB($db);
    }

}
