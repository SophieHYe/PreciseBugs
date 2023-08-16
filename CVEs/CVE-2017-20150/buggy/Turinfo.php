<html>
<?php
echo "<h1>" . $_GET['INFO'] . "</h1>";
$info = $_GET['INFO'];
?>

<!-- Denne del står for information/descrpiton, venstre side af turnerings-informationssiden -->
<div id="indholdLeft">
    <?php
    $result = mysqli_query($db, "SELECT turtabel.Tid, turtabel.Dag , turtabel.Description "
            . "FROM turtabel "
            . "WHERE turtabel.TurneringsNavn = '$info' ");
    while ($row = mysqli_fetch_array($result)) {
	
        echo ("<div id='turDesc'>
			  <table class='turneringTable' style='width:80%; '>
				<tr>
						<td>" . $_GET['INFO'] . " </br> " . $row['Dag'] . " kl." . $row['Tid'] . "</td>
				</tr>
				<tr>
						<td> ");

		echo nl2br($row['Description']);
		echo (" </td>
				</tr>
		</table>
		</div>");
    }
    ?>
</div>

<!-- Her vises den table der indeholder alle navne på deltagere til den valgte turnering -->
<div id="indholdRight">
    <br>
    <table class="turneringTable" style="width:100%; margin-top: -20px;">
        <tr>
            <td>Navn</td>
            <td>Gamertag</td>
            <td>BordID</td>
        </tr>
        <?php
        $result = mysqli_query($db, "SELECT deltager.Navn, deltager.Gamertag, deltager.BordID "
                . "FROM turtabel , deltager "
                . "WHERE turtabel.TurneringsNavn = '$info' "
                . "AND turtabel.TurneringsID = deltager.TurneringsID");
        while ($row = mysqli_fetch_array($result)) {
            echo "<tr><td>" . $row['Navn'] . "</td>"
            . "<td> '" . $row['Gamertag'] . "' </td>"
            . "<td>" . $row['BordID'] . "</td></tr>";
        }
        ?>   
    </table>
</div>

<div id="back" >
    <a href=turnering.php> <--- Back </a> 
</div>
</html>