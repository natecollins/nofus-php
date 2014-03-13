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

/****************************************
 * Config file line examples
 ****************************************

# Pound sign is a line comment
# Variable assignment is done with an "=" sign
var1 = 42
var2 = 94       # comments can appear at the end of a line
name = Example
longname = John Doe     # would parse as "John Doe"
name2 = " Jane Doe "    # to prevent whitespace trimming, add double quotes; would parse as " Jane Doe "
name3 = 'Jerry'         # single quotes parse as normal characters; would parse as "'Jerry'"
words = "Quotes \"inside\" a string"                  # can use double quotes inside double quotes, but must be escaped
specials = "This has #, \, and = inside of it"      # can use special characters in a quoted value
tricky = "half-quoted         # unmatched double quote parses as "\"half-quoted"
badquoted = this is "NOT" a quoted string           # value doesn't start with a quote, so quotes are treated as normal chars
oddquote = "not a quoted value" cause extra         # value will parse as: "\"not a quoted value\" cause extra"
novalue =                     # values can be left blank
enable_keys                   # no assignment delimiter given (aka '='), variable is assigned boolean value true

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

[sql.maria]      # scopes can have sub-scopes as well
auth.server = sql.example.com
auth.user = apache      # e.g. full scope is: sql.maria.auth.user
auth.pw = secure
auth.db = website

 **************************************
 * Invalid examples
 **************************************

my var = my val         # spaces are not allowed in variable identifiers
[]#.$ = something       # only a-zA-Z0-9_- are allow for variable identifier (. is allowed for scope)
a..b = c                # scopes cannot be blank
.d. = e                 # start and end scope can't be blank


 **************************************
 * Use examples
 **************************************

$cf = new ConfigFile("test.conf");
if ($cf->load()) {
    $v1 = $cf->get("var1");         # get value from var1, or null if doesn't exist
    $v9 = $cf->get("var9", 123);    # get value from var9, or 123 if doesn't exist

    $mw = $cf->get("marbles.white", 1);     # get marbles.white, or 1 if doesn't exist
    $pw = $cf->get("sql.maria.auth.pw");    # get sql.maria.auth.pw, or null if doesn't exist

    $sql = $cf->get('sql.maria');           # get a scope
    $svr = $sql->get('auth.server');        # get auth.server (from sql.maria scope), or null if doesn't exist

    $bad = $cf->get('does.not.exist');      # attempt to get a non-existant scope, returns null
}

*/


/**
 * Handles parsing of config files.
 * 
 * Files can be parsed generically, or a ruleset and parse error messages can be predefined.
 */
class ConfigFile {
    // File Info
    private $sFilePath;

    // Parse values
    private $aLineCommentStart;         # multiples allowed
    private $sVarValDelimiter;          # first instance of this is the variable/value delmiiter
    private $sScopeDelimiter;           # character(s) that divides scope levels
    private $sQuoteChar;                # the quote character
    private $sEscapeChar;               # the escape character

    // Errors
    private $aErrors;

    function __construct($sFileToOpen) {
        $this->sFilePath = null;

        $this->aLineCommentStart = array('#','//');
        $this->sVarValDelimiter = "=";
        $this->sScopeDelimiter = ".";
        $this->sQuoteChar = "\"";
        $this->sEscapeChar = "\\";

        $this->aErrors = array();

        # Parse sFileToOpen, if null, then this is a scope query result
        if ($sFileToOpen != null && is_string($sFileToOpen)) {
            $this->sFilePath = $sFileToOpen;
        }
    }

    /**
     * Load in default values for certain variables/scopes. If a variable already
     * exists (e.g. from a file that was already loaded), then this will NOT overwrite
     * the value.
     * @param array $aDefaults An array of "scope.variable"=>"default value" pairs.
     */
    public function preload($aDefaults) {
        //TODO
    }

    /**
     * Attempt to open and parse the config file.
     * @return boolean True if the file loaded without errors, false otherwise
     */
    public function load() {
        # If file is null, then this is a scope query result, do nothing
        if ($this->sFilePath === null) {
            $this->aErrors[] = "Cannot load file; no file was given. (Note: you cannot load() a query result.)";
            return false;
        }

        if ( !(file_exists($this->sFilePath) && !is_dir($this->sFilePath) && is_readable($this->sFilePath)) ) {
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
        if (count($this->aErrors) > 0) return false;
        # Make it past all error conditions, so return true
        return true;
    }

    /**
     * Find the position of the first line comment (ignoring all other rules)
     * @param string $sLine The line to search over
     * @param int (Optional) Offset from start of line in characters to skip before searching
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
     * Find the next unescaped quote character and return it's position in the provided
     * line segment
     * @param string $sLinePart A line or line segment
     * @return int|false The position of the unescaped quote character, or false if not found
     */
    private function findNextQuotePosition($sLinePart) {
        $sRegexQuoteChar = preg_quote($this->sQuoteChar, "/");
        $sRegexEscapeChar = preg_quote($this->sEscapeChar, "/");
        $sCloseQuotePattern = "/[^{$sRegexEscapeChar}]?{$sRegexQuoteChar}/";
        $iMatchPos = false;
        # find next double quote that isn't escaped
        if (preg_match($sCloseQuotePattern,$sLine,$aMatches,PREG_OFFSET_CAPTURE)) {
            $iMatchPos = $aMatches[0][1];
        }
        return $iMatchPos;
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
     * @param int|null $sLineForError If a line number is provided, will add error messages if invalidly quoted
     * @return boolean Returns true if a quoted value exist, false otherwise
     */
    private function hasQuotedValue($sLine, $iLineForError=null) {
        $bQuotedValue = false;
        #################################################
        # - Variable name must be valid
        # - Assignment delimiter must exist after variable name (allowing for whitespace)
        # - First character after assignment delimiter must be a quote (allowing for whitespace)
        # - Assignment delimiter and open quote must not be in a comment
        # - A matching quote character must exist to close the value
        # - The closing quote has no other chars are after it (other than whitespace and comments)
        #################################################
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
            $sQuoteValPattern = "/^[^{$sEscDelim}]+{$sEscDelim}\s*{$sEscQuote}(?:{$sEscEscape}{$sEscQuote}|[^{$sEscQuote}])*(?<!{$sEscEscape}){$sEscQuote}\s*(?:({$sEscCommentStarts}).*)?$/";

            if (preg_match($sQuoteValPattern, $sLine) === 1) {
                $bQuotedValue = true;
            }

            # if not a valid quoted value, but has a valid open quote, add an error
            if ($bQuotedValue == false && $iLineForError !== null) {
                # check if open quote is valid
                $sOpenQuotePattern = "/^[^{$sEscDelim}]+{$sEscDelim}\s*{$sEscQuote}/";
                if (preg_match($sOpenQuotePattern, $sLine) === 1) {
                    $this->addError($iLineForError, "Open quotes without matching close quotes.");
                }
            }
        }

        return $bQuotedValue;
    }

    /**
     * Check to see if line has unescaped quotes in the value.
     * @param string $sLine The line to check
     * @return boolean Returns true if line has unescaped quotes other than those used in a proper quoted value, false otherwise
     */
    private function hasBadQuotes($sLine, $iLineForError=null) {
        $bBadQuotes = false;
        # to check for bad quotes requires a valid variable name and delimiter
        if ($this->hasValueDelimiter($sLine)) {
            $sEscDelim = preg_quote($this->sVarValDelimiter, '/');
            $sEscQuote = preg_quote($this->sQuoteChar, '/');
            $sEscEscape = preg_quote($this->sEscapeChar, '/');
            $sEscCommentStarts = "";
            foreach ($this->aLineCommentStart as $sCommentStart) {
                if ($sEscCommentStarts != "") { $sEscCommentStarts .= "|"; }
                $sEscCommentStarts .= preg_quote($sCommentStart, '/');
            }
            
            $sBadQuotePattern = "//";

            //TODO
        }
        return $bBadQuotes;
    }

    private function getQuotedValue($sLine) {
        //
    }

    private function getVariableValue($sLine, $iLineForError=null) {
        $mValue = false;
        if ($this->hasValueVariableName($sLine)) {
            $mValue = true;
            if ($this->hasValueDelimiter($sLine)) {
               // 
            }
        }
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
        if ($iLineCommentPos !== false && ($iAssignDelimPos === false || $iLineCommentPos < $iAssignDelimPos) ) {
            $sLine = substr($sLine, 0, $iLineCommentPos);
        }

        # if the delimiter exists (non-commented)
        if ($iAssignDelimPos !== false) {
            $sLine = substr($sLine, 0, $iAssignDelimPos);
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
        $sVarNameCheck = $this->getPreDelimiter($sLine);
        $sValidCharSet = "a-zA-Z0-9_\-" . preg_quote($this->sScopeDelimiter, "/");

        # an empty variable name is not valid
        $bValid = ($sVarNameCheck !== "");
        # check for invalid characters
        if (preg_match("/[^{$sValidCharSet}]/", $sVarNameCheck)) {
            $bValid = false;
            if ($iLineForError !== null) {
                $this->addError($iLineForError, "Invalid variable name.");
            }
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
     * Check if the line is in a valid format.
     * Option 1:
     *  - Line has no variable or value name; line is blank or just a comment.
     * Option 2:
     *  - Line has a variable name, but no value due to no assignment delimiter.
     *  - Variable is only made of allowed characters (a-zA-Z0-9_-) plus scope character.
     * Option 3:
     *  - Line has a variable name plus an optional value after the assignment delimiter.
     *  - If value starts and ends with the quote character, ignore the outside quotes and treat the entire quoted string as the value
     */
    private function isValidLine($sLine) {
        // FUNCTION TO BE REMOVED
    }

    /**
     * Parse a line
     */
    private function processLine($iLineNum, $sLine) {
        // DEBUG
        echo PHP_EOL . "PROCESSING LINE " .($iLineNum+1).": {$sLine}" . PHP_EOL;

        $sVarName = $this->getVariableName($sLine, $iLineNum);
        if ($sVarName !== false) {
            echo "NAME         : " . $sVarName . PHP_EOL;
        }
        $sQuotedValue = $this->hasQuotedValue($sLine, $iLineNum);
        echo "QUOTED VALUE : " . ($sQuotedValue ? 'yes' : 'no') . PHP_EOL;


#        # ignore line comments if they are in a quoted value string
#        $iLineCommendSearchStart = 0;
#        # check if quoted value string exists (after assignment delimiter, but before any line comment start)
#        $iAssignDelimPos = $this->findAssignmentDelimiterPosition($sLine);
#        $iOpenQuotePos = $this->findOpenQuotePosition($sLine);
#        $iLineCommentPos = $this->findLineCommentPosition($sLine);
#
#        # the assignment delimiter and open quote must not be in a line comment AND the quote must come after the delimiter
#        if ($iLineCommentPos > $iAssignDelimPos && $iLineCommentPos > $iOpenQuotePos && $iAssignDelimPos < $iOpoenQuotePos) {}
#        # check if quoted string has a matching end quote (non-escaped)
#        # if quoted string value exists, then do not check for line comments until after the close quote
#        //TODO
#        
#
#        # check for and remove line comment data
#        $iLineCommentPos = $this->findLineCommentPosition($sLine, $iLineCommentSearchStart);
#        if ($iLineCommentPos !== false) {
#            $sLine = substr($sLine, 0, $iLineCommentPos);
#        }

        # check for assignment delimiter
        # split on assignment delimiter
        # trim data (both variable and value)
        # if double-quoted on both ends, trim quotes from value data
        # un-escape remaining

    }

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
     * Query the config for a scope/variable. Returns the value or scope on success,
     * or mDefault (default: null) if the query was not found.
     * @param string $sQuery The query string. e.g. "variable", "scope", "scope.variable", etc
     * @param mixed $mDefault The return value should the query not find anything.
     * @return string|null The matching value from the query, or mDefault if not found
     */
    public function get($sQuery, $mDefault=null) {
        //TODO
    }

}

?>
