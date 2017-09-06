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
	echo '<table class="noborder">';
	
	switch ($report) {
		case 'statistiques_projet':
			_print_filtre_liste_projet($form,$PDOdb);
			_print_filtre_plage_date($form);
			break;
		
		default:
			break;
	}
	
	echo '<tr><td colspan="2" align="left">'.$form->btsubmit('Valider', 'valider').'</td></tr>';
	echo '</table>';
	
	echo '</div>';
}

function _get_statistiques_projet(&$PDOdb){
	global $db,$conf;

	$date_deb = GETPOST('date_deb');
	$t_deb = !$date_deb ? 0 : Tools::get_time($date_deb);

	$date_fin = GETPOST('date_fin');
	$t_fin = !$date_fin ? 0 : Tools::get_time($date_fin);

	// Sous requête parce que : si pas de thm sur le temps saisie, alors prendre celui actuellement sur la fiche user (Ticket 1504)
	$sql_thm_fiche_user = "SELECT u.thm 
						   FROM ".MAIN_DB_PREFIX."user u
						   WHERE u.rowid = tt.fk_user";

	$sql = "SELECT p.rowid as IdProject, p.ref, p.title
	, (
		SELECT SUM(pp.total_ht) FROM ".MAIN_DB_PREFIX."propal as pp WHERE pp.fk_projet = p.rowid AND pp.fk_statut IN(2, 4)
		".($t_deb>0 && $t_fin>0 ? " AND datep BETWEEN '".date('Y-m-d', $t_deb)."' AND '".date('Y-m-d', $t_fin)."' " : ''  )."
		) as total_devis
	,(
		SELECT SUM(f.total) FROM ".MAIN_DB_PREFIX."facture as f WHERE f.fk_projet = p.rowid AND f.fk_statut IN(1, 2)
		".($t_deb>0 && $t_fin>0 ? " AND datef BETWEEN '".date('Y-m-d', $t_deb)."' AND '".date('Y-m-d', $t_fin)."' " : ''  )."
		) as total_vente
	, (
		SELECT SUM(ff.total_ht) FROM ".MAIN_DB_PREFIX."facture_fourn as ff WHERE ff.fk_projet = p.rowid AND ff.fk_statut >= 1 
		".($t_deb>0 && $t_fin>0 ? " AND datef BETWEEN '".date('Y-m-d', $t_deb)."' AND '".date('Y-m-d', $t_fin)."' " : ''  )."
	) as total_achat
	, (
		SELECT SUM(ndfp.total_ht) FROM ".MAIN_DB_PREFIX."ndfp as ndfp WHERE ndfp.fk_project = p.rowid AND ndfp.statut >= 1  
		".($t_deb>0 && $t_fin>0 ? " AND datef BETWEEN '".date('Y-m-d', $t_deb)."' AND '".date('Y-m-d', $t_fin)."' " : ''  )."
	) as total_ndf
	, (SELECT SUM(tt.task_duration) FROM ".MAIN_DB_PREFIX."projet_task_time as tt WHERE tt.fk_task IN (
			SELECT t.rowid FROM ".MAIN_DB_PREFIX."projet_task as t WHERE t.fk_projet = p.rowid AND t.rowid NOT IN (175)
			)
		".($t_deb>0 && $t_fin>0 ? " AND task_date BETWEEN '".date('Y-m-d', $t_deb)."' AND '".date('Y-m-d', $t_fin)."' " : ''  )."
	) as total_temps
	, (SELECT SUM(pt.planned_workload) FROM ".MAIN_DB_PREFIX."projet_task as pt WHERE pt.fk_projet = p.rowid
	) as total_temps_prevu
	,(SELECT SUM(IFNULL(tt.thm, ($sql_thm_fiche_user)) * tt.task_duration/3600) FROM ".MAIN_DB_PREFIX."projet_task_time as tt WHERE tt.fk_task IN (
			SELECT t.rowid FROM ".MAIN_DB_PREFIX."projet_task as t WHERE t.fk_projet = p.rowid AND t.rowid NOT IN (175))
		".($t_deb>0 && $t_fin>0 ? " AND task_date BETWEEN '".date('Y-m-d', $t_deb)."' AND '".date('Y-m-d', $t_fin)."' " : ''  )."
	) as total_cout_homme
	
	
			FROM ".MAIN_DB_PREFIX."projet as p 
	 ";
	
	$sql.=" ORDER BY p.ref";
	
	$PDOdb->Execute($sql);

	$TRapport = array();
	$PDOdb2 = new TPDOdb;
	
	while ($PDOdb->Get_line()) {
		//echo ($conf->global->DOC2PROJECT_NB_HOURS_PER_DAY*60*60).'<br>';
		//echo $PDOdb->Get_field('total_temps')." ".($conf->global->DOC2PROJECT_NB_HOURS_PER_DAY*60*60).'<br>';
		
		$marge = $PDOdb->Get_field('total_vente') - ($PDOdb->Get_field('total_achat') + $PDOdb->Get_field('total_ndf') + $PDOdb->Get_field('total_cout_homme'));
		//if($marge!=0) {
			$TRapport[]= array(
				"IdProject" => $PDOdb->Get_field('IdProject'),
				"total_devis" => $PDOdb->Get_field('total_devis'),
				"total_vente" => $PDOdb->Get_field('total_vente'),
				"total_achat" => $PDOdb->Get_field('total_achat'),
				"total_ndf" => $PDOdb->Get_field('total_ndf'),
				"total_temps" => $PDOdb->Get_field('total_temps'),
				"total_temps_prevu" => $PDOdb->Get_field('total_temps_prevu'),
				"total_diff_temps" => abs($PDOdb->Get_field('total_temps_prevu')-$PDOdb->Get_field('total_temps')),
				"total_cout_homme" => $PDOdb->Get_field('total_cout_homme'),
				"marge" => $marge
			);
			
			
		//}
	}
	
	//pre($TRapport,true);
	
	_print_statistiques_projet($TRapport);

}

function _print_statistiques_projet(&$TRapport){
	global $conf, $db;
	
	dol_include_once('/core/lib/date.lib.php');
	dol_include_once('/projet/class/project.class.php');
	
	$idprojet = GETPOST('id_projet');
	?>
	<div class="tabBar" style="padding-bottom: 5px;">
		<table class="noborder" width="100%">
			<thead>
				<tr style="text-align:left;" class="liste_titre nodrag nodrop">
					<td width="138">Réf. affaire</td>
					<td width="110"><img src="./img/info.png" title="Total HT des devis signés (à la date de saisie)"> Commandes HT</td>
					<td width="110"><img src="./img/info.png" title="Total HT des factures clients émises (à la date de saisie)"> Factures HT</td>
					<td width="110"><img src="./img/info.png" title="Total HT des factures fournisseurs et sous-traitants enregistrées (à la date de saisie)"> Achats HT</td>
					<td width="110"><img src="./img/info.png" title="Total HT des notes de frais enregistrées (à la date de saisie)"> NdF HT</td>
					<td width="110"><img src="./img/info.png" title="Total heures prévues (lors de la création des tâches)"> Tps prévu</td>
					<td width="110"><img src="./img/info.png" title="Total heures passées (lors de la saisie des temps consommés)"> Tps passé</td>
					<td width="110"><img src="./img/info.png" title="Temps prévu - temps passé"> Tps restant</td>
					<td><img src="./img/info.png" title="Valorisation HT du total temps consommé (H) x Taux Horaire Moyen de chaque intervenant"> Total MO HT</td>
					<td><img src="./img/info.png" title="Marge brute : total HT (factures clients émises - (achats + notes de frais + coût main d'oeuvre)) HORS IMPUTATION FRAIS GENERAUX (00_37)"> Marge brute HT</td>
					<td><img src="./img/info.png" title="IMPUTATION FRAIS GENERAUX (00_37)"> FG € HT</td>
					<td><img src="./img/info.png" title="Marge nette : total HT (factures clients émises - (achats + notes de frais + coût main d'oeuvre)) INCLUS IMPUTATION FRAIS GENERAUX (00_37)"> Marge nette HT</td>
					<td><img src="./img/info.png" title="Coefficient de vente (ou marge)"> Kv</td>
					<td><img src="./img/info.png" title="Total HT des devis signés restant à facturer"> Reste HT</td>
				</tr>
			</thead>
		</table>
	</div>
	<div class="noborder" style="display:block; overflow:scroll; max-height:400px;">
		<table id="statistiques_projet" class="noborder" width="100%">

			<tbody>

				<?php
				foreach($TRapport as $k => $line){
					$total_vente += $line['total_vente'];
					if($line['IdProject'] == 5){
						$margeBruteFraisGeneraux = round($line['marge'],2);
					}
					
					if($idprojet > 0 && $idprojet != $line['IdProject']){
						unset($TRapport[$k]);
					}
					else{
						$kprojet = $k;
					}
				}
				
				//pre($TRapport,true);exit;
				
				foreach($TRapport as $line){
					
					$project=new Project($db);
					$project->fetch($line['IdProject']);
					
					$affectationFrais = $line['total_vente'] / abs($total_vente) * abs($margeBruteFraisGeneraux);
					$marge_net = $line['marge'] - $affectationFrais;
					//echo $marge_net." -------- ".($line['marge'] - $affectationFrais).'<br>';
					$kv = $line['total_vente'] / ($line['total_achat'] + $line['total_ndf'] + $line['total_cout_homme'] + $affectationFrais);
					?>
					<tr style="text-align:left;">
					<style>
					tbody tr:nth-last-child(2n) { background-color: #EBEBEB; }
					</style>
						<td width="155"><?php echo $project->getNomUrl(1,'',1)  ?></td>
						<td width="100"><?php echo ($project->ref == "00_37") ? "" : price(round($line['total_devis'],2)) ?></td>
						<td width="97"><?php echo price(round($line['total_vente'],2)) ?></td>
						<td width="96"><?php echo price(round($line['total_achat'],2)) ?></td>
						<td width="96"><?php echo price(round($line['total_ndf'],2)) ?></td>
						<td width="94"><?php echo ($project->ref == "00_37") ? "" : convertSecondToTime($line['total_temps_prevu'],'all',$conf->global->DOC2PROJECT_NB_HOURS_PER_DAY*60*60) ?></td>
						<td width="94"><?php echo convertSecondToTime($line['total_temps'],'all',$conf->global->DOC2PROJECT_NB_HOURS_PER_DAY*60*60) ?></td>
						<td width="100"<?php echo ($line['total_temps_prevu'] < $line['total_temps']) ? ' style="color:red;font-weight: bold" ' : ' style="color:green" ' ?>><?php echo ($project->ref == "00_37") ? "" : convertSecondToTime($line['total_diff_temps'],'all',$conf->global->DOC2PROJECT_NB_HOURS_PER_DAY*60*60) ?></td>
						<td width="108"><?php echo price(round($line['total_cout_homme'],2)) ?></td>
						<td width="129"<?php echo ($line['marge'] < 0) ? ' style="color:red;font-weight: bold" ' : ' style="color:green" ' ?> nowrap="nowrap"><?php echo price(round($line['marge'],2)) ?></td>
						
						<td width="81"><?php echo ($project->ref == "00_37") ? "" : price(round($affectationFrais,2)); ?></td>
						<td width="124"<?php echo ( $marge_net < 0) ? ' style="color:red;font-weight: bold" ' : ' style="color:green" ' ?> nowrap="nowrap"><?php echo ($project->ref == "00_37") ? "" : price(round($marge_net,2)); ?></td>
												
						<td width="37"<?php echo (round($kv,2) < 1) ? ' style="color:red;font-weight: bold" ' : ' style="color:green" ' ?>><?php echo round($kv,2); ?> </td>
						<td nowrap="nowrap"><?php echo ($project->ref == "00_37") ? "" : price(round($line['total_devis'] - $line['total_vente'],2)); ?></td>
					
					</tr>
					<?
					$total_devis += $line['total_devis'];
					$total_achat += $line['total_achat'];
					$total_ndf += $line['total_ndf'];
					$total_temps += $line['total_temps'];
					$total_temps_prevu += $line['total_temps_prevu'];
					$total_cout_homme += $line['total_cout_homme'];
					$total_marge += $line['marge'];
					$total_frais_generaux += $affectationFrais;
					$total_marge_net += $marge_net;
				}
				//echo " **** ".$total_marge_net." ------- ".$total_marge.'<br>';
				if($idprojet > 0){
					$total_vente = $TRapport[$kprojet]['total_vente'];
				}
				else{
					$total_marge_net = $total_marge_net - $margeBruteFraisGeneraux;
				}
				
				?>
			</tbody>

		</table>
	</div>
	<div class="noborder" style="display:block;">
		<table id="statistiques_projet" class="noborder" width="100%" style="background-color:#FFE1E1">
				<tfoot>
				<tr style="font-weight: bold;" style="text-align:left;">
					<td width="167" style="text-transform: uppercase;">total activité</td>
					<td width="115"><?php echo price($total_devis) ?></td>
					<td width="108"><?php echo price($total_vente) ?></td>
					<td width="111"><?php echo price($total_achat) ?></td>
					<td width="110"><?php echo price($total_ndf) ?></td>
					<td width="108"><?php echo convertSecondToTime($total_temps_prevu,'all',$conf->global->DOC2PROJECT_NB_HOURS_PER_DAY*60*60) ?></td>
					<td width="110"><?php echo convertSecondToTime($total_temps,'all',$conf->global->DOC2PROJECT_NB_HOURS_PER_DAY*60*60) ?></td>
					<td width="112"<?php echo ($total_temps_prevu < $total_temps) ? ' style="color:red;font-weight: bold" ' : ' style="color:green" ' ?> nowrap="nowrap"><?php echo convertSecondToTime(abs($total_temps_prevu - $total_temps),'all',$conf->global->DOC2PROJECT_NB_HOURS_PER_DAY*60*60) ?></td>
					<td width="122"><?php echo price(round($total_cout_homme,2)) ?></td>
					<td width="144"<?php echo ($total_marge < 0) ? ' style="color:red" ' : ' style="color:green" ' ?>><?php echo price(round($total_marge,2)) ?></td>
					<td width="92"><?php echo price(round($total_frais_generaux,2)); ?></td>
					<td width="141"<?php 
						$total_marge_net = $total_marge - $total_frais_generaux;
						echo ($total_marge_net < 0) ? ' style="color:red" ' : ' style="color:green" ' ?>><?php echo price(round($total_marge_net,2)) ?></td>
					<td width="49"<?php echo (round(($total_vente / ($total_achat + $total_ndf + $total_cout_homme + $total_frais_generaux)),2) < 1) ? ' style="color:red;font-weight: bold" ' : ' style="color:green" ' ?>><?php echo round(($total_vente / ($total_achat + $total_ndf + $total_cout_homme + $total_frais_generaux)),2); ?> </td>
					<td><?php echo price(round($total_devis - $total_vente,2)) ?></td>
				</tr>
			</tfoot>
		</table>
	</div>
	<?php
}
