<?php defined('SYSPATH') or die('No direct script access.');

date_default_timezone_set('Asia/Shanghai');
setlocale(LC_ALL, 'zh_CN.utf-8');
Kohana::init(array('errors' => TRUE, 'profile' => TRUE));
Kohana::$log->attach(new Log_File(APPPATH.'logs'));
Kohana::$config->attach(new Config_File);
Route::set('default', '(<controller>(/<action>))')->defaults(array('controller' => 'welcome', 'action' => 'index'));
