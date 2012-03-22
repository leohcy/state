<?php defined('SYSPATH') or die('No direct script access.');

class State_Notice {

    const UPDATE = 'update';
    const REMOVE = 'remove';
    const PUSH = 'push';
    const PULL = 'pull';

    /**
     * 向通知消息队列投递消息，进行通知
     * @param   $domain    领域对象
     * @param   $id    ID
     * @param   $path    路径
     * @param   $value    值
     * @param   $time    时间戳
     * @param   $type    操作类型
     * @return  TRUE/FALSE
     */
    public static function notice($domain, $id, $path, $value, $time, $type) {
        $data = json_encode(array(
            'domain' => $domain,
            'id' => $id,
            'path' => $path,
            'value' => $value,
            'time' => $time,
            'type' => $type
        ));
        $success = State_Kestrel::instance()->set($data);
        $level = $success ? 'info' : 'warning';
        Kohana::$level("notice success:[:success] content: :data", array(
            ':success' => $success ? 'TRUE' : 'FALSE',
            ':data' => $data
        ));
        return $success;
    }

}
