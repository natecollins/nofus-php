<?php
declare(strict_types=1);

/****************************************************************************************

Copyright 2016 Nathan Collins. All rights reserved.

Redistribution and use in source and binary forms, with or without modification, are
permitted provided that the following conditions are met:

   1. Redistributions of source code must retain the above copyright notice, this list of
      conditions and the following disclaimer.

   2. Redistributions in binary form must reproduce the above copyright notice, this list
      of conditions and the following disclaimer in the documentation and/or other materials
      provided with the distribution.

THIS SOFTWARE IS PROVIDED BY Nathan Collins ``AS IS'' AND ANY EXPRESS OR IMPLIED
WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND
FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL Nathan Collins OR
CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF
ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

The views and conclusions contained in the software and documentation are those of the
authors and should not be interpreted as representing official policies, either expressed
or implied, of Nathan Collins.

*****************************************************************************************/

/****************************************
 * Use examples
 ****************************************
use Nofus\Logger;

# Initialize built-in file logger; default level logs all except TRACE
Logger::initialize('/path/to/file.log')

# Initialize built-in file logger with customize logger levels
Logger::initialize('/path/to/file.log', Logger::LOG_ERROR | Logger::LOG_CRITICAL | Logger::LOG_WARNING);

# Disable logger
Logger::disable();

# Register custom logger instance which implements LoggingInterface
use Nofus\LoggingInterface;
class CustomLogger implements LoggingInterface {
    ...
}
Logger::register( new CustomLogger() );

# Make log entries
Logger::trace("Trace!");
Logger::debug("Debug!");
Logger::info("Info!");
Logger::notice("Notice!");
Logger::warning("Warning!");
Logger::error("Error!");
Logger::critical("Critical!");

# Log entry which includes an exception stack trace
try {
    intdiv(1, 0);
}
catch (DivisionByZeroError $exc) {
    Logger::info("Caught something.", $exc);
}
*/

namespace Nofus;

/**
 * Interface required by Logger instance
 */
interface LoggingInterface {
    public function makeLog($sEntry, $iLogLevel);
}

/**
 * Logger class and default file logging implementation
 */
class Logger implements LoggingInterface {
    const LOG_CRITICAL  = 0x00000001;
    const LOG_ERROR     = 0x00000002;
    const LOG_WARNING   = 0x00000004;
    const LOG_NOTICE    = 0x00000008;
    const LOG_INFO      = 0x00000010;
    const LOG_DEBUG     = 0x00000020;
    const LOG_TRACE     = 0x00000040;

    const LOG_NONE      = 0x00000000;
    const LOG_LOW       = 0x00000003;   # CRITICAL & HIGH
    const LOG_MED       = 0x0000000F;   # LOW + WARNING & NOTICE
    const LOG_HIGH      = 0x0000003F;   # MED + INFO & DEBUG
    const LOG_ALL       = 0x0000FFFF;

    const LOG_RAW       = 0x80000000;   # Raw log message; e.g. remove log prefixes

    // Instance of class implementing LoggingInterface
    static private $oLogger = null;

    protected $sLogFile;
    protected $iLogLevel;

    protected function __construct($sLogFile=null, $iLogLevel=self::LOG_HIGH) {
        $this->sLogFile = $sLogFile;
        $this->iLogLevel = $iLogLevel;
    }

    /**
     * Register a custom logger instead of using the built-in one
     * @param object $oLogger An instance of a class that implements LoggingInterface
     */
    static public function register($oLogger) {
        if (!in_array('Nofus\LoggingInterface', class_implements($oLogger))) {
            trigger_error("Logger failure. Can only register classes which implement LoggingInterface.", E_USER_ERROR);
            exit(1);
        }
        self::$oLogger = $oLogger;
    }

    static public function disable() {
        self::$oLogger = false;
    }

    static public function initialize($sLogFile, $iLogLevel=self::LOG_HIGH) {
        $bFileWritable = is_file($sLogFile) && is_writable($sLogFile);
        $bCanCreateFile = !is_file($sLogFile) && is_writable(dirname($sLogFile));
        if ($bFileWritable || $bCanCreateFile) {
            self::$oLogger = new Logger($sLogFile, $iLogLevel);
        }
        else {
            trigger_error("Logger failure. Can not initialize; log file not writable.", E_USER_ERROR);
            exit(1);
        }
    }

    public function makeLog($sEntry, $iLogLevel) {
        if (($this->iLogLevel & $iLogLevel & self::LOG_ALL) !== self::LOG_NONE) {
            $sTimestamp = date("Y-m-d H:i:s");
            $sLevel = 'CUSTOM';
            if ($iLogLevel == self::LOG_CRITICAL) { $sLevel = 'CRITICAL'; }
            elseif ($iLogLevel == self::LOG_ERROR) { $sLevel = 'ERROR'; }
            elseif ($iLogLevel == self::LOG_WARNING) { $sLevel = 'WARNING'; }
            elseif ($iLogLevel == self::LOG_NOTICE) { $sLevel = 'NOTICE'; }
            elseif ($iLogLevel == self::LOG_INFO) { $sLevel = 'INFO'; }
            elseif ($iLogLevel == self::LOG_DEBUG) { $sLevel = 'DEBUG'; }
            elseif ($iLogLevel == self::LOG_TRACE) { $sLevel = 'TRACE'; }

            $bRawline = boolval($iLogLevel & self::LOG_RAW);
            $sEntry = ($bRawline ? "" : "[{$sTimestamp}] [{$sLevel}] ")
                      . "{$sEntry}" . PHP_EOL;
            if (!file_put_contents($this->sLogFile, $sEntry, FILE_APPEND | LOCK_EX)) {
                trigger_error("Logger failure. Could not write to log file.", E_USER_ERROR);
                exit(1);
            }
        }
    }

    static public function processLog($sEntry, $iLogLevel, $oExc=null) {
        if (self::$oLogger === null) {
            trigger_error("Logger failure. Logger not initialized.", E_USER_ERROR);
            exit(1);
        }
        // Skip if logging is disabled
        elseif (self::$oLogger !== false) {
            if ($oExc !== null) {
                $sEntry .= PHP_EOL . $oExc;
            }
            self::$oLogger->makeLog($sEntry, $iLogLevel);
        }
    }

    /**
     * Check if logging is enabled for a given log level
     * @param int $iLogLevel The level to check
     * @return bool|null True if logging is enabled for level, or null if iLogLevel is not defined
     */
    static public function isEnabled($iLogLevel) {
        if (!isset(self::$oLogger->iLogLevel)) { return null; }
        return ((self::$oLogger->iLogLevel & $iLogLevel) !== self::LOG_NONE);
    }

    static public function critical($sEntry, $oExc=null) {
        self::processLog($sEntry, self::LOG_CRITICAL, $oExc);
    }

    static public function error($sEntry, $oExc=null) {
        self::processLog($sEntry, self::LOG_ERROR, $oExc);
    }

    static public function warning($sEntry, $oExc=null) {
        self::processLog($sEntry, self::LOG_WARNING, $oExc);
    }

    static public function notice($sEntry, $oExc=null) {
        self::processLog($sEntry, self::LOG_NOTICE, $oExc);
    }

    static public function info($sEntry, $oExc=null) {
        self::processLog($sEntry, self::LOG_INFO, $oExc);
    }

    static public function debug($sEntry, $oExc=null) {
        self::processLog($sEntry, self::LOG_DEBUG, $oExc);
    }

    static public function trace($sEntry, $oExc=null) {
        self::processLog($sEntry, self::LOG_TRACE, $oExc);
    }
}
