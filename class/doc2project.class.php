<?php

class Doc2Project {
	
	public static function isExclude(&$line)
	{
		global $conf;
	
		$TExclude = explode(';', $conf->global->DOC2PROJECT_EXCLUDED_PRODUCTS);
		if (in_array($line->ref, $TExclude)) return true;
		else return false;
	}
	
	public static function get_project_ref(&$project) {
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
	
	public static function create_task(&$object,&$line,&$p,&$start,$fk_parent=0,$isParent=false,$fk_workstation=0){
		global $conf,$langs,$db,$user;
	
		$s = new Product($db);
		var_dump($conf->global->DOC2PROJECT_CONVERSION_RULE);exit;
		if(!empty($conf->global->DOC2PROJECT_CONVERSION_RULE)) {
				
			$eval = strtr($conf->global->DOC2PROJECT_CONVERSION_RULE,array(
						
					'{qty}'=>$line->qty
					,'{totalht}'=>$line->total_ht
						
			));
				
			$durationInSec = eval('return ('.$eval.');') * 3600;
			$nbDays = ceil(($durationInSec / 3600) / $conf->global->DOC2PROJECT_NB_HOURS_PER_DAY);
				
		}
		else if($line->ref!=null){
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
			$nbDays = $line->qty;
				
		}
		$end = strtotime('+'.$nbDays.' weekdays', $start);
	
		$t = new Task($db);
		$defaultref='';
		if(!empty($conf->global->DOC2PROJECT_TASK_REF_PREFIX)) {
			$defaultref = $conf->global->DOC2PROJECT_TASK_REF_PREFIX.$line->rowid;
		}
	
		if(empty($fk_workstation) && !empty($line->array_options['options_fk_workstation'])) {
			$fk_workstation = $line->array_options['options_fk_workstation'];
		}
	
		if(empty($fk_workstation) && !empty($object->array_options['options_fk_workstation'])) {
			$fk_workstation = $object->array_options['options_fk_workstation'];
		}
	
	
		if(!empty($defaultref)) $t->fetch(0, $defaultref);
		if($t->id==0) {
	
			$t->fk_project = $p->id;
				
			if(empty($defaultref)) {
				$obj = empty($conf->global->PROJECT_TASK_ADDON)?'mod_task_simple':$conf->global->PROJECT_TASK_ADDON;
				if (! empty($conf->global->PROJECT_TASK_ADDON) && is_readable(DOL_DOCUMENT_ROOT ."/core/modules/project/task/".$conf->global->PROJECT_TASK_ADDON.".php"))
				{
					$soc = new stdClass;
					require_once DOL_DOCUMENT_ROOT ."/core/modules/project/task/".$conf->global->PROJECT_TASK_ADDON.'.php';
					$modTask = new $obj;
					$defaultref = $modTask->getNextValue($soc,$p);
				}
	
			}
	
			//echo $defaultref.'<br>';
			//Pour les tâches libres
			if($line->ref == null && $line->desc !=null &&!empty( $conf->global->DOC2PROJECT_ALLOW_FREE_LINE )){
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
			$t->planned_workload = $durationInSec;
			$t->fk_project = $p->id;
			$t->update($user);
		}
	
		$start = strtotime('+1 weekday', $end);
	
		return $t->id;
	}
	
	public static function createProject(&$object) {
		
		if (!class_exists('Project')) dol_include_once('/projet/class/project.class.php');
		if (!class_exists('Task')) dol_include_once('/projet/class/task.class.php');
		
		if (!empty($object->fk_project))
		{
			$project = new Project($db);
			$r = $project->fetch($object->fk_project);
			if ($r > 0) return false;
		}
			
		$defaultref='';
		$modele = empty($conf->global->PROJECT_ADDON)?'mod_project_simple':$conf->global->PROJECT_ADDON;
			
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
			$langs->load('doc2project@doc2project');
		
			$project = new Project($db);
			$thirdparty=new Societe($db);
		
			dol_include_once($reldir."core/modules/project/".$modele.'.php');
			$modProject = new $classname;
		
			$project->ref 			 = $modProject->getNextValue($thirdparty, $project);
			$title = (!empty($object->ref_client)) ? $object->ref_client : $object->thirdparty->name.' - '.$object->ref.' '.$langs->trans('DocConverted');
			$project->title			 = $langs->trans('Doc2ProjectTitle', $title);
			$project->socid          = $object->socid;
			$project->description    = '';
			$project->public         = 1; // 0 = Contacts du projet  ||  1 = Tout le monde
			$project->datec			 = dol_now();
			$project->date_start	 = $object->date_livraison;
			$project->date_end		 = null;
		
			$r = $project->create($user);
			if ($r > 0)
			{
				$object->setProject($r);
				
				return $project;
				
				setEventMessage($langs->transnoentitiesnoconv('Doc2ProjectProjectCreated', $project->ref));
			}
			else
			{
				setEventMessage($langs->transnoentitiesnoconv('Doc2ProjectErrorCreateProject', $r), 'errors');
			}
		
		}
		else
		{
			setEventMessage($langs->transnoentitiesnoconv('Doc2ProjectErrorClassNotFoundProject', $file), 'errors');
		}
		
		return false;
		
	}
	
	public static function createTask2(&$object,&$line,&$p,&$start,$fk_parent=0,$isParent=false,$fk_workstation=0){
	{
		global $conf,$langs,$db,$user;
	
		// CREATION D'UNE TACHE GLOBAL POUR LA SAISIE DES TEMPS
		if (!empty($conf->global->DOC2PROJECT_CREATE_GLOBAL_TASK))
		{
			self::createOneTask($db, $user, $project->id, $conf->global->DOC2PROJECT_TASK_REF_PREFIX.'GLOBAL', $langs->trans('Doc2ProjectGlobalTaskLabel'), $langs->trans('Doc2ProjectGlobalTaskDesc'));
		}
	
		// Tableau qui va contenir à chaque indice (niveau du titre) l'id de la dernier tache parent
		// Par contre il faut les titres suivants correctement, T1 => T2 => T3 ... et pas de T1 => T3, dans ce cas T3 sera du même niveau que T1
		$TTask_id_parent = array();
		$index = 1;
	
		$fk_task_parent = 0;
		// CREATION DES TACHES PAR RAPPORT AUX LIGNES DE LA COMMANDE
		foreach($object->lines as $line)
		{
			if (!empty($conf->global->DOC2PROJECT_CREATE_TASK_WITH_SUBTOTAL) && $conf->subtotal->enabled && $line->product_type == 9) null; // Si la conf
			else if ($line->product_type == 9) continue;
				
			if ($line->product_type == 9)
			{
				if ($line->qty >= 1 && $line->qty <= 10) // TITRE
				{
					$index = $line->qty - 1; // -1 pcq je veux savoir si un id task existe sur un niveau parent
					$fk_task_parent = isset($TTask_id_parent[$index]) && !empty($TTask_id_parent[$index]) ? $TTask_id_parent[$index] : 0;
						
					$label = !empty($line->product_label) ? $line->product_label : $line->desc;
					$fk_task_parent = self::createOneTask($db, $user, $project->id, $conf->global->DOC2PROJECT_TASK_REF_PREFIX.$line->rowid, $label, '', '', '', $fk_task_parent);
						
					$TTask_id_parent[$index+1] = $fk_task_parent; //+1 pcq je replace le titre à son niveau (exemple : titre niveau 2 à l'indice 2)
				}
				else // SOUS-TOTAL
				{
					$index = 100 - $line->qty - 1;
					$fk_task_parent = isset($TTask_id_parent[$index]) && !empty($TTask_id_parent[$index]) ? $TTask_id_parent[$index] : 0;
				}
	
			}
			elseif (!empty($conf->global->DOC2PROJECT_USE_NOMENCLATURE_AND_WORKSTATION))
			{
				//self::createOneTask(...); //Avec les postes de travails liés à la nomenclature
			}
				
			// => ligne de type service										=> ligne libre
			elseif( (!empty($line->fk_product) && $line->fk_product_type == 1) || (!empty($conf->global->DOC2PROJECT_ALLOW_FREE_LINE) && $line->fk_product === null) )
			{ // On ne créé que les tâches correspondant à des services
				$product = new Product($db);
				if (!empty($line->fk_product)) $product->fetch($line->fk_product);
	
				$durationInSec = $start = $end = '';
				$duration = trim($product->duration_value);
				if (!empty($duration))
				{
					// On part du principe que les services sont vendus à l'heure ou au jour. Pas au mois ni autre.
					$durationInSec = $line->qty * $product->duration_value * 3600;
					$nbDays = 0;
					if($product->duration_unit == 'd')
					{ // Service vendu au jour, la date de fin dépend du nombre de jours vendus
						$durationInSec *= $conf->global->DOC2PROJECT_NB_HOURS_PER_DAY;
						$nbDays = $line->qty * $product->duration_value;
					} else if($product->duration_unit == 'h')
					{ // Service vendu à l'heure, la date de fin dépend du nombre d'heure vendues
						$nbDays = ceil($line->qty * $product->duration_value / $conf->global->DOC2PROJECT_NB_HOURS_PER_DAY);
					}
						
					$end = strtotime('+'.$nbDays.' weekdays', $start);
				}
	
				$label = !empty($line->product_label) ? $line->product_label : $line->desc;
				self::createOneTask($db, $user, $project->id, $conf->global->DOC2PROJECT_TASK_REF_PREFIX.$line->rowid, $label, $line->desc, $start, $end, $fk_task_parent, $durationInSec, $line->total_ht);
	
			}
				
		}
	}
	
	public static function createOneTask(&$db, &$user, $fk_project, $ref, $label='', $desc='', $start='', $end='', $fk_task_parent=0, $planned_workload='', $total_ht='')
	{
		$task = new Task($db);
	
		$task->fk_project = $fk_project;
		$task->ref = $ref;
		$task->label = $label;
		$task->description = $desc;
	
		$task->date_start = $start;
		$task->date_end = $end;
		$task->fk_task_parent = $fk_task_parent;
		$task->planned_workload = $planned_workload;
	
		$task->array_options['options_soldprice'] = $total_ht;
	
		$r = $task->create($user);
		if ($r > 0) return $r;
		else return 0;
	}
}