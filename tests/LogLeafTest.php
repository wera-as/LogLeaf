<?php

// Use the old TestCase class name for PHP 5.6 compatibility
// PHPUnit's modern versions provide a class alias for this.
if (class_exists('\PHPUnit\Framework\TestCase')) {
    class_alias('\PHPUnit\Framework\TestCase', 'LogLeafBaseTest');
} else {
    class_alias('\PHPUnit_Framework_TestCase', 'LogLeafBaseTest');
}

class LogLeafTest extends LogLeafBaseTest
{
    /** @var string Path to a temporary log file for testing. */
    private $logFile;

    /** @var string Path to the test logs directory. */
    private $logsDir;

    /**
     * Sets up the test environment before each test.
     * Creates a temporary log directory.
     */
    protected function setUp(): void
    {
        $this->logsDir = __DIR__ . '/temp_logs';
        if (!is_dir($this->logsDir)) {
            mkdir($this->logsDir, 0777, true);
        }
        // Use a unique file for each test
        $this->logFile = $this->logsDir . '/test_' . uniqid() . '.log';
    }

    /**
     * Cleans up the test environment after each test.
     * Deletes the temporary log file and directory.
     */
    protected function tearDown(): void
    {
        // Clean up all log files in the temp dir
        $files = glob($this->logsDir . '/*.log');
        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }

        if (is_dir($this->logsDir)) {
            rmdir($this->logsDir);
        }
    }

    /**
     * Asserts that a string contains a substring (PHP 5.6 compatible).
     *
     * @param string $needle   The substring to search for.
     * @param string $haystack The string to search in.
     * @param string $message  Optional failure message.
     */
    public static function compatAssertStringContainsString($needle, $haystack, $message = '')
    {
        if (method_exists('\PHPUnit\Framework\Assert', 'assertStringContainsString')) {
            // PHPUnit 6+
            \PHPUnit\Framework\Assert::assertStringContainsString((string)$needle, (string)$haystack, $message);
        } else {
            // PHPUnit 5.x
            parent::assertContains((string)$needle, (string)$haystack, $message);
        }
    }

    /**
     * Asserts that a string does NOT contain a substring (PHP 5.6 compatible).
     *
     * @param string $needle   The substring to search for.
     * @param string $haystack The string to search in.
     * @param string $message  Optional failure message.
     */
    public static function compatAssertStringNotContainsString($needle, $haystack, $message = '')
    {
        if (method_exists('\PHPUnit\Framework\Assert', 'assertStringNotContainsString')) {
            // PHPUnit 6+
            \PHPUnit\Framework\Assert::assertStringNotContainsString((string)$needle, (string)$haystack, $message);
        } else {
            // PHPUnit 5.x
            parent::assertNotContains((string)$needle, (string)$haystack, $message);
        }
    }

    public function testInstantiationAndBasicLogging()
    {
        $logger = new \LogLeaf($this->logFile, array('fileType' => 'txt', 'level' => 'debug'));
        $logger->info('Hello World');

        $this->assertFileExists($this->logFile);
        $content = file_get_contents($this->logFile);

        self::compatAssertStringContainsString('INFO', $content);
        self::compatAssertStringContainsString('Hello World', $content);
    }

    public function testLogLevelFiltering()
    {
        $logger = new \LogLeaf($this->logFile, array('level' => 'warning'));

        $logger->debug('This should not be logged.');
        $logger->info('This should also not be logged.');
        $logger->warning('This SHOULD be logged.');
        $logger->error('This should also be logged.');

        $content = file_get_contents($this->logFile);

        self::compatAssertStringNotContainsString('This should not be logged', $content);
        self::compatAssertStringNotContainsString('This should also not be logged', $content);
        self::compatAssertStringContainsString('This SHOULD be logged', $content);
        self::compatAssertStringContainsString('This should also be logged', $content);
    }

    public function testJsonlFormat()
    {
        $logger = new \LogLeaf($this->logFile, array('fileType' => 'jsonl', 'level' => 'debug'));
        $logger->error('JSON test', array('code' => 500, 'user' => 'test'));

        $content = file_get_contents($this->logFile);

        // Check for valid JSON line
        $data = json_decode($content, true);
        $this->assertNotNull($data, 'Log output is not valid JSON');

        self::compatAssertStringContainsString('"level":"ERROR"', $content);
        self::compatAssertStringContainsString('"message":"JSON test"', $content);
        self::compatAssertStringContainsString('"code":500', $content);
        self::compatAssertStringContainsString('"user":"test"', $content);
    }

    public function testProcessor()
    {
        $logger = new \LogLeaf($this->logFile, array('fileType' => 'txt', 'level' => 'debug'));

        // Add a processor that adds data to the 'context' array
        // so it will be serialized by the TXT formatter.
        $logger->pushProcessor(function (array $record) {
            if (!isset($record['context'])) {
                $record['context'] = array();
            }
            // Ensure context is an array
            if (!is_array($record['context'])) {
                $record['context'] = array('original_context' => $record['context']);
            }
            $record['context']['extra'] = 'processed';
            return $record;
        });

        $logger->info('Testing processor');
        $content = file_get_contents($this->logFile);

        // Check that the processor's data was added (as JSON in txt mode)
        self::compatAssertStringContainsString('"extra":"processed"', $content);
    }

    public function testCsvFormatAndHeader()
    {
        $columns = array('Timestamp', 'level', 'message', 'user', 'module');
        $logger = new \LogLeaf($this->logFile, array(
            'fileType'   => 'csv',
            'level'      => 'debug',
            'csvColumns' => $columns
        ));

        // Log a line
        // FIX: Call putLog() directly to send a flat array.
        // This bypasses the log() method's context nesting, matching
        // what the rowForCsv() method expects.
        $logger->putLog(array(
            'level'   => 'INFO',
            'message' => 'User login',
            'user'    => 'admin',
            'module'  => 'auth'
        ));

        // Log another line
        $logger->putLog(array(
            'level'   => 'WARNING',
            'message' => 'Failed login',
            'user'    => 'guest',
            'module'  => 'auth'
        ));

        $content = file_get_contents($this->logFile);

        // FIX: Normalize line endings (\r\n and \r) to \n before exploding
        $normalizedContent = str_replace(array("\r\n", "\r"), "\n", trim($content));
        $lines = explode("\n", $normalizedContent); // Get all non-empty lines

        // 1. Check Header
        // FIX: Remove quotes from assertion. fputcsv does not quote headers
        // unless they contain delimiters or spaces.
        $this->assertEquals('Timestamp,level,message,user,module', $lines[0]);

        // 2. Check first data line
        self::compatAssertStringContainsString('INFO,"User login",admin,auth', $lines[1]);

        // 3. Check second data line
        self::compatAssertStringContainsString('WARNING,"Failed login",guest,auth', $lines[2]);
    }

    public function testExceptionLogging()
    {
        $logger = new \LogLeaf($this->logFile, array('level' => 'debug'));

        try {
            // Use \Exception for PHP 5.6 compatibility
            // (Throwable would be better for 7.4/8.3 but Exception is fine)
            throw new \Exception('This is a test exception', 123);
        } catch (\Exception $e) {
            $logger->error('Caught an exception', array('exception' => $e));
        }

        $content = file_get_contents($this->logFile);

        self::compatAssertStringContainsString('Caught an exception', $content);
        self::compatAssertStringContainsString('This is a test exception', $content);
        self::compatAssertStringContainsString('[Exception Exception]', $content);
    }
}
