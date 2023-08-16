<?php
session_start();
require ('_sessioninfo.php');

function redirect() {
	header("Location: search.php");
	exit();
}

if (!isExpired()) {
	// Session is valid
	redirect();
}

// Check for submission post
// http://www.formget.com/login-form-in-php/
if (isset($_POST['login'])) {
	// Missing fields
	if (empty($_POST['user']) || empty($_POST['pass'])) {
		$message = "Invalid username/password";
		$msg_class = 'error';
	} else {
		// Require a connection to the database.
		require ('_database.php');
		$user = $_POST['user'];
		$pass = $_POST['pass'];
		// <-- Bad.

		$query = "SELECT user_name, class, person_id FROM users WHERE user_name = '$user' AND password = '$pass'";
		
		$statement = oci_parse($connection, $query);
		$results = oci_execute($statement);

		// Did we get a valid result?
		if ($results) {
			// Are credentials valid?
			if (oci_fetch($statement)) {
				// Yes.
				// Store user details in the session
				$_SESSION['user_name'] = oci_result($statement, 'USER_NAME');
				$_SESSION['class'] = oci_result($statement, 'CLASS');
				$_SESSION['person_id'] = oci_result($statement, 'PERSON_ID');
				
				// Create session and redirect
				$_SESSION['_user_session'] = true;
				$_SESSION['us_created_time'] = time();
				$_SESSION['us_last_activity'] = time();

				// Clean up database objects
				oci_free_statement($statement);
				oci_close($connection);

				// Redirect to home page
				redirect();
			} else {
				// No.
				// Clean up database objects
				oci_free_statement($statement);
				oci_close($connection);
				
				// Inform the user
				$message = 'Incorrect username/password';
				$msg_class = 'error';
			}

		} else {
			$message = 'Database error. Please try again later.';
			$msg_class = 'error';
			
			// Clean up database objects
			oci_free_statement($statement);
			oci_close($connection);
		}
	}
} else {
	// Check if we received info about the error
	if (isset($_SESSION['err_message'])) {
		$message = $_SESSION['err_message'];
		$msg_class = 'error';
		unset($_SESSION['err_message']);
	} else {
		$message = 'Login is required.';
		$msg_class = 'normal';
	}
}
?>