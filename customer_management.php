<?php

require('config.php');
dol_include_once("/doc2project/lib/report.lib.php");
dol_include_once("/doc2project/filtres.php");
dol_include_once("../comm/propal/class/propal.class.php");
dol_include_once("../compta/facture/class/facture.class.php");

llxHeader('',$langs->trans('Report'));
print dol_get_fiche_head(reportPrepareHead('Doc2Project') , 'Doc2Project', $langs->trans('Doc2Project'));
print_fiche_titre($langs->trans("Gestion Client"));


$PDOdb=new TPDOdb($db);

_get_filtres();
_fiche($PDOdb);


//function _fiche









//affiche le tableau des filtres
function _get_filtres(){
	$form=new TFormCore('auto','formCustManagement', 'POST');
	
	print '<table>';
	_print_filtre_customer_management($form);
	print '</table>';
}

//function _fiche
function _fiche(&$PDOdb){
	
	_print_rapport($PDOdb);
	//$TRapport=_get_infos_rapport($PDOdb);
}

function _print_rapport(&$PDOdb){
	global $db;
	
	//var_dump($_REQUEST);	
	?>
	<div style="padding-bottom: 25px;">
		<table id="gestion_client" class="noborder" width="100%">
			<thead>
				<!--
				<tr style="text-align:left;" class="liste_titre nodrag nodrop">
					<?php
					$TCateg = _select_categ($PDOdb);
					$colspan = count($TCateg) + 6;
					
					print '<td colspan='.$colspan.'></td>';
					
					foreach ($TCateg as $categ) {
						print '<td colspan=7>'.$categ['label'].'</td>';						
					}
					?>
				</tr>-->
				<tr style="text-align:left;" class="liste_titre nodrag nodrop">
					<th class="liste_titre">Tiers</th>
					<th class="liste_titre">Devis</th>
					<?php 
					print_liste_field_titre('Date cloture', $_SERVER["PHP_SELF"], "p.datee", "", $params, "", $sortfield, $sortorder);
					?>
					<th class="liste_titre">Facture</th>
					<th class="liste_titre">Délais</th>
					<?php
					
					foreach ($TCateg as $categ) {
						print '<th class="liste_titre">'.$categ['label'].'</th>';						
					}
					?>
					<td class="liste_titre">commentaires</td>
					<?php
						//_print_titre_categories($idCategorie, $TCateg); //ATTRIBUTS A REDEFINIR						
					?>
				</tr>
			</thead>
			<!--AJOUTER UN BANDEAU POUR FILTRER SUR LE PROJET, ETC.. -->
			<tbody>
				<?php
				$TInfosPropal = _get_infos_propal_rapport($PDOdb);
				foreach ($TInfosPropal as $infoLine) {

					$societe= new Societe($db);
					$societe->fetch($infoLine['socId']);
					
					$propal=new Propal($db);
					
					$propal->fetch($infoLine['propId']);
					
					$facture=new Facture($db);
					$facture->fetch($infoLine['facId']);
					//var_dump($facture);
					
					
					print '<tr>';
					print '<td>'.$societe->getNomUrl(1,'').'</td>';
					print '<td>'.$propal->getNomUrl(1,'').'</td>';
					print '<td>'.$infoLine['prop_cloture'].'</td>';
					if ($facture->statut==2)print '<td bgcolor="#A9F5A9">'.$facture->getNomUrl(1,'').'</td>';
					else print '<td bgcolor="#F78181">'.$facture->getNomUrl(1,'').'</td>';
					print '<td>'.$infoLine[''].'</td>';
					
					
					foreach ($TCateg as $categ) {
						
					}
					print '<td>'.$infoLine['proj_note'].'</td>';
					print '</tr>';											
				}
				?>
				<td></td>
			</tbody>
			<tfoot>
				<tr style="font-weight: bold;">
					<td>Totaux</td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
					<?php
					foreach ($TCateg as $categ) {
							print '<td>'.' '.'</td>';						
						}
					?>
					<td></td>
				</tr>
			</tfoot>
		</table>
	</div>
	<?php
}

function _get_infos_categs_rapport($PDOdb){
	
	$sql='SELECT ';
	
	$TReport=array();
	
	return $TReport;
}

function _get_infos_propal_rapport($PDOdb){
	
	
	//var_dump($_REQUEST);
	
	$plageReception_deb   =  GETPOST('date_deb_reception');
	$plageReception_fin   = GETPOST('date_fin_reception');
	$plageEssai_deb       = GETPOST('date_deb_essai');
	$plageEssai_fin       = GETPOST('date_fin_essai');
	$plageClotureProp_deb = GETPOST('date_deb_cloture');
	$plageClotureProp_fin = GETPOST('date_fin_cloture');
	$client               = GETPOST('client');
	$categ                = GETPOST('parent');
	
	
	$plageEssai_deb       = date("Y-m-d", strtotime(str_replace('/', '-', $plageEssai_deb)));
	$plageEssai_deb       = date("Y-m-d", strtotime(str_replace('/', '-', $plageEssai_deb)));
	
	
	//var_dump($plageClotureProp_deb, $plageClotureProp_fin);
	$sql = 'SELECT soc.nom AS soc_name, soc.rowid AS socId, prop.ref AS prop_ref, prop.rowid AS propId, prop.date_cloture AS prop_cloture, 
	fact.rowid AS facId, fact.facnumber AS facnumber, fact.fk_statut AS fac_statut, proj.note_private AS proj_note, proj.rowid AS id_project  
	FROM '.MAIN_DB_PREFIX.'societe soc INNER JOIN '.MAIN_DB_PREFIX.'propal prop ON soc.rowid=prop.fk_soc 
	INNER JOIN '.MAIN_DB_PREFIX.'element_element el ON el.fk_source = prop.rowid 
	INNER JOIN '.MAIN_DB_PREFIX.'facture fact ON el.fk_target = fact.rowid 
	LEFT JOIN '.MAIN_DB_PREFIX.'projet proj ON fact.fk_projet=proj.rowid 
	WHERE 1 ';
	
	if (!empty($plageClotureProp_fin) && !empty($plageClotureProp_deb)){
		$plageClotureProp_deb = date("Y-m-d", strtotime(str_replace('/', '-', $plageClotureProp_deb)));
		$plageClotureProp_fin = date("Y-m-d", strtotime(str_replace('/', '-', $plageClotureProp_fin)));
		
		$sql.='AND prop.date_cloture BETWEEN ()"'.$plageClotureProp_deb.'" AND "'.$plageClotureProp_fin.'") ';
	}

	if (!empty($plageReception_deb) && !empty($plageReception_fin)){
		$plageReception_deb   = date("Y-m-d", strtotime(str_replace('/', '-', $plageReception_deb)));
		$plageReception_fin   = date("Y-m-d", strtotime(str_replace('/', '-', $plageReception_fin)));
		
		$sql.='';
	}
	if (!empty($plageEssai_deb) && !empty($plageEssai_fin)){
		$plageEssai_deb       = date("Y-m-d", strtotime(str_replace('/', '-', $plageEssai_deb)));
		$plageEssai_deb       = date("Y-m-d", strtotime(str_replace('/', '-', $plageEssai_deb)));
	
		$sql.='';
	}
	if (!empty($client)){
		$sql.= 'AND soc.rowid='.$client.' ';
	}
	//$sql.= 'ORDER BY soc.nom';
	
	//pre($sql, TRUE);
	$PDOdb->Execute($sql);
	$TInfosPropal = array();
	while ($PDOdb->Get_line()) {
		$TInfosPropal[]=array(
						"socId"        => $PDOdb->Get_field('socId'),
						"soc_name"     => $PDOdb->Get_field('soc_name'),
						"propId"       => $PDOdb->Get_field('propId'),
						"prop_ref"     => $PDOdb->Get_field('prop_ref'),
						"prop_cloture" => $PDOdb->Get_field('prop_cloture'),
						"facId"        => $PDOdb->Get_field('facId'),
						"facnumber"    => $PDOdb->Get_field('facnumber'),
						"fac_statut"   => $PDOdb->Get_field('fac_statut'),
						"proj_note"    => $PDOdb->Get_field('proj_note'),	
						"id_project"   => $PDOdb->Get_field('id_project')					
					);
	}
	//var_dump($TInfosPropal);
	return $TInfosPropal;
	
	
}

/*
 *Fonction qui va aller chercher les catégories de service dans un devis (et par la meme un projet)
 * 
*/
function _select_categ($PDOdb){
	$sql = 'SELECT cat.rowid AS rowid, cat.label AS label FROM '.MAIN_DB_PREFIX.'categorie cat WHERE cat.fk_parent=73';
	
	$PDOdb->Execute($sql);
	$TCategs = array();
	while ($PDOdb->Get_line()) {
		$TCategs[]= array(
					"rowid" 		=> $PDOdb->Get_field('rowid'),
					"label" 		=> $PDOdb->Get_field('label')
				);
		
		
	}
	return $TCategs;
}

/*
 * Fonction qui va afficher les titres des différentes catégories de service contenues dans un devis/projet
 */
function _print_titre_categories($idCategorie, $TReport){
	
	foreach ($TReport as $categ) {
		print '<th class="liste_titre">N° de rapport</th>';
		print '<th class="liste_titre">Envoi rapport</th>';
		print '<th class="liste_titre">Délai rapport</th>';
		print '<th class="liste_titre">Envoi ES</th>';
		print '<th class="liste_titre">Relance 1 ES</th>';
		print '<th class="liste_titre">Relance 2 ES</th>';
		print '<th class="liste_titre">Reception ES</th>';
	}
}


function _get_factures_from_propale($PDOdb, $id){
	
	$sql= 'SELECT fac.rowid AS facid, fac.ref AS facref FROM '.MAIN_DB_PREFIX.' INNER JOIN '.MAIN_DB_PREFIX.'element_element 
	WHERE sourcetype= propal AND fk_source='.$id.' ';
	
	//var_dump($sql);
	$PDOdb->Execute($sql);
	$TFactures = array();
	while ($PDOdb->Get_line()) {
		$TFactures[]=array(
						"facid"        => $PDOdb->Get_field('facid'),
						"facref"     => $PDOdb->Get_field('facref')				
					);
	}
	return $TFactures;
	
}
llxFooter();
