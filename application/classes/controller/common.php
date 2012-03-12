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
        $level = $warning ? 'warning' : 'notice';
        // 错误日志
        Kohana::$level('[:source] :message', array(
            ':source' => $this->source,
            ':message' => $message
        ), $exception);
        $datatype = $this->request->param('datatype', 'json');
        if($datatype == 'json') {
            $this->response->headers('Content-Type', 'application/json; charset='.Kohana::$charset);
            $this->response->body(json_encode(array(
                'success' => FALSE,
                'message' => $message
            )));
            // 响应日志
            Kohana::info('[:source] response: :body', array(
                ':source' => $this->source,
                ':body' => $this->response->body()
            ));
            return FALSE;
        }
        State_Exception::handler(isset($exception) ? $exception : new State_Exception($message));
    }

    /**
     * 根据指定类型，提取用户传入参数，转换为对应类型
     * @param   $param    参数名
     * @param   $type    类型信息
     * @return  FALSE   处理完毕
     */
    protected function convert($param, $type) {
        if(is_array($type)) {// 嵌入式对象
            $value = array();
            foreach($type as $p => $t) {
                $v = $this->convert($param.'.'.$p, $t);
                if($v === FALSE)
                    return FALSE;
                Arr::set_path($value, $p, $v);
            }
            return $value;
        } else {// 单值对象
            $value = $this->request->param(strtr($param, '.', '_'));
            if(!Valid::not_empty($value))
                return $this->handler("invalid parameters. $param:[$value]");
            switch($type) {// 数据类型检查及转换
                case 'int':
                    if(!Valid::digit($value))
                        return $this->handler("invalid parameters. $param:[$value]");
                    return (int)$value;
                case 'float':
                    if(!Valid::numeric($value))
                        return $this->handler("invalid parameters. $param:[$value]");
                    return (float)$value;
                case 'bool':
                    if(Valid::regex($value, '/^(true|1|yes|on)$/iD'))
                        return 1;
                    elseif(Valid::regex($value, '/^(false|0|no|off)$/iD'))
                        return 0;
                    else
                        return $this->handler("invalid parameters. $param:[$value]");
                case 'string':
                default:
                    return (string)$value;
            }
        }
    }

    protected $source;
    protected $domain;
    protected $id;
    protected $path;

    public function before() {
        $this->source = __(':client->:uri?:params', array(
            ':client' => Request::$client_ip,
            ':uri' => $this->request->controller().'/'.$this->request->action(),
            ':params' => http_build_query($this->request->param())
        ));
        // 请求日志
        Kohana::info('[:source] request', array(':source' => $this->source));
        // 领域对象
        $this->domain = $this->request->param('domain');
        // ID
        $this->id = $this->convert('id', 'int');
        // 路径
        $path = $this->request->param('path');
        if(!Valid::not_empty($path) || !Valid::regex($path, '/^[a-z0-9.]++$/iD'))
            return $this->handler("invalid parameters. path:[$path]");
        $this->path = $path;
    }

    public function after() {
        $datatype = $this->request->param('datatype', 'json');
        if($datatype == 'json') {
            $this->response->headers('Content-Type', 'application/json; charset='.Kohana::$charset);
            $this->response->body(json_encode($this->model()));
            // 响应日志
            Kohana::info('[:source] response: :body', array(
                ':source' => $this->source,
                ':body' => $this->response->body()
            ));
        } else {
            $this->response->body(Debug::vars($this->model()).View::factory('kohana/profiler'));
        }
    }

}
