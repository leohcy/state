<?php

/**
 * When developing your application, it is highly recommended to enable notices
 * and strict warnings. Enable them by using: E_ALL | E_STRICT
 * In a production environment, it is safe to ignore notices and strict warnings.
 * Disable them by using: E_ALL ^ E_NOTICE
 * When using a legacy application with PHP >= 5.3, it is recommended to disable
 * deprecated notices. Disable with: E_ALL & ~E_DEPRECATED
 */
error_reporting(E_ALL | E_STRICT);

define('KOHANA_START_TIME', microtime(TRUE));
define('KOHANA_START_MEMORY', memory_get_usage());

$application = 'application';
$system = 'system';
define('DOCROOT', realpath(dirname(__FILE__)).DIRECTORY_SEPARATOR);
if(!is_dir($application) AND is_dir(DOCROOT.$application))
    $application = DOCROOT.$application;
if(!is_dir($system) AND is_dir(DOCROOT.$system))
    $system = DOCROOT.$system;
define('APPPATH', realpath($application).DIRECTORY_SEPARATOR);
define('SYSPATH', realpath($system).DIRECTORY_SEPARATOR);
unset($application, $system);

require SYSPATH.'classes/kohana.php';
require APPPATH.'bootstrap.php';
echo Request::factory()->execute()->send_headers()->body();
