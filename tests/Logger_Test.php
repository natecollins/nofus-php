<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/src/Logger.php';

use PHPUnit\Framework\TestCase;
use Nofus\Logger;
use Nofus\LoggingInterface;

$aMemLogs = [];

class CustomLogger implements LoggingInterface {

    protected $iLogLevel = Logger::LOG_LOW;

    public function makeLog($sEntry, $iLogLevel) {
        global $aMemLogs;
        if (($this->iLogLevel & $iLogLevel) !== Logger::LOG_NONE) {
            $sLevel = 'CUSTOM';
            if ($iLogLevel == Logger::LOG_CRITICAL) { $sLevel = 'CRITICAL'; }
            elseif ($iLogLevel == Logger::LOG_ERROR) { $sLevel = 'ERROR'; }
            elseif ($iLogLevel == Logger::LOG_WARNING) { $sLevel = 'WARNING'; }
            elseif ($iLogLevel == Logger::LOG_NOTICE) { $sLevel = 'NOTICE'; }
            elseif ($iLogLevel == Logger::LOG_INFO) { $sLevel = 'INFO'; }
            elseif ($iLogLevel == Logger::LOG_DEBUG) { $sLevel = 'DEBUG'; }
            elseif ($iLogLevel == Logger::LOG_TRACE) { $sLevel = 'TRACE'; }

            $sEntry = "[{$sLevel}] {$sEntry}";
            $aMemLogs[] = $sEntry;
        }
    }
}

final class LoggerTest extends TestCase {

    public function testDefaultLogger(): void {
        $sLogFile = '/tmp/.nofus_test.log';
        if (file_exists($sLogFile)) {
            unlink($sLogFile);
        }
        Logger::initialize($sLogFile);
        Logger::trace("Trace!");
        Logger::debug("Debug!");
        Logger::info("Info!");
        Logger::notice("Notice!");
        Logger::warning("Warning!");
        Logger::error("Error!");
        Logger::critical("Critical!");
        try {
            throw new Exception("Bad Val");
        }
        catch (Exception $exc) {
            Logger::info("Uh oh", $exc);
        }

        $this->assertTrue(Logger::isEnabled(Logger::LOG_WARNING));
        $this->assertFalse(Logger::isEnabled(Logger::LOG_TRACE));

        Logger::disable();
        Logger::critical("Disabled logs.");
        $this->assertNull(Logger::isEnabled(Logger::LOG_NOTICE));

        $sValidLog = "[TS] [DEBUG] Debug!\n" .
                     "[TS] [INFO] Info!\n" .
                     "[TS] [NOTICE] Notice!\n" .
                     "[TS] [WARNING] Warning!\n" .
                     "[TS] [ERROR] Error!\n" .
                     "[TS] [CRITICAL] Critical!\n" .
                     "[TS] [INFO] Uh oh\n" .
                    "Exception: Bad Val";

        $sLogContent = file_get_contents($sLogFile);
        $sLogContent = preg_replace('/^\[[^[]+\]/',"[TS]", $sLogContent);
        $sLogContent = preg_replace('/\n\[[^[]+\]/',"\n[TS]", $sLogContent);
        $this->assertNotNull($sLogContent);
        $this->assertStringStartsWith($sValidLog, $sLogContent);

        unlink($sLogFile);
    }

    public function testCustomLogger(): void {
        global $aMemLogs;

        Logger::register(new CustomLogger());
        Logger::trace("Trace!");
        Logger::debug("Debug!");
        Logger::info("Info!");
        Logger::notice("Notice!");
        Logger::warning("Warning!");
        Logger::error("Error!");
        Logger::critical("Critical!");

        $this->assertCount(2, $aMemLogs);
        $this->assertEquals("[ERROR] Error!", $aMemLogs[0]);
        $this->assertEquals("[CRITICAL] Critical!", $aMemLogs[1]);
    }

}
