<?php defined('SYSPATH') or die('No direct script access.');

return array(
    'profile' => array('presence' => array(
            'online' => array(
                'away' => array('type' => 'bool'),
                'busy' => array()
            ),
            'offline' => array('type' => array(
                    'a' => 'int',
                    'b' => 'float',
                    'c.d' => 'bool',
                    'e.f' => 'string'
                ))
        )),
    'video' => array()
);
