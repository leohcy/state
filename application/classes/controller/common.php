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
        $this->domain = $this->request->param('domain');
        $id = $this->request->param('id');
        if(!Valid::not_empty($id) || !Valid::digit($id))
            return $this->handler("invalid parameters. id:[$id]");
        $this->id = (int)$id;
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
