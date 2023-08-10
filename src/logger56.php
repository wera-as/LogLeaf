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
     * @var bool Use advanced detection method
     */
    private $useAdvancedDetection;

    /**
     * @var string Path to Mobile_Detect library
     */
    private $mobileDetectPath;

    /**
     * @var string Path to Browser library
     */
    private $browserDetectPath;

    /**
     * Logger constructor.
     *
     * @param string $filename              File name
     * @param string $fileType              File type (txt or csv)
     * @param string $timestampFormat       Timestamp format (optional)
     * @param array  $csvColumns            CSV column names (optional)
     * @param bool   $logIP                 Whether to log IP address (optional)
     * @param bool   $logBrowserOS          Whether to log Browser and OS (optional)
     * @param bool   $useAdvancedDetection  Whether to use advanced detection method (optional)
     * @param string $mobileDetectPath      Path to Mobile_Detect library (optional)
     * @param string $browserDetectPath     Path to Browser library (optional)
     * @throws InvalidArgumentException If the file name is empty
     */
    public function __construct($filename, $fileType, $timestampFormat = 'Y-m-d H:i:s', $csvColumns = array(), $logIP = false, $logBrowserOS = false, $useAdvancedDetection = false, $mobileDetectPath = '', $browserDetectPath = '')
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
        $this->useAdvancedDetection = $useAdvancedDetection;
        $this->mobileDetectPath = $mobileDetectPath;
        $this->browserDetectPath = $browserDetectPath;

        if ($logIP) {
            $this->csvColumns[] = "IP";
        }

        if ($logBrowserOS) {
            $this->csvColumns[] = "Browser";
            $this->csvColumns[] = "OS";
        }

        if ($this->fileType === 'csv' && !empty($this->csvColumns) && filesize($this->file) === 0) {
            $file = fopen($this->file, 'a');
            fputcsv($file, $this->csvColumns);
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
        if (!is_array($insert)) {
            $insert = [$insert];
        }

        $logData = [$timestamp];

        if (in_array("IP", $this->csvColumns)) {
            $logData[] = $_SERVER['REMOTE_ADDR'];
        }

        if (in_array("Browser", $this->csvColumns) && in_array("OS", $this->csvColumns)) {
            $user_agent = $_SERVER['HTTP_USER_AGENT'];
            $logData[] = $this->getBrowser($user_agent);
            $logData[] = $this->getOS($user_agent);
        }

        if ($this->fileType === 'txt') {
            $logEntry = $timestamp . " : " . $insert . PHP_EOL;
            if (file_put_contents($this->file, $logEntry, FILE_APPEND) === false) {
                throw new RuntimeException("Failed to write to log file {$this->file}");
            }
        } elseif ($this->fileType === 'csv') {
            $file = fopen($this->file, 'a');
            $data = array_merge($logData, $insert);

            if (!empty($this->csvColumns)) {
                $csvData = array_combine($this->csvColumns, $data);
                if (fputcsv($file, $csvData) === false) {
                    throw new RuntimeException("Failed to write to CSV file {$this->file}");
                }
            } else {
                if (fputcsv($file, $data) === false) {
                    throw new RuntimeException("Failed to write to CSV file {$this->file}");
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
            throw new RuntimeException("Failed to read log file {$this->file}");
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
        if ($this->useAdvancedDetection && file_exists($this->browserDetectPath)) {
            include_once $this->browserDetectPath;
            $browser = new Browser($user_agent);
            $detectedBrowser = $browser->getBrowser();
            if ($detectedBrowser) {
                return $detectedBrowser;
            } else {
                return "Error: Browser detection failed";
            }
        } else {
            // Fallback to basic in-house method
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
    }

    /**
     * Get OS from user agent string
     *
     * @param string $user_agent User agent string
     * @return string OS name
     */
    private function getOS($user_agent)
    {
        if ($this->useAdvancedDetection && file_exists($this->mobileDetectPath)) {
            include_once $this->mobileDetectPath;
            $detect = new Mobile_Detect();
            $detectedOS = $detect->getOperatingSystem();
            if ($detectedOS) {
                return $detectedOS;
            } else {
                return "Error: OS detection failed";
            }
        } else {
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
    }
}
