# LogLeaf

LogLeaf is a lightweight, modular PHP logging class supporting multiple output formats — **TXT**, **CSV**, **TSV**, and **JSONL** — with optional IP, browser, and OS detection. Version 2.0 introduces native support for **PHP 5.6 → 8.3+**, automatic log rotation, and enhanced file handling and structure.



## ✨ Features

* **Automatic log rotation** by week or file size, retaining logs for up to 12 rotations (≈3 months).
* **Multi-format output:** write to `.txt`, `.csv`, `.tsv`, or `.jsonl` files.
* **Custom timestamp format** via `setTimestampFormat()`.
* **Accurate IP detection**, including proxies and load balancers.
* **Browser/OS parsing** via native user-agent analysis.
* **Customizable CSV/TSV columns** — `Timestamp` is auto-added if missing.
* **Custom error message definitions** using the `define()` method.
* **Optional GZIP compression** for rotated logs.
* **Thread-safe file writes** with locking.



## 🧩 Requirements

* PHP **5.6 or higher** (fully compatible with PHP 8.3+)



## ⚙️ Installation

Clone or download the repository:

```bash
git clone https://github.com/wera-as/LogLeaf.git
```

Then include the version matching your PHP environment:

```php
// PHP 5.6
include_once 'php56/LogLeaf.php';

// PHP 7.0+
include_once 'php70/LogLeaf.php';

// PHP 8.3+
include_once 'php83/LogLeaf.php';
```



## 🚀 Usage

### Basic Example (TXT)

```php
$logger = new LogLeaf('downloads.txt', 'txt', 'Y-m-d H:i:s', [], true, true);
$logger->putLog('File abc.jpg has been downloaded');
```

### CSV Example with Columns

```php
$columns = ['Timestamp', 'IP', 'Browser', 'OS', 'File'];
$logger = new LogLeaf('downloads.csv', 'csv', 'Y-m-d H:i:s', $columns, true, true);
$logger->putLog(['File' => 'report.pdf']);
```

### JSONL Example (v2.0+)

```php
$logger = new LogLeaf('logs.jsonl', 'jsonl');
$logger->info('Process completed successfully', ['module' => 'sync']);
```



## 🕒 Reading Logs

```php
echo $logger->getLog(); // Returns entire log as a string
```

Retrieve the last N lines efficiently:

```php
echo $logger->tail(100); // Returns the last 100 lines
```



## ⚠️ Error Handling

Exceptions are thrown if:

* Log file or directory is unwritable.
* Unsupported file extension or format.
* File read/write failure occurs.

Define custom error messages:

```php
$logger->define('writeFailed', 'Cannot write to the log — check permissions.');
```



## 🧰 Version Highlights (2.0.0)

* Unified codebase across PHP 5.6–8.3.
* Added **JSONL** structured logging support.
* Introduced **readonly properties** for immutability in PHP 8.3 build.
* Enhanced file creation logic (auto-creates missing logs).
* Simplified constructor signature and validation.
* Optimized tail-reading and CSV row construction.
* Improved rotation naming consistency.



## 🤝 Contributing

Contributions are welcome! Fork the repo, submit PRs, or open issues for feature suggestions.



## 📜 License

**MIT License** — © 2025 [Wera AS](https://github.com/wera-as)
