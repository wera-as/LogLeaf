# File Download Logger

The File Download Logger is a versatile and adaptable PHP class designed to log file download events to either a text (.txt) or a comma-separated value (.csv) file. With each download event, the Logger generates a timestamped entry. Additionally, it can capture IP addresses, browser details, and operating system information. The Logger supports PHP 5.6 and onwards.

## Features

- Customize the log file path as per your needs.
- Log to a text (.txt) or a CSV (.csv) file.
- Set a custom timestamp format to fit your application's requirements.
- Append new log entries with accurate timestamps for chronological tracking.
- Retrieve all log entries for review or analysis.
- Define custom CSV column names when logging to a CSV file.
- Optionally log IP addresses of users downloading files.
- Capture browser and operating system details for each download event.
- Choose between basic (in-house) and advanced (using external libraries) detection methods.

## Requirements

- PHP 5.6 or higher.
- For advanced browser and OS detection:
  - [Mobile Detect](https://github.com/serbanghita/Mobile-Detect) library.
  - [Browser.php](https://github.com/cbschuld/Browser.php) library.

## Installation

To install the File Download Logger:

1. Clone this repository or download the Logger file (`logger56.php` for PHP 5.6, `logger.php` for PHP 7.0+):

```bash
git clone https://github.com/wera-as/file-download-logger.git
```

2. Include the Logger file in your PHP script:

For PHP 5.6:

```php
include_once 'logger56.php';
```

For PHP 7.0+:

```php
include_once 'logger.php';
```

3. (Optional) For advanced detection, download and include the following libraries:

   - [Mobile Detect](https://github.com/serbanghita/Mobile-Detect)
   - [Browser.php](https://github.com/cbschuld/Browser.php)

## Usage

Instantiate the Logger class with your log file's name, file type (either 'txt' or 'csv'), and, if desired, specify the timestamp format, CSV columns, and flags for IP and Browser/OS logging.

For TXT logging:

```php
$loggerTxt = new Logger("downloads.txt", 'txt', 'Y-m-d H:i:s', [], true, true, false);
```

Here, it will log using basic detection.

```php
$loggerTxtAdvanced = new Logger("downloads.txt", 'txt', 'Y-m-d H:i:s', [], true, true, true, 'path/to/MobileDetect.php', 'path/to/Browser.php');
```

Here, it will log using advanced detection.

For CSV logging:

```php
$csvColumns = ['Timestamp', 'IP', 'Browser', 'OS', 'File'];
$loggerCsv = new Logger("downloads.csv", 'csv', 'Y-m-d H:i:s', $csvColumns, true, true, false);
```

This will create a CSV logger with basic detection.

```php
$loggerCsvAdvanced = new Logger("downloads.csv", 'csv', 'Y-m-d H:i:s', $csvColumns, true, true, true, 'path/to/MobileDetect.php', 'path/to/Browser.php');
```

This will create a CSV logger using advanced detection.

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
- Choosing advanced detection without having the required external libraries.

## Contributing

Your contributions are always welcome! Feel free to fork this project and submit enhancements via a pull request.

## License

The File Download Logger is open-source software under the MIT license.
