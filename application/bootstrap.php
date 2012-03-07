<?php defined('SYSPATH') or die('No direct script access.');

Kohana::init();
Kohana::$log->attach(new Log_File(APPPATH.'logs'));
Kohana::$config->attach(new Config_File);
Route::set('default', '(<controller>(/<action>(/<id>)))')->defaults(array('controller' => 'welcome', 'action' => 'index'));
