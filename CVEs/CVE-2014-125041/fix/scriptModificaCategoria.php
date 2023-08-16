<?php
include '../settings/configurazione.inc';
include HOME_ROOT . '/script/funzioni.php';

if($_SERVER['REQUEST_METHOD'] != 'GET'){

    $connessione = creaConnessione(SERVER, UTENTE, PASSWORD, DATABASE);
    $query = sprintf("UPDATE tblcategorie SET nome='%s' WHERE nome='%s'", rendiSicuro($_POST['nome']), rendiSicuro($_POST['nomeOld']));
    $dati = eseguiQuery($connessione, $query);

    chiudiConnessione($connessione);

    print '<p class="successo">La modifica della categoria &egrave; avvenuta correttamente</p>';

} else {
    include HOME_ROOT . '/html/testa.php';
    print '<p class="errore">Attenzione, non puoi accedere direttamente a questa pagina</p>';
    include HOME_ROOT . '/html/coda.html';
}
?>