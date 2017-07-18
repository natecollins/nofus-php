web-utilities
=======================

Web-utilities is a set of simple-to-use tools designed to deal with some of the basic hurdles
when web programming from scratch with PHP. Each class can be used independently of the rest.  

* [configfile.php](#configfilephp) - Easy parser for config files
* [logger.php](#loggerphp) - Simple logger class with built-in file logging implementation
* [dbconnect.php](#dbconnectphp) - Quick, safe interface for making MySQL/MariaDB queries
* [userdata.php](#userdataphp) - Simple validator for user data


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
  - Can enumerate available scopes
  - Can preload default values

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
# Create config file object and attempt to load it
$cf = new ConfigFile('app.conf');

if (!$cf->load()) {
    echo "Could not load file!" . PHP_EOL;
    print_r( $cf->errors() ); // an array of reasons for failure to load
    exit(1);
}

####################################################
# Optionally preload default values (will NOT override already loaded values)
$cf->preload(
    array(
        "email"=>"root@localhost",
        "debug_mode"=>false,
        "address.home.city"=>"Kalamazoo",
        "address.home.state"=>"Michigan"
    )
);

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

####################################################
# Enumerate available scopes
$scopes1 = $cf->emumerateScope("address.work");
$scopes2 = $cf->emumerateScope();   // Default enumerates top level scope
// value of $scopes1 would be the array: ('line_1','line_2','state','city')
// value of $scipes2 would be the array: ('email','name','debug_mode','nickname','date','address','children')
```

**Loading only loads the first time**  
Calling the `load()` method only parses and loads the config file the first time for any ConfigFile
object. Subsequent calls check if the file was successfully loaded the first time, and then doesn't
bother to re-parse.  

If you want to force a config file to re-load a file, you must first `reset()` the ConfigFile object
and then `load()` it again. Note that this will also clear out any `preload()` variables, so you will
have to `preload()` them again after calling `reset()`.  
```php
$cf->reset();
$cf->load();
```


logger.php
-----------------------
A class to create logs, with a built-in simple file-logging implementation
  - Very simple to setup and use
  - Can register custom logger implementations to replace built-in file-logger
  - Can specify log levels

Examples:  
```
# Initialize logger with built-in file logger; default level logs all levels
Logger::initialize('/path/to/file.log')

# Initialize logger with customize logger levels
Logger::initialize('/path/to/file.log', Logger::LOG_ERROR | Logger::LOG_CRITICAL);

# Disable logger
Logger::disable();

# Register custom logger instance which must implement `LoggingInterface`.
Logger::register( new CustomLogger() );

# Make log entries
Logger::debug("Debug!");
Logger::notice("Notice!");
Logger::warning("Warning!");
Logger::error("Error!");
Logger::critical("Critical!");
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
 - `queryRow` Only the first row of results is returned. If no rows are found, returns null.
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

**Showing Errors and Debug Information**  
By default, an uninformative message is thrown on errors and exceptions. To enable detailed errors and output of
query information during development, make sure you enable debugging information:  
```php
$db->enableDebugInfo();
```

**Throwing Exceptions**  
The `silentErrors()` method will set the `PDO::ATTR_ERRMODE`. By default, PDO exceptions
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

An optional second boolean parameter can be passed if you want to specify that the resulting identifier should not be
backtick quoted.  
```php
$column = $obj->getColumnMatch();   // NOT user supplied data

// $safe_column = $db->escapeIdentifier($column);        // by default, result is escaped with backticks
$safe_column    = $db->escapeIdentifier($column, false); // or you can have it not escape with backticks

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
// if no connection exists or if the connection was closed, empty string will be returned
$db->close(); 
$db->getHost(); // returns ''
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


userdata.php
-----------------------
A class to access and validate user data types from GET, POST, COOKIE, and FILES.
  - Object based interface
  - Support for retrieving arrays of values, including file data
  - Functions auto-convert to basic data types
  - Simple filter options for range, length, regexp, or pre-set values
  - Default value option for all value retrieval functions

**Creating UserData Objects**  
When creating a new UserData object, you must specify the name of the data field to retrieve, and
optionally name where to look for the data at. If not specified, UserData will look for the
value field in-order from these locations: GET, POST, COOKIE, and FILES.  
```
# Create UserData objects 
$ud_userid = new UserData("user_id");
$ud_message = new UserData("message", "POST");

# Alternate way of creating UserData objects
$ud_attachments = UserData::create("attach", "FILES");
$ud_session = UserData::create("sess_key", "COOKIE");
```

**Getting Simple Values**  
Getting simple values is easy, and returns the appropriate value type,
or returns null if variable name was not passed.  
```
# Get string values
$firstname = $ud_firstname->getStr();
$lastname = $ud_lastname->getString();

# Get integer values (truncates decimals)
$age = $ud_age->getInt();
$zip = $ud_zipcode->getInteger();

# Get float values
$temperature = $ud_temp->getFloat();
$distance = $ud_dist->getDouble();

# Get boolean values (true values are either: 1, true)
$allow_email = $ud_email->getBool();
$allow_text = $ud_text->getBoolean();
```

**Setting Default Values**  
If no value was found, use the default value passed instead. With
out a default value specified, the default is null.  
```
$msg_type = $ud_type->getStr("public");
$guest = $ud_guest->getBoolean("1");
```

**Filter by Allowed Values**  
If you know the value retrived must be from a set of values, you can
set a filter to reject all but those values. Any non-allowed values
will return the default value instead.  
```
$ud_acct->filterAllowed( ['guest','normal','admin'] );
$acct_type = $ud_acct->getStr("guest");
```

**Filter by Range**  
Only applicable when getting integer or float values. Values outside
the range will return the default value, unless set to limit the value.  
```
$ud_age->filterRange(0,120);
$age = $ud_age->getInteger();

# Only limit the minimum 
$ud_minimum->filterRange(15.0);

# Only limit the maximum range
$ud_maximum->filterRange(null, 99.5);

# Limit the value to be within the filter range
$ud_limit->filterRange(0, 100, true);
```

**Filter by Length**  
Only applicable for string values. Values not within length limits
will return the default value, unless set to truncate.  
```
# Filter minimum and maximum length
$ud_username->filterLength(2, 10);

# Filter minimum length only
$ud_username->filterLength(2);

# Filter minimum length and truncate string past maximum length
$ud_username->filterLength(2, 10, true);
```

**Filter by Regular Expression**  
Only applicable for string values. Values not matching pattern
will return the default value.  
```
$ud_date->filterRegExp('/^\d{4}-\d\d-\d\d$/');
```

**Get Errors**  
If your value is out of bounds of a filter, it will genereate an error
message. All error messages are stored in an array. Using `getErrors()`
you can retrieve and view those filter errors.  
``` 
$errors = $ud_data->getErrors();
foreach ($errors as $err) {
    echo "$err";
}
```

**Get File Values**  
Get information about an uploaded file. Returns array containing:  
 - `name` The original name of the uploaded file
 - `type` The mime type of the file (can be falsified by client)
 - `size` The size of the uploaded file
 - `tmp_name` The file location and name as it exists on the server
 - `error` An error code if there was a problem with the upload (0 means no error)
```
$file_info = $ud_attachment->getFile();
```

**Get Array Values**  
When using PHP form variable arrays, you can use the array version of
the UserData functions. Any filters you've applied will apply to each
value of the array.  
```
$string_array = $ud_list->getStrArray();
# Can still provide default value to return
$string_array = $ud_list->getStringArray( array() );

$int_array = $ud_numbers->getIntArray();
$int_array = $ud_numbers->getIntegerArray();

$float_array = $ud_numbers->getFloatArray();
$float_array = $ud_numbers->getDoubleArray();

$bool_array = $ud_switches->getBoolArray();
$bool_array = $ud_switches->getBooleanArray();

$file_array = $ud_files->getFileArray();
```


