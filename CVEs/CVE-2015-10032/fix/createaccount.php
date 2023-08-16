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
	echo "<p>Connected successfully</p>";
	
	// Placeholders for variables from form
	$username = $password = $first_name = $last_name = $company = $phone = "";
	
	// Prevent XSS hacks / exploits by stripping the data
	if ($_SERVER["REQUEST_METHOD"] == "POST") {
		$username = test_input($_POST["username"]);
		$password = test_input($_POST["password"]);
		$first_name = test_input($_POST["first_name"]);
		$last_name = test_input($_POST["last_name"]);
		$company = test_input($_POST["company"]);
		$phone = test_input($_POST["phone"]);
	}
	
	// Removes unwanted and potentially malicious characters
	// from the form data
	function test_input($data) {
		$data = trim($data);
		$data = stripslashes($data);
		$data = htmlspecialchars($data);
		return $data;
	}
	
	// Adds a new user account with form data into the physician table of the database
	// -- To do: form checking (e.g., username already exists, security, etc.)
	$sql = "INSERT INTO physician (group_id, username, password, first_name, last_name, company, phone) VALUES (1, '".$username."', '".$password."', '".$first_name."', '".$last_name."', '".$company."', '".$phone."')";
	
	// Probably keep even after debug
	if ($conn->query($sql) === TRUE) {
		echo "<p>Account created successfully.</p>";
	} else {
		echo "Error: " . $sql . "<br />" . $conn->error;
	}
	
	// Peace out
	$conn->close();
?>