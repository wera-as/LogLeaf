<?php

/** 
 * Logger class for handling different file types.
 */
class LogLeaf
{
    const ROTATE_DAY        =   'Monday';   // Rotate logs every Monday
    const LOG_PREFIX        =   'Week';     // Prefix for log names
    const MAX_LOG_DURATION  =   3 * 4;      // Keep logs for 3 months (assuming 4 weeks per month)
    const MAX_LOG_SIZE      =   26214400;   // 25 MB in bytes

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
     * @var int Week of the last rotation.
     */
    private $lastRotationWeek;

    /**
     * @var array Custom error messages
     */
    private $errorMessages = [
        'illegalExtension'          =>  'Invalid file extension. Allowed extensions are:',
        'writeFailed'               =>  'Failed to write to log file %s',
        'readFailed'                =>  'Failed to read log file %s',
        'browserDetectionFailed'    =>  'Error: Browser detection failed',
        'osDetectionFailed'         =>  'Error: OS detection failed'
    ];

    /**
     * Logger constructor.
     *
     * @param string $filename              File name
     * @param string $fileType              File type (txt or csv)
     * @param string $timestampFormat       Timestamp format (optional)
     * @param array  $csvColumns            CSV column names (optional)
     * @param bool   $logIP                 Whether to log IP address (optional)
     * @param bool   $logBrowserOS          Whether to log Browser and OS (optional)
     * @throws InvalidArgumentException If the file name is empty
     */
    public function __construct($filename, $fileType, $timestampFormat = 'Y-m-d H:i:s', $csvColumns = [], $logIP = false, $logBrowserOS = false)
    {
        $defaultFilename = 'logleaf_log.txt';

        $filename = empty($filename) ? $defaultFilename : $filename;
        $allowedExtensions = ['txt', 'csv', 'tsv'];
        $fileExtension = pathinfo($filename, PATHINFO_EXTENSION);
    
        if (!in_array($fileExtension, $allowedExtensions)) {
            throw new InvalidArgumentException($this->errorMessages['illegalExtension'] . implode(', ', $allowedExtensions));
        }

        if (!file_exists($filename)) {
            touch($filename);
        }

        $this->file = $filename;
        $this->timestampFormat = $timestampFormat;
        $this->fileType = $fileType;
        $this->csvColumns = $csvColumns;
        $this->lastRotationWeek = (int) date('W');

        if ($logIP) {
            $this->csvColumns[] = "IP";
        }

        if ($logBrowserOS) {
            $this->csvColumns[] = "Browser";
            $this->csvColumns[] = "OS";
        }

        if (($this->fileType === 'csv' || $this->fileType === 'tsv') && !empty($this->csvColumns) && filesize($this->file) === 0) {
            $delimiter = $this->fileType === 'csv' ? ',' : "\t";
            $file = fopen($this->file, 'a');
            fputcsv($file, $this->csvColumns, $delimiter);
            fclose($file);    
        }
    }

    /**
     * Define custom error messages.
     *
     * @param string $key Key for the error type.
     * @param string $message Custom error message.
     */
    public function define($key, $message)
    {
        if (isset($this->errorMessages[$key])) {
            $this->errorMessages[$key] = $message;
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
        $this->rotateLogs();
        $this->cleanupOldLogs();

        $timestamp = date($this->timestampFormat);
        if (!is_array($insert)) {
            $insert = [$insert];
        }

        $logData = [$timestamp];

        if (in_array("IP", $this->csvColumns) || in_array("IP", $insert)) {
            $logData[] = $this->getClientIP();
        }

        if ((in_array("Browser", $this->csvColumns) && in_array("OS", $this->csvColumns)) || (in_array("Browser", $insert) && in_array("OS", $insert))) {
            $user_agent = $_SERVER['HTTP_USER_AGENT'];
            $logData[] = $this->getBrowser($user_agent);
            $logData[] = $this->getOS($user_agent);
        }

        if ($this->fileType === 'txt') {
            $logEntryComponents = array_merge(array_slice($logData, 1), $insert);
            $logEntry = $timestamp . ", " . implode(", ", $logEntryComponents) . PHP_EOL;
            if (file_put_contents($this->file, $logEntry, FILE_APPEND) === false) {
                throw new RuntimeException(sprintf($this->errorMessages['writeFailed'], $this->file));
            }
        } elseif ($this->fileType === 'csv' || $this->fileType === 'tsv') {
            $delimiter = $this->fileType === 'csv' ? ',' : "\t";
            $file = fopen($this->file, 'a');
            $data = array_merge($logData, $insert);

            if (!empty($this->csvColumns)) {
                $csvData = array_combine($this->csvColumns, $data);
                if (fputcsv($file, $data, $delimiter) === false) {
                    throw new RuntimeException(sprintf($this->errorMessages['writeFailed'], $this->file));
                }
            } else {
                if (fputcsv($file, $data) === false) {
                    throw new RuntimeException(sprintf($this->errorMessages['writeFailed'], $this->file));
                }
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
            throw new RuntimeException(sprintf($this->errorMessages['readFailed'], $this->file));
        }

        return $content;
    }

    /**
     * Get Browser from user agent string
     *
     * @param string $user_agent User agent string
     * @return string Browser name
     */
    private function getBrowser($user_agent)
    {
        if (strpos($user_agent, 'Firefox') !== false) {
            return 'Firefox';
        } elseif (strpos($user_agent, 'Chrome') !== false) {
            return 'Chrome';
        } elseif (strpos($user_agent, 'Safari') !== false) {
            return 'Safari';
        } elseif (strpos($user_agent, 'MSIE') !== false || strpos($user_agent, 'Trident') !== false) {
            return 'Internet Explorer';
        } else {
            return 'Others';
        }
    }


    /**
     * Get OS from user agent string
     *
     * @param string $user_agent User agent string
     * @return string OS name
     */
    private function getOS($user_agent)
    {
        if (strpos($user_agent, 'Windows NT') !== false) {
            return 'Windows';
        } elseif (strpos($user_agent, 'Mac OS X') !== false) {
            return 'MacOS';
        } elseif (strpos($user_agent, 'Linux') !== false) {
            return 'Linux';
        } elseif (strpos($user_agent, 'iPhone') !== false || strpos($user_agent, 'iPad') !== false) {
            return 'iOS';
        } elseif (strpos($user_agent, 'Android') !== false) {
            return 'Android';
        } else {
            return 'Others';
        }
    }

    /**
     * Retrieves the client's IP address.
     * 
     * Tries to obtain the IP address from various headers set by proxies and load balancers.
     * If none are found, it falls back to $_SERVER['REMOTE_ADDR'].
     * 
     * @return string Client's IP address. Returns 'UNKNOWN' if the IP cannot be determined.
     */
    function getClientIP() {
        $headers = [
            'HTTP_CLIENT_IP', 
            'HTTP_X_FORWARDED_FOR', 
            'HTTP_X_FORWARDED', 
            'HTTP_X_CLUSTER_CLIENT_IP', 
            'HTTP_FORWARDED_FOR', 
            'HTTP_FORWARDED', 
            'REMOTE_ADDR'
        ];
    
        foreach ($headers as $header) {
            if (isset($_SERVER[$header])) {
                $ip_list = explode(',', $_SERVER[$header]);
                return trim(end($ip_list)); // Return the last IP in the list
            }
        }
    
        return 'UNKNOWN';
    }

    /**
     * Rotates the logs based on the criteria mentioned.
     */
    private function rotateLogs()
    {
        $currentWeek = (int) date('W');
        $currentYear = date('Y');
        $baseLogName = $this->file . ' ' . self::LOG_PREFIX . ' ' . $currentWeek . ' ' . $currentYear;

        // Check if we need to rotate based on the week or size
        if ($this->lastRotationWeek !== $currentWeek || (filesize($this->file) >= self::MAX_LOG_SIZE && $this->lastRotationWeek === $currentWeek)) {
            $logName = $this->getNextLogFilename($baseLogName);

            rename($this->file, $logName);
            touch($this->file);

            // If CSV, write the header again
            if ($this->fileType === 'csv' && !empty($this->csvColumns)) {
                $file = fopen($this->file, 'a');
                fputcsv($file, $this->csvColumns);
                fclose($file);
            }

            $this->lastRotationWeek = $currentWeek;
        }
    }

    /**
     * Get the next available log filename based on the base log name.
     * 
     * @param string $baseLogName Base log name
     * @return string Next available log filename
     */
    private function getNextLogFilename($baseLogName)
    {
        $counter = 1;
        $logName = $baseLogName . ' ' . $counter;

        while (file_exists($logName)) {
            $counter++;
            $logName = $baseLogName . ' ' . $counter;
        }
        return $logName;
    }

    /**
     * Cleanup old logs based on the criteria mentioned.
     */
    private function cleanupOldLogs()
    {
        $logFiles = glob($this->file . ' ' . self::LOG_PREFIX . '*');
        usort($logFiles, function ($a, $b) {
            return filemtime($a) < filemtime($b);
        });


        while (count($logFiles) > self::MAX_LOG_DURATION) {
            $fileToDelete = array_pop($logFiles);
            unlink($fileToDelete);
        }
    }
}