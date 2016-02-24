<?php

require('config.php');
dol_include_once("/doc2project/lib/report.lib.php");
dol_include_once("/doc2project/filtres.php");
dol_include_once("../comm/propal/class/propal.class.php");
dol_include_once("../compta/facture/class/facture.class.php");
dol_include_once("../projet/class/project.class.php");
dol_include_once("../commande/class/commande.class.php");
dol_include_once("../projet/class/task.class.php");

llxHeader('',$langs->trans('Report'));
print dol_get_fiche_head(reportPrepareHead('Doc2Project') , 'Doc2Project', $langs->trans('Doc2Project'));
print_fiche_titre($langs->trans("Gestion Client"));


$PDOdb=new TPDOdb($db);

_get_filtres();
_print_legende();
_fiche($PDOdb);
//_print_totaux();











/*
 * Affiche les différents filtres pour le rapport 
*/
function _get_filtres(){
	$form=new TFormCore('auto','formCustManagement', 'POST');
	
	print '<table>';
	_print_filtre_customer_management($form);
	print '</table>';
}


/*
 * Affiche la légende pour les couleurs du rapport. 
 */
function _print_legende(){

    print_fiche_titre('Legende');
	?>
	<div class="tabBar">
		<table>
			<tr>
				<td>Facture Payée : </td>
				<td bgcolor="#A9F5A9" width=70%></td>
			</tr>
			<tr>
				<td>Facture Impayée : </td>
				<td bgcolor="#F78181" width=70%></td>
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
	</div>
	<?php	
}
/*
 * Affiche : 
 * le total de chaque prestation du rapport (nombre réalisé, nombre programmé, et nombre total de prestations)
 * le total des enquetes de satisfaction (total envoyé, nb relance 1, nb relance 2, nb relance 3, total réceptionné et total de rapport envoyé)
*/
function _print_totaux(){
	print_fiche_titre('Totaux');
}
/*
 * Affiche le rapport 
*/
function _fiche(&$PDOdb){
	
	_print_rapport($PDOdb);
	//$TRapport=_get_infos_rapport($PDOdb);
}

function _print_rapport(&$PDOdb){
	global $db;
	
	//var_dump($_REQUEST);	
	?>
	<style type="text/css">
		table#gestion_client td,table#gestion_client th {
			white-space: nowrap;
		}
	</style>
	
	<div style="padding-bottom: 25px;">
		<table id="gestion_client" class="noborder" width="100%">
			<thead>
				
				<tr style="text-align:left;" class="liste_titre nodrag nodrop">
					<?php
					$TCateg = _select_categ($PDOdb);
					$colspan = count($TCateg) + 7;
					
					print '<td colspan='.$colspan.'></td>';
					
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
					foreach ($TCateg as $categ) {
						print '<th class="liste_titre">'.$categ['label'].'</th>';						
					}
					?>
					<td class="liste_titre">commentaires</td>
					<?php
						_print_titre_categories($TCateg); //ATTRIBUTS A REDEFINIR						
					?>
				</tr>
			</thead>
			<tbody>
				<?php
				$TInfosPropal = _get_infos_propal_rapport($PDOdb);
				foreach ($TInfosPropal as $K => $infoLine) {

					$societe= new Societe($db);
					$societe->fetch($infoLine['socId']);
					
					$propal=new Propal($db);
					$propal->fetch($infoLine['propId']);
					
					$commande = new Commande($db);
					$commande->fetch($infoLine['commId']);
					
					$Tfactures = _get_factures_from_propale($PDOdb, $propal->id);
					
					$TProjet= _get_projet_from_commande($PDOdb, $commande->id);
					
					$projet = new Project($db);
					$projet->fetch($TProjet['projId']);
					//var_dump($infoLine['prop_cloture']);
					
					print '<tr '.(($K % 2) ? 'class="pair"' : 'class="impair"' ).'>';
					print '<td>'.$societe->getNomUrl(1,'').'</td>';
					print '<td>'.$propal->getNomUrl(1,'').'</td>';
					print '<td>'.date("d-m-Y", strtotime($infoLine['prop_cloture'])).'</td>';
					print '<td>';
					foreach ($Tfactures as $lstfacture) {
						$facture=new Facture($db);
						$facture->fetch($lstfacture['facid']);
						if ($facture->statut==2)print '<div style="background-color:#A9F5A9">'.$facture->getNomUrl(1,'').'</div>';
						else print '<div style="background-color:#F78181">'.$facture->getNomUrl(1,'').'</div>';
							
						}
					print '</td>';
					print '<td>'.$commande->getNomUrl(1,'').'</td>';
					print '<td>'.$propal->array_options['options_delai_realisation'].'</td>';
					print '<td>'.$projet->getNomUrl(1,'');
						
					$TCateg_task=_get_categ_from_tasks($PDOdb, $projet->id);
					//var_dump($TCateg_task);
					foreach ($TCateg as $categ) {
						print '<td>';
							foreach ($TCateg_task as $categ_task) {
								$task = new Task($db);
								$task->fetch($categ_task['taskId']);
								if ($categ_task['catid']==$categ['rowid']){
									if ($task->date_start==null){
										print '<div style="background-color:#9A2EFE">'.$task->getNomUrl(1,'').'</div>';
									}elseif ($task->date_start!=null && $task->date_end >= date("Y-m-d") && $task->progress!=100){
										
										print '<div style="background-color:#FFFF00">'.$task->getNomUrl(1,'').'</div>';
									}elseif($task->progress==100) {
										print '<div style="background-color:#00BFFF">'.$task->getNomUrl(1,'').'</div>';
									}
								}
							}
						print '</td>';
					}
					print '<td>'.$projet->note_private.'</td>';
					_print_infos_categories($TCateg);
					print '</tr>';
															
				}
				?>
				<td></td>
			</tbody>
			<tfoot>
				<tr></tr>
			</tfoot>
		</table>
	</div>
	<?php
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
	$client               = GETPOST('client');
	$categ                = GETPOST('parent');
	
	
	$plageEssai_deb       = date("Y-m-d", strtotime(str_replace('/', '-', $plageEssai_deb)));
	$plageEssai_deb       = date("Y-m-d", strtotime(str_replace('/', '-', $plageEssai_deb)));
	
	
	//var_dump($plageClotureProp_deb, $plageClotureProp_fin);
	$sql = 'SELECT soc.nom AS soc_name, soc.rowid AS socId, prop.ref AS prop_ref, prop.rowid AS propId, prop.date_cloture AS prop_cloture, co.rowid AS commId
	FROM '.MAIN_DB_PREFIX.'societe soc 
	INNER JOIN '.MAIN_DB_PREFIX.'propal prop ON soc.rowid=prop.fk_soc
	INNER JOIN '.MAIN_DB_PREFIX.'element_element el ON el.fk_source=prop.rowid 
	INNER JOIN '.MAIN_DB_PREFIX.'commande co ON co.rowid=el.fk_target 
	INNER JOIN '.MAIN_DB_PREFIX.'projet proj  ON proj.rowid = co.fk_projet  
	WHERE proj.fk_statut=1 AND el.targettype="commande" AND el.sourcetype="propal"';
	
	if (!empty($plageClotureProp_fin) && !empty($plageClotureProp_deb)){
		$plageClotureProp_deb = date("Y-m-d", strtotime(str_replace('/', '-', $plageClotureProp_deb)));
		$plageClotureProp_fin = date("Y-m-d", strtotime(str_replace('/', '-', $plageClotureProp_fin)));
		
		$sql.='AND prop.date_cloture BETWEEN ()"'.$plageClotureProp_deb.'" AND "'.$plageClotureProp_fin.'") ';
	}
	//A REMPLIR POUR FILTRE SUR PLAFE RECEPTION ENQUETE DE SATISFACTION
	if (!empty($plageReception_deb) && !empty($plageReception_fin)){
		$plageReception_deb   = date("Y-m-d", strtotime(str_replace('/', '-', $plageReception_deb)));
		$plageReception_fin   = date("Y-m-d", strtotime(str_replace('/', '-', $plageReception_fin)));
		
		$sql.='';
	}
	// A REMPLIR POUR FILTRE SUR REALISATION DES ESSAIS
	if (!empty($plageEssai_deb) && !empty($plageEssai_fin)){
		$plageEssai_deb       = date("Y-m-d", strtotime(str_replace('/', '-', $plageEssai_deb)));
		$plageEssai_deb       = date("Y-m-d", strtotime(str_replace('/', '-', $plageEssai_deb)));
	
		$sql.='';
	}
	if (!empty($client)){
		$sql.= 'AND soc.rowid='.$client.' ';
	}
	$sql.= 'GROUP BY prop.rowid 
	ORDER BY soc.nom';
	
	//pre($sql, true);
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
function _print_infos_categories($TReport){
	foreach ($TReport as $categ){
		print '<td></td>';
		print '<td></td>';
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
	WHERE el.sourcetype= "propal" AND el.fk_source='.$id.' ';
	
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
	while ($PDOdb->Get_line()) {
		$TCateg_task[]=array(
						"catid"         => $PDOdb->Get_field('catid'),
						"catLabel"      => $PDOdb->Get_field('catLabel'),
						"taskId"  	    => $PDOdb->Get_field('taskId')
					);
	}
	return $TCateg_task;
}



llxFooter();
