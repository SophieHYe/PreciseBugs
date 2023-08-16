<?php

$DEFAULT_REDIRECT = "https://someothersite.org";

$mysqli = new mysqli("127.0.0.1", "db_username", "db_password", "db_name");

if ($mysqli->connect_errno) {
	printf("Connect failed: %s\n", $mysqli->connect_error);
	exit();
}

if (isset($_SERVER["QUERY_STRING"]) && ($_SERVER["QUERY_STRING"] != '')) {

  $query = sprintf("SELECT id, redirect_to FROM short_links WHERE short_link = '%s' 
                    ORDER BY id DESC LIMIT 1", $mysqli->real_escape_string($_SERVER["QUERY_STRING"]));
  $result = $mysqli->query($query);

  $fail = 0;

  if (!$result)  {
    // query failed
    error_log("Sent an error 404 having executed query $query and got an error of " . $mysqli->error());
    $fail = 1;
  }

  if ($result->num_rows < 1) {
    // short link not found in db
    error_log("Sent an error 404 having executed query $query and got no rows back");
    $fail = 1;
  }

  $link = $result->fetch_array(MYSQLI_ASSOC);
  $query = sprintf("UPDATE short_links SET count = count + 1, last_request = NOW() WHERE id = ''%s''", $link["id"]);

  $result = $mysqli->query($query);

  if (!$result)  {
    // query failed updating db (this really shouldn't occur ever
    error_log("Sent an error 404 having executed query $query and got an error of " . $mysqli->error());
    $fail = 1;
  }

  if ($fail == 1) {
    header("HTTP/1.0 404 Not Found");
    print "<html><body><p>The requested short link was not found.</p></body></html>";
  } else {
    header("HTTP/1.1 301 Moved Permanently");
    header("Location: " . $link["redirect_to"]);
  }
    
} else {
  // redirect to some default site
  // I don't have a home page, but here you could just output some HTML
  header("HTTP/1.1 301 Moved Permanently");
  header("Location: " . $DEFAULT_REDIRECT);
}

?>
