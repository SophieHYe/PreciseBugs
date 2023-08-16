<?php
include '../settings/configurazione.inc';
include HOME_ROOT . '/script/funzioni.php';

if($_SERVER['REQUEST_METHOD'] != 'GET'){

    $connessione = creaConnessione(SERVER, UTENTE, PASSWORD, DATABASE);

    $utente = $_SESSION['username'];

    $quantitaInserimento = $_POST['quantita'];
    $codiceInserimento = $_POST['codiceprodotto'];

    $query = sprintf("SELECT numeropezzi FROM tblprodotti WHERE codiceprodotto='%s'", $codiceInserimento);
    $dati = eseguiQuery($connessione, $query);

    if (($quantitaInserimento > $dati[0]['numeropezzi']) || ($quantitaInserimento <= 0)) {
        print '<p class="errore">Non puoi comprare un numero di prodotti negativo oppure maggiore della quantita disponibile. Riprova.</p>';
    } else {

        $query = sprintf("SELECT u.codicefiscale, c.codiceprodotto, c.quantita, p.numeropezzi
             FROM (tblutenti AS u JOIN tblcarrelli AS c ON u.codicefiscale = c.codiceutente)
             JOIN tblprodotti AS p on c.codiceprodotto = p.codiceprodotto
             WHERE u.user='%s' AND c.codiceprodotto='%s'", $utente, $codiceInserimento);

        $dati = eseguiQuery($connessione, $query);
        $codiceFiscale = $dati[0]['codicefiscale'];

        // Se il prodotto selezionato non è già presente nel carrello, lo aggiunge.
        if (!$dati) {
            $query = sprintf("INSERT INTO tblcarrelli(codiceprodotto, codiceutente, quantita) VALUE ('%s','%s','%d')", $codiceInserimento, $codiceFiscale, $quantitaInserimento);
            $dati = eseguiQuery($connessione, $query);
            print '<p class="successo">Inserimento nel carrello avvenuto correttamente</p>';
        } else {
            // Se è già presente vengono fatti dei controlli e viene aggiorata la quantità
            $quantitaTotale = $dati[0]['quantita'] + $quantitaInserimento;
            if ($quantitaTotale > $dati[0]['numeropezzi']) {
                print '<p class="errore">Attenzione, non puoi inserire una quantit&agrave; di prodotto maggiore di quella in magazzino!</p>';
            } else {
                $query = sprintf("UPDATE tblcarrelli SET quantita='%d' WHERE codiceprodotto='%s' AND codiceutente ='%s'", $quantitaTotale, $codiceInserimento, $codiceFiscale);
                $dati = eseguiQuery($connessione, $query);
                print '<p class="successo">Aggiornamento del carrello avvenuto correttamente</p>';
            }
        }
    }

    chiudiConnessione($connessione);

} else {
    include HOME_ROOT . '/html/testa.php';
    print '<p class="errore">Attenzione, non puoi accedere direttamente a questa pagina</p>';
    include HOME_ROOT . '/html/coda.html';
}
?>