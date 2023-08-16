<?php
include '../settings/configurazione.inc';
include HOME_ROOT . '/script/funzioni.php';

if($_SERVER['REQUEST_METHOD'] != 'GET'){

    $connessione = creaConnessione(SERVER,UTENTE,PASSWORD,DATABASE);

    /* Il codice fiscale viene salvato per poter recuperare le tuple presenti nella tabella molti a molti del carrello
     in base all'utente collegato con un dato username in quell'istante */
    $query = sprintf("SELECT codicefiscale FROM tblutenti WHERE user='".rendiSicuro($_SESSION['username'])."'");
    $dati = eseguiQuery($connessione,$query);
    $codiceFiscale = $dati[0]['codicefiscale'];

    // Recupero dei prodotti inseriti nel carrello dell'utente collegato
    $connessione = creaConnessione(SERVER,UTENTE,PASSWORD,DATABASE);
    $query = sprintf("SELECT p.codiceprodotto, p.nomeprodotto, c.quantita, p.prezzo FROM tblcarrelli AS c JOIN tblprodotti AS p ON c.codiceprodotto = p.codiceprodotto WHERE c.codiceutente='%s'",rendiSicuro($codiceFiscale));
    $dati = eseguiQuery($connessione, $query);

    foreach ($dati as $prodotto) {
        // Per ogni prodotto, viene recuperata la quantità in magazzino
        $query = sprintf("SELECT numeropezzi FROM tblprodotti WHERE codiceprodotto='%s'",rendiSicuro($prodotto['codiceprodotto']));
        $infoPezzi = eseguiQuery($connessione,$query);
        // La quantità aggiornata è data dalla quantità in magazzino meno la quantità richiesta
        $quantitàAggiornata = $infoPezzi[0]['numeropezzi'] - $prodotto['quantita'];
        // L'informazione su dabatase viene aggiornata
        $query = sprintf("UPDATE tblprodotti SET numeropezzi='%d' WHERE codiceprodotto='%s'",rendiSicuro($quantitàAggiornata),rendiSicuro($prodotto['codiceprodotto']));
        $dati = eseguiQuery($connessione,$query);
    }

// Una volta compiute le operazioni d'aggiornamento, le tuple dell'utente nel carrello vengono cancellate
    $query = sprintf("DELETE FROM tblcarrelli WHERE codiceutente='%s'",rendiSicuro($codiceFiscale));
    eseguiQuery($connessione, $query);

    chiudiConnessione($connessione);

    print '<p class="successo">L\'acquisto &egrave stato completato con successo</p>';

} else {
    include HOME_ROOT . '/html/testa.php';
    print '<p class="errore">Attenzione, non puoi accedere direttamente a questa pagina</p>';
    include HOME_ROOT . '/html/coda.html';
}
?>
