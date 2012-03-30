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
            if(Text::start_with(key($this->where), '$')) {
                $this->where = array($this->path => $this->where);
            } else {
                foreach($this->where as $path => $value) {
                    $this->where[$this->path.'.'.$path] = $value;
                    unset($this->where[$path]);
                }
            }
        } else {
            $this->where = array($this->path => $this->where);
        }
    }

    public function action_value() {
        return $this->action_count();
    }

    public function action_list() {
        list($skip, $limit) = $this->extraParams();
        // 从数据库查询id列表
        try {
            list($total, $result) = State_MongoDB::query($this->domain, $this->where, NULL, $skip, $limit);
            $this->model('success', TRUE);
            $this->model('list', $result);
            $this->model('total', $total);
            $this->model('skip', $skip);
            $this->model('limit', $limit);
        } catch (Exception $e) {
            return $this->handler('cannot connect to mongodb', $e, TRUE);
        }
    }

    public function action_table() {
        list($skip, $limit) = $this->extraParams();
        // 从数据库查询结果列表
        try {
            list($total, $result) = State_MongoDB::query($this->domain, $this->where, $this->path, $skip, $limit);
            $this->model('success', TRUE);
            $this->model('table', $result);
            $this->model('total', $total);
            $this->model('skip', $skip);
            $this->model('limit', $limit);
        } catch (Exception $e) {
            return $this->handler('cannot connect to mongodb', $e, TRUE);
        }
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

    private function extraParams() {
        $page = $this->request->param('page');
        $limit = $this->request->param('limit');
        if($limit == 'no') {
            $skip = NULL;
            $limit = NULL;
        } else {
            if(Valid::not_empty($page) && Valid::digit($page))
                $page = (int)$page;
            else
                $page = 1;
            if(Valid::not_empty($limit) && Valid::digit($limit))
                $limit = (int)$limit;
            else
                $limit = 20;
            $skip = ($page - 1) * $limit;
            if($skip < 0)
                $skip = 0;
        }
        return array(
            $skip,
            $limit
        );
    }

}
