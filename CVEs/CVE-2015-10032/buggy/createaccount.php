<?php
	// Some DB info - users use the HMTest user
	// and tests are done on the testdb database
	$servername = "localhost";
	$username = "HMTest";
	$password = "comp490";
	$dbname = "testdb";

	// Create connection
	$conn = new mysqli($servername, $username, $password, $dbname);

	// Check connection
	if ($conn->connect_error) {
		die("Connection failed: " . $conn->connect_error);
	}
	
	// Remove after debug
	echo "Connected successfully";
	
	// Adds a new user account with form data into the physician table of the database
	// -- To do: form checking (e.g., username already exists, etc.)
	$sql = "INSERT INTO physician (group_id, username, password, first_name, last_name, company, phone) VALUES (1, '".$_POST['username']."', '".$_POST['password']."', '".$_POST['firstN']."', '".$_POST['lastN']."', '".$_POST['company']."', '".$_POST['phone']."')";
	
	// Probably keep even after debug
	if ($conn->query($sql) === TRUE) {
		echo "Account created successfully.";
	} else {
		echo "Error: " . $sql . "<br />" . $conn->error;
	}
	
	$conn->close();
?>