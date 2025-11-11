<?php

/**
 * PSR-3 Logger Interface (PHP 5.6 Compatible)
 *
 * This is included so the file is standalone and does not
 * require composer install of psr/log.
 */

namespace Psr\Log {
    if (!interface_exists('Psr\Log\LoggerInterface')) {
        /**
         * Describes a logger instance.
         */
        interface LoggerInterface
        {
            /**
             * System is unusable.
             *
             * @param string $message
             * @param array  $context
             * @return void
             */
            public function emergency($message, array $context = array());

            /**
             * Action must be taken immediately.
             *
             * @param string $message
             * @param array  $context
             * @return void
             */
            public function alert($message, array $context = array());

            /**
             * Critical conditions.
             *
             * @param string $message
             * @param array  $context
             * @return void
             */
            public function critical($message, array $context = array());

            /**
             * Runtime errors.
             *
             * @param string $message
             * @param array  $context
             * @return void
             */
            public function error($message, array $context = array());

            /**
             * Exceptional occurrences that are not errors.
             *
             * @param string $message
             * @param array  $context
             * @return void
             */
            public function warning($message, array $context = array());

            /**
             * Normal but significant events.
             *
             * @param string $message
             * @param array  $context
             * @return void
             */
            public function notice($message, array $context = array());

            /**
             * Interesting events.
             *
             * @param string $message
             * @param array  $context
             * @return void
             */
            public function info($message, array $context = array());

            /**
             * Detailed debug information.
             *
             * @param string $message
             * @param array  $context
             * @return void
             */
            public function debug($message, array $context = array());

            /**
             * Logs with an arbitrary level.
             *
             * @param mixed  $level
             * @param string $message
             * @param array  $context
             * @return void
             */
            public function log($level, $message, array $context = array());
        }
    }
}

/**
 * PSR-3 Log Level Constants (PHP 5.6 Compatible)
 */

namespace Psr\Log {
    if (!class_exists('Psr\Log\LogLevel')) {
        /**
         * Describes log levels.
         */
        class LogLevel
        {
            const EMERGENCY = 'emergency';
            const ALERT     = 'alert';
            const CRITICAL  = 'critical';
            const ERROR     = 'error';
            const WARNING   = 'warning';
            const NOTICE    = 'notice';
            const INFO      = 'info';
            const DEBUG     = 'debug';
        }
    }
}

/**
 * Return to global namespace for LogLeaf class
 */

namespace {
    /**
     * LogLeaf — PSR-3 compliant lightweight file logger for PHP 5.6+.
     *
     * - PSR-3 LoggerInterface compliant.
     * - Weekly/size-based rotation with optional gzip.
     * - Column-aware CSV/TSV with auto "Timestamp" header.
     * - JSON Lines (JSONL) and plain text output.
     * - Pluggable processors (function(array $record): array).
     * - Log level filtering (e.g., only log 'WARNING' and above).
     *
     * @author     Wera (Original v2)
     * @author     Gemini (Refactor v3)
     * @license    MIT
     * @version    3.0 (PHP 5.6)
     * @requires   PHP 5.6+
     */
    class LogLeaf implements \Psr\Log\LoggerInterface
    {
        /** @var string TXT output format key. */
        const FORMAT_TXT   = 'txt';
        /** @var string CSV output format key. */
        const FORMAT_CSV   = 'csv';
        /** @var string TSV output format key. */
        const FORMAT_TSV   = 'tsv';
        /** @var string JSON Lines output format key. */
        const FORMAT_JSONL = 'jsonl';

        // --- Configuration (set via constructor options) ---

        /** @var string|null Weekday name for time-based rotation (e.g., "Monday"). null to disable. */
        private $rotateDay = 'Monday';
        /** @var string Prefix for rotated filenames. */
        private $logPrefix = 'Week';
        /** @var int Maximum number of rotated files to keep. */
        private $maxLogDuration = 12;
        /** @var int Max active file size in bytes before rotation (25MB). */
        private $maxLogSize = 26214400;
        /** @var string Default timezone identifier. */
        private $defaultTz = 'UTC';
        /** @var bool Enable gzip compression for rotated files. */
        private $enableGzip = false;

        // --- Internal State ---

        /** @var string Absolute path to the active log file. */
        private $file;
        /** @var string Output format: txt|csv|tsv|jsonl. */
        private $fileType;
        /** @var string PHP date() format for timestamps. */
        private $timestampFormat;
        /** @var string[] Ordered CSV/TSV columns (Timestamp auto-prepended if missing). */
        private $csvColumns;
        /** @var int ISO week number of last rotation. */
        private $lastRotationWeek;
        /** @var string Current timezone identifier. */
        private $tz;
        /** @var callable[] Processors: function(array $record): array. */
        private $processors = array();
        /** @var int Minimum log level to write. */
        private $minLevelValue;

        /** @var array Map of log levels to severity. */
        private $levelMap = array(
            'emergency' => 600,
            'alert'     => 550,
            'critical'  => 500,
            'error'     => 400,
            'warning'   => 300,
            'notice'    => 250,
            'info'      => 200,
            'debug'     => 100,
        );

        /** @var array Customizable error messages. */
        private $errorMessages = array(
            'illegalExtension' => 'Invalid file extension. Allowed extensions are:',
            'writeFailed'      => 'Failed to write to log file %s',
            'readFailed'       => 'Failed to read log file %s',
            'pathInvalid'      => 'Invalid log path or directory not writable: %s',
        );

        /**
         * Constructor.
         *
         * @param string $filename Target file path (created if missing)
         * @param array  $options  Configuration options:
         * - 'fileType' (string): txt|csv|tsv|jsonl|auto (default: auto)
         * - 'level' (string): Minimum PSR-3 log level to record (default: debug)
         * - 'timestampFormat' (string): PHP date() format (default: Y-m-d H:i:s)
         * - 'csvColumns' (array): CSV/TSV column order (Timestamp auto-added)
         * - 'timezone' (string): Timezone identifier (default: UTC)
         * - 'rotateDay' (string|null): Weekday for rotation, or null to disable (default: Monday)
         * - 'maxLogSize' (int): Max file size in bytes for rotation (default: 26214400)
         * - 'maxLogDuration' (int): Max number of rotated files to keep (default: 12)
         * - 'enableGzip' (bool): Gzip rotated files (default: false)
         *
         * @throws \InvalidArgumentException If filename extension or format is invalid
         * @throws \RuntimeException         If directory cannot be created or is not writable
         */
        public function __construct($filename, array $options = array())
        {
            // --- 1. Set Configuration ---
            $this->rotateDay       = isset($options['rotateDay']) ? $options['rotateDay'] : $this->rotateDay;
            $this->logPrefix       = isset($options['logPrefix']) ? $options['logPrefix'] : $this->logPrefix;
            $this->maxLogDuration  = isset($options['maxLogDuration']) ? $options['maxLogDuration'] : $this->maxLogDuration;
            $this->maxLogSize      = isset($options['maxLogSize']) ? $options['maxLogSize'] : $this->maxLogSize;
            $this->defaultTz       = isset($options['timezone']) ? $options['timezone'] : $this->defaultTz;
            $this->enableGzip      = isset($options['enableGzip']) ? $options['enableGzip'] : $this->enableGzip;
            $this->tz              = $this->defaultTz;

            $this->timestampFormat = isset($options['timestampFormat']) ? $options['timestampFormat'] : 'Y-m-d H:i:s';
            $this->csvColumns      = isset($options['csvColumns']) ? $options['csvColumns'] : array();

            $minLevel = strtolower(isset($options['level']) ? $options['level'] : 'debug');
            $this->minLevelValue = isset($this->levelMap[$minLevel]) ? $this->levelMap[$minLevel] : 100;

            // --- 2. Validate File and Format ---
            $allowedExtensions = array('txt', 'csv', 'tsv', 'log', 'jsonl');
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            if (!in_array($ext, $allowedExtensions, true)) {
                throw new \InvalidArgumentException($this->errorMessages['illegalExtension'] . ' ' . implode(', ', $allowedExtensions));
            }

            $fileType = strtolower((string)(isset($options['fileType']) ? $options['fileType'] : 'auto'));
            if ($fileType === '' || $fileType === 'auto') {
                $format = $this->inferFormatFromExtension($ext);
            } else {
                $format = $fileType;
            }

            if (!in_array($format, array(self::FORMAT_TXT, self::FORMAT_CSV, self::FORMAT_TSV, self::FORMAT_JSONL), true)) {
                throw new \InvalidArgumentException('Unsupported format: ' . $format);
            }
            $this->fileType = $format;

            // --- 3. Validate Path and Permissions ---
            $dir = dirname($filename);
            if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                // Check is_dir again in case of race condition
                throw new \RuntimeException(sprintf($this->errorMessages['pathInvalid'], $dir));
            }
            if (!is_writable($dir)) {
                throw new \RuntimeException(sprintf($this->errorMessages['pathInvalid'], $dir));
            }

            // --- 4. Initialize File and State ---
            if (!file_exists($filename)) {
                touch($filename);
            }

            $this->file = $filename;
            $this->setTimezone($this->tz);
            $this->lastRotationWeek = (int) date('W');

            clearstatcache(true, $this->file);
            if (($this->fileType === self::FORMAT_CSV || $this->fileType === self::FORMAT_TSV) && !empty($this->csvColumns) && filesize($this->file) === 0) {
                $this->writeCsvHeader($this->file, $this->fileType === self::FORMAT_CSV ? ',' : "\t", null);
            }
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
            $this->tz = $tz ?: $this->defaultTz;
            date_default_timezone_set($this->tz);
        }

        /**
         * Change output format at runtime.
         *
         * @param string $format txt|csv|tsv|jsonl
         * @return void
         *
         * @throws \InvalidArgumentException If an unsupported format is provided
         */
        public function setFormat($format)
        {
            $format = strtolower((string)$format);
            if (!in_array($format, array(self::FORMAT_TXT, self::FORMAT_CSV, self::FORMAT_TSV, self::FORMAT_JSONL), true)) {
                throw new \InvalidArgumentException('Unsupported format: ' . $format);
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

        // --- PSR-3 Implementation ---

        /**
         * Logs with an arbitrary level.
         *
         * @param mixed  $level
         * @param string $message
         * @param array  $context
         * @return void
         */
        public function log($level, $message, array $context = array())
        {
            $levelName = strtolower((string) $level);

            // 1. Check if this log level should be skipped
            if (!isset($this->levelMap[$levelName]) || $this->levelMap[$levelName] < $this->minLevelValue) {
                return;
            }

            // 2. Interpolate context
            $message = $this->interpolate($message, $context);

            // 3. Build the core record
            $record = array(
                'level'   => strtoupper($levelName),
                'message' => $message
            );

            // 4. Handle exceptions and context
            $normContext = array();
            foreach ($context as $key => $val) {
                // Use \Exception for PHP 5.6 compatibility
                if ($key === 'exception' && $val instanceof \Exception) {
                    // Add formatted exception string
                    $record['exception'] = $this->formatException($val);
                } else {
                    $normContext[$key] = $val;
                }
            }

            // Add remaining context if it's not empty
            if (!empty($normContext)) {
                $record['context'] = $normContext;
            }

            // 5. Write to file
            $this->putLog($record);
        }

        /**
         * @param string $message
         * @param array  $context
         * @return void
         */
        public function emergency($message, array $context = array())
        {
            $this->log('emergency', $message, $context);
        }

        /**
         * @param string $message
         * @param array  $context
         * @return void
         */
        public function alert($message, array $context = array())
        {
            $this->log('alert', $message, $context);
        }

        /**
         * @param string $message
         * @param array  $context
         * @return void
         */
        public function critical($message, array $context = array())
        {
            $this->log('critical', $message, $context);
        }

        /**
         * @param string $message
         * @param array  $context
         * @return void
         */
        public function error($message, array $context = array())
        {
            $this->log('error', $message, $context);
        }

        /**
         * @param string $message
         * @param array  $context
         * @return void
         */
        public function warning($message, array $context = array())
        {
            $this->log('warning', $message, $context);
        }

        /**
         * @param string $message
         * @param array  $context
         * @return void
         */
        public function notice($message, array $context = array())
        {
            $this->log('notice', $message, $context);
        }

        /**
         * @param string $message
         * @param array  $context
         * @return void
         */
        public function info($message, array $context = array())
        {
            $this->log('info', $message, $context);
        }

        /**
         * @param string $message
         * @param array  $context
         * @return void
         */
        public function debug($message, array $context = array())
        {
            $this->log('debug', $message, $context);
        }

        // --- Core IO Methods ---

        /**
         * Append a record to the log file.
         * (Called by log() after PSR-3 processing)
         *
         * @param array $record The final record array to be written
         * @return void
         *
         * @throws \RuntimeException On write failure
         */
        public function putLog(array $record)
        {
            $this->maybeRotate();
            $this->cleanupOldLogs();

            $timestamp = date($this->timestampFormat);

            // Run all processors
            foreach ($this->processors as $p) {
                $record = $p($record);
            }

            if ($this->fileType === self::FORMAT_TXT) {
                // Simple text format: [Timestamp] [LEVEL] message {json_context}
                $line = $timestamp . ' [' . $record['level'] . '] ' . $record['message'];

                // Add context/exception if they exist
                $extra = array();
                if (isset($record['context'])) {
                    $extra['context'] = $record['context'];
                }
                if (isset($record['exception'])) {
                    $extra['exception'] = $record['exception'];
                }
                if (!empty($extra)) {
                    $line .= ' ' . json_encode($extra, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                }

                $this->appendLine($this->file, $line . PHP_EOL);
            } elseif ($this->fileType === self::FORMAT_JSONL) {
                // JSONL format: merge timestamp into the record
                $payload = array('@ts' => $timestamp) + $record;
                $this->appendLine($this->file, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
            } else {
                // CSV / TSV format
                $delimiter = $this->fileType === self::FORMAT_CSV ? ',' : "\t";
                $fh = fopen($this->file, 'a');
                if ($fh === false) {
                    throw new \RuntimeException(sprintf($this->errorMessages['writeFailed'], $this->file));
                }
                try {
                    if (flock($fh, LOCK_EX)) {
                        clearstatcache(true, $this->file);
                        if (filesize($this->file) === 0 && !empty($this->csvColumns)) {
                            // Write header if file is empty
                            $this->writeCsvHeader($this->file, $delimiter, $fh);
                        }

                        $row = $this->rowForCsv($record, $timestamp);
                        if (fputcsv($fh, $row, $delimiter, '"') === false) {
                            throw new \RuntimeException(sprintf($this->errorMessages['writeFailed'], $this->file));
                        }
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
         * @throws \RuntimeException On read failure
         */
        public function getLog()
        {
            $content = file_get_contents($this->file);
            if ($content === false) {
                throw new \RuntimeException(sprintf($this->errorMessages['readFailed'], $this->file));
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
            $f = @fopen($this->file, 'rb'); // Suppress warning if file just rotated
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
            // Return the last N lines, plus a newline
            return implode("\n", array_slice($parts, -$lines)) . "\n";
        }

        // --- Private Helper Methods ---

        /**
         * Append a line with locking.
         *
         * @param string $file Target file
         * @param string $line Line to append (with newline if desired)
         * @return void
         *
         * @throws \RuntimeException On write failure
         */
        private function appendLine($file, $line)
        {
            $fh = fopen($file, 'a');
            if ($fh === false) {
                throw new \RuntimeException(sprintf($this->errorMessages['writeFailed'], $file));
            }
            try {
                if (flock($fh, LOCK_EX)) {
                    fwrite($fh, $line);
                }
            } finally {
                flock($fh, LOCK_UN);
                fclose($fh);
            }
        }

        /**
         * Build a CSV/TSV row honoring configured columns.
         *
         * @param array  $record    Record data
         * @param string $timestamp Pre-formatted timestamp
         * @return array Ordered row values
         */
        private function rowForCsv(array $record, $timestamp)
        {
            if (!empty($this->csvColumns)) {
                $row = array();
                $cols = $this->prepareHeaderColumns();

                foreach ($cols as $col) {
                    if ($col === 'Timestamp') {
                        $row[] = $timestamp;
                    } else {
                        $val = array_key_exists($col, $record) ? $record[$col] : '';
                        $row[] = is_scalar($val) ? $val : json_encode($val, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    }
                }
                return $row;
            }

            // Fallback if no columns defined: [Timestamp, flat_record_values]
            $flat = array($timestamp);
            foreach ($record as $v) {
                $flat[] = is_scalar($v) ? $v : json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
            return $flat;
        }

        /**
         * Prepare header columns (ensures "Timestamp" is first).
         *
         * @return string[] Columns
         */
        private function prepareHeaderColumns()
        {
            $cols = $this->csvColumns;
            if (!in_array('Timestamp', $cols, true)) {
                array_unshift($cols, 'Timestamp');
            }
            return $cols;
        }


        /**
         * Write CSV/TSV header to a new/rotated file.
         *
         * @param string   $file      Target file
         * @param string   $delimiter Comma or tab
         * @param resource $fh        Optional: existing locked file handle
         * @return void
         */
        private function writeCsvHeader($file, $delimiter, $fh = null)
        {
            $needsClose = false;
            if (!$fh) {
                $fh = fopen($file, 'a');
                if (!$fh) return; // Cannot write
                if (!flock($fh, LOCK_EX)) {
                    fclose($fh);
                    return; // Cannot get lock
                }
                $needsClose = true;
            }

            fputcsv($fh, $this->prepareHeaderColumns(), $delimiter, '"');

            if ($needsClose) {
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
            $currentWeek = (int) date('W');
            $today       = date('l');

            clearstatcache(true, $this->file);
            $size = file_exists($this->file) ? filesize($this->file) : 0;

            $sizeExceeded = ($size !== false && $size >= $this->maxLogSize);
            $weekdayHit   = ($this->rotateDay && $today === $this->rotateDay && $this->lastRotationWeek !== $currentWeek);
            $weekChanged  = (!$this->rotateDay && $this->lastRotationWeek !== $currentWeek); // Only rotate weekly if no day is set

            if ($sizeExceeded || $weekdayHit || $weekChanged) {
                $rotated = $this->buildRotatedName($this->file, $currentWeek, date('Y'));

                if (file_exists($this->file) && filesize($this->file) > 0) {
                    rename($this->file, $rotated);
                } elseif (!file_exists($this->file)) {
                    touch($this->file); // Create the new active file
                }

                if (($this->fileType === self::FORMAT_CSV || $this->fileType === self::FORMAT_TSV) && !empty($this->csvColumns)) {
                    $this->writeCsvHeader($this->file, $this->fileType === self::FORMAT_CSV ? ',' : "\t", null);
                }

                if ($this->enableGzip && file_exists($rotated)) {
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
            $ext  = pathinfo($file, PATHINFO_EXTENSION);
            $name = basename($file, "." . $ext);

            $counter = 1;
            do {
                // Format: /path/basename Week-W-Y-c.ext
                $candidate = sprintf(
                    '%s/%s %s-%s-%s-%d.%s',
                    $dir,
                    $name,
                    $this->logPrefix,
                    $week,
                    $year,
                    $counter,
                    $ext
                );
                $counter++;
            } while (file_exists($candidate) || file_exists($candidate . '.gz'));

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
            if (!$gz) {
                return; // Gzip failed
            }

            $in = fopen($path, 'rb');
            if (!$in) {
                gzclose($gz);
                return; // Cannot read source
            }

            while (!feof($in)) {
                gzwrite($gz, fread($in, 8192));
            }

            fclose($in);
            gzclose($gz);
            if (file_exists($path)) {
                unlink($path);
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
            $ext  = pathinfo($this->file, PATHINFO_EXTENSION);
            $name = basename($this->file, "." . $ext);

            // Pattern: /path/basename Week-*
            $pattern = sprintf('%s/%s %s-*', $dir, $name, $this->logPrefix);

            $logFiles = glob($pattern);
            if (empty($logFiles)) {
                return;
            }

            // Sort files by modification time (oldest first)
            usort($logFiles, function ($a, $b) {
                $ma = file_exists($a) ? filemtime($a) : 0;
                $mb = file_exists($b) ? filemtime($b) : 0;
                return $ma - $mb;
            });

            // Remove files until we are at or below the duration limit
            while (count($logFiles) > $this->maxLogDuration) {
                $fileToDelete = array_shift($logFiles);
                if ($fileToDelete && file_exists($fileToDelete)) {
                    unlink($fileToDelete);
                }
            }
        }

        /**
         * Formats an exception for logging.
         * @param \Exception $e
         * @return string
         */
        private function formatException(\Exception $e)
        {
            return sprintf(
                '[Exception %s] "%s" at %s:%d -- Trace: %s',
                get_class($e),
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $e->getTraceAsString() // Include trace
            );
        }

        /**
         * Interpolates context values into the message placeholders.
         * @param string $message
         * @param array  $context
         * @return string
         */
        private function interpolate($message, array $context)
        {
            if (strpos($message, '{') === false) {
                return $message;
            }

            $replace = array();
            foreach ($context as $key => $val) {
                // Check if val can be cast to string
                if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                    $replace['{' . $key . '}'] = (string) $val;
                }
            }

            return strtr($message, $replace);
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
}
