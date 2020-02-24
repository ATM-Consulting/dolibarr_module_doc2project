<?php
require('config.php');
dol_include_once("/doc2project/lib/report.lib.php");
dol_include_once("/doc2project/filtres.php");

llxHeader('',$langs->trans('Report'));
print dol_get_fiche_head(reportPrepareHead('Doc2Project') , 'Doc2Project', $langs->trans('Doc2Project'));
print_fiche_titre($langs->trans("Report"));
?>
<script type="text/javascript" src="<?php echo COREHTTP?>includes/js/dataTable/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="<?php echo COREHTTP?>includes/js/dataTable/js/dataTables.tableTools.min.js"></script>

<link rel="stylesheet" href="<?php echo COREHTTP?>includes/js/dataTable/css/jquery.dataTables.css" type="text/css" />
<link rel="stylesheet" href="<?php echo COREHTTP?>includes/js/dataTable/css/dataTables.tableTools.css" type="text/css" />
<?php
$langs->load('doc2project@doc2project');
$PDOdb=new TPDOdb;

// Get parameters
_action($PDOdb);

llxFooter();

/**
 * @param TPDOdb $PDOdb
 */
function _action(&$PDOdb) {
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
	            "sSwfPath": "<?php echo COREHTTP?>includes/js/dataTable/swf/copy_csv_xls_pdf.swf"
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

function _fiche(&$PDOdb,$report=''){

	echo '<div>';

	$form = new TFormCore('auto','formReport', 'GET');

	echo $form->hidden('action', 'report');

	$TRapport = array(
					'statistiques_projet'=>"Statistiques Projets",
				);

	echo $form->combo('Rapport à afficher : ', 'report', $TRapport,($_REQUEST['report'])? $_REQUEST['report'] : '');

	$THide = array();

	if($report){

		if(!in_array($report,$THide)){
			//Affichage des filtres
			_get_filtre($report,$PDOdb,$form);
		}
		else{
			echo $form->btsubmit('Afficher', '');
		}

		$form->end();

		switch ($report) {
			case 'statistiques_projet':
				_get_statistiques_projet($PDOdb);
				break;
		}
	}
	else{
		echo $form->btsubmit('Afficher', '');
	}

	echo '</div>';
}

/**
 * @param string $report
 * @param TPDOdb $PDOdb
 * @param TFormCore $form
 */
function _get_filtre($report,$PDOdb,$form){
	global $conf;

	print_fiche_titre('Filtres');
	echo '<div class="tabBar">';
	echo '<table>';

	switch ($report) {
		case 'statistiques_projet':
			_print_filtre_liste_projet($form,$PDOdb);
			_print_filtre_plage_date($form, 'project');
			if (!empty($conf->global->DOC2PROJECT_SHOW_DOCUMENT_DATE_FILTER_ON_STATISTICS_REPORT)) {
				_print_filtre_plage_date($form, 'document');
			}
			_print_filtre_type_projet($form, $PDOdb);
			break;

		default:
			break;
	}

	echo '<tr><td colspan="2" align="center">'.$form->btsubmit('Valider', '').'</td></tr>';
	echo '</table>';

	echo '</div>';
}

	/**
	 * @param string $prefix
	 * @return object
	 */
function _get_date_filter($prefix) {
	$start = GETPOST($prefix . '_start_date', 'alpha');
	$end   = GETPOST($prefix . '_end_date',   'alpha');
	if (!empty($start)) $t_start = Tools::get_time($start); else $t_start = 0;
	if (!empty($end))   $t_end   = Tools::get_time($end);   else $t_end   = 0;
	return (object) array(
		'start_str' => $start,
		'end_str'   => $end,
		'start' => $t_start,
		'end'   => $t_end,
		'wrong_order' => ($t_start && $t_end && $t_start > $t_end),
	);
}

/**
 * @param TPDOdb $PDOdb
 */
function _get_statistiques_projet(&$PDOdb){
	global $conf, $langs;

	$idprojet = GETPOST('id_projet');

	$projectDateFilter = _get_date_filter('project');
	$documentDateFilter = _get_date_filter('document');

	// make sure that $date_deb and $date_fin are in the right order
	if ($projectDateFilter->wrong_order) {
		$langs->load('doc2project@doc2project');
		setEventMessages($langs->trans('Doc2ProjectErrorDateStartBeforeEnd'), array(), 'errors');
		return;
	}

	// subqueries
	$sqlTotalVente       = 'SELECT SUM(f.total) FROM                        ' . MAIN_DB_PREFIX . 'facture as f               '. ' WHERE f.fk_projet = p.rowid AND f.fk_statut IN (1, 2)';
	$sqlTotalVenteFutur  = 'SELECT SUM(total_ht) FROM                       ' . MAIN_DB_PREFIX . 'propal as prop             '. ' WHERE prop.fk_statut = 2  AND prop.fk_projet = p.rowid';
	$sqlTotalVentePrevis = 'SELECT SUM(total_ht) FROM                       ' . MAIN_DB_PREFIX . 'propal as prop             '. ' WHERE prop.fk_statut <> 0 AND prop.fk_projet = p.rowid';
	$sqlTotalAchat       = 'SELECT SUM(ff.total_ht) FROM                    ' . MAIN_DB_PREFIX . 'facture_fourn as ff        '. ' WHERE ff.fk_projet = p.rowid AND ff.fk_statut >= 1';
	$sqlTotalAchatFutur  = 'SELECT SUM(total_ht) FROM                       ' . MAIN_DB_PREFIX . 'commande_fournisseur as cmd'. ' WHERE cmd.fk_statut <> 0 AND cmd.fk_projet = p.rowid';
	$sqlTotalNDF         = 'SELECT SUM(ndfp.total_ht) FROM                  ' . MAIN_DB_PREFIX . 'ndfp as ndfp               '. ' WHERE ndfp.fk_project = p.rowid AND ndfp.statut >= 1';
	$sqlTotalTemps       = 'SELECT SUM(tt.task_duration) FROM               ' . MAIN_DB_PREFIX . 'projet_task_time as tt     '. ' WHERE tt.fk_task IN (SELECT t.rowid FROM ' . MAIN_DB_PREFIX . 'projet_task as t WHERE t.fk_projet = p.rowid)';
	$sqlTotalCoutHomme   = 'SELECT SUM(tt.thm * tt.task_duration/3600) FROM ' . MAIN_DB_PREFIX . 'projet_task_time as tt     '. ' WHERE tt.fk_task IN (SELECT t.rowid FROM ' . MAIN_DB_PREFIX . 'projet_task as t WHERE t.fk_projet = p.rowid)';

	// some subqueries can have an additional date restriction
	if (!empty($conf->global->DOC2PROJECT_SHOW_DOCUMENT_DATE_FILTER_ON_STATISTICS_REPORT)) {
		$addDateRestriction = function($fieldName) use ($documentDateFilter, $PDOdb) {
			if ($documentDateFilter->start == 0 || $documentDateFilter->end == 0) return '';
			return sprintf(
				' AND %s BETWEEN %s AND %s ',
				$fieldName,
				$PDOdb->quote(date('Y-m-d', $documentDateFilter->start)),
				$PDOdb->quote(date('Y-m-d', $documentDateFilter->end))
			);
		};
		$sqlTotalVente     .= $addDateRestriction('datef');
		$sqlTotalAchat     .= $addDateRestriction('datef');
		$sqlTotalNDF       .= $addDateRestriction('datef');
		$sqlTotalTemps     .= $addDateRestriction('task_date');
		$sqlTotalCoutHomme .= $addDateRestriction('task_date');
	}

	$sql = 'SELECT p.rowid AS IdProject, p.ref, p.title, pe.datevent, pe.datefin, pe.typeevent, '
		. "\n" . ' (' . $sqlTotalVente       . ') AS total_vente,'
		. "\n" . ' (' . $sqlTotalVenteFutur  . ') AS total_vente_futur,'
		. "\n" . ' (' . $sqlTotalVentePrevis . ') AS total_vente_previsionnel,'
		. "\n" . ' (' . $sqlTotalAchat       . ') AS total_achat,'
		. "\n" . ' (' . $sqlTotalAchatFutur  . ') AS total_achat_futur,'
		. (($conf->ndfp->enabled) ? "\n" . ' (' . $sqlTotalNDF . ') AS total_ndf,' : '')
		. "\n" . ' (' . $sqlTotalTemps       . ') AS total_temps,'
		. "\n" . ' (' . $sqlTotalCoutHomme   . ') AS total_cout_homme'
		. "\n" . ' FROM ' . MAIN_DB_PREFIX . 'projet AS p'
		. "\n" . ' INNER JOIN ' . MAIN_DB_PREFIX . 'projet_extrafields AS pe ON pe.fk_object = p.rowid'
		. ' WHERE 1 = 1';
	$sqlProjectEndsAfterFilterStartDate  = ' AND pe.datefin  >= ' . $PDOdb->quote(date('Y-m-d', $projectDateFilter->start));
	$sqlProjectStartsBeforeFilterEndDate = ' AND pe.datevent <= ' . $PDOdb->quote(date('Y-m-d', $projectDateFilter->end));
	if (!empty($projectDateFilter->start_str)) $sql .= $sqlProjectEndsAfterFilterStartDate;
	if (!empty($projectDateFilter->end_str))   $sql .= $sqlProjectStartsBeforeFilterEndDate;

	if($idprojet > 0) $sql.= " AND p.rowid = ".$idprojet;

	$type_event = GETPOST('type_event');
	if (!empty($type_event)) {
		$sql .= ' AND pe.typeevent = ' . $type_event;
	}

	$sql.=" ORDER BY ";

	$sortfield = GETPOST('sortfield');
	$sortorder = GETPOST('sortorder');

	if (!empty($sortfield) && !empty($sortorder)) {
		$sql .= $sortfield . ' ' . $sortorder;
	} else {
		$sql .= 'pe.datevent';
	}
	print '<pre>' . $sql . '</pre>' ;exit;

	$PDOdb->Execute($sql);

	$TRapport = array();

	while ($PDOdb->Get_line()) {
		//echo ($conf->global->DOC2PROJECT_NB_HOURS_PER_DAY*60*60).'<br>';
		//echo $PDOdb->Get_field('total_temps')." ".($conf->global->DOC2PROJECT_NB_HOURS_PER_DAY*60*60).'<br>';
		$id_projet 								= $PDOdb->Get_field('IdProject');
		$date_event 							= $PDOdb->Get_field('datevent');
		$date_fin_event 					= $PDOdb->Get_field('datefin');
		$total_vente 							= $PDOdb->Get_field('total_vente');
		$total_vente_futur 				= $total_vente + $PDOdb->Get_field('total_vente_futur');
		$total_vente_previsionnel = $PDOdb->Get_field('total_vente_previsionnel');
		$total_achat 							= $PDOdb->Get_field('total_achat');
		$total_achat_futur 				= $total_achat + $PDOdb->Get_field('total_achat_futur');
		$total_temps 							= $PDOdb->Get_field('total_temps');
		$total_cout_homme 				= $PDOdb->Get_field('total_cout_homme');

		if($conf->ndfp->enabled){
			$marge 				= $total_vente - $total_achat - $PDOdb->Get_field('total_ndf') - $total_cout_homme;
			$marge_futur 	= $total_vente_futur - $total_achat_futur - $PDOdb->Get_field('total_ndf') - $total_cout_homme;
			$marge_prev 	= $total_vente_previsionnel - $total_achat_futur - $PDOdb->Get_field('total_ndf') - $total_cout_homme;
		} else {
			$marge 				= $total_vente - $total_achat - $total_cout_homme;
			$marge_futur 	= $total_vente_futur - $total_achat_futur - $total_cout_homme;
			$marge_prev 	= $total_vente_previsionnel - $total_achat_futur - $total_cout_homme;
		}

		$TRapport[]= array(
			"IdProject" 								=> $id_projet,
			"datevent" 									=> $date_event,
			"datefin" 									=> $date_fin_event,
			"total_vente" 							=> $total_vente,
			"total_vente_futur" 				=> $total_vente_futur,
			"total_vente_previsionnel" 	=> $total_vente_previsionnel,
			"total_achat" 							=> $total_achat,
			"total_achat_futur" 				=> $total_achat_futur,
			"total_temps" 							=> $total_temps,
			"total_cout_homme" 					=> $total_cout_homme,
			"marge" 										=> $marge,
			"marge_futur"								=> $marge_futur,
			"marge_prev"								=> $marge_prev
		);
	}

	if ($conf->ndfp->enabled) {
		$TRapport['total_ndf'] = $PDOdb->Get_field('total_ndf');
	}

	_print_statistiques_projet($TRapport, $sortfield, $sortorder);
}

function _print_statistiques_projet(&$TRapport, $sortfield, $sortorder){
	global $conf, $db;

	dol_include_once('/core/lib/date.lib.php');
	dol_include_once('/projet/class/project.class.php');

	$params = $_SERVER['QUERY_STRING'];

	$extrafields = new Extrafields($db);
	$extrafields->fetch_name_optionals_label('projet');

	$TTypes = $extrafields->attribute_param['typeevent']['options'];

	?>
	<div class="tabBar" style="padding-bottom: 25px;">
		<table id="statistiques_projet" class="noborder" width="100%">
			<thead>
				<tr style="text-align:center;" class="liste_titre nodrag nodrop">
					<th class="liste_titre">Réf. Projet</th>
					<?php
					print_liste_field_titre('Date début', $_SERVER["PHP_SELF"], "pe.datevent", "", $params, "", $sortfield, $sortorder);
					print_liste_field_titre('Date fin', $_SERVER["PHP_SELF"], "pe.datefin", "", $params, "", $sortfield, $sortorder);
					?>
					<th class="liste_titre">Type</th>
					<th class="liste_titre">Total vente (€)</th>
					<th class="liste_titre">Total vente futur (€)</th>
					<th class="liste_titre">Total vente prévisionnel (€)</th>
					<th class="liste_titre">Total achat (€)</th>
					<th class="liste_titre">Total achat futur (€)</th>
					<?php if($conf->ndfp->enabled){ ?><th class="liste_titre">Total Note de frais (€)</th><?php } ?>
					<th class="liste_titre">Total temps passé (JH)</th>
					<th class="liste_titre">Total coût MO (€)</th>
					<th class="liste_titre">Rentabilité</th>
					<th class="liste_titre">Rentabilité future</th>
					<th class="liste_titre">Rentabilité prévisionnelle</th>
				</tr>
			</thead>
			<tbody>
				<?php
				$total_vente = 0;
				$total_achat = 0;
				$total_ndf = 0;
				$total_temps = 0;
				$total_cout_homme = 0;
				$total_marge = 0;
				$total_marge_futur = 0;
				$total_marge_prev = 0;
				$total_vente_futur = 0;
				$total_vente_previsionnel = 0;
				$total_achat_futur = 0;
				foreach($TRapport as $line){
					$project=new Project($db);
					$project->fetch($line['IdProject']);
					$project->fetch_optionals();

					$client = new Societe($db);
					$client->fetch($project->socid);

					$type = $TTypes[$project->array_options['options_typeevent']];

					$date_debut = ($line['datevent'] !== false ? date('d/m/Y', strtotime($line['datevent'])) : '');
					$date_fin = ($line['datefin'] !== false ? date('d/m/Y', strtotime($line['datefin'])) : '');
					?>
					<tr>
						<td><?php echo $project->getNomUrl(1,'',1)  ?><br /><?php echo $client->getNomUrl(1); ?></td>
						<td><?php echo $date_debut;  ?></td>
						<td><?php echo $date_fin; ?></td>
						<td><?php echo $type; ?></td>
						<td nowrap="nowrap"><?php echo price(round($line['total_vente'],2)) ?></td>
						<td><?php echo price($line['total_vente_futur'], 2); ?></td>
						<td><?php echo price($line['total_vente_previsionnel'], 2); ?></td>
						<td nowrap="nowrap"><?php echo price(round($line['total_achat'],2)) ?></td>
						<td><?php echo price($line['total_achat_futur'], 2); ?></td>
						<?php if($conf->ndfp->enabled){ ?><td nowrap="nowrap"><?php echo price(round($line['total_ndf'],2)) ?></td><?php } ?>
						<td nowrap="nowrap"><?php echo convertSecondToTime($line['total_temps'],'all',$conf->global->DOC2PROJECT_NB_HOURS_PER_DAY*60*60) ?></td>
						<td nowrap="nowrap"><?php echo price(round($line['total_cout_homme'],2)) ?></td>
						<td<?php echo ($line['marge'] < 0) ? ' style="color:red;font-weight: bold" ' : ' style="color:green" ' ?> nowrap="nowrap"><?php echo price(round($line['marge'],2)) ?></td>
						<td<?php echo ($line['marge_futur'] < 0) ? ' style="color:red;font-weight: bold" ' : ' style="color:green" ' ?> nowrap="nowrap"><?php echo price(round($line['marge_futur'],2)) ?></td>
						<td<?php echo ($line['marge_prev'] < 0) ? ' style="color:red;font-weight: bold" ' : ' style="color:green" ' ?> nowrap="nowrap"><?php echo price(round($line['marge_prev'],2)) ?></td>
					</tr>
					<?php
					$total_vente += $line['total_vente'];
					$total_achat += $line['total_achat'];
					if($conf->ndfp->enabled)$total_ndf += $line['total_ndf'];
					$total_temps += $line['total_temps'];
					$total_cout_homme += $line['total_cout_homme'];
					$total_marge += $line['marge'];
					$total_marge_futur += $line['marge_futur'];
					$total_marge_prev += $line['marge_prev'];

					$total_vente_futur				+= $line['total_vente_futur'];
					$total_vente_previsionnel += $line['total_vente_previsionnel'];
					$total_achat_futur 				+= $line['total_achat_futur'];
				}
				?>
			</tbody>
			<tfoot>
				<tr style="font-weight: bold;">
					<td>Totaux</td>
					<td></td>
					<td></td>
					<td></td>
					<td><?php echo price($total_vente) ?></td>
					<td><?php echo price($total_vente_futur); ?></td>
					<td><?php echo price($total_vente_previsionnel); ?></td>
					<td><?php echo price($total_achat) ?></td>
					<td><?php echo price($total_achat_futur); ?></td>
					<?php if($conf->ndfp->enabled){ ?><td><?php echo price($total_ndf) ?></td><?php } ?>
					<td><?php echo convertSecondToTime($total_temps,'all',$conf->global->DOC2PROJECT_NB_HOURS_PER_DAY*60*60) ?></td>
					<td><?php echo price($total_cout_homme) ?></td>
					<td<?php echo ($total_marge < 0) ? ' style="color:red" ' : ' style="color:green" ' ?>><?php echo price($total_marge) ?></td>
					<td<?php echo ($total_marge_futur < 0) ? ' style="color:red" ' : ' style="color:green" ' ?>><?php echo price($total_marge_futur) ?></td>
					<td<?php echo ($total_marge_prev < 0) ? ' style="color:red" ' : ' style="color:green" ' ?>><?php echo price($total_marge_prev) ?></td>
				</tr>
			</tfoot>
		</table>
	</div>
	<?php
}
