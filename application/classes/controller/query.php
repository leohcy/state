<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Query extends Controller_Common {

    protected $single;
    protected $ids;
    protected $path;
    protected $prop;

    public function before() {
        if(parent::before() === FALSE)
            return FALSE;
        // ID
        $id = $this->request->param('id');
        if(Valid::not_empty($id)) {
            if(!Valid::digit($id))
                return $this->handler("invalid parameters. id:[$id] need int");
            $this->single = TRUE;
            $this->ids = array((int)$id);
        } else {
            $ids = $this->request->param('ids');
            if(!Valid::not_empty($ids))
                return $this->handler("invalid parameters. id or ids not found");
            if(!Valid::regex($ids, '/^(?!,)[0-9,]++(?<!,)$/iD'))
                return $this->handler("invalid parameters. ids:[$ids] need int list separated by comma");
            $this->single = FALSE;
            $this->ids = explode(',', $ids);
            foreach($this->ids as &$id)
                $id = (int)$id;
        }
        // 路径
        $this->path = $this->request->param('path');
        if(!Valid::not_empty($this->path) || !Valid::regex($this->path, '/^(?![.])[a-z0-9.]++(?<![.])$/iD'))
            return $this->handler("invalid path:[{$this->path}] wrong format");
        // 读取配置
        $this->prop = Kohana::$config->load('property.'.$this->domain.'.'.$this->path);
        if(!isset($this->prop))
            return $this->handler("invalid path:[{$this->path}] not allowed");
    }

    public function action_value() {
        // 从数据库查询指定路径
        try {
            $result = State_MongoDB::byIdList($this->domain, $this->ids, $this->path);
            $this->model('success', TRUE);
            foreach($result as &$doc)
                if(is_array($doc))
                    $doc = $this->standardization($doc, $this->prop);
            if($this->single)
                $this->model('value', Arr::get(array_values($result), 0), TRUE);
            else
                $this->model('values', $result);
        } catch (Exception $e) {
            return $this->handler('cannot connect to mongodb', $e, TRUE);
        }
    }

    public function action_array() {
        // 类型检查
        if(!is_array($this->prop) || !isset($this->prop['$array']))
            return $this->handler('invalid request. use uri: /<domain>/query/value');
        // 条件
        $where = $this->convert('where', $this->prop);
        if($where === FALSE)
            return FALSE;
        // 从数据库按条件查询数组中的值
        try {
            if(is_array($where)) {
                $where = Arr::flatten_path($where);
                $query = array($this->path => array('$elemMatch' => $where));
            } else {
                $query = array($this->path => $where);
            }
            $result = State_MongoDB::byIdList($this->domain, $this->ids, $this->path, $query);
            $this->model('success', TRUE);
            foreach($result as &$doc) {
                if(is_array($doc)) {
                    $doc = $this->standardization($doc, $this->prop);
                    // 只筛选查询的部分
                    $doc = Arr::contain($doc, $where);
                }
            }
            if($this->single)
                $this->model('value', Arr::get(array_values($result), 0), TRUE);
            else
                $this->model('values', $result);
        } catch (Exception $e) {
            return $this->handler('cannot connect to mongodb', $e, TRUE);
        }
    }

    private function standardization(array $array, array $prop) {
        if(empty($array)) {
            // 把[]转换为{}
            if(!isset($prop['$array']))
                return new stdClass;
            return $array;
        }
        foreach($array as $key => &$value)
            if(is_array($value))
                $value = $this->standardization($value, isset($prop[$key]) ? $prop[$key] : $prop);
        return $array;
    }

}
