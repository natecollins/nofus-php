web-utilities
==============

Web-utilities is a set of simple-to-use tools designed to deal with some of the standard headaches when web programming with PHP. Each tool can be used independently of the rest.

DBConnect
--------------
A class to handle MySQL compatible database connections. Features include:

  - Automatic failover between multiple servers
  - Safe escaping of data via prepared statements
  - Based off PDO extension
  - Query tracking
  - Query construction emulation (for debugging)
  - Safe escaping of user supplied table and column names

**Examples:**
```php
$sql_servers = array(
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

$db = new DBConnect($sql_servers);

$query = "SELECT firstname, lastname FROM users WHERE age > ?";
$values = array(21);

$names = $db->query($query, $values);

foreach ($names as $row) {
    echo "{$row['lastname']}, {$row['firstname']}";
}
```


__Utility Classes for Web Development__  
In the example code below, the DBConnect object has been instantiated as `$db`.


Public Methods
--------------

- **silentErrors()**  
Takes: boolean  
Returns: nothing  
	
Set whether or not an exception should be thrown on a MySQL error. Takes a boolean argument. If called without an argument, the default is to set silent to true. Note that the directive only takes effect if the database handle object has an active connection to the database, since rather than setting an instance value in the object it actually sets a PDO attribute of the connection.

```php
	$db->silentErrors(); //disables exceptions on MySQL errors  
	$db->silentErrors(true); //also disables exceptions on MySQL errors  
	$db->silentErrors(false); //an exception will be thrown on MySQL errors from this connection  
```
- **connectionExists()**  
Takes: nothing  
Returns: boolean  
	
Determines whether or not the database object has an active connection to the database.

```php
	if ($db->connectionExists()) 
	{
		do(aThing);
	}
	else
	{
		echo "No connection to database";
	}
```
- ***quoteColumn()***
> DEPRECATED in favor of escapeIdentifier() (quoteColumn() is 
> actually just an alias for escapeIdentifier())

- **escapeIdentifier()**  
Takes: string  
Returns: string  

Sanitizes an identifier (table or column name) which may have come from an untrusted source (e.g. form input). If the passed string matches any column or table name, the first match is returned in a backticked string. If it doesn't match any table or column name, it returns an empty string. This is useful in preventing SQL injection attacks in cases where it is difficult or impossible to build a statement without a table or column name from an untrusted source.

```php
	$inColumn = $_GET{"column"};
	if ($db->escapeIdentifier($inColumn) != '')
	{
		// the identifier matches either a column or table
		// it's safe to prepare and run a statement with it
	}
	else
	{
		echo "$inColumn is not a valid identifier!"; // possible injection attack
	}
```

- **loadBalance()**  
Takes: nothing  
Returns: nothing  

Performs a sort of load balancing by randomizing the array of servers once per call. 

Possible example:
```php
	// $db->aServers addresses are ('mysql1', 'mysql2', 'mysql3')
	$db->loadBalance();
	// $db->aServers addresses are now ('mysql3', 'mysql1', 'mysql2')
```

- **setPersistentConnection()**  
Takes: boolean  
Returns: nothing  

Sets or disables persistent database connections for the object. If called with no argument, the default is false. If the current connection state is the same as the argument given, does nothing. If the current connection state differs from the argument, the connection persistence is toggled, and the object is recreated.

- **close()**  
Takes: nothing  
Returns: nothing  

Closes the PDO object.

```php
	$db->close(); // sets the PDO object to null
```

- **getHost()**  
Takes: nothing  
Returns: string  

Returns either the IP address or hostname of the currently active connection, depending on which was used to create it. If no connection is active, returns the string "No Connection".

```php
	echo $db->getHost(); // prints the string used for the host when the current connection was created
	$db->close(); 
	echo $db->getHost(); // prints 'No Connection'
```

- **getDatabaseName()**  
Takes: nothing  
Returns: string  

Returns the database string that was used when the currently active connection was created. If no connection is active, returns an empty string.

```php
	echo $db->getDatabaseName(); // prints the database string for the current connection
	$db->close();
	echo $db->getDatabaseName(); // prints nothing
```

- **quoteSmart()**  
Takes: string  
Returns: string  

A wrapper for PDO::quote. Returns the given string with proper quoting and escaping for the relevant database engine. Since it requires a PDO object for that, it creates one if one doesn't already exist. If get\_magic\_quotes\_gpc() exists in the global namespace, triggers an error to that effect and exits nonzero. If the string is a number, it is returned unquoted. Otherwise, it is passed through PDO::quote and returned.

```php
	echo $db->quoteSmart("123"); // prints 123
	echo $db->quoteSmart("I'm a string"); // prints 'I''m a string'
```

- **statementReturn()**  
Takes: prepared statement object  
Returns: string  

A wrapper for PDO::debugDumpParams. Returns the statement, along with any bound parameters, using output buffering.

- **query()**  
Takes: string(statement), optional array(params), optional int(fetchtype), optional [mixed or null], optional boolean(fetchall)  
Returns: varies based on type of statement  

Executes a statement, given the statement as a string and an array of parameters (if needed). Returns all matching rows as an array of arrays. For retrieving very large result sets, see queryLoop()/queryNext().

The return value depends on the type of statement executed:  
	- SELECT: an array of rows (empty array if there were no rows)
	- INSERT: the primary key for the insert (or NULL if none was returned)  
	- UPDATE/DELETE/REPLACE: number of rows affected  

Examples:
```php
    $sQuery = "SELECT name,age FROM users WHERE hair_color = ?";
    $aValues = array("brown");
    $aRows = $db->query($sQuery,$aValues);
```
```php
    $sQuery = "SELECT name,age FROM users WHERE hair_color = :hair";
    $aValues = array(":hair"=>"brown");
    $aRows = $db->query($sQuery,$aValues);
```

Note: If you use '?' to identify variable positions, you MAY pass an array as a value, and it will be expanded and comma delimited.
For example, this query:
```php
    $sQuery = "SELECT name,age FROM users WHERE hair_color IN (?) AND age > ?";
    $aValues = array(array("brown","red","black"),20);
    $aRows = $db->query($sQuery,$aValues);
```
Would translate into:
```php
    $sQuery = "SELECT name,age FROM users WHERE hair_color IN (?,?,?) AND age > ?";
    $aValues = array("brown","red","black",20);
    $aRows = $db->query($sQuery,$aValues);
```

For queries with only a single value, you may pass the value directly
```php
    $sQuery = "SELECT name,age FROM users WHERE hair_color = ?";
    $sValue = "brown";
    $aRows = $db->query($sQuery,$sValue);
```

Returns an array of rows for SELECT.
Returns integer auto increment id or null for INSERT.
Returns integer of rows affected for UPDATE, DELETE, REPLACE.
Returns false and throws a E_USER_WARNING on a SQL related error.


- **queryLoop()**  
Takes: string (statement), array (params)  
Returns: nothing  

Executes a statement, but returns nothing. Retrieval of rows is expected to be done using queryNext().

```php
    $sQuery = "SELECT name, address FROM phonebook WHERE state = ?";
    $aValues = array("Michigan");
    $db->queryLoop($sQuery,$aValues);
    while ($aRow = $db->queryNext()) {
        echo "{$aRow['name']} lives at {$aRow['address']}" . PHP_EOL;
    } 
```

- **queryNext()**  
Takes: optional int(fetchtype)  
Returns: array OR false  

Retrieves the next row from a previously called queryLoop() as an array. If no more rows are available, it returns false. See queryLoop().

- **queryRow()**  
Takes: string (statement), array (params), boolean (require row, default: true)  
Returns: array  

Executes a statement and returns the first row as an array. If third arguement is 'true' (the default), will trigger an E_USER_ERROR if no rows are returned.

```php
    // Assuming an 'id' of 33 exists
    $sQuery = "SELECT name FROM users WHERE id = ?";
    $aValues = array(33);
    $aRow = $db->queryRow($sQuery,$aValues);

    echo $aRow['name'] . PHP_EOL;
```

```php
    // Assuming 'id' of 123 does not exist
    $sQuery = "SELECT name FROM users WHERE id = ?";
    $aValues = array(123);
    $aRow = $db->queryRow($sQuery,$aValues,false);   // This returns null, but throws no error.
    $aRow = $db->queryRow($sQuery,$aValues);         // This will throw an E_USER_ERROR
```

- **queryColumn()**  
Takes: string (statement), array (params), int (column index)  
Returns: array  

Given a column index, returns all values for that column that are returned by the statement. If no column index is passed, the first column is returned.

- **queryReturn()**  
Takes: string (statement), array (params), boolean  
Returns: string  

Returns a string representing the statement as it would be run if it were passed into query() with the given params, but does not actually execute the statement. Primarily used for debugging purposes to see how the params would be executed. It is possible that the returned statement may differ from the statement as it would be executed. The third argument is a boolean which determines if the notice "**[WARNING] This only EMULATES what the prepared statement will run.**" preceeding the return is suppressed (default is false, pass true to suppress the warning).

- **queryDump()**  
Takes: string (statement), array (params), boolean  
Returns: string  

Prints out into a HTML stream a string representing the statement as it would be run if it were passed into query() with the given params, but does not actually execute the statement. Primarily used for debugging purposes to see how the params would be executed. It is possible that the returned statement may differ from the statement as it would be executed. The third argument is a boolean which determines if the notice "**[WARNING] This only EMULATES what the prepared statement will run.**" preceeding the return is suppressed (default is false, pass true to suppress the warning).

- **enumValues()**  
Takes: string (table name), string (column name)  
Returns: array  

Queries the database to retrieve all possible values for an enum of a specified table and column. Returns an array containing these values.

- **getTables()**  
Takes: nothing  
Returns: array  

Queries the database for a listing of all available tables. Return the tables names in an array.

- **getAllColumns()**  
Takes: nothing  
Returns: array  

Queries all tables for all their column names. Returns these column names as an array.
PERFORMANCE NOTE: This function only queries the database the FIRST time it is used. After which it remembers the columns and doesn't bother re-querying the database on subsequent calls.

- **getTableColumns()**  
Takes: string (table name, optional)  
Returns: array  

Queries the database for information on columns from a given table. If no table is specified, then it queries all tables for column info. It returns columns info cordered by ordinal position. Currently, this function only returns: 'name' (string), 'is_nullable' (bool), 'is_autokey' (bool)

- **startTransaction()**  
Takes: boolean|null (optional)  
Returns: nothing  

Start a transaction. Optionally, can pass a boolean to set the transaction isolation. If set to true, sets transaction isolation to "READ COMMITTED"; if false, sets it to "REPEATABLE READ"; if left null, no transaction level is set (MySQL default is "REPEATABLE READ").

- **commitTransaction()**  
Takes: nothing  
Returns: nothing  

Commits a previously started transaction to the database.

- **rollbackTransaction()**  
Takes: nothing  
Returns: boolean  

Attempts to rollback a previously started transaction. Returns false if there was no previously started transaction, or true otherwise.

- **getQueryCount()**  
Takes: nothing  
Returns: int  

Return the number of queries run since this object was created.

- **getLast()**  
Takes: nothing  
Returns: string  

Returns a dump of the last query run; if last query was part of a transaction, then returns a dump of all queries run since the transaction was started.


Private Methods
---------------

- **create()**  
- **expandValueLocation()**  
- **recordQuery()**  


UserData
--------------
A class to handle parsing and limitations on suspicious data.

  - Set rules prior to parsing user data, rejecting violations.
  - Can return a list of human readable violations.
  - Works with strings, integers, floats, arrays, and files.

- **WORK IN PROGRESS**

