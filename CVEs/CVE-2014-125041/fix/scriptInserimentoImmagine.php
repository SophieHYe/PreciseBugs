<?php
include '../settings/configurazione.inc';
include HOME_ROOT . '/script/funzioni.php';
include HOME_ROOT . '/html/testa.php';

if($_SERVER['REQUEST_METHOD'] != 'GET'){

    $connessione = creaConnessione(SERVER, UTENTE, PASSWORD, DATABASE);
    $query = sprintf("SELECT galleria FROM tblprodotti WHERE codiceprodotto='" . rendiSicuro($_POST['codiceprodotto']) . "'");
    $dati = eseguiQuery($connessione, $query);

    $percorsoGalleria = 'img/' . $dati[0]['galleria'];

    for ($i = 0; $i < count($_FILES['immagini']['name']); $i++) {
        if(gestioneImmagine($i,$dati[0]['galleria'])){
            generaThumbnail($_FILES['immagini']['tmp_name'][$i],'/img/thumb/'.$dati[0]['galleria'],370,220,$i);
        }
    }

    chiudiConnessione($connessione);

} else {
    print '<p class="errore">Attenzione, non puoi accedere direttamente a questa pagina</p>';
}

include HOME_ROOT . '/html/coda.html';
?>