# LogLeaf

**LogLeaf** is a lightweight, PSR-3 compliant PHP logging class supporting multiple output formats — TXT, CSV, TSV, and JSONL.

Version 3.0 introduces a modern, flexible options-based constructor, full PSR-3 compliance, and decouples environment-specific logic (like IP/UA detection) for use in any application, web or CLI.



## ✨ Features

- ✨ **PSR-3 Compliant:** Full implementation of `Psr\Log\LoggerInterface` for maximum compatibility.
- ✨ **Log Level Filtering:** Set a minimum log level (e.g., `warning`) in options to reduce noise in production.
- 🧩 **Extensible Processing:** Add custom data (like IP, User-Agent, or User ID) to every log entry using `pushProcessor()`.
- 🔄 **Automatic Rotation:** Log rotation by week or file size, retaining logs for a configurable duration.
- 📄 **Multi-Format Output:** Write to `.txt`, `.csv`, `.tsv`, or `.jsonl` files.
- 🕒 **Custom Timestamps:** Set any PHP `date()` format for your timestamps.
- 🗄️ **Custom Columns:** Define and order columns for CSV/TSV output. `Timestamp` is auto-added if missing.
- 🔒 **Safe Writes:** Thread-safe file writes with locking.
- 💬 **Custom Errors:** Define custom error messages using the `define()` method.
- 🤐 **Gzip Compression:** Optional GZIP compression for rotated logs.



## 🧩 Requirements

- PHP 5.6 or higher (fully compatible with PHP 8.3+)
- `ZipArchive` (for the snippet's zip feature)



## ⚙️ Installation

Clone or download the repository:

```bash
git clone [https://github.com/wera-as/LogLeaf.git](https://github.com/wera-as/LogLeaf.git)
```

Then include the version matching your PHP environment:

```php
// PHP 5.6
include_once 'LogLeaf/logleaf_v3_php5.6.php';

// PHP 7.4+
include_once 'LogLeaf/logleaf_v3_php7.4.php';

// PHP 8.3+
include_once 'LogLeaf/logleaf_v3_php8.3.php';
```



## 🚀 Usage

### Basic Example (TXT)

The constructor now takes a filename and a single `$options` array. Use the PSR-3 methods (`info`, `error`, etc.) to log.

```php
// 1. Set options
$options = [
    'fileType'        => 'txt',
    'level'           => 'debug', // Log all messages
    'timestampFormat' => 'Y-m-d H:i:s'
];

// 2. Create the logger
$logger = new LogLeaf('app.log', $options);

// 3. Log messages
$logger->info('File abc.jpg has been downloaded');
$logger->error('Failed to process payment', ['order_id' => 123]);
```



### Advanced Example (JSONL with IP/UA Processor)

This example shows how to add IP Address and User-Agent data using the `pushProcessor()` method.

```php
// 1. Define options
$options = [
    'fileType'   => 'jsonl',
    'level'      => 'info', // Only log info and above
    'csvColumns' => ['Timestamp', 'level', 'message', 'IP', 'Browser', 'OS', 'product', 'count']
];

$logger = new LogLeaf('downloads.jsonl', $options);

// 2. Add a processor to automatically add IP and User Agent
// (Requires helper functions like getLogLeafClientIP() and parseLogLeafUAOS())
$logger->pushProcessor(function(array $record) {
    $record['IP'] = getLogLeafClientIP(); // From snippet
    
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string)$_SERVER['HTTP_USER_AGENT'] : '';
    list($browser, $os) = parseLogLeafUAOS($ua); // From snippet
    $record['Browser'] = $browser;
    $record['OS']      = $os;

    return $record; // Always return the modified record
});

// 3. Log a message
$logger->info('Generated new file', [
    'product' => 'My Product',
    'archive' => 'dnh_download_123.zip',
    'count'   => 5
]);

// 4. This message will be skipped because its level is 'debug'
$logger->debug('Checking file permissions...');
```



### CSV Example

The logger will automatically use the `csvColumns` from the options to build the header and order the data.

```php
$options = [
    'fileType'   => 'csv',
    'csvColumns' => ['Timestamp', 'level', 'message', 'File']
];

$logger = new LogLeaf('reports.csv', $options);

$logger->info('Report generated', ['File' => 'report.pdf']);
```



## 🕒 Reading Logs

```php
// Returns entire log as a string
echo $logger->getLog(); 

// Retrieve the last N lines efficiently:
echo $logger->tail(100); // Returns the last 100 lines
```



## ⚠️ Error Handling

Exceptions are thrown if:

- Log file or directory is unwritable.
- Unsupported file extension or format.
- File read/write failure occurs.

Define custom error messages:

```php
$logger->define('writeFailed', 'Cannot write to the log — check permissions.');
```



## 🧰 Version Highlights (3.0.0)

- **PSR-3 Compliance:** The class now implements `Psr\Log\LoggerInterface` and all its methods (`debug`, `info`, `error`, etc.).
- **Flexible Constructor:** The constructor now takes a single `$options = []` array instead of 7 arguments, making it cleaner and more extensible.
- **Decoupled Logic:** `logIP` and `logBrowserOS` are removed from the constructor. This logic is now handled via `pushProcessor()`, making the logger environment-agnostic (works in CLI) and easier to test.
- **Log Level Filtering:** You can now set a minimum `level` in the options.
- **Code Modernization:** Refactored builds for PHP 5.6 and 8.3, including typed properties and `readonly` where appropriate.



## 🤝 Contributing

Contributions are welcome! Fork the repo, submit PRs, or open issues for feature suggestions.



## 📜 License

MIT License — © 2025 Wera AS
