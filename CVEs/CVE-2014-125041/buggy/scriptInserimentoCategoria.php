<?php
include '../settings/configurazione.inc';
include HOME_ROOT . '/script/funzioni.php';

if($_SERVER['REQUEST_METHOD'] != 'GET'){

    $connessione = creaConnessione(SERVER, UTENTE, PASSWORD, DATABASE);
    $query = sprintf("INSERT INTO tblcategorie(nome) VALUE ('%s')", $_POST['nome']);
    $dati = eseguiQuery($connessione, $query);

    chiudiConnessione($connessione);

    print '<p class="successo">' . "L'inserimento della categoria &egrave; avvenuto correttamente</p>";
} else {
    include HOME_ROOT . '/html/testa.php';
    print '<p class="errore">Attenzione, non puoi accedere direttamente a questa pagina</p>';
    include HOME_ROOT . '/html/coda.html';
}
?>