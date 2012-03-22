<?php defined('SYSPATH') or die('No direct script access.');

return array(
    'servers' => array( array(
            'server' => '192.168.101.229:40551',
            'weight' => 1
        )),
    'url' => '/crontab/crontab.jsp',
    'params' => array('method' => 'SetCrontab')
);
