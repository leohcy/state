<?php defined('SYSPATH') or die('No direct script access.');

class State_Schedule {

    /**
     * 调用定时服务接口，设定定时任务
     * @param   $params    请求参数
     * @param   $uri    设定的回调URI
     * @param   $id    ID
     * @param   $path    路径
     * @param   $field    使用哪类参数传递
     * @param   $translate    转换为哪类参数传递
     * @return  TRUE/FALSE
     */
    public static function schedule($params, $uri, $id, $path, $field = 'next', $translate = 'value') {
        if(!isset($params['duration']) || !Valid::digit($params['duration']))
            return;
        $duration = (int)$params['duration'];
        $value = array();
        foreach($params as $key => $val) {
            if(Text::start_with($key, $field)) {
                $key = __($key, array(
                    '_' => '.',
                    $field => $translate
                ));
                $value[$key] = $val;
            }
        }
        $value = json_encode($value);
        $response = State_HttpClient::instance()->get(array(
            'uri' => $uri,
            'id' => $id,
            'path' => $path,
            'value' => $value,
            'delay' => $duration
        ), "$uri:$id:$path");
        $success = $response !== FALSE;
        $level = $success ? 'info' : 'warning';
        Kohana::$level("schedule success:[:success] uri::uri id::id path::path value::value delay::delay", array(
            ':success' => $success ? 'TRUE' : 'FALSE',
            ':uri' => $uri,
            ':id' => $id,
            ':path' => $path,
            ':value' => $value,
            ':delay' => $duration,
        ));
        return $success;
    }

}
