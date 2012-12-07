web-utilities
=============

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

Performs a sort of load balancing by randomizing the array of servers once per call. If persistent connections are used, this needs to be called before every statement. 

```php
	// $db->aServers addresses are ('mysql1', 'mysql2', 'mysql3')
	$db->loadBalance();
	// $db->aServers addresses are now ('mysql3', 'mysql1', 'mysql2')
```

- **setPersistentConnection()**  
Takes: boolean  
Returns: nothing  

Sets or disables persistent database connections for the object. If called with no argument, the default is false. If the current connection state is the same as the argument given, does nothing. If the current connection state differs from the argument, the connection persistence is toggled, and the object is recreated.

```php
	$db->setPersistentConnection(); // set persistence to false (if it isn't already)
	$db->setPersistentConnection(false); // set persistence to false (if it isn't already)
	$db->setPersistentConnection(true); // set persistence to true (if it isn't already)
	// if the persistence state is changed, the $db object will be recreated
```

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
Takes: array (string, optional [value or array of values], optional int, optional [mixed or null])  
Returns: varies based on type of statement  

Executes a statement (not just a query), given an array containing the statement as a string, then either a single parameter or an array of parameters (if needed), and optionally, PDO fetch style and its argument. The return value depends on the type of statement executed:  
	- SELECT: an array of rows  
	- INSERT: the primary key for the insert (or NULL if none was returned)  
	- UPDATE/DELETE: number of rows affected  

```php
	$sQuery = "SELECT * FROM things WHERE id =?";
	$aValues = array(1, 3, 5);
	$db->query($sQuery, $aValues);
```

- **queryRow()**  
Takes: string (statement), array (params)  
Returns: array  

Executes a statement and returns the first row as an array. Triggers an error if no rows are returned, so should only be used for queries that are expected to return at least one row.

- **queryColumn()**  
Takes: string (statement), array (params), int (column index)  
Returns: array  

Given a column index, returns all values for that column that are returned by the statement. If no column index is passed, the first column is returned.

- **queryReturn()**  
Takes: string (statement), array (params), boolean  
Returns: string  

Returns a string representing the statement as it would be run if it were passed into query() with the given params, but does not actually execute the statement. Primarily used for debugging purposes to see how the params would be executed. It is possible that the returned statement may differ from the statement as it would be executed. The third argument is a boolean which determines if the notice "**[WARNING] This only EMULATES what the prepared statement will run.**" preceeding the return is suppressed (default is false, pass true to suppress the warning).


- queryDump()
- enumValues()
- getTables()
- getAllColumns()
- getTableColumns()
- startTransaction()
- commitTransaction()
- rollbackTransaction()
- getQueryCount()
- getLast()

Private Methods
---------------

- create()
- expandValueLocation()
- recordQuery()