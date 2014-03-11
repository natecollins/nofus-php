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
words = "Quotes "inside" a string"                  # can use double quotes inside double quotes
specials = "This has #, \, and = inside of it"      # can use special characters in a quoted value
tricky = "half-quoted         # unmatched double quote parses as "\"half-quoted"
badquoted = this is "NOT" a quoted string           # value doesn't start with a quote, so quotes are treated as normal chars
oddquote = "not a quoted value" cause extra         # value will parse as: "\"not a quoted value\" cause extra"
novalue =                     # values can be left blank
enable_keys                   # no assignment delimiter given (aka '='), variable is assigned boolean value true

// Alternate line comment style
// Block comments are also allowed in the style of / * Comment * / (spaces removed)

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
    private $sBlockCommentStart;
    private $sBlockCommentEnd;
    private $sVarValDelimiter;          # first instance of this is the variable/value delmiiter
    private $sScopeDelimiter;           # character(s) that divides scope levels
    private $sQuoteChar;                # the quote character
    private $sEscapeChar;               # escape character

    // Parse state
    private $bInsideBlockComment;

    // Errors
    private $aErrors;

    function __construct($sFileToOpen) {
        $this->sFilePath = null;

        $this->aLineCommentStart = array('#','//');
        $this->sBlockCommentStart = "/*";
        $this->sBlockCommentEnd = "*/";
        $this->sVarValDelimiter = "=";
        $this->sScopeDelimiter = ".";
        $this->sQuoteChar = "\"";
        $this->sEscapeChar = "\\";

        $this->bInsideBlockComment = false;

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

        $sFileData = file_get_contents($this->sFilePath);
        if ($sFileData === false) {
            $this->aErrors[] = "Cannot load file; unknown file error.";
            return false;
        }

        # remove block comments before splitting into lines

        $aLines = preg_split('/^\R$/', $sFileData);

        # Process lines
        foreach ($aLines as $iLineNum => $sLine) {
            $this->processLine($iLineNum, $sLine);
        }

        # If parsing lines generated errors, return false
        if (count($this->aErrors) > 0) return false;
        # Make it past all error conditions, so return true
        return true;
    }

    private function removeBlockComments($sFileData) {
        

        return $sFileData;
    }

    /**
     * Check for the end of a block comment and handle processing of it
     * @param string $sLine The line to check
     * @return string The line after removal of any block comment end
     */
    private function handleBlockEnd($sLine) {
        if ($this->bInsideBlockComment) {
            # check if line has end of block comment
            $iEndCheck = strpos($sLine,$this->sBlockCommentEnd);
            # if true, find first block end and remove it and comment data before it
            if ($iEndCheck !== false) {
                $iBlockEndLen = strlen($this->sBlockCommentEnd);
                $sLine = substr($sLine,$iEndCheck+$iBlockEndLen);
                $this->bInsideBlockComment = false;
            }
            # if false, then entire line is inside block comment
            else {
                $sLine = '';
            }
        }
        return $sLine;
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
     * Checks if the line has a valid quoted value. Assumes we are not in a block comment.
     *  - First non-whitespace character after assignment delimiter must be a quote
     *  - A matching quote character must exist to close the value
     *  - The closing quote has no other chars are after it (other than whitespace and comments)
     *  - Assignment delimiter and open quote must not be in a comment
     * @param string $sLine The line to check
     * @return boolean Returns true if a quoted value exist, false otherwise
     */
    private function hasQuotedValue($sLine) {
        $iLineCommentPos = $this->findLineCommentPosition($sLine);
        $iAssignDelimPos = $this->findAssignmentDelimiterPosition($sLine);
        $iOpenQuotePos = $this->findOpenQuotePosition($sLine);

        $bIsQuoted = true;
        if ($iOpenQuotePos < $iAssignDelimPos || $iLineCommentPos < $iOpenQuotePos) {
            $bIsQuoted = false;
        }

        return $bIsQuoted;
    }

    /**
     * Check if the line is in a valid format. Assumes we are not in a block comment.
     * Option 1:
     *  - Line has no variable or value name; line is blank or just a comment.
     * Option 2:
     *  - Line has a variable name, but no value due to no assignment delimiter.
     *  - Variable is only made of allowed characters (a-zA-Z0-9_-) plus scope character.
     *  - Inline block comments should be ignored.
     * Option 3:
     *  - Line has a variable name plus an optional value after the assignment delimiter.
     *  - If value starts and ends with the quote character, ignore the outside quotes and treat the entire quoted string as the value
     *  - Inline block comments outside of a quoted value should be ignored.
     */
    private function isValidLine($sLine) {
        #################################################
        # Remove any line comment before quoted value
        #################################################
        $iOpenQuotePos = $this->findOpenQuotePosition($sLine);
        $iLineCommentPos = $this->findLineCommentPosition($sLine);
        # should the possibilty of a line comment exist
        if ($iLineCommentPos !== false) {
            # if line comment starts before an open quote, then line comment is definitely not in a quoted value
            if ($iOpenQuotePos === false || $iLineCommentPos < $iOpenQuotePos) {
                $sLine = substr($sLine, 0, $iLineCommentPos);
            }
        }
        

        # remove inline block quotes except for those in a quoted value string
        $sEscapedBlockStart = preg_quote($this->sBlockCommentStart, '/');
        $sEscapedBlockEnd = preg_quote($this->sBlockCommentEnd, '/');
        $sBlockPattern = "/{$sEscapedBlockStart}.*?{$sEscapedBlockEnd}/";
        $sLine = preg_replace('//', '', $sLine);
        # check if delimiter exists
    }

    /**
     * Parse a line
     */
    private function processLine($iLineNum, $sLine) {
        # check if starting already inside a block comment
        $sLine = $this->handleBlockEnd($sLine);

        # ignore line comments if they are in a quoted value string
        $iLineCommendSearchStart = 0;
        # check if quoted value string exists (after assignment delimiter, but before any line comment start)
        $iAssignDelimPos = $this->findAssignmentDelimiterPosition($sLine);
        $iOpenQuotePos = $this->findOpenQuotePosition($sLine);
        $iLineCommentPos = $this->findLineCommentPosition($sLine);

        # the assignment delimiter and open quote must not be in a line comment AND the quote must come after the delimiter
        if ($iLineCommentPos > $iAssignDelimPos && $iLineCommentPos > $iOpenQuotePos && $iAssignDelimPos < $iOpoenQuotePos) {}
        # check if quoted string has a matching end quote (non-escaped)
        # if quoted string value exists, then do not check for line comments until after the close quote
        //TODO
        

        # check for and remove line comment data
        $iLineCommentPos = $this->findLineCommentPosition($sLine, $iLineCommentSearchStart);
        if ($iLineCommentPos !== false) {
            $sLine = substr($sLine, 0, $iLineCommentPos);
        }

        # ignore block comments if they are in a quoted value string
        //TODO

        # check for block comment open/close pairs and remove them
        $sEscapedBlockStart = preg_quote($this->sBlockCommentStart, '/');
        $sEscapedBlockEnd = preg_quote($this->sBlockCommentEnd, '/');
        $sBlockPattern = "/{$sEscapedBlockStart}.*?{$sEscapedBlockEnd}/";
        $sLine = preg_replace($sBlockPattern,'',$sLine);
        if ($sLine === null) {
            $this->aError[] = "Block commend parse failure at line {$iLineNum}";
            $sLine = '';    # this line has failed us for the last time
        }

        # check for new block comment start
        $iBlockCheck = strpos($sLine, $this->sBlockCommentStart);
        if ($iBlockCheck !== false) {
            $sLine = substr($sLine,0,$iBlockCheck);
            $this->bInsideBlockComment = true;
        }

        # check for assignment delimiter
        # split on assignment delimiter
        # trim data (both variable and value)
        # if double-quoted on both ends, trim quotes from value data
        # un-escape remaining

        // TEST OUTPUT
        echo $sLine . PHP_EOL;
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
