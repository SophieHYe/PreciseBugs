<?php
require_once ('session.php');
requireUserClass('a');
?>

<html>
	<body>
		<a href='index.php'>Home</a>
		<h1> Users </h1>
		<p>
			<a href="register.php">Add Account</a>
		</p>
		<div>
			<form id="searchform" required="1" name="search_users" method="get" action="<?php $PHP_SELF ?>" >
				<input type="text" name="q" />
				<input type="submit" value="Search"  />
			</form>
		</div>
		<div>
			<?php
			if (isset($_GET['q']) && $_GET['q'] != '') {
				$search = $_GET['q'];
				$max = 5;
				$keys = preg_split("/\s/", $search, $max);
				
				$count = count($keys);
				
				// Build up a query
				$query = "SELECT persons.person_id, user_name, class, date_registered, first_name, last_name, address, email, phone ".
				"FROM users, persons WHERE users.person_id = persons.person_id AND (";
				
				// These are the terms we're searching through
				$terms = array(
					"user_name",
					"first_name",
					"last_name",
					"address",
					"email",
					"phone"
				);
				
				$operator = "";
				foreach ($terms as $term) {
					$query .= $operator." UPPER($term) IN (";
					$comma = "";
					// Build binding values
					for ($i = 0; $i < min($count, $max); $i++) {
						$query .= "$comma :key$i";
						$comma = ", ";
					}
					$query .= ") ";
					$operator = "OR";
				}
				
				$query .= ")";
				
				require('_database.php');
				
				$statement = oci_parse($connection, $query);
				
				// Bind keys
				for ($i = 0; $i < min($count, $max); $i++) {
					oci_bind_by_name($statement, ":key$i", strtoupper($keys[$i]));
				}
				oci_execute($statement);
				
				$num = 0;


			?>
			<table border="1">
				<?php
				while (oci_fetch($statement)) {
					if ($num == 0) {
						echo "<tr>";
						for ($field = 1; $field <= oci_num_fields($statement); $field++) {
							echo "<th>" . oci_field_name($statement, $field) . "</th>";
						}
						echo "</tr>\n";
					}
					echo "<tr>";
					$num++;
					// Link jumps to edit_user div with user id
					echo "<td><a href='edituser.php?user=".oci_result($statement, "USER_NAME")."'>";
					echo oci_result($statement, 1)."</a></td>";
					for ($field = 2; $field <= oci_num_fields($statement); $field++) {
						echo "<td>", oci_result($statement, $field), "</td>";
					}

					echo "</tr>\n";
				}
				?>
			</table>
			<?php oci_free_statement($statement);
			oci_close($connection);

			if ($num == 1) {
				echo "<p>Found 1 user</p>";
			} else {
				echo "<p>Found $num users</p>";
			}
			} else {
			?>
			<p>
				Use the search feature above to find users.
			</p>
			<?php } ?>
			
		</div>
	</body>
</html>
