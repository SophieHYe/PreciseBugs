<?php
include '../settings/configurazione.inc';
include HOME_ROOT . '/script/funzioni.php';

if($_SERVER['REQUEST_METHOD'] != 'GET'){

    $connessione = creaConnessione(SERVER, UTENTE, PASSWORD, DATABASE);
    if (trim($_POST['produttore']) == '') {
        $query = sprintf("INSERT INTO tblconsole(nome) VALUE ('%s')", $_POST['nome']);
    } else {
        $query = sprintf("INSERT INTO tblconsole(nome,produttore) VALUE ('%s','%s')", $_POST['nome'], $_POST['produttore']);
    }
    $dati = eseguiQuery($connessione, $query);

    chiudiConnessione($connessione);

    print '<p class="successo">' . "L'inserimento della console &egrave; avvenuto correttamente</p>";
} else {
    include HOME_ROOT . '/html/testa.php';
    print '<p class="errore">Attenzione, non puoi accedere direttamente a questa pagina</p>';
    include HOME_ROOT . '/html/coda.html';
}
?>