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

/* Example database authentication for mirrored server setup */
/*
function dbauth() {
        $SERVERS = array(
                array(
                        'host'=>'primarymysql.example.com',
                        'username'=>'my_user',
                        'password'=>'my_pw',
                        'database'=>'my_db',
                        'port'=>3306            # optional, defaults to 3306
                ),
                array(
                        'host'=>'secondarymysql.example.com',
                        'username'=>'my_user',
                        'password'=>'my_pw',
                        'database'=>'my_db'
                )
        );
        return $SERVERS;
}
*/

# Include guard, for people who can't remember to use '_once'
if (!defined('__DBCONNECT_GUARD__')) {
    define('__DBCONNECT_GUARD__',true);

/**
 *  A wrapper class for MySQL PDO connections.
 *  Provides:
 *      Seamless failover between multiple servers.
 *      Easy utility functions for identifying table columns and enum values.
 *      Emulates prepared statements into dumps of constructed queries for debugging.
 *      Tracks query count and last query run.
 *      Auto-rollback of transactions when a query fails.
 *      Pass an array as a value to a query; provides auto-expansion of array to comma-delimited values.
 *      Provides a safe mechanism to validate table and column names.
 *
 * @author Nathan Collins
 */
class DBConnect {
    private $aServers;         // server array - where to connect to
    private $iServerIndex;     // index of the server currently connected to
    private $iQueryCount;      // number of queries run in this instance of this class
    private $sLastQuery;       // the last query that attempted to run
    private $bTransaction;     // whether or not we are running a transaction
    private $bPersistent;      // whether to establish a persistent connection to the database
    private $cInstance;        // the instance of the PDO connection
    private $cStatement;       // the current statement (needed for queryLoop()/queryNext())
    private $bDebug;           // Enabled detailed debug info display
    private $bAutoDump;        // When enabled, will auto dump error information to the output stream
    private $sErrMessage;      // The error message if an exception is thrown

    /**
     * Constructor requires valid MySQL connection server(s) in an array.
     *   Example:
     *     $conn_info = array(
     *           array(
     *                   'host'=>'primarymysql.example.com',
     *                   'username'=>'my_user',
     *                   'password'=>'my_pw',
     *                   'database'=>'my_db',
     *                   'port'=>3306            # optional, defaults to 3306
     *           ),
     *           array(
     *                   'host'=>'secondarymysql.example.com',
     *                   'username'=>'my_user',
     *                   'password'=>'my_pw',
     *                   'database'=>'my_db'
     *           )
     *     );
     *
     * @param array conn_info An array containing a list of servers' connection information
     */
    function __construct($conn_info, $bLoadBalance=false) {
        $this->aServers = array();
        $this->iServerIndex = null;
        $this->iQueryCount = 0;
        $this->sLastQuery = null;
        $this->bTransaction = false;
        $this->bPersistent = false;
        $this->cInstance = null;
        $this->cStatement = null;
        $this->bDebug = false;
        $this->bAutoDump = false;
        $this->sErrMessage = null;

        # psudeo verify connection info (vars exist, not empty)
        if (is_array($conn_info)) {
            foreach ($conn_info as $conn) {
                $aServ = array('port'=>3306);
                foreach (array('host','username','password','database') as $at) {
                    if (isset($conn[$at]) && trim($conn[$at]) != '') { $aServ[$at] = $conn[$at]; }
                }
                # optional port
                if (array_key_exists('port', $conn)) { $aServ['port'] = intval($conn['port']); }
                # ensure all fields exist, otherwise we ignore the server
                if (count($aServ) == 5) $this->aServers[] = $aServ;
            }
        }

        if ($bLoadBalance) { $this->loadBalance(); }
    }

    /**
     * Set (or unset) the query log and whether or not additional debugging info will be displayed on errors.
     * @param bool bAutoDump Will enable auto dumping debug info to output if true, or disable on false
     */
    public function enableDebugInfo($bAutoDump=true) {
        $this->bDebug = true;
        $this->bAutoDump = $bAutoDump;
    }

    /**
     * Disables detailed error information and also disables auto dumping of information
     */
    public function disableDebugInfo() {
        $this->bDebug = false;
        $this->bAutoDump = false;
    }

    /**
     * Set whether or not errors should throw exceptions when a MySQL error occurs
     *
     * @param boolean silent Do not show exceptions if set to true; does throw exceptions if set to false
     */
    public function silentErrors($silent=true) {
        if ($this->connectionExists()) {
           if ($silent == false) {
                /* Throw exceptions on SQL error */
               $this->cInstance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
           }
           else {
               /* No exceptions thrown */
               $this->cInstance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
           }
       }
    }

    /**
     * Check database connection
     *
     * @return boolean Returns true if a connection to the database exists; false otherwise
     */
    public function connectionExists() {
        if ($this->cInstance === null) {
            return false;
        }
        return true;
    }

    /**
     * Escape identifiers for use in a query.
     * Note: Only works on table name and column names for the currently connected database.
     *
     * @param string sName The potentially dangerous identifier
     * @param boolean bBacktick If true (the default) resulting successes are surrounded by backticks (`), otherwise results are just the identifier.
     * @return string The safe identifier surrounded by backticks (`) if requested; if identifer is invalid, returns an empty string ('')
     */
    public function escapeIdentifier($sName, $bBacktick=true) {
        $sSafe = '';
        /* Get all valid table name and column name identifiers */
        $aValids = array_merge($this->getTables(),$this->getAllColumns());
        foreach ($aValids as $sValid) {
            if ($sName == $sValid) {
                $sSafe = $sValid;
                if ($bBacktick) $sSafe = '`'.str_replace("`","``",$sSafe).'`';
                break;
            }
        }
        return $sSafe;
    }

    /**
     * Enables "load balancing" between all servers in the server array.
     *
     * (In practice, this just randomizes the order of the server array.)
     */
    public function loadBalance() {
        shuffle($this->aServers);
    }

    /**
     * Set connection peristance; if persistance is changed, then recreate the database connection
     *
     * @param boolean bPersistent If true, the connnection will be persistent; otherwise it will not be
     */
    public function setPersistentConnection($bPersistent=false) {
        if ($this->bPersistent != $bPersistent) {
            $this->bPersistent = $bPersistent;
            $this->create(true);
        }
    }

    /**
     * Get the current PDO instance.
     *
     * @return PDO|null The current instance of the PDO connection, or null if no connection exists.
     */
    public function getPDO() {
        return $this->cInstance;
    }

    /**
     * Create a PDO connection to a MySQL server
     *
     * @param boolean bReinitialize If set to true, then any curent connection is terminated and a new one is created
     * @return boolean If there is a valid connection or one was created, return true; false otherwise.
     */
    private function create($bReinitialize=false) {
        /* Destroy any existing connection if reinitializing */
        if ($bReinitialize == true) $this->close();

        /* Only create if no connection already exists */
        if (!$this->connectionExists()) {
            $this->bTransaction = false;
            for ($i = 0, $n = count($this->aServers); $i < $n; $i++) {
                $aServer = $this->aServers[$i];
                try {
                    $cInst = new PDO(
                                "mysql:host={$aServer['host']};dbname={$aServer['database']};port={$aServer['port']}",
                                $aServer['username'], $aServer['password'],
                                array(
                                    PDO::ATTR_PERSISTENT=>$this->bPersistent,
                                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                                )
                            );
                    // enable true prepared statements (instead of emulation, which forces all values to be strings)
                    $cInst->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
                    // default to throwing exceptions for PDO errors
                    $cInst->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                }
                /* Shut down all the execptions while on the connection level! */
                catch (Exception $e) {
                    continue;    // connection failed; but we'll keep trying until we run out of servers
                }
                $this->iServerIndex = $i;
                $this->cInstance = $cInst;
                $this->cStatement = null;
                /* Connection created */
                return true;
            }
            /* Could not create a connection */
            return false;
        }
        /* Connection already exists */
        return true;
    }

    /**
     * Close the PDO connection
     */
    public function close() {
        $this->cInstance = null;   // destroying the variable triggers a connection close during object destruction
    }

    /**
     * Get host name of connected server
     *
     * @return string The domain name or IP of the last connection, or empty string if not connected
     */
    public function getHost() {
        if ($this->iServerIndex === null) return '';
        return $this->aServers[$this->iServerIndex]['host'];
    }

    /**
     * Get the name of the database being used on the connected server
     *
     * @return string The name of the datbase currently connected to, or empty string if not connected
     */
    public function getDatabaseName() {
        if (!$this->create()) return '';
        return $this->aServers[$this->iServerIndex]['database'];
    }


    /**
     * Emulate safe-quoting variables to make them safe (actual query uses prepared statements)
     *
     * @param mixed xValue A value to escape
     * @return mixed The value escaped by the connection instance
     */
    public function quoteFake($xValue) {
        # create connection if one doesn't exist
        if ( !$this->create() ) return null;

        # if inserting NULL, then it might as well be NULL
        if (is_null($xValue)) {
            return 'NULL';
        }

        # quote if not a number
        if ( !(is_int($xValue) || is_float($xValue)) ) {
            $xValue = $this->cInstance->quote($xValue);
        }

        return $xValue;
    }

    /**
     * Get a string representing the query and values for a given SQL statement
     *
     * @param object cStatement The PDO statement
     * @return string The dump of query and params, captured into a string
     */
    public function statementReturn($cStatement) {
        ob_start();
        $cStatement->debugDumpParams();
        echo "FullSQL:" . PHP_EOL;
        echo $cStatement->queryString . PHP_EOL;
        return ob_get_clean();
    }

    /**
     * Replace a '?' with comma delimited '?'s at the nth occurance of '?'
     *
     * @param string sQuery The string that contains '?' for value placeholders
     * @param int iNth The nth occurance of '?' in the query (First occurance = 1, second = 2, etc)
     * @param int iCount The number of comma delimted '?' to replace the existing placeholder with
     * @return The new query
     */
    private function expandValueLocation($sQuery,$iNth,$iCount) {
        $sNewQuery = $sQuery;
        if ($iNth > 0 && $iCount > 1) {
            $sReplaceString = substr(str_repeat(",?",$iCount),1);
            $iMatch = 0; // The number of matches already found
            $iQueryLen = strlen($sQuery);
            for ($i=0; $i<$iQueryLen; $i++) {
                if ($sQuery[$i] == '?') $iMatch++;
                if ($iMatch == $iNth) {
                    $sNewQuery = substr($sQuery,0,$i) . $sReplaceString . substr($sQuery,$i+1);
                    break;
                }
            }
        }
        return $sNewQuery;
    }

    /**
     * Replace all anonymous placeholders in query that has an array as a value with multiple
     * placeholders, also replaceing the array with multiple separate values.
     *
     * @param string sQuery The query string with placeholders that may be expanded
     * @param array aValues An array of values; values that are non-empty arrays will be expanded
     */
    private function expandQueryPlaceholders(&$sQuery,&$aValues) {
        $aExpandedValues = array();
        $iPlaceholderLoc = 0;
        foreach ($aValues as $key=>$value) {
            if (is_array($value)) {
                // We can't have an empty array
                if (count($value) == 0) {
                    $this->triggerError("Error: Cannot pass empty array to value placeholder #{$iPlaceholderLoc} ({$key}) in query: {$sQuery}");
                }
                $sQuery = $this->expandValueLocation($sQuery,$iPlaceholderLoc+1,count($value));
                // Adding more placeholders shifts the current placeholder location
                $iPlaceholderLoc += count($value) - 1;
                // not sure what would happen if you mix anonymous placeholders (?)
                // with named placeholders (:param). probably shouldn't do that.
                $aExpandedValues = array_merge($aExpandedValues,$value);
            }
            else if (is_int($key) ) {
                // use shifted location for anonymous placeholders
                $aExpandedValues[$iPlaceholderLoc] = $value;
            }
            else {
                // preserve named placeholders key
                $aExpandedValues[$key] = $value;
            }
            $iPlaceholderLoc++;
        }
        $aValues = $aExpandedValues;
    }

    /**
     * Record the last query statement attempted into the query log
     *
     * @param object cStatement The statement to record the query from
     */
    private function recordQuery($cStatement) {
        static $exceed_msg = "LAST QUERY LOG DISABLED : Exceeded 64 MB limit.";
        if ($this->bDebug) {
            if ($this->sLastQuery == $exceed_msg || strlen($this->sLastQuery) > 67108864) {
                $this->sLastQuery = $exceed_msg;
            }
            else {
                $sQuery = $this->statementReturn($cStatement);
                if ($this->bTransaction == true) { $this->sLastQuery .= "\n\n$sQuery"; }
                else { $this->sLastQuery = $sQuery; }
            }
        }
    }

    /**
     * Perform a query. Will return all the rows at once for SELECT. For very large datasets, see queryLoop()/queryNext().
     * Examples:
     *     $sQuery = "SELECT name,age FROM users WHERE hair_color = ?";
     *     $aValues = array("brown");
     *     $sQuery = "SELECT name,age FROM users WHERE hair_color = :hair";
     *     $aValues = array(":hair"=>"brown");
     *
     * Note: If you use '?' to identify variable positions, you MAY pass an array as a value, and it will be expanded and comma delimited.
     *   For example, this query:
     *     $sQuery = "SELECT name,age FROM users WHERE hair_color IN (?) AND age > ?";
     *     $aValues = array(array("brown","red","black"),20);
     *   Would translate into:
     *     $sQuery = "SELECT name,age FROM users WHERE hair_color IN (?,?,?) AND age > ?";
     *     $aValues = array("brown","red","black",20);
     *
     * For queries with only a single value, you may pass the value directly
     *     $sQuery = "SELECT name,age FROM users WHERE hair_color = ?";
     *     $aValues = "brown";
     *
     * @param string|array(PDOStatement,bool,bool) mQuery String query with ? placeholders for linearly inserted values, or :name placeholders for associative values; or already prepared statement
     * @param array|mixed aValues Array of values to be escaped and inserted into the query
     * @param int iFetchStyle The PDO fetch style to query using
     * @param mixed vFetchArg Additional argument to pass if the fetch style requires it
     * @param boolean bFetchAll If true, all rows from a SELECT will be returned; if false, no rows will be returned (see queryNext())
     * @param boolean bRecordQuery If false, will not place query into query log (hides extra query calls internal to DBConnect)
     * @return array|int|null|false An array of rows for SELECT; primary key for INSERT (NULL is none returned); number of rows affected for UPDATE/DELETE/REPLACE. If a SQL error occurs, an Exception is thrown and false is returned.
     */
    public function query($mQuery, $aValues=array(), $iFetchStyle=PDO::FETCH_ASSOC, $vFetchArg=null, $bFetchAll=true, $bRecordQuery=true) {
        $this->iQueryCount += 1;

        // If value was passed directly (for single value queries), place it into an array
        if (!is_array($aValues)) {
            $aValues = array($aValues);
        }

        # the array where the rows are to be stored
        $aRows = array();
        # clear the last statement
        $this->sErrMessage = null;
        $this->cStatement = null;
        $bIsInsert = false;
        $bIsUpdateDelete = false;

        # Check if we received a query string
        if ( is_string($mQuery) ) {
            # catch array values and expand them
            $this->expandQueryPlaceholders($mQuery,$aValues);

            $mQuery = $this->prepare($mQuery);
        }

        # Check if we recieved a prepared PDOStatement
        if ( is_array($mQuery) && count($mQuery) == 3 && $mQuery[0] instanceof PDOStatement ) {
            # We use this as our statement
            $this->cStatement = $mQuery[0];
            $bIsInsert = $mQuery[1];
            $bIsUpdateDelete = $mQuery[2];
        }
        else {
            $this->triggerError("Error: Method query() does not have a valid prepared statement to execute against.");
            return false;
        }

        # run query
        try {
            // Execute Query
            $this->cStatement->execute($aValues);

            // Only fetch rows if requested
            if ($bFetchAll == true) {
                /* Fetch rows, if this isn't a select/insert/update/delete */
                if (!($bIsInsert || $bIsUpdateDelete)) {
                    if ($vFetchArg === null) {
                        $aRows = $this->cStatement->fetchAll($iFetchStyle);
                    }
                    else $aRows = $this->cStatement->fetchAll($iFetchStyle, $vFetchArg);
                }
            }
        }
        catch (PDOException $e) {
            $this->rollbackTransaction();
            $this->recordQuery($this->cStatement);
            $this->triggerErrorDump("Error: Query execute failed.");
            return false;
        }
        if ($bRecordQuery) {
            $this->recordQuery($this->cStatement);
        }

        # pull AUTO_INCREMENT id if previous query was INSERT
        if ( $bIsInsert ) {
            $iInsertId = $this->cInstance->lastInsertId();

            # if insert id was pulled, return it
            if ( !empty($iInsertId) && intVal($iInsertId) > 0 ) {
                return intVal($iInsertId);
            }
            return null;
        }

        # pull rows affected count if query was UPDATE/etc
        if ( $bIsUpdateDelete ) {
            $iChangeCount = $this->cStatement->rowCount();

            # if count was pulled, return it
            if ( is_numeric($iChangeCount) && $iChangeCount >= 0 ) {
                return $iChangeCount;
            }
            return null;
        }

        # close the cursor if we're done
        if ($bFetchAll || $bIsInsert || $bIsUpdateDelete) {
           $this->cStatement->closeCursor();
        }

        return $aRows;
    }

    /**
     * Creates a prepared PDOStatement object that can be passed to the query() function in lieu of a query string.
     * By passing the prepared PDOStatement, you can significantly increase performance when running the same query
     * multiple times.
     * Note: The placeholder expansion feature is disabled when using this method.
     * Example:
     *     $sQuery = "SELECT name,age FROM users WHERE hair_color = ?";
     *     $pPrepared = $db->prepare($sQuery);
     *
     *     $aValues1 = array("brown");
     *     $aValues2 = array("black");
     *     $aResults1 = $db->query($pPrepared,$aValues1);
     *     $aResults2 = $db->query($pPrepared,$aValues2);
     *
     * @param string sQuery String query with ? placeholders for linearly inserted values, or :name placeholders for associative values
     * @param int iReconnectAttempts If server has gone away, will attempt this many reconnects before failing
     * @return array(PDOStatement,bool,bool)|false The statement for the prepared query. If a SQL error occurs, an Exception is thrown and false is returned.
     */
    public function prepare($sQuery, $iReconnectAttempts=1) {
        $this->sErrMessage = null;
        $oStatement = false;

        # query type
        $bIsInsert = preg_match('/^\s*INSERT/i',$sQuery);
        $bIsUpdateDelete = preg_match('/^\s*(UPDATE|REPLACE|DELETE)/i',$sQuery);

        # create connection if one doesn't exist
        if ( !$this->create() ) {
            $this->triggerError("Error: Could not establish connection to server.");
            return false;
        }

        # catch timed out connections and attempt to reconnect
        try {
            # prepare query
            $oStatement = $this->cInstance->prepare($sQuery);
        }
        catch (PDOException $e) {
            $sExceptMsg = $e->getMessage();
            if (strpos($sExceptMsg, 'has gone away') !== false) {
                if ($iReconnectAttempts > 0) {
                    $this->close();
                    return $this->prepare($sQuery, $iReconnectAttempts - 1);
                }
                else {
                    $this->triggerError("Error: Lost connection to SQL server and could not re-connect.");
                    return false;
                }
            }
        }
        if ($oStatement == false) {
            $this->triggerErrorDump("Error: SQL could not prepare query; it is not valid or references something non-existant.".PHP_EOL.PHP_EOL.$sQuery);
            return false;
        }

        return array($oStatement,$bIsInsert,$bIsUpdateDelete);
    }


    /**
     * Executes a SELECT query for use with queryNext(). Does not return any query data; all rows are to be
     * retrieved with queryNext()
     * Example:
     *     $sQuery = "SELECT name, address FROM phonebook WHERE state = ?";
     *     $aValues = array("Michigan");
     *     $dbc->queryLoop($sQuery,$aValues);
     *     while ($aRow = $dbc->queryNext()) {
     *         echo "{$aRow['name']} lives at {$aRow['address']}" . PHP_EOL;
     *     }
     *
     * @param string sQuery String query with ? placeholders for linearly inserted values, or :name placeholders for associative values
     * @param array aValues Array of values to be escaped and inserted into the query
     */
    public function queryLoop($sQuery, $aValues=array()) {
        $this->query($sQuery, $aValues, PDO::FETCH_ASSOC, null, false);
    }

    /**
     * Returns one row from a SELECT query as an array(), starting with the first row. Each sequential call
     * to queryNext() will return the next row from the results. If no more rows are available, the null
     * is returned. See queryLoop() for example.
     * @return array|false A row from the query as an array, or boolean false if no more rows are left.
     */
    public function queryNext($iFetchStyle=PDO::FETCH_ASSOC) {
        return $this->cStatement->fetch($iFetchStyle);
    }

    /**
     * Returns only the first row from the query, or null if no rows matched
     *
     * @param string sQuery The query to run
     * @param array aValues The values the query is to use
     * @param int iFetchStyle The PDO fetch style to query using
     * @return array|null The first row of the results of the query; or null if no rows found
     */
    public function queryRow($sQuery, $aValues=array(), $iFetchStyle=PDO::FETCH_ASSOC) {
        $aArray = $this->query($sQuery,$aValues,$iFetchStyle);
        $aRow = null;
        if ( is_array($aArray) && count($aArray) > 0 ) {
            $aRow = array_shift($aArray);
        }
        return $aRow;
    }

    /**
     * Return all the values for a column from a given query (defaults to first column)
     *
     * @param string sQuery The query to run
     * @param array aValues The values the query is to use
     * @param int iColumnNum What column to retrieve (0 based column index)
     */
    public function queryColumn($sQuery, $aValues=array(), $iColumnNum=0) {
        return $this->query($sQuery,$aValues,PDO::FETCH_COLUMN,$iColumnNum);
    }

    /**
     * Return an emulated query with values escaped and inserted as a string; query is NOT executed.
     *   It is possible that the query string returned does not exactly match the query that would be
     *   run as a prepared statement, as this only emulates the escaping that prepared statments would
     *   perform. Support for using labeled values is limited and may not be accurate.
     * For assisting in debugging only.
     *
     * @param string sQuery The query to run
     * @param array aValues The values the query is to use
     * @return string The emulated query string with escaped values inserted
     */
    public function queryReturn($sQuery, $aValues=array(), $bSupressWarning=false) {
        $sReturn = "\n-- [WARNING] This only EMULATES what the prepared statement will run.\n\n";
        if ($bSupressWarning) $sReturn = "\n";

        # Replace labels
        foreach ($aValues as $mKey=>$mVal) {
            if (is_string($mKey) && strlen($mKey) > 1 && $mKey[0] == ':') {
                $sEscapedVal = $this->quoteFake($mVal);
                $sQuery = str_replace($mKey,$sEscapedVal,$sQuery);
                unset($aValues[$mKey]);
            }
        }

        # Catch Array Values and Expand them
        $this->expandQueryPlaceholders($sQuery,$aValues);

        # Escape values
        foreach ($aValues as $mKey=>$mVal) {
            $aValues[$mKey] = $this->quoteFake($mVal);
        }

        # Replace question marks with sprintf specifiers
        $sQuery = str_replace("%","%%",$sQuery); // escape existing '%' chars so sprintf doesn't grab them
        $sQuery = str_replace("?","%s",$sQuery);

        # merge values into query
        $sReturn .= trim(vsprintf($sQuery,$aValues)).PHP_EOL.PHP_EOL;

        return $sReturn;
    }

    /**
     * Dump an emulated query with values escaped and inserted into an HTML stream; query is NOT executed.
     *   It is possible that the query string returned does not exactly match the query that would be
     *   run as a prepared statement, as this only emulates the escaping that prepared statments would
     *   perform. Support for using labeled values is limited and may not be accurate.
     * For assisting in debugging only.
     *
     * @param string sQuery The query to run
     * @param array aValues The values the query is to use
     * @return string The emulated query string with escaped values inserted
     */
    public function queryDump($sQuery, $aValues=array()) {
        echo "<pre>".$this->queryReturn($sQuery,$aValues)."</pre>";
        return null;
    }

    /**
     * Return all possible enum values from a column in index order.
     *
     * @param string sTable The name of the table the column field is in
     * @param string sField The name of the column
     * @return array The enum values in index order
     */
    public function enumValues($sTable, $sField) {
        $aEnums = array();
        $sSafeTable = $this->escapeIdentifier($sTable);
        $sSafeField = $this->escapeIdentifier($sField, false);
        $sQuery = "SHOW COLUMNS FROM {$sSafeTable} LIKE '{$sSafeField}'";

        $aRows = $this->query($sQuery,array(),PDO::FETCH_NUM,null,true,false);
        $aRow = array_shift($aRows);

        preg_match_all('/\'(.*?)\'/', $aRow[1], $aMatchEnums);
        if(!empty($aMatchEnums[1])) {
            // organize values based on their mysql order
            foreach($aMatchEnums[1] as $mkey => $mval) { $aEnums[$mkey+1] = $mval; }
        }
        return $aEnums;
    }

    /**
     * Get a list of tables for this database
     *
     * @return array The array of table identifier names
     */
    public function getTables() {
        static $aTables = array();
        /* Only run the query to get table names the first time; additional calls will just use the static variable */
        if (count($aTables) == 0) {
            $aRows = $this->query("SHOW TABLES",array(),PDO::FETCH_NUM,null,true,false);
            foreach ($aRows as $aRow) {
                $aTables[] = $aRow[0];
            }
            $aTables = array_unique($aTables);
        }
        return $aTables;
    }

    /**
     * Get a list of column names for this database
     *
     * @return array The array of columns identifier names
     */
    public function getAllColumns() {
        static $aColumns = array();
        /* Only run the query to get column names the first time; additional calls will just use the static variable */
        if (count($aColumns) == 0) {
            $aInfos = $this->getTableColumns();
            foreach ($aInfos as $aInfo) {
                $aColumns[] = $aInfo['name'];
            }
            $aColumns = array_unique($aColumns);
        }
        return $aColumns;
    }

    /**
     * Return all columns for a table, including type info and flags in index order
     *   Data returned for each column includes:
     *     name                 = The name of the column
     *     is_nullable          = If the column is allowed to be NULL
     *     is_autokey           = If the column is an auto_increment field
     *
     * @param string sTable The table name to examine; if null, then pull from all tables in this database
     * @return array An array of all the columns with data regarding each, in index order
     */
    public function getTableColumns($sTable=null) {
        $sQuery     = "
                    SELECT column_name, column_default, is_nullable, data_type, character_maximum_length,
                        numeric_precision, column_type, column_key, extra
                    FROM information_schema.columns
                    WHERE table_schema = ?";
        $aValues = array($this->getDatabaseName());
        if ($sTable != null) {
            $sQuery .= "
                        AND table_name = ?";
            $aValues[] = $sTable;
        }
        $sQuery     .= "
                    ORDER BY ordinal_position ASC";

        $aRows = $this->query($sQuery,$aValues,PDO::FETCH_ASSOC,null,true,false);

        $aCols = array();
        foreach ($aRows as $aRow) {
            $aCols[] = array(
                    'name'=>$aRow['column_name'],
                    'is_nullable'=>($aRow['is_nullable'] == 'NO') ? false : true,
                    'is_autokey'=>strpos($aRow['extra'],'auto_increment') === false ? false : true
                );
        }
        return $aCols;
    }

    /**
     * Start a transaction.
     *
     * @param boolean|null bReadCommitted If set to true, sets transaction isolation to "READ COMMITTED";
     *                                     if false, sets it to "REPEATABLE READ"; if left null, no transaction
     *                                     level is set (MySQL default is "REPEATABLE READ").
     */
    public function startTransaction($bReadCommitted=null) {
        $this->sErrMessage = null;
        # create connection if one doesn't exist
        if ( !$this->create() ) return null;

        if ($this->bTransaction == false) {
            if ($bReadCommitted === true) {
                $sQuery = "SET TRANSACTION ISOLATION LEVEL READ COMMITTED";
                $this->query($sQuery,array(),PDO::FETCH_ASSOC,null,true,false);
            }
            else if ($bReadCommitted === false) {
                $sQuery = "SET TRANSACTION ISOLATION LEVEL REPEATABLE READ";
                $this->query($sQuery,array(),PDO::FETCH_ASSOC,null,true,false);
            }
            $this->cInstance->beginTransaction();
            $this->bTransaction = true;
            // Clear the last query log
            $this->sLastQuery = "";
        }
    }

    /**
     * Commit a transaction
     */
    public function commitTransaction() {
        if ($this->bTransaction == true) {
            $this->cInstance->commit();
            $this->bTransaction = false;
        }
    }

    /**
     * Rollback a transaction
     *
     * @return boolean Returns true on rollback; false if no transaction was taking place
     */
    public function rollbackTransaction() {
        if ($this->bTransaction == true) {
            $this->cInstance->rollBack();
            $this->bTransaction = false;
            return true;
        }
        return false;
    }

    /**
     * Return the number of queries run since this object was created
     *
     * @return int The number of queries run
     */
    public function getQueryCount() {
        return $this->iQueryCount;
    }

    /**
     * Return info about the last query run
     *
     * @return string A dump of the last query run; if last query was
     *     part of a transaction, then returns a dump of all queries
     *     run since the transaction was started.
     */
    public function getLast() {
        return $this->sLastQuery;
    }

    /**
     * All errors pass through here
     * @param string sMsg The DBConnect error message
     * @param bool bDump If true, dumps query info triggering error
     */
    private function triggerError($sMsg, $bDump=false) {
        $this->sErrMessage = $sMsg;
        if ($this->bDebug) {
            $this->getErrorInfo($bDump);
            throw new Exception($this->sErrMessage);
        }
        else {
            // The non-debug message
            throw new Exception("A database error has occurred");
        }
    }

    /**
     * Trigger error with dump of query information if autodump is enabled
     */
    private function triggerErrorDump($sMsg) {
        $this->triggerError($sMsg, true);
    }

    /**
     * Get information regarding the last error/exception thrown
     *
     * @param bool bDump If set to true and autodumping is enabled, outputs the error info directly into a HTML stream
     * @return string Returns the error info
     */
    public function getErrorInfo($bDump=false) {
        ob_start();
        echo "========================================================".PHP_EOL;
        echo "** DBConnect Error **".PHP_EOL;
        echo $this->sErrMessage. PHP_EOL;
        if ($bDump) {
            if ($this->cStatement !== null) {
                $aError = $this->cStatement->errorInfo();
                echo "========================================================".PHP_EOL;
                echo "** SQL Error Info **".PHP_EOL;
                echo "ERROR {$aError[1]} ({$aError[0]}): {$aError[2]}".PHP_EOL;
                echo PHP_EOL;
                echo $this->statementReturn($this->cStatement) . PHP_EOL;
                echo PHP_EOL . PHP_EOL;
            }
            if (!empty($this->sLastQuery)) {
                echo "========================================================".PHP_EOL;
                echo "** Query Log **";
                echo PHP_EOL;
                echo $this->getLast() . PHP_EOL;
                echo PHP_EOL;
            }
        }
        echo "========================================================".PHP_EOL;
        $sErrInfo = ob_get_clean();

        if ($this->bAutoDump && $bDump) {
            echo "<pre>";
            echo htmlspecialchars($sErrInfo, ENT_QUOTES);
            echo "</pre>";
        }
        return $sErrInfo;
    }
}

} // Include guard end

?>
