<?php
include '../settings/configurazione.inc';
include HOME_ROOT . '/script/funzioni.php';

if($_SERVER['REQUEST_METHOD'] != 'GET'){

    $connessione = creaConnessione(SERVER, UTENTE, PASSWORD, DATABASE);

    $query = sprintf("DELETE FROM tblcarrelli WHERE codiceprodotto='%s'", rendiSicuro($_POST['codiceprodotto']));
    $dati = eseguiQuery($connessione, $query);

    $query = sprintf("DELETE FROM tblprodotticonsole WHERE codiceprodotto='%s'", rendiSicuro($_POST['codiceprodotto']));
    $dati = eseguiQuery($connessione, $query);

    $query = sprintf("SELECT galleria, immagine FROM tblprodotti WHERE codiceprodotto='%s'", rendiSicuro($_POST['codiceprodotto']));
    $dati = eseguiQuery($connessione, $query);

    $query = sprintf("SELECT COUNT(galleria) FROM tblprodotti WHERE galleria ='%s'", rendiSicuro($dati[0]['galleria']));
    $numeroVersioni = eseguiQuery($connessione, $query);

    // $numeroVersioni indica quanti prodotti condividono la stessa galleria
    if (intval($numeroVersioni[0]['COUNT(galleria)'] > 1)) {
        print '<p class="informazione">Poichè ci sono più versioni dello stesso gioco, la corrispondente galleria non &egrave; stata cancellata.</p>';
    } else {
        if (cancellaCartella('../img/' . $dati[0]['galleria'])) {
            print '<p class="successo">La cartella contenente le immagini &egrave; stata cancellata con successo</p>';
        } else {
            print '<p class="errore">La cartella contenente le immagini non esiste</p>';
        }

        if (cancellaCartella('../img/thumb/' . $dati[0]['galleria'])) {
            print '<p class="successo">La cartella contenente le thumbnails &egrave; stata cancellata con successo</p>';
        } else {
            print '<p class="errore">La cartella contenente le thumbnails non esiste</p>';
        }
    }

    if (cancellaImmagine('../img/' . $dati[0]['immagine'])) {
        print '<p class="successo">L\'immagine principale &egrave; stata cancellata con successo</p>';
    } else {
        print '<p class="errore">L\'immagine principale non esiste</p>';
    }

    if (cancellaImmagine('../img/thumb/' . $dati[0]['immagine'])) {
        print '<p class="successo">La thumbnail dell\'immagine principale &egrave; stata cancellata con successo</p>';
    } else {
        print '<p class="errore">La thumbnail dell\'immagine principale non esiste</p>';
    }

    $query = sprintf("DELETE FROM tblprodotti WHERE codiceprodotto='%s'", rendiSicuro($_POST['codiceprodotto']));
    $dati = eseguiQuery($connessione, $query);

    print '<p class="successo">Il prodotto &egrave; stato eliminato con successo</p>';
    chiudiConnessione($connessione);

} else {
    include HOME_ROOT . '/html/testa.php';
    print '<p class="errore">Attenzione, non puoi accedere direttamente a questa pagina</p>';
    include HOME_ROOT . '/html/coda.html';
}
?>