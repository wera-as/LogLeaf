# CHANGELOG



## Version 1.6 (November 27, 2023)
### Added
- Added support for PHP 8.3

### Tweaks
- Adjusted the `getClientIP()` function to be more effective.



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
  - Ability to define paths for external libraries (`Mobile_Detect` and `Browser.php`) directly from the constructor.
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
