<?php defined('SYSPATH') or die('No direct script access.');

class State_HttpClient {

    private static $_instance = NULL;
    public static function instance() {
        if(!isset(State_HttpClient::$_instance))
            State_HttpClient::$_instance = new State_HttpClient;
        return State_HttpClient::$_instance;
    }

    private $_url;
    private $_params;

    public function __construct() {
        $servers = Kohana::$config->load('httpclient.servers');
        foreach($servers as $server)
            $this->addServer($server['server'], $server['weight']);
        $this->_url = Kohana::$config->load('httpclient.url');
        $this->_params = Kohana::$config->load('httpclient.params');
    }

    private $_serverToPositions = array();
    private $_positionToServer = array();
    private $_serverCount = 0;
    private $_positionToServerSorted = FALSE;

    // 一致性哈希，增加服务器到环
    private function addServer($server, $weight = 1) {
        if(isset($this->_serverToPositions[$server]))
            return;
        $this->_serverToPositions[$server] = array();
        for($i = 0; $i < round(64 * $weight); $i++) {
            $position = crc32($server.$i);
            $this->_positionToServer[$position] = $server;
            $this->_serverToPositions[$server][] = $position;
        }
        $this->_positionToServerSorted = FALSE;
        $this->_serverCount++;
    }

    // 一致性哈希，从环删除服务器
    private function delServer($server) {
        if(!isset($this->_serverToPositions[$server]))
            return;
        foreach($this->_serverToPositions[$server] as $position)
            unset($this->_positionToServer[$position]);
        unset($this->_serverToPositions[$server]);
        $this->_serverCount--;
    }

    // 一致性哈希，从环中查找
    private function lookup($resource) {
        // 没有可用服务器了
        if(empty($this->_positionToServer))
            return FALSE;
        // 如果可用服务器只有1个，避免后续调用
        if($this->_serverCount == 1)
            return Arr::get(array_values($this->_positionToServer), 0);
        // 如果还没有排序，则排序
        if(!$this->_positionToServerSorted) {
            ksort($this->_positionToServer, SORT_REGULAR);
            $this->_positionToServerSorted = TRUE;
        }
        // CRC32哈希
        $resourcePosition = crc32($resource);
        // 在环上查找位于资源之后的服务器
        foreach($this->_positionToServer as $key => $value)
            if($key > $resourcePosition)
                return $value;
        // 没找到的话，则使用环中的第一个服务器
        return reset($this->_positionToServer);
    }

    /**
     * 发起GET请求
     * @param   $query    请求参数
     * @param   $resource    分片条件
     * @param   $try    尝试次数
     * @return  Response Body / FALSE
     */
    public function get(array $query, $resource, $try = 2) {
        // 合并默认参数和传入参数
        $query = Arr::merge($this->_params, $query);
        do {
            // 找到可用服务器
            $server = $this->lookup($resource);
            if($server === FALSE)
                return FALSE;
            $url = __('http://:server:url', array(
                ':server' => $server,
                ':url' => $this->_url
            ));
            try {
                // 使用CURL执行请求
                $request = Request::factory($url)->query($query);
                $request->client()->options(CURLOPT_TIMEOUT, 1);
                $response = $request->execute();
                $code = $response->status();
                if($code == 200)
                    return $response->body();
                Kohana::notice("httpclient request failed. url: $url code: $code");
            } catch(Exception $e) {
                Kohana::notice("httpclient request failed", NULL, $e);
            }
            // 请求不成功则视此服务器为不可用，进行下一次尝试
            $this->delServer($server);
            $try--;
        } while($try > 0);
        return FALSE;
    }

}
