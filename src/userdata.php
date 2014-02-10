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
    private $bTrimWhitespace;
    // Errors
    private $aErrors;

    function __construct($sVarName, $sMethod="either") {
        if (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc())
            trigger_error('Please disable magic quotes.');
        $this->setName($sVarName);
        $this->setMethod($sMethod);
        $this->vValue = null;
        $this->aAllowed = array();
        $this->iLengthMin = -1;
        $this->iLengthMax = -1;
        $this->vMinRange = -1;
        $this->vMaxRange = -1;
        $this->sRangeCompare = 'numeric';
        $this->bTrimWhitespace = true;
        $this->aErrors = array();
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

    /**
     * Retrieve data from method (GET/POST) and set the UserData object's value.
     * The value will be null if the key sVarName for the method doesn't exist.
     */
    private function retrieveFromMethod() {
        $vParseValue = null;
        # check POST
        if ( $this->sMethod !== "get" && isset($_POST[$this->sVarName]) ) {
            $vParseValue = $_POST[$this->sVarName];
        }
        # check GET
        elseif ( $this->sMethod !== "post" && isset($_GET[$this->sVarName]) ) {
            $vParseValue = $_GET[$this->sVarName];
        }

        setValue($vParseValue);
    }

    private function setValue($vValue) {
        # Trim data of whitespace
        if ($this->bTrimWhitespace == true) {
            if (is_array($vValue)) {
                foreach (array_keys($vValue) as $key) {
                    $vValue[$key] = trim("{$vValue[$key]}");
                }
            }
            else {
                $vValue = trim("{$svValue}");
            }
        }
        # Set value
        $this->vValue = $vValue;
        # Enforce rules
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

    /**
     * Returns the length of the value as a string length.
     * @return int
     */
    public function getLength() {
        $iLenCheck = 0;
        if (is_array($this->vValue)) {
            $this-aErrors[] = "Cannot getLength() on type array().";
        }
        else {
            $iLenCheck = strlen("{$this->vValue}");
        }
        return $iLenCheck;
    }
   
    /**
     * Check if length of value is within allowed range.
     * @return boolean True if length of value is permissible, false otherwise
     */ 
    public function checkLengthLimits() {
        if (is_array($this->vValue)) {
            //TODO array handling?
        }
        else {
            $iLength = $this->getLength();
            if (($this->iLengthMin >= 0 && $iLength < $this->iLengthMin) || $iLength > $this->iLengthMax) {
                return false;
            }
        }
        return true;
    }
   
    /**
     * 
     */ 
    public function enforceLengthLimits($bTruncate=true) {
        //
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
     * @return float|null The float value or fDefault (which defaults to null)
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

    /**
     * Checks if the value for this data is empty()
     * @return bool True if value is empty(); false otherwise
     */
    public function isEmpty() {
    }

    /**
     * Checks if any errors have occurred while processing the input.
     * @return bool True if any errors happened, false otherwise
     */
    public function hasErrors() {
        return (count($this->aErrors) > 0);
    }

    /**
     * Get an array containing all errors that happened while processing input
     * @return array An array of strings contianing the error messages; may be empty.
     */
    public function getErrrors() {
        return $this->aErrors;
    }

    /**
     * Check all arguments to this function to ensure they are not set to null
     * @params unknown
     * @return bool False if any argument is null, true otherwise 
     */
    public static function exists() {
        $aArgs = func_get_args();
    }

    /**
     * Checks all arguments to this function to ensure they are not empty() (PHP function)
     * @return bool True if ALL arguments are not empty(); false otherwise
     */
    public static function notEmpty() {
        $aArgs = func_get_args();
    }
}

?>
