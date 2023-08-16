<?php
include '../settings/configurazione.inc';
include HOME_ROOT . '/script/funzioni.php';
print '<script type="text/javascript" src="'.HOME_WEB.'js/funzioni.js"></script>';

if($_SERVER['REQUEST_METHOD'] != 'GET'){
    $connessione = creaConnessione(SERVER, UTENTE, PASSWORD, DATABASE);
    $query = sprintf("SELECT * FROM tblprodotti WHERE codiceprodotto='" . rendiSicuro($_POST['codiceprodotto']) . "'");
    $dati = eseguiQuery($connessione, $query);
    print '<form enctype="multipart/form-data" method="post" action="../script/scriptModificaProdotto.php">';
    print '<fieldset><legend>Modifica Prodotto</legend>';
    print '<input type="hidden" name="codiceprodotto" value="' . $dati[0]['codiceprodotto'] . '"></input><br /> ';
    print '<div class="label"><label >Nome Prodotto</label></div>';
    print '<input type="text" name="nomeprodotto" class="obbligatorio" value="' . $dati[0]['nomeprodotto'] . '"></input><br /> ';
    print '<div class="label"><label >Descrizione</label></div>';
    print '<textarea rows="5" cols="40" name="descrizione">' . $dati[0]['descrizione'] . '</textarea><br />';
    print '<div class="label"><label >Prezzo (&#128)</label></div>';
    // La funzione number_format viene utilizzata per visualizzare correttamente il prezzo
    print '<input type="text" name="prezzo" class="obbligatorio decimale" value="' . number_format($dati[0]['prezzo'],2) . '"/><br />';
    print '<div class="label"><label >Numero Pezzi</label></div>';
    print '<input type="text" name="numeropezzi" class="obbligatorio intero" value="' . $dati[0]['numeropezzi'] . '"/><br />';
    print '<div class="label"><label >Immagine</label></div>';
    print '<input type="file" name="immagine" class="obbligatorio"/><br />';
    print '<div class="label"><label >Galleria Immagini</label></div>';
    print '<input type="text" name="galleria" class="obbligatorio" value="' . $dati[0]['galleria'] . '"/><br />';
    print '<div class="label"><label >Categoria Prodotto</label></div>';
    print '<select name="categoria" class="obbligatorio">';

    $query = sprintf("SELECT nome FROM tblcategorie");
    $datiCategoria = eseguiQuery($connessione, $query);

    foreach ($datiCategoria as $riga) {
        print '<option value="' . $riga['nome'] . '"' . ($riga['nome'] == $dati[0]['categoria'] ? 'selected="selected"' : "") . '>' . $riga['nome'] . '</option>';
    }
    print '</select><br />';
    print '<div class="label"><label >Console</label></div>';
    print '<select name="console" class="obbligatorio">';

    $query = sprintf("SELECT nome FROM tblconsole");
    $datiConsole = eseguiQuery($connessione, $query);

    foreach ($datiConsole as $riga) {
        print '<option value="' . $riga['nome'] . '"' . ($riga['nome'] == $_POST['console'] ? 'selected="selected"' : "") . '>' . $riga['nome'] . '</option>';
    }
    print '</select>';
    print '<br /><input type="submit" class="invia" value="Conferma"></input>';
    print '</fieldset>';
    print "</form>";

    chiudiConnessione($connessione);

} else {
    include HOME_ROOT . '/html/testa.php';
    print '<p class="errore">Attenzione, non puoi accedere direttamente a questa pagina</p>';
    include HOME_ROOT . '/html/coda.html';
}
?>