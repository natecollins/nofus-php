<?php
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

# Initialize logger with built-in file logger; default level logs all levels
Logger::initialize('/path/to/file.log')

# Initialize logger with customize logger levels
Logger::initialize('/path/to/file.log', Logger::LOG_ERROR | Logger::LOG_CRITICAL);

# Disable logger
Logger::disable();

# Register custom logger instance which implements LoggingInterface
Logger::register( new CustomLogger() );

# Make log entries
Logger::debug("Debug!");
Logger::notice("Notice!");
Logger::warning("Warning!");
Logger::error("Error!");
Logger::critical("Critical!");

*/

# Include guard, for people who can't remember to use '_once'
if (!defined('__LOGGER_GUARD__')) {
    define('__LOGGER_GUARD__',true);

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
    const LOG_NONE      = 0x00000000;
    const LOG_CRITICAL  = 0x00000001;
    const LOG_ERROR     = 0x00000002;
    const LOG_WARNING   = 0x00000004;
    const LOG_NOTICE    = 0x00000010;
    const LOG_DEBUG     = 0x00000020;
    const LOG_ALL       = 0xFFFFFFFF;

    // Instance of class implementing LoggingInterface
    static private $oLogger = null;

    private $sLogFile;
    private $iLogLevel;

    private function __construct() {
        $this->sLogFile = null;
        $this->iLogLevel = self::LOG_ALL;
    }

    /**
     * Register a custom logger instead of using the built-in one
     * @param oLogger An instance of a class that implements LoggingInterface
     */
    static public function register($oLogger) {
        // TODO ensure object implements LoggingInterface
        self::$oLogger = $oLogger;
    }

    static public function disable() {
        self::$oLogger = null;
        self::iLogLevel = self::LOG_NONE;
    }

    static public function initialize($sLogFile, $iLogLevel=self::LOG_ALL) {
        //TODO
        //TODO check if log file exists is writable
        //TODO if log files doesn't exist, check if directory is writable
    }

    public function makeLog($sEntry, $iLogLevel) {
        if (self::iLogLevel !== self::LOG_NONE) {
            if (self::$oLogger === null) {
                trigger_error("Logger failure. Logger not initialized.", E_USER_ERROR);
                exit(1);
            }

            if (!file_put_contents($this->sLogFile, $sEntry, FILE_APPEND | LOCK_EX)) {
                //TODO could not write log to file
            }
        }
    }

    static public function processLog($sEntry, $iLogLevel) {
        if (self::$oLogger === null) {
            //TODO logger not initialized/disabled/registered
        }
        // Skip if logging is disabled
        elseif (self::$oLogger !== false) {
            self::$oLogger->makeLog($sEntry, $iLogLevel);
        }
    }

    static public function critical($sEntry) {
        self::processLog($sEntry, self::LOG_CRITICAL);
    }

    static public function error($sEntry) {
        self::processLog($sEntry, self::LOG_ERROR);
    }

    static public function warning($sEntry) {
        self::processLog($sEntry, self::LOG_WARNING);
    }

    static public function notice($sEntry) {
        self::processLog($sEntry, self::LOG_NOTICE);
    }

    static public function debug($sEntry) {
        self::processLog($sEntry, self::LOG_DEBUG);
    }
}

} // Include guard end

?>
