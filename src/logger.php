<?php

/**
 * Logger class for handling different file types.
 */
class Logger
{
    /**
     * @var string File name
     */
    private $file;

    /**
     * @var string Timestamp format
     */
    private $timestampFormat;

    /**
     * @var string File type (txt or csv)
     */
    private $fileType;

    /**
     * @var array CSV column names
     */
    private $csvColumns;

    /**
     * Logger constructor.
     *
     * @param string $filename       File name
     * @param string $fileType       File type (txt or csv)
     * @param string $timestampFormat Timestamp format (optional)
     * @param array  $csvColumns     CSV column names (optional)
     * @throws InvalidArgumentException If the file name is empty
     */
    public function __construct($filename, $fileType, $timestampFormat = 'Y-m-d H:i:s', $csvColumns = [])
    {
        if (empty($filename)) {
            throw new InvalidArgumentException('Filename cannot be empty.');
        }

        if (!file_exists($filename)) {
            touch($filename);
        }

        $this->file = $filename;
        $this->timestampFormat = $timestampFormat;
        $this->fileType = $fileType;
        $this->csvColumns = $csvColumns;

        if ($this->fileType === 'csv' && !empty($this->csvColumns)) {
            $file = fopen($this->file, 'a');
            if (filesize($this->file) === 0) {
                fputcsv($file, $this->csvColumns);
            }
            fclose($file);
        }
    }

    /**
     * Set timestamp format.
     *
     * @param string $format Timestamp format
     */
    public function setTimestampFormat($format)
    {
        $this->timestampFormat = $format;
    }

    /**
     * Write log entry to file.
     *
     * @param mixed $insert Log entry data
     * @throws RuntimeException If failed to write to file
     */
    public function putLog($insert)
    {
        $timestamp = date($this->timestampFormat);
        if ($this->fileType === 'txt') {
            $logEntry = $timestamp . " : " . $insert . PHP_EOL;
            if (file_put_contents($this->file, $logEntry, FILE_APPEND) === false) {
                throw new RuntimeException("Failed to write to log file {$this->file}");
            }
        } elseif ($this->fileType === 'csv') {
            $file = fopen($this->file, 'a');
            $data = array_merge([$timestamp], $insert);
            $csvData = array_combine($this->csvColumns, $data);
            if (fputcsv($file, $csvData) === false) {
                throw new RuntimeException("Failed to write to CSV file {$this->file}");
            }
            fclose($file);
        }
    }

    /**
     * Get content of log file.
     *
     * @return string Content of log file
     * @throws RuntimeException If failed to read log file
     */
    public function getLog()
    {
        $content = @file_get_contents($this->file);
        if ($content === false) {
            throw new RuntimeException("Failed to read log file {$this->file}");
        }

        return $content;
    }
}
