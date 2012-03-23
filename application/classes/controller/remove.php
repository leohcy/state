<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Remove extends Controller_Common {

    protected $id;
    protected $path;
    protected $prop;
    protected $time;

    public function before() {
        if(parent::before() === FALSE)
            return FALSE;
        // ID
        $this->id = $this->convert('id', 'int');
        if($this->id === FALSE)
            return FALSE;
        // 路径
        $this->path = $this->request->param('path');
        if(!Valid::not_empty($this->path) || !Valid::regex($this->path, '/^(?![.])[a-z0-9.]++(?<![.])$/iD'))
            return $this->handler("invalid path:[{$this->path}] wrong format");
        // 读取配置
        $this->prop = Kohana::$config->load('property.'.$this->domain.'.'.$this->path);
        if(!isset($this->prop))
            return $this->handler("invalid path:[{$this->path}] not allowed");
        // 时间戳
        $time = $this->request->param('time');
        if(!Valid::not_empty($time))
            $time = time();
        elseif(!Valid::digit($time))
            return $this->handler("invalid parameters. time:[$time]");
        $this->time = (int)$time;
    }

    public function action_value() {
        // 从数据库删除指定路径
        try {
            $update = array('$unset' => array($this->path => 1));
            $result = State_MongoDB::findAndModify($this->domain, $this->id, $this->path, $this->time, $update);
            $this->model('success', $result['ok'] == 1);
            $old = Arr::path($result, 'value.'.$this->path);
            $this->model('changed', $old != NULL);
            $this->model('existed', (bool)Arr::path($result, 'lastErrorObject.updatedExisting'));
        } catch (Exception $e) {
            return $this->handler('cannot connect to mongodb', $e, TRUE);
        }
        if(!$this->model('success'))
            return $this->handler('cannot remove domain property', new State_Exception(Arr::path($result, 'lastErrorObject.err'), NULL, Arr::path($result, 'lastErrorObject.code')), TRUE);
        // 新增对象
        if(!$this->model('existed'))
            Kohana::notice("[{$this->source}] new domain object detected: {$this->domain}#{$this->id}");
        // 数据没变化则停止后续处理
        if(!$this->model('changed'))
            return;
        // 通知
        State_Notice::notice($this->domain, $this->id, $this->path, $old, $this->time, State_Notice::REMOVE);
        // 如果有DBRef变化，则修改，并通知
        if(is_array($old)) {
            $deleted = array();
            $this->diffDBRef($old, NULL, $deleted);
            foreach($deleted as $info) {
                // 更新该DBRef的反向引用
                $path = 'by.'.$this->domain.'_'.strtr($info[0], '.', '_');
                $update = array('$pull' => array($path => $this->id));
                $result = State_MongoDB::update($info[1]['$ref'], $info[1]['$id'], $update);
                if($result['ok'] == 1) {
                    // 通知
                    State_Notice::notice($info[1]['$ref'], $info[1]['$id'], $path, $this->id, $this->time, State_Notice::PULL);
                }
            }
        }
    }

    public function action_array() {
        // 类型检查
        if(!is_array($this->prop) || !isset($this->prop['$array']))
            return $this->handler('invalid request. use uri: /<domain>/remove/value');
        // 条件
        $where = $this->convert('where', $this->prop);
        if($where === FALSE)
            return FALSE;
        // 从数据库中按条件删除数组中的值
        try {
            if(is_array($where))
                $where = Arr::flatten_path($where);
            $update = array('$pull' => array($this->path => $where));
            $result = State_MongoDB::findAndModify($this->domain, $this->id, $this->path, $this->time, $update);
            $this->model('success', $result['ok'] == 1);
            $old = Arr::path($result, 'value.'.$this->path);
            if(!is_array($old)) {
                $changed = FALSE;
            } else {
                $removed = Arr::contain($old, $where);
                $changed = $removed !== FALSE;
            }
            $this->model('changed', $changed);
            $this->model('existed', (bool)Arr::path($result, 'lastErrorObject.updatedExisting'));
        } catch (Exception $e) {
            return $this->handler('cannot connect to mongodb', $e, TRUE);
        }
        if(!$this->model('success'))
            return $this->handler('cannot remove domain property', new State_Exception(Arr::path($result, 'lastErrorObject.err'), NULL, Arr::path($result, 'lastErrorObject.code')), TRUE);
        // 新增对象
        if(!$this->model('existed'))
            Kohana::notice("[{$this->source}] new domain object detected: {$this->domain}#{$this->id}");
        // 数据没变化则停止后续处理
        if(!$this->model('changed'))
            return;
        // 通知
        State_Notice::notice($this->domain, $this->id, $this->path, $removed, $this->time, State_Notice::PULL);
        // 如果有DBRef变化，则修改，并通知
        if(is_array($removed)) {
            $deleted = array();
            $this->diffDBRef($removed, NULL, $deleted);
            foreach($deleted as $info) {
                // 更新该DBRef的反向引用
                $path = 'by.'.$this->domain.'_'.strtr($info[0], '.', '_');
                $update = array('$pull' => array($path => $this->id));
                $result = State_MongoDB::update($info[1]['$ref'], $info[1]['$id'], $update);
                if($result['ok'] == 1) {
                    // 通知
                    State_Notice::notice($info[1]['$ref'], $info[1]['$id'], $path, $this->id, $this->time, State_Notice::PULL);
                }
            }
        }
    }

}
