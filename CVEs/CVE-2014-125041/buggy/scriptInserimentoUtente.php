<?php
include '../settings/configurazione.inc';
include HOME_ROOT . '/script/funzioni.php';

if($_SERVER['REQUEST_METHOD'] != 'GET'){

    $connessione = creaConnessione(SERVER, UTENTE, PASSWORD, DATABASE);
    $query = sprintf("INSERT INTO tblUtenti(codicefiscale, nome, cognome, datanascita, citta, indirizzo, email, telefono, user, psw, dirittoamministratore) VALUE ('%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s')", strtoupper($_POST['codicefiscale']), $_POST['nome'], $_POST['cognome'], $_POST['datanascita'],$_POST['citta'], $_POST['indirizzo'], $_POST['email'], $_POST['telefono'], $_POST['username'], sha1($_POST['password']), "no");
    $dati = eseguiQuery($connessione, $query);
    chiudiConnessione($connessione);

    print '<p class="successo">' . "L'inserimento dell'utente &egrave; avvenuto correttamente</p>";

} else {
    include HOME_ROOT . '/html/testa.php';
    print '<p class="errore">Attenzione, non puoi accedere direttamente a questa pagina</p>';
    include HOME_ROOT . '/html/coda.html';
}
?>