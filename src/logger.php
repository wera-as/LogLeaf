<?php

class Logger
{
    private $file;
    private $timestampFormat;

    public function __construct($filename, $timestampFormat = 'Y-m-d H:i:s')
    {
        if (empty($filename)) {
            throw new InvalidArgumentException('Filename cannot be empty.');
        }

        $this->file = $filename;
        $this->timestampFormat = $timestampFormat;
    }

    public function setTimestampFormat($format)
    {
        $this->timestampFormat = $format;
    }

    public function putLog($insert)
    {
        $timestamp = date($this->timestampFormat) . " : ";
        $logEntry = $timestamp . $insert . PHP_EOL;

        if (file_put_contents($this->file, $logEntry, FILE_APPEND) === false) {
            throw new RuntimeException("Failed to write to log file {$this->file}");
        }
    }

    public function getLog()
    {
        $content = @file_get_contents($this->file);
        if ($content === false) {
            throw new RuntimeException("Failed to read log file {$this->file}");
        }

        return $content;
    }
}
