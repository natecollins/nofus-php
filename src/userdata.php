<?php

/**
 * Parsing and validation of user data
 */
class UserData {
    private $sVarName;
    private $sMethod;
    private $vValue;

    function __construct($sVarName, $sMethod="either") {
        $this->setName($sVarName);
        $this->setMethod($sMethod);
    }

    private function setName($sVarName) {
    }

    private function setMethod($sMethod) {
    }

    private function setValue($vValue) {
    }

    private function getValue() {
        /* If value isn't set, then attempt to find it */
        if ($this->vValue == null) {
            //TODO
        }
        return $this->vValue;
    }

    public function setAllowedValues() {
        $aArgs = func_get_args();
    }

    public function setAllowedRange($vMin, $vMax, $sCompare="numeric") {
    }

    public function setMaximumLength($iLength) {
    }

    public function getString($sDefault=null) {
    }

    public function getInteger($iDefault=null) {
    }

    public function getFloat($fDefault=null) {
    }

    public function getArray($aDefault=null) {
    }

    public function getFiles() {
    }

    public function getImages() {
    }

    public function exists() {
    }

    public function fileExists() {
    }

    public function isEmpty() {
    }

    public static function exists() {
        $aArgs = func_get_args();
    }

    public static function isEmpty() {
        $aArgs = func_get_args();
    }
}

?>
