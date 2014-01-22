<?php

/**
 * Parsing and validation of user data
 */
class UserData {
    // Values
    private $sVarName;
    private $sMethod;
    private $vValue;
    // Limitations
    private $aAllowed;
    private $iLengthMin;
    private $iLengthMax;
    private $vMinRange;
    private $vMaxRange;
    private $sRangeCompare;

    function __construct($sVarName, $sMethod="either") {
        if (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc())
            trigger_error('Please disable magic quotes.');
        $this->setName($sVarName);
        $this->setMethod($sMethod);
        $this->aAllowed = array();
        $this->iLengthMin = -1;
        $this->iLengthMax = -1;
        $this->vMinRange = -1;
        $this->vMaxRange = -1;
        $this->sRangeCompare = 'numeric';
    }

    private function setName($sVarName) {
        $this->sVarName = trim($sVarName);
    }

    private function setMethod($sMethod) {
        $sMethod = strtolower($sMethod);
        if (!in_array($sMethod,array('get','post'))) {
            $sMethod = 'either';
        }
        $this->sMethod = $sMethod;
    }

    private function setValue($vValue) {
        $this->vValue = trim($svValue);
        $this->enforceAllowedRange();
        $this->enforceLengthLimits();
        $this->enforceAllowedValues();
    }

    private function getValue($vDefault=null) {
        /* If value isn't set, then attempt to find it */
        if ($this->vValue == null) {
            return $vDefault;
        }
        return $this->vValue;
    }

    public function setAllowedValues() {
        $aArgs = func_get_args();
        $this->aAllowed = $aArgs;
        $this->enforceAllowedValues();
    }

    /**
     * Enforce any allowed values list on the current vValue variable
     * 
     * @param bool $bReplace Should vValue be replaced by the matching allowed value
     * @param bool $bStrict Should strict matching be used (value and type must match)
     */
    private function enforceAllowedValues($bReplace = true, $bStrict = false) {
        /* If there isn't any allowed values set, then do not enforce */
        if (count($this->aAllowed) > 0) {
            $vNewValue = null;
            foreach ($this->aAllowed as $vAllow) {
                if ($bStrict == true && $this->vValue === $vAllow) {
                    $vNewValue = $this->vValue;
                    break;
                }
                else if ($this->vValue == $vAllow) {
                    if ($bReplace == true) {
                        $vNewValue = $vAllow;
                    }
                    else {
                        $vNewValue = $this->vValue;
                    }
                    break;
                }
            }
            $this->vValue = $vNewValue;
        }
    }

    /**
     * Set a minimum and maximum value and how the comparison to these are performed 
     * 
     * @param mixed $vMin
     * @param mixed $vMax
     * @param string $sCompare Can be either 'numeric' (the default) or 'string'; any other value results in a string compare  
     */
    public function setAllowedRange($vMin, $vMax, $sCompare="numeric") {
        $this->vMinRange = $vMin;
        $this->vMaxRange = $vMax;
        $sCompare = strtolower($sCompare);
        if (!in_array($sCompare, array('numeric','string'))) {
            $sCompare = 'string';
        }
        $this->sRangeCompare = $sCompare;
    }
    
    /**
     * Check if the currently loaded value falls within the allowed range (inclusive).
     * If no range has been set, this function will return true.
     * @return boolean True if within the set range (inclusive), false otherwise
     */
    public function checkAllowedRange() {
        $checkValue = $this->vValue;
        $checkLow = $checkValue;
        $checkHigh = $checkValue;
        if ($this->sCompare == 'numeric' && is_numeric($checkValue)) {
            if (intval($checkValue) == $checkValue) {
                $checkValue = intval($checkValue);
                $checkLow = intval($checkLow);
                $checkHigh = intval($checkHigh);
            }
            else {
                $checkValue = floatval($checkValue);
                $checkLow = floatval($checkLow);
                $checkHigh = floatval($checkHigh);
            }
        }

        /* If outside range */
        if ($checkValue < $checkLow || $checkValue > $checkHigh) {
            return false;
        }
        /* Otherwise, it's in range */
        return true;
    }

    /**
     * If current value is not within the set range (inclusive), the value is set to null.
     */
    public function enforceAllowedRange() {
        if (!$this->checkAllowedRange()) {
            $this->vValue = null;
        }
    }

    public function setLengthLimits($iMinimum, $iMaximum) {
        $this->iLengthMin = $iMinimum;
        $this->iLengthMax = $iMaximum;
    }
    
    public function getLength() {
        $sLenCheck = "{$this->vValue}";
        return strlen($sLenCheck);
    }
    
    public function checkLengthLimits() {
        $iLength = $this->getLength();
        if (($this->iLengthMin >= 0 && $iLength < $this->iLengthMin) || $iLength > $this->iLengthMax) {
            return false;
        }
        return true;
    }
    
    public function enforceLengthLimits($bTruncate=true) {
    }

    public function getString($sDefault=null) {
        // if exists and is not an array, return as string
    }

    public function getInteger($iDefault=null) {
        $iVal = $iDefault;
        if (is_numeric($this->getValue())) {
            $iVal = intval($this->getValue());
        }
        return $iVal;
    }

    /**
     * Get a floating point representation of the loaded user data.
     * If not a floating point, or if NaN, or if infinite, then returns fDefault
     * 
     * @param string $fDefault The default to return if value is not a float
     * @return float|null The float value or null
     */
    public function getFloat($fDefault=null) {
        $fVal = $fDefault;
        if (is_numeric($this->getValue())) {
            $fTempVal = floatval($this->getValue());
            if (!is_nan($fVal) && is_finite($fVal)) {
                $fVal = $fTempVal;
            }
        }
        return $iVal;
    }

    public function getArray($aDefault=null) {
        // if value is array, return it, otherwise return default 
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
