web-utilities
=============

Utility Classes for Web Development

Public Methods
--------------

- silentErrors()
Set whether or not an exception should be thrown on a MySQL error. Takes a boolean argument. If called without an argument, the default is to set silent to true. Note that the directive only takes effect if the database handle object '$db' has an active connection to the database.

```php
	$db->silentErrors(); _disables exceptions on MySQL errors_  
	$db->silentErrors(true); _also disables exceptions on MySQL errors_  
	$db->silentErrors(false); _an exception will be thrown on MySQL errors from this connection_  
```
- connectionExists()
- quoteColumn()
- escapeIdentifier()
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