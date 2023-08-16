<?php
include '../settings/configurazione.inc';
include HOME_ROOT . '/script/funzioni.php';

if($_SERVER['REQUEST_METHOD'] != 'GET'){

    $connessione = creaConnessione(SERVER, UTENTE, PASSWORD, DATABASE);

    if (trim($_POST['produttore']) == '') {
        $query = sprintf("UPDATE tblconsole SET nome='%s', produttore=NULL WHERE nome='%s'", $_POST['nome'], $_POST['nomeOld']);
    } else {
        $query = sprintf("UPDATE tblconsole SET nome='%s', produttore='%s' WHERE nome='%s'", $_POST['nome'], $_POST['produttore'], $_POST['nomeOld']);
    }

    $dati = eseguiQuery($connessione, $query);

    chiudiConnessione($connessione);

    print '<p class="successo">La modifica della console &egrave; avvenuta correttamente</p>';

} else {
    include HOME_ROOT . '/html/testa.php';
    print '<p class="errore">Attenzione, non puoi accedere direttamente a questa pagina</p>';
    include HOME_ROOT . '/html/coda.html';
}
?>