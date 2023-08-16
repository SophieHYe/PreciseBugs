<?php
include '../settings/configurazione.inc';
include HOME_ROOT . '/script/funzioni.php';

if($_SERVER['REQUEST_METHOD'] != 'GET'){

    $connessione = creaConnessione(SERVER, UTENTE, PASSWORD, DATABASE);
    $query = sprintf("SELECT user, psw, dirittoamministratore FROM tblutenti WHERE user=" . "'" . rendiSicuro($_POST['username']) . "'" . " AND " . "psw=" . "'" . rendiSicuro(sha1($_POST['password'])) . "'");
    $dati = eseguiQuery($connessione, $query);

    if ($dati == null) {
        print '<p class="errore">Alcuni dei tuoi dati sono errati, riprova</p>';
    } else { // Se la query ritorna una tupla
        // Le due variabili di sessione vengono impostate per mantenere lo stato dell'utente verificato
        $_SESSION['username'] = $_POST['username'];
        $_SESSION['password'] = $_POST['password'];
        // Se il flag relativo ai privilegi amministrativi è si, allora l'utente è un amministratore
        if ($dati[0]["dirittoamministratore"] == "si") {
            /* La seguente variabile di sessione viene usata in diversi punti del codice per fare in modo che solamente
            l'amministratore possa accedere a determinare aree del sito*/
            $_SESSION['amministratore'] = true;
        } else {
            $_SESSION['amministratore'] = false;
        }
        /* La seguente variabile di sessione viene usata per controllare che qualcuno sia collegato, indipendentemente dal
        fatto che sia amministratore o meno */
        $_SESSION['collegato'] = true;
        // Una volta terminate le operazioni, viene eseguito un ridirezionamento tramite jQuery
        print '<script type="text/javascript">';
        print "$(window.location).attr('href', '../index.php');";
        print '</script>';
    }

    chiudiConnessione($connessione);

} else {
    include HOME_ROOT . '/html/testa.php';
    print '<p class="errore">Attenzione, non puoi accedere direttamente a questa pagina</p>';
    include HOME_ROOT . '/html/coda.html';
}
?>
