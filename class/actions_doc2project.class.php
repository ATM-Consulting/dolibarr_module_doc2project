<?php
class ActionsDoc2Project
{
	// Affichage du bouton d'action => 3.6 uniquement.....
	function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
	{
		global $conf,$langs,$db,$user;
		
		if($user->rights->projet->all->creer &&
			(in_array('propalcard',explode(':',$parameters['context'])) && $conf->global->DOC2PROJECT_DISPLAY_ON_PROPOSAL && $object->statut == 2)
			|| (in_array('ordercard',explode(':',$parameters['context'])) && $conf->global->DOC2PROJECT_DISPLAY_ON_ORDER && $object->statut == 1)
		)
		{
			if((float)DOL_VERSION>=3.6) {
				$langs->load('doc2project@doc2project');
				$link = $_SERVER["PHP_SELF"] . '?id=' . $object->id . '&action=create_project';
				$label = empty($object->fk_project) ? $langs->trans('CreateProjectAndTasks') : $langs->trans('CreateTasksInProject');
				print '<div class="inline-block divButAction"><a class="butAction" href="' . $link . '">' . $label . '</a></div>';
			}
		}
		
		return 0;
	}
	
	function formObjectOptions($parameters, &$object, &$action, $hookmanager) {
		
		global $langs,$db,$user,$conf;
		
		if($user->rights->projet->all->creer &&
			(in_array('propalcard',explode(':',$parameters['context'])) && $conf->global->DOC2PROJECT_DISPLAY_ON_PROPOSAL && $object->statut == 2)
			|| (in_array('ordercard',explode(':',$parameters['context'])) && $conf->global->DOC2PROJECT_DISPLAY_ON_ORDER && $object->statut == 1)
		)
		{
			$langs->load('doc2project@doc2project');
			$link = $_SERVER["PHP_SELF"] . '?id=' . $object->id . '&action=create_project';
			$label = empty($object->fk_project) ? $langs->trans('CreateProjectAndTasks') : $langs->trans('CreateTasksInProject');
			?>
			<script type="text/javascript">
				$(document).ready(function(){
					$('.tabsAction').append('<?php echo '<div class="inline-block divButAction"><a class="butAction" href="' . $link . '">' . $label . '</a></div>'; ?>');
				});
			</script>
			<?php
		}
		
		if(in_array('projectcard',explode(':',$parameters['context'])) && $object->id > 0) {
			$langs->load('doc2project@doc2project');
			
			dol_include_once('/comm/propal/class/propal.class.php');
			dol_include_once('/fourn/class/fournisseur.facture.class.php');
			dol_include_once('/core/lib/date.lib.php');
			
			$propalTotal=$otherExpenses=0;
			$Tab = $object->get_element_list('propal', 'propal');
			if(is_array($Tab)){
				foreach($Tab as $id) {
					$propal=new Propal($db);
					$propal->fetch($id);
					
					if($propal->statut == 2 || $propal->statut == 4) $propalTotal+=$propal->total_ht;
				}
			}
			
			$Tab = $object->get_element_list('facturefourn', 'facture_fourn');
			if(is_array($Tab)){
				foreach($Tab as $id) {
				
					$f=new FactureFournisseur($db);
					$f->fetch($id);
					
					$otherExpenses+=$f->total;
					
				}
			}
			
			$sql = "SELECT total_ht FROM " . MAIN_DB_PREFIX . "ndfp WHERE fk_project=" . $object->id;
			$res=$db->query($sql);
			while($obj=$db->fetch_object($res)) {
				$otherExpenses+=$obj->total_ht;				
			}			
			
			
			$resultset = $db->query("SELECT SUM(tt.task_duration) as duration_effective, SUM(tt.thm * tt.task_duration/3600) as costprice  
			FROM ".MAIN_DB_PREFIX."projet_task_time tt LEFT JOIN ".MAIN_DB_PREFIX."projet_task t ON (t.rowid=tt.fk_task)
			WHERE t.fk_projet=".$object->id);
			$obj=$db->fetch_object($resultset);
			
			
			$marge = $propalTotal - $obj->costprice - $otherExpenses;
			
			?>
			<tr>
				<td><?php echo $langs->trans('DurationEffective'); ?> (Jours Homme)</td>
				<td><?php echo convertSecondToTime( $obj->duration_effective,'all',$conf->global->DOC2PROJECT_NB_HOURS_PER_DAY*60*60) ?></td>
				
			</tr>
			<tr>
				<td><?php echo $langs->trans('CostEffective'); ?></td>
				<td><?php echo price($obj->costprice) ?></td>
			</tr>
			<tr>
				<td><?php echo $langs->trans('OtherExpenses'); ?></td>
				<td><?php echo price($otherExpenses) ?></td>
			</tr>
			<tr>
				<td><?php echo $langs->trans('TotalPropal'); ?></td>
				<td><?php echo price($propalTotal) ?></td>
			</tr>
			<!-- <tr>
				<td><?php echo $langs->trans('TotalBill'); ?></td>
				<td><?php echo price($billsTotal) ?></td>
			</tr>-->
			<tr>
				<td><?php echo $langs->trans('Margin'); ?></td>
				<td><?php echo price($marge) ?></td>
			</tr>
			
			<?php
			
		}
		else if(in_array('projecttaskcard',explode(':',$parameters['context']))) {
			$langs->load('doc2project@doc2project');
			//$object->duration_effective souvent faux :-/ recalcule en requête
			dol_include_once('/product/class/product.class.php');
			
			$resultset = $db->query("SELECT SUM(task_duration) as duration_effective, SUM(thm * task_duration/3600) as costprice  FROM ".MAIN_DB_PREFIX."projet_task_time WHERE fk_task=".$object->id);
			$obj=$db->fetch_object($resultset);
		
			?>
			<tr>
				<td><?php echo $langs->trans('DurationEffective'); ?></td>
				<td><?php echo convertSecondToTime($obj->duration_effective) ?></td>
				
			</tr>
			<tr>
				<td><?php echo $langs->trans('CostEffective'); ?></td>
				<td><?php echo price($obj->costprice) ?></td>
				
			</tr>
			
			<?php
			if($object->array_options['options_linkservice'] > 0){
				$product_static=new Product($db);
				$product_static->fetch($object->array_options['options_linkservice']);
				?>
				<tr>
					<td><?php echo $langs->trans('LinkService'); ?></td>
					<td><?php print $product_static->getNomUrl(1,'',24); ?></td>
				</tr>
			<?php
			}
			
		}
		else if(in_array('usercard',explode(':',$parameters['context']))) {
			
			if((float)DOL_VERSION>=3.6) {
				$thm = $object->thm;
			}
			else{
				$resql = $db->query('SELECT thm FROM '.MAIN_DB_PREFIX.'user WHERE rowid = '.$object->id);
				$res = $db->fetch_object($resql);
				$thm = $res->thm;
			}
			?>
			<tr>
				<td><?php echo $langs->trans('THM'); ?></td>
				<td><?php 
				
					if($action=='edit') {
						echo '<input id="thm" type="text" value="'.$thm.'" maxlength="11" size="9" name="thm">';
					}
					else{
						echo price($thm);
					}

				?></td>
				
			</tr>
			<?
		}

	}
	
	function doActions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf,$langs,$db,$user;
		$langs->load('doc2project@doc2project');
		
		if($user->rights->projet->all->creer && $action == 'create_project' &&
			((in_array('propalcard',explode(':',$parameters['context'])) && $object->statut == 2)
			|| (in_array('ordercard',explode(':',$parameters['context'])) && $object->statut == 1))
		)
		{
			dol_include_once('/projet/class/project.class.php');
			dol_include_once('/projet/class/task.class.php');
			
			$p = new Project($db);

			// CREATION OU CHARGEMENT DU PROJET
			if(empty($object->fk_project)) {
				
				// Création du projet
				$p->title			= (!empty($object->ref_client)) ? $object->ref_client : $object->thirdparty->name.' - '.$object->ref.' '.$langs->trans('DocConverted');
				$p->socid			= $object->socid;
				$p->statut			= 0;
				$p->date_start		= dol_now();
				$p->date_end		= $object->date_livraison;
				$p->ref				= $this->_get_project_ref($p);
				/*echo '<pre>';
				print_r($p);exit;*/
				$p->create($user);
			} else {
				$p->fetch($object->fk_project);
			}
			
			$start = strtotime('today'); // La 1ère tâche démarre à la même date que la date de début du projet
			
			// CREATION DES TACHES
			foreach($object->lines as $line) {
				if(!empty($line->fk_product) && $line->fk_product_type == 1) { // On ne créé que les tâches correspondant à des services
					$s = new Product($db);
					$s->fetch($line->fk_product);
					
					// On part du principe que les services sont vendus à l'heure ou au jour. Pas au mois ni autre.
					$durationInSec = $line->qty * $s->duration_value * 3600;
					$nbDays = 0;
					if($s->duration_unit == 'd') { // Service vendu au jour, la date de fin dépend du nombre de jours vendus
						$durationInSec *= $conf->global->DOC2PROJECT_NB_HOURS_PER_DAY;
						$nbDays = $line->qty * $s->duration_value;
					} else if($s->duration_unit == 'h') { // Service vendu à l'heure, la date de fin dépend du nombre d'heure vendues
						$nbDays = ceil($line->qty * $s->duration_value / $conf->global->DOC2PROJECT_NB_HOURS_PER_DAY);
					}
					
					$end = strtotime('+'.$nbDays.' weekdays', $start);
					
					$t = new Task($db);
					$ref = $conf->global->DOC2PROJECT_TASK_REF_PREFIX.$line->rowid;
					echo $ref.'<br>';
					/*echo '<pre>';
					print_r($s);exit;*/
					$t->fetch(0, $t);
					if($t->id==0) {
						
						$t->fk_project = $p->id;
						
						$obj = empty($conf->global->PROJECT_TASK_ADDON)?'mod_task_simple':$conf->global->PROJECT_TASK_ADDON;
						require_once DOL_DOCUMENT_ROOT ."/core/modules/project/task/".$conf->global->PROJECT_TASK_ADDON.'.php';
						$modTask = new $obj;
						$defaultref = $modTask->getNextValue($soc,$object);
						
						$t->ref = $defaultref;
						$t->label = $line->product_label;
						$t->description = $line->product_desc;
						
						$t->date_start = $start;
						$t->date_end = $end;
						$t->fk_task_parent = 0;
						
						//Gestion spécifique GPC => calcul de la charge de travail prévue
						// temps prévisionnel = qty ligne (nb de mot) / mph tâche (extrafield tâche)
						$t->planned_workload = convertTime2Seconds($line->qty / $s->array_options['options_wph']);
						
						$t->array_options['options_soldprice'] = $line->total_ht;
						
						//Gestion spécifique GPC => extrafields
						$t->array_options['options_wordnumber'] = $line->qty;
						$t->array_options['options_linkservice'] = $line->fk_product;

						$t->create($user);

						//Gestion spécifique GPC => création tâche relecture
						if($s->array_options['options_proofread'] == 2){
							$relecture = new Task($db);
							$relecture = clone $t;
							
							$modTask2 = new $obj;
							$defaultref2 = $modTask2->getNextValue($soc,$object);

							$relecture->ref = $defaultref2;
							
							$relecture->label = "Relecture - ".$relecture->label;
							$relecture->planned_workload = convertTime2Seconds(($line->qty / $s->array_options['options_wph']) / 4);
							
							$relecture->create($user);
						}
					}
					
					$start = strtotime('+1 weekday', $end);
				}
			}
			
			// LIEN OBJECT / PROJECT
			/*$p->date_end = $end;
			if($resetProjet) $p->statut = 0;
			$p->update($user);
			$object->setProject($p->id);*/
			
			header('Location:'.dol_buildpath('/projet/tasks.php?id='.$p->id,2));
		}
		
		return 0;
	}
	
	function _get_project_ref(&$project) {
		global $conf;
		$project->fetch_thirdparty();
		
		$defaultref='';
		$modele = empty($conf->global->PROJECT_ADDON)?'mod_project_simple':$conf->global->PROJECT_ADDON;
		
		// Search template files
		$file=''; $classname=''; $filefound=0;
		$dirmodels=array_merge(array('/'),(array) $conf->modules_parts['models']);
		foreach($dirmodels as $reldir)
		{
			$file=dol_buildpath($reldir."core/modules/project/".$modele.'.php',0);
			if (file_exists($file))
			{
				$filefound=1;
				$classname = $modele;
				break;
			}
		}
	
		if ($filefound)
		{
			$result=dol_include_once($reldir."core/modules/project/".$modele.'.php');
			$modProject = new $classname;
	
			$defaultref = $modProject->getNextValue($project->thirdparty,$project);
		}
		
		return $defaultref;
	}
}