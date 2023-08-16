<?php
include '../settings/configurazione.inc';
include HOME_ROOT . '/script/funzioni.php';

if($_SERVER['REQUEST_METHOD'] != 'GET'){
    $connessione = creaConnessione(SERVER, UTENTE, PASSWORD, DATABASE);

    $utente = $_SESSION['username'];
    $quantitaEliminazione = $_POST['quantitaEliminazione'];
    $codiceEliminazione = $_POST['codiceEliminazione'];

// La query recupera la tupla del prodotto da eliminare dal carrello in base al codice del prodotto stesso e all'utente collegato
    $query = sprintf("SELECT u.codicefiscale, c.codiceprodotto, c.quantita
             FROM tblutenti AS u JOIN tblcarrelli AS c ON u.codicefiscale = c.codiceutente
             WHERE u.user='%s' AND c.codiceprodotto='%s'", rendiSicuro($utente), rendiSicuro($codiceEliminazione));
    $dati = eseguiQuery($connessione, $query);

    if ($quantitaEliminazione > $dati[0]['quantita']) {
        print '<p class="errore">Non puoi eliminare dal carrello una quantita maggiore di quella inserita precedentemente</p>';
    } else {

        // il codice fiscale dell'utente collegato viene recuperato
        $query = sprintf("SELECT codicefiscale FROM tblutenti WHERE user='%s'", rendiSicuro($utente));
        $infoUtente = eseguiQuery($connessione, $query);
        $codiceFiscale = $infoUtente[0]['codicefiscale'];


        // la quantità di prodotto presente nel carrello dell'utente viene aggiornata
        $quantitaAggiornata = $dati[0]['quantita'] - $quantitaEliminazione;
        $query = sprintf("UPDATE tblCarrelli SET quantita='%d' WHERE codiceprodotto='%s' AND codiceutente='%s'", rendiSicuro($quantitaAggiornata), rendiSicuro($codiceEliminazione), rendiSicuro($codiceFiscale));
        $dati = eseguiQuery($connessione, $query);

        $query = sprintf("SELECT quantita FROM tblcarrelli WHERE codiceprodotto='%s' AND codiceutente='%s'", rendiSicuro($codiceEliminazione), rendiSicuro($codiceFiscale));
        $dati = eseguiQuery($connessione, $query);

        // Se la nuova quantità di prodotto presente nel carrello è negativa o zero, l'intera tupla viene eliminata
        if ($dati[0]['quantita'] <= 0) {
            $query = sprintf("DELETE FROM tblcarrelli WHERE codiceutente='%s' AND codiceprodotto='%s'", rendiSicuro($codiceFiscale), rendiSicuro($codiceEliminazione));
            $dati = eseguiQuery($connessione, $query);
        }
        print '<p class="successo">Aggiornamento del carrello eseguito correttamente</p>';
    }
} else {
    include HOME_ROOT . '/html/testa.php';
    print '<p class="errore">Attenzione, non puoi accedere direttamente a questa pagina</p>';
    include HOME_ROOT . '/html/coda.html';
}
?>
