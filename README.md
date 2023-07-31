# File Download Logger

The File Download Logger is a powerful and adaptable PHP class that enables you to log file download events to either a text (.txt) file or a comma-separated value (.csv) file. With each download event, the Logger generates a timestamped entry. It's also capable of retrieving the entire log data in a structured format for subsequent data analysis or auditing purposes. The Logger supports PHP 5.6 and onwards.

## Features

- Customize the log file path as per your needs.
- Choose to log to a text (.txt) or a CSV (.csv) file.
- Set a custom timestamp format to suit your application's requirements.
- Append new log entries with accurate timestamps for chronological order.
- Retrieve the complete log entries for review or analysis.
- Define custom CSV column names when logging to a CSV file.

## Requirements

- PHP 5.6 or higher.

## Installation

To install the File Download Logger, clone this repository or download the Logger file (`logger56.php` for PHP 5.6, `logger.php` for PHP 7.0+) and include it in your PHP project:

```bash
git clone https://github.com/username/file-download-logger.git
```

Then, include the Logger file in your PHP script:

For PHP 5.6:

```php
include_once 'logger56.php';
```

For PHP 7.0+:

```php
include_once 'logger.php';
```

## Usage

First, instantiate the Logger class with your log file's name, the file type (either 'txt' or 'csv'), and optionally, your preferred timestamp format and CSV columns:

```php
$logger = new Logger("downloads.log", 'txt');

$csvColumns = ['Timestamp', 'File Name', 'Additional Info'];
$csvLogger = new Logger('downloads.csv', 'csv', 'Y-m-d H:i:s', $csvColumns);
```

If you need a custom timestamp format, set it using the `setTimestampFormat` method:

```php
$logger->setTimestampFormat('Y-m-d H:i:s'); // Optional, 'Y-m-d H:i:s' is the default
```

When a file is downloaded, log an entry using the `putLog` method. If you're logging to a CSV file, pass an array of data that matches the CSV columns:

```php
$logger->putLog('File abc.jpg has been downloaded'); // Add a new entry
$csvLogger->putLog(['2023-05-20 14:54:21', 'File abc.jpg', 'Additional download data']); // Add a new entry to CSV file
```

To retrieve all the logs as a string, use the `getLog` method:

```php
echo $logger->getLog(); // Output all logs
```

## Errors and Exceptions

The Logger class will throw an exception in the following scenarios:

- If the log file cannot be read or written to.
- If an empty file name is provided.
- If the data provided in the `putLog` method doesn't match the specified CSV columns.

## Contributing

Contributions are welcome! Please feel free to fork this project and submit your enhancements via a pull request.

## License

The File Download Logger is open-source software licensed under the MIT license.
