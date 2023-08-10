# CHANGELOG



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
