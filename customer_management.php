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
	?>
	
	<div class="tabBar" style="padding-bottom: 25px;">
		<table id="gestion_client" class="noborder" width="100%">
			<thead>
				<tr style="text-align:left;" class="liste_titre nodrag nodrop">
					<th class="liste_titre">Tiers</th>
					<th class="liste_titre">Devis</th>
					<?php 
					print_liste_field_titre('Date cloture', $_SERVER["PHP_SELF"], "p.datee", "", $params, "", $sortfield, $sortorder);
					?>
					<th class="liste_titre">Facture</th>
					<th class="liste_titre">Délais</th>
					<?php
					$TCateg = _select_categ($PDOdb);
					foreach ($TCateg as $categ) {
						print '<th class="liste_titre">'.$categ['label'].'</th>';						
					}
					?>
					<td class="liste_titre">commentaires</td>
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
					
					
					print '<tr>';
					print '<td>'.$societe->getNomUrl(1,'').'</td>';
					print '<td>'.$propal->getNomUrl(1,'').'</td>';
					print '<td>'.$infoLine['prop_cloture'].'</td>';
					print '<td>'.$facture->getNomUrl(1,'').'</td>';
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
	$sql = 'SELECT soc.nom AS soc_name, soc.rowid AS socId, prop.ref AS prop_ref, prop.rowid AS propId, prop.date_cloture AS prop_cloture, 
	fact.rowid AS facId, fact.facnumber AS facnumber, fact.fk_statut AS fac_statut, proj.note_private AS proj_note, proj.rowid AS id_project  
	FROM '.MAIN_DB_PREFIX.'societe soc INNER JOIN '.MAIN_DB_PREFIX.'propal prop ON soc.rowid=prop.fk_soc 
	INNER JOIN '.MAIN_DB_PREFIX.'element_element el ON el.fk_source = prop.rowid 
	INNER JOIN '.MAIN_DB_PREFIX.'facture fact ON el.fk_target = fact.rowid 
	LEFT JOIN '.MAIN_DB_PREFIX.'projet proj ON fact.fk_projet=proj.rowid
	WHERE 1
	ORDER BY soc.nom';
	
	//var_dump($sql);
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


llxFooter();
