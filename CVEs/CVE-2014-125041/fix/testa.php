<!DOCTYPE html>
<head>
    <title>Games Commerce</title>
    <script type="text/javascript" src="<?php print HOME_WEB; ?>js/jquery.min.js"></script>
    <script type="text/javascript" src="<?php print HOME_WEB; ?>js/jquery-ui.min.js"></script>
    <script type="text/javascript" src="<?php print HOME_WEB; ?>js/pirobox-extended-min.js"></script>
    <script type="text/javascript" src="<?php print HOME_WEB; ?>js/modernizr.custom.17475.js"></script>
    <script type="text/javascript" src="<?php print HOME_WEB; ?>js/jquery.elastislide.js"></script>
    <script type="text/javascript" src="<?php print HOME_WEB; ?>js/funzioni.js"></script>
    <link rel="stylesheet" href="<?php print HOME_WEB;?>css/stile.css" type="text/css">
    <link rel="stylesheet" href="<?php print HOME_WEB;?>css/jquery-ui.min.css" type="text/css">
    <link rel="stylesheet" href="<?php print HOME_WEB;?>css/style.css" type="text/css">
    <link rel="stylesheet" href="<?php print HOME_WEB;?>css/elastislide.css" type="text/css">
</head>
<body>
<div id="contenuto">
    <div id="testa">
        <p id="titolo">Games Commerce</p>
    </div>
    <div id="logo">
    </div>
    <nav id="menu">
        <ul>

            <li><a href="<?php print HOME_WEB; ?>index.php">Home</a></li>

            <?php

            if (!isset($_SESSION['collegato'])) {
                print '<li><a href="' . HOME_WEB . 'html/moduloRegistrazione.php">Registrazione Utente</a></li>';
            }
            if (isset($_SESSION['amministratore'])) {
                if ($_SESSION['amministratore']) {
                    print '<li><a href="#">Prodotto</a>';
                    print '<ul>';
                    print'<li><a href=' . HOME_WEB . 'html/moduloInserimentoProdotto.php>Inserimento Prodotto</a></li>';
                    print'<li><a href=' . HOME_WEB . 'html/moduloModificaProdotto.php>Modifica Prodotto</a></li>';
                    print'<li><a href=' . HOME_WEB . 'html/moduloEliminazioneProdotto.php>Eliminazione Prodotto</a></li>';
                    print '</ul></li>';
                    print '<li><a href="#">Galleria</a>';
                    print '<ul>';
                    print'<li><a href=' . HOME_WEB . 'html/moduloInserimentoImmagine.php>Inserimento Immagini</a></li>';
                    print'<li><a href=' . HOME_WEB . 'html/moduloEliminazioneImmagine.php>Eliminazione Immagini</a></li>';
                    print '</ul></li>';
                    print '<li><a href="#">Categoria</a>';
                    print '<ul>';
                    print'<li><a href=' . HOME_WEB . 'html/moduloInserimentoCategoria.php>Inserimento Categoria</a></li>';
                    print'<li><a href=' . HOME_WEB . 'html/moduloModificaCategoria.php>Modifica Categoria</a></li>';
                    print'<li><a href=' . HOME_WEB . 'html/moduloEliminazioneCategoria.php>Eliminazione Categoria</a></li>';
                    print '</ul></li>';
                    print '<li><a href="#">Console</a>';
                    print '<ul>';
                    print'<li><a href=' . HOME_WEB . 'html/moduloInserimentoConsole.php>Inserimento Console</a></li>';
                    print'<li><a href=' . HOME_WEB . 'html/moduloModificaConsole.php>Modifica Console</a></li>';
                    print'<li><a href=' . HOME_WEB . 'html/moduloEliminazioneConsole.php>Eliminazione Console</a></li>';
                    print '</ul></li>';
                    print'<li><a href=' . HOME_WEB . 'html/moduloAmministrazione.php>Amministrazione</a></li>';
                }
            }
            if (isset($_SESSION['collegato'])) {
                print'<li><a href=' . HOME_WEB . 'html/moduloVisualizzazioneCarrello.php>Carrello</a></li>';
            } else {
                print'<li><a href=' . HOME_WEB . 'html/moduloLogin.php>Carrello</a></li>';
            }
            ?>
            <li><a href="<?php print HOME_WEB ?>html/moduloVisualizzazioneCatalogo.php">Catalogo</a></li>
            <?php
            if (isset($_SESSION['collegato'])) {
                print '<li><a href=' . HOME_WEB . 'html/moduloModificaUtente.php>Profilo Utente</a></li>';
            }
            if (!isset($_SESSION['collegato'])) {
                print '<li><a href=' . HOME_WEB . 'html/moduloLogin.php>Login</a></li>';
            } else {
                print '<li><a href=' . HOME_WEB . 'script/scriptLogout.php>Logout</a></li>';
            }
            print '</ul>';
            print '</nav>';
            ?>
            <div id="corpo">
                <div id="colsx">
                </div>
                <div id="coldx"> <!-- Chiusura di coldx nel file coda.html -->