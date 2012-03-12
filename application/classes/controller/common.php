<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Common extends Controller {

    private $_model = array();
    private $_view = NULL;

    /**
     * Gets or sets model.
     * @param   mixed  $key    Key or key value pairs to set
     * @param   string $value  Value to set to a key
     * @return  mixed
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
     * Get or set view name.
     * @param   mixed  $view    View name to set
     * @return  mixed
     */
    protected function view($view = NULL) {
        if($view === NULL)
            return $this->_view;
        $this->_view = $view;
        return $this;
    }

    public function after() {
        $datatype = $this->request->param('datatype', 'json');
        switch($datatype) {
            case 'debug':
                $body = Debug::vars($this->model());
                $body .= View::factory('kohana/profiler');
                break;
            case 'html':
                if($this->view() === NULL)
                    $this->view($this->request->controller().'/'.$this->request->action());
                $body = View::factory($this->view())->set($this->model());
                break;
            case 'json':
            default:
                $this->response->headers('Content-Type', 'application/json; charset='.Kohana::$charset);
                $body = json_encode($this->model());
                break;
        }
        $this->response->body($body);
    }

}
