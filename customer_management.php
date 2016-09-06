<?php

require('config.php');
dol_include_once("/doc2project/lib/report.lib.php");
dol_include_once("/doc2project/filtres.php");
dol_include_once("/asset/class/asset.class.php");
dol_include_once("../comm/propal/class/propal.class.php");
dol_include_once("../compta/facture/class/facture.class.php");
dol_include_once("../projet/class/project.class.php");
dol_include_once("../commande/class/commande.class.php");
dol_include_once("../projet/class/task.class.php");

llxHeader('',$langs->trans('Report'));
print dol_get_fiche_head(reportPrepareHead('Doc2Project') , 'Doc2Project', $langs->trans('Doc2Project'));
print_fiche_titre($langs->trans("Gestion Client"));


$PDOdb=new TPDOdb($db);
//var_dump($_REQUEST);

_fiche($PDOdb);


/*
 * Affiche les différents filtres pour le rapport 
*/
function _get_filtres($PDOdb){
	$form=new TFormCore('auto','formCustManagement', 'POST');
	
	print '<table>';
	_print_filtre_customer_management($PDOdb, $form);
	print '</table>';
}


/*
 * Affiche la légende pour les couleurs du rapport. 
 */
function _print_legende(){

    print_fiche_titre('Legende');
	?>
	<div class="tabBar">
		<table width=70%>
			<tr>
				<td>
					<table>
						<tr>
							<td><b>Factures :</b></td>
						</tr>
						<tr>
							<td>Facture Payée : </td>
							<td bgcolor="#A9F5A9" width=70%></td>
						</tr>
						<tr>
							<td>Facture Impayée : </td>
							<td bgcolor="#F78181" width=70%></td>
						</tr>
					</table>
				</td>
				<td>
					<table>
						<tr>
							<td><b>Taches :</b></td>
						</tr>
						<tr>
							<td>Tache à Programmer : </td>
							<td bgcolor="#AC58FA" width=70%></td>
						</tr>
						<tr>
							<td>Tache Programmée : </td>
							<td bgcolor="#FFFF00" width=70%></td>
						</tr>
						<tr>
							<td>Tache Terminée : </td>
							<td bgcolor="#00BFFF" width=70%></td>
						</tr>
					</table>
				</td>
			</tr>
		</table>
	</div>
	<?php	
}
/*
 * Affiche : 
 * le total de chaque prestation du rapport (nombre réalisé, nombre programmé, nombre à programmer et nombre total de prestations)
 * le total des enquetes de satisfaction (total envoyé, nb relance 1, nb relance 2, nb relance 3, total réceptionné et total de rapport envoyé)
 * TODO total des enquetes
*/
function _print_totaux($TPrestations){
	print_fiche_titre('Totaux');
	?>
	<div class="tabBar">
		<table>
			<tbody>
				<tr>
					<td><b>Prestations</b></td>
				</tr>
				<tr>
					<td>nombre réalisées :</td>
					<td>
					<?php
					print $TPrestations['realisees']
					?>
					</td>
				</tr>
				<tr>
					<td>nombre programmées :</td>
					<td>
					<?php
					print $TPrestations['programmees']
					?>
					</td>
				</tr>
				<tr>
					<td>nombre à programmer :</td>
					<td>
					<?php
					print $TPrestations['a_programmer']
					?>
					</td>
				</tr>
				<tr>
					<td>total :</td>
					<td>
					<?php
					print $TPrestations['total']
					?>
					</td>
				</tr>
				<tr>
					<td><b>Enquetes</b></td>
				</tr>
				<tr>
					<td>Envoyées :</td>
					<td></td>
				</tr>
				<tr>
					<td>Relance 1 :</td>
					<td></td>
				</tr>
				<tr>
					<td>Relance 2 :</td>
					<td></td>
				</tr>
				<tr>
					<td>Relance 3 :</td>
					<td></td>
				</tr>
				<tr>
					<td>Rapports envoyés :</td>
					<td></td>
				</tr>
				
			</tbody>			
		</table>
	</div>
	<?php
}
/*
 * Affiche le rapport 
*/
function _fiche(&$PDOdb){
	
	_get_filtres($PDOdb);
	_print_legende();
	_print_rapport($PDOdb);
	//_print_totaux();
	//$TRapport=_get_infos_rapport($PDOdb);
}

function _print_rapport(&$PDOdb){
	global $db;
	
	//var_dump($_REQUEST);	
	?>
	<style type="text/css">
		table#gestion_client td,table#gestion_client th {
			white-space: nowrap;
			border-right: 1px solid #D8D8D8;
		}
	</style>
	
	<div style="padding-bottom: 25px;">
		<table id="gestion_client" class="noborder" width="100%">
			<thead>
				
				<tr style="text-align:left;" class="liste_titre nodrag nodrop">
					<?php
					$TCateg = _select_categ($PDOdb);
					$colspan = count($TCateg) + 8;
					$TCateg = _select_categ($PDOdb,true);
					
					print '<td colspan='.$colspan.'></td>';
					$NbCategTotal = count($TCateg);
					foreach ($TCateg as $categ) {
						print '<td colspan=8>'.$categ['label'].'</td>';						
					}
					?>
				</tr>
				<tr style="text-align:left;" class="liste_titre nodrag nodrop">
					<th class="liste_titre">Tiers</th>
					<th class="liste_titre">Devis</th>
					<?php 
					print_liste_field_titre('Date cloture', $_SERVER["PHP_SELF"], "p.datee", "", $params, "", $sortfield, $sortorder);
					?>
					<th class="liste_titre">Facture</th>
					<th class="liste_titre">Commande</th>
					<th class="liste_titre">Délais</th>
					<th class="liste_titre">Projet</th>
					<?php
					$TCateg = _select_categ($PDOdb);
					foreach ($TCateg as $categ) {
						print '<th class="liste_titre">'.$categ['label'].'</th>';						
					}
					?>
					<td class="liste_titre">commentaires</td>
					<?php
						$TCateg = _select_categ($PDOdb,true);
						_print_titre_categories($TCateg); //ATTRIBUTS A REDEFINIR
					?>
				</tr>
			</thead>
			<tbody>
				<?php
				$nbPropale=0;
				$nbFacture=0;
				$nbCommande=0;
				$nbProjet=0;
				$TInfosPropal = _get_infos_propal_rapport($PDOdb);
				
				$TPrestations=array();
				
				$societe= new Societe($db);
				$propal=new Propal($db);
				$commande = new Commande($db);
				$projet = new Project($db);
				$facture=new Facture($db);
				
				foreach ($TInfosPropal as $K => $infoLine) {
					
					$nbPropale++;
					$nbCommande++;
					$nbProjet++;
					
					$societe->fetch($infoLine['socId']);
					$propal->fetch($infoLine['propId']);
					$commande->fetch($infoLine['commId']);
					
					$Tfactures = _get_factures_from_propale($PDOdb, $propal->id);
					
					$TProjet= _get_projet_from_commande($PDOdb, $commande->id);
					
					
					$projet->fetch($TProjet['projId']);
					//var_dump($infoLine['prop_cloture']);
					
					print '<tr '.(($K % 2) ? 'class="pair"' : 'class="impair"' ).'>';
					print '<td>'.$societe->getNomUrl(1,'').'</td>';
					print '<td>'.$propal->getNomUrl(1,'').'</td>';
					print '<td>'.date("d/m/Y", strtotime($infoLine['prop_cloture'])).'</td>';
					print '<td>';
					foreach ($Tfactures as $lstfacture) {

						$facture->fetch($lstfacture['facid']);
						
						if ($facture->statut==2)
							Print '<div style="background-color:#A9F5A9">'.$facture->getNomUrl(1,'').'</div>';
						else 
							print '<div style="background-color:#F78181">'.$facture->getNomUrl(1,'').'</div>';
						
						$nbFacture++;
					}
					print '</td>';
					print '<td>'.$commande->getNomUrl(1,'').'</td>';
					print '<td>'.$propal->array_options['options_delai_realisation'].'</td>';
					print '<td>'.$projet->getNomUrl(1,'');
					
					$TCateg_task=_get_categ_from_tasks($PDOdb, $projet->id);
					//var_dump($TCateg_task);
					$TCateg = _select_categ($PDOdb);
					foreach ($TCateg as $categ) {
						print '<td>';

						foreach ($TCateg_task as $categ_task) {
							
							if(is_array($categ_task) && $categ_task['catid'] == $categ['rowid']){
								print '<div style="background-color:'.$categ_task['bgColor'].'">'.$categ_task['taskId'].'</div>';
							}
						}
						
						print '</td>';
					}
					$TCateg = _select_categ($PDOdb,true);
					
					$TPrestations["realisees"] += $TCateg_task['total_realise'];
					$TPrestations["programmees"] += $TCateg_task['total_programme'];
					$TPrestations["a_programmer"] += $TCateg_task['total_a_programmer'];
					$TPrestations["total"] += $TCateg_task['total_prestation'];
					
					print '<td>'.$projet->note_private.'</td>';
					_print_infos_categories($PDOdb, $TCateg,$commande->ref);
					print '</tr>';
															
				}
				?>
				<td></td>
			</tbody>
			 <tfoot>
				<tr style="font-weight: bold;">
					<?php
					print '<td>Totaux :</td>';
					print '<td>'.$nbPropale.'</td>';
					print '<td></td>';
					print '<td>'.$nbFacture.'</td>';
					print '<td>'.$nbCommande.'</td>';
					print '<td></td>';
					print '<td>'.$nbProjet.'</td>';
					?>
				</tr>
			</tfoot>
		</table>
	</div>
	<?php
	_print_totaux($TPrestations);
}


/*
 * Recupere les différentes lignes du rapport :
 * société,  propal, commande, et projet
 * Applique les filtres 
 * TODO Filtres à compléter à l'avenir
*/
function _get_infos_propal_rapport($PDOdb){
	
	
	//var_dump($_REQUEST);
	
	$plageReception_deb   =  GETPOST('date_deb_reception');
	$plageReception_fin   = GETPOST('date_fin_reception');
	$plageEssai_deb       = GETPOST('date_deb_essai');
	$plageEssai_fin       = GETPOST('date_fin_essai');
	$plageClotureProp_deb = GETPOST('date_deb_cloture');
	$plageClotureProp_fin = GETPOST('date_fin_cloture');
	$client               = GETPOST('socid');
	$categ                = GETPOST('parent');
	
	//var_dump($plageClotureProp_deb, $plageClotureProp_fin);
	$sql = 'SELECT soc.nom AS soc_name, soc.rowid AS socId, prop.ref AS prop_ref, prop.rowid AS propId, prop.date_cloture AS prop_cloture, co.rowid AS commId
	FROM '.MAIN_DB_PREFIX.'societe soc 
		INNER JOIN '.MAIN_DB_PREFIX.'propal prop ON soc.rowid=prop.fk_soc
		INNER JOIN '.MAIN_DB_PREFIX.'element_element el ON el.fk_source=prop.rowid 
		INNER JOIN '.MAIN_DB_PREFIX.'commande co ON co.rowid=el.fk_target 
		INNER JOIN '.MAIN_DB_PREFIX.'projet proj  ON proj.rowid = co.fk_projet ';
		
	if (!empty($plageEssai_deb) || !empty($plageEssai_fin)){
		$sql .= 'INNER JOIN '.MAIN_DB_PREFIX.'projet_task task ON task.fk_projet=proj.rowid
		INNER JOIN '.MAIN_DB_PREFIX.'projet_task_extrafields ext ON ext.fk_object=task.rowid 
		INNER JOIN '.MAIN_DB_PREFIX.'product prod ON prod.rowid=ext.fk_linked_product 
		INNER JOIN '.MAIN_DB_PREFIX.'categorie_product catp ON catp.fk_product=prod.rowid 
		INNER JOIN '.MAIN_DB_PREFIX.'categorie cat ON (catp.fk_categorie=cat.rowid AND cat.fk_parent=73)';
	}
		
	$sql .= 'WHERE el.targettype="commande" AND el.sourcetype="propal" ';
	
	if(!empty($plageClotureProp_deb)){
		$plageClotureProp_deb = date("Y-m-d", strtotime(str_replace('/', '-', $plageClotureProp_deb)));
		$sql.=' AND prop.date_cloture >= "'.$plageClotureProp_deb.'" ';
	}
	if (!empty($plageClotureProp_fin)){
		$plageClotureProp_fin = date("Y-m-d", strtotime(str_replace('/', '-', $plageClotureProp_fin)));
		$sql.=' AND prop.date_cloture <= "'.$plageClotureProp_fin.'" ';
	}

	//A REMPLIR POUR FILTRE SUR PLAFE RECEPTION ENQUETE DE SATISFACTION
	if (!empty($plageReception_deb)){
		$plageReception_deb   = date("Y-m-d", strtotime(str_replace('/', '-', $plageReception_deb)));
		$sql.='';
	}	
	if(!empty($plageReception_fin)){
		$plageReception_fin   = date("Y-m-d", strtotime(str_replace('/', '-', $plageReception_fin)));
		$sql.='';
	}

	if(GETPOST('etat') && GETPOST('etat') != '-1'){
		$sql.=' AND proj.fk_statut = '.GETPOST('etat');
	}

	// A REMPLIR POUR FILTRE SUR REALISATION DES ESSAIS
	if (!empty($plageEssai_deb)){
		$plageEssai_deb       = date("Y-m-d", strtotime(str_replace('/', '-', $plageEssai_deb)));
		$sql.=' AND task.dateo >= "'.$plageEssai_deb.'" ';
	}
	if (!empty($plageEssai_fin)){
		$plageEssai_fin       = date("Y-m-d", strtotime(str_replace('/', '-', $plageEssai_fin)));
		$sql.=' AND task.dateo <= "'.$plageEssai_fin.'" ';
	}
	
	if (!empty($client)){
		$sql.= ' AND soc.rowid='.$client.' ';
	}
	$sql.= ' GROUP BY prop.rowid 
	ORDER BY co.ref';
	
	//pre($sql, true);exit;
	$PDOdb->Execute($sql);
	$TInfosPropal = array();
	while ($PDOdb->Get_line()) {
		$TInfosPropal[]=array(
						"socId"        => $PDOdb->Get_field('socId'),
						"soc_name"     => $PDOdb->Get_field('soc_name'),
						"propId"       => $PDOdb->Get_field('propId'),
						"prop_ref"     => $PDOdb->Get_field('prop_ref'),
						"prop_cloture" => $PDOdb->Get_field('prop_cloture'),
						"commId"       => $PDOdb->Get_field('commId')       
					);
	}
	//var_dump($TInfosPropal);
	return $TInfosPropal;
	
	
}




/*
 * Recupere toutes les catégories de produits/services existantes
*/
function _select_categ($PDOdb,$withCorpsEpreuve=false){
	$sql = 'SELECT cat.rowid AS rowid, cat.label AS label 
			FROM '.MAIN_DB_PREFIX.'categorie cat';
	
	if($withCorpsEpreuve)
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'categories_extrafields extracat ON (extracat.fk_object = cat.rowid)';
	
	$sql .= ' WHERE cat.fk_parent=73';
	
	if($withCorpsEpreuve)
		$sql .= ' AND extracat.corp_epreuve_categorie = 1';
	
	//echo $sql;
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
 * Affiche les titres des différentes catégories de service contenues dans un devis/projet
*/
function _print_titre_categories($TReport){
	
	foreach ($TReport as $categ) {
		print '<th class="liste_titre">N° Corps d\'épreuve ES</th>';
		print '<th class="liste_titre">N° de rapport</th>';
		print '<th class="liste_titre">Envoi rapport</th>';
		print '<th class="liste_titre">Délai rapport</th>';
		print '<th class="liste_titre">Envoi ES</th>';
		print '<th class="liste_titre">Relance 1 ES</th>';
		print '<th class="liste_titre">Relance 2 ES</th>';
		print '<th class="liste_titre">Reception ES</th>';
	}
}

/*
 * Affiche les infos des catégories 
 * TODO remplir chaque td avec les infos correspondantes 
*/
function _print_infos_categories($PDOdb, $TReport,$refcommande){
	
	foreach ($TReport as $categ){
		
		$TAsset=_get_equipement($PDOdb, $categ['rowid'],$refcommande);
		
		print '<td><div>';
		foreach ($TAsset as $TVal) {
			print '<div>';
			print $TVal['serial_number'];
			print '</div>';
		}
		print '</div></td>';
		print '<td><div>';
		foreach ($TAsset as $TVal) {
			print '<div>';
			print $TVal['lot_number'];
			print '</div>';
		}
		print '</div></td>';
		print '<td></td>';
		print '<td></td>';
		print '<td></td>';
		print '<td></td>';
		print '<td></td>';
		print '<td></td>';
		
	}
	
}

/*
 * Recupere les factures associées à une propal 
*/
function _get_factures_from_propale($PDOdb, $id){
	
	$sql= 'SELECT fac.rowid AS facid, fac.facnumber AS facref FROM '.MAIN_DB_PREFIX.'facture fac 
	INNER JOIN '.MAIN_DB_PREFIX.'element_element el ON fac.rowid=el.fk_target 
	WHERE el.sourcetype= "propal" AND el.targettype= "facture" AND el.fk_source='.$id.' ';
	
	//var_dump($sql);
	$PDOdb->Execute($sql);
	$TFactures = array();
	while ($PDOdb->Get_line()) {
		$TFactures[]=array(
						"facid"      => $PDOdb->Get_field('facid'),
						"facref"     => $PDOdb->Get_field('facref')				
					);
	}
	//var_dump($TFactures);
	return $TFactures;
	
}

/*
 * Recupere le projet associé à une commande 
*/
function _get_projet_from_commande($PDOdb, $id){
	
	$sql='SELECT com.rowid AS comId, proj.rowid AS projId, proj.note_private AS projNote FROM '.MAIN_DB_PREFIX.'commande com 
	INNER JOIN '.MAIN_DB_PREFIX.'projet proj ON com.fk_projet=proj.rowid
	WHERE com.rowid='.$id.' ';
	
	//var_dump($sql);
	$PDOdb->Execute($sql);
	$TProjet = array();
	while ($PDOdb->Get_line()) {
		$TProjet=array(
						"comId"      => $PDOdb->Get_field('comId'),
						"projId"      => $PDOdb->Get_field('projId')  	
					);
	}

	return $TProjet;
}


/*
 * Recupere la catégorie associée au produit/service d'une tache 
*/
function _get_categ_from_tasks($PDOdb, $idProjet){
	global $db;
	
	$sql='SELECT task.rowid AS taskId, cat.rowid AS catid, cat.label catLabel
	FROM '.MAIN_DB_PREFIX.'projet proj
	INNER JOIN '.MAIN_DB_PREFIX.'projet_task task ON task.fk_projet=proj.rowid 
	INNER JOIN '.MAIN_DB_PREFIX.'projet_task_extrafields ext ON ext.fk_object=task.rowid 
	INNER JOIN '.MAIN_DB_PREFIX.'product prod ON prod.rowid=ext.fk_linked_product 
	INNER JOIN '.MAIN_DB_PREFIX.'categorie_product catp ON catp.fk_product=prod.rowid 
	INNER JOIN '.MAIN_DB_PREFIX.'categorie cat ON catp.fk_categorie=cat.rowid 
	WHERE cat.fk_parent=73 AND proj.rowid='.$idProjet.' ';
	
	//pre($sql,true);
	$PDOdb->Execute($sql);
	$TCateg_task = array();
	$task = new Task($db);
	
	while ($PDOdb->Get_line()) {
		
		$task->fetch($PDOdb->Get_field('taskId'));
		$TCateg_task['total_prestation'] ++;
		
		if ($task->date_start==null){
			$TCateg_task['total_a_programmer'] ++;
			$bgColor = '#9A2EFE';
		}elseif ($task->date_start!=null && $task->date_end >= date("Y-m-d") && $task->progress!=100){
			$TCateg_task['total_programme'] ++;
			$bgColor = '#FFFF00';
		}elseif($task->progress==100) {
			$TCateg_task['total_realise'] ++;
			$bgColor = '#00BFFF';
		}

		$TCateg_task[]=array(
						"catid"         => $PDOdb->Get_field('catid'),
						"catLabel"      => $PDOdb->Get_field('catLabel'),
						"taskId"  	    => $task->getNomUrl(1,''),
						"bgColor"		=> $bgColor
					);
	}
	return $TCateg_task;
}

function _get_equipement($PDOdb, $idCateg, $refcommande){
	
	$sql = 'SELECT ass.rowid as assId,ass.lot_number AS lot_number, ass.serial_number AS serial_number
			FROM '.MAIN_DB_PREFIX.'asset ass
				INNER JOIN '.MAIN_DB_PREFIX.'product prod ON (prod.rowid=ass.fk_product) 
				INNER JOIN '.MAIN_DB_PREFIX.'categorie_product cat ON (cat.fk_product=prod.rowid )
				INNER JOIN '.MAIN_DB_PREFIX.'commande as co ON (co.rowid = ass.fk_commande) 
			WHERE cat.fk_categorie='.$idCateg.' AND co.ref = "'.$refcommande.'"';
	//if($refcommande == 'CO1602-0037'){ echo $sql; exit;}
	//var_dump($sql);
	$PDOdb->Execute($sql);
	$TAsset = array();
	$asset = new TAsset;
	
	$TRes = $PDOdb->Get_All();
	
	foreach($TRes as $Res){

		$asset->load($PDOdb,$Res->assId);
		$TAsset[]=array(
						"assId"           => $Res->assId,
						"lot_number"      => $Res->lot_number,
						"serial_number"   => $asset->getNomUrl()//$Res->serial_number
					);
	}
	
	return $TAsset;
	
}



llxFooter();
