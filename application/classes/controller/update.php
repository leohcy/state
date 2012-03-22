<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Update extends Controller_Common {

    protected $id;
    protected $path;
    protected $prop;
    protected $value;
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
        if(!Valid::not_empty($this->path) || !Valid::regex($this->path, '/^[a-z0-9.]++$/iD'))
            return $this->handler("invalid path:[{$this->path}] wrong format");
        // 读取配置
        $this->prop = Kohana::$config->load('property.'.$this->domain.'.'.$this->path);
        if(!isset($this->prop))
            return $this->handler("invalid path:[{$this->path}] not allowed");
        // 数据
        $this->value = $this->convert('value', $this->prop);
        if($this->value === FALSE)
            return FALSE;
        // 时间戳
        $time = $this->request->param('time');
        if(!Valid::not_empty($time))
            $time = time();
        elseif(!Valid::digit($time))
            return $this->handler("invalid parameters. time:[$time]");
        $this->time = (int)$time;
    }

    public function action_value() {
        // 类型检查
        if(is_array($this->prop) && isset($this->prop['$array']))
            return $this->handler('invalid request. use uri: /<domain>/update/array');
        // 插入数据库
        try {
            $update = array('$set' => array($this->path => $this->value));
            $result = State_MongoDB::findAndModify($this->domain, $this->id, $this->path, $this->time, $update);
            $this->model('success', $result['ok'] == 1);
            $old = Arr::path($result, 'value.'.$this->path);
            $this->model('changed', $old != $this->value);
            $this->model('existed', (bool)Arr::path($result, 'lastErrorObject.updatedExisting'));
        } catch (Exception $e) {
            return $this->handler('cannot connect to mongodb', $e, TRUE);
        }
        if(!$this->model('success'))
            return $this->handler('cannot update domain property', new State_Exception(Arr::path($result, 'lastErrorObject.err'), NULL, Arr::path($result, 'lastErrorObject.code')), TRUE);
        // 新增对象
        if(!$this->model('existed'))
            Kohana::notice("[{$this->source}] new domain object detected: {$this->domain}#{$this->id}");
        // 定时
        State_Schedule::schedule($this->request->param(), "{$this->domain}/update/value", $this->id, $this->path);
        // 数据没变化则停止后续处理
        if(!$this->model('changed'))
            return;
        // 通知
        State_Notice::notice($this->domain, $this->id, $this->path, $this->value, $this->time, State_Notice::UPDATE);
        // 如果有DBRef变化，则修改，并通知
        if(is_array($old)) {
            $deleted = array();
            $this->diffDBRef($old, $this->value, $deleted);
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
        if(is_array($this->value)) {
            $added = array();
            $this->diffDBRef($this->value, $old, $added);
            foreach($added as $info) {
                // 更新该DBRef的反向引用
                $path = 'by.'.$this->domain.'_'.strtr($info[0], '.', '_');
                $update = array('$addToSet' => array($path => $this->id));
                $result = State_MongoDB::update($info[1]['$ref'], $info[1]['$id'], $update);
                if($result['ok'] == 1) {
                    // 新增对象
                    if(!$result['updatedExisting'])
                        Kohana::notice("[{$this->source}] new domain object detected: {$info[1]['$ref']}#{$info[1]['$id']}");
                    // 通知
                    State_Notice::notice($info[1]['$ref'], $info[1]['$id'], $path, $this->id, $this->time, State_Notice::PUSH);
                }
            }
        }
    }

    public function action_array() {
        // 类型检查
        if(!is_array($this->prop) || !isset($this->prop['$array']))
            return $this->handler('invalid request. use uri: /<domain>/update/value');
        // 插入数据库
        try {
            $update = array('$addToSet' => array($this->path => $this->value));
            $result = State_MongoDB::findAndModify($this->domain, $this->id, $this->path, $this->time, $update);
            $this->model('success', $result['ok'] == 1);
            $old = Arr::path($result, 'value.'.$this->path);
            $this->model('changed', !is_array($old) ? TRUE : !in_array($this->value, $old));
            $this->model('existed', (bool)Arr::path($result, 'lastErrorObject.updatedExisting'));
        } catch (Exception $e) {
            return $this->handler('cannot connect to mongodb', $e, TRUE);
        }
        if(!$this->model('success'))
            return $this->handler('cannot update domain property', new State_Exception(Arr::path($result, 'lastErrorObject.err'), NULL, Arr::path($result, 'lastErrorObject.code')), TRUE);
        // 新增对象
        if(!$this->model('existed'))
            Kohana::notice("[{$this->source}] new domain object detected: {$this->domain}#{$this->id}");
        // 定时
        State_Schedule::schedule($this->request->param(), "{$this->domain}/remove/array", $this->id, $this->path, 'value', 'where');
        // 数据没变化则停止后续处理
        if(!$this->model('changed'))
            return;
        // 通知
        State_Notice::notice($this->domain, $this->id, $this->path, $this->value, $this->time, State_Notice::PUSH);
        // 如果有DBRef变化，则修改，并通知
        if(is_array($this->value)) {
            $added = array();
            $this->diffDBRef($this->value, NULL, $added);
            foreach($added as $info) {
                // 更新该DBRef的反向引用
                $path = 'by.'.$this->domain.'_'.strtr($info[0], '.', '_');
                $update = array('$addToSet' => array($path => $this->id));
                $result = State_MongoDB::update($info[1]['$ref'], $info[1]['$id'], $update);
                if($result['ok'] == 1) {
                    // 新增对象
                    if(!$result['updatedExisting'])
                        Kohana::notice("[{$this->source}] new domain object detected: {$info[1]['$ref']}#{$info[1]['$id']}");
                    // 通知
                    State_Notice::notice($info[1]['$ref'], $info[1]['$id'], $path, $this->id, $this->time, State_Notice::PUSH);
                }
            }
        }
    }

    public function action_incr() {
        // 类型检查
        if($this->prop != 'incr')
            return $this->handler('invalid request. use uri: /<domain>/update/value');
        // 插入数据库
        try {
            $update = array('$inc' => array($this->path => $this->value));
            $result = State_MongoDB::findAndModify($this->domain, $this->id, $this->path, $this->time, $update, TRUE);
            $this->model('success', $result['ok'] == 1);
            $this->model('newValue', Arr::path($result, 'value.'.$this->path));
            $this->model('existed', (bool)Arr::path($result, 'lastErrorObject.updatedExisting'));
        } catch (Exception $e) {
            return $this->handler('cannot connect to mongodb', $e, TRUE);
        }
        if(!$this->model('success'))
            return $this->handler('cannot update domain property', new State_Exception(Arr::path($result, 'lastErrorObject.err'), NULL, Arr::path($result, 'lastErrorObject.code')), TRUE);
        // 新增对象
        if(!$this->model('existed'))
            Kohana::notice("[{$this->source}] new domain object detected: {$this->domain}#{$this->id}");
        // 定时
        State_Schedule::schedule($this->request->param(), "{$this->domain}/update/decr", $this->id, $this->path, 'value');
        // 通知
        State_Notice::notice($this->domain, $this->id, $this->path, $this->model('newValue'), $this->time, State_Notice::UPDATE);
    }

    public function action_decr() {
        $this->value = -$this->value;
        return $this->action_incr();
    }

}
