* # CHANGELOG

  ## Version 3.0 (November 3, 2025)

  This is a major rewrite focused on PSR-3 compliance, modernization, and flexibility.

  ### Added

  - **PSR-3 Compliance:** The class now implements `Psr\Log\LoggerInterface` and all its methods (`emergency`, `alert`, `critical`, `notice`, `log`).
  - **Log Level Filtering:** A new `level` option in the constructor allows setting a minimum log level (e.g., `warning`) to record.
  - **Context Interpolation:** Messages are now interpolated as per PSR-3 (e.g., `log('User {id}', ['id' => 123])`).
  - **Exception Handling:** PSR-3 standard exception handling via `['exception' => $e]` in context.
  - **Standalone Builds:** PSR-3 interfaces are embedded in each file, removing the need for `composer require psr/log`.
  - **PHP Version Builds:** Provides builds for PHP 5.6, 7.4, and 8.3, using modern syntax (typed properties, etc.) where available.

  ### Changed

  - **BREAKING: Constructor:** The constructor signature is completely changed. It now accepts `(string $filename, array $options = [])`. All configuration (e.g., `fileType`, `timestampFormat`, `csvColumns`, `maxLogSize`) is now passed via the `$options` array.
  - **BREAKING: `putLog()`:** This method is now intended for internal use. All logging should be done via the PSR-3 methods (`debug`, `info`, `warning`, `error`, etc.).
  - **Configuration:** `const` values (like `MAX_LOG_SIZE`, `ROTATE_DAY`) are now internal properties set by the constructor options, making them instance-specific.
  - `setFormat()`: This method is now deprecated. The file format should be set immutably in the constructor options.

  ### Removed

  - **BREAKING: IP/UA Detection:** All built-in IP and User-Agent detection (`logIP`, `logBrowserOS`, `getClientIP()`, `parseUAOS()`) has been **removed**. This logic is now decoupled from the logger.
  - The old 7-argument constructor is removed.

  ### Migration Notes

  - **Constructor:** You *must* update your constructor call.
    - **Old (v2):** `new LogLeaf('file.log', 'txt', 'Y-m-d H:i:s', [], true, true);`
    - **New (v3):** `new LogLeaf('file.log', ['fileType' => 'txt', 'timestampFormat' => 'Y-m-d H:i:s']);`
  - **IP/Browser Logging:** To log IP, Browser, or OS, you *must* use `pushProcessor()` to add this data yourself. See `README.md` for an example.
  - **Logging Data:**
    - **Old (v2):** `putLog('My message');`
    - **New (v3):** `info('My message');`
    - **Old (v2):** `putLog(['File' => 'report.pdf']);`
    - **New (v3):** `info('Some log message', ['File' => 'report.pdf']);`

  ## Version 2.0 (October 6, 2025)

  ### Added

  - Multi-format output: TXT, CSV, TSV, and JSONL are supported uniformly. The format can be set explicitly in the constructor or inferred via `fileType="auto"` from the filename extension.
  - Processors: `pushProcessor(callable $processor)` allows mutation/enrichment of records before write.
  - Level helpers: `debug()`, `info()`, `warning()`, `error()` convenience methods.
  - Tail helper: `tail(int $lines = 200)` to efficiently read the last N lines of large files.
  - Timezone control: `setTimezone()` and `DEFAULT_TZ` for consistent timestamps and rotation checks.
  - Gzip option: `ENABLE_GZIP` constant to compress rotated logs (`.gz`).
  - Header management: CSV/TSV now auto-prepend a `Timestamp` column when absent and rewrite headers after rotation.
  - Path validation & bootstrap: Ensures parent directory exists (creates recursively with 0775) and file is created on first use.
  - PHP 8.3 build: Uses `readonly` properties and modern language features; strong typing throughout.

  ### Changed

  - Rotation: Weekly (by ISO week), specific weekday (`ROTATE_DAY`), or size-based (`MAX_LOG_SIZE`)—whichever triggers first. Rotated filenames include week, year, and an incremental suffix and are prefixed with `LOG_PREFIX`.
  - Retention: Old rotated files are pruned to `MAX_LOG_DURATION` with oldest-first deletion; optional gzip retained alongside policy.
  - CSV/TSV writing: Column order strictly respected; headers are written when the file is new or after rotation. Safer quoting and escaping.
  - Concurrency: All writes now use `flock(LOCK_EX)` to prevent interleaved log lines under load.
  - Client info: Built-in IP and UA/OS parsing; when enabled, public-looking IPs are preferred from proxy headers.
  - Constructor: Allows `fileType="auto"`; validates extensions (txt|csv|tsv|log|jsonl).

  ### Fixed

  - File not created on first log write in some environments.
  - Duplicate/omitted CSV headers after rotation.
  - Potential race conditions when multiple writers append simultaneously.
  - `getClientIP()` now prefers public IPs and falls back gracefully.
  - Consistent timestamp handling across formats.

  ### Removed

  - Reliance on external UA detection libraries (kept since 1.5) — clarified and finalized for 2.0.

  ### Deprecated

  - PHP 8.3 build: `setFormat()` is intentionally immutable and will throw; choose the format at construction time.

  ### Migration Notes

  - If you previously mutated the output format at runtime, construct separate LogLeaf instances per format (especially on PHP 8.3 build where the format is immutable).
  - If you depended on external UA libraries, remove those dependencies; built-in parsing remains intentionally simple.
  - For CSV/TSV users: ensure your downstream tooling expects an initial `Timestamp` column; you can reorder by supplying explicit columns in the constructor.
  - Ensure the parent directory of your log file is writable by the PHP process. The logger will create it if missing.

  ## Version 1.6 (November 27, 2023)

  ### Added

  - Added support for PHP 8.3

  ### Update

  - Adjusted the `getClientIP()` function to be more effective.
  - Adjusted the `__construct` to allow for empty filenames.

  ## Version 1.5.1 (August 21, 2023)

  ### Added

  - Added support for tsv files.
  - Added error handling for illegal file extensions.

  ## Version 1.5 (August 11, 2023)

  ### Removed

  - Removed the inclusion of external libraries as they are not needed.

  ## Version 1.4.2 (August 11, 2023)

  ### Optimized

  - Improved the `putLog()` function to handle data more efficiently, ensuring consistent log structure regardless of the logging flags.
  - Enhanced log rotation mechanism to accommodate varying log file names while ensuring that log history is maintained according to defined criteria.

  ### Fixed

  - Resolved an issue where the IP address was not being logged despite the flag being set.
  - Addressed an issue where the timestamp was being duplicated in log entries.

  ## Version 1.4.1 (August 11, 2023)

  ### Added

  - Restructured the folders
  - Implemented advanced IP address detection to account for proxies, load balancers, etc.
  - Introduced log rotation mechanism to manage large log files.
  - Logs are rotated weekly.
  - Log naming convention includes week of the year and year.
  - Maximum retention period for logs set to 3 months.
  - Files exceeding 25MB are split with an incremental suffix.
  - Added constants for easily configurable log rotation settings.

  ## Version 1.4 (August 11, 2023)

  ### Added

  - Introduced a mechanism to define custom error messages via a `define` method.

  ### Updated

  - Replaced hardcoded error messages with references to a new `$errorMessages` property to allow for custom error definitions.
  - The Logger class's error handling mechanism was enhanced to utilize the custom error messages.

  ## Version 1.3 (August 10, 2023)

  ### Added

  - Introduced advanced browser and OS detection using external libraries.
  - Ability to define paths for external libraries (Mobile_Detect and Browser.php) directly from the constructor.
  - Provided error handling for cases where advanced detection fails, reverting to a basic method.

  ### Updated

  - The Logger class to be more flexible, allowing for the capture of IP addresses, browser details, and operating system information.
  - Enhanced error handling and improved CSV column definition checks.
  - Modified the Logger class to handle cases where CSV columns might not be provided.
  - Updated the `putLog` method to handle both strings and arrays, enhancing flexibility.

  ### Documentation

  - Updated the Markdown documentation to reflect new features and usage examples.
  - Provided more explicit instructions for installation, especially regarding the inclusion of external libraries.
  - Usage examples were expanded to cover both txt and csv logging scenarios.

  ### Analysis

  - Conducted an in-depth code analysis to identify potential issues and rectified them.

  ### Fixes

  - Resolved potential issues with `$insert` where it might not be an array when logging to a CSV.
  - Fixed the potential problem of `$this->csvColumns` being empty, allowing the class to handle such cases gracefully.
