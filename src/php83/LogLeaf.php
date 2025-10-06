<?php

declare(strict_types=1);

/**
 * LogLeaf — lightweight file logger with rotation and TXT/CSV/TSV/JSONL support (PHP 8.3+).
 *
 * Features
 * - Weekly and size-based rotation, optional gzip for rotated files.
 * - CSV/TSV with column order and automatic "Timestamp" header.
 * - JSON Lines (JSONL) and plain text output.
 * - Pluggable processors (callables) to mutate records before writing.
 * - Convenience helpers: debug(), info(), warning(), error().
 * - File locking, path validation, tail() helper.
 *
 * @author   Wera
 * @license  MIT
 * @version  2.0
 * @requires PHP 8.3+
 */
final class LogLeaf
{
    /** Weekday name used for time-based rotation, e.g. "Monday". */
    public const ROTATE_DAY       = 'Monday';
    /** Prefix for rotated filenames. */
    public const LOG_PREFIX       = 'Week';
    /** Maximum number of rotated files to keep. */
    public const MAX_LOG_DURATION = 12;
    /** Max active file size in bytes before rotation. */
    public const MAX_LOG_SIZE     = 26214400;
    /** Default timezone identifier. */
    public const DEFAULT_TZ       = 'UTC';
    /** Enable gzip compression for rotated files. */
    public const ENABLE_GZIP      = false;

    /** Supported output formats. */
    public const FORMAT_TXT       = 'txt';
    public const FORMAT_CSV       = 'csv';
    public const FORMAT_TSV       = 'tsv';
    public const FORMAT_JSONL     = 'jsonl';

    /** Absolute path to the active log file. */
    private readonly string $file;
    /** Output format: txt|csv|tsv|jsonl. */
    private readonly string $fileType;
    /** PHP date() format for timestamps. */
    private string $timestampFormat;
    /** Ordered CSV/TSV columns; "Timestamp" auto-prepended if missing. */
    private array $csvColumns;
    /** ISO week number of last rotation. */
    private int $lastRotationWeek;
    /** Current timezone identifier. */
    private string $tz;
    /** @var array<callable(array):array> Processors to mutate records before writing. */
    private array $processors = [];

    /**
     * Customizable error messages.
     * @var array{illegalExtension:string,writeFailed:string,readFailed:string,pathInvalid:string}
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
     * @param string      $filename         Target file path (created if missing).
     * @param string      $fileType         txt|csv|tsv|jsonl|auto  ("auto" infers from $filename extension).
     * @param string      $timestampFormat  PHP date() format (default "Y-m-d H:i:s").
     * @param string[]    $csvColumns       CSV/TSV column order (Timestamp auto-added if absent).
     * @param bool        $logIP            Append public-looking client IP.
     * @param bool        $logBrowserOS     Append Browser and OS fields.
     * @param string|null $timezone         Timezone identifier (default UTC).
     *
     * @throws InvalidArgumentException     If filename extension or format is invalid.
     * @throws RuntimeException             If directory cannot be created or is not writable.
     */
    public function __construct(
        string $filename,
        string $fileType,
        string $timestampFormat = 'Y-m-d H:i:s',
        array $csvColumns = [],
        bool $logIP = false,
        bool $logBrowserOS = false,
        ?string $timezone = null
    ) {
        $allowed = ['txt', 'csv', 'tsv', 'log', 'jsonl'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            throw new InvalidArgumentException($this->errorMessages['illegalExtension'] . ' ' . implode(', ', $allowed));
        }

        $format = strtolower($fileType);
        if ($format === '' || $format === 'auto') {
            $format = $this->inferFormatFromExtension($ext);
        }
        if (!in_array($format, [self::FORMAT_TXT, self::FORMAT_CSV, self::FORMAT_TSV, self::FORMAT_JSONL], true)) {
            throw new InvalidArgumentException('Unsupported format: ' . $format);
        }

        $dir = dirname($filename);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            throw new RuntimeException(sprintf($this->errorMessages['pathInvalid'], $dir));
        }
        if (!is_writable($dir)) {
            throw new RuntimeException(sprintf($this->errorMessages['pathInvalid'], $dir));
        }
        if (!file_exists($filename)) {
            @touch($filename);
        }

        $this->file             = $filename;
        $this->fileType         = $format;
        $this->timestampFormat  = $timestampFormat;
        $this->csvColumns       = $csvColumns;
        $this->lastRotationWeek = (int)date('W');
        $this->tz               = $timezone ?? self::DEFAULT_TZ;
        $this->setTimezone($this->tz);

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

        if (($this->fileType === self::FORMAT_CSV || $this->fileType === self::FORMAT_TSV)
            && !empty($this->csvColumns) && filesize($this->file) === 0
        ) {
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
        $this->tz = $tz ?: self::DEFAULT_TZ;
        date_default_timezone_set($this->tz);
    }

    /**
     * Change output format at runtime (immutable in this PHP 8.3 build).
     *
     * @param string $format One of txt|csv|tsv|jsonl.
     * @return never
     *
     * @throws InvalidArgumentException For unsupported format.
     * @throws LogicException           Always thrown: format is immutable after construction.
     */
    public function setFormat(string $format): void
    {
        $format = strtolower($format);
        if (!in_array($format, [self::FORMAT_TXT, self::FORMAT_CSV, self::FORMAT_TSV, self::FORMAT_JSONL], true)) {
            throw new InvalidArgumentException('Unsupported format: ' . $format);
        }
        throw new LogicException('Format is immutable after construction in PHP 8.3 build.');
    }

    /**
     * Register a processor to mutate records before writing.
     *
     * @param callable(array):array $processor Function receiving and returning the record.
     * @return void
     */
    public function pushProcessor(callable $processor): void
    {
        $this->processors[] = $processor;
    }

    /**
     * Log a DEBUG record.
     *
     * @param string $msg Message string.
     * @param array  $ctx Context data merged under "context".
     *  @return void
     */
    public function debug(string $msg, array $ctx = []): void
    {
        $this->putLog(['level' => 'DEBUG', 'message' => $msg, 'context' => $ctx]);
    }

    /**
     * Log an INFO record.
     *
     * @param string $msg Message string.
     * @param array  $ctx Context data merged under "context".
     * @return void
     */
    public function info(string $msg, array $ctx = []): void
    {
        $this->putLog(['level' => 'INFO', 'message' => $msg, 'context' => $ctx]);
    }

    /**
     * Log a WARNING record.
     *
     * @param string $msg Message string.
     * @param array  $ctx Context data merged under "context".
     * @return void
     */
    public function warning(string $msg, array $ctx = []): void
    {
        $this->putLog(['level' => 'WARNING', 'message' => $msg, 'context' => $ctx]);
    }

    /**
     * Log an ERROR record.
     *
     * @param string $msg Message string.
     * @param array  $ctx Context data merged under "context".
     * @return void
     */
    public function error(string $msg, array $ctx = []): void
    {
        $this->putLog(['level' => 'ERROR', 'message' => $msg, 'context' => $ctx]);
    }

    /**
     * Append a record to the log file.
     *
     * @param array<string,mixed>|string $insert Scalar message or associative record.
     * @return void
     *
     * @throws RuntimeException On write failure.
     */
    public function putLog(array|string $insert): void
    {
        $this->maybeRotate();
        $this->cleanupOldLogs();

        $timestamp = date($this->timestampFormat);
        $record    = is_array($insert) ? $insert : ['message' => $insert];

        if ($this->needsIP($record)) {
            $record['IP'] = $this->getClientIP();
        }
        if ($this->needsUAOS($record)) {
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
            [$browser, $os] = $this->parseUAOS($ua);
            $record['Browser'] = $browser;
            $record['OS']      = $os;
        }

        foreach ($this->processors as $p) {
            $record = $p($record);
        }

        if ($this->fileType === self::FORMAT_TXT) {
            $line = $timestamp . ' ' . (isset($record['level']) ? '[' . $record['level'] . '] ' : '');
            $line .= (count($record) === 1 && isset($record['message']))
                ? (string)$record['message']
                : json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $this->appendLine($this->file, $line . PHP_EOL);
        } elseif ($this->fileType === self::FORMAT_JSONL) {
            $payload = ['@ts' => $timestamp] + $record;
            $this->appendLine($this->file, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
        } else {
            $delimiter = $this->fileType === self::FORMAT_CSV ? ',' : "\t";
            $fh = fopen($this->file, 'ab');
            if ($fh === false) {
                throw new RuntimeException(sprintf($this->errorMessages['writeFailed'], $this->file));
            }
            try {
                if (!flock($fh, LOCK_EX)) {
                    throw new RuntimeException(sprintf($this->errorMessages['writeFailed'], $this->file));
                }
                if (filesize($this->file) === 0 && !empty($this->csvColumns)) {
                    fputcsv($fh, $this->prepareHeaderColumns(), $delimiter, '"');
                }
                $row = $this->rowForCsv($record);
                if (fputcsv($fh, $row, $delimiter, '"') === false) {
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
     * @return string File contents.
     *
     * @throws RuntimeException On read failure.
     */
    public function getLog(): string
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
     * @param int $lines Number of lines from the end.
     * @return string Tail content ending with newline.
     */
    public function tail(int $lines = 200): string
    {
        $f = @fopen($this->file, 'rb');
        if (!$f) return '';
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
            if ($filesize === 0) break;
        }
        fclose($f);
        $parts = explode("\n", $buffer);
        return implode("\n", array_slice($parts, -$lines)) . "\n";
    }

    /**
     * Whether to include IP in the current record.
     *
     * @param array<string,mixed> $record Current record.
     * @return bool True if IP should be included.
     */
    private function needsIP(array $record): bool
    {
        return in_array('IP', $this->csvColumns, true) || array_key_exists('IP', $record);
    }

    /**
     * Whether to include Browser/OS in the current record.
     *
     * @param array<string,mixed> $record Current record.
     * @return bool True if Browser/OS should be included.
     */
    private function needsUAOS(array $record): bool
    {
        $need = (in_array('Browser', $this->csvColumns, true) && in_array('OS', $this->csvColumns, true));
        return $need || (isset($record['Browser']) && isset($record['OS']));
    }

    /**
     * Append a line to a file with locking.
     *
     * @param string $file Target file.
     * @param string $line Line to append (may include newline).
     * @return void
     *
     * @throws RuntimeException On write failure.
     */
    private function appendLine(string $file, string $line): void
    {
        $fh = fopen($file, 'ab');
        if ($fh === false) {
            throw new RuntimeException(sprintf($this->errorMessages['writeFailed'], $file));
        }
        try {
            if (!flock($fh, LOCK_EX)) {
                throw new RuntimeException(sprintf($this->errorMessages['writeFailed'], $file));
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
     * @param array<string,mixed> $record Record data.
     * @return array<int,mixed> Ordered values for CSV/TSV row.
     */
    private function rowForCsv(array $record): array
    {
        if (!empty($this->csvColumns)) {
            $row = [];
            foreach ($this->prepareHeaderColumns() as $col) {
                if ($col === 'Timestamp') {
                    $row[] = date($this->timestampFormat);
                } else {
                    $val = $record[$col] ?? '';
                    $row[] = is_scalar($val) ? $val : json_encode($val, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                }
            }
            return $row;
        }
        $flat = [date($this->timestampFormat)];
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
     * @return void
     */
    private function writeCsvHeader(string $file, string $delimiter): void
    {
        $fh = fopen($file, 'ab');
        if ($fh) {
            try {
                if (flock($fh, LOCK_EX)) {
                    fputcsv($fh, $this->prepareHeaderColumns(), $delimiter, '"');
                }
            } finally {
                flock($fh, LOCK_UN);
                fclose($fh);
            }
        }
    }

    /**
     * Rotate active log when size/week/weekday criteria match.
     *
     * @return void
     */
    private function maybeRotate(): void
    {
        $currentWeek = (int)date('W');
        $today = date('l');
        $sizeExceeded = @filesize($this->file) !== false && filesize($this->file) >= self::MAX_LOG_SIZE;
        $weekdayHit   = (self::ROTATE_DAY && $today === self::ROTATE_DAY && $this->lastRotationWeek !== $currentWeek);
        $weekChanged  = ($this->lastRotationWeek !== $currentWeek);

        if ($sizeExceeded || $weekdayHit || $weekChanged) {
            $rotated = $this->buildRotatedName($this->file, $currentWeek, date('Y'));
            @rename($this->file, $rotated);
            @touch($this->file);
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
     * @param string $file Base file path.
     * @param int    $week ISO week number.
     * @param string $year Year string.
     * @return string Rotated filename.
     */
    private function buildRotatedName(string $file, int $week, string $year): string
    {
        $dir = dirname($file);
        $base = basename($file);
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        $name = preg_replace('/\.' . preg_quote($ext, '/') . '$/', '', $base);
        $counter = 1;
        do {
            $candidate = sprintf('%s/%s %s-%d-%s.%d.%s', $dir, $name, self::LOG_PREFIX, $week, $year, $counter, $ext);
            $counter++;
        } while (file_exists($candidate));
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
    private function cleanupOldLogs(): void
    {
        $dir = dirname($this->file);
        $name = basename($this->file);
        $pattern = sprintf('%s/%s %s-*', $dir, preg_replace('/\.[^.]+$/', '', $name), self::LOG_PREFIX);
        $logFiles = glob($pattern) ?: [];
        if (!$logFiles) return;

        usort($logFiles, static function (string $a, string $b): int {
            return (@filemtime($a) ?: 0) <=> (@filemtime($b) ?: 0);
        }); // oldest first

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
     * @param string $ua User agent string.
     * @return array{0:string,1:string} [browser, os].
     */
    private function parseUAOS(string $ua): array
    {
        if ($ua === '') return ['CLI/Unknown', 'Unknown'];

        $browser = match (true) {
            str_contains($ua, 'Firefox')                                 => 'Firefox',
            str_contains($ua, 'Chrome') && !str_contains($ua, 'Chromium') => 'Chrome',
            str_contains($ua, 'Safari') && !str_contains($ua, 'Chrome')   => 'Safari',
            str_contains($ua, 'MSIE') || str_contains($ua, 'Trident')     => 'Internet Explorer',
            default                                                       => 'Others',
        };

        $os = match (true) {
            str_contains($ua, 'Windows NT')                      => 'Windows',
            str_contains($ua, 'Mac OS X')                        => 'MacOS',
            str_contains($ua, 'Linux')                           => 'Linux',
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Android')                         => 'Android',
            default                                              => 'Others',
        };

        return [$browser, $os];
    }

    /**
     * Resolve public-looking client IP from common proxy headers.
     *
     * @return string IP address or 'UNKNOWN'.
     */
    public function getClientIP(): string
    {
        $keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        $candidates = [];
        foreach ($keys as $k) {
            if (!empty($_SERVER[$k])) {
                foreach (explode(',', $_SERVER[$k]) as $ip) {
                    $ip = trim($ip);
                    if ($ip !== '') $candidates[] = $ip;
                }
            }
        }
        foreach ($candidates as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return $ip;
        }
        return $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
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
