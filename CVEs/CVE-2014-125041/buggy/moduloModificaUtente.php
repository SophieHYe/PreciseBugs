<?php
include '../settings/configurazione.inc';
include HOME_ROOT . '/script/funzioni.php';
include HOME_ROOT . '/html/testa.php';

if (isset($_SESSION['collegato'])){
    $connessione = creaConnessione(SERVER, UTENTE, PASSWORD, DATABASE);
    $query = sprintf("SELECT * FROM tblutenti WHERE user='" . $_SESSION['username']. "'");
    $dati = eseguiQuery($connessione, $query);

    print '<p class="informazione">Attenzione, dovrai eseguire di nuovo il login, una volta modificato il profilo</p>';
    print '<form id="formProfiloUtente" action="../script/scriptModificaUtente.php" method="post">';
    print '<fieldset><legend>Informazioni profilo utente</legend>';
    print '<input type="hidden" name="oldcodicefiscale" value="' . $dati[0]['codicefiscale'] . '">';
    print '<div class="label"><label >Codice Fiscale</label></div>';
    print '<input type="text" maxlength="16" name="codicefiscale" class="obbligatorio" value="' . $dati[0]['codicefiscale'] . '"><br /> ';
    print '<div class="label"><label >Nome</label></div>';
    print '<input type="text" name="nome" class="obbligatorio" value="' . $dati[0]['nome'] . '"><br /> ';
    print '<div class="label"><label >Cognome</label></div>';
    print '<input type="text" name="cognome" class="obbligatorio" value="' . $dati[0]['cognome'] . '"><br /> ';
    print '<div class="label"><label >Data Nascita</label></div>';
    print '<input type="text" name="datanascita" value="'.$dati[0]['datanascita'].'" class="obbligatorio" id="calendario"><br/>';
    print '<div class="label"><label >Citt&agrave;</label></div>';
    print '<input type="text" name="citta" class="obbligatorio" value="' . $dati[0]['citta'] . '"><br /> ';
    print '<div class="label"><label >Indirizzo</label></div>';
    print '<input type="text" name="indirizzo" class="obbligatorio" value="' . $dati[0]['indirizzo'] . '"><br /> ';
    print '<div class="label"><label >Email</label></div>';
    print '<input type="text" name="email" class="obbligatorio" value="' . $dati[0]['email'] . '"><br /> ';
    print '<div class="label"><label >Telefono</label></div>';
    print '<input type="text" name="telefono" class="obbligatorio intero" value="' . $dati[0]['telefono'] . '"><br /> ';
    print '<div class="label"><label >Username</label></div>';
    print '<input type="text" name="username" class="obbligatorio" value="' . $dati[0]['user'] . '"><br /> ';
    print '<div class="label"><label>Password</label></div>';
    print '<input type="password" name="password" class="obbligatorio" value=""><br /> ';
    print '<input type="submit" class="invia" value="Conferma">';
    print '</fieldset>';
    print '</form>';
    print '<script type="text/javascript">';
    print "gestisciForm('#formProfiloUtente','" .'../script/scriptModificaUtente.php'. "','#coldx');";
    print '</script>';
} else {
    print '<p class="errore">Non sei autorizzato a visualizzare questa pagina, per favore, esegui il login.</p>';
}

include HOME_ROOT . '/html/coda.html';
?>