<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Modify extends Controller {

    public function action_value() {
        $this->model('controller', $this->request->controller());
        $this->model('action', $this->request->action());
        $this->model('params', $this->request->param());
    }

    public function action_array() {
        $this->model('controller', $this->request->controller());
        $this->model('action', $this->request->action());
        $this->model('params', $this->request->param());
    }

}
