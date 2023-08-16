<?php
include '../settings/configurazione.inc';
include HOME_ROOT . '/script/funzioni.php';

if($_SERVER['REQUEST_METHOD'] != 'GET'){
    $connessione = creaConnessione(SERVER, UTENTE, PASSWORD, DATABASE);

    $query = sprintf("SELECT codiceprodotto, nomeprodotto FROM tblprodotti WHERE codiceprodotto='" . rendiSicuro($_POST['codiceprodotto']) . "'");
    $dati = eseguiQuery($connessione, $query);

    if (!$dati) {
        print '<p class="errore">Il prodotto non è stato individuato, controlla il nome inserito</p>';
    } else {
        print '<form method="post" enctype="multipart/form-data" action="../script/scriptInserimentoImmagine.php">';
        print '<fieldset><legend>Inserisci immagini nella galleria di ' . $dati[0]['nomeprodotto'] . '</legend>';
        print '<div class="label"><label>Seleziona le immagini</label></div>';
        print '<input type="hidden" name="codiceprodotto" value="' . $dati[0]['codiceprodotto'] . '"/>';
        print '<input type="file" class="obbligatorio" name="immagini[]" multiple="multiple" accept="image/*" />';
        print '<input type="submit" value="Inserisci" class="invia" />';
        print '</fieldset>';
        print '</form>';
    }
    chiudiConnessione($connessione);
} else {
    include HOME_ROOT . '/html/testa.php';
    print '<p class="errore">Attenzione, non puoi accedere direttamente a questa pagina</p>';
    include HOME_ROOT . '/html/coda.html';
}
?>