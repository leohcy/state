<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Welcome extends Controller {

    public function action_index() {
        $this->response->body(View::factory('kohana/profiler'));
        Kohana::info(':ctrl -> :act has been proceed.', array(':ctrl' => $this->request->controller(), ':act' => $this->request->action()));
        Kohana::warning('', array(), new Exception("fake alert"), TRUE);
    }

} // End Welcome
