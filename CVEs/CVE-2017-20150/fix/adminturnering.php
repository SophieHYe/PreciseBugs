<?php
include('top2.php');
include('isLoggedIn.php');
?>

<!-- Opretter en turnering når der klikkes "Opret turnering",
     Sletter en given turnering hvis der klikkes "slet turnering",
	 og sletter alle turneringer hvis der klikkes "slet alle turneringer" -->
<?php
if (isset($_GET['Opret'])) {
    $Turnavn = mysqli_real_escape_string(stripslashes($_GET['Turnavn']));
    $Dag = mysqli_real_escape_string(stripslashes($_GET['Dag']));
    if ($Dag == '1') {
        $DagB = "Fredag";
    } else if ($Dag == '2') {
        $DagB = "Lørdag";
    } else if ($Dag == '3') {
        $DagB = "Søndag";
    }
    $Tid = mysqli_real_escape_string(stripslashes($_GET['Tid']));
    $Desc = mysqli_real_escape_string(stripslashes($_GET['Desc']));
    $sql = "INSERT INTO `turtabel` (`TurneringsID`, `TurneringsNavn`, `Tid`, `Dag`, `Description`) "
            . " VALUES (NULL, '$Turnavn', '$Tid', '$DagB', '$Desc')";
} else if (isset($_GET['slet'])) {
    $ID = $_GET['Turid'];
    $deletedel = "DELETE FROM `deltager` WHERE `deltager`.`TurneringsID` = $ID";
    $deletetur = "DELETE FROM `turtabel` WHERE `turtabel`.`TurneringsID` = $ID";
} else if (isset($_GET['reset'])) {
    $truncatedel = "TRUNCATE TABLE `deltager` ";
    $truncatetur = "TRUNCATE TABLE `turtabel` ";
}
?>

<!-- Denne del gennemfører slet-kommandoen fra knappen -->
<?php
if (isset($_REQUEST['slet'])) {
    if ($ID != 0) {
        mysqli_query($db, $deletedel);
        mysqli_query($db, $deletetur);
    }
} else if (isset($_REQUEST['reset'])) {
    mysqli_query($db, $truncatedel);
    mysqli_query($db, $truncatetur);
}
?>



<div id="page">
    <div id="indhold">
        <div id="indholdText2">
            <div id="indholdDiv2">


<!-- Her kan der oprettes turneringer som administrator-->
                <h2 text align="Center">Opret Turneringer</h2>
                <form action="<?php echo $_SERVER['PHP_SELF']; ?>">
                    <input type="hidden" name="Opret" value="Tur">
                    <table border=7; bordercolor="#C80000" tabel align="center" >
                        <tr><td>Turneringens Navn&nbsp;</td><td><input type="text" name="Turnavn"></td></tr>
                        <tr><td>Dag</td><td>
                                <select name="Dag">
                                    <option value="0">Vælg Dag</option>
                                    <option value="1">Fredag</option>
                                    <option value="2">Lørdag</option>
                                    <option value="3">Søndag</option>
                                </select></td>
                        </tr>
                        <tr><td>Tid</td><td>
                                <select name="Tid">
                                    <option value="0">Vælg Tid</option>
                                    <?php
                                    for ($hours = 0; $hours < 24; $hours++) // the interval for hours is '1'
                                        for ($mins = 0; $mins < 60; $mins+=30) // the interval for mins is '30'
                                            echo '<option>' . str_pad($hours, 2, '0', STR_PAD_LEFT) . ':'
                                            . str_pad($mins, 2, '0', STR_PAD_LEFT) . '</option>';
                                    ?>
                                </select></td></tr>
                        <tr><td>Description</td>
                            <td><textarea name="Desc" rows="12" cols="25"></textarea>

                            </td></tr>
                        <tr><td colspan=2 align=center>
                                <input type="Submit" value="Submit">
                                <input type="Reset" value="Reset"></td><td>
                                <?php
                                if (isset($_REQUEST['Opret'])) {
                                    if ($Turnavn > "%") {
                                        if ($Dag > 0) {
                                            if ($Tid != 0) {
                                                mysqli_query($db, $sql);
                                                echo "<p text align=\"center\"> Succes <p>";
                                            } else {
                                                echo "<p text align=\"center\"> Error ved tid </p>";
                                            }
                                        } else {
                                            echo "<p text align=\"center\"> Error ved Dag </p>";
                                        }
                                    } else {
                                        echo "<p text align=\"center\"> Error ved navn </p>";
                                        $turnavn = mysqli_query($db, "SELECT TurneringsID , TurneringsNavn "
                                                . "FROM turtabel "
                                                . "ORDER BY turtabel.TurneringsID desc");
                                        while ($row = mysqli_fetch_array($turnavn)) {
                                            echo $row[0] . " ";
                                        }
                                    }
                                }
                                ?>
                            </td></tr>
                    </table>
                </form>

		<!-- Her kan der slettes enkelte turneringer-->
                <h2 text align="Center">Slet enkelte Turneringer</h2>
                <form>
                    <input type="hidden" name="slet" value="DELETE">
                    <table border=3; bordercolor="#C80000" tabel align="center" >
                        <tr><td>Turnering&nbsp;</td><td>
                                <select name="Turid">
                                    <option value="0">Vælg Turnering</option>
                                    <?php
                                    $turnavn = mysqli_query($db, "SELECT TurneringsID , TurneringsNavn "
                                            . "FROM turtabel "
                                            . "ORDER BY turtabel.TurneringsID desc");
                                    while ($row = mysqli_fetch_array($turnavn)) {
                                        echo "<option value=\"$row[0]\"> $row[1]</option>";
                                    }
                                    ?>
                                </select>
                        <tr><td colspan=2 align=center>
                                <input type="submit" value="Slet Turneringer"></td></tr>
                    </table>
                </form>

				<!-- Her slettes ALLE turneringer-->
                <h2 text align="Center">Slet ALLE Turneringer:</h2>
                <form form align="center">
                    <input type="hidden" name="reset" value="Tøm">
                    <input type="submit" value="Klik her">
                </form>
            </div>
        </div>
    </div>
</div>

<?php
include('bottom.html');
?>
