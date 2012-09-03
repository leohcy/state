<?php defined('SYSPATH') or die('No direct script access.');

return array(
    'servers' => array(
        array(
            'server' => '192.168.101.229:8080',
            'weight' => 1
        ),
        array(
            'server' => '192.168.151.159:8080',
            'weight' => 1
        )
    ),
    'url' => '/crontab.jsp',
    'params' => array('method' => 'SetCrontab')
);
