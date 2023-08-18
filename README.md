![LogLeaf Logo](https://raw.githubusercontent.com/wera-as/LogLeaf/fdb8945c5ea09841bd5826fb7fab80dbfb312d06/img/logleaf_logo.svg)

LogLeaf is a versatile and adaptable PHP class designed to log file download events to either a text (.txt) or a comma-separated value (.csv) file. With each download event, the Logger generates a timestamped entry. Additionally, it can capture IP addresses, browser details, and operating system information. The Logger supports PHP 5.6 and onwards, and offers enhanced error customization.

## Features


- Automatic log rotation based on both time and file size. Logs are named using a pattern like `Week 22 2023 2` where the last number increments if a log file for that week already exists. Logs are retained for a maximum of 3 months by default.
- Customize the log file path as per your needs.
- Log to a text (.txt) or a CSV (.csv) file.
- Set a custom timestamp format to fit your application's requirements.
- Append new log entries with accurate timestamps for chronological tracking.
- Retrieve all log entries for review or analysis.
- Define custom CSV column names when logging to a CSV file.
- Optionally log IP addresses of users downloading files using an improved IP detection mechanism that accounts for proxies and load balancers.
- Capture browser and operating system details for each download event.
- Define custom error messages for specific scenarios to better suit your application's requirements.

## Requirements

- PHP 5.6 or higher.

## Installation

To install LogLeaf:

1. Clone this repository or download the Logger file (`php56/LogLeaf.php` for PHP 5.6, `php70/LogLeaf.php` for PHP 7.0+):

```bash
git clone https://github.com/wera-as/LogLeaf.git
```

2. Include the Logger file in your PHP script:

For PHP 5.6:

```php
include_once 'php56/LogLeaf.php';
```

For PHP 7.0+:

```php
include_once 'php70/LogLeaf.php';
```

## Usage

Instantiate the LogLeaf class with your log file's name, file type (either 'txt' or 'csv'), and, if desired, specify the timestamp format, CSV columns, and flags for IP and Browser/OS logging.

For TXT logging:

```php
$loggerTxt = new LogLeaf("downloads.txt", 'txt', 'Y-m-d H:i:s', [], true, true);
```

For CSV logging:

```php
$csvColumns = ['Timestamp', 'IP', 'Browser', 'OS', 'File'];
$loggerCsv = new LogLeaf("downloads.csv", 'csv', 'Y-m-d H:i:s', $csvColumns, true, true);
```

If a custom timestamp format is required, set it using the setTimestampFormat method:

```php
$logger->setTimestampFormat('Y-m-d H:i:s'); // Optional, 'Y-m-d H:i:s' is the default
```

When a file is downloaded, log the event using the putLog method:

```php
$logger->putLog('File abc.jpg has been downloaded');
```

Retrieve all log entries as a string using the getLog method:

```php
echo $logger->getLog(); // Output all logs
```

## Errors and Exceptions

The Logger class will throw exceptions in the following scenarios:

- Inability to read or write to the log file.
- Providing an empty file name.
- Mismatch between data provided in the `putLog` method and the specified CSV columns.
- Allows users to define custom error messages for specific error scenarios, offering a more tailored logging experience.

## Contributing

Your contributions are always welcome! Feel free to fork this project and submit enhancements via a pull request.

## License

LogLeaf is open-source software under the MIT license.