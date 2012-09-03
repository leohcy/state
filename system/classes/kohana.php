<?php defined('SYSPATH') or die('No direct script access.');

class Kohana {

    public static $log;
    public static $config;
    public static $base_url = '/';
    public static $index_file = 'index.php';
    public static $profiling = TRUE;
    public static $errors = TRUE;
    public static $content_type = 'text/html';
    public static $charset = 'utf-8';
    public static $safe_mode = FALSE;
    public static $magic_quotes = FALSE;
    public static $shutdown_errors = array(
        E_PARSE,
        E_ERROR,
        E_USER_ERROR
    );
    protected static $_init = FALSE;
    protected static $_paths = array(
        APPPATH,
        SYSPATH
    );

    /**
     * Initializes the environment:
     * - Disables magic_quotes_gpc
     * - Set global settings
     * - Sanitizes GET, POST, and COOKIE variables
     * - Converts GET, POST, and COOKIE variables to the global character set
     * The following settings can be set:
     * Type      | Setting    | Description                                    | Default Value
     * ----------|------------|------------------------------------------------|---------------
     * `string`  | base_url   | The base URL for your application.  This should be the *relative* path from your DOCROOT to your `index.php` file, in other words, if Kohana is in a subfolder, set this to the subfolder name, otherwise leave it as the default.  **The leading slash is required**, trailing slash is optional.   | `"/"`
     * `string`  | index_file | The name of the [front controller](http://en.wikipedia.org/wiki/Front_Controller_pattern).  This is used by Kohana to generate relative urls like [HTML::anchor()] and [URL::base()]. This is usually `index.php`.  To [remove index.php from your urls](tutorials/clean-urls), set this to `FALSE`. | `"index.php"`
     * `boolean` | errors     | Should Kohana catch PHP errors and uncaught Exceptions and show the `error_view`. See [Error Handling](kohana/errors) for more info. <br /> <br /> Recommended setting: `TRUE` while developing, `FALSE` on production servers. | `TRUE`
     * `boolean` | profile    | Whether to enable the [Profiler](kohana/profiling). <br /> <br />Recommended setting: `TRUE` while developing, `FALSE` on production servers. | `TRUE`
     *
     * @throws  Kohana_Exception
     * @param   array   Array of settings.  See above.
     * @return  void
     */
    public static function init(array $settings = NULL) {
        if(Kohana::$_init)
            return;
        Kohana::$_init = TRUE;
        ob_start();
        if(isset($settings['base_url']))
            Kohana::$base_url = rtrim($settings['base_url'], '/').'/';
        if(isset($settings['index_file']))
            Kohana::$index_file = trim($settings['index_file'], '/');
        if(isset($settings['profile']))
            Kohana::$profiling = (bool)$settings['profile'];
        if(isset($settings['errors']))
            Kohana::$errors = (bool)$settings['errors'];
        spl_autoload_register(array(
            'Kohana',
            'auto_load'
        ));
        if(Kohana::$errors === TRUE) {
            set_exception_handler(array(
                'Kohana_Exception',
                'handler'
            ));
            set_error_handler(array(
                'Kohana',
                'error_handler'
            ));
        }
        register_shutdown_function(array(
            'Kohana',
            'shutdown_handler'
        ));
        if(function_exists('mb_internal_encoding'))
            mb_internal_encoding(Kohana::$charset);
        Kohana::$safe_mode = (bool) ini_get('safe_mode');
        Kohana::$magic_quotes = (bool) get_magic_quotes_gpc();
        $_GET = Kohana::sanitize($_GET);
        $_POST = Kohana::sanitize($_POST);
        $_COOKIE = Kohana::sanitize($_COOKIE);
        Kohana::$log = Log::instance();
        Kohana::$config = new Config;
    }

    /**
     * Provides auto-loading support of classes that follow Kohana's [class
     * naming conventions](kohana/conventions#class-names-and-file-location).
     * Class names are converted to file names by making the class name
     * lowercase and converting underscores to slashes
     * You should never have to call this function, as simply calling a class
     * will cause it to be called.
     * This function must be enabled as an autoloader in the bootstrap
     * @param   string   class name
     * @return  boolean
     */
    public static function auto_load($class) {
        try {
            $file = str_replace('_', '/', strtolower($class));
            if($path = Kohana::find_file('classes', $file)) {
                require $path;
                return TRUE;
            }
            return FALSE;
        } catch (Exception $e) {
            Kohana_Exception::handler($e);
            die ;
        }
    }

    /**
     * Searches for a file in the [Cascading Filesystem](kohana/files), and
     * returns the path to the file that has the highest precedence, so that it
     * can be included.
     * When the `$array` flag is set to true, an array of all the files that match
     * that path in the [Cascading Filesystem](kohana/files) will be returned.
     * These files will return arrays which must be merged together.
     *
     * If no extension is given, the default extension (`.php`) will be used.
     * @param   string   directory name (views, i18n, classes, extensions, etc.)
     * @param   string   filename with subdirectory
     * @param   string   extension to search for
     * @param   boolean  return an array of files?
     * @return  array    a list of files when $array is TRUE
     * @return  string   single file path
     */
    public static function find_file($dir, $file, $ext = NULL, $array = FALSE) {
        $ext = ($ext === NULL) ? '.php' : ($ext ? ".{$ext}" : '');
        $path = $dir.DIRECTORY_SEPARATOR.$file.$ext;
        if(Kohana::$profiling === TRUE AND class_exists('Profiler', FALSE))
            $benchmark = Profiler::start('Kohana', __FUNCTION__);
        if($array) {
            $found = array();
            foreach(array_reverse(Kohana::$_paths) as $dir)
                if(is_file($dir.$path))
                    $found[] = $dir.$path;
        } else {
            $found = FALSE;
            foreach(Kohana::$_paths as $dir) {
                if(is_file($dir.$path)) {
                    $found = $dir.$path;
                    break;
                }
            }
        }
        if(isset($benchmark))
            Profiler::stop($benchmark);
        return $found;
    }

    /**
     * Loads a file within a totally empty scope and returns the output
     * @param   string
     * @return  mixed
     */
    public static function load($file) {
        return
        include $file;
    }

    /**
     * PHP error handler, converts all errors into ErrorExceptions. This handler
     * respects error_reporting settings.
     * @throws  ErrorException
     * @return  TRUE
     */
    public static function error_handler($code, $error, $file = NULL, $line = NULL) {
        if(error_reporting() & $code)
            throw new ErrorException($error, $code, 0, $file, $line);
        return TRUE;
    }

    /**
     * Catches errors that are not caught by the error handler, such as E_PARSE.
     * @uses    Kohana_Exception::handler
     * @return  void
     */
    public static function shutdown_handler() {
        if(!Kohana::$_init)
            return;
        if(Kohana::$errors AND $error = error_get_last() AND in_array($error['type'], Kohana::$shutdown_errors)) {
            ob_get_level() and ob_clean();
            Kohana_Exception::handler(new ErrorException($error['message'], $error['type'], 0, $error['file'], $error['line']));
            exit(1);
        }
    }

    /**
     * Recursively sanitizes an input variable:
     * - Strips slashes if magic quotes are enabled
     * - Normalizes all newlines to LF
     * @param   mixed  any variable
     * @return  mixed  sanitized variable
     */
    public static function sanitize($value) {
        if(is_array($value) OR is_object($value)) {
            foreach($value as $key => $val)
                $value[$key] = Kohana::sanitize($val);
        } elseif(is_string($value)) {
            if(Kohana::$magic_quotes === TRUE)
                $value = stripslashes($value);
            if(strpos($value, "\r") !== FALSE)
                $value = str_replace(array(
                    "\r\n",
                    "\r"
                ), "\n", $value);
        }
        return $value;
    }

    /**
     * Adds a message to the log with DEBUG level.
     * @param   string  message body
     * @param   array   values to replace in the message
     */
    public static function debug($message, array $values = NULL) {
        Kohana::$log->add(Log::DEBUG, $message, $values);
    }

    /**
     * Adds a message to the log with INFO level.
     * @param   string  message body
     * @param   array   values to replace in the message
     */
    public static function info($message, array $values = NULL) {
        Kohana::$log->add(Log::INFO, $message, $values);
    }

    /**
     * Adds a message to the log with NOTICE level.
     * @param   string  message body
     * @param   array   values to replace in the message
     * @param   Exception   exception to be handled
     */
    public static function notice($message, array $values = NULL, Exception $e = NULL) {
        if(isset($e))
            $message .= PHP_EOL.Kohana_Exception::text($e);
        Kohana::$log->add(Log::NOTICE, $message, $values);
    }

    /**
     * Adds a message to the log with WARNING level.
     * @param   string  message body
     * @param   array   values to replace in the message
     * @param   Exception   exception to be handled
     */
    public static function warning($message, array $values = NULL, Exception $e = NULL) {
        if(isset($e))
            $message .= PHP_EOL.Kohana_Exception::text($e);
        Kohana::$log->add(Log::WARNING, $message, $values);
    }

}

if(!function_exists('__')) {
    /**
     * Kohana translation function. The PHP function
     * [strtr](http://php.net/strtr) is used for replacing parameters.
     *    __('Welcome back, :user', array(':user' => $username));
     * @param   string  text to translate
     * @param   array   values to replace in the translated text
     * @return  string
     */
    function __($string, array $values = NULL) {
        return empty($values) ? $string : strtr($string, $values);
    }

}
