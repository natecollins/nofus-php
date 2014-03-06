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
words = "Quotes \"inside\" a string"    # to put double quotes inside double quotes, escape them
tricky = "half-quoted         # unmatched double quote parses as "\"half-quoted"
novalue =                     # values can be left blank

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
    // File Values
    private $sFileName;
    private $sPath;
    private $iHandle;

    // Parse values
    private $aLineCommentStart;         # multiples allowed
    private $sBlockCommentStart;
    private $sBlockCommentEnd;
    private $sVarValDelimiter;          # first instance of this is the variable/value delmiiter
    private $sScopeDelimiter;           # character(s) that divides scope levels

    // Parse rules
    //TODO

    // Errors
    private $aErrors;

    function __construct($sFileToOpen, $sAccess="ro") {
        $this->sFileName = null;
        $this->sPath = null;
        $this->iHandle = 0;

        $this->aLineCommentStart = array('#','//');
        $this->sBlockCommentStart = "/*";
        $this->sBlockCommentEnd = "*/";
        $this->sVarValDelimiter = "=";
        $this->sScopeDelimiter = ".";

        $this->aErrors = array();

        # Parse sFileToOpen, if null, then this is a scope query result
        //TODO
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
        if ($this->sFileName === null) {
            $this->aErrors[] = "Cannot load file; no file was given. (Note: you cannot load() a query result.)";
            return false;
        }

        //TODO
    }

    /**
     * Get a list of errors when attempting to load() the file
     * @return array And array of errors; can be empty if no errors were encountered or the file has not been loaded yet
     */
    public function errors() {
        //TODO
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
