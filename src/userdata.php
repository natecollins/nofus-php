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

# Grab a string from a 'firstname' field from _GET, _POST, or _COOKIE; which ever is found first
$udFirst = new UserData('firstname');
$first = $udFirst->getStr();
# If field 'firstname' doesn't exist, getStr() will return null by default

# Grab an integer from field 'age' in _POST
$udAge = new UserData('age', 'POST');
# Set minimum and maximim allowed
$udAge->filterRange(0, 120);
# Will default to 0 if 'age' if outside of range specified or if doesn't exist
$age = $ud->getInt(0)

# Alternate way to create UserData
$udHeight = UserData::create('height', 'GET');
$udHeight->filterRange(0.0, 8.9);
$height = $udHeight->getFloat();

# Filter length and regular expression
$udPhone = new UserData('phone');
$udPhone->filterLength(7,10);
$udPhone->filterRegExp('/^\d{7,10}$/');
$phone = $udPhone->getStr();

# Filter to only allowed values
$udChoice = new UserData('choice');
# Specify allowed option
$udChoice->filterAllowed(array('chicken','beef','fish'));
# Default to 'chicken' if value not in allowed options
$choice = $udChoice->getStr('chicken');

# Get boolean; will be true for values of '1' or 'true'
$udEnabled = new UserData('enabled', 'COOKIE');
# Default to false if field doesn't exist
$enabled = $udEnabled->getBool(false);

# Get array of values from field name 'books[]'
$udBooks = new UserData('books');
# Default to empty array if no values found
$books = $udBooks->getStrArray(array());

# Get a info on uploaded file
$udFile = new UserData('photo')
$file_info = $udFile->getFile();

# Get info on array of uploaded files from field 'pictures[]'
$udFiles = new UserData('pictures')
# Default to empty array if no files found or field doesn't exist
$file_array = $udFiles->getFileArray(array());

*/

# Include guard, for people who can't remember to use '_once'
if (!defined('__USERDATA_GUARD__')) {
    define('__USERDATA_GUARD__',true);

/**
 * Handles validation of User/Client Data
 */
class UserData {
    // Data location
    private $sFieldName;
    private $sMethod;

    // Filters
    private $sRegExp;
    private $mRangeLow;
    private $mRangeHigh;
    private $iLengthMin;
    private $iLengthMax;
    private $bTruncateLength;
    private $aAllowed;
    private $bAllowedStrict;

    // Errors
    private $aErrors;

    /**
     * UserData 
     * @param string sFieldName The name of the field to parse/validate
     * @param string sMethod One of GET,POST,COOKIE,FILES; or ANY, which
     *          checks the previously mentioned method in the order listed.
     */
    function __construct($sFieldName, $sMethod="ANY") {
        $this->aErrors = array();
        $this->sMethod = "NONE";
        $this->sRegExp = null;
        $this->mRangeLow = null;
        $this->mRangeHigh = null;
        $this->iLengthMin = null;
        $this->iLengthMax = null;
        $this->bTruncateLength = false;
        $this->aAllowed = null;
        $this->bAllowedStrict = false;

        if (!is_string($sFieldName)) {
            $this->aErrors[] = "Invalid UserData field name specified; name must be a string.";
        }
        else {
            $this->sFieldName = $sFieldName;
        }

        $sMethod = strtoupper($sMethod);
        if ( in_array($sMethod, array('ANY','GET','POST','COOKIE','FILES')) ) {
            $this->sMethod = $sMethod;
        }

    }

    /**
     * Static constructor wrapper
     */
    static public function create($sFieldName, $sMethod="ANY") {
        return new UserData($sFieldName, $sMethod);
    }

    /**
     * Get the appropriate value given the requested method
     * @return string|null The string value, or null if not found
     */
    private function getValue() {
        $mValue = null;
        if ($mValue === null &&
          in_array($this->sMethod, array('ANY','GET')) &&
          array_key_exists($this->sFieldName, $_GET)) {
            $mValue = $_GET[$this->sFieldName];
        }
        if ($mValue === null &&
          in_array($this->sMethod, array('ANY','POST')) &&
          array_key_exists($this->sFieldName, $_POST)) {
            $mValue = $_POST[$this->sFieldName];
        }
        if ($mValue === null &&
          in_array($this->sMethod, array('ANY','COOKIE')) &&
          array_key_exists($this->sFieldName, $_COOKIE)) {
            $mValue = $_COOKIE[$this->sFieldName];
        }
        return $mValue;
    }

    public function getStr($mDefault=null) {
        $sValue = $this->getValue();
        if (!$this->matchesRegExp($sValue)) {
            $this->aErrors[] = "Value for {$this->sFieldName} does not match required pattern.";
            $sValue = $mDefault;
        }
        $sValue = $this->applyLength($sValue);
        if ($sValue === null) {
            $sValue = $mDefault;
        }
        if (!$this->isAllowed($sValue)) {
            $sValue = $mDefault;
        }
        return $sValue;
    }

    public function getString($mDefault=null) {
        return $this->getStr($mDefault);
    }

    public function getInt($mDefault=null) {
        $iVal = null;
        $sRaw = $this->getValue();
        if (ctype_digit($sRaw)) {
            $iVal = intval($sRaw);
        }

        $iVal = $this->applyRange($iVal);
        if (!$this->isAllowed($iVal)) {
            $iVal = $mDefault;
        }

        if ($iVal === null) {
            $iVal = $mDefault;
        }
        return $iVal;
    }

    public function getInteger($mDefault=null) {
        return $this->getInt($mDefault);
    }

    public function getFloat($mDefault=null) {
        $fVal = null;
        $sRaw = $this->getValue();
        if (is_numeric($sRaw)) {
            $fVal = floatval($sRaw);
        }

        $fVal = $this->applyRange($fVal);
        if (!$this->isAllowed($fVal)) {
            $fVal = $mDefault;
        }

        if ($fVal === null) {
            $fVal = $mDefault;
        }
        return $fVal;
    }

    public function getDouble($mDefault=null) {
        return $this->getFloat($mDefault);
    }

    /**
     * Attempt to get a boolean value from the data
     * If the string is a '1' or 'true' (case-insensitive), returns true
     * @param mixed mDefault
     * @return bool|null Returns true or false based on the parsed value, or null if field name does not exist
     */
    public function getBool($mDefault=null) {
        $bVal = $mDefault;
        $sVal = $this->getValue();
        if ($sVal !== null) {
            $bVal = false;
            if (in_array(strtolower($sVal), array('1','true'))) {
                $bVal = true;
            }
        }
        return $bVal;
    }

    public function getBoolean($mDefault=null) {
        return $this->getBool($mDefault);
    }

    /**
     * Get an array of string values
     *
     */
    public function getStrArray($mDefault=null) {
        $aValues = $this->getValue();
        $aReturn = array();
        if (is_array($aValues)) {
            foreach ($aValues as $sValue) {
                if (!$this->matchesRegExp($sValue)) {
                    $this->aErrors[] = "A value from {$this->sFieldName} array does not match required pattern.";
                    $sValue = $mDefault;
                }
                $sValue = $this->applyLength($sValue);
                if ($sValue === null) {
                    $sValue = $mDefault;
                }
                if (!$this->isAllowed($sValue)) {
                    $sValue = $mDefault;
                }
                $aReturn[] = $sValue;
            }
        }
        return $aReturn;
    }

    public function getStringArray($mDefault=null) {
        return $this->getStrArray($mDefault);
    }

    public function getArray($mDefault=null) {
        return $this->getStrArray($mDefault);
    }

    /**
     * Get information about a single uploaded file
     * @param mixed mDefault Return this value if no matching value was found
     * @return array A file array with keys (or mDefault if field was not found):
     *      name    => The original name of the uploaded file
     *      type    => The mime type of the file (can be falsified by client)
     *      size    => The size of the uploaded file
     *      tmp_name=> The file location and name as it exists on the server
     *      error   => An error code if there was a problem with the upload (0 means no error)
     *                 See http://php.net/manual/en/features.file-upload.errors.php
     */
    public function getFile($mDefault=null) {
        $aFile = $mDefault;
        if (array_key_exists($this->sFieldName, $_FILES)) {
            $aFile = $_FILES[$this->sFieldName];
        }
        return $aFile;
    }

    /**
     * Get information about one or more uploaded files
     * @param mixed mDefault Return this value if no matching value was found
     * @return array An array of file arrays, see return of getFile() for contents of a file array
     */
    public function getFileArray($mDefault=null) {
        $aFiles = $mDefault;
        if (array_key_exists($this->sFieldName, $_FILES) && is_array($_FILES[$this->sFieldName]['name'])) {
            $aFiles = array();
            $aFileKeys = array_keys($_FILES[$this->sFieldName]['name']);
            foreach ($aFileKeys as $iFileId) {
                $aFiles[] = array(
                    'name'=>$_FILES[$this->sFieldName]['name'][$iFileId],
                    'type'=>$_FILES[$this->sFieldName]['type'][$iFileId],
                    'size'=>$_FILES[$this->sFieldName]['size'][$iFileId],
                    'tmp_name'=>$_FILES[$this->sFieldName]['tmp_name'][$iFileId],
                    'error'=>$_FILES[$this->sFieldName]['error'][$iFileId]
                );
            }
        }
        return $aFiles;
    }

    public function filterRegExp($sRegExp) {
        $this->sRegExp = $sRegExp;
    }

    private function matchesRegExp($mValue) {
        return (is_string($mValue) && is_string($this->sRegExp) && preg_match($this->sRegExp, $mValue) === 1);
    }

    /**
     * Filter the value to be between two numbers (inclusive).
     * To NOT filter one of the numbers (minimum or maximum), set it to null
     * @param mixed mLow The minimum allowed value; integer, float, or null
     * @param mixed mHigh The maximum allowed value; integer, float, or null
     */
    public function filterRange($mLow, $mHigh) {
        $this->mRangeLow = $mLow;
        $this->mRangeHigh = $mHigh;
    }

    private function applyRange($mValue) {
        if (is_int($mValue)) {
            if ($this->mRangeLow !== null) {
                $mValue = max($mValue,intval($this->mRangeLow));
            }
            if ($this->mRangeHigh !== null) {
                $mValue = min($mValue,intval($this->mRangeHigh));
            }
        }
        if (is_float($mValue)) {
            if ($this->mRangeLow !== null) {
                $mValue = max($mValue,floatval($this->mRangeLow));
            }
            if ($this->mRangeHigh !== null) {
                $mValue = min($mValue,floatval($this->mRangeHigh));
            }
        }
        return $mValue;
    }

    /**
     * Filter the length of string values; optionally truncates if too long
     * To NOT have a maximum value, set iMax to null
     * @param int mLow The minimum length of a string
     * @param int|null mHigh The maximum length of a string; set to null to not have a maximum
     * @param bool bTruncate If set to true, will truncate string if over iMax with no error
     */
    public function filterLength($iMin, $iMax, $bTruncate=false) {
        $this->iLengthMin = $iMin;
        $this->iLengthMax = $iMax;
        $this->bTruncateLength = $bTruncate;
    }

    /**
     *
     * @return string|null The proper length value, or null if an invalid length
     */
    private function applyLength($sValue) {
        $mReturn = null;
        if (is_string($sValue)) {
            if (strlen($sValue) < $this->iLengthMin) {
                $this->aErrors[] = "Value is shorter than the minimum length.";
            }
            elseif ($this->iLengthMax !== null && strlen($sValue) > $this->iLengthMax) {
                if ($this->bTruncateLength) {
                    $mReturn = substr($mValue, 0, $this->iLengthMax);
                }
                else {
                    $this->aErrors[] = "Value is longer than the maximum length.";
                }
            }
            else {
                $mReturn = $sValue;
            }
        }
        return $mReturn;
    }

    /**
     * Filter to only allow specific values
     * @param mixed aAllowed An array of allowed values, or a single allowed value
     * @param bool bStrict If set to true, will enforce type checks (see in_array())
     */
    public function filterAllowed($aAllowed, $bStrict=false) {
        // Put single value into an array
        if (!is_array($aAllowed)) {
            $aAllowed = array($aAllowed);
        }
        $this->aAllowed = $aAllowed;
        $this->bAllowedStrict = $bStrict;
    }

    private function isAllowed($mValue) {
        // Allow all if no limits were set
        if ($this->aAllowed === null) { return true; }
        return in_array($mValue, $this->aAllowed, $this->bAllowedStrict);
    }
}

} // Include guard end

?>
