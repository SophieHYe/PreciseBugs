<?php
include '../settings/configurazione.inc';
include HOME_ROOT . '/script/funzioni.php';

if($_SERVER['REQUEST_METHOD'] != 'GET'){

    $connessione = creaConnessione(SERVER, UTENTE, PASSWORD, DATABASE);
    $query = sprintf("INSERT INTO tblUtenti(codicefiscale, nome, cognome, datanascita, citta, indirizzo, email, telefono, user, psw, dirittoamministratore) VALUE ('%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s')", rendiSicuro(strtoupper($_POST['codicefiscale'])), rendiSicuro($_POST['nome']), rendiSicuro($_POST['cognome']), rendiSicuro($_POST['datanascita']),rendiSicuro($_POST['citta']), rendiSicuro($_POST['indirizzo']), rendiSicuro($_POST['email']), rendiSicuro($_POST['telefono']), rendiSicuro($_POST['username']), rendiSicuro(sha1($_POST['password'])), rendiSicuro("no"));
    $dati = eseguiQuery($connessione, $query);
    chiudiConnessione($connessione);

    print '<p class="successo">' . "L'inserimento dell'utente &egrave; avvenuto correttamente</p>";

} else {
    include HOME_ROOT . '/html/testa.php';
    print '<p class="errore">Attenzione, non puoi accedere direttamente a questa pagina</p>';
    include HOME_ROOT . '/html/coda.html';
}
?>