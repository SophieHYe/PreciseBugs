<?php
include '../settings/configurazione.inc';
include HOME_ROOT . '/script/funzioni.php';
print '<script type="text/javascript" src="'.HOME_WEB.'js/funzioni.js"></script>';

if($_SERVER['REQUEST_METHOD'] != 'GET'){
    $connessione = creaConnessione(SERVER, UTENTE, PASSWORD, DATABASE);

    $query = sprintf("SELECT * FROM tblcategorie WHERE nome='" . rendiSicuro($_POST['nome']) . "'");
    $dati = eseguiQuery($connessione, $query);

    print '<form id="formModificaCategoria" method="post" action="../script/scriptModificaCategoria.php">';
    print '<fieldset><legend>Informazioni categoria</legend>';
    print '<div class="label"><label >Nome</label></div>';
    // Il seguente campo nascosto viene utilizzato per individuare il vecchio valore della chiave primaria nel database e quindi aggiornarlo
    print '<input type="hidden" name="nomeOld" value="' . $dati[0]['nome'] . '"></input>';
    print '<input type="text" name="nome" value="' . $dati[0]['nome'] . '" class="obbligatorio"></input>';
    print '<input type="submit" value="Conferma" class="invia"></input>';
    print '</fieldset>';
    print "</form>";

    print '<script type="text/javascript">';
    print "gestisciForm('#formModificaCategoria','../script/scriptModificaCategoria.php','#coldx');";
    print '</script>';

} else {
    include HOME_ROOT . '/html/testa.php';
    print '<p class="errore">Attenzione, non puoi accedere direttamente a questa pagina</p>';
    include HOME_ROOT . '/html/coda.html';
}
?>