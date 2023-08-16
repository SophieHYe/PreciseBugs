<?php

function creaConnessione($server,$utente,$password,$database) {
    $connessione = mysqli_connect($server, $utente, $password);
    mysqli_select_db($connessione,$database) or die(mostraErrore($connessione));
    return $connessione;
}

function eseguiQuery($connessione,$query){
    $risultatoQuery = mysqli_query($connessione,$query)or die(mostraErrore($connessione));
    $dati = array();
    /* Se non viene eseguita una query di insert, update o delete, che ritornano un booleano,
    le tuple selezionate vengono messe in un vettore; viene cosi ottenuto un vettore di
    vettori associativi*/
    if(!is_bool($risultatoQuery)) {
        while($riga = mysqli_fetch_assoc($risultatoQuery)){
            $dati[] = $riga;
        }
    }
    return $dati;
}

function chiudiConnessione($connessione){
    mysqli_close($connessione);
}

function mostraErrore($connessione){
    echo '<p class="errore">'."I dati che hai inserito hanno generato questo errore: ".mysqli_error($connessione)."</p>";
}

function gestioneImmagine($indice, $galleria){
    // Vengono analizzati i valori presenti nel vettore $_FILES per determinare l'esito del caricamento
    if($indice == -1){
        // Caso in cui viene gestita l'immagine principale del prodotto
        $errore = $_FILES['immagine']['error'];
        $nome = $_FILES['immagine']['name'];
        $temp = $_FILES['immagine']['tmp_name'];
    }else{
        // Caso in cui viene gestita un'immagine proveniente dalla galleria
        $errore = $_FILES['immagini']['error'][$indice];
        $nome = $_FILES['immagini']['name'][$indice];
        $temp = $_FILES['immagini']['tmp_name'][$indice];
    }
    // Se le campo 'error' viene trovato il seguente flag, si può procedere con la copia dell'immagine
    if ($errore == UPLOAD_ERR_OK) {
        copy($temp, HOME_ROOT . '/' . 'img' . '/'.$galleria.'/' . $nome);
        $messaggio = "L'immagine &egrave; stata caricata senza problemi";
        print '<p class="successo">' . $nome . ' - Esito : ' . $messaggio . "</p>";
        return true;
    } else {
        // Se viene individuato qualsiasi altro flag, c'è stato un problema
        switch ($errore) {
            case UPLOAD_ERR_INI_SIZE:
                $messaggio = "L'immagine caricata &egrave; troppo grande rispetto alla direttiva specificata in php.ini";
                break;
            case UPLOAD_ERR_FORM_SIZE:
                $messaggio = "L'immagine caricata &egrave; troppo grande rispetto alla direttiva specificata nel form html";
                break;
            case UPLOAD_ERR_PARTIAL:
                $messaggio = "L'immagine &egrave; stata caricata parzialmente";
                break;
            case UPLOAD_ERR_NO_FILE:
                $messaggio = "L'immagine non &egrave; stata caricata";
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $messaggio = "Immagine temporanea mancante";
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $messaggio = "Impossibile scrivere l'immagine su disco";
                break;
            case UPLOAD_ERR_EXTENSION:
                $messaggio = "Caricamento dell'immagine bloccato da un estensione";
                break;
        }
        // L'errore viene infine stampato a schermo
        print '<p class="errore">' . $nome . ' - Esito : ' . strtoupper($messaggio) . "</p>";
        return false;
    }
}

function generaThumbnail($imSorgente, $percorso, $larghezza, $altezza, $indice){
    if(!is_dir(HOME_ROOT .$percorso)){
        mkdir(HOME_ROOT .$percorso);
    }
    // Assegna alle variabili specificate le dimensioni dell'immagine sorgente.
    list($larghezzaOriginale, $altezzaOriginale) = getimagesize($imSorgente);
    // Genera un' immagine nera con le dimensioni specificate
    $thumb = imagecreatetruecolor($larghezza, $altezza);
    // Caso in cui viene gestita l'immagine principale del prodotto
    if($indice == -1){
        $tipo = $_FILES['immagine']['type'];
    }else{
        // Caso in cui viene gestita un'immagine proveniente dalla galleria
        $tipo = $_FILES['immagini']['type'][$indice];
    }
    // In base al tipo dell'immagine sorgente viene generata una nuova immagine
    switch($tipo) {
        case "image/jpeg":
        $immagine = @imagecreatefromjpeg($imSorgente);
        break;
        case "image/gif":
        $immagine = @imagecreatefromgif($imSorgente);
        break;
        case "image/png":
        $immagine = @imagecreatefrompng($imSorgente);
        break;
    }
    /* L'immagine generata dall' imaagine sorgente viene copiata interamente nell' immagine nera generata
    precedentemente e viene ridimensionata secondo le dimensioni specificate*/
    imagecopyresized($thumb, $immagine, 0, 0, 0, 0, $larghezza, $altezza, $larghezzaOriginale, $altezzaOriginale);
    // L'immagine che costituirà la thumbnail viene creata nel percorso specificato.
    if($indice == -1){
        imagejpeg($thumb,HOME_ROOT.$percorso.'/'. $_FILES['immagine']['name']);
    } else {
        imagejpeg($thumb,HOME_ROOT.$percorso.'/'. $_FILES['immagini']['name'][$indice]);
    }
    // Viene liberata la memoria
    imagedestroy($thumb);
    imagedestroy($immagine);
}

function visualizzaGalleria($galleria,$nomeClasse) {
    $percorsoThumbnails = '../img/thumb/'.$galleria.'/';
    $percorsoGalleria = '../img/'.$galleria.'/';
    // La funzione glob cerca tutte le immagini presenti nel percorso specificato.
    $thumbnails = glob($percorsoThumbnails . "*");
    foreach ($thumbnails as $thumb) {
        print '<li><a href="'.$percorsoGalleria.basename($thumb).'" rel="gallery" class="pirobox_gall'.$nomeClasse.'">
                <img width="100" alt="Non trovata" height="58" src="'.$thumb.'"></a></li>';
    }
}

function ricercaProdotto($nomeCercato,$destinazione) {

    // La funzione di ricerca viene utilizzata nelle fasi di modifica e cancellazione per individuare l'oggetto dell'operazione
    $connessione = creaConnessione(SERVER,UTENTE,PASSWORD,DATABASE);
    print '<p class="informazione">Sono stati individuati i seguenti risultati potenziali</p>';
    $query = "SELECT * FROM tblprodotti AS p LEFT JOIN tblprodotticonsole AS pc ON p.codiceprodotto = pc.codiceprodotto WHERE nomeprodotto LIKE '%".$nomeCercato."%' ORDER BY nomeprodotto ASC";
    $dati = eseguiQuery($connessione,$query);
    $contantoreRisultati = 0;

    foreach($dati as $riga ){
        /* Ogni risultato parziale costituisce un form a se stante; per ognuno di essi, viene assegnato un id univoco e
         e viene fatto il print della chiamata alla funzione jQuery che li invia in modo asincrono.
        la chiave primaria della tupla viene  utilizzata come id, e i campi necessari sono passati come controlli input nascosti.*/
        print '<form id="' . trim($riga['codiceprodotto']) . '" method="post" action="' . trim($destinazione) . '">';
        print '<input type="hidden" name="codiceprodotto" value="'.$riga['codiceprodotto'].'"/>';
        print '<input type="hidden" name="console" value="'.$riga['console'].'"/>';
        print '<div class="label"><label>'.$riga['nomeprodotto'].' - '.$riga['console'].'</label></div>';
        print '<input type="submit" value="Seleziona"/>';
        print '</form>';
        print '<br />';
        $contantoreRisultati++;
        // Chiamata alla funzione
        print '<script type="text/javascript">';
        print "gestisciForm('" . "#" . trim($riga['codiceprodotto']) . "','" . trim($destinazione) . "','#coldx');";
        print '</script>';
    }

    chiudiConnessione($connessione);

    if($contantoreRisultati == 0){
        print '<p class="errore">La ricerca non ha prodotto alcun risultato.</p>';
    }
}

function ricercaCategoria($nomeCercato, $destinazione){

    // La funzione di ricerca viene utilizzata nelle fasi di modifica e cancellazione per individuare l'oggetto dell'operazione
    $connessione = creaConnessione(SERVER,UTENTE,PASSWORD,DATABASE);
    $query = "SELECT * FROM tblcategorie WHERE nome LIKE '%".$nomeCercato."%'"."ORDER BY nome ASC";
    $dati = eseguiQuery($connessione,$query);
    $contantoreRisultati = 0;
    print '<p class="informazione">Sono stati individuati i seguenti risultati potenziali</p>';

    foreach($dati as $riga ){
        /* La funzione è analoga a quella impiegata nella ricerca del prodotto, con una variante;
        poichè la chiave primaria della tabella delle categoria è il nome, può contenere degli spazi,
        perciò viene usata un'espressione regolare per rimuovere gli spazi indesiderati e ottenere
        un id adeguato per ciascun form */
        $idCorretto = preg_replace('/\s+/', 'A', $riga['nome']);
        print '<form id="'.$idCorretto.'" method="post" action="' . trim($destinazione) . '">';
        print '<div class="label"><label>'.$riga['nome'].'</label></div>';
        print '<input type="hidden" name="nome" value="'.$riga['nome'].'">';
        print '<input type="submit" value="Seleziona"/>';
        print '</form>';
        print '<br />';
        $contantoreRisultati++;
        // Chiamata alla funzione dove il primo parametro è l'id corretto
        print '<script type="text/javascript">';
        print "gestisciForm('"."#".$idCorretto."','".trim($destinazione) . "','#coldx');";
        print '</script>';
    }

    chiudiConnessione($connessione);

    if($contantoreRisultati == 0){
        print '<p class="errore">La ricerca non ha prodotto alcun risultato.</p>';
    }
}

function ricercaConsole($nomeCercato, $destinazione){

    // La funzione di ricerca viene utilizzata nelle fasi di modifica e cancellazione per individuare l'oggetto dell'operazione
    $connessione = creaConnessione(SERVER,UTENTE,PASSWORD,DATABASE);
    $query = "SELECT * FROM tblconsole WHERE nome LIKE '%".$nomeCercato."%'"."ORDER BY nome ASC";
    $dati = eseguiQuery($connessione,$query);
    $contantoreRisultati = 0;

    print '<p class="informazione">Sono stati individuati i seguenti risultati potenziali</p>';
    foreach($dati as $riga ){
        // Il funzionamento è analogo a quello della ricerca della categoria
        $idCorretto = preg_replace('/\s+/', 'A', $riga['nome']);
        print '<form id="' . $idCorretto . '" method="post" action="' . trim($destinazione) . '">';
        print '<input type="hidden" name="nome" value="'.$riga['nome'].'">';
        print '<div class="label"><label>'.$riga['nome'].'</label></div>';
        print '<input type="submit" value="Seleziona"/>';
        print '</form>';
        print '<br />';
        $contantoreRisultati++;
        print '<script type="text/javascript">';
        print "gestisciForm('" . "#" . $idCorretto . "','" . trim($destinazione) . "','#coldx');";
        print '</script>';
    }

    chiudiConnessione($connessione);

    if($contantoreRisultati == 0){
        print '<p class="errore">La ricerca non ha prodotto alcun risultato.</p>';
    }
}

function ricercaUtente($utenteCercato, $destinazione){

    // La funzione stampa direttamente tutti gli utenti registrati
    $connessione = creaConnessione(SERVER,UTENTE,PASSWORD,DATABASE);
    $query = "SELECT user, dirittoAmministratore FROM tblutenti WHERE user != '".$_SESSION['username']."' AND user LIKE '%".$utenteCercato."%'"." ORDER BY user ASC";
    $dati = eseguiQuery($connessione,$query);
    $contantoreRisultati = 0;

    // Il codice seguente stampa il controllo che da la possibilità di modificare i privilegi amministrativi degli utenti registrati
    print '<p class="informazione">Sono stati individuati i seguenti risultati potenziali</p><br />';
    foreach($dati as $riga ){
        $contantoreRisultati++;
        print '<form method="post" action="' . trim($destinazione) . '">';
        print '<div class="label"><label>'.$riga['user'].'</label></div>';
        print '<input type="hidden" name="user[]" value="'.$riga['user'].'">';
        print '<select name="dirittoAmministratore[]">';
        if($riga['dirittoAmministratore'] == 'si'){
            print '<option selected>si</option>';
            print '<option>no</option>';
        }else{
            print '<option>si</option>';
            print '<option selected>no</option>';
        }
        print '</select>';
        print '<label> diritto da amministratore';
        print '<br /><br />';
    }
    print '<input type="submit" value="Conferma"/>';
    print '</form>';
    print '<script type="text/javascript">';
    print "gestisciForm('" . "#" . trim($riga['user']) . "','" . trim($destinazione) . "','#coldx');";
    print '</script>';

    chiudiConnessione($connessione);

    if($contantoreRisultati == 0){
        print '<p class="errore">La ricerca non ha prodotto alcun risultato.</p>';
    }
}

function stampaModuloRicerca($destinazione, $nome){
    /* La funzione viene chiamata prima di ogni operazione di modifica o eliminazione per individuare l'oggetto
    dell'operazione stessa */
    print '<form id="formRicerca" method="post" action="' . trim($destinazione) . '">';
    print '<fieldset><legend>Ricerca '.$nome.'</legend>';
    print '<div class="label"><label >Nome</label></div>';
    print '<input type="text" name="nome" class="obbligatorio">';
    print '<input type="submit" value="Cerca" >';
    print '</fieldset>';
    print "</form>";
    print '<script type="text/javascript">';
    print "gestisciForm('#formRicerca','" . trim($destinazione) . "','#coldx');";
    print '</script>';
}

function cancellaCartella($cartella) {
    // La funzione viene chiamata quando viene eliminato un prodotto
    if (is_dir($cartella)) {
        // Vengono individuati tutti i file della cartella
        $files = scandir($cartella);
        /* Ogni file viene eliminato. Il ciclo parte da due poichè le prime due posizioni del vettore sono occupate da
        elementi di navigazione per entrare in eventuali sotto cartelle */
        for ($i = 2; $i < count($files); $i++) {
            unlink($cartella . '/' . $files[$i]);
        }
        // Una volta eliminati i file, viene eliminata la cartella
        rmdir($cartella);
        return true;
    } else {
        return false;
    }
}

function cancellaImmagine($immagine) {
    if (file_exists($immagine)) {
        unlink($immagine);
        return true;
    } else {
        return false;
    }
}

function visualizzaPaginazione($pagina,$numeroPagine,$destinazione){
    /* La funzione viene chiamata quando viene stampato un numero elevato di elementi e si rende quindi
    necessaria la paginazione degli stessi. I successivi controlli servono a stampare i numeri di pagina nel
    formato desiderato. Se la pagina corrente ha uno scarto maggiore di uno dall'inizio o dalla fine, vengono
    aggiunti dei puntini, oltre al link alla pagina precedente e a quella successiva

    Esempio: 1 ... 4 5 6 ... 9 */

    if($numeroPagine > 1){
        print '<p class="numeriPagine">Pagine: ';
        if($pagina == 1) {
            if(($pagina + 1) != $numeroPagine){
                print '<b>'.($pagina).' </b><a href="moduloVisualizzazione'.$destinazione.'.php?pagina=' . ($pagina + 1) . '">'.($pagina+1).'</a>';
            } else {
                print '<b>'.($pagina).' </b>';
            }
            if($numeroPagine-$pagina>2){
                print' ... '.'<a href="moduloVisualizzazione'.$destinazione.'.php?pagina='.$numeroPagine.'">'.$numeroPagine.'</a>';
            }else{
                print'<a href="moduloVisualizzazione'.$destinazione.'.php?pagina='.$numeroPagine.'"> '.$numeroPagine.'</a>';
            }
        } elseif ($pagina == $numeroPagine) {
            if($numeroPagine-1>2){
                print '<a href="moduloVisualizzazione'.$destinazione.'.php?pagina=1">1</a> ... ';
            }else{
                print '<a href="moduloVisualizzazione'.$destinazione.'.php?pagina=1">1 </a>';
            }
            if(($pagina - 1) != 1){
                print '<a href="moduloVisualizzazione'.$destinazione.'.php?pagina=' . ($pagina - 1) . '">'.($pagina-1).'</a>'.'<b> '.($pagina).'</b>';
            }else{
                print '<b> '.($pagina).'</b>';
            }
        } else {
            print '<a href="moduloVisualizzazione'.$destinazione.'.php?pagina=1">1 </a>';
            if($pagina-1>2){
                print ' ... ';
            }
            if($pagina - 1 != 1){
                print '<a href="moduloVisualizzazione'.$destinazione.'.php?pagina=' . ($pagina - 1) . '">'.($pagina - 1).' </a>';
            }
            print '<b>'.$pagina.'</b>';
            if($pagina + 1 != $numeroPagine){
                print '<a href="moduloVisualizzazione'.$destinazione.'.php?pagina=' . ($pagina + 1) . '"> '.($pagina + 1).'</a>';
            }
            if($numeroPagine-$pagina>2){
                print' ...';
            }
            print '<a href="moduloVisualizzazione'.$destinazione.'.php?pagina='.$numeroPagine.'"> '.$numeroPagine.'</a>';
        }
        print '</p>';
    }
}
?>