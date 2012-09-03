<?php defined('SYSPATH') or die('No direct script access.');

class State_Notice {

    const UPDATE = 'update';
    const REMOVE = 'remove';
    const PUSH = 'push';
    const PULL = 'pull';

    /**
     * 向通知消息队列投递消息，进行通知
     * @param   $type    操作类型
     * @param   $time    时间戳
     * @param   $domain    领域对象
     * @param   $id    ID
     * @param   $path    路径
     * @param   $value    值
     * @return  TRUE/FALSE
     */
    public static function notice($type, $time, $domain, $id, $path, $value, $value2 = NULL) {
        $data = array(
            'type' => $type,
            'time' => $time,
            'domain' => $domain,
            'id' => $id,
            'path' => $path
        );
        if($type == State_Notice::UPDATE) {
            $data['origin'] = $value;
            $data['newValue'] = $value2;
        } elseif($type == State_Notice::REMOVE) {
            $data['removed'] = $value;
        } elseif($type == State_Notice::PUSH) {
            $data['pushed'] = $value;
        } elseif($type == State_Notice::PULL) {
            $data['pulled'] = $value;
        }
        $data = json_encode($data);
        $success = State_Kestrel::instance()->set($data);
        $level = $success ? 'info' : 'warning';
        Kohana::$level("notice success:[:success] content: :data", array(
            ':success' => $success ? 'TRUE' : 'FALSE',
            ':data' => $data
        ));
        return $success;
    }

}
