<?php
/****************************************************************************************

Copyright 2012 Nathan Collins. All rights reserved.

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
                        'database'=>'my_db'
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

    /**
     * Constructor requires valid MySQL connection server(s) in an array.
     *   Example:
     *     $conn_info = array(
     *           array(
     *                   'host'=>'primarymysql.example.com',
     *                   'username'=>'my_user',
     *                   'password'=>'my_pw',
     *                   'database'=>'my_db'
     *           ),
     *           array(
     *                   'host'=>'secondarymysql.example.com',
     *                   'username'=>'my_user',
     *                   'password'=>'my_pw',
     *                   'database'=>'my_db'
     *           )
     *     );
     * 
     * @param array $conn_info An array containing a list of servers' connection information
     */
    function __construct($conn_info, $bLoadBalance=false) {
        $this->aServers = array();
        $this->iServerIndex = null;
        $this->iQueryCount = 0;
        $this->sLastQuery = null;
        $this->bTransaction = false;
        $this->bPersistent = false;
        $this->cInstance = null;

        # psudeo verify connection info (vars exist, not empty)
        if (is_array($conn_info)) {
            foreach ($conn_info as $conn) {
                $aServ = array();
                foreach (array('host','username','password','database') as $at) {
                    if (isset($conn[$at]) and trim($conn[$at]) != '') $aServ[$at] = $conn[$at];
                }
                if (count($aServ) == 4) $this->aServers[] = $aServ;
            }
        }

        if ($bLoadBalance) $this->loadBalance();
     }

     /**
      * Set whether or not errors should throw exceptions when a MySQL error occurs
      * 
      * @param boolean $silent Do not show exceptions if set to true; does throw exceptions if set to false
      */
     public function silentErrors($silent=true) {
         if ($this->connectionExists()) {
            if ($silent == false) {
                 /* Throw exceptions on SQL error */
                $cInst->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            }
            else {
                /* No exceptions thrown */
                $cInst->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
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
     * DEPRECATED (will be removed in version 1.0)
     * Alias for escapeIdentifier()
     */
    public function quoteColumn($value) {
        return $this->escapeIdentifier($value);
    }

    /**
     * Escape identifiers for use in a query.
     * Note: Only works on table name and column names for the currently connected database.
     * 
     * @param string $sName The potentially dangerous identifier
     * @return string The safe identifier surrounded by backticks (`); if identifer is invalid, returns an empty string ('')
     */
    public function escapeIdentifier($sName) {
        $sSafe = '';
        /* Get all valid table name and column name identifiers */
        $aValids = array_merge($this->getTables(),$this->getAllColumns());
        foreach ($aValids as $sValid) {
            if ($sName == $sValid) {
                $sSafe = '`'.str_replace("`","``",$sValid).'`';
                break;
            }
        }
        return $sSafe;
    }

    /**
     * Enables "load balancing" between all servers in the server array. If using persistent
     *   connections, needs to be called before and query is run.
     *   
     * (In practice, this just randomizes the order of the server array.) 
     */
    public function loadBalance() {
        shuffle($this->aServers);
    }
    
    /**
     * Set connection peristance; if persistance is changed, then recreate the database connection
     * 
     * @param boolean $bPersistent If true, the connnection will be persistent; otherwise it will not be
     */
    public function setPersistentConnection($bPersistent=false) {
        if ($this->bPersistent != $bPersistent) {
            $this->bPersistent = $bPersiseant;
            $this->create(true);
        }
    }

    /**
     * Create a PDO connection to a MySQL server
     * 
     * @param boolean $bReinitialize If set to true, then any curent connection is terminated and a new one is created
     * @return boolean If there is a valid connection or one was created, return true; false otherwise.
     */
    private function create($bReinitialize=false) {
        /* Destroy any existing connection if reinitializing */
        if ($bReinitialize == true) $this->close();
        
        /* Only create if no connection already exists */
        if (!$this->connectionExists()) {
            $this->bTransaction = false;
            for ($i = 0, $n = count($this->aServers); $i < $n; $i++) {
                $aServer = self::$this->aServers[$i];
                try {
                    $cInst = new PDO(
                                "mysql:host={$aServer['host']};dbname={$aServer['database']}",
                                $aServer['username'], $aServer['password'],
                                array(
                                    PDO::ATTR_PERSISTENT=>$this->bPersistent,
                                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
                                )
                            );
                    // enable true prepared statements (instead of emulation, which forces all values to be strings)
                    $cInst->setAttribute( PDO::ATTR_EMULATE_PREPARES, false );
                    // enable errors
                    $this->silentErrors(false);
                }
                /* Shut down all the execptions while on the connection level! */
                catch (Exception $e) {
                    continue;    // connection failed; but we'll keep trying until we run out of servers
                }
                $this->iServerIndex = $i;
                $this->cInstance = $cInst;
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
     * @return string The domain name or IP of the last connection
     */
    public function getHost() {
        if ($this->iServerIndex === null) return 'No Connection';
        return $this->aServers[$this->iServerIndex]['host'];
    }
    
    /**
     * Get the name of the database being used on the connected server
     * 
     * @return string The name of the datbase currently connected to, or empty string ('') if not connected
     */
    public function getDatabaseName() {
        if (!$this->create()) return '';
        return $this->aServers[$this->iServerIndex]['database'];
    }
    
    
    /**
     * Emulate safe-quoting variables to make them safe (actual query uses prepared statements)
     * 
     * @param mixed $xValue A value to escape
     * @return mixed The value escaped by the connection instance 
     */
    public function quoteSmart($xValue) {
        # create connection if one doesn't exist
        if ( !$this->create() ) return null;

        # if inserting NULL, then it might as well be NULL
        if (is_null($xValue)) {
            return 'NULL';
        }

        # magic quotes must die
        if (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc()) {
            trigger_error('Magic quotes has been DEPRECATED and should not be used; please contact your administrator for further help.');
            exit(1);
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
     * @param object $cStatement The PDO statement
     * @return string The dump of query and params, captured into a string
     */
    public function statementReturn($cStatement) {
        ob_start();
        $cStatement->debugDumpParams();
        return ob_get_clean();
    }

    /**
     * Replace a '?' with comma delimited '?'s at the nth occurance of '?'
     * 
     * @param string $sQuery The string that contains '?' for value placeholders
     * @param int $iNth The nth occurance of '?' in the query (First occurance = 1, second = 2, etc)
     * @param int $iCount The number of comma delimted '?' to replace the existing placeholder with
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
     * Record the last query statement attempted
     * 
     * @param object $cStatement The statement to record the query from
     */
    private function recordQuery($cStatement) {
        $sQuery = $this->statementReturn($cStatement);
        if ($this->bTransaction == true) $this->sLastQuery .= "\n\n$sQuery";
        else $this->sLastQuery = $sQuery;
    }

    /**
     * Perform a query. Examples:
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
     * @param string $sQuery String query with ? placeholders for linearly inserted values, or :name placeholders for associative values
     * @param array $aValues Array of values to be escaped and inserted into the query
     * @param int $iFetchStyle The PDO fetch style to query using
     * @param mixed $vFetchArg Additional argument to pass if the fetch style requires it 
     * @return array An array of rows for SELECT; primary key for INSERT (NULL is none returned); number of rows affected for UPDATE/DELETE.
     */
    public function query( $sQuery, $aValues=array(), $iFetchStyle=PDO::FETCH_ASSOC, $vFetchArg=null ) {
        $this->iQueryCount += 1;
        
        # query type
        $bIsInsert = preg_match('/^\s*INSERT/i',$sQuery);
        $bIsUpdateDelete = preg_match('/^\s*(UPDATE|DELETE)/i',$sQuery);

        # the array where the rows are to be stored
        $aRows = array();

        # create connection if one doesn't exist
        if ( !$this->create() ) return null;

        # perform the query
        $cStatement = null;
        $aRows = array();
        try {
            // Catch Array Values and Expand them
            $aExpandedValues = array();
            for ($i=0; $i<count($aValues); $i++) {
                if (is_array($aValues[$i])) {
                    $sQuery = $this->expandValueLocation($sQuery,$i+1,count($aValues[$i]));
                    $aExpandedValues = array_merge($aExpandedValues,$aValues[$i]);
                }
                else {
                    $aExpandedValues[] = $aValues[$i];
                }
            }
            $aValues = $aExpandedValues;

            // Execute Query
            $cStatement = $this->cInstance->prepare($sQuery);
            if ($cStatement == false) trigger_error("SQL Error: Could not prepare query. Query is not valid or references something non-existant.");
            $cStatement->execute($aValues);
            
            /* Fetch rows, if this isn't a select/insert/update/delete */
            if (!($bIsInsert || $bIsUpdateDelete)) {
                if ($vFetchArg === null) {
                    $aRows = $cStatement->fetchAll($iFetchStyle);
                }
                else $aRows = $cStatement->fetchAll($iFetchStyle, $vFetchArg);
            } 
        }
        catch (PDOException $e) {
            $this->rollbackTransaction();
            $aError = $cStatement->errorInfo();
?>            
============================================================
MySQL Error Details
Error Type <?= $aError[1] ?>: <?= $aError[2] ?>

<?
            echo $this->queryReturn($sQuery,$aValues,true);
            echo PHP_EOL;
            echo $this->statementReturn($cStatement);
?>

============================================================
<?
            $this->recordQuery($cStatement);
            trigger_error("Query Failed ({$aError[1]}): {$aError[2]}");
        }
        $this->recordQuery($cStatement);

        # pull AUTO_INCREMENT id if previous query was INSERT
        if ( $bIsInsert ) {
            $iInsertId = $this->cInstance->lastInsertId();

            # if insert id was pulled, return it
            if ( !empty($iInsertId) && intVal($iInsertId) > 0 ) {
                return $iInsertId;
            }
            return null;
        }

        # pull rows affected count if query was UPDATE/etc
        if ( $bIsUpdateDelete ) {
            $iChangeCount = $cStatement->rowCount();

            # if count was pulled, return it
            if ( is_numeric($iChangeCount) and $iChangeCount >= 0 ) {
                return $iChangeCount;
            }
            return null;
        }

        return $aRows;
    }

    /**
     * Returns only the first row from the query; throws error if no rows were returned.
     * Should be used only where the query guarantees a result of at least 1 row.  
     * 
     * @param string $sQuery The query to run
     * @param array $aValues The values the query is to use
     * @return array The first row of the results of the query
     */
    public function queryRow( $sQuery, $aValues=array() ) {
        $aArray = $this->query($sQuery,$aValues);
        $aRow = array();
        if ( is_array($aArray) and count($aArray) > 0 ) {
            $aRow = array_shift($aArray);
        }
        else {
            trigger_error("SQL Query returned no rows from query where one row was required");
        }
        return $aRow;
    }

    /**
     * Return all the values for a column from a given query (defaults to first column)
     * 
     * @param string $sQuery The query to run
     * @param array $aValues The values the query is to use
     * @param int $iColumnNum What column to retrieve (0 based column index)
     */
    public function queryColumn( $sQuery, $aValues=array(), $iColumnNum=0 ) {
        return $this->query($sQuery,$aValues,PDO::FETCH_COLUMN,$iColumnNum);
    }

    /**
     * Return an emulated query with values escaped and inserted as a string; query is NOT executed.
     *   It is possible that the query string returned does not exactly match the query that would be
     *   run as a prepared statement, as this only emulates the escaping that prepared statments would
     *   perform.
     * For assisting in debugging only.
     * 
     * @param string $sQuery The query to run
     * @param array $aValues The values the query is to use
     * @return string The emulated query string with escaped values inserted
     */
    public function queryReturn( $sQuery, $aValues=array(), $bSupressWarning=false) {
        $sReturn = "\n-- [WARNING] This only EMULATES what the prepared statement will run.\n\n";
        if ($bSupressWarning) $sReturn = "\n";
        
        # Catch Array Values and Expand them
        $aExpandedValues = array();
        for ($i=0; $i<count($aValues); $i++) {
            if (is_array($aValues[$i])) {
                $sQuery = $this->expandValueLocation($sQuery,$i+1,count($aValues[$i]));
                $aExpandedValues = array_merge($aExpandedValues,$aValues[$i]);
            }
            else {
                $aExpandedValues[] = $aValues[$i];
            }
        }
        $aValues = $aExpandedValues;
        
        # escape values
        for ($i = 0; $i < count($aValues); $i++) {
            $aValues[$i] = $this->quoteSmart($aValues[$i]);
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
     *   perform.
     * For assisting in debugging only.
     * 
     * @param string $sQuery The query to run
     * @param array $aValues The values the query is to use
     * @return string The emulated query string with escaped values inserted
     */
    public function queryDump( $sQuery, $aValues=array() ) {
        echo "<br /><pre>".$this->queryReturn($sQuery,$aValues)."</pre><br />";
        return null;
    }

    /**
     * Return all possible enum values from a column in index order.
     * 
     * @param string $sTable The name of the table the column field is in
     * @param string $sField The name of the column
     * @return array The enum values in index order
     */
    public function enumValues($sTable, $sField) {
        $aEnums = array();
        $sTable = $this->escapeIdentifier($sTable);
        $sField = $this->escapeIdentifier($sField);
        $sQuery = 'SHOW COLUMNS FROM ' . $sTable . ' LIKE ' . $sField . '';
        $aRow = $this->queryRow($sQuery);
        preg_match_all('/\'(.*?)\'/', $aRow[1], $aEnums);
        if(!empty($aEnums[1])) {
            // organize values based on their mysql order
            foreach($aEnums[1] as $mkey => $mval) $aEnums[$mkey+1] = $mval;
            return $aEnums;
        }
        else return array();
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
            $aRows = $this->query("SHOW TABLES",array(),PDO::FETCH_NUM);
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
     * @param string $sTable The table name to examine; if null, then pull from all tables in this database
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

        $aRows = $this->query($sQuery,$aValues);

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
     * Start a transaction; this will automatically enable persistent database connections
     * 
     * @param boolean|null $bReadCommitted If set to true, sets transaction isolation to "READ COMMITTED";
     *                                     if false, sets it to "REPEATABLE READ"; if left null, no transaction
     *                                     level is set (MySQL default is "REPEATABLE READ").
     */
    public function startTransaction($bReadCommitted=null) {
        # enable persistent connections
        $this->setPersistentConnection(true);

        # create connection if one doesn't exist
        if ( !$this->create() ) return null;

        if ($this->bTransaction == false) {
            if ($bReadCommitted === true) {
                $this->query("SET TRANSACTION ISOLATION LEVEL READ COMMITTED");
            }
            else if ($bReadCommitted === false) {
                $this->query("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ");
            }
            $this->cInstance->beginTransaction();
            $this->bTransaction = true;
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

}

?>
