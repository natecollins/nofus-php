web-utilities
=======================

Web-utilities is a set of simple-to-use tools designed to deal with some of the standard headaches
when web programming with PHP. Each tool can be used independently of the rest.  

* [configfile.php](#configfilephp) - Easy parser for config files
* [userdata.php](#userdataphp) - Simple validator for user data
* [dbconnect.php](#dbconnectphp) - Quick, safe interface for making MySQL/MariaDB queries


configfile.php
-----------------------
A class to read in plain text config files.

  - Simple "variable = value" syntax
  - Allows quoted string values
  - Allows line comments, including end line
  - Allows variable scopes
  - Allows scope section definitions
  - Allows valueless variables
  - Allows multiple values per variable name

**Sample Config File**:  
```
####################################################
# My Config File
# Comment style 1
// Comment style 2

email = me@example.com
name = John Doe     # End line comments allowed

debug_mode

nickname = " John \"Slick\" Doe "

date.birth = 1975-02-18

[address]
home.line_1 = 123 Rural Rd
home.state = Ohio
home.city = Delta

work.line_1 = 456 Main St.
work.line_2 = Acme Corporation
work.state = Ohio
work.city = Napoleon

[children]
name = Alice
name = Bobby
name = Chris

```

**Examples:**
```php
$cf = new ConfigFile('app.conf');

if (!$cf->load()) {
    echo "Could not load file!";
    exit(1);
}

####################################################
# Simple Variable
$email = $cf->get('email');
// if the email variable exists in the file, returns it as a string; if not, returns null

####################################################
# Simple Variable with Default Value
$phone = $cf->get('phone', '555-1234');
// if the phone variable exists in the file, returns it as a string; if not, returns '555-1234'

####################################################
# Simple Valueless Variable
$debug = $cf->get('debug_mode', false);
// valueless variables return true if they exist, otherwise returns the custom default of false

####################################################
# Get Quoted Value
$nickname = $cf->get('nickname');
echo "==${$nickname}==";
# will print: == John "Slick" Doe ==

####################################################
# Get Variable with Scope
$birthdate = $cf->get('date.birth');

####################################################
# Get Scope, Example 1
$home = $cf->get('address.home');
$home_line_1 = $home->get("line_1");

####################################################
# Get Scope, Example 2
$addresses = $cf->get('address');
$work_line_1 = $addresses->get("work.line_1");

####################################################
# Get Array of Values
$first_child = $cf->get("children.name");
$children = $cf->getArray("children.name");
// value of $first_child would be the string 'Alice'
// value of $children would be the array: ('Alice','Bobby','Chris')
```


userdata.php
-----------------------
A class to access and validate user data types
  - TODO
  - TODO

Examples:  
```
TODO
```

dbconnect.php
-----------------------
A class to handle MySQL/MariaDB compatible database connections. Features include:

  - Automatic failover between multiple servers
  - Safe escaping of data via prepared statements
  - Based off PDO extension
  - Query tracking
  - Query construction emulation (for debugging)
  - Safe escaping of user supplied table and column names
  - Single SQL connection shared among all instances

**Establishing a Connection**
Establishing a connection to SQL server requires an array object to be passed. This array must
contain at least one set of server authentication parameters, including `host`, `username`,
`password`, and `database`. More than one set of parameters can be passed if you have redundant
SQL servers, in which case each server will be tried in-order until a connection is established
or there are no connection left to try.  

Pass the array of connection parameters to the `DBConnect` object to prepare your connection.  
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
```

**Executing Queries**  
To execute a query, you pass a query string and an array of values to the `query()` method.
If there are no values, you can omit the second argument. All queries are processed as
prepared queries.  

Return values are dependent on the type of query that is run. `SELECT` queries return an array
of rows; `INSERT` queries return the unique id of the new row or `null` otherwise; `DELETE`
and `UPDATE` queries return the number of rows affected.  

Additional means of running queries include:  
 - `queryRow` Only the first row of results is returned. By default, throws `E_USER_ERROR` if no rows were found.
 - `queryColumn` Returns an array containing the values of a specific column. By default, the first column is used.
 - `queryLoop` & `queryNext` For gathering results one row at a time. See examples below.
 - `queryPrepare` For manually creating a prepared query, with the ability to re-use it multiple times.

_Example Queries_:  
```php
####################################################
# Simple Query
$query = "SELECT firstname, lastname FROM users WHERE age > ? AND lastname != ?";
$values = array(21, "Smith");

$names = $db->query($query, $values);

foreach ($names as $row) {
    echo "{$row['lastname']}, {$row['firstname']}";
}

####################################################
# Labeled Placeholders
$query = "SELECT firstname, lastname FROM users WHERE hair_color = :hair";
$values = array(":hair"=>"brown");

$names = $db->query($query, $values);

####################################################
# Single Row Query
$query = "SELECT firstname, lastname FROM users WHERE user_id = ?";
$values = array(42);

$row = $db->queryRow($query, $values);

echo "{$row['lastname']}, {$row['firstname']}";

####################################################
# Single Row Query (optional)
$query = "SELECT firstname, lastname FROM users WHERE user_id = ?";
$values = array(42);

$row = $db->queryRow($query, $values, false);

if ($row !== null) {
    echo "{$row['lastname']}, {$row['firstname']}";
}

####################################################
# Argument Expansion Query (requires anonymous placeholder: ?)
$user_id_list = array(2,3,5,7,11)
$query = "SELECT firstname, lastname FROM users WHERE user_id IN (?)";
$values = array($user_id_list);

$names = $db->query($query, $values);

foreach ($names as $row) {
    echo "{$row['lastname']}, {$row['firstname']}";
}

####################################################
# Column Query
$query = "SELECT number FROM phone_directory WHERE city = ?";
$values = array('Kalamazoo');

$phone_numbers = $db->queryColumn($query, $values);
// will contain an array of the values from the 'number' database column

####################################################
# Column Query (selected)
$query = "SELECT number, unlisted, area_code FROM phone_directory WHERE city = ?";
$values = array('Kalamazoo');

$area_codes = $db->queryColumn($query, $values, 2); // 2 = 3rd column, 0-based index
// will contain an array of the values from the 'area_code' database column

####################################################
# Loop Over Large Resultset Query
$query = "SELECT firstname, lastname FROM phone_directory WHERE country = ?";
$values = array('US');

$db->queryLoop($query, $values);
while ($row = $db->queryNext()) {
    echo "{$row['lastname']}, {$row['firstname']}";
}

####################################################
# Re-using Prepared Statements (argument expansion not permitted)
$query = "SELECT * FROM users WHERE lastname = ?";
$prepared = $db->prepare($query);

$lastnames = array('Smith','Cooper','Harris');
for ($lastnames as $lname) {
    $users = $db->query($prepared, array($lname));
    echo "Found ".count($users)." users with a lastname of ${lname}";
}

####################################################
# Insert Query
$query = "INSERT INTO users (firstname, lastname) VALUES(?,?)";
$values = array('John', 'Doe');

$user_id = $db->query($query, $values);
// returns the last insert id on success, or null on failure

####################################################
# Update Query
$query = "UPDATE users SET lastname = ? WHERE user_id = ?";
$values = array('doe', 42);

$update_count = $db->query($query, $values);
echo "Updated {$update_count} rows.";

####################################################
# Delete Query
$query = "DELETE FROM users WHERE user_id = ?";
$values = array(33);

$delete_count = $db->query($query, $values);
echo "Deleted {$delete_count} rows.";

####################################################
# Safe Table and Column Name Escaping
# (safe from SQL injections; you should still use caution to prevent other shenanigans)
$safe_table = $db->escapeIdentifier($table);
$safe_column = $db->escapeIdentifier($column);
$query = "SELECT firstname, lastname FROM {$safe_table} WHERE {$safe_column} = ?";
$values = array(42);

$names = $db->query($query, $values);

foreach ($names as $row) {
    echo "{$row['lastname']}, {$row['firstname']}";
}

```

**Throwing Exceptions**  
The `silentErrors()` method will set the `PDO::ATTR_ERRMODE`. By default, exceptions
will be thrown.  
```php
$db->silentErrors();        // disables exceptions on MySQL errors
$db->silentErrors(true);    // also disables exceptions on MySQL errors
$db->silentErrors(false);   // an exception will be thrown on MySQL errors from this connection
```

**Sanitizing Identifiers**  
Table and column names are not typically something you want to use variables for, but there are rare circumstances
where it might be needed. By calling `escapeIdentifier()`, you can ensure your identifer is sanitized and safe from
SQL injection attacks. This is accomplished by querying the database for all valid table and column names, and only
if the passed identifier exactly matches an existing database identifier pulled from the database is it considered
safe. If the identifer is not safe, an empty string is returned.  

_NOTE_: This only checks that the identifier is valid in the database. You should still **never** trust user supplied
data for use in your queries.  

For performance reasons, this method only queries the database for identifiers the first time it is called. The
method caches the results and any subsequent calls make use of the data previously loaded.  

An optional second boolean parameter can be passed if you want the resulting identifier to be backtick quoted.  
```php
$column = $obj->getColumnMatch();   // NOT user supplied data

$safe_column    = $db->escapeIdentifier($column);
// $safe_column = $db->escapeIdentifier($column, true);  // optionally, escaped with backticks

if ($safe_column == '') {
    echo "Unable to match column!";
    exit(1);
}
$query = "SELECT * FROM books WHERE {$safe_column} = ?";
$values = array( $obj->getColumnValue() );
$results = $db->query($query,$values);
```

**Load Balancing Multiple Servers**  
Not a true load balancer yet, the `loadBalance()` method will shuffle the order in which servers are connected to.
Obviously, this must be called before any queries are run and any connections are established.  
```php
$db->loadBalance();
```

**Forcing a Connection to End**  
You can force a connection to a database to end by calling the `close()` method. This method does
nothing if the connection is already closed.  
```php
$db->close();
```

**Get Database Hostname**  
```php
// prints the domain string used for the 'host' when the current connection was created
$db->getHost();
// if no connection exists, or if the connection was closed, "No Connection" will be returned
$db->close(); 
$db->getHost(); // returns 'No Connection'
```

**Get Database Name**  
Returns the name of the database currently connected. If no database is connected, will attempt to
connect to one and return it. If it cannot connect to any databases, it will return an empty string.  
```php
$db->getDatabaseName();
```

**Debugging a Query**  
To dump the PDO debugParams and get a copy of the query string for the last query run, you can call
the `getLast()` method. If you ran series of querys as a transaction, then it will include all queries
that were part of that transaction.  
```php
$db->getLast()
```

To emulate a query before it's run and see what it will likely be combined as, you can call either
the `queryReturn()` or `queryDump()` methods. The first of which emulates joining the query and values
together and returns it as a string; the latter which does the same, but dumps the query to stdout.  
```php
$debug = $db->queryReturn($query,$values);
$db->queryDump($query,$values);
```

**Grabbing a List of Enum Values**  
If a table column is an enum, you can get and array containing all possible
enum values by calling the `enumValues()` method, and passing the table
and column in question. The returned array will be ordered in the same order
as the enum values are defined in the table.  
```php
$enums = $db->enumValues('mytable', 'mycolumn');
```

**Using Transactions**  
Transactions can be used via the methods `startTransaction()`, `commitTransaction()`, and `rollbackTransaction()`
assuming the database engine support it.  
```php
$db->startTransaction();

$update_count = $db->query($query, $values);
if ($update_count > 1) {
    $db->rollbackTransaction();
}
else {
    $db->commitTransaction();
}
```

By default, MySQL sets the transaction isolation to `REPEATABLE READ`. You can change this to `READ COMMITTED` by passing
a boolean `true` when starting the transaction.  
```php
$db->startTransaction(true);
```

**Get Count of Query Calls Made**  
To get a listing of the total number of queries for this specific instance of
a DBConnect object, you can use the `getQueryCount()` method.  
```php
$count = $db->getQueryCount();
```

