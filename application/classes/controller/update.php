<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Update extends Controller_Common {

    protected $value;
    protected $time;

    public function before() {
        if(parent::before() === FALSE)
            return FALSE;
        $value = $this->request->param('value');
        if(!Valid::not_empty($value))
            return $this->handler("invalid parameters. value:[$value]");
        $this->value = $value;
        $time = $this->request->param('time');
        if(!Valid::not_empty($time))
            $time = time();
        elseif(!Valid::digit($time))
            return $this->handler("invalid parameters. time:[$time]");
        $this->time = (int)$time;
    }

    public function action_value() {
        try {
            $result = State_MongoDB::primary()->command(array(
                'findAndModify' => $this->domain,
                'query' => array(
                    '_id' => $this->id,
                    '$or' => array(
                        array($this->path.'.time' => array('$exists' => FALSE)),
                        array($this->path.'.time' => array('$lt' => $this->time))
                    )
                ),
                'update' => array('$set' => array(
                        $this->path.'.value' => $this->value,
                        $this->path.'.time' => $this->time
                    )),
                'fields' => array($this->path.'.value' => 1),
                'upsert' => true
            ));
            $this->model('success', $result['ok'] == 1);
            $this->model('changed', Arr::path($result, 'value.'.$this->path.'.value') != $this->value);
            $this->model('existed', (bool)Arr::path($result, 'lastErrorObject.updatedExisting'));
        } catch (Exception $e) {
            return $this->handler('cannot connect to mongodb', $e, TRUE);
        }
        if(!$this->model('success'))
            return $this->handler('cannot update domain property', new State_Exception(Arr::path($result, 'lastErrorObject.err'), NULL, Arr::path($result, 'lastErrorObject.code')), TRUE);
    }

    public function action_array() {
        $this->model('controller', $this->request->controller());
        $this->model('action', $this->request->action());
        $this->model('params', $this->request->param());
    }

}
