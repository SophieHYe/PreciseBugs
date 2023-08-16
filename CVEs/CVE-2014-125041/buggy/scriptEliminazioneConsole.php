<?php
include '../settings/configurazione.inc';
include HOME_ROOT . '/script/funzioni.php';

if($_SERVER['REQUEST_METHOD'] != 'GET'){

    $connessione = creaConnessione(SERVER, UTENTE, PASSWORD, DATABASE);

    $query = sprintf("SELECT COUNT(console) FROM tblprodotticonsole WHERE console='%s'", $_POST['nome']);
    $dati = eseguiQuery($connessione, $query);

    if(intval($dati[0]['COUNT(console)'] > 0)){
        print '<p class="informazione">Elimina tutti i prodotti associati a questa console prima di procedere</p>';
    } else {
        $query = sprintf("DELETE FROM tblconsole WHERE nome='%s'", $_POST['nome']);
        $dati = eseguiQuery($connessione, $query);
        print '<p class="successo">La console &egrave; stata eliminata con successo</p>';
    }

    chiudiConnessione($connessione);

} else {
    include HOME_ROOT . '/html/testa.php';
    print '<p class="errore">Attenzione, non puoi accedere direttamente a questa pagina</p>';
    include HOME_ROOT . '/html/coda.html';
}
?>