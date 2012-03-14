<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Common extends Controller {

    private $_model = array();
    /**
     * 获取或查看model值
     * @param   $key    model的key或完整model的数组
     * @param   $value  model的value
     * @return  单值或数组或Controller本身
     */
    protected function model($key = NULL, $value = NULL) {
        if(is_array($key))
            $this->_model = $key;
        elseif($key === NULL)
            return $this->_model;
        elseif($value === NULL)
            return Arr::get($this->_model, $key);
        else
            $this->_model[$key] = $value;
        return $this;
    }

    /**
     * 处理异常
     * @param   $message    消息
     * @param   $exception    异常
     * @param   $warning    日志级别是否设置为警告
     * @return  FALSE   处理完毕
     */
    protected function handler($message, $exception = NULL, $warning = FALSE) {
        // 错误日志
        $level = $warning ? 'warning' : 'notice';
        Kohana::$level("[{$this->source}] {$message}", NULL, $exception);
        $datatype = $this->request->param('datatype', 'json');
        if($datatype == 'json') {
            //$this->response->headers('Content-Type', 'application/json; charset='.Kohana::$charset);
            $this->response->body(json_encode(array(
                'success' => FALSE,
                'message' => $message
            )));
            // 响应日志
            Kohana::info("[{$this->source}] response: {$this->response->body()}");
            return FALSE;
        }
        State_Exception::handler(isset($exception) ? $exception : new State_Exception($message));
    }

    protected $source;
    protected $domain;
    protected $id;
    protected $path;
    protected $prop;

    public function before() {
        $this->source = __(':client->:uri?:params', array(
            ':client' => Request::$client_ip,
            ':uri' => $this->request->controller().'/'.$this->request->action(),
            ':params' => http_build_query($this->request->param())
        ));
        // 请求日志
        Kohana::info("[{$this->source}] request");
        // 领域对象
        $this->domain = $this->request->param('domain');
        // ID
        $this->id = $this->convert('id', 'int');
        if($this->id === FALSE)
            return FALSE;
        // 路径
        $this->path = $this->request->param('path');
        if(!Valid::not_empty($this->path) || !Valid::regex($this->path, '/^[a-z0-9.]++$/iD'))
            return $this->handler("invalid path:[{$this->path}] wrong format");
        // 读取配置
        $this->prop = Kohana::$config->load('property.'.$this->domain.'.'.$this->path);
        if(!isset($this->prop))
            return $this->handler("invalid path:[{$this->path}] not allowed");
    }

    public function after() {
        $datatype = $this->request->param('datatype', 'json');
        if($datatype == 'json') {
            //$this->response->headers('Content-Type', 'application/json; charset='.Kohana::$charset);
            $this->response->body(json_encode($this->model()));
            // 响应日志
            Kohana::info("[{$this->source}] response: {$this->response->body()}");
        } else {
            $this->response->body(Debug::vars($this->model()).View::factory('kohana/profiler'));
        }
    }

    /**
     * 根据指定类型，提取用户传入参数，转换为对应类型
     * @param   $param    参数名
     * @param   $type    类型信息
     * @return  FALSE   处理完毕        NULL    跳过
     */
    protected function convert($param, $prop) {
        if(is_string($prop)) {// 单值类型
            $value = $this->request->param(strtr($param, '.', '_'));
            if(!Valid::not_empty($value)) {
                if(strpos($param, '.') !== FALSE)// 跳过
                    return NULL;
                return $this->handler("invalid parameters. $param:[$value] required");
            }
            if($prop == 'int' || $prop == 'incr') {// 整型
                if(!Valid::digit($value))
                    return $this->handler("invalid parameters. $param:[$value] need int");
                return (int)$value;
            } elseif($prop == 'float') {// 浮点
                if(!Valid::numeric($value))
                    return $this->handler("invalid parameters. $param:[$value] need float");
                return (float)$value;
            } elseif($prop == 'bool') {// 布尔
                if(Valid::regex($value, '/^(true|1|yes|on)$/iD'))
                    return 1;
                elseif(Valid::regex($value, '/^(false|0|no|off)$/iD'))
                    return 0;
                else
                    return $this->handler("invalid parameters. $param:[$value] need bool");
            } elseif($prop == 'string') {// 字符串
                return (string)$value;
            } elseif(Text::start_with($prop, 'ref:')) {// DBRef
                if(!Valid::digit($value))
                    return $this->handler("invalid parameters. $param:[$value] need int");
                return MongoDBRef::create(substr($prop, 4), (int)$value);
            } else {// 其他
                return $this->handler("invalid request. $param:[$value] not allowed");
            }
        }
        // 数组类型
        $array = array();
        foreach($prop as $param1 => $prop1) {
            if($param1 == '$array') {
                if($prop1 == 'embed')
                    continue;
                return $this->convert($param, $prop1);
            }
            $value = $this->convert($param.'.'.$param1, $prop1);
            if($value === FALSE)// 错误
                return FALSE;
            if($value === NULL || (is_array($value) && empty($value)))// 可选 跳过
                continue;
            if(is_array($prop1) && isset($prop1['$array']))// 包装数组
                $value = array($value);
            $array[$param1] = $value;
        }
        if(strpos($param, '.') === FALSE && !$array)// 至少有一个参数
            return $this->handler('invalid parameters. need at least one param');
        return $array;
    }

}
