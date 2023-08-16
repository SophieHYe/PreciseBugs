<?php
include('top.php');
?>	
     
        <div id="page">
			
				<div id="indhold">
					<div id="indholdText2">
						<div id="indholdDiv2">

						<h1> 
							<?php
								$result = mysqli_fetch_assoc(mysqli_query($db,"SELECT * FROM forside1stor"));
								echo $result["desc"];								
							?>
						</h1>
						<p>	<?php
								$result = mysqli_fetch_assoc(mysqli_query($db,"SELECT * FROM forside1"));
								echo $result["desc"];
							?>
						</p>
						
							<div id="tab1">
								<h2>
									<?php
										$result = mysqli_fetch_assoc(mysqli_query($db,"SELECT * FROM forside2stor"));
										echo $result["desc"];
									?>
								</h2>
								
								<p> 
									<?php
										$result = mysqli_fetch_assoc(mysqli_query($db,"SELECT * FROM forside2"));
										echo $result["desc"];	
									?>	
								</p>
							</div>
							
							<div id="tab2">
								<h2>
									<?php
										$result = mysqli_fetch_assoc(mysqli_query($db,"SELECT * FROM forside3stor"));
										echo $result["desc"];	
									?>	
								</h2>
								<p>
									<?php
										$result = mysqli_fetch_assoc(mysqli_query($db,"SELECT * FROM forside3"));
										echo $result["desc"];	
									?>	
								</p>
							</div>
							
							<div id="tab3">
								<h2>
									<?php
										$result = mysqli_fetch_assoc(mysqli_query($db,"SELECT * FROM forside4stor"));
										echo $result["desc"];	
									?>	
								</h2>
								
								<p>
									<?php
										$result = mysqli_fetch_assoc(mysqli_query($db,"SELECT * FROM forside4"));
										echo $result["desc"];	
									?>	
								</p>
							</div>
						
<?php

/*

// Drops the Table "Persons"
$sql1="DROP TABLE Persons;";
mysqli_query($db,$sql1);



$sql="CREATE TABLE Persons(FirstName CHAR(30),LastName CHAR(30),Age INT)";

// Execute query
if (mysqli_query($db,$sql)) {
  echo "";
}
else {
  echo "Error creating table: " . mysqli_error($con);
}



// adds people to the tables

mysqli_query($db,"INSERT INTO Persons (FirstName, LastName, Age)
VALUES ('Peter', 'Griffin',35)");

mysqli_query($db,"INSERT INTO Persons (FirstName, LastName, Age) 
VALUES ('Glenn', 'Quagmire',33)");

mysqli_query($db,"INSERT INTO Persons (FirstName, LastName, Age) 
VALUES ('Darth', 'Vader',62)");

mysqli_query($db,"INSERT INTO Persons (FirstName, LastName, Age) 
VALUES ('Luke', 'Skywalker',28)");

mysqli_query($db,"INSERT INTO Persons (FirstName, LastName, Age) 
VALUES ('Han', 'Solo',32)");


// Kigger i databasen og hiver ud via SELECT
$result = mysqli_query($db,"SELECT * FROM Persons");

echo " <div class='phpTable'> <table border='1'>
<tr>
<th>Firstname</th>
<th>Lastname</th>
<th>Age</th>
</tr>";

while($row = mysqli_fetch_array($result)) {
  echo "<tr>";
  echo "<td>" . $row['FirstName'] . "</td>";
  echo "<td>" . $row['LastName'] . "</td>";
  echo "<td>" . $row['Age'] . "</td>";
  echo "</tr>";
}

echo "</table> </div>";

$result = mysqli_query($db,"SELECT * FROM Persons");


// Lukker Databasen
mysqli_close($db);

*/
?>





						</div>
					</div>
					
				</div>
			
        </div>

		
<?php
include('bottom.html');
?>
