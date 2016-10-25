<?php
class ActionsDoc2Project
{
	// Affichage du bouton d'action => 3.6 uniquement.....
	function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
	{
		global $conf,$langs,$db,$user;

		if($user->rights->projet->all->creer &&
			((in_array('propalcard',explode(':',$parameters['context'])) && $conf->global->DOC2PROJECT_DISPLAY_ON_PROPOSAL && $object->statut == 2)
			|| (in_array('ordercard',explode(':',$parameters['context'])) && $conf->global->DOC2PROJECT_DISPLAY_ON_ORDER && $object->statut == 1))
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
			((in_array('propalcard',explode(':',$parameters['context'])) && $conf->global->DOC2PROJECT_DISPLAY_ON_PROPOSAL && $object->statut == 2)
			|| (in_array('ordercard',explode(':',$parameters['context'])) && $conf->global->DOC2PROJECT_DISPLAY_ON_ORDER && $object->statut == 1))
			&& (float)DOL_VERSION < 3.6
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

			if ($conf->ndfp->enabled)
			{
				$sql = "SELECT total_ht FROM " . MAIN_DB_PREFIX . "ndfp WHERE fk_project=" . $object->id;
				$res=$db->query($sql);

				while($obj=$db->fetch_object($res)) {
					$otherExpenses+=$obj->total_ht;
				}
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
			if(!empty($object->id))
			{
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
			<?php
		}

	}

	function doActions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf,$langs,$db,$user;

		if($user->rights->projet->all->creer && $action == 'create_project' &&
			((in_array('propalcard',explode(':',$parameters['context'])) && $object->statut == 2)
			|| (in_array('ordercard',explode(':',$parameters['context'])) && $object->statut == 1))
		)
		{
			$langs->load('doc2project@doc2project');

			define('INC_FROM_DOLIBARR', true);
			dol_include_once('/doc2project/config.php');
			dol_include_once('/projet/class/project.class.php');
			dol_include_once('/projet/class/task.class.php');


			$PDOdb = new TPDOdb;

			$p = new Project($db);

			// CREATION OU CHARGEMENT DU PROJET
			if(empty($object->fk_project)) {

				// Création du projet
				$p->title			= (!empty($object->ref_client)) ? $object->ref_client : $object->thirdparty->name.' - '.$object->ref.' '.$langs->trans('DocConverted');
				$p->socid			= $object->socid;
				$p->statut			= 0;
				$p->date_start		= dol_now();
				$p->ref				= $this->_get_project_ref($p);
				$p->create($user);
			} else {
				$p->fetch($object->fk_project);
			}

			$start = strtotime('today'); // La 1ère tâche démarre à la même date que la date de début du projet
			
			// CREATION DES TACHES
			foreach($object->lines as &$line) {
				$fk_parent = 0;
					
				if($line->fk_product>=0 && $line->product_type == 1) { // On ne créé que les tâches correspondant à des services
					
					if(!empty($line->ref)){//Test pour voir si c'est une saisie libre
						if ($this->isExclude($line)) continue;
					}
					if (!empty($conf->global->DOC2PROJECT_DO_NOT_CONVERT_SERVICE_WITH_PRICE_ZERO) && $line->subprice == 0) continue;
					if (!empty($conf->global->DOC2PROJECT_DO_NOT_CONVERT_SERVICE_WITH_QUANTITY_ZERO) && $line->qty == 0) continue;
										
					
					if($conf->global->DOC2PROJECT_CREATE_TASK_FOR_VIRTUAL_PRODUCT && $conf->global->PRODUIT_SOUSPRODUITS && ($line->ref != null))
					{

						$s = new Product($db);
						$s->fetch($line->fk_product);
						$s->get_sousproduits_arbo();
						$TProdArbo = $s->get_arbo_each_prod();

						if(!empty($TProdArbo)){

							if($conf->global->DOC2PROJECT_CREATE_TASK_FOR_PARENT){
								$fk_parent = $this->create_task($line,$p,$start,0,true);

								if($conf->workstation->enabled && $conf->global->DOC2PROJECT_WITH_WORKSTATION){
									dol_include_once('/workstation/class/workstation.class.php');

									$Tids = TRequeteCore::get_id_from_what_you_want($PDOdb, MAIN_DB_PREFIX."workstation_product",array('fk_product'=>$line->fk_product));

									foreach ($Tids as $workstationProductid) {
										$TWorkstationProduct = new TWorkstationProduct;
										$TWorkstationProduct->load($PDOdb, $workstationProductid);

										$TWorkstation = new TWorkstation;
										$TWorkstation->load($PDOdb, $TWorkstationProduct->fk_workstation);

										$line->fk_product = $line->fk_product;
										//$line->qty = $line->qty * $TWorkstationProduct->nb_hour;
										$line->product_label = $TWorkstation->name;
										$line->desc = '';
										$line->total_ht = 0;

										$this->create_task($line, $p, $start,$fk_parent,false,$TWorkstation->rowid);
									}
								}
							}

							foreach($TProdArbo as $prod){

								if($prod['type'] == 1){ //Uniquement les services

									$ss = new Product($db);
									$ss->fetch($prod['id']);
									$line->fk_product = $ss->id;
									$line->qty = $line->qty * $prod['nb'];
									$line->product_label = $prod['label'];
									$line->desc = ($ss->description) ? $ss->description : '';
									$line->total_ht = $ss->price;

									$new_fk_parent = $this->create_task($line,$p,$start,$fk_parent);

									if($conf->workstation->enabled && $conf->global->DOC2PROJECT_WITH_WORKSTATION){
										dol_include_once('/workstation/class/workstation.class.php');

										$Tids = TRequeteCore::get_id_from_what_you_want($PDOdb, MAIN_DB_PREFIX."workstation_product",array('fk_product'=>$ss->id));

										foreach ($Tids as $workstationProductid) {
											$TWorkstationProduct = new TWorkstationProduct;
											$TWorkstationProduct->load($PDOdb, $workstationProductid);

											$TWorkstation = new TWorkstation;
											$TWorkstation->load($PDOdb, $TWorkstationProduct->fk_workstation);

											$line->fk_product = $ss->id;
											$line->qty = $line->qty * $TWorkstationProduct->nb_hour;
											$line->product_label = $TWorkstation->name;
											$line->desc = '';
											$line->total_ht = 0;

											$this->create_task($line, $p, $start,$new_fk_parent,false,$TWorkstation->rowid);
										}
									}
								}
							}
						}else{
							$this->create_task($line,$p,$start);
						}
					}else{
						$this->create_task($line,$p,$start);
					}
				}
			}
			// LIEN OBJECT / PROJECT
			$p->date_end = $end;
			if($resetProjet) $p->statut = 0;
			$p->update($user);
			$object->setProject($p->id);
			if($conf->global->DOC2PROJECT_AUTO_AFFECT_PROJECTLEADER) $p->add_contact($user->id,'PROJECTLEADER','internal');
			//exit;
			header('Location:'.dol_buildpath('/projet/tasks.php?id='.$p->id,2));
		}

		return 0;
	}

	function isExclude(&$line)
	{
		global $conf;

		$TExclude = explode(';', $conf->global->DOC2PROJECT_EXCLUDED_PRODUCTS);
		if (in_array($line->ref, $TExclude)) return true;
		else return false;
	}

	function create_task(&$line,&$p,&$start,$fk_parent=0,$isParent=false,$fk_workstation=0){
		global $conf,$langs,$db,$user;

		$s = new Product($db);
		if($line->ref!=null){
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
		} else {
			
			$durationInSec = $line->qty *$conf->global->DOC2PROJECT_NB_HOURS_PER_DAY* 3600;
			
			$nbDays = 0;
			
			$nbDays = $line->qty;
			
		}
		$end = strtotime('+'.$nbDays.' weekdays', $start);
		
		$t = new Task($db);
		$ref = $conf->global->DOC2PROJECT_TASK_REF_PREFIX.$line->rowid;
		//echo $ref.'<br>';

		$t->fetch(0, $ref);
		if($t->id==0) {

			$t->fk_project = $p->id;

			$defaultref='';
			$obj = empty($conf->global->PROJECT_TASK_ADDON)?'mod_task_simple':$conf->global->PROJECT_TASK_ADDON;
			if (! empty($conf->global->PROJECT_TASK_ADDON) && is_readable(DOL_DOCUMENT_ROOT ."/core/modules/project/task/".$conf->global->PROJECT_TASK_ADDON.".php"))
			{
				$soc = new stdClass;
				require_once DOL_DOCUMENT_ROOT ."/core/modules/project/task/".$conf->global->PROJECT_TASK_ADDON.'.php';
				$modTask = new $obj;
				$defaultref = $modTask->getNextValue($soc,$p);
			}

			//echo $defaultref.'<br>';
			//Pour les tâches libres
			if($line->ref == null && $line->desc !=null && $conf->global->DOC2PROJECT_ALLOW_FREE_LINE){
				$t->ref = $defaultref;
				$t->label = $line->desc;
				$t->description = $line->desc;
				$t->fk_task_parent = $fk_parent;
				$t->date_start = $start;
				
				if($isParent)
				{
					$t->date_end = $start;
					$t->progress = 100;
				}
				else
				{
					$t->date_end = $end;
					$t->planned_workload = $durationInSec;
				}
				$t->array_options['options_soldprice'] = $line->total_ht;
	
				if($fk_workstation){
					$t->array_options['options_fk_workstation'] = $fk_workstation;
				}
			}else if($line->ref != null) {
				$t->ref = $defaultref;
				$t->label = $line->product_label;
				$t->description = $line->desc;
	
				$t->fk_task_parent = $fk_parent;
				$t->date_start = $start;
				if($isParent)
				{
					$t->date_end = $start;
					$t->planned_workload = 1;
					$t->progress = 100;
				}
				else
				{
					$t->date_end = $end;
					$t->planned_workload = $durationInSec;
				}
				$t->array_options['options_soldprice'] = $line->total_ht;
	
				if($fk_workstation){
					$t->array_options['options_fk_workstation'] = $fk_workstation;
				}
			}
		
			$t->create($user);
		}else {
			$t->fk_project = $p->id;
			$t->update($user);
		}

		$start = strtotime('+1 weekday', $end);

		return $t->id;
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
