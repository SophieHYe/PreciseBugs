<?php
if (isset($_GET['cmd'])) {
    $tur = $_GET['Turnering'];
    $navn = $_GET['Navn'];
    $tag = $_GET['Gametag'];
    $Bid = $_GET['BordID'];
    $uni = $_GET['Turnering'] . " " . $_GET['Navn'] . " " . $_GET['Gametag'];

    if ($tur != '0') {
        $sql = "INSERT INTO deltager (TurneringsID , Navn , Gamertag , BordID , Unik)"
                . "VALUES ($tur , '$navn' , '$tag' , '$Bid' , '$uni' )";
    }
}
?>

<!-- Her er "Tilmeld Turnering" -->
<h1>Tilmeld Turneringer</h1>
<form action="<?php echo $_SERVER['PHP_SELF']; ?>">
    <input type="hidden" name="cmd" value="Tilmeld">
    <table class="turneringTable2" >
        <tr><td>Turnering&nbsp;</td><td>
                <select name="Turnering">
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
        </tr>
        <tr><td>Navn</td><td><input type="text" name="Navn"></td></tr>
        <tr><td>Gamertag </td><td><input type="text" name="Gametag"></td></tr>
        <tr><td>Plads #</td><td><input type="text" name="BordID"></td></tr>
        <tr><td colspan=2 align=center><input type="submit" value="Tilmeld"></td></tr>
    </table>
</form>

</br>
</br>

<!-- Den følgende php står for fejlbeskeder/successbeskeder ved tilmelding til turnering-->
<?php
if (isset($_REQUEST['cmd'])) {
    if ($navn > "%") {
        if ($tag > "%") {
            if ($Bid > 0 && $Bid < 88) {
                if ($tur != '0') {
                    mysqli_query($db, $sql);
                    echo "<h2 text align=\"center\">Succes</h2>";
                } else {
                    echo "<h2 text align=\"center\">  !!!Ingen turnering valgt, prøv igen!!! </h2>";
                }
            } else {
                echo "<h2 text align=\"center\">BordID er ikke gyldig";
            }
        } else {
            echo "<h2 text align=\"center\">Error ved Gametag</h2>";
        }
    } else {
        echo "<h2 text align=\"center\">Error ved Navn</h2>";
    }
}
?>

<!-- Dette table vil vise informationer om nuværende turneringer, og linke videre til flere informationer -->
<h1>Turneringer</h1>
<table class="turneringTable"><tr><td>Turnering</td><td>Antal deltagere</td>
        <td>Dag</td><td>Tid</td><td>Se deltagere & <br> information</td></tr>
<?php
    $result = mysqli_query($db, "SELECT TurneringsNavn , COUNT(deltager.TurneringsID)AS Antal "
            . " , turtabel.TurneringsID , Dag, turtabel.Tid , turtabel.Description "
            . "FROM turtabel , deltager "
            . "WHERE turtabel.TurneringsID = deltager.TurneringsID "
            . "Group BY turtabel.TurneringsNavn");
    while ($row = mysqli_fetch_array($result)) {
        echo "<tr><td>" . $row['TurneringsNavn'] . "</td>"
        . "<td>" . $row['Antal'] . "</td>"
        . "<td>" . $row['Dag'] . "</td>"
        . "<td>" . $row['Tid'] . "</td>"
        . "<td>" . "<a href=\"turnering.php?INFO=$row[0]\"> Se Informationer </a>" . "</td></tr>";
    }
?>
</table>