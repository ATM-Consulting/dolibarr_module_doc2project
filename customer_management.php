<?php

require('config.php');
dol_include_once("/doc2project/lib/report.lib.php");
dol_include_once("/doc2project/filtres.php");

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
	
	print '<div>';
	$TRapport=_get_infos_rapport($PDOdb);
	?>
	<div class="tabBar" style="padding-bottom: 25px;">
		<table id="statistiques_projet" class="noborder" width="100%">
			<thead>
				<tr style="text-align:left;" class="liste_titre nodrag nodrop">
					<th class="liste_titre">Tiers</th>
					<th class="liste_titre">Devis</th>
					<?php 
					print_liste_field_titre('Date cloture', $_SERVER["PHP_SELF"], "p.datee", "", $params, "", $sortfield, $sortorder);
					?>
					<th class="liste_titre">Ref facture</th>
					<th class="liste_titre">Délais</th>
					<?php
					$TCateg = _select_categ($PDOdb);
					foreach ($TCateg as $categ) {
						print '<th class="liste_titre">'.$categ['label'].'</th>';						
					}
					?>
				</tr>
			</thead>
			<!--AJOUTER UN BANDEAU POUR FILTRER SUR LE PROJET, ETC.. -->
			<tbody>
				<td></td>
				<td></td>
				<td></td>
				<td></td>
				<td></td>
				<?php
				foreach ($TCateg as $categ) {
						print '<td>'.' '.'</td>';						
					}
				?>
			</tbody>
			<tfoot>
				<tr style="font-weight: bold;">
					<td>Totaux</td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
				</tr>
			</tfoot>
		</table>
	</div>
	<?php
	
	print '</div>';
}

function _get_infos_rapport(&$PDOdb){
	$TReport=array();
	/*
	 *Requete SQL 
	*/
	
	
	return $TReport;
}

function _print_infos_rapport($TRapport){
	
	
}


function _select_categ(&$PDOdb){
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
