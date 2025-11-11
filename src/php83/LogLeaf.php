<?php

declare(strict_types=1);

/**
 * PSR-3 Logger Interface (PHP 8.3 Compatible)
 *
 * This is included so the file is standalone and does not
 * require composer install of psr/log.
 */

namespace Psr\Log {

    use Stringable;

    if (!interface_exists('Psr\Log\LoggerInterface')) {
        /**
         * Describes a logger instance.
         */
        interface LoggerInterface
        {
            /**
             * System is unusable.
             *
             * @param string|Stringable $message
             * @param array<string, mixed> $context
             */
            public function emergency(string|Stringable $message, array $context = []): void;

            /**
             * Action must be taken immediately.
             *
             * @param string|Stringable $message
             * @param array<string, mixed> $context
             */
            public function alert(string|Stringable $message, array $context = []): void;

            /**
             * Critical conditions.
             *
             * @param string|Stringable $message
             * @param array<string, mixed> $context
             */
            public function critical(string|Stringable $message, array $context = []): void;

            /**
             * Runtime errors.
             *
             * @param string|Stringable $message
             * @param array<string, mixed> $context
             */
            public function error(string|Stringable $message, array $context = []): void;

            /**
             * Exceptional occurrences that are not errors.
             *
             * @param string|Stringable $message
             * @param array<string, mixed> $context
             */
            public function warning(string|Stringable $message, array $context = []): void;

            /**
             * Normal but significant events.
             *
             * @param string|Stringable $message
             * @param array<string, mixed> $context
             */
            public function notice(string|Stringable $message, array $context = []): void;

            /**
             * Interesting events.
             *
             * @param string|Stringable $message
             * @param array<string, mixed> $context
             */
            public function info(string|Stringable $message, array $context = []): void;

            /**
             * Detailed debug information.
             *
             * @param string|Stringable $message
             * @param array<string, mixed> $context
             */
            public function debug(string|Stringable $message, array $context = []): void;

            /**
             * Logs with an arbitrary level.
             *
             * @param mixed $level
             * @param string|Stringable $message
             * @param array<string, mixed> $context
             */
            public function log($level, string|Stringable $message, array $context = []): void;
        }
    }
}

/**
 * PSR-3 Log Level Constants (PHP 8.3 Compatible)
 */

namespace Psr\Log {
    if (!class_exists('Psr\Log\LogLevel')) {
        /**
         * Describes log levels.
         */
        class LogLevel
        {
            public const EMERGENCY = 'emergency';
            public const ALERT     = 'alert';
            public const CRITICAL  = 'critical';
            public const ERROR     = 'error';
            public const WARNING   = 'warning';
            public const NOTICE    = 'notice';
            public const INFO      = 'info';
            public const DEBUG     = 'debug';
        }
    }
}

/**
 * Return to the global namespace for the LogLeaf class.
 */

namespace {

    use Psr\Log\LoggerInterface;
    use Psr\Log\LogLevel;

    /**
     * LogLeaf — PSR-3 compliant lightweight file logger for PHP 8.3+.
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
     * @version    3.0 (PHP 8.3)
     * @requires   PHP 8.3+
     */
    final class LogLeaf implements LoggerInterface
    {
        /** Supported output formats. */
        public const FORMAT_TXT   = 'txt';
        public const FORMAT_CSV   = 'csv';
        public const FORMAT_TSV   = 'tsv';
        public const FORMAT_JSONL = 'jsonl';

        /** @var array<string,int> Map of log levels to severity. */
        private const LEVEL_MAP = [
            LogLevel::DEBUG     => 100,
            LogLevel::INFO      => 200,
            LogLevel::NOTICE    => 250,
            LogLevel::WARNING   => 300,
            LogLevel::ERROR     => 400,
            LogLevel::CRITICAL  => 500,
            LogLevel::ALERT     => 550,
            LogLevel::EMERGENCY => 600,
        ];

        // --- Configuration (set via constructor options) ---

        /** @var string|null Weekday name for time-based rotation (e.g., "Monday"). null to disable. */
        private ?string $rotateDay = 'Monday';
        /** @var string Prefix for rotated filenames. */
        private string $logPrefix = 'Week';
        /** @var int Maximum number of rotated files to keep. */
        private int $maxLogDuration = 12;
        /** @var int Max active file size in bytes before rotation (25MB). */
        private int $maxLogSize = 26214400;
        /** @var string Default timezone identifier. */
        private string $defaultTz = 'UTC';
        /** @var bool Enable gzip compression for rotated files. */
        private bool $enableGzip = false;

        // --- Internal State ---

        /** @var string Absolute path to the active log file. */
        private readonly string $file;
        /** @var string Output format: txt|csv|tsv|jsonl. */
        private string $fileType;
        /** @var string PHP date() format for timestamps. */
        private string $timestampFormat;
        /** @var string[] Ordered CSV/TSV columns (Timestamp auto-prepended if missing). */
        private array $csvColumns;
        /** @var int ISO week number of last rotation. */
        private int $lastRotationWeek;
        /** @var string Current timezone identifier. */
        private string $tz;
        /** @var callable[] Processors: function(array $record): array. */
        private array $processors = [];
        /** @var int Minimum log level to write. */
        private int $minLevelValue;

        /**
         * @var array<string,string> Customizable error messages.
         */
        private array $errorMessages = [
            'illegalExtension' => 'Invalid file extension. Allowed extensions are:',
            'writeFailed'      => 'Failed to write to log file %s',
            'readFailed'       => 'Failed to read log file %s',
            'pathInvalid'      => 'Invalid log path or directory not writable: %s',
        ];

        /**
         * Constructor.
         *
         * @param string $filename Target file path (created if missing)
         * @param array<string, mixed> $options Configuration options:
         * - 'fileType' (string): txt|csv|tsv|jsonl|auto (default: auto)
         * - 'level' (string): Minimum PSR-3 log level to record (default: debug)
         * - 'timestampFormat' (string): PHP date() format (default: Y-m-d H:i:s)
         * - 'csvColumns' (array): CSV/TSV column order (Timestamp auto-added)
         * - 'timezone' (string): Timezone identifier (default: UTC)
         * - 'rotateDay' (?string): Weekday for rotation, or null to disable (default: Monday)
         * - 'maxLogSize' (int): Max file size in bytes for rotation (default: 26214400)
         * - 'maxLogDuration' (int): Max number of rotated files to keep (default: 12)
         * - 'enableGzip' (bool): Gzip rotated files (default: false)
         *
         * @throws \InvalidArgumentException If filename extension or format is invalid
         * @throws \RuntimeException         If directory cannot be created or is not writable
         */
        public function __construct(
            string $filename,
            private readonly array $options = []
        ) {
            // --- 1. Set Configuration ---
            $this->rotateDay       = $this->options['rotateDay'] ?? $this->rotateDay;
            $this->logPrefix       = $this->options['logPrefix'] ?? $this->logPrefix;
            $this->maxLogDuration  = $this->options['maxLogDuration'] ?? $this->maxLogDuration;
            $this->maxLogSize      = $this->options['maxLogSize'] ?? $this->maxLogSize;
            $this->defaultTz       = $this->options['timezone'] ?? $this->defaultTz;
            $this->enableGzip      = $this->options['enableGzip'] ?? $this->enableGzip;
            $this->tz              = $this->defaultTz;

            $this->timestampFormat = $this->options['timestampFormat'] ?? 'Y-m-d H:i:s';
            $this->csvColumns      = $this->options['csvColumns'] ?? [];

            $minLevel = strtolower($this->options['level'] ?? LogLevel::DEBUG);
            $this->minLevelValue = self::LEVEL_MAP[$minLevel] ?? self::LEVEL_MAP[LogLevel::DEBUG];

            // --- 2. Validate File and Format ---
            $allowedExtensions = ['txt', 'csv', 'tsv', 'log', 'jsonl'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExtensions, true)) {
                throw new \InvalidArgumentException($this->errorMessages['illegalExtension'] . ' ' . implode(', ', $allowedExtensions));
            }

            $format = strtolower((string)($this->options['fileType'] ?? 'auto'));
            if ($format === '' || $format === 'auto') {
                $format = $this->inferFormatFromExtension($ext);
            }

            if (!in_array($format, [self::FORMAT_TXT, self::FORMAT_CSV, self::FORMAT_TSV, self::FORMAT_JSONL], true)) {
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
                $this->writeCsvHeader($this->file, $this->fileType === self::FORMAT_CSV ? ',' : "\t");
            }
        }

        /**
         * Override a predefined error message by key.
         *
         * @param string $key     One of: illegalExtension|writeFailed|readFailed|pathInvalid.
         * @param string $message Replacement text.
         * @return void
         */
        public function define(string $key, string $message): void
        {
            if (array_key_exists($key, $this->errorMessages)) {
                $this->errorMessages[$key] = $message;
            }
        }

        /**
         * Set the timestamp format used by date().
         *
         * @param string $format PHP date() format string.
         * @return void
         */
        public function setTimestampFormat(string $format): void
        {
            $this->timestampFormat = $format;
        }

        /**
         * Set timezone for date() and rotation checks.
         *
         * @param string $tz Timezone identifier (e.g., "UTC", "Europe/Oslo").
         * @return void
         */
        public function setTimezone(string $tz): void
        {
            $this->tz = $tz ?: $this->defaultTz;
            date_default_timezone_set($this->tz);
        }

        /**
         * Change output format at runtime.
         *
         * @param string $format One of txt|csv|tsv|jsonl.
         * @return void
         *
         * @throws \InvalidArgumentException For unsupported format.
         */
        public function setFormat(string $format): void
        {
            $format = strtolower($format);
            if (!in_array($format, [self::FORMAT_TXT, self::FORMAT_CSV, self::FORMAT_TSV, self::FORMAT_JSONL], true)) {
                throw new \InvalidArgumentException('Unsupported format: ' . $format);
            }
            $this->fileType = $format;
        }

        /**
         * Register a processor to mutate records before writing.
         *
         * @param callable(array<string, mixed>):array<string, mixed> $processor Function receiving and returning the record.
         * @return void
         */
        public function pushProcessor(callable $processor): void
        {
            $this->processors[] = $processor;
        }

        // --- PSR-3 Implementation ---

        /**
         * Logs with an arbitrary level.
         *
         * @param mixed $level
         * @param string|Stringable $message
         * @param array<string, mixed> $context
         * @return void
         */
        public function log($level, string|\Stringable $message, array $context = []): void
        {
            $levelName = strtolower((string) $level);

            // 1. Check if this log level should be skipped
            if ((self::LEVEL_MAP[$levelName] ?? 0) < $this->minLevelValue) {
                return;
            }

            // 2. Interpolate context
            $message = $this->interpolate((string) $message, $context);

            // 3. Build the core record
            $record = [
                'level'   => strtoupper($levelName),
                'message' => $message
            ];

            // 4. Handle exceptions and context
            $normContext = [];
            foreach ($context as $key => $val) {
                if ($key === 'exception' && $val instanceof \Throwable) {
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
         * @param string|Stringable $message
         * @param array<string, mixed> $context
         */
        public function emergency(string|\Stringable $message, array $context = []): void
        {
            $this->log(LogLevel::EMERGENCY, $message, $context);
        }

        /**
         * @param string|Stringable $message
         * @param array<string, mixed> $context
         */
        public function alert(string|\Stringable $message, array $context = []): void
        {
            $this->log(LogLevel::ALERT, $message, $context);
        }

        /**
         * @param string|Stringable $message
         * @param array<string, mixed> $context
         */
        public function critical(string|\Stringable $message, array $context = []): void
        {
            $this->log(LogLevel::CRITICAL, $message, $context);
        }

        /**
         * @param string|Stringable $message
         * @param array<string, mixed> $context
         */
        public function error(string|\Stringable $message, array $context = []): void
        {
            $this->log(LogLevel::ERROR, $message, $context);
        }

        /**
         * @param string|Stringable $message
         * @param array<string, mixed> $context
         */
        public function warning(string|\Stringable $message, array $context = []): void
        {
            $this->log(LogLevel::WARNING, $message, $context);
        }

        /**
         * @param string|Stringable $message
         * @param array<string, mixed> $context
         */
        public function notice(string|\Stringable $message, array $context = []): void
        {
            $this->log(LogLevel::NOTICE, $message, $context);
        }

        /**
         * @param string|Stringable $message
         * @param array<string, mixed> $context
         */
        public function info(string|\Stringable $message, array $context = []): void
        {
            $this->log(LogLevel::INFO, $message, $context);
        }

        /**
         * @param string|Stringable $message
         * @param array<string, mixed> $context
         */
        public function debug(string|\Stringable $message, array $context = []): void
        {
            $this->log(LogLevel::DEBUG, $message, $context);
        }

        // --- Core IO Methods ---

        /**
         * Append a record to the log file.
         * (Called by log() after PSR-3 processing)
         *
         * @param array<string, mixed> $record The final record array to be written
         * @return void
         *
         * @throws \RuntimeException On write failure
         */
        public function putLog(array $record): void
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
                $extra = [];
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
                $payload = ['@ts' => $timestamp] + $record;
                $this->appendLine($this->file, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
            } else {
                // CSV / TSV format
                $delimiter = $this->fileType === self::FORMAT_CSV ? ',' : "\t";
                $fh = fopen($this->file, 'ab');
                if ($fh === false) {
                    throw new \RuntimeException(sprintf($this->errorMessages['writeFailed'], $this->file));
                }
                try {
                    if (!flock($fh, LOCK_EX)) {
                        throw new \RuntimeException(sprintf($this->errorMessages['writeFailed'], $this->file));
                    }
                    clearstatcache(true, $this->file);
                    if (filesize($this->file) === 0 && !empty($this->csvColumns)) {
                        // Write header if file is empty
                        $this->writeCsvHeader($this->file, $delimiter, $fh);
                    }

                    $row = $this->rowForCsv($record, $timestamp);
                    if (fputcsv($fh, $row, $delimiter, '"') === false) {
                        throw new \RuntimeException(sprintf($this->errorMessages['writeFailed'], $this->file));
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
         * @return string File contents.
         *
         * @throws \RuntimeException On read failure.
         */
        public function getLog(): string
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
         * @param int $lines Number of lines from the end.
         * @return string Tail content ending with newline.
         */
        public function tail(int $lines = 200): string
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
         * Append a line to a file with locking.
         *
         * @param string $file Target file.
         * @param string $line Line to append (may include newline).
         * @return void
         *
         * @throws \RuntimeException On write failure.
         */
        private function appendLine(string $file, string $line): void
        {
            $fh = fopen($file, 'ab');
            if ($fh === false) {
                throw new \RuntimeException(sprintf($this->errorMessages['writeFailed'], $this->file));
            }
            try {
                if (!flock($fh, LOCK_EX)) {
                    throw new \RuntimeException(sprintf($this->errorMessages['writeFailed'], $this->file));
                }
                fwrite($fh, $line);
            } finally {
                flock($fh, LOCK_UN);
                fclose($fh);
            }
        }

        /**
         * Build a CSV/TSV row honoring configured columns.
         *
         * @param array<string, mixed> $record Record data.
         * @param string $timestamp Pre-formatted timestamp.
         * @return array<int, mixed> Ordered values for CSV/TSV row.
         */
        private function rowForCsv(array $record, string $timestamp): array
        {
            if (!empty($this->csvColumns)) {
                $row = [];
                foreach ($this->prepareHeaderColumns() as $col) {
                    if ($col === 'Timestamp') {
                        $row[] = $timestamp;
                    } else {
                        $val = $record[$col] ?? '';
                        $row[] = is_scalar($val) ? $val : json_encode($val, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    }
                }
                return $row;
            }

            // Fallback if no columns defined: [Timestamp, flat_record_values]
            $flat = [$timestamp];
            foreach ($record as $v) {
                $flat[] = is_scalar($v) ? $v : json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
            return $flat;
        }

        /**
         * Prepare header columns for CSV/TSV, ensuring "Timestamp" is first.
         *
         * @return string[] Column names.
         */
        private function prepareHeaderColumns(): array
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
         * @param string $file      Target file.
         * @param string $delimiter CSV: ","  TSV: "\t".
         * @param resource|null $fh Optional: existing locked file handle
         * @return void
         */
        private function writeCsvHeader(string $file, string $delimiter, $fh = null): void
        {
            $needsClose = false;
            if ($fh === null) {
                $fh = fopen($file, 'ab');
                if ($fh === false) return; // Cannot write
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
        private function maybeRotate(): void
        {
            $currentWeek = (int) date('W');
            $today       = date('l');

            clearstatcache(true, $this->file);
            $size = file_exists($this->file) ? filesize($this->file) : 0;

            $sizeExceeded = ($size !== false && $size >= $this->maxLogSize);
            $weekdayHit   = ($this->rotateDay && $today === $this->rotateDay && $this->lastRotationWeek !== $currentWeek);
            $weekChanged  = ($this->rotateDay === null && $this->lastRotationWeek !== $currentWeek); // Only rotate weekly if no day is set

            if ($sizeExceeded || $weekdayHit || $weekChanged) {
                $rotated = $this->buildRotatedName($this->file, $currentWeek, date('Y'));

                if (file_exists($this->file) && $size > 0) {
                    rename($this->file, $rotated);
                } elseif (!file_exists($this->file)) {
                    touch($this->file); // Create the new active file
                }

                if (($this->fileType === self::FORMAT_CSV || $this->fileType === self::FORMAT_TSV) && !empty($this->csvColumns)) {
                    $this->writeCsvHeader($this->file, $this->fileType === self::FORMAT_CSV ? ',' : "\t");
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
         * @param string $file Base file path.
         * @param int    $week ISO week number.
         * @param string $year Year string.
         * @return string Rotated filename.
         */
        private function buildRotatedName(string $file, int $week, string $year): string
        {
            $dir = dirname($file);
            $ext = pathinfo($file, PATHINFO_EXTENSION);
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
         * @param string $path Absolute path of the rotated file.
         * @return void
         */
        private function gzipFile(string $path): void
        {
            $gz = gzopen($path . '.gz', 'wb9');
            if ($gz === false) {
                return; // Gzip failed
            }

            $in = fopen($path, 'rb');
            if ($in === false) {
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
        private function cleanupOldLogs(): void
        {
            $dir = dirname($this->file);
            $ext = pathinfo($this->file, PATHINFO_EXTENSION);
            $name = basename($this->file, "." . $ext);

            // Pattern: /path/basename Week-*
            $pattern = sprintf('%s/%s %s-*', $dir, $name, $this->logPrefix);

            $logFiles = glob($pattern) ?: [];
            if (empty($logFiles)) {
                return;
            }

            // Sort files by modification time (oldest first)
            usort($logFiles, static function (string $a, string $b): int {
                return (file_exists($a) ? filemtime($a) : 0) <=> (file_exists($b) ? filemtime($b) : 0);
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
         * @param \Throwable $e
         * @return string
         */
        private function formatException(\Throwable $e): string
        {
            return sprintf(
                '[Exception %s] "%s" at %s:%d -- Trace: %s',
                $e::class,
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $e->getTraceAsString() // Include trace
            );
        }

        /**
         * Interpolates context values into the message placeholders.
         * @param string $message
         * @param array<string, mixed> $context
         * @return string
         */
        private function interpolate(string $message, array $context): string
        {
            if (!str_contains($message, '{')) {
                return $message;
            }

            $replace = [];
            foreach ($context as $key => $val) {
                // Check if val can be cast to string
                if ($val instanceof \Stringable || is_scalar($val) || $val === null) {
                    $replace['{' . $key . '}'] = (string) $val;
                }
            }

            return strtr($message, $replace);
        }

        /**
         * Infer format from filename extension; defaults to txt.
         *
         * @param string $ext Extension without dot.
         * @return string One of self::FORMAT_* constants.
         */
        private function inferFormatFromExtension(string $ext): string
        {
            return match (strtolower($ext)) {
                'csv'   => self::FORMAT_CSV,
                'tsv'   => self::FORMAT_TSV,
                'jsonl' => self::FORMAT_JSONL,
                default => self::FORMAT_TXT,
            };
        }
    }
}
