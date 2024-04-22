<?php
/**
* SPDX-License-Identifier: GPL-3.0-or-later
* This file is part of Dolibarr module Doc2Project
*/

require('config.php');



if(!$user->hasRight('doc2project', 'read')) accessforbidden();

dol_include_once("/doc2project/lib/report.lib.php");
dol_include_once("/doc2project/filtres.php");

$PDOdb=new TPDOdb;

llxHeader('',$langs->trans('Report'));

// Get parameters
_action($PDOdb);


function _action(&$PDOdb) {
	global $user, $conf;

	if(isset($_REQUEST['action'])) {
		switch($_REQUEST['action']) {

			case 'report':
				_fiche($PDOdb,$_REQUEST['report']);
				break;
			default :
				_fiche($PDOdb);
		}

	}
	else{
		_fiche($PDOdb);
	}
}

//Déclaration des DataTables
?>
<script type="text/javascript">
	$(document).ready(function() {
		$('#statistiques_projet').dataTable({
			"sDom": 'T<"clear">lfrtip',
			"oTableTools": {
				"sSwfPath": "<?php echo dol_buildpath('/abricot/includes/js/dataTable/swf/copy_csv_xls_pdf.swf', 1); ?>"
			},
			"bSort": false,
			"iDisplayLength": 100,
			"oLanguage": {
					"sProcessing":     "Traitement en cours...",
					"sSearch":         "Rechercher&nbsp;:",
					"sLengthMenu":     "Afficher _MENU_ &eacute;l&eacute;ments",
					"sInfo":           "Affichage de l'&eacute;lement _START_ &agrave; _END_ sur _TOTAL_ &eacute;l&eacute;ments",
					"sInfoEmpty":      "Affichage de l'&eacute;lement 0 &agrave; 0 sur 0 &eacute;l&eacute;ments",
					"sInfoFiltered":   "(filtr&eacute; de _MAX_ &eacute;l&eacute;ments au total)",
					"sInfoPostFix":    "",
					"sLoadingRecords": "Chargement en cours...",
					"sZeroRecords":    "Aucun &eacute;l&eacute;ment &agrave; afficher",
					"sEmptyTable":     "Aucune donnée disponible dans le tableau",
					"oPaginate": {
						"sFirst":      "Premier",
						"sPrevious":   "Pr&eacute;c&eacute;dent",
						"sNext":       "Suivant",
						"sLast":       "Dernier"
					},
					"oAria": {
						"sSortAscending":  ": activer pour trier la colonne par ordre croissant",
						"sSortDescending": ": activer pour trier la colonne par ordre décroissant"
					}
			}
		});
	});
</script>
<?php

llxFooter();
$db->close();

function _fiche(&$PDOdb,$report=''){
	global $langs;


	print dol_get_fiche_head(reportPrepareHead('Doc2Project') , 'Doc2Project', $langs->trans('Doc2Project'));
	print_fiche_titre($langs->trans("Report"));
	?>
	<script type="text/javascript" src="<?php echo dol_buildpath('/abricot/includes/js/dataTable/js/jquery.dataTables.min.js', 1); ?>"></script>
	<script type="text/javascript" src="<?php echo dol_buildpath('/abricot/includes/js/dataTable/js/dataTables.tableTools.min.js', 1); ?>"></script>

	<link rel="stylesheet" href="<?php echo dol_buildpath('/abricot/includes/js/dataTable/css/jquery.dataTables.css', 1); ?>" type="text/css" />
	<link rel="stylesheet" href="<?php echo dol_buildpath('/abricot/includes/js/dataTable/css/dataTables.tableTools.css', 1); ?>" type="text/css" />
	<?php


	echo '<div>';

	$form = new TFormCore('auto','formReport', 'GET');

	echo $form->hidden('action', 'report');

	$TRapport = array(
					'statistiques_projet'=>"Statistiques Projets",
					'statistiques_categorie'=>'Statistiques Catégories',
				);

	echo $form->combo('Rapport à afficher : ', 'report', $TRapport,($_REQUEST['report'])? $_REQUEST['report'] : '');

	$THide = array();

	if(!empty($report)){

		if(!in_array($report,$THide)){
			//Affichage des filtres
			_get_filtre($report,$PDOdb,$form);
		}
		else{
			echo $form->btsubmit('Afficher', '');
		}

		echo $form->end();

		print dol_get_fiche_end();

		switch ($report) {
			case 'statistiques_projet':
				$TReport=_get_statistiques_projet($PDOdb);
				_print_statistiques_projet($TReport);

				break;

			case 'statistiques_categorie':
				$TReport=_get_statistiques_projet($PDOdb);
				print_statistiques_categorie($PDOdb, $TReport);
				break;

			default:

				break;
		}
	}
	else{
		echo $form->btsubmit('Afficher', '');
		print dol_get_fiche_end();
	}

	echo '</div>';
}

function _get_filtre($report,$PDOdb,$form){

	print_fiche_titre('Filtres');
	echo '<div class="tabBar">';
	echo '<table>';

	switch ($report) {
		case 'statistiques_projet':
			_print_filtre_liste_projet($form,$PDOdb);
			_print_filtre_plage_date($form);
			break;

		default:
			break;
	}

	echo '<tr><td colspan="2" align="center">'.$form->btsubmit('Valider', '').'</td></tr>';
	echo '</table>';

	echo '</div>';
}

function _get_statistiques_projet(&$PDOdb){
	global $db,$conf;


	$idprojet = GETPOST('id_projet');

	$date_deb = GETPOST('date_deb');
	$t_deb = !$date_deb ? 0 : Tools::get_time($date_deb);

	$date_fin = GETPOST('date_fin');
	$t_fin = !$date_fin ? 0 : Tools::get_time($date_fin);

	$sql = "SELECT p.rowid as IdProject, p.ref, p.title, p.dateo, p.datee, pe.categorie as categorie ";

    if (version_compare(DOL_VERSION, '18.0.0', '<'))
    {
        $sql .= ", (SELECT SUM(tt.task_duration) FROM ".MAIN_DB_PREFIX."projet_task_time as tt WHERE tt.fk_task IN (";
        $sql .= " SELECT t.rowid FROM ".MAIN_DB_PREFIX."projet_task as t WHERE t.fk_projet = p.rowid)";
        $sql .= ($t_deb>0 && $t_fin>0 ? " AND task_date BETWEEN '".date('Y-m-d', $t_deb)."' AND '".date('Y-m-d', $t_fin)."' " : ''  ).") as total_temps";

        $sql .= ", (SELECT SUM(tt.thm * tt.task_duration/3600) FROM ".MAIN_DB_PREFIX."projet_task_time as tt WHERE tt.fk_task IN (";
        $sql .= " SELECT t.rowid FROM ".MAIN_DB_PREFIX."projet_task as t WHERE t.fk_projet = p.rowid)";
        $sql .= ($t_deb>0 && $t_fin>0 ? " AND task_date BETWEEN '".date('Y-m-d', $t_deb)."' AND '".date('Y-m-d', $t_fin)."' " : ''  ).") as total_cout_homme";
    }
    else
    {
        $sql .= ", (SELECT SUM(tt.element_duration) FROM ".MAIN_DB_PREFIX."element_time as tt WHERE tt.elementtype = 'task' AND tt.fk_element IN (";
        $sql .= " SELECT t.rowid FROM ".MAIN_DB_PREFIX."projet_task as t WHERE t.fk_projet = p.rowid)";
        $sql .= ($t_deb>0 && $t_fin>0 ? " AND element_date BETWEEN '".date('Y-m-d', $t_deb)."' AND '".date('Y-m-d', $t_fin)."' " : ''  ).") as total_temps";

        $sql .= ", (SELECT SUM(tt.thm * tt.element_duration/3600) FROM ".MAIN_DB_PREFIX."element_time as tt WHERE tt.elementtype = 'task' AND tt.fk_element IN (";
        $sql .= " SELECT t.rowid FROM ".MAIN_DB_PREFIX."projet_task as t WHERE t.fk_projet = p.rowid)";
        $sql .= ($t_deb>0 && $t_fin>0 ? " AND element_date BETWEEN '".date('Y-m-d', $t_deb)."' AND '".date('Y-m-d', $t_fin)."' " : ''  ).") as total_cout_homme";
    }


    $sql .= " FROM ".MAIN_DB_PREFIX."projet as p LEFT JOIN ".MAIN_DB_PREFIX."projet_extrafields pe ON p.rowid = pe.fk_object WHERE p.entity IN (".getEntity('project').")";

	if($idprojet > 0) $sql.= " AND p.rowid = ".$idprojet;

	$sql.=" ORDER BY ";

	$sortfield = GETPOST('sortfield');
	$sortorder = GETPOST('sortorder');

	if (!empty($sortfield) && !empty($sortorder)) {
		$sql .= $sortfield . ' ' . $sortorder;
	} else {
		$sql .= 'p.dateo';
	}

	$PDOdb->Execute($sql);
	$TRapport = array();
	//pre($sql, true);
	while ($PDOdb->Get_line()) {
		list($vente,$achat,$ndf) = _getTotauxProjet($PDOdb, $PDOdb->Get_field('IdProject'),$t_deb, $t_fin);
		$marge = $vente - $achat - $ndf- $PDOdb->Get_field('total_cout_homme');
		$TRapport[]= array(
				"IdProject"         => $PDOdb->Get_field('IdProject'),
				"date_debut"        => $PDOdb->Get_field('dateo'),
				"date_fin"          => $PDOdb->Get_field('datee'),
				"total_vente"       => $vente,
				"total_achat"       => $achat,
				"total_ndf"         => $ndf,
				"total_temps"       => $PDOdb->Get_field('total_temps'),
				"total_cout_homme"  => $PDOdb->Get_field('total_cout_homme'),
				"marge"             => $marge,
				"categorie"         =>$PDOdb->Get_field('categorie')
		);

	}
	//pre($TRapport, true);
	return $TRapport;


}

function _print_statistiques_projet(&$TRapport){
	global $conf, $db;

	dol_include_once('/core/lib/date.lib.php');
	dol_include_once('/projet/class/project.class.php');

	$id_projet = GETPOST('');

	$params = $_SERVER['QUERY_STRING'];
	$sortfield = $sortorder = '';
	?>
	<div class="tabBar" style="padding-bottom: 25px;">
		<table id="statistiques_projet" class="noborder" width="100%">
			<thead>
				<tr style="text-align:left;" class="liste_titre nodrag nodrop">
					<th class="liste_titre">Réf. Projet</th>
					<?php
					print_liste_field_titre('Date début', $_SERVER["PHP_SELF"], "p.dateo", "", $params, "", $sortfield, $sortorder);
					print_liste_field_titre('Date fin', $_SERVER["PHP_SELF"], "p.datee", "", $params, "", $sortfield, $sortorder);
					?>
					<th class="liste_titre">Total vente (€)</th>
					<th class="liste_titre">Total achat (€)</th>
					<?php if($conf->ndfp->enabled){ ?><th class="liste_titre">Total Note de frais (€)</th><?php } ?>
					<th class="liste_titre">Total temps passé (JH)</th>
					<th class="liste_titre">Total coût MO (€)</th>
					<th class="liste_titre">Rentabilité</th>
				</tr>
			</thead>
			<tbody>
				<?php
				$total_vente = $total_achat = $total_ndf = $total_temps = $total_cout_homme = $total_marge = 0;
				foreach($TRapport as $line){
					$project=new Project($db);
					$project->fetch($line['IdProject']);

					$date_debut = date('d/m/Y', strtotime($line['date_debut']));
					$date_fin = date('d/m/Y', strtotime($line['date_fin']));

					?>
					<tr>
						<td><?php echo $project->getNomUrl(1,'',1)  ?></td>
						<td><?php echo $date_debut;  ?></td>
						<td><?php echo $date_fin; ?></td>
						<td nowrap="nowrap"><?php echo price(round($line['total_vente'],2)) ?></td>
						<td nowrap="nowrap"><?php echo price(round($line['total_achat'],2)) ?></td>
						<?php if($conf->ndfp->enabled){ ?><td nowrap="nowrap"><?php echo price(round($line['total_ndf'],2)) ?></td><?php } ?>
						<td nowrap="nowrap"><?php echo convertSecondToTime($line['total_temps'],'all',getDolGlobalInt('DOC2PROJECT_NB_HOURS_PER_DAY') * 60 * 60) ?></td>
						<td nowrap="nowrap"><?php echo price(round($line['total_cout_homme'],2)) ?></td>
						<td<?php echo ($line['marge'] < 0) ? ' style="color:red;font-weight: bold" ' : ' style="color:green" ' ?> nowrap="nowrap"><?php echo price(round($line['marge'],2)) ?></td>
					</tr>
					<?php
					$total_vente += $line['total_vente'];
					$total_achat += $line['total_achat'];
					if($conf->ndfp->enabled)$total_ndf += $line['total_ndf'];
					$total_temps += $line['total_temps'];
					$total_cout_homme += $line['total_cout_homme'];
					$total_marge += $line['marge'];
				}
				?>
			</tbody>
			<tfoot>

				<tr style="font-weight: bold;">
					<td>Totaux</td>
					<td></td>
					<td></td>
					<td><?php echo price($total_vente) ?></td>
					<td><?php echo price($total_achat) ?></td>
					<?php if($conf->ndfp->enabled){ ?><td><?php echo price($total_ndf) ?></td><?php } ?>
					<td><?php echo convertSecondToTime($total_temps,'all',getDolGlobalInt('DOC2PROJECT_NB_HOURS_PER_DAY') * 60 * 60) ?></td>
					<td><?php echo price($total_cout_homme) ?></td>
					<td<?php echo ($total_marge < 0) ? ' style="color:red" ' : ' style="color:green" ' ?>><?php echo price($total_marge) ?></td>
				</tr>
			</tfoot>
		</table>
	</div>
	<?php
}

//pour chaque catégorie additionne les infos de tous les projets appartenant à cette catégorie
function get_statistiques_categorie($PDOdb, $TRapport){
	global $db,$conf, $object;


	//$extrafield = new ExtraFields($db);
	//var_dump($extrafield);

	$TCateg=array();
	$TRapportCategorie=array();
	//var_dump($TRapport);
	//pre($TRapport, true);
	foreach($TRapport as $TProjet) {
		if (!empty($TProjet['categorie']))
		{
			$TCateg[$TProjet['categorie']][]=$TProjet;
		} else {
			$TCateg[0][]=$TProjet;
		}
	}
	$extrafield = new ExtraFields($db);
	$extrafield->fetch_name_optionals_label('projet');

	$TCategorie = $extrafield->attribute_param['categorie']['options'];

	//svar_dump($TCateg);
	foreach ($TCateg as $TProjets) {

		$idCategorie=0;
		$categorie="";//Récupérer le label via array extrafield_options
		$date_debut=null;
		$date_fin=null;
		$total_vente=0;
		$total_achat=0;
		$total_ndf=0;
		$total_temps=0;
		$total_cout_homme=0;
		$marge=0;

		foreach ($TProjets as $projet) {

			$idCategorie=$projet['categorie'];
			$categorie="";//Récupérer le label via array extrafield
			$total_vente+=$projet['total_vente'];
			$total_achat+=$projet['total_achat'];
			$total_ndf+=$projet['total_ndf'];
			$total_temps+=$projet['total_temps'];
			$total_cout_homme+=$projet['total_cout_homme'];
			$marge+=$projet['marge'];


			$TRapportCategorie[$idCategorie]=array(
				'idCategorie' => $idCategorie,
				'date_debut'  => $date_debut,
				'date_fin'    => $date_fin,
				'total_vente' => $total_vente,
				'total_achat' => $total_achat,
				'total_temps' => $total_temps,
				'total_cout_homme' =>$total_cout_homme,
				'marge' => $marge,
				'categorie' => $TCategorie[$idCategorie]

			);
		}

	}

	return $TRapportCategorie;
}


function print_statistiques_categorie($PDOdb, &$TReport){
	global $conf, $db;

	dol_include_once('/core/lib/date.lib.php');
	dol_include_once('/projet/class/project.class.php');
	$TRapport = get_statistiques_categorie($PDOdb, $TReport);

	//$id_projet = GETPOST('');

	//$params = $_SERVER['QUERY_STRING'];
	$params = $sortfield = $sortorder = '';
	?>
	<div class="tabBar" style="padding-bottom: 25px;">
		<table id="statistiques_projet" class="noborder" width="100%">
			<thead>
				<tr style="text-align:left;" class="liste_titre nodrag nodrop">
					<th class="liste_titre">Catégories</th>
					<?php
					print_liste_field_titre('date début', $_SERVER["PHP_SELF"], "p.dateo", "", $params, "", $sortfield, $sortorder);
					print_liste_field_titre('date fin', $_SERVER["PHP_SELF"], "p.datee", "", $params, "", $sortfield, $sortorder);
					?>
					<th class="liste_titre">Total vente (€)</th>
					<th class="liste_titre">Total achat (€)</th>
					<?php if($conf->ndfp->enabled){ ?><th class="liste_titre">Total Note de frais (€)</th><?php } ?>
					<th class="liste_titre">Total temps passé (JH)</th>
					<th class="liste_titre">Total coût MO (€)</th>
					<th class="liste_titre">Rentabilité</th>
				</tr>
			</thead>
			<tbody>
				<?php
				$total_vente = $total_achat = $total_ndf = $total_temps = $total_cout_homme = $total_marge = 0;
				foreach($TRapport as $line){

					$project=new Project($db);
					$project->fetch($line['IdProject']);

					$date_debut = date('d/m/Y', strtotime($line['date_debut']));
					$date_fin = date('d/m/Y', strtotime($line['date_fin']));

					?>
					<tr>
						<td><?php echo $line['categorie']?></td>
						<td><?php echo $date_debut;  ?></td>
						<td><?php echo $date_fin; ?></td>
						<td nowrap="nowrap"><?php echo price(round($line['total_vente'],2)) ?></td>
						<td nowrap="nowrap"><?php echo price(round($line['total_achat'],2)) ?></td>
						<?php if($conf->ndfp->enabled){ ?><td nowrap="nowrap"><?php echo price(round($line['total_ndf'],2)) ?></td><?php } ?>
						<td nowrap="nowrap"><?php echo convertSecondToTime($line['total_temps'],'all',getDolGlobalInt('DOC2PROJECT_NB_HOURS_PER_DAY') * 60 * 60)  ?></td>
						<td nowrap="nowrap"><?php echo price(round($line['total_cout_homme'],2)) ?></td>
						<td<?php echo ($line['marge'] < 0) ? ' style="color:red;font-weight: bold" ' : ' style="color:green" ' ?> nowrap="nowrap"><?php echo price(round($line['marge'],2)) ?></td>
					</tr>
					<?php
					$total_vente += $line['total_vente'];
					$total_achat += $line['total_achat'];
					if($conf->ndfp->enabled)$total_ndf += $line['total_ndf'];
					$total_temps += $line['total_temps'];
					$total_cout_homme += $line['total_cout_homme'];
					$total_marge += $line['marge'];
				}
				?>
			</tbody>
			<tfoot>
				<tr style="font-weight: bold;">
					<td>Totaux</td>
					<td></td>
					<td></td>
					<td><?php echo price($total_vente) ?></td>
					<td><?php echo price($total_achat) ?></td>
					<?php if($conf->ndfp->enabled){ ?><td><?php echo price($total_ndf) ?></td><?php } ?>
					<td><?php echo convertSecondToTime($total_temps,'all',getDolGlobalInt('DOC2PROJECT_NB_HOURS_PER_DAY') * 60 * 60)  ?></td>
					<td><?php echo price($total_cout_homme) ?></td>
					<td<?php echo ($total_marge < 0) ? ' style="color:red" ' : ' style="color:green" ' ?>><?php echo price($total_marge) ?></td>
				</tr>
			</tfoot>
		</table>
	</div>
	<?php
}



function _getTotauxProjet($PDOdb, $fk_projet, $t_deb=0,$t_fin=0){
	global $db, $conf;
	$vente = $achat = $ndf = 0;
	$factureTotalHTSQLField = 'total_ht';
	if ((float)DOL_VERSION <= 13) $factureTotalHTSQLField = 'total';
	 $sqlClient = "
		SELECT DISTINCT(f.rowid), f.".$factureTotalHTSQLField." as total
		FROM ".MAIN_DB_PREFIX."facture as f LEFT JOIN ".MAIN_DB_PREFIX."element_element el ON (el.fk_source=f.rowid)
		WHERE (f.fk_projet = ".$fk_projet." OR (el.fk_target=".$fk_projet." AND el.sourcetype LIKE 'facture' AND el.targettype LIKE 'project'))
		".($t_deb>0 && $t_fin>0 ? " AND f.datef BETWEEN '".date('Y-m-d', $t_deb)."' AND '".date('Y-m-d', $t_fin)."' " : ''  );

	$PDOdb2= new TPDOdb;
	$PDOdb2->Execute($sqlClient);

	$vente = 0;
	while($obj = $PDOdb2->Get_line()){
		$vente += $obj->total;
	}

	$sqlAchat='SELECT DISTINCT(f.rowid),f.total_ht AS total
	FROM '.MAIN_DB_PREFIX.'facture_fourn f LEFT JOIN '.MAIN_DB_PREFIX.'element_element el ON (el.fk_source=f.rowid)
	WHERE 1
	AND f.fk_projet = '.$fk_projet.' OR (el.fk_target='.$fk_projet.' AND el.sourcetype LIKE "facturefournisseur" AND el.targettype LIKE "project")
	';
	//var_dump($sql);

	$PDOdb2->Execute($sqlAchat);

	$achat = 0;
	while($obj2 = $PDOdb2->Get_line()){
		$achat+=$obj2->total;
	}

	 if($conf->ndfp->enabled){
		$sqlNdf=" , (
			SELECT SUM(DISTINCT(ndfp.total_ht)) AS totalNdf FROM ".MAIN_DB_PREFIX."ndfp as ndfp WHERE ndfp.fk_project = p.rowid AND ndfp.statut >= 1
			".($t_deb>0 && $t_fin>0 ? " AND datef BETWEEN '".date('Y-m-d', $t_deb)."' AND '".date('Y-m-d', $t_fin)."' " : ''  )."
		) as total_ndf ";

		$PDOdb2->Execute($sqlNdf);
		$obj3 = $PDOdb2->Get_line();
		$ndf=$obj3->totalNdf;

	 }


	//var_dump($sqlAchat);
	return array(
		$vente
	   ,$achat
	   ,$ndf
	);

}
