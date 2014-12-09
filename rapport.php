<?php
require('config.php');
dol_include_once("/report/lib/report.lib.php");
dol_include_once("/report/filtres.php");

llxHeader('',$langs->trans('Report'));
print dol_get_fiche_head(reportPrepareHead('Doc2Project') , 'Doc2Project', $langs->trans('Doc2Project'));
print_fiche_titre($langs->trans("Report"));
?>
<script type="text/javascript" src="<?php echo COREHTTP?>includes/js/dataTable/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="<?php echo COREHTTP?>includes/js/dataTable/js/dataTables.tableTools.min.js"></script>

<link rel="stylesheet" href="<?php echo COREHTTP?>includes/js/dataTable/css/jquery.dataTables.css" type="text/css" />
<link rel="stylesheet" href="<?php echo COREHTTP?>includes/js/dataTable/css/dataTables.tableTools.css" type="text/css" />
<?php

$PDOdb=new TPDOdb;

// Get parameters
_action($PDOdb);

llxFooter();

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
	
	$form = new TFormCore('auto','formReport');
	
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
			echo $form->btsubmit('Afficher', 'afficher');
		}

		echo $form->end();

		switch ($report) {
			case 'statistiques_projet':
				_get_statistiques_projet($PDOdb);
				break;
		}
	}
	else{
		echo $form->btsubmit('Afficher', 'afficher');
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
			break;
		
		default:
			break;
	}
	
	echo '<tr><td colspan="2" align="center">'.$form->btsubmit('Valider', 'valider').'</td></tr>';
	echo '</table>';
	
	echo '</div>';
}

function _get_statistiques_projet(&$PDOdb){
	global $db,$conf;

	$idprojet = GETPOST('id_projet');

	$sql = "SELECT p.ref, p.title, SUM(pp.total) as total_vente, SUM(ff.total_ht) as total_achat, SUM(ndfp.total_ht) as total_ndf, SUM(tt.task_duration) as total_temps, SUM(tt.thm * tt.task_duration/3600) as total_cout_homme
			FROM ".MAIN_DB_PREFIX."projet as p 
				LEFT JOIN ".MAIN_DB_PREFIX."propal as pp ON (pp.fk_projet = p.rowid AND pp.fk_statut >= 2)
				LEFT JOIN ".MAIN_DB_PREFIX."facture_fourn as ff ON (ff.fk_projet = p.rowid AND ff.fk_statut >= 1)
				LEFT JOIN ".MAIN_DB_PREFIX."ndfp as ndfp ON (ndfp.fk_project = p.rowid AND ndfp.statut >= 1)
				LEFT JOIN ".MAIN_DB_PREFIX."projet_task as t ON (t.fk_projet = p.rowid)
				LEFT JOIN ".MAIN_DB_PREFIX."projet_task_time as tt ON (tt.fk_task = t.rowid)";

	if($idprojet > 0) $sql.= " WHERE p.rowid = ".$idprojet;
	else $sql.= " GROUP BY p.rowid";
	
	//echo $sql.'<br>';
	
	$PDOdb->Execute($sql);

	$TRapport = array();
	$PDOdb2 = new TPDOdb;
	
	while ($PDOdb->Get_line()) {
		//echo ($conf->global->DOC2PROJECT_NB_HOURS_PER_DAY*60*60).'<br>';
		//echo $PDOdb->Get_field('total_temps')." ".($conf->global->DOC2PROJECT_NB_HOURS_PER_DAY*60*60).'<br>';
		$TRapport[]= array(
			"ref" => $PDOdb->Get_field('ref')." - ".$PDOdb->Get_field('title'),
			"total_vente" => $PDOdb->Get_field('total_vente'),
			"total_achat" => $PDOdb->Get_field('total_achat'),
			"total_ndf" => $PDOdb->Get_field('total_ndf'),
			"total_temps" => $PDOdb->Get_field('total_temps'),
			"total_cout_homme" => $PDOdb->Get_field('total_cout_homme'),
			"marge" => $PDOdb->Get_field('total_vente') - $PDOdb->Get_field('total_achat') - $PDOdb->Get_field('total_ndf') - $PDOdb->Get_field('total_cout_homme')
		);
	}
	
	//pre($TRapport,true);
	
	_print_statistiques_projet($TRapport);

}

function _print_statistiques_projet(&$TRapport){
	global $conf;
	dol_include_once('/core/lib/date.lib.php');
	?>
	<div class="tabBar" style="padding-bottom: 25px;">
		<table id="statistiques_projet" class="noborder" width="100%">
			<thead>
				<tr style="text-align:center;" class="liste_titre nodrag nodrop">
					<td>Réf. Projet</td>
					<td>Total vente (€)</td>
					<td>Total achat (€)</td>
					<td>Total Note de frais (€)</td>
					<td>Total temps passé (JH)</td>
					<td>Total coût MO (€)</td>
					<td>Rentabilité</td>
				</tr>
			</thead>
			<tbody>
				<?php
				
				foreach($TRapport as $line){
					?>
					<tr>
						<td><?php echo $line['ref'] ?></td>
						<td><?php echo price($line['total_vente']) ?></td>
						<td><?php echo price($line['total_achat']) ?></td>
						<td><?php echo price($line['total_ndf']) ?></td>
						<td><?php echo convertSecondToTime($line['total_temps'],'all',$conf->global->DOC2PROJECT_NB_HOURS_PER_DAY*60*60) ?></td>
						<td><?php echo price($line['total_cout_homme']) ?></td>
						<td<?php echo ($line['marge'] < 0) ? ' style="color:red;font-weight: bold" ' : ' style="color:green" ' ?>><?php echo price($line['marge']) ?></td>
					</tr>
					<?
					$total_vente += $line['total_vente'];
					$total_achat += $line['total_achat'];
					$total_ndf += $line['total_ndf'];
					$total_temps += $line['total_temps'];
					$total_cout_homme += $line['total_cout_homme'];
					$total_marge += $line['marge'];
				}
				?>
			</tbody>
			<tfoot>
				<tr style="font-weight: bold;">
					<td>Totaux</td>
					<td><?php echo price($total_vente) ?></td>
					<td><?php echo price($total_achat) ?></td>
					<td><?php echo price($total_ndf) ?></td>
					<td><?php echo convertSecondToTime($total_temps,'all',$conf->global->DOC2PROJECT_NB_HOURS_PER_DAY*60*60) ?></td>
					<td><?php echo price($total_cout_homme) ?></td>
					<td<?php echo ($total_marge < 0) ? ' style="color:red" ' : ' style="color:green" ' ?>><?php echo price($total_marge) ?></td>
				</tr>
			</tfoot>
		</table>
	</div>
	<?php
}
