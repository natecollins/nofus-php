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

TODO

*/

# Include guard, for people who can't remember to use '_once'
if (!defined('__USERDATA_GUARD__')) {
    define('__USERDATA_GUARD__',true);

/**
 * Handles validation of 
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
        $this->aAllowed = null;
        $this->bAllowedStrict = false;

        if (!is_string($sFieldName)) {
            $this->aErrors[] = "Invalid UserData field name specified; name must be a string.");
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
        //TODO filterRegExp
        //TODO filterLength
        //TODO filterAllowed
        //TODO
    }

    public function getString($mDefault=null) {
        return $this->getStr($mDefault);
    }

    public function getInt($mDefault=null) {
        //TODO filterRange
        //TODO filterAllowed
        //TODO
    }

    public function getInteger($mDefault=null) {
        return $this->getInt($mDefault);
    }

    public function getFloat($mDefault=null) {
        //TODO filterRange
        //TODO filterAllowed
        //TODO
    }

    public function getBool($mDefault=null) {
        //TODO
    }

    public function getBoolean($mDefault=null) {
        return $this->getBool($mDefault);
    }

    public function getArray($mDefault=null) {
        //TODO filterRegExp
        //TODO filterLength
        //TODO filterAllowed
        //TODO
    }

    public function getFile($mDefault=null) {
        //TODO
    }

    public function getFileArray($mDefault=null) {
        //TODO
    }

    public function filterRegExp($sRegExp) {
        $this->sRegExp = $sRegExp;
    }

    private function applyRegExp($mValue) {
        //TODO
    }

    public function filterRange($mLow, $mHigh) {
        //TODO
        $this->mRangeLow = $mLow;
        $this->mRangeHigh = $mHigh;
    }

    private function applyRange($mValue) {
        //TODO
    }

    public function filterLength($iMin, $iMax) {
        //TODO
        $this->iLengthMin = $iMin;
        $this->iLengthMax = $iMax;
    }

    private function applyLength($mValue) {
        //TODO
    }

    public function filterAllowed($aAllowed, $bStrict=false) {
        //TODO
        $this->aAllowed = $aAllowed;
        $this->bAllowedStrict = $bStrict;
    }

    private function applyAllowed($mValue) {
        //TODO
    }
}

} // Include guard end


?>
