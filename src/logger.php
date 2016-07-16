<?php

interface LoggingInterface {
    public function makeLog($sEntry, $iLogLevel);
}

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

    static public function register($oLogger) {
        // TODO ensure object implements LoggingInterface
        self::$oLogger = $oLogger;
    }

    static public function disable() {
        self::$oLogger = false;
    }

    static public function initialize($sLogFile, $iLogLevel=self::LOG_ALL) {
        //
    }

    public function makeLog($sEntry, $iLogLevel) {
        //
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

?>
