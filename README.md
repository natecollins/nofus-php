web-utilities
=============

__Utility Classes for Web Development__  
In the example code below, the DBConnect object has been instantiated as `$db`.


Public Methods
--------------

- silentErrors()  
Takes  
	boolean  
Returns  
	nothing  
	
Set whether or not an exception should be thrown on a MySQL error. Takes a boolean argument. If called without an argument, the default is to set silent to true. Note that the directive only takes effect if the database handle object has an active connection to the database.

```php
	$db->silentErrors(); //disables exceptions on MySQL errors  
	$db->silentErrors(true); //also disables exceptions on MySQL errors  
	$db->silentErrors(false); //an exception will be thrown on MySQL errors from this connection  
```
- connectionExists()  
Takes  
	nothing  
Returns  
	boolean  
	
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
- _quoteColumn()_
> DEPRECATED in favor of escapeIdentifier() (quoteColumn() is 
> actually just an alias for escapeIdentifier)

- escapeIdentifier()  
Takes  
	string  
Returns  
	string  

Sanitizes an identifier (table or column name) which may have come from an untrusted source (e.g. form input). If the passed string matches any column or table name, the first match is returned in a backticked string. If it doesn't match any table or column name, it returns an empty string. This is useful in preventing SQL injection attacks in cases where it is difficult or impossible to build a statement without a table or column name from an untrusted source.

```php
	$inColumn = $_GET{"column"};
	if ($escapeIdentifier($inColumn) != '')
	{
		// the identifier matches either a column or table
		// it's safe to prepare and run a statement with it
	}
	else
	{
		echo "$inColumn is not a valid identifier!"; // possible injection attack
	}
```
- loadBalance()
- setPersistentConnection()
- close()
- getHost()
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