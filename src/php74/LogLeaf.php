<?php

/**
 * LogLeaf — lightweight file logger with rotation and TXT/CSV/TSV/JSONL support.
 *
 * - Weekly/size-based rotation with optional gzip (see ENABLE_GZIP)
 * - Column-aware CSV/TSV with auto "Timestamp" header
 * - JSON Lines (JSONL) and plain text output
 * - Pluggable processors (function(array $record): array)
 * - Convenience level helpers: debug/info/warning/error
 * - Safe file locking and path validation
 *
 * @author    Wera
 * @license   MIT
 * @version   2.0
 * @requires  PHP 7.0+
 */
class LogLeaf
{
    /** Weekday name for time-based rotation (e.g., "Monday"). */
    const ROTATE_DAY       = 'Monday';
    /** Prefix for rotated filenames. */
    const LOG_PREFIX       = 'Week';
    /** Maximum number of rotated files to keep. */
    const MAX_LOG_DURATION = 12;
    /** Max active file size in bytes before rotation. */
    const MAX_LOG_SIZE     = 26214400;
    /** Default timezone identifier. */
    const DEFAULT_TZ       = 'UTC';
    /** Enable gzip compression for rotated files. */
    const ENABLE_GZIP      = false;

    /** Supported output formats. */
    const FORMAT_TXT       = 'txt';
    const FORMAT_CSV       = 'csv';
    const FORMAT_TSV       = 'tsv';
    const FORMAT_JSONL     = 'jsonl';

    /** @var string Absolute path to the active log file. */
    private $file;
    /** @var string Output format: txt|csv|tsv|jsonl. */
    private $fileType;
    /** @var string PHP date() format for timestamps. */
    private $timestampFormat;
    /** @var array<string> Ordered CSV/TSV columns (Timestamp auto-prepended if missing). */
    private $csvColumns;
    /** @var int ISO week number of last rotation. */
    private $lastRotationWeek;
    /** @var string Current timezone identifier. */
    private $tz;
    /** @var callable[] List of processors: function(array $record): array */
    private $processors = [];

    /**
     * @var array<string,string> Customizable error messages keyed by identifier.
     */
    private $errorMessages = [
        'illegalExtension'       => 'Invalid file extension. Allowed extensions are:',
        'writeFailed'            => 'Failed to write to log file %s',
        'readFailed'             => 'Failed to read log file %s',
        'browserDetectionFailed' => 'Error: Browser detection failed',
        'osDetectionFailed'      => 'Error: OS detection failed',
        'pathInvalid'            => 'Invalid log path or directory not writable: %s'
    ];

    /**
     * Constructor.
     *
     * @param string      $filename        Target file path (created if missing)
     * @param string      $fileType        txt|csv|tsv|jsonl|auto (auto = infer from $filename extension)
     * @param string      $timestampFormat PHP date() format (default Y-m-d H:i:s)
     * @param array       $csvColumns      CSV/TSV column order (Timestamp auto-added if absent)
     * @param bool        $logIP           Append public-looking client IP
     * @param bool        $logBrowserOS    Append Browser and OS fields
     * @param string|null $timezone        Timezone identifier (default UTC)
     *
     * @throws InvalidArgumentException If filename extension or format is invalid
     * @throws RuntimeException         If directory cannot be created or is not writable
     */
    public function __construct(
        $filename,
        $fileType,
        $timestampFormat = 'Y-m-d H:i:s',
        array $csvColumns = [],
        $logIP = false,
        $logBrowserOS = false,
        $timezone = null
    ) {
        $allowedExtensions = ['txt', 'csv', 'tsv', 'log', 'jsonl'];
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        if (!in_array($ext, $allowedExtensions, true)) {
            throw new InvalidArgumentException($this->errorMessages['illegalExtension'] . ' ' . implode(', ', $allowedExtensions));
        }

        $format = strtolower((string) $fileType);
        if ($format === '' || $format === 'auto') {
            $format = $this->inferFormatFromExtension($ext);
        }
        if (!in_array($format, [self::FORMAT_TXT, self::FORMAT_CSV, self::FORMAT_TSV, self::FORMAT_JSONL], true)) {
            throw new InvalidArgumentException('Unsupported format: ' . $format);
        }

        $this->fileType         = $format;
        $this->timestampFormat  = $timestampFormat;
        $this->csvColumns       = $csvColumns;
        $this->lastRotationWeek = (int) date('W');
        $this->tz               = $timezone ?: self::DEFAULT_TZ;

        if ($logIP && !in_array('IP', $this->csvColumns, true)) {
            $this->csvColumns[] = 'IP';
        }
        if ($logBrowserOS) {
            if (!in_array('Browser', $this->csvColumns, true)) {
                $this->csvColumns[] = 'Browser';
            }
            if (!in_array('OS', $this->csvColumns, true)) {
                $this->csvColumns[] = 'OS';
            }
        }

        $dir = dirname($filename);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            throw new RuntimeException(sprintf($this->errorMessages['pathInvalid'], $dir));
        }
        if (!is_writable($dir)) {
            throw new RuntimeException(sprintf($this->errorMessages['pathInvalid'], $dir));
        }

        if (!file_exists($filename)) {
            touch($filename);
            if (($this->fileType === self::FORMAT_CSV || $this->fileType === self::FORMAT_TSV) && !empty($this->csvColumns)) {
                $this->writeCsvHeader($filename, $this->fileType === self::FORMAT_CSV ? ',' : "\t");
            }
        }

        $this->file = $filename;
        $this->setTimezone($this->tz);
    }

    /**
     * Override a predefined error message by key.
     *
     * @param string $key     One of the keys in $errorMessages
     * @param string $message Replacement message
     * @return void
     */
    public function define($key, $message)
    {
        if (isset($this->errorMessages[$key])) {
            $this->errorMessages[$key] = $message;
        }
    }

    /**
     * Set the timestamp format used by date().
     *
     * @param string $format PHP date() format string
     * @return void
     */
    public function setTimestampFormat($format)
    {
        $this->timestampFormat = $format;
    }

    /**
     * Set timezone for date() and rotation checks.
     *
     * @param string $tz Timezone identifier (e.g., "UTC", "Europe/Oslo")
     * @return void
     */
    public function setTimezone($tz)
    {
        $this->tz = $tz ?: self::DEFAULT_TZ;
        date_default_timezone_set($this->tz);
    }

    /**
     * Change output format at runtime.
     *
     * @param string $format txt|csv|tsv|jsonl
     * @return void
     *
     * @throws InvalidArgumentException If an unsupported format is provided
     */
    public function setFormat($format)
    {
        $format = strtolower((string)$format);
        if (!in_array($format, [self::FORMAT_TXT, self::FORMAT_CSV, self::FORMAT_TSV, self::FORMAT_JSONL], true)) {
            throw new InvalidArgumentException('Unsupported format: ' . $format);
        }
        $this->fileType = $format;
    }

    /**
     * Register a processor to modify records before writing.
     *
     * @param callable $processor function(array $record): array
     * @return void
     */
    public function pushProcessor(callable $processor)
    {
        $this->processors[] = $processor;
    }

    /**
     * Log a DEBUG record.
     *
     * @param string $msg Message
     * @param array  $ctx Context data merged under "context"
     * @return void
     */
    public function debug($msg, array $ctx = [])
    {
        $this->putLog(['level' => 'DEBUG', 'message' => $msg, 'context' => $ctx]);
    }

    /**
     * Log an INFO record.
     *
     * @param string $msg Message
     * @param array  $ctx Context data merged under "context"
     * @return void
     */
    public function info($msg, array $ctx = [])
    {
        $this->putLog(['level' => 'INFO', 'message' => $msg, 'context' => $ctx]);
    }

    /**
     * Log a WARNING record.
     *
     * @param string $msg Message
     * @param array  $ctx Context data merged under "context"
     * @return void
     */
    public function warning($msg, array $ctx = [])
    {
        $this->putLog(['level' => 'WARNING', 'message' => $msg, 'context' => $ctx]);
    }

    /**
     * Log an ERROR record.
     *
     * @param string $msg Message
     * @param array  $ctx Context data merged under "context"
     * @return void
     */
    public function error($msg, array $ctx = [])
    {
        $this->putLog(['level' => 'ERROR', 'message' => $msg, 'context' => $ctx]);
    }

    /**
     * Append a record to the log file.
     *
     * @param mixed $insert Scalar/string/array payload; arrays serialized per format
     * @return void
     *
     * @throws RuntimeException On write failure
     */
    public function putLog($insert)
    {
        $this->maybeRotate();
        $this->cleanupOldLogs();

        $timestamp = date($this->timestampFormat);
        $record    = is_array($insert) ? $insert : ['message' => (string) $insert];

        if ($this->needsIP($record)) {
            $record['IP'] = $this->getClientIP();
        }
        if ($this->needsUAOS($record)) {
            $ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
            list($browser, $os) = $this->parseUAOS($ua);
            $record['Browser'] = $browser;
            $record['OS']      = $os;
        }

        foreach ($this->processors as $p) {
            $record = $p($record);
        }

        if ($this->fileType === self::FORMAT_TXT) {
            $line = $timestamp . ' ' . (isset($record['level']) ? '[' . $record['level'] . '] ' : '');
            $line .= (count($record) === 1 && isset($record['message']))
                ? $record['message']
                : json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $this->appendLine($this->file, $line . PHP_EOL);
        } elseif ($this->fileType === self::FORMAT_JSONL) {
            $payload = ['@ts' => $timestamp] + $record;
            $this->appendLine($this->file, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
        } else {
            $delimiter = $this->fileType === self::FORMAT_CSV ? ',' : "\t";
            $fh = fopen($this->file, 'a');
            if ($fh === false) {
                throw new RuntimeException(sprintf($this->errorMessages['writeFailed'], $this->file));
            }
            try {
                flock($fh, LOCK_EX);
                if (filesize($this->file) === 0 && !empty($this->csvColumns)) {
                    fputcsv($fh, $this->csvColumns, $delimiter, '"', '\\');
                }
                $row = $this->rowForCsv($record);
                if (fputcsv($fh, $row, $delimiter, '"', '\\') === false) {
                    throw new RuntimeException(sprintf($this->errorMessages['writeFailed'], $this->file));
                }
            } finally {
                flock($fh, LOCK_UN);
                fclose($fh);
            }
        }
    }

    /**
     * Read the entire log file content.
     *
     * @return string File contents
     *
     * @throws RuntimeException On read failure
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
     * Return the last N lines of the log efficiently.
     *
     * @param int $lines Number of lines from the end
     * @return string Tail content ending with newline
     */
    public function tail($lines = 200)
    {
        $f = @fopen($this->file, 'rb');
        if (!$f) {
            return '';
        }
        $buffer = '';
        $lineCount = 0;
        fseek($f, 0, SEEK_END);
        $filesize = ftell($f);
        while ($filesize > 0 && $lineCount <= $lines) {
            $step = min(4096, $filesize);
            $filesize -= $step;
            fseek($f, $filesize);
            $chunk = fread($f, $step);
            $buffer = $chunk . $buffer;
            $lineCount = substr_count($buffer, "\n");
            if ($filesize === 0) {
                break;
            }
        }
        fclose($f);
        $parts = explode("\n", $buffer);
        return implode("\n", array_slice($parts, -$lines)) . "\n";
    }

    /**
     * Decide if IP should be appended to the record.
     *
     * @param array $record Current record
     * @return bool
     */
    private function needsIP(array $record)
    {
        return in_array('IP', $this->csvColumns, true) || array_key_exists('IP', $record);
    }

    /**
     * Decide if Browser/OS should be appended to the record.
     *
     * @param array $record Current record
     * @return bool
     */
    private function needsUAOS(array $record)
    {
        $need = (in_array('Browser', $this->csvColumns, true) && in_array('OS', $this->csvColumns, true));
        return $need || (isset($record['Browser']) && isset($record['OS']));
    }

    /**
     * Append a line with locking.
     *
     * @param string $file Target file
     * @param string $line Line to append (with newline if desired)
     * @return void
     *
     * @throws RuntimeException On write failure
     */
    private function appendLine($file, $line)
    {
        $fh = fopen($file, 'a');
        if ($fh === false) {
            throw new RuntimeException(sprintf($this->errorMessages['writeFailed'], $file));
        }
        try {
            flock($fh, LOCK_EX);
            fwrite($fh, $line);
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }

    /**
     * Build a CSV/TSV row honoring configured columns.
     *
     * @param array $record Record data
     * @return array Ordered row values
     */
    private function rowForCsv(array $record)
    {
        if (!empty($this->csvColumns)) {
            $row = [];
            if (!in_array('Timestamp', $this->csvColumns, true)) {
                array_unshift($this->csvColumns, 'Timestamp');
            }
            foreach ($this->csvColumns as $col) {
                if ($col === 'Timestamp') {
                    $row[] = date($this->timestampFormat);
                } else {
                    $row[] = array_key_exists($col, $record)
                        ? (is_scalar($record[$col]) ? $record[$col] : json_encode($record[$col]))
                        : '';
                }
            }
            return $row;
        }
        $flat = [$this->timestampFormat ? date($this->timestampFormat) : date('c')];
        foreach ($record as $v) {
            $flat[] = is_scalar($v) ? $v : json_encode($v);
        }
        return $flat;
    }

    /**
     * Write CSV/TSV header to a new/rotated file.
     *
     * @param string $file      Target file
     * @param string $delimiter Comma or tab
     * @return void
     */
    private function writeCsvHeader($file, $delimiter)
    {
        $fh = fopen($file, 'a');
        if ($fh) {
            flock($fh, LOCK_EX);
            $cols = $this->csvColumns;
            if (!in_array('Timestamp', $cols, true)) {
                array_unshift($cols, 'Timestamp');
            }
            fputcsv($fh, $cols, $delimiter, '"', '\\');
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }

    /**
     * Rotate active log when size/week/weekday criteria match.
     *
     * @return void
     */
    private function maybeRotate()
    {
        $currentWeek  = (int) date('W');
        $today        = date('l');
        $sizeExceeded = @filesize($this->file) !== false && filesize($this->file) >= self::MAX_LOG_SIZE;
        $weekdayHit   = (self::ROTATE_DAY && $today === self::ROTATE_DAY && $this->lastRotationWeek !== $currentWeek);
        $weekChanged  = ($this->lastRotationWeek !== $currentWeek);

        if ($sizeExceeded || $weekdayHit || $weekChanged) {
            $rotated = $this->buildRotatedName($this->file, $currentWeek, date('Y'));
            @rename($this->file, $rotated);
            touch($this->file);

            if (($this->fileType === self::FORMAT_CSV || $this->fileType === self::FORMAT_TSV) && !empty($this->csvColumns)) {
                $this->writeCsvHeader($this->file, $this->fileType === self::FORMAT_CSV ? ',' : "\t");
            }

            if (self::ENABLE_GZIP && file_exists($rotated)) {
                $this->gzipFile($rotated);
            }

            $this->lastRotationWeek = $currentWeek;
        }
    }

    /**
     * Build a unique filename for a rotated log.
     *
     * @param string $file Base file path
     * @param int    $week ISO week number
     * @param string $year Year component
     * @return string Rotated filename
     */
    private function buildRotatedName($file, $week, $year)
    {
        $dir  = dirname($file);
        $base = basename($file);
        $ext  = pathinfo($file, PATHINFO_EXTENSION);
        $name = preg_replace('/\.' . preg_quote($ext, '/') . '$/', '', $base);
        $counter = 1;
        do {
            $candidate = sprintf('%s/%s %s-%d-%d.%d.%s', $dir, $name, self::LOG_PREFIX, $week, $year, $counter, $ext);
            $counter++;
        } while (file_exists($candidate));
        return $candidate;
    }

    /**
     * Gzip a rotated file and remove the original.
     *
     * @param string $path Absolute path of the rotated file
     * @return void
     */
    private function gzipFile($path)
    {
        $gz = gzopen($path . '.gz', 'wb9');
        if ($gz) {
            $in = fopen($path, 'rb');
            while (!feof($in)) {
                gzwrite($gz, fread($in, 8192));
            }
            fclose($in);
            gzclose($gz);
            @unlink($path);
        }
    }

    /**
     * Delete rotated files exceeding MAX_LOG_DURATION.
     *
     * @return void
     */
    private function cleanupOldLogs()
    {
        $dir  = dirname($this->file);
        $name = basename($this->file);
        $pattern = sprintf('%s/%s %s-*', $dir, preg_replace('/\.[^.]+$/', '', $name), self::LOG_PREFIX);

        $logFiles = glob($pattern);
        if (!$logFiles) {
            return;
        }

        usort($logFiles, function ($a, $b) {
            $ma = @filemtime($a) ?: 0;
            $mb = @filemtime($b) ?: 0;
            if ($ma === $mb) {
                return 0;
            }
            return ($ma < $mb) ? -1 : 1;
        });

        while (count($logFiles) > self::MAX_LOG_DURATION) {
            $fileToDelete = array_shift($logFiles);
            @unlink($fileToDelete);
            if (file_exists($fileToDelete . '.gz')) {
                @unlink($fileToDelete . '.gz');
            }
        }
    }

    /**
     * Parse browser and OS from a user agent string.
     *
     * @param string $ua User agent
     * @return array{0:string,1:string} [browser, os]
     */
    private function parseUAOS($ua)
    {
        if ($ua === '') {
            return ['CLI/Unknown', 'Unknown'];
        }
        $browser = 'Others';
        if (strpos($ua, 'Firefox') !== false) {
            $browser = 'Firefox';
        } elseif (strpos($ua, 'Chrome') !== false && strpos($ua, 'Chromium') === false) {
            $browser = 'Chrome';
        } elseif (strpos($ua, 'Safari') !== false && strpos($ua, 'Chrome') === false) {
            $browser = 'Safari';
        } elseif (strpos($ua, 'MSIE') !== false || strpos($ua, 'Trident') !== false) {
            $browser = 'Internet Explorer';
        }

        $os = 'Others';
        if (strpos($ua, 'Windows NT') !== false) {
            $os = 'Windows';
        } elseif (strpos($ua, 'Mac OS X') !== false) {
            $os = 'MacOS';
        } elseif (strpos($ua, 'Linux') !== false) {
            $os = 'Linux';
        } elseif (strpos($ua, 'iPhone') !== false || strpos($ua, 'iPad') !== false) {
            $os = 'iOS';
        } elseif (strpos($ua, 'Android') !== false) {
            $os = 'Android';
        }

        return [$browser, $os];
    }

    /**
     * Resolve public-looking client IP from common proxy headers.
     *
     * @return string IP address or 'UNKNOWN'
     */
    public function getClientIP()
    {
        $candidates = [];
        $keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        foreach ($keys as $k) {
            if (!empty($_SERVER[$k])) {
                foreach (explode(',', $_SERVER[$k]) as $ip) {
                    $ip = trim($ip);
                    if ($ip !== '') {
                        $candidates[] = $ip;
                    }
                }
            }
        }
        foreach ($candidates as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'UNKNOWN';
    }

    /**
     * Infer format from filename extension; default to txt.
     *
     * @param string $ext Extension without dot
     * @return string One of self::FORMAT_* constants
     */
    private function inferFormatFromExtension($ext)
    {
        $ext = strtolower((string)$ext);
        if ($ext === 'csv') {
            return self::FORMAT_CSV;
        }
        if ($ext === 'tsv') {
            return self::FORMAT_TSV;
        }
        if ($ext === 'jsonl') {
            return self::FORMAT_JSONL;
        }
        return self::FORMAT_TXT;
    }
}
