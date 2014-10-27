<?php
class ActionsDoc2Project
{
	// Affichage du bouton d'action
	function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
	{
		global $conf,$langs,$db,$user;
		
		if($user->rights->projet->all->creer &&
			(in_array('propalcard',explode(':',$parameters['context'])) && $conf->global->DOC2PROJECT_DISPLAY_ON_PROPOSAL && $object->statut == 2)
			|| (in_array('ordercard',explode(':',$parameters['context'])) && $conf->global->DOC2PROJECT_DISPLAY_ON_ORDER && $object->statut == 1)
		)
		{
			$langs->load('doc2project@doc2project');
			$link = $_SERVER["PHP_SELF"] . '?id=' . $object->id . '&action=create_project';
			$label = empty($object->fk_project) ? $langs->trans('CreateProjectAndTasks') : $langs->trans('CreateTasksInProject');
			print '<div class="inline-block divButAction"><a class="butAction" href="' . $link . '">' . $label . '</a></div>';
		}
		
		return 0;
	}
	
	function doActions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf,$langs,$db,$user;
		
		if($user->rights->projet->all->creer && $action == 'create_project' &&
			(in_array('propalcard',explode(':',$parameters['context'])) && $object->statut == 2)
			|| (in_array('ordercard',explode(':',$parameters['context'])) && $object->statut == 1)
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
				$p->ref				= $this->_get_project_ref($p);
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
					
					$t->fetch(0, $ref);
					if($t->id==0) {
						
						$t->fk_project = $p->id;
						$t->ref = $ref;
						$t->label = $line->product_label;
						$t->description = $line->desc;
						
						$t->date_start = $start;
						$t->date_end = $end;
						$t->fk_task_parent = 0;
						$t->planned_workload = $durationInSec;
						
						$t->create($user);
					}
					
					$start = strtotime('+1 weekday', $end);
				}
			}
			
			// LIEN OBJECT / PROJECT
			$p->date_end = $end;
			if($resetProjet) $p->statut = 0;
			$p->update($user);
			$object->setProject($p->id);
			
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