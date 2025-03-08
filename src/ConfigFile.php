<?php
declare(strict_types=1);

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

/****************************************
 * Config file line examples
 ****************************************

# Pound sign is a line comment
# Variable assignment is done with an "=" sign
var1 = 42
# comments can appear at the end of a line
var2 = 94           # EoL Comment!
name = Example
# would parse as "John Doe"
longname = John Doe
# to prevent whitespace trimming, add double quotes; would parse as " Jane Doe "
name2 = " Jane Doe "
# single quotes parse as normal characters; would parse as "'Jerry'"
name3 = 'Jerry'
# can use double quotes inside double quotes, but must be escaped
words = "Quotes \"inside\" a string"
# can use special characters in a quoted value (escape character must be escaped)
specials = "This has #, \\, and = inside of it"
# value doesn't start with a quote, so quotes are treated as normal chars
badquoted = this is "NOT" a quoted string
# value will parse as: "\"not a quoted value\" cause extra"
oddquote = "not a quoted value" cause extra
# values can be left blank
novalue =
# no assignment delimiter given (aka '='), variable is assigned boolean value true
enable_keys
# variables can be defined multiple times and retrieved as an array
multi_valued = abc
multi_valued = xyz

// Alternate line comment style

# variables can have a scope by placing a dot in their identifier
marbles.green = 2
marbles.blue = 4
marbles.red = 3

# alternatively, you can set scope on variables by making a section using []
[marbles]
white = 6
clear = 8
yellow = 1

[sql.maria]   # scope lines can have sub-scopes as well (and comments)
auth.server = sql.example.com
auth.user = apache  # full scope for this line is: sql.maria.auth.user
auth.pw = secure
auth.db = website

 **************************************
 * Invalid examples (these won't work!)
 **************************************

my var = my val         # spaces are not allowed in variable identifiers
[]#.$ = something       # only a-zA-Z0-9_- are allow for variable identifier (. is allowed for scope)
[my.scope]  = val       # scopes cannot have values
a..b = c                # scopes cannot be blank
.d. = e                 # start and end scope can't be blank

 **************************************
 * Use examples
 **************************************
use Nofus\ConfigFile;
$cf = new ConfigFile("test.conf");
if ($cf->load()) {
    # can preload default values, even after loading
    $cf->preload(
        array(
            "var1"=>12,
            "name"=>"none",
            "enable_keys"=>false,
            "marbles.green"=>0
        )
    );

    $v1 = $cf->get("var1");         # get value from var1, or null if doesn't exist
    $v9 = $cf->get("var9", 123);    # get value from var9, or 123 if doesn't exist

    $arr = $cf->getArray("multi_valued"); # get all values for multi_valued as an array

    $mw = $cf->get("marbles.white", 1);   # get marbles.white, or 1 if doesn't exist
    $pw = $cf->get("sql.maria.auth.pw");  # get sql.maria.auth.pw, or null if doesn't exist

    $sql = $cf->get('sql.maria');         # get a scope
    $svr = $sql->get('auth.server');      # get auth.server (from sql.maria scope)

    $bad = $cf->get('does.not.exist');    # attempt to get a non-existant scope, returns null

    $subScopes = $cf->enumerateScope("sql.maria.auth"); # returns array ['server','user','pw','db']
}

*/

namespace Nofus;

/**
 * Handles parsing of config files.
 */
class ConfigFile {
    // File Info
    private $sFilePath;
    private $bLoaded;

    // Static parse values
    private $aLineCommentStart;     // multiples allowed
    private $sVarValDelimiter;      // first instance of this is the variable/value delmiiter
    private $sScopeDelimiter;       // character(s) that divides scope levels
    private $sQuoteChar;            // the quote character
    private $sEscapeChar;           // the escape character
    private $sScopeCharSet;         // RegExp pattern of characters allow for scopes
    private $sVarNameCharSet;       // RegExp pattern of characters allowed for variable names

    // Dynamic parse values
    private $sCurrentScope;

    // Errors
    private $aErrors;

    // Value keys marked as being preloaded
    private $aPreloaded;

    // Parsed content
    private $aValues;

    function __construct($sFileToOpen=null) {
        $this->sFilePath = null;
        $this->bLoaded = false;

        $this->aLineCommentStart = array('#','//');
        $this->sVarValDelimiter = "=";
        $this->sScopeDelimiter = ".";
        $this->sQuoteChar = "\"";
        $this->sEscapeChar = "\\";
        $this->sScopeCharSet = "a-zA-Z0-9_\-";
        $this->sVarNameCharSet = "a-zA-Z0-9_\-";

        $this->sCurrentScope = "";

        $this->aErrors = array();
        $this->aPreloaded = array();
        $this->aValues = array();

        # Parse sFileToOpen
        if ($sFileToOpen != null && is_string($sFileToOpen)) {
            $this->sFilePath = $sFileToOpen;
        }
    }

    /**
     * Change what strings indicate the start of a comment.
     * WARNING: Change this at your own risk. Setting unusual values here may break parsing.
     * @param array|string $mLineCommentStart An array containing strings which indicate the start of a comment
     *                  OR a string that indicates the start of a comment
     */
    public function overrideCommentStarts($mLineCommentStart) {
        if (!is_array($mLineCommentStart) && is_string($mLineCommentStart)) {
            $mLineCommentStart = array($mLineCommentStart);
        }
        $this->aLineCommentStart = $mLineCommentStart;
    }

    /**
     * Change the delimiter used between variable name and values.
     * WARNING: Change this at your own risk. Setting unusual values here may break parsing.
     * @param string $sVarValDelimiter The string to indicate the delimiter between variable name and value
     */
    public function overrideVariableDelimiter($sVarValDelimiter) {
        $this->sVarValDelimiter = $sVarValDelimiter;
    }

    /**
     * Change the string used as a delimiter between scopes.
     * WARNING: Change this at your own risk. Setting unusual values here may break parsing.
     * @param string $sScopeDelimiter The string to indicate the delimiter between scopes
     */
    public function overrideScopeDelimiter($sScopeDelimiter) {
        $this->sScopeDelimiter = $sScopeDelimiter;
    }

    /**
     * Change the character used to quote variable values.
     * WARNING: Change this at your own risk. Setting unusual values here may break parsing.
     * @param string $sQuoteChar The character to indicate the start and end of a quoted value
     */
    public function overrideQuoteCharacter($sQuoteChar) {
        $this->sQuoteChar = $sQuoteChar;
    }

    /**
     * Change the character used to escape other characters in a variable value
     * WARNING: Change this at your own risk. Setting unusual values here may break parsing.
     * @param string $sEscapeChar The character to indicate an excaped character follows
     */
    public function overrideEscapeCharacter($sEscapeChar) {
        $this->sEscapeChar = $sEscapeChar;
    }

    /**
     * Change the regular expression patterned used to verify valid scope names.
     * WARNING: Change this at your own risk. Setting unusual values here may break parsing.
     * @param string $sScopeCharSet A regexp patterns to indicate allowed characters in a scope name
     */
    public function overrideScopeCharacters($sScopeCharSet) {
        $this->sScopeCharSet = $sScopeCharSet;
    }

    /**
     * Change the regular expression patterned used to verify valid variable names.
     * WARNING: Change this at your own risk. Setting unusual values here may break parsing.
     * @param string $sScopeCharSet A regexp patterns to indicate allowed characters in a variable name
     */
    public function overrideVariableNameCharacters($sVarNameCharSet) {
        $this->sVarNameCharSet = $sVarNameCharSet;
    }

    /**
     * Load in default values for certain variables/scopes. If a variable already
     * exists (e.g. from a file that was already loaded), then this will NOT overwrite
     * the value.
     * @param array $aDefaults An array of "scope.variable"=>"default value" pairs.
     *                  OR array value may also be an array of values
     */
    public function preload($aDefaults) {
        foreach($aDefaults as $sName=>$mValue) {
            if (!array_key_exists($sName,$this->aValues)) {
                $this->aPreloaded[$sName] = true;
                $this->aValues[$sName] = array();
                if (is_array($mValue)) {
                    $this->aValues[$sName] = $mValue;
                }
                else {
                    $this->aValues[$sName][] = $mValue;
                }
            }
        }
    }

    /**
     * Reset the config file object, basically "unloading" everything so it can be reloaded.
     */
    public function reset() {
        $this->bLoaded = false;
        $this->aErrors = array();
        $this->aValues = array();
    }

    /**
     * Attempt to open and parse the config file.
     * @return boolean True if the file loaded without errors, false otherwise
     */
    public function load() {
        # If we've successfully loaded this file before, skip the load and return success
        if ($this->bLoaded == true) {
            return true;
        }

        # If file is null, then this is a scope query result, do nothing
        if ($this->sFilePath === null) {
            $this->aErrors[] = "Cannot load file; no file was given. (Note: you cannot load() a query result.)";
            return false;
        }

        if (!(file_exists($this->sFilePath) && !is_dir($this->sFilePath) && is_readable($this->sFilePath))) {
            $this->aErrors[] = "Cannot load file; file does not exist or is not readable.";
            return false;
        }

        $aLines = file($this->sFilePath, FILE_IGNORE_NEW_LINES);
        if ($aLines === false) {
            $this->aErrors[] = "Cannot load file; unknown file error.";
            return false;
        }

        # Process lines
        foreach ($aLines as $iLineNum => $sLine) {
            $this->processLine($iLineNum, $sLine);
        }

        # If parsing lines generated errors, return false
        if (count($this->aErrors) > 0) {
            return false;
        }
        # Make it past all error conditions, so return true
        $this->bLoaded = true;
        return true;
    }

    /**
     * Find the position of the first line comment (ignoring all other rules)
     * @param string $sLine The line to search over
     * @param int $iSearchOffset Offset from start of line in characters to skip before searching
     * @return int|false The position of line comment start, or false if no line comment was found
     */
    private function findLineCommentPosition($sLine, $iSearchOffset=0) {
        $iStart = false;
        foreach ($this->aLineCommentStart as $sCommentStart) {
            $iStartCheck = strpos($sLine, $sCommentStart, $iSearchOffset);
            if ($iStartCheck !== false && ($iStart === false || $iStartCheck < $iStart)) {
                $iStart = $iStartCheck;
            }
        }
        return $iStart;
    }

    /**
     * Find the position of the first assignment delimiter (ignoring all other rules)
     * @param string $sLine The line to search over
     * @return int|false The position of the assignment delimiter, or false if no delimiter was found
     */
    private function findAssignmentDelimiterPosition($sLine) {
        return strpos($sLine, $this->sVarValDelimiter);
    }

    /**
     * Find the position of the opening double quote character (ignoring all other rules)
     * @param string $sLine The line to search over
     * @return int|false The position of the double quote, or false is not found
     */
    private function findOpenQuotePosition($sLine) {
        return strpos($sLine, $this->sQuoteChar);
    }

    /**
     * Given a line, check to see if it is a valid scope definition
     * @param string $sLine The line to check
     * @return boolean Returns true if well formed and valid, false otherwise
     */
    private function isValidScopeDefinition($sLine) {
        $sValidCharSet = $this->sScopeCharSet;
        $sScopeChar = preg_quote($this->sScopeDelimiter, "/");
        $sEscCommentStarts = "";
        foreach ($this->aLineCommentStart as $sCommentStart) {
            if ($sEscCommentStarts != "") {
                $sEscCommentStarts .= "|";
            }
            $sEscCommentStarts .= preg_quote($sCommentStart, '/');
        }
        #                ---------------- NAME CHARS -------- SCOPE CHAR --- NAME CHARS -------------------- ALLOW COMMENTS AFTER -----
        $sScopePattern = "/^\s*\[\s*(?:[{$sValidCharSet}]+(?:{$sScopeChar}[{$sValidCharSet}]+)*)?\s*\]\s*(?:({$sEscCommentStarts}).*)?$/";

        # default to not a scope
        $bValid = false;
        # check for validity
        if (preg_match($sScopePattern, $sLine) === 1) {
            $bValid = true;
        }

        return $bValid;
    }

    /**
     * Set the current scope (assumes the line is a scope definition) while parsing the file. Does nothing if line is not a scope definition.
     * @param string $sLine The line to get the scope from
     */
    private function setScope($sLine) {
        $sValidCharSet = $this->sScopeCharSet;
        $sScopeChar = preg_quote($this->sScopeDelimiter, "/");
        $sEscCommentStarts = "";
        foreach ($this->aLineCommentStart as $sCommentStart) {
            if ($sEscCommentStarts != "") {
                $sEscCommentStarts .= "|";
            }
            $sEscCommentStarts .= preg_quote($sCommentStart, '/');
        }
        $sScopePattern = (
            # ------------ NAME CHARS --------- SCOPE CHAR -
            "/^\s*\[\s*([{$sValidCharSet}]+(?:{$sScopeChar}" . 
            # - NAME CHARS ---------------------- ALLOW COMMENTS AFTER ---
            "[{$sValidCharSet}]+)*)?\s*\]\s*(?:({$sEscCommentStarts}).*)?$/"
        );

        # check for invalid characters
        if (preg_match($sScopePattern, $sLine, $aMatches) === 1) {
            $this->sCurrentScope = $aMatches[1];
        }
    }

    /**
     * Check if line has a value delimiter. Can only return true if the line
     * also has a valid variable name.
     * @param string $sLine The line to check against
     * @return boolean Returns true if line has a delimiter after a valid variable name
     */
    private function hasValueDelimiter($sLine) {
        $bHasDelim = false;
        if ($this->hasValidVariableName($sLine)) {
            $sEscDelim = preg_quote($this->sVarValDelimiter, '/');
            $sDelimPattern = "/^[^{$sEscDelim}]+{$sEscDelim}/";
            if (preg_match($sDelimPattern, $sLine) === 1) {
                $bHasDelim = true;
            }
        }
        return $bHasDelim;
    }

    /**
     * Checks if the line has a valid quoted value.
     *
     * @param string $sLine The line to check
     * @param int|null $iLineForError If a line number is provided, will add error messages if invalidly quoted
     * @return boolean Returns true if a quoted value exist, false otherwise
     */
    private function hasQuotedValue($sLine, $iLineForError=null) {
        $bQuotedValue = false;
        // #################################################
        // # - Variable name must be valid
        // # - Assignment delimiter must exist after variable name (allowing for whitespace)
        // # - First character after assignment delimiter must be a quote (allowing for whitespace)
        // # - Assignment delimiter and open quote must not be in a comment
        // # - A matching quote character must exist to close the value
        // # - The closing quote has no other chars are after it (other than whitespace/comments)
        // #################################################
        if ($this->hasValidVariableName($sLine)) {
            // at this point, we know the variable name is valid
            $sEscDelim = preg_quote($this->sVarValDelimiter, '/');
            $sEscQuote = preg_quote($this->sQuoteChar, '/');
            $sEscEscape = preg_quote($this->sEscapeChar, '/');
            $sEscCommentStarts = "";
            foreach ($this->aLineCommentStart as $sCommentStart) {
                if ($sEscCommentStarts != "") { $sEscCommentStarts .= "|"; }
                $sEscCommentStarts .= preg_quote($sCommentStart, '/');
            }
            $sQuoteValPattern = (
                "/^[^{$sEscDelim}]+{$sEscDelim}\s*{$sEscQuote}" .
                "(?:{$sEscEscape}{$sEscQuote}|[^{$sEscQuote}])*" .
                "(?<!{$sEscEscape}){$sEscQuote}\s*(?:({$sEscCommentStarts}).*)?$/"
            );

            if (preg_match($sQuoteValPattern, $sLine) === 1) {
                $bQuotedValue = true;
            }
        }
        return $bQuotedValue;
    }

    /**
     * Returns the content from inside a properly quoted value string given a whole line.
     * The content from inside the string may still have escaped values.
     * @param string $sLine The line to operate from
     * @return string The value between the openening and closed quote of the value (does NOT include open/closing quotes); on failure, returns empty string.
     */
    private function getQuotedValue($sLine) {
        $sValue = "";

        if ($this->hasValidVariableName($sLine)) {
            # at this point, we know the variable name is valid
            $sEscDelim = preg_quote($this->sVarValDelimiter, '/');
            $sEscQuote = preg_quote($this->sQuoteChar, '/');
            $sEscEscape = preg_quote($this->sEscapeChar, '/');
            $sEscCommentStarts = "";
            foreach ($this->aLineCommentStart as $sCommentStart) {
                if ($sEscCommentStarts != "") { $sEscCommentStarts .= "|"; }
                $sEscCommentStarts .= preg_quote($sCommentStart, '/');
            }
            $sQuoteValPattern = (
                # ---- NAME -------- DELIMITER ---- OPEN QUOTE -
                "/^[^{$sEscDelim}]+{$sEscDelim}\s*{$sEscQuote}" .
                # --- ALLOW ESCAPED QUOTES ------ NO UNESCAPED QUOTES -
                "((?:{$sEscEscape}{$sEscQuote}|[^{$sEscQuote}])*)" .
                # ---- NON ESCAPED CLOSE QUOTE -------- ALLOW COMMENTS AFTER ---
                "(?<!{$sEscEscape}){$sEscQuote}\s*(?:({$sEscCommentStarts}).*)?$/"
            );

            if (preg_match($sQuoteValPattern, $sLine, $aMatches) === 1) {
                $sValue = $aMatches[1];
            }
        }

        return $sValue;
    }

    /**
     * Get the processed value for the given line. Handles quotes, comments, and unescaping characters.
     * @param string $sLine The line to operate from
     * @param int|null $iLineForError If a line number is provided, will add error messages if invalidly quoted
     * @return string The value processed variable value
     */
    private function getVariableValue($sLine, $iLineForError=null) {
        $mValue = false;
        if ($this->hasValidVariableName($sLine)) {
            $mValue = true;
            if ($this->hasValueDelimiter($sLine)) {
                $mValue = "";
                if ($this->hasQuotedValue($sLine, $iLineForError)) {
                    # getting the quoted value will strip off comments automatically
                    $mValue = $this->getQuotedValue($sLine);
                }
                else {
                    $mValue = $this->getPostDelimiter($sLine);
                    # handle comments
                    $iCommentStart = $this->findLineCommentPosition($mValue);
                    if ($iCommentStart !== false) {
                        $mValue = substr($mValue, 0, $iCommentStart);
                    }
                    $mValue = trim($mValue);
                }
                # handle escaped chars
                $sEscEscape = preg_quote($this->sEscapeChar, '/');
                $sUnescapePattern = "/{$sEscEscape}(.)/";
                $sUnescapeReplace = '${1}';
                $mValue = preg_replace($sUnescapePattern, $sUnescapeReplace, $mValue);
            }
        }
        return $mValue;
    }

    /**
     * Returns the trimmed string before any delimiter on a line.
     *  - Removes comments from line
     *  - If no delimiter is present, returns the whole line (minus any comment)
     * @param string $sLine The line to operate from
     * @return string The value before any delimiter
     */
    private function getPreDelimiter($sLine) {
        $iAssignDelimPos = $this->findAssignmentDelimiterPosition($sLine);
        $iLineCommentPos = $this->findLineCommentPosition($sLine);

        # if comment starts before the delimiter, then the delimiter is commented out; ignore it
        if (
            $iLineCommentPos !== false &&
            ($iAssignDelimPos === false || $iLineCommentPos < $iAssignDelimPos)
        ) {
            $sLine = substr($sLine, 0, $iLineCommentPos);
        }

        # if the delimiter exists (non-commented)
        if ($iAssignDelimPos !== false) {
            $sLine = substr($sLine, 0, $iAssignDelimPos);
        }

        # trim off any whitespace
        return trim($sLine);
    }

    /**
     * Returns the trimmed string after any delimiter on a line.
     *  - If no delimiter is present (or if delimiter is commented out) returns empty string
     *  - Does NOT remove comments from post delimiter content
     * @param string $sLine The line to operate from
     * @return string The value after any delimiter
     */
    private function getPostDelimiter($sLine) {
        $iAssignDelimPos = $this->findAssignmentDelimiterPosition($sLine);
        $iLineCommentPos = $this->findLineCommentPosition($sLine);

        # if comment starts before delimiter, then delimiter is commented; no post delim content
        if (
            $iLineCommentPos !== false &&
            ($iAssignDelimPos === false || $iLineCommentPos < $iAssignDelimPos)
        ) {
            $sLine = "";
        }

        # if the delimiter exists (non-commented)
        if ($iAssignDelimPos !== false) {
            $sLine = substr($sLine, 1 + $iAssignDelimPos);
        }

        # trim off any whitespace
        $sLine = trim($sLine);

        return $sLine;
    }

    /**
     * Checks if the line has a variable name and that it's valid
     * @param string $sLine The line to check
     * @param int|null $iLineForError If provided, will add an error on invalid variable name characters
     * @return boolean Returns true if variable name exists and is valid, false otherwise
     */
    private function hasValidVariableName($sLine, $iLineForError=null) {
        $sValidCharSet = $this->sVarNameCharSet;
        $sScopeChar = preg_quote($this->sScopeDelimiter, "/");
        $sVarNamePattern = (
            "/^\s*(?:[{$sValidCharSet}]+(?:{$sScopeChar}[{$sValidCharSet}]+)*)\s*$/"
        );
        $sVarNameCheck = $this->getPreDelimiter($sLine);

        # default to not a valid name
        $bValid = false;
        # check for invalid characters
        if (preg_match($sVarNamePattern, $sVarNameCheck) === 1) {
            $bValid = true;
        }
        # don't error for empty line
        else if ($sVarNameCheck !== "" && $iLineForError !== null) {
            $this->addError($iLineForError, "Invalid variable name.");
        }

        return $bValid;
    }

    /**
     * Gets a valid variable name for a line, or false if no valid variable name exists.
     * @param string $sLine The line to check
     * @param int|null $iLineForError If provided, will add an error on invalid variable name characters
     * @return string|false The variable name, or false if no valid variable name existed
     */
    private function getVariableName($sLine, $iLineForError=null) {
        $sValidVar = false;
        if ($this->hasValidVariableName($sLine, $iLineForError)) {
            $sValidVar = $this->getPreDelimiter($sLine);
        }
        return $sValidVar;
    }

    /**
     * Process a line into the store values array.
     * @param int $iLineNum The line number processing (for use in error reporting)
     * @param string $sLine The full line from the file to process
     */
    private function processLine($iLineNum, $sLine) {
        if ($this->isValidScopeDefinition($sLine)) {
            $this->setScope($sLine);
        }
        else {
            $sVarName = $this->getVariableName($sLine, $iLineNum);
            if ($sVarName !== false) {
                $sAdjustedName = (
                    $this->sCurrentScope .
                    ($this->sCurrentScope === "" ? "" : $this->sScopeDelimiter) .
                    $sVarName
                );
                # initialize variable name array if doesn't exist (or if it was a preloaded value)
                if (
                    !array_key_exists($sAdjustedName, $this->aValues) ||
                    array_key_exists($sAdjustedName, $this->aPreloaded)
                ) {
                    $this->aValues[$sAdjustedName] = array();
                    # unmark key as preloaded, if it was set
                    unset($this->aPreloaded[$sAdjustedName]);
                }
                # append value to values array
                $this->aValues[$sAdjustedName][] = $this->getVariableValue($sLine, $iLineNum);
            }
        }
    }

    /**
     * Store an error for retrieval with errors() function.
     * @param int $iLine The line on which the error occured (0 based count)
     * @param string $sMessage The error message associated with the line
     */
    private function addError($iLine, $sMessage) {
        $iLine++;   // due to base 0 line indexing
        $this->aErrors[] = "ConfigFile parse error on line {$iLine}: {$sMessage}";
    }

    /**
     * Get a list of errors when attempting to load() the file
     * @return array And array of errors; can be empty if no errors were encountered or the file has not been loaded yet
     */
    public function errors() {
        return $this->aErrors;
    }

    /**
     * Query the config for a scope/variable. Returns the first value or scope on success,
     * or mDefault (default: null) if the query was not found.
     * @param string $sQuery The query string. e.g. "variable", "scope", "scope.variable", etc
     * @param mixed $mDefault The return value should the query not find anything.
     * @return string|ConfigFile|null The matching value from the query, or mDefault if not found
     */
    public function get($sQuery, $mDefault=null) {
        $mVal = $mDefault;
        # try to get value match first
        if (array_key_exists($sQuery, $this->aValues) && count($this->aValues[$sQuery]) > 0) {
            $mVal = $this->aValues[$sQuery][0];
        }
        else {
            # check if this matches any scopes
            $sScopeChar = preg_quote($this->sScopeDelimiter, "/");
            $sQueryStr = preg_quote($sQuery, "/");
            # must match a scope exactly ( "my.scope" should not match "my.scopeless" )
            $sScopePattern = "/^{$sQueryStr}{$sScopeChar}(.+)$/";

            $aScopeMatches = array();
            foreach ($this->aValues as $sName=>$mValue) {
                if (preg_match($sScopePattern, (string)$sName, $aMatch) === 1) {
                    $aScopeMatches[$aMatch[1]] = $mValue;
                }
            }
            if (count($aScopeMatches) > 0) {
                $mVal = new ConfigFile();
                $mVal->preload($aScopeMatches);
            }
        }
        return $mVal;
    }

    /**
     * Query the config for a variable. Returns all values for the given query as an array.
     * If no value for the query exists, returns an empty array.
     * @param string $sQuery The query string. e.g. "variable", "scope.variable", etc
     * @return array And array containing all matching values from the query, or empty array if not found
     */
    public function getArray($sQuery) {
        $aVal = array();
        if (array_key_exists($sQuery, $this->aValues)) {
            $aVal = $this->aValues[$sQuery];
        }
        return $aVal;
    }

    /**
     * Get all name/value pairs that have been parsed from the file.
     * @return array An associative array containing name=>value pairs will full scope names.
     */
    public function getAll() {
        return $this->aValues;
    }

    /**
     * Query to return all available scopes/variables for a given scope level. An empty
     * string (the default) will return top level scopes/variables.
     * @param string $sQuery A scope level to match, or empty string to query for top level scopes
     * @return array An array of available scopes/variables for the given scope level
     */
    public function enumerateScope($sQuery="") {
        $aScopeValues = array();
        $aAllScopes = array_keys($this->aValues);
        if ($sQuery !== "") { $sQuery .= "."; }
        foreach ($aAllScopes as $sScope) {
            if ($sQuery === "" || strpos((string)$sScope, $sQuery) === 0) {
                $sSubScope = substr($sScope, strlen($sQuery));
                $iScopeEnd = strpos($sSubScope, ".");
                $sVal = substr($sSubScope, 0);
                // Grab only the next level of scope
                if ($iScopeEnd !== false) {
                    $sVal = substr($sSubScope, 0, $iScopeEnd);
                }
                $aScopeValues[] = $sVal;
            }
        }
        return array_values(array_unique($aScopeValues));
    }

}
