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
			_print_filtre_plage_date($form);
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

	$date_deb = GETPOST('date_deb');
	$t_deb = !$date_deb ? 0 : Tools::get_time($date_deb);

	$date_fin = GETPOST('date_fin');
	$t_fin = !$date_fin ? 0 : Tools::get_time($date_fin);

	$sql = "SELECT p.rowid as IdProject, p.ref, p.title
	, (
                SELECT SUM(f.total) FROM ".MAIN_DB_PREFIX."propal as f WHERE f.fk_projet = p.rowid AND f.fk_statut IN(2)
                ".($t_deb>0 && $t_fin>0 ? " AND date_valid BETWEEN '".date('Y-m-d', $t_deb)."' AND '".date('Y-m-d', $t_fin)."' " : ''  )."
                ) as total_propal
	, (
		SELECT SUM(f.total) FROM ".MAIN_DB_PREFIX."facture as f WHERE f.fk_projet = p.rowid AND f.fk_statut IN(1, 2)
		".($t_deb>0 && $t_fin>0 ? " AND datef BETWEEN '".date('Y-m-d', $t_deb)."' AND '".date('Y-m-d', $t_fin)."' " : ''  )."
		) as total_vente
	, (
		SELECT SUM(ff.total_ht) FROM ".MAIN_DB_PREFIX."facture_fourn as ff WHERE ff.fk_projet = p.rowid AND ff.fk_statut >= 1 
		".($t_deb>0 && $t_fin>0 ? " AND datef BETWEEN '".date('Y-m-d', $t_deb)."' AND '".date('Y-m-d', $t_fin)."' " : ''  )."
	) as total_achat";
	
	if($conf->ndfp->enabled){
		$sql .=" , (
			SELECT SUM(ndfp.total_ht) FROM ".MAIN_DB_PREFIX."ndfp as ndfp WHERE ndfp.fk_project = p.rowid AND ndfp.statut >= 1  
			".($t_deb>0 && $t_fin>0 ? " AND datef BETWEEN '".date('Y-m-d', $t_deb)."' AND '".date('Y-m-d', $t_fin)."' " : ''  )."
		) as total_ndf ";
	}
	
	$sql .= ", (SELECT SUM(tt.task_duration) FROM ".MAIN_DB_PREFIX."projet_task_time as tt WHERE tt.fk_task IN (
			SELECT t.rowid FROM ".MAIN_DB_PREFIX."projet_task as t WHERE t.fk_projet = p.rowid)
		".($t_deb>0 && $t_fin>0 ? " AND task_date BETWEEN '".date('Y-m-d', $t_deb)."' AND '".date('Y-m-d', $t_fin)."' " : ''  )."
	) as total_temps

	, (SELECT SUM(t.planned_workload) FROM ".MAIN_DB_PREFIX."projet_task as t WHERE t.fk_projet = p.rowid 
                ".($t_deb>0 && $t_fin>0 ? " AND t.dateo BETWEEN '".date('Y-m-d', $t_deb)."' AND '".date('Y-m-d', $t_fin)."' " : ''  )."
        ) as total_temps_plannif

	,(SELECT SUM(tt.thm * tt.task_duration/3600) FROM ".MAIN_DB_PREFIX."projet_task_time as tt WHERE tt.fk_task IN (
			SELECT t.rowid FROM ".MAIN_DB_PREFIX."projet_task as t WHERE t.fk_projet = p.rowid)
		".($t_deb>0 && $t_fin>0 ? " AND task_date BETWEEN '".date('Y-m-d', $t_deb)."' AND '".date('Y-m-d', $t_fin)."' " : ''  )."
	) as total_cout_homme
	
	
			FROM ".MAIN_DB_PREFIX."projet as p 
	 ";

	if($idprojet > 0) $sql.= " WHERE p.rowid = ".$idprojet;
	
	$sql.=" ORDER BY p.title";
	
//	$PDOdb->debug=true;
	$PDOdb->Execute($sql);

	$TRapport = array();
	$PDOdb2 = new TPDOdb;
	
	while ($PDOdb->Get_line()) {
		//echo ($conf->global->DOC2PROJECT_NB_HOURS_PER_DAY*60*60).'<br>';
		//echo $PDOdb->Get_field('total_temps')." ".($conf->global->DOC2PROJECT_NB_HOURS_PER_DAY*60*60).'<br>';
		if($conf->ndfp->enabled){
			$marge = $PDOdb->Get_field('total_vente') - $PDOdb->Get_field('total_achat') - $PDOdb->Get_field('total_ndf') - $PDOdb->Get_field('total_cout_homme');
		}
		else{
			$marge = $PDOdb->Get_field('total_vente') - $PDOdb->Get_field('total_achat') - $PDOdb->Get_field('total_cout_homme');
		}
		
		//if($marge!=0) {
			if($conf->ndfp->enabled){
				$TRapport[]= array(
					"IdProject" => $PDOdb->Get_field('IdProject'),
					"total_propal" => $PDOdb->Get_field('total_propal'),
					"total_vente" => $PDOdb->Get_field('total_vente'),
					"total_achat" => $PDOdb->Get_field('total_achat'),
					"total_ndf" => $PDOdb->Get_field('total_ndf'),
					"total_temps" => $PDOdb->Get_field('total_temps'),
					"total_cout_homme" => $PDOdb->Get_field('total_cout_homme'),
					'total_temps_plannif'=> $PDOdb->Get_field('total_temps_plannif'),
					"marge" => $marge
				);
			}
			else{
				$TRapport[]= array(
					"IdProject" => $PDOdb->Get_field('IdProject'),
					"total_temps" => $PDOdb->Get_field('total_temps'),
					"total_vente" => $PDOdb->Get_field('total_vente'),
					"total_achat" => $PDOdb->Get_field('total_achat'),
					'total_temps_plannif'=> $PDOdb->Get_field('total_temps_plannif'),
					"total_propal" => $PDOdb->Get_field('total_propal'),
					"total_cout_homme" => $PDOdb->Get_field('total_cout_homme'),
					"marge" => $marge
				);
			}
			
			
		//}
	}
	
	//pre($TRapport,true);
	
	_print_statistiques_projet($TRapport);

}

function _print_statistiques_projet(&$TRapport){
	global $conf, $db;
	
	dol_include_once('/core/lib/date.lib.php');
	dol_include_once('/projet/class/project.class.php');
	
	
	?>
	<div class="tabBar" style="padding-bottom: 25px;">
		<table id="statistiques_projet" class="noborder" width="100%">
			<thead>
				<tr style="text-align:center;" class="liste_titre nodrag nodrop">
					<td>Réf. Projet</td>
					<td>Total propal (€)</td>
					<td>Total vente (€)</td>
					<td>Total achat (€)</td>
					<?php if($conf->ndfp->enabled){ ?><td>Total Note de frais (€)</td><?php } ?> 
					<td>Total temps passé (JH)</td>
					<td>Total temps prévu (JH)</td>
					<td>Total coût MO (€)</td>
					<td>Rentabilité</td>
				</tr>
			</thead>
			<tbody>
				<?php
				
				foreach($TRapport as $line){
					
					if($line['total_temps_plannif'] == 0 && $line['total_temps'] == 0) continue;

					$project=new Project($db);
					$project->fetch($line['IdProject']);
					
					?>
					<tr>
						<td><?php echo $project->getNomUrl(1,'',1)  ?></td>
						<td nowrap="nowrap"><?php echo price(round($line['total_propal'],2)) ?></td>
						<td nowrap="nowrap"><?php echo price(round($line['total_vente'],2)) ?></td>
						<td nowrap="nowrap"><?php echo price(round($line['total_achat'],2)) ?></td>
						<?php if($conf->ndfp->enabled){ ?><td nowrap="nowrap"><?php echo price(round($line['total_ndf'],2)) ?></td><?php } ?> 
						<td nowrap="nowrap"><?php echo convertSecondToTime($line['total_temps'],'all',$conf->global->DOC2PROJECT_NB_HOURS_PER_DAY*60*60) ?></td>
						<td<?php echo ($line['total_temps']>$line['total_temps_plannif']) ? ' style="color:red;font-weight: bold" ' : ' style="color:green" ' ?> nowrap="nowrap"><?php echo convertSecondToTime($line['total_temps_plannif'],'all',$conf->global->DOC2PROJECT_NB_HOURS_PER_DAY*60*60) ?></td>
						<td nowrap="nowrap"><?php echo price(round($line['total_cout_homme'],2)) ?></td>
						<td<?php echo ($line['marge'] < 0) ? ' style="color:red;font-weight: bold" ' : ' style="color:green" ' ?> nowrap="nowrap"><?php echo price(round($line['marge'],2)) ?></td>
					</tr>
					<?php
					$total_vente += $line['total_vente'];
					$total_achat += $line['total_achat'];
					if($conf->ndfp->enabled)$total_ndf += $line['total_ndf'];
					$total_temps += $line['total_temps'];
					$total_temps_plannif+=$line['total_temps_plannif'];
					$total_cout_homme += $line['total_cout_homme'];
					$total_marge += $line['marge'];
				}
				?>
			</tbody>
			<tfoot>
				<tr style="font-weight: bold;">
					<td>Totaux</td>
					<td><?php /*echo price($total_vente)*/ ?></td>
					<td><?php echo price($total_vente) ?></td>
					<td><?php echo price($total_achat) ?></td>
					<?php if($conf->ndfp->enabled){ ?><td><?php echo price($total_ndf) ?></td><?php } ?> 
					<td><?php echo convertSecondToTime($total_temps,'all',$conf->global->DOC2PROJECT_NB_HOURS_PER_DAY*60*60) ?></td>
					<td><?php echo convertSecondToTime($total_temps_plannif,'all',$conf->global->DOC2PROJECT_NB_HOURS_PER_DAY*60*60) ?></td>
					<td><?php echo price(round($total_cout_homme)) ?></td>
					<td<?php echo ($total_marge < 0) ? ' style="color:red" ' : ' style="color:green" ' ?>><?php echo price(round($total_marge)) ?></td>
				</tr>
			</tfoot>
		</table>
	</div>
	<?php
}
