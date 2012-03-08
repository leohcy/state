<?php defined('SYSPATH') or die('No direct script access.');

class Log_File extends Log_Writer {

    protected $_filename;
    protected $_pattern;
    protected $_maxFileSize;
    protected $_maxLogFiles;

    /**
     * Creates a new file logger. Checks that the directory exists and
     * is writable.
     * @param   string  log directory
     * @param   string  log filename (default application.log)
     * @param   string  log pattern (default [:time] :level :file::line - :body)
     * @param   string  log maxFileSize in MB (default 10)
     * @param   string  log maxLogFiles (default 200)
     * @return  void
     */
    public function __construct($directory, $filename = 'application.log', $pattern = '[:time][:level][:file::line] :body', $maxFileSize = 10, $maxLogFiles = 200) {
        if (!is_dir($directory) OR !is_writable($directory))
            throw new Kohana_Exception('Directory :dir must be writable', array(':dir' => Debug::path($directory)));
        $this->_filename = realpath($directory).DIRECTORY_SEPARATOR.$filename;
        $this->_pattern = $pattern;
        $this->_maxFileSize = (int)$maxFileSize;
        if ($this->_maxFileSize < 1)
            $this->_maxFileSize = 1;
        $this->_maxLogFiles = (int)$maxLogFiles;
        if ($this->_maxLogFiles < 1)
            $this->_maxLogFiles = 1;
    }

    /**
     * Writes each of the messages into the log file.
     * @param   array   messages
     * @return  void
     */
    public function write(array $messages) {
        if (@filesize($this->_filename) > $this->_maxFileSize * 1024 * 1024)
            $this->rotate();
        $fp = @fopen($this->_filename, 'a');
        @flock($fp, LOCK_EX);
        foreach ($messages as $message) {
            $message[':level'] = $this->_log_levels[$message[':level']];
            @fwrite($fp, strtr($this->_pattern, $message).PHP_EOL);
        }
        @flock($fp, LOCK_UN);
        @fclose($fp);
    }

    protected function rotate() {
        for ($i = $this->_maxLogFiles; $i > 0; --$i) {
            $file = $this->_filename.'.'.$i;
            if (is_file($file)) {
                if ($i === $this->_maxLogFiles)
                    @unlink($file);
                else
                    @rename($file, $this->_filename.'.'.($i + 1));
            }
        }
        if (is_file($this->_filename))
            @rename($this->_filename, $this->_filename.'.1');
    }

}
