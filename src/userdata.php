<?php
/****************************************************************************************

Copyright 2014 Nathan Collins. All rights reserved.

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

    /**
     * Specify exactly what values are allowed. Values that don't match these will be set to null.
     * @param mixed Arguments are allowed values. If an argument is an array, then its
     *      contents will also be set as allowed values.
     */
    public function setAllowedValues() {
        $aArgs = func_get_args();
        //TODO check each argument to see if it's an array, and if so, add it's contents to aAllowed
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
     * If length is outside bounds, then either truncate the value, or set value to null.
     */ 
    public function enforceLengthLimits($bTruncate=true) {
        if (!$this->checkLengthLimits()) {
            // If allowed, truncate length to acceptable lenth
            if ($bTruncate) {
                //TODO
            }
            // Not allowed to truncate, set value to null
            else {
                $this->vValue = null;
            }
        }
    }

    /**
     * Gets the current value as a string.
     * @param string $sDefault If value doesn't exist or cannot be a string, return this value (default: null)
     * @return string The string value if possible, otherwise $sDefault
     */
    public function getString($sDefault=null) {
        $sRet = $sDefault;
        // if exists and is not an array, return as string
        if ($this->exists($this->getValue()) && !is_array($this->getValue())) {
            // Force convert to string
            $sRet = "" . $this->getValue();
        }
        return $sRet;
    }

    /**
     * Gets the current value as a integer. Value must be numeric.
     * @param int $iDefault If value doesn't exist or isn't numeric, return this value (default: null)
     * @return int The integer value is possible, otherwise $iDefault
     */
    public function getInteger($iDefault=null) {
        $iVal = $iDefault;
        if (is_numeric($this->getValue())) {
            $iVal = intval($this->getValue());
        }
        return $iVal;
    }

    /**
     * Get a floating point representation of the loaded user data. Value must be numeric.
     * If not a floating point, or if NaN, or if infinite, then returns $fDefault
     * @param float $fDefault If value doesn't exist or isn't numeric, return this value (default: null)
     * @return float The float value if possible, otherwise $fDefault
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

    /**
     * Get the loaded user data as an array. Value must be an array.
     * @param array $aDefault If value doesn't exist or isn't an array, return this value (default: null)
     * @return array The user data array if possible, otherwise $aDefault
     */
    public function getArray($aDefault=null) {
        $aVal = $this->getValue();
        if (!is_array($aVal)) {
            $aVal = $aDefault;
        }
        return $aVal;
    }

    public function getFiles() {
    }

    public function getImages() {
    }

    public function exists() {
        return UserData::exists($this->getValue());
    }

    public function fileExists() {
    }

    /**
     * Checks if the value for this data is empty()
     * @return bool True if value is empty(); false otherwise
     */
    public function isEmpty() {
        return empty($this->getValue());
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
     * @params mixed
     * @return bool False if any argument is null, true otherwise 
     */
    public static function exists() {
        $aArgs = func_get_args();
        $bExists = true;
        foreach ($aArgs as $mArg) {
            if ($mArg === null) { $bExists = false; }
        }
        return $bExists;
    }

    /**
     * Checks all arguments to this function to ensure they are not empty() (PHP function)
     * @return bool True if ALL arguments are not empty(); false otherwise
     */
    public static function notEmpty() {
        $aArgs = func_get_args();
        $bNotEmpty = true;
        foreach ($aArgs as $mArg) {
            if (empty($mArg)) { $bNotEmpty = false; }
        }
        return $bNotEmpty;
    }
}

?>
