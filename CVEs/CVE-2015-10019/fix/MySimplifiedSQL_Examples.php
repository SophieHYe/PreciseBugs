<?php
/*
MySimplifiedSQL is an easy-to-use PHP class to interact with an SQL database. Currently this makes use of the MySQLi extension and therefore only supports MySQL/MariaDB.
All field values are properly sanitized , this means that you don't have to worry about security (Unless your perform direct queries, example: $database->query("SELECT * FROM users WHERE FirstName='John'"))
This .php file shows you some examples on how you can use MySimplifiedSQL.
GitHub: https://github.com/foxoverflow/MySimplifiedSQL
*/
require_once 'MySimplifiedSQL.php';
//$database = New MySimplifiedSQL($db_user, $db_password, $db_host, $db_name); // Connect to MySQL and build class object
$database = New MySimplifiedSQL("example_user", "example_password", "example_host", "example_name", 1); // Test mode activated, set the last parameter to 0 or omit it to perform queries instead of viewing them
//$database->testMode(1); // (Optional) Set test mode to 1 if you want to see querys instead of executing them
$database->insert("users", array("FirstName" => "John")); // "INSERT INTO users (FirstName) VALUES ('John')"
$database->insert("users", array("FirstName" => "John", "LastName" => "Smith")); // "INSERT INTO users (FirstName, LastName) VALUES ('John', 'Smith')"
$database->select("users", array("FirstName" => "John")); // "SELECT * FROM users WHERE FirstName='John'"
$database->select("users", array("FirstName" => "John", "LastName" => "Smith")); // "SELECT * FROM users WHERE FirstName='John' AND LastName='Smith'"
$database->select("users", array("FirstName" => "John", "LastName" => "Smith"), "OR"); // "SELECT * FROM users WHERE FirstName='John' OR LastName='Smith'"
$database->select("users", array("FirstName" => "John", "LastName" => "Smith", "MiddleName" => "Bill"), "OR"); // "SELECT * FROM users WHERE FirstName='John' OR LastName='Smith' OR MiddleName='Bill'"
/*
The following $database->select is correctly written, however, there is a bug with the current MySimplifiedSQL version when we execute it:
$database->select("users", array("FirstName" => "John", "LastName" => "Smith", "LastName" => "Bill"), "OR"); 
The query that should have been producted is: "SELECT * FROM users WHERE FirstName='John' OR LastName='Smith' OR LastName='Bill'"
However, in the current version, the produced query is: "SELECT * FROM users WHERE FirstName='John' OR LastName='Smith'"
There is currently no ETA for a fix for this since you can easily solve it by performing a direct query, see at the end of this file examples.
*/
foreach($database->rowArray as $row) // Only to be used after a SELECT query ($database->select). If test mode is enabled, the array will be empty.
{
	echo "First name:" . htmlentities($row['FirstName']) . "<br>"; // htmlentities is used here to combat XSS attack attempts
	echo "Last name:" . htmlentities($row['LastName']) . "<br>";
}
$database->delete("users", array("FirstName" => "John", "LastName" => "Smith")); // "DELETE FROM users WHERE FirstName='John' AND LastName='Smith'" - Works like $database->select
// If you want more control over your queries, you can manually build an SQL Query, also known as direct query.
$userInputDemo = "John' Smith"; // The ' could cause an SQL error, allowing to know SQL Injection is possible.
$userInputDemo = $database->sanitize($userInputDemo); // Field sanitization, escaping "malicious" characters like '
$database->query("SELECT * FROM users WHERE Name='" . $userInputDemo . "'"); // "SELECT * WHERE FirstName='John\' Smith'" - The ' was successfully escaped
// You can also use MySQLi functions directly if you want, the connection handle/link is $database->mysqli
// mysqli_query($database->mysqli, $query);  
?> 