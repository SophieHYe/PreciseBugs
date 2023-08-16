<?php
include '../settings/configurazione.inc';
include HOME_ROOT . '/script/funzioni.php';

if($_SERVER['REQUEST_METHOD'] != 'GET'){
    $connessione = creaConnessione(SERVER,UTENTE,PASSWORD,DATABASE);
    $query = sprintf("SELECT p.codiceprodotto, p.nomeprodotto, c.quantita, p.prezzo FROM tblcarrelli AS c JOIN tblprodotti AS p ON c.codiceprodotto = p.codiceprodotto WHERE c.codiceutente='%s'",rendiSicuro($_POST['codicefiscale']));
    $dati = eseguiQuery($connessione, $query);

    $prezzoFinale = 0;

    print '<form method="post" id="formFattura" action="../script/scriptConfermaAcquisto.php">';
    print '<fieldset><legend>Fattura</legend>';
    print '<div id="tabella"><table>';
    print '<tr><td>Codice</td><td>Nome</td><td>Quantit&agrave</td><td>Prezzo unitario</td><td>Prezzo parziale</td></tr>';
    foreach ($dati as $prodotto) {
        print '<tr><td>'.$prodotto['codiceprodotto'].'</td><td>'.$prodotto['nomeprodotto'].'</td><td>'.$prodotto['quantita'].'</td><td>'.$prodotto['prezzo'].' &#128</td><td>'.($prodotto['prezzo']*$prodotto['quantita']).' &#128</td></tr>';
        $prezzoFinale = $prezzoFinale + ($prodotto['prezzo']*$prodotto['quantita']);
    }
    print '<tr><td colspan="4"></td><td>'.$prezzoFinale.' &#128</td><td>Prezzo finale</td></tr>';
    print '</table></div>';
    print '</fieldset>';

    print '<p id="carteCredito">';
    print '<span>Modalit&agrave di pagamento:</span> ';
    print '<img src="../img/style/amex1.png"/>';
    print '<img src="../img/style/visa1.png"/>';
    print '<img src="../img/style/mastercard1.png"/>';
    print '<img src="../img/style/cirrus1.png"/>';
    print '<br /><input type="submit" value="Conferma pagamento" class="invia" />';
    print '</p>';

    print '</form>';

    print '<script type="text/javascript">';
    print "gestisciForm('#formFattura','" . '../script/scriptConfermaAcquisto.php' . "','#coldx');";
    print '</script>';

    chiudiConnessione($connessione);

} else {
    include HOME_ROOT . '/html/testa.php';
    print '<p class="errore">Attenzione, non puoi accedere direttamente a questa pagina</p>';
    include HOME_ROOT . '/html/coda.html';
}

?>
