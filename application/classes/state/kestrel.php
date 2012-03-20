<?php defined('SYSPATH') or die('No direct script access.');

class State_Kestrel {

    private static $_instance = NULL;
    public static function instance() {
        if(!isset(State_Kestrel::$_instance))
            State_Kestrel::$_instance = new State_Kestrel;
        return State_Kestrel::$_instance;
    }

    private $_default_config = array(
        'host' => 'localhost',
        'port' => 11211,
        'queue' => 'default',
        'persistent' => FALSE,
        'weight' => 1,
        'timeout' => 1,
        'retry_interval' => 15,
        'status' => TRUE
    );
    private $_kestrel;
    private $_candidate_servers;
    private $_current_server;
    private $_queue_name;

    public function __construct() {
        $this->_kestrel = new Memcache;
        $this->_candidate_servers = Kohana::$config->load('kestrel.servers');
        // 随机化队列列表
        shuffle($this->_candidate_servers);
        // 选择列表中第一个节点为本次操作节点
        $this->addServer();
    }

    public function _failed_request($hostname, $port) {
        Kohana::warning("kestrel request failed: $hostname:$port");
        $server = $this->_current_server;
        // 把该节点设置为失效状态
        $this->_kestrel->setServerParams($server['host'], $server['port'], $server['timeout'], $server['retry_interval'], FALSE);
        // 选择列表中下一个节点为本次操作节点
        return $this->addServer();
    }

    private function addServer() {
        $server = array_shift($this->_candidate_servers);
        if($server === NULL)
            return;
        $server += $this->_default_config;
        $this->_current_server = $server;
        // 初始化队列名，过滤特殊字符
        $this->_queue_name = str_replace(array(
            '/',
            '\\',
            ' '
        ), '_', $server['queue']);
        return $this->_kestrel->addServer($server['host'], $server['port'], $server['persistent'], $server['weight'], $server['timeout'], $server['retry_interval'], $server['status'], array(
            $this,
            '_failed_request'
        ));
    }

    /**
     * 向队列投递数据
     * @param   $data    数据
     * @param   $try    尝试次数
     * @return  TRUE/FALSE
     */
    public function set($data, $try = 2) {
        $success = FALSE;
        do {
            $success = $this->_kestrel->set($this->_queue_name, $data);
            $try--;
        } while($success == FALSE && $try > 0);
        return $success;
    }

}
