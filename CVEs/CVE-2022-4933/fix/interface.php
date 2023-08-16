<?php

	if (! defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL','1'); // Disables token renewal

    require("../config.php");
    dol_include_once('/product/class/product.class.php');
    dol_include_once('/fourn/class/fournisseur.product.class.php');

    $get = GETPOST('get');
    $put = GETPOST('put');

    $id_prod = (int)GETPOST('idprod','int');                  // id du produit demandé
    $ref_search= GETPOST('ref_search', 'alpha');        // ref du produit demandé
    $ref = GETPOST('ref', 'alpha');                     // ref entrée par l'utilisateur
    $fk_soc = GETPOST('fk_supplier', 'int');            // id du fournisseur
    $price = price2num(GETPOST('price','int') );                   // prix entré par l'utilisateur
    $qte = (int)GETPOST('qty','int');                         // quantité demandée
    $unitprice = ($qte > 1) ? $price/$qte : $price;     // prix unitaire
    $tvatx = GETPOST('tvatx', 'alpha');                     		// taux de tva saisi
    $fk_order = (int)GETPOST('fk_order','int');               // id de la commande en cours de modification

    // si la ref est laissée vide je rempli la ref (ne pas utiliser pour l'instant)
    // if($ref == '') $ref = 'FP-'.$fk_soc.'-'.$id_prod.'-'.$price;

    switch($put){
        case 'updateprice': // renvoie l'id d'une ligne produit
        	upatePrice($id_prod, $fk_soc, $unitprice, $qte, $ref_search, $price, $ref, $tvatx);
            break;

        case 'checkprice': // vérifie s'il y a des prix unitaire strictement inférieurs et on en renvoie le nombre
        	checkprice($id_prod, $unitprice, $fk_order, $qte, $price, $fk_soc, $tvatx);
            break;

        Default:
            break;

    }

    /**
     * Vérifie s'il existe des prix plus bas que celui saisi et en renvoie le nombre et la liste
     *
     * @param $id_prod        id du produit
     * @param $unitprice      prix unitaire
     * @param $fk_order       id de la commande en cours
     * @param $qte            quantité commandée
     * @param $price          total HT
     * @param $fk_soc         id du fournisseur courant
     * @param $tvatx          tva tx supplier
     *
     */
    function checkprice($id_prod, $unitprice, $fk_order, $qte, $price, $fk_soc, $tvatx){
        global $db, $langs;
        $langs->load('quicksupplierprice@quicksupplierprice');

        ob_start();
        $sql = 'SELECT pfp.rowid as product_fourn_price_id, pfp.unitprice as fourn_unitprice, pfp.quantity as fourn_qty, pfp.price as fourn_price, s.nom as fourn_name, s.rowid as fourn_id';
        $sql .= ' FROM ' . MAIN_DB_PREFIX .'product_fournisseur_price as pfp , ' . MAIN_DB_PREFIX .'societe as s';
        $sql .= ' WHERE pfp.entity = 1';
        $sql .= ' AND pfp.fk_soc = s.rowid';
        $sql .= ' AND s.status=1';
        $sql .= ' AND pfp.fk_product = '.$id_prod.' AND pfp.unitprice < '.$unitprice;

        $res = $db->query($sql);
        $nb = $db->num_rows($res);

        $liste = '';
        if($nb > 0){ // s'il existe des prix plus bas
            // on génère la liste des prix inférieurs au prix demandé
            $liste = '<form action="'.dol_buildpath('/fourn/commande/card.php', 1).'?id='. $fk_order .'" method="POST">'."\n";
            $liste .= '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
            $liste .= '<input type="hidden" name="action" value="selectpriceQSP">';
            $liste .= '<input type="hidden" name="qty" value="'.$qte.'">';
            $liste .= '<input type="hidden" name="tvatx" value="'.$tvatx.'">';
            $liste .= '<div><p>Plusieurs prix sont disponibles pour ce produit veuillez valider le prix saisie ou choisir le produit dans la liste</p></div>';
            $liste .= '<div class="div-table-responsive"><table class="noborder noshadow" width="100%"><thead>';
            $liste .= '<tr class="liste_titre"><td>Fournisseur</td><td align="right">P.U. HT</td><td align="right">Quantité minimum</td><td align="right">Total HT</td><td width="20%" align="right" style="padding-right: 20px;">Choix</td></tr>';
            $liste .= '</thead><tbody>';

            $liste .= '<tr><td><label for="saisie">'.$langs->trans('validPrice').' '.$langs->trans('AddedToThis').'</label></td>';
            $liste .= '<td align="right"><label for="saisie">' . number_format($unitprice, 2) . '</label></td>';
            $liste .= '<td align="right"><label for="saisie">' . $qte . '</label></td>';
            $liste .= '<td align="right"><label for="saisie">' . number_format($price, 2) . '</label></td>';
            $liste .= '<td align="right" style="padding-right: 20px;"><input id="saisie" type="radio" name="prix" value="saisie" checked></td></tr>';

            while($obj = $db->fetch_object($res)){
                $tooltip = ($fk_soc == $obj->fourn_id) ? $langs->trans('AddedToThis') : $langs->trans('NewCommand');
                $liste .= '<tr><td><label for="sel_'.$obj->product_fourn_price_id.'">'. $obj->fourn_name .' '.$tooltip.'</label></td>';
                $liste .= '<td align="right"><label for="sel_'.$obj->product_fourn_price_id.'">' . number_format($obj->fourn_unitprice, 2) . '</label></td>';
                $liste .= '<td align="right"><label for="sel_'.$obj->product_fourn_price_id.'">' . $obj->fourn_qty . '</label></td>';
                $liste .= '<td align="right"><label for="sel_'.$obj->product_fourn_price_id.'">' . number_format($obj->fourn_price, 2) . '</label></td>';
                $liste .= '<td align="right" style="padding-right: 20px;"><input id="sel_'.$obj->product_fourn_price_id.'" type="radio" name="prix" value="'.$obj->product_fourn_price_id.'"></td></tr>';
            }

            $liste .= '</tbody></table></div>';

            $liste .= '<div class="center">';
            $liste .= '<input type="submit" class="button" value="'.$langs->trans("Validate").'">';
            $liste .= '</div>';
            $liste .= '</form>';
        }

        ob_clean();
        print json_encode( array('nb' => $nb, 'liste' => $liste));

    }

    /**
     *
     * @param unknown $id_prod
     * @param unknown $fk_soc
     * @param unknown $unitprice
     * @param unknown $qte
     * @param unknown $ref_search
     * @param unknown $price
     * @param unknown $ref
     * @param unknown $tvatx
     */
    function upatePrice($id_prod, $fk_soc, $unitprice, $qte, $ref_search, $price, $ref, $tvatx){
        global $db, $user;

		if ($price === '' || $unitprice === '') {
			print json_encode(array('retour' => 0, 'error' => 'prix non renseigné'));
			return;
		}

        ob_start();

        // Clean vat code
        $vat_src_code = '';
        if (preg_match('/\((.*)\)/', $tvatx, $reg)) {
        	$vat_src_code = $reg[1];
        	$tvatx = preg_replace('/\s*\(.*\)/', '', $tvatx); // Remove code into vatrate.
        }

        // On vérifie si la ligne de tarif n'existe pas déjà pour ce fournisseur
		$sql = 'SELECT rowid FROM ' . MAIN_DB_PREFIX . 'product_fournisseur_price'
			. ' WHERE fk_product=' . intval($id_prod)
			. ' AND fk_soc=' . intval($fk_soc)
			. ' AND unitprice=' . floatval($unitprice)
			. ' AND quantity=' . intval($qte);
        if (!empty($vat_src_code)) {
        	$sql .= ' AND default_vat_code="' . $db->escape($vat_src_code).'"';
        }


        $resq = $db->query($sql);
		if (!$resq) {
			print json_encode(array('retour' => 0, 'error' => $db->lasterror()));
			return;
		}

        if($resq->num_rows !== 0){ // s'il existe, on renvoie l'id de cet ligne prix
            $obj = $db->fetch_object($resq);
            $ret = $obj->rowid;
        } else { // si on ne trouve rien, création du prix fournisseur
            $product = new ProductFournisseur($db);
            $product->fetch($id_prod, $ref_search);

            $fourn = new Fournisseur($db);
            $fourn->fetch($fk_soc);

             $product->product_fourn_id = $fourn->id;

            // La methode update_buyprice() renvoie -1 ou -2 en cas d'erreur ou l'id de l'objet modifié ou créé en cas de réussite
             $ret=$product->update_buyprice($qte , $price, $user, 'HT', $fourn, 1, $ref, $tvatx, 0, 0, 0, 0, 0, '', array(), $vat_src_code, $price);
        }

        ob_clean();

        if($ret<0) print json_encode( array('retour'=>$ret,'error'=> $product->error) );
        else {
            print json_encode(  array('retour'=> $ret, 'error'=>'', 'dp_desc'=>$product->description ) );
        }
    }

