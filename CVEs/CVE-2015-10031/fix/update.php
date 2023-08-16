<?php
	
	if ($_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest'){
		$username = "alpertmd";
		$password = "yofer`";
		$hostname = "repos.insttech.washington.edu";

		$dbhandle = mysqli_connect($hostname, $username, $password) or die("Unable to connect to MySQL");
	
		$selected = mysqli_select_db($dbhandle,$username) or die("Could not select high score db");
	
		$result = mysqli_query($dbhandle, "SELECT MAX(idHighscores) FROM Highscores");
	
		$id = mysqli_fetch_row($result)[0] + 1;
		$name = $_POST["name"];
		$score = $_POST["score"];

		$query =  mysqli_prepare($dbhandle, "INSERT INTO Highscores Values (?, ?, ?)");
	
		mysqli_stmt_bind_param($query, 'isi', $id, $name, $score);
		mysqli_stmt_execute($query);
		mysqli_stmt_close($query);
		
		$query = "SELECT idHighscores FROM Highscores WHERE score = (SELECT Min(score) FROM Highscores)";
		$result = mysqli_query($dbhandle, $query);
		$minID = mysqli_fetch_row($result)[0];

		$query = "DELETE FROM Highscores WHERE idHighscores = $minID";
		$result = mysqli_query($dbhandle, $query);
	
		mysqli_close($dbhandle);
	}
?>