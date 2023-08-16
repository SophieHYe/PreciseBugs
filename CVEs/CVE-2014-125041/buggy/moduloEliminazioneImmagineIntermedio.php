<?php
include '../settings/configurazione.inc';
include HOME_ROOT . '/script/funzioni.php';

if($_SERVER['REQUEST_METHOD'] != 'GET'){
    $connessione = creaConnessione(SERVER, UTENTE, PASSWORD, DATABASE);
    $query = sprintf("SELECT galleria FROM tblprodotti WHERE codiceprodotto='%s'", $_POST['codiceprodotto']);
    $dati = eseguiQuery($connessione, $query);

    $percorsoThumbnails = '../img/thumb/' . $dati[0]['galleria'] . '/';

    // La funzione glob cerca tutte le immagini presenti nel percorso specificato.

    $thumbnails = glob($percorsoThumbnails . "*");

    // Se nella galleria sono presenti immagini
    if($thumbnails) {
        print '<p class="informazione">Seleziona le immagini da eliminare</p>';

        print '<form id="formEliminazioneImmagini" method="post" action="../script/scriptEliminazioneImmagine.php">';
        print '<input type="hidden" name="galleria" value="' . $dati[0]['galleria'] . '">';

        // Successivamente le thumbnail relative a ciascuna immagine vengono visualizzate
        // Con la checkbox si indicano quelle da eliminare

        foreach ($thumbnails as $thumb) {
            print '<div id="immagineEliminazione">';
            print '<div><img src="' . $thumb . '"></div>';
            print '<div><input type="checkbox" name="immagine[]" value="' . basename($thumb) . '"/>';
            print '<label>' . basename($thumb) . '</label></div>';
            print '</div>';
        }

        print '<div id="pulsanteEliminazione"><input type="submit" value="Elimina"/></div>';
        print '</form>';

        print '<script type="text/javascript">';
        print "gestisciForm('#formEliminazioneImmagini','../script/scriptEliminazioneImmagine.php','#coldx');";
        print '</script>';
    } else {
        print '<p class="informazione">La galleria di questo prodotto &egrave; vuota</p>';
    }
    chiudiConnessione($connessione);
} else {
    include HOME_ROOT . '/html/testa.php';
    print '<p class="errore">Attenzione, non puoi accedere direttamente a questa pagina</p>';
    include HOME_ROOT . '/html/coda.html';
}
?>