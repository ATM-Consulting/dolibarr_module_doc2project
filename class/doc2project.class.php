<?php

class Doc2Project {
	
	public static function isExclude(&$line)
	{
		global $conf;

		if (!empty($conf->global->DOC2PROJECT_DO_NOT_CONVERT_SERVICE_WITH_PRICE_ZERO) && $line->subprice == 0) return true;
		if (!empty($conf->global->DOC2PROJECT_DO_NOT_CONVERT_SERVICE_WITH_QUANTITY_ZERO) && $line->qty == 0) return true;

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
	
	public static function createProject(&$object) {
		
		global $conf,$langs,$db,$user;
		
		if (!class_exists('Project')) dol_include_once('/projet/class/project.class.php');
		if (!class_exists('Task')) dol_include_once('/projet/class/task.class.php');
		
		if(empty($object->thirdparty)) $object->fetch_thirdparty();
		
		$project = new Project($db);
		
		if(!empty($conf->global->DOC2PROJECT_SEARCH_CUSTOMER_PROJECT) && $object->thirdparty->has_projects()) {
			$fk_projet = self::getCustomerProject($object->thirdparty);
			$project->fetch($fk_projet);
			
			$object->setProject($project->id);

			if($project->id>0) return $project;
			else return false;
		}
		elseif (!empty($object->fk_project))
		{
			$r = $project->fetch($object->fk_project);

			if($project->id>0) return $project;
			else return false;
		}

		$langs->load('doc2project@doc2project');
		
		if(!empty($conf->global->DOC2PROJECT_TITLE_PROJECT) ) {
			$Trans=array(
				'{ref_client}'=>	$object->ref_client
				,'{thirdparty_name}'=>$object->thirdparty->name
				,'{ref}'=>$object->ref
			);
			
			if(!empty($object->array_options )) {
				foreach($object->array_options as $k=>$v) {
					$Trans['{'.$k.'}'] = $v;	
				}
			}
			
			$title = strtr($conf->global->DOC2PROJECT_TITLE_PROJECT,$Trans);
						
		}
		else{
			$title = (!empty($object->ref_client)) ? $object->ref_client : $object->thirdparty->name.' - '.$object->ref.' '.$langs->trans('DocConverted');
			$title = $langs->trans('Doc2ProjectTitle', $title);
		}
		
		$project->title			 = $title;
		$project->socid          = $object->socid;
		$project->description    = '';
		$project->public         = 1; // 0 = Contacts du projet  ||  1 = Tout le monde
		$project->datec			 = dol_now();
		$project->date_start	 = $object->date_livraison;
		$project->date_end		 = null;
	
		$project->ref 			 = self::get_project_ref($project);
			
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
		
		
		
		return false;
		
	}

	public static function getCustomerProject(&$soc) {
		
		global $db;
		
		$sql = 'SELECT rowid
				FROM '.MAIN_DB_PREFIX.'projet
				WHERE fk_soc = '.$soc->id;
		$resql = $db->query($sql);
		$res = $db->fetch_object($resql);
		
		return $res->rowid;
		
	}
	
	public static function lineToTask(&$object,&$line, &$project,&$start,&$end,$fk_parent=0,$isParent=false,$fk_workstation=0) {
		
		global $conf,$langs,$db,$user;
//var_dump($line);exit;		
		$product = new Product($db);
		if (!empty($line->fk_product)) $product->fetch($line->fk_product);
		
		if(empty($fk_workstation) && !empty($line->array_options['options_fk_workstation'])) {
			$fk_workstation = $line->array_options['options_fk_workstation'];
		}
		
		if(empty($fk_workstation) && !empty($object->array_options['options_fk_workstation'])) {
			$fk_workstation = $object->array_options['options_fk_workstation'];
		}
		
		
		$durationInSec = $end = '';
		if(!empty($conf->global->DOC2PROJECT_CONVERSION_RULE)) {
		
			$Trans = array(
					'{qty}'=>$line->qty
					,'{totalht}'=>$line->total_ht
					,'{fk_workstation}'=>$fk_workstation
					
			);
			
			if(!empty($conf->workstation->enabled) && $fk_workstation>0) {
				define('INC_FROM_DOLIBARR',true);
				dol_include_once('/workstation/config.php');
				dol_include_once('/workstation/class/workstation.class.php');
				$PDOdb=new TPDOdb;
				$ws = new TWorkstation;
				$ws->load($PDOdb, $fk_workstation);
				$Trans['{workstation_code}']=$ws->code;
			}
			
			$eval = strtr($conf->global->DOC2PROJECT_CONVERSION_RULE,$Trans);
			
			if(strpos($eval,'return ')===false)$eval = 'return ('.$eval.');';
			
			$durationInSec = eval($eval) * 3600;
			$nbDays = ceil(($durationInSec / 3600) / $conf->global->DOC2PROJECT_NB_HOURS_PER_DAY);
		
		}
		else if($line->ref!=null){
			$product->fetch($line->fk_product);
		
			// On part du principe que les services sont vendus à l'heure ou au jour. Pas au mois ni autre.
		
			$durationInSec = $line->qty * $product->duration_value * 3600;
		
			$nbDays = 0;
			if($product->duration_unit == 'd') { // Service vendu au jour, la date de fin dépend du nombre de jours vendus
				$durationInSec *= $conf->global->DOC2PROJECT_NB_HOURS_PER_DAY;
				$nbDays = $line->qty * $product->duration_value;
			} else if($product->duration_unit == 'h') { // Service vendu à l'heure, la date de fin dépend du nombre d'heure vendues
				$nbDays = ceil($line->qty * $product->duration_value / $conf->global->DOC2PROJECT_NB_HOURS_PER_DAY);
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
		
		$label = !empty($line->product_label) ? $line->product_label : $line->desc;
		
//var_dump($defaultref, $label,  $project->id);exit;		
		self::createOneTask( $project->id, $defaultref, $label, $line->desc, $start, $end, $fk_task_parent, $durationInSec, $line->total_ht,$fk_workstation);
		
		
	}
	
	public static function parseLines(&$object,&$project,&$start,&$end)
	{
		global $conf,$langs,$db,$user;
	
		// CREATION D'UNE TACHE GLOBAL POUR LA SAISIE DES TEMPS
		if (!empty($conf->global->DOC2PROJECT_CREATE_GLOBAL_TASK))
		{
			self::createOneTask($project->id, $conf->global->DOC2PROJECT_TASK_REF_PREFIX.'GLOBAL', $langs->trans('Doc2ProjectGlobalTaskLabel'), $langs->trans('Doc2ProjectGlobalTaskDesc'));
		}
	
		// Tableau qui va contenir à chaque indice (niveau du titre) l'id de la dernier tache parent
		// Par contre il faut les titres suivants correctement, T1 => T2 => T3 ... et pas de T1 => T3, dans ce cas T3 sera du même niveau que T1
		$TTask_id_parent = array();
		$index = 1;
//var_dump($object->lines);exit;		
		$fk_task_parent = 0;
		$object->fetch_lines();
		// CREATION DES TACHES PAR RAPPORT AUX LIGNES DE LA COMMANDE
		foreach($object->lines as &$line)
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
					$fk_task_parent = self::createOneTask($project->id, $conf->global->DOC2PROJECT_TASK_REF_PREFIX.$line->rowid, $label, '', '', '', $fk_task_parent);
						
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
				
//var_dump(self::isExclude($line), $line->desc);
				if(self::isExclude($line)) continue;
//var_dump($conf->global->DOC2PROJECT_CREATE_TASK_FOR_VIRTUAL_PRODUCT,$line);exit;				
				if(!empty($conf->global->DOC2PROJECT_CREATE_TASK_FOR_VIRTUAL_PRODUCT) && !empty($conf->global->PRODUIT_SOUSPRODUITS) && !is_null($line->ref))
				{
				
					$s = new Product($db);
					$s->fetch($line->fk_product);
					$s->get_sousproduits_arbo();
					$TProdArbo = $s->get_arbo_each_prod();
				
					if(!empty($TProdArbo)){
				
						if(!empty($conf->global->DOC2PROJECT_CREATE_TASK_FOR_PARENT)){
							$fk_parent = self::lineToTask($object, $line,$project,$start,$end,0,true);
				
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
				
									self::lineToTask($object,$line, $project, $start,$end,$fk_parent,false,$TWorkstation->rowid);
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
				
								$new_fk_parent = $this->create_task($object,$line,$project,$start,$end,$fk_parent);
				
								if(!empty($conf->workstation->enabled) && !empty($conf->global->DOC2PROJECT_WITH_WORKSTATION)){
									dol_include_once('/workstation/class/workstation.class.php');
				
									$Tids = TRequeteCore::get_id_from_what_you_want($PDOdb, MAIN_DB_PREFIX."workstation_product",array('fk_product'=>$ss->id));
									if(!empty($Tids)) {
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
					
											self::lineToTask($object,$line, $project, $start,$end,$new_fk_parent,false,$TWorkstation->rowid);
										}
									}
								}
							}
						}
					}else{
						
						self::lineToTask($object,$line,$project,$start,$end);
					}
				}
				else{
					self::lineToTask($object,$line,$project,$start,$end);
				}
				
				
			}
				
		}
//exit('LA');
	}
	
	
	public static function createOneTask($fk_project, $ref, $label='', $desc='', $start='', $end='', $fk_task_parent=0, $planned_workload='', $total_ht='', $fk_workstation = 0)
	{
		global $conf,$langs,$db,$user,$hookmanager;
		
		$hookmanager->initHooks(array('doc2projecttaskcard','globalcard'));
		
		$task = new Task($db);
		$task->fetch('',$ref);
		if($task->id>0) {
			$task->planned_workload = $planned_workload;
			$task->fk_project = $fk_project;
			
			if($fk_workstation) $task->array_options['options_fk_workstation'] = $fk_workstation;
			$task->array_options['options_soldprice'] = $total_ht;
			$task->progress = (int)$task->progress;
			$task->update($user);
			
			return $task->id;
		}
		else{
			
			$action = 'create_task';
			$reshook = $hookmanager->executeHooks('doActions', array('id_project'=>$fk_project), $task, $action);
			
			$task->fk_project = $fk_project;
			$task->ref = $ref;
			$task->label = $label;
			$task->description = $desc;
			$task->date_c=dol_now();
			$task->date_start = $start;
			$task->date_end = $end;
			$task->fk_task_parent = (int)$fk_task_parent;
			$task->planned_workload = $planned_workload;
			
			if($fk_workstation) $task->array_options['options_fk_workstation'] = $fk_workstation;
			$task->array_options['options_soldprice'] = $total_ht;
			
			$r = $task->create($user);
//var_dump($task);
//exit('create');

			if ($r > 0) {
				return $r;
			} else {
				dol_print_error($db);
			}
				
		}
		return 0;
	}
}
