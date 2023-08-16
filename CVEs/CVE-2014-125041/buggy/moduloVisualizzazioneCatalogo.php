<?php
include '../settings/configurazione.inc';
include HOME_ROOT . '/script/funzioni.php';
include HOME_ROOT . '/html/testa.php';

$connessione = creaConnessione(SERVER, UTENTE, PASSWORD, DATABASE);
$query = sprintf("SELECT * FROM tblprodotti AS p LEFT JOIN tblprodotticonsole AS pc ON p.codiceprodotto = pc.codiceprodotto");
$dati = eseguiQuery($connessione, $query);
$cartellaImmaginePrincipale = 'img';

$numeroPagine = ceil(count($dati) / PRODOTTIPERPAGINA);

if (!isset($_GET["pagina"])) {
    $pagina = 1;
} else {
    $pagina = $_GET["pagina"];
}

// $i indica il primo prodotto di ogni pagina.
$i = ($pagina * PRODOTTIPERPAGINA) - (PRODOTTIPERPAGINA);

while ($i < PRODOTTIPERPAGINA * $pagina && $i< count($dati)) {
    print '<div class="corpoCatalogo">' . '<div class="catcolsx"><img alt="Immagine non trovata"
        src="' . HOME_WEB . '/' . $cartellaImmaginePrincipale . '/thumb/' . $dati[$i]['immagine'] . '">' .
        '</div>' . '<div class="catcoldx"><p><b>Codice Prodotto: </b>' . $dati[$i]['codiceprodotto'] . '</p>' .
        '<p><b>Nome Prodotto: </b>' . $dati[$i]['nomeprodotto'] . '</p>' .
        '<p><b>Descrizione: </b>' . $dati[$i]['descrizione'] . '</p>' .
        '<p><b>Prezzo: </b>' . number_format($dati[$i]['prezzo'],2) . ' &euro;</p>' .
        '<p><b>Quantit&agrave; Disponibile: </b>' . $dati[$i]['numeropezzi'] . '</p>' .
        '<p><b>Categoria: </b>' . $dati[$i]['categoria'] . '</p>' .
        '<p><b>Console: </b>' . $dati[$i]['console'] . '</p>';
    if (isset($_SESSION['collegato'])) {
        print '<form id="' . $dati[$i]['codiceprodotto'] . '" method="post" action="../script/scriptInserimentoCarrello.php">';
        print '<input type="hidden" name="codiceprodotto" value="' . $dati[$i]['codiceprodotto'] . '"/>';
        print '<p><b>Quantit&agrave;</b>';
        print '<input type="text" size="3" name="quantita" class="intero" value="0">';
        print '<input type="submit" class="invia" value="Aggiungi al carrello"></p>';
        print '</form>';
        print '<script type="text/javascript">';
        print "gestisciForm('#" . $dati[$i]['codiceprodotto'] . "','" . '../script/scriptInserimentoCarrello.php' . "','#coldx');";
        print '</script>';
    } else {
        print '<p class="informazione">Esegui il login per inserire il prodotto nel carrello</p>';
    }
    print '<div class="galleria">';
    print '<script type="text/javascript">';
    print 'gestisciImmaginiGalleria();';
    print '</script>';
    print '<ul id="carouse" class="elastislide-list">';
    visualizzaGalleria($dati[$i]['galleria'], $dati[$i]['codiceprodotto']);
    print '</ul>';
    print '</div>';
    print '</div></div>';
    $i++;
}

visualizzaPaginazione($pagina,$numeroPagine,'Catalogo');

print '<script type="text/javascript">';
print 'gestisciThumbnailsGalleria();';
print '</script>';

chiudiConnessione($connessione);

include HOME_ROOT . '/html/coda.html';
?>
