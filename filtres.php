<?php
function _print_filtre_fournisseur(&$form,&$PDOdb){
	
	$PDOdb->Execute("SELECT rowid, nom FROM ".MAIN_DB_PREFIX."societe WHERE fournisseur = 1 ORDER BY nom");

	$TFourn = array();
	while($PDOdb->Get_line()){
		$TFourn[$PDOdb->Get_field('rowid')] = $PDOdb->Get_field('nom');
	}

	?>
		<tr>
			<td>Fournisseur : </td>
			<td><?php echo $form->combo('', 'fournisseur', $TFourn, ($_REQUEST['fournisseur'])? $_REQUEST['fournisseur'] : ''); ?></td>
		</tr>
	<?php
}
function _print_filtre_societe(&$form,&$PDOdb){
	
	$PDOdb->Execute("SELECT rowid, nom FROM ".MAIN_DB_PREFIX."societe WHERE 1 ORDER BY nom");

	$TSoc = array(0=>'');
	while($PDOdb->Get_line()){
		$TSoc[$PDOdb->Get_field('rowid')] = $PDOdb->Get_field('nom');
	}

	?>
		<tr>
			<td>Société : </td>
			<td><?php echo $form->combo('', 'socid', $TSoc, ($_REQUEST['socid'])? $_REQUEST['socid'] : ''); ?></td>
		</tr>
	<?php
}
function _print_filtre_plage_date(&$form){
	?>
		<tr>
			<td>Date de début : </td>
			<td><?php echo $form->calendrier('', 'date_deb', ($_REQUEST['date_deb'])? $_REQUEST['date_deb'] : ''); ?></td>
		</tr>
		<tr>
			<td>Date de fin : </td>
			<td><?php echo $form->calendrier('', 'date_fin', ($_REQUEST['date_fin'])? $_REQUEST['date_fin'] : ''); ?></td>
		</tr>
	<?php
}

function _print_filtre_annee(&$form,&$PDOdb,$table,$champ){
	
	$sql = "SELECT YEAR(".$champ.") as annee FROM ".MAIN_DB_PREFIX.$table." GROUP BY YEAR(".$champ.") ORDER BY YEAR(".$champ.") DESC";
	$PDOdb->Execute($sql);
	
	$Tfiltre = array();
	while($PDOdb->Get_line()){
		$Tfiltre[$PDOdb->Get_field('annee')] = $PDOdb->Get_field('annee');
	}
	
	?>
		<tr>
			<td>Année : </td>
			<td><?php echo $form->combo('', 'annee', $Tfiltre, ($_REQUEST['annee'])? $_REQUEST['annee'] : ''); ?></td>
		</tr>
	<?php
}

function _print_filtre_categorie_product($form,$PDOdb,$TData=array()){
	
	$sql = "SELECT c.label as libelle FROM ".MAIN_DB_PREFIX."categorie as c WHERE type = 0";
	
	$PDOdb->Execute($sql);

	$Tfiltre = array();
	while($PDOdb->Get_line()){
		$Tfiltre[$PDOdb->Get_field('libelle')] = $PDOdb->Get_field('libelle');
	}
	
	?>
		<tr>
			<td>Catégorie : </td>
			<td><?php echo $form->combo('', 'categorie', (count($TData)) ? $TData : $Tfiltre, ($_REQUEST['categorie'])? $_REQUEST['categorie'] : ''); ?></td>
		</tr>
	<?php
}

function _print_filtre_categorie_produit(&$form,&$PDOdb){
	
	$PDOdb->Execute("SELECT c.rowid, c.label FROM ".MAIN_DB_PREFIX."categorie as c WHERE c.type = 0 "); //0 => produit

	$TCategorie = array();
	$TCategorie[""] = "";
	while($PDOdb->Get_line()){
		$TCategorie[$PDOdb->Get_field('rowid')] = $PDOdb->Get_field('label');
	}

	?>
		<tr>
			<td>Catégorie produit : </td>
			<td><?php echo $form->combo('', 'categorie', $TCategorie, ($_REQUEST['categorie'])? $_REQUEST['categorie'] : ''); ?></td>
		</tr>
	<?php
}

function _print_filtre_type_document(&$form,&$PDOdb){
	?>
		<tr>
			<td>Type document : </td>
			<td><?php echo $form->combo('', 'type_doc', array('facture'=>'Facture', 'commande'=>'Commande', 'propal'=>'Offres'), ($_REQUEST['type_doc'])? $_REQUEST['type_doc'] : ''); ?></td>
		</tr>
	<?php
}

function _print_filtre_mois_annee(){
	global $db;
	
	dol_include_once('/core/class/html.formother.class.php');
	$form = new FormOther($db);
	
	?>
		<tr>
			<td>Mois : </td>
			<td><?php print $form->select_month(($_REQUEST['monthid'])? $_REQUEST['monthid'] : date("m")); print $form->select_year(($_REQUEST['yearid'])? $_REQUEST['yearid'] : ''); ?></td>
		</tr>
	<?php
}

function _print_filtre_liste_projet(&$form,&$PDOdb) {
	global $db;
	dol_include_once('/core/class/html.formprojet.class.php');
	$formproject = new FormProjets($db);
	
	?>
		<tr>
			<td>Projet : </td>
			<td><?php $formproject->select_projects(-1, $_REQUEST['id_projet'], 'id_projet', 0); ?></td>
		</tr>
	<?php
}

function _print_filtre_customer_management(&$formcore){
	global $db;
    print_fiche_titre('Filtres');
    print '<div class="tabBar">';
    print '<table>';
	
	
	$form = new Form($db);
	print '<tr><td><b>Client</b></td></tr>';
	print '<tr>';
	print '<td>Société : </td>';
	print '<td>'.$form->select_company('', 'client', '', 1, 0, 0, array(), 0, 'minwidth50').'</td>';
	print '</tr>';
	
	
	print '<tr><td><b>Prestation</b></td></tr>';
	print '<tr>';
	print '<td>Catégorie : </td>';
	print '<td>'.$form->select_all_categories(0).'</td>';
	print '</tr>';
	
	
	print '<tr><td><b>Cloture du devis</b></td></tr>';
	print '<tr>';
	print '<td>Date de début : </td>';
	print '<td>'.$formcore->calendrier('', 'date_deb_cloture', ($_REQUEST['date_deb'])? $_REQUEST['date_deb'] : '').'</td>';
	print '</tr>';
	print '<tr>';
	print '<td>Date de fin : </td>';
	print '<td>'.$formcore->calendrier('', 'date_fin_cloture', ($_REQUEST['date_fin'])? $_REQUEST['date_fin'] : '').'</td>';
	print '</tr>';
	
	
	print '<tr><td><b>Réception des enquetes</b></td></tr>';
	print '<tr>';
	print '<td>Date de début : </td>';
	print '<td>'.$formcore->calendrier('', 'date_deb_reception', ($_REQUEST['date_deb'])? $_REQUEST['date_deb'] : '').'</td>';
	print '</tr>';
	print '<tr>';
	print '<td>Date de fin : </td>';
	print '<td>'.$formcore->calendrier('', 'date_fin_reception', ($_REQUEST['date_fin'])? $_REQUEST['date_fin'] : '').'</td>';
	print '</tr>';
	
	
	print '<tr><td><b>Réalisation des essais</b></td></tr>';
	print '<tr>';
	print '<td>Date de début : </td>';
	print '<td>'.$formcore->calendrier('', 'date_deb_essai', ($_REQUEST['date_deb'])? $_REQUEST['date_deb'] : '').'</td>';
	print '</tr>';
	print '<tr>';
	print '<td>Date de fin : </td>';
	print '<td>'.$formcore->calendrier('', 'date_fin_essai', ($_REQUEST['date_fin'])? $_REQUEST['date_fin'] : '').'</td>';
	print '</tr>';
	
    print '<tr><td colspan="2" align="center">'.$formcore->btsubmit('Valider', '').'</td></tr>';
	
	
    print '</table>';
    
    print '</div>';
}
