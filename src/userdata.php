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

    /**
     * Create a user parsing and verification object
     * @param string $sVarName The _POST or _GET array key
     * @param string $sMethod If 'post','get' or 'file', only searches in the respective array. Otherwise searches all, post first, get second, file last.
     */
    function __construct($sVarName, $sMethod="any") {
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

    /**
     * Set the variable name (array key) to retrieve from _POST, _GET, or _FILES
     * @param string $sVarName The array key string
     */
    private function setName($sVarName) {
        $this->sVarName = trim($sVarName);
    }

    /**
     * Get the variable name (array key)
     */
    private function getName() {
        return $this-sVarName;
    }

    /**
     * Set the method to use, if not 'post','get', or 'file'; then 'any' is set.
     * @param string $sMethod The method to use
     */
    private function setMethod($sMethod) {
        $sMethod = strtolower($sMethod);
        // catch common mistake
        if ($sMethod == 'files') { $sMethod = 'file'; }
        if (!in_array($sMethod,array('get','post','file'))) {
            $sMethod = 'any';
        }
        $this->sMethod = $sMethod;
    }

    /**
     * Retrieve data from method (GET/POST) and set the UserData object's value.
     * The value will be null if the key sVarName for the method doesn't exist.
     */
    private function retrieveFromMethod() {
        $vParseValue = null;
        # check _POST
        if ( in_array($this->sMethod, array('post','any')) && isset($_POST[$this->sVarName]) ) {
            $vParseValue = $_POST[$this->sVarName];
        }
        # check _GET
        elseif ( in_array($this->sMethod, array('get','any')) && isset($_GET[$this->sVarName]) ) {
            $vParseValue = $_GET[$this->sVarName];
        }

        setValue($vParseValue);
    }

    /**
     * Set the user value and enforce all rules on the new vlue
     * @param mixed $vValue Value to set
     */
    private function setValue($vValue) {
        if ($vValue === null) {
            $this->vValue = null;
        }
        else {
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
    }

    /**
     * Get the value if not null, or $vDefault if value is null
     * @param mixed What to return if value is null
     * @return mixed The value or $vDefault
     */
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
        $this->aAllowed = array();
        for ($aArgs as $mArg) {
            // check each argument to see if it's an array, and if so, add it's contents to aAllowed
            if (is_array($mArg)) {
                $this->aAllowed = array_merge($this->aAllowed, $mArg);
            }
            // otherwise just add the value to the allowed array
            else {
                $this->aAllowed[] = $mArg
            }
        }
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
     * @return int Length of value as a string
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
     * Check if length of value is within allowed range. If the value is an arrays, it always return true.
     * @return boolean True if length of value is permissible, false otherwise
     */ 
    public function checkLengthLimits() {
        if (is_array($this->vValue)) {
            return true;
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
     * @param boolean $bTruncate If set to true, will truncate values longer than the max allowed
     */ 
    public function enforceLengthLimits($bTruncate=true) {
        if (!$this->checkLengthLimits()) {
            // If allowed and not already too short, truncate length to acceptable length
            if (($this->getLength() >= $this->iLengthMin) && $bTruncate) {
                $this->vValue = substr("{$this->vValue}",0,$this->iLengthMax);
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

    /**
     * Only files with the given file extensions will be allowed
     */
    public function setAllowedFileExtensions() {
        $aArgs = func_get_args();
        //TODO
    }

    /**
     * Parse through $_FILES getting basic data for the file upload.
     * - Checks if the uploaded file name is actually an array of files.
     * - Loads any file errors into the aErrors array.
     */
    private function getFilesData() {
    }

    /**
     * Get array of metadata for file(s) uploaded
     */
    public function getFiles() {
        //TODO
    }

    /**
     *
     */
    public function getImages() {
        //TODO
    }

    /**
     * Checks if a file upload exists.
     * @param boolean $bExcludeErrors If false, will return true, even if the file uploaded has errors
     * @return boolean Returns true if a file upload occured for the given name.
     */
    public function fileExists($bExcludeErrors=false) {
        $bFile = false;
        if (array_key_exists($this->getName(), $_FILES)) {
            if ($bAllowErrors) {
                $bFile = true;
            }
            else if (array_key_exists('error', $_FILES[$this->getName()])) {
                // Ensure no errors happened
                if ($_FILES[$this->getName()]['error'] == UPLOAD_ERR_OK) {
                    $bFile = true;
                }
            }
        }
        return $bFile;
    }

    /**
     * Checks that the 'get' or 'post' value of this variable is not null.
     * Will return false if variable is a 'file'. Use fileExists() instead.
     * @return boolean Returns true if value is null, false otherwise
     */
    public function exists() {
        return UserData::exists($this->getValue());
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
