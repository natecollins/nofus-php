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
	echo $db->getHost(); // returns the string used for the host when the current connection was created
	$db->close(); 
	echo $db->getHost(); // returns 'No Connection'
```

- getDatabaseName()
- quoteSmart()
- statementReturn()
- query()
- queryRow()
- queryColumn()
- queryReturn()
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