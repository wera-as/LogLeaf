# File Download Logger

The File Download Logger is a simple, lightweight, and highly efficient PHP class designed for logging file downloads to a text file. The class creates timestamped entries for each download event and is capable of returning all log data in a structured format for potential data analysis purposes.

## Features

- Set custom log file path
- Define custom timestamp formats
- Append new log entries with timestamps
- Retrieve all log entries

## Requirements

- PHP 5.6 or higher

## Installation

Simply clone this repository or download the Logger.php file and include it in your PHP project:
```bash
git clone https://github.com/username/file-download-logger.git
```

And include it in your PHP script:
```php
include_once 'Logger.php';
```

## Usage

First, you need to instantiate the Logger class with the name of your log file:
```php
$logger = new Logger("downloads.log");
```

If you wish to use a custom timestamp format, you can set it using the `setTimestampFormat` method:
```php
$logger->setTimestampFormat('Y-m-d H:i:s'); // Set timestamp format (optional, 'Y-m-d H:i:s' is the default)
```

When a file is downloaded, add an entry to the log file using the `putLog` method:
```php
$logger->putLog('File abc.jpg has been downloaded'); // Add a new entry
```

If you want to retrieve all the logs as a string, you can use the `getLog` method:
```php
echo $logger->getLog(); // Output all logs
```

## Errors and Exceptions

The Logger class will throw an exception in the following cases:

- If the log file cannot be read or written to.
- If you attempt to set an empty file name.

## Contributing

Feel free to fork the project and submit your contributions via a pull request.

## License

The File Download Logger is open-sourced software licensed under the MIT license.
