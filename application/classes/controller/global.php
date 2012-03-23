<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Global extends Controller_Common {

    protected $path;
    protected $prop;
    protected $where;

    public function before() {
        if(parent::before() === FALSE)
            return FALSE;
        // 路径
        $this->path = $this->request->param('path');
        if(!Valid::not_empty($this->path) || !Valid::regex($this->path, '/^(?![.])[a-z0-9.]++(?<![.])$/iD'))
            return $this->handler("invalid path:[{$this->path}] wrong format");
        // 读取配置
        $this->prop = Kohana::$config->load('property.'.$this->domain.'.'.$this->path);
        if(!isset($this->prop))
            return $this->handler("invalid path:[{$this->path}] not allowed");
        // 条件
        $this->where = $this->convert('where', $this->prop);
        if($this->where === FALSE)
            return FALSE;
        if(is_array($this->where)) {
            $this->where = Arr::flatten_path($this->where);
            foreach($this->where as $path => $value) {
                $this->where[$this->path.'.'.$path] = $value;
                unset($this->where[$path]);
            }
        }
    }

    public function action_value() {
        return $this->action_count();
    }

    public function action_list() {

    }

    public function action_table() {

    }

    public function action_count() {
        // 从数据库查询数量
        try {
            $count = State_MongoDB::count($this->domain, $this->where);
            $this->model('success', TRUE);
            $this->model('count', $count);
        } catch (Exception $e) {
            return $this->handler('cannot connect to mongodb', $e, TRUE);
        }
    }

}
