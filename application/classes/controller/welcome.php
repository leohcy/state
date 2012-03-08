<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Welcome extends Controller {

    public function action_index() {
        $this->response->body(View::factory('kohana/profiler'));
    }

} // End Welcome
