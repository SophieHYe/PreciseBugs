<?php
include '../settings/configurazione.inc';
include HOME_ROOT . '/html/testa.php';
include HOME_ROOT . '/script/funzioni.php';

if (isset($_SESSION['collegato'])) {

    $connessione = creaConnessione(SERVER, UTENTE, PASSWORD, DATABASE);

    $query = sprintf("SELECT u.codicefiscale, c.codiceprodotto, c.quantita, p.nomeprodotto, p.prezzo,
    p.immagine, p.categoria, pc.console FROM ((tblutenti AS u JOIN tblcarrelli AS c
    ON u.codicefiscale = c.codiceutente) JOIN tblprodotti AS p on c.codiceprodotto = p.codiceprodotto)
    JOIN tblprodotticonsole AS pc ON p.codiceprodotto = pc.codiceprodotto WHERE u.user='%s'", $_SESSION['username']);

    $dati = eseguiQuery($connessione, $query);

    if($dati) {
    $numeroPagine = ceil(count($dati) / PRODOTTIPERPAGINA);

    if (!isset($_GET["pagina"])) {
        $pagina = 1;
    } else {
        $pagina = $_GET["pagina"];
    }

    // $i indica il primo prodotto di ogni pagina.
    $i = ($pagina * PRODOTTIPERPAGINA) - (PRODOTTIPERPAGINA);

    while ($i < PRODOTTIPERPAGINA * $pagina && $i < count($dati)) {
        print '<div class="corpoCatalogo">' .
            '<div class="catcolsx"><img src="' . HOME_WEB . 'img/thumb/' . $dati[$i]['immagine'] . '" alt="Immagine del prodotto" height="165" width="120">' . '</div>' .
            '<div class="catcoldx"> <p><b>Codice Prodotto: </b>' . $dati[$i]['codiceprodotto'] . '</p>' .
            '<p><b>Nome Prodotto: </b>' . $dati[$i]['nomeprodotto'] . '</p>' .
            '<p><b>Prezzo: </b>' . number_format($dati[$i]['prezzo'], 2) . ' &euro;</p>' .
            '<p><b>Categoria: </b>' . $dati[$i]['categoria'] . '</p>' .
            '<p><b>Console: </b>' . $dati[$i]['console'] . '</p>' .
            '<p><b>Quantita Richiesta: </b>' . $dati[$i]['quantita'] . '</p>';
        print '<form id="' . $dati[$i]['codiceprodotto'] . '" method="post" action="../script/scriptEliminazioneCarrello.php">';
        print '<input type="hidden" name="codiceEliminazione" value="' . $dati[$i]['codiceprodotto'] . '"/>';
        print '<p><b>Quantit&agrave;</b>';
        print '<input type="text" size="3" name="quantitaEliminazione" class="intero" value="0">';
        print '<input type="submit" class="invia" value="Elimina"></p>';
        print '</form>';
        print '</div>' . '</div>';
        print '<script type="text/javascript">';
        print "gestisciForm('#" . $dati[$i]['codiceprodotto'] . "','" . '../script/scriptEliminazioneCarrello.php' . "','#coldx');";
        print '</script>';
        $i++;
    }
        print '<form id="confermaAcquisto" method="post" action="moduloVisualizzazioneFattura.php">';

        // Questo campo nascosto verrà utilizzato per la conferma dell'acquisto e la conseguente eliminazione del carrello dell'utente.

        print '<input type="hidden" name="codicefiscale" value="' . $dati[0]['codicefiscale'] . '"/>';
        print '<input type="submit" id="pulsanteAcquisto" value="Conferma l\'acquisto">';
        print '</form>';

        visualizzaPaginazione($pagina, $numeroPagine, 'Carrello');

        print '<script type="text/javascript">';
        print "gestisciForm('#confermaAcquisto','" . 'moduloVisualizzazioneFattura.php' . "','#coldx');";
        print '</script>';
    } else {
        print '<p class="informazione">Il tuo carrello &egrave; vuoto.</p>';
    }
    chiudiConnessione($connessione);
} else {
    print '<p class="errore">Non sei autorizzato a visualizzare questa pagina, per favore, esegui il login.</p>';
}

include HOME_ROOT . '/html/coda.html';
?>