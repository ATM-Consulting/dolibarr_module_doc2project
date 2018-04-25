<?php

class Doc2Project {
	
	public static function isExclude(&$line)
	{
		global $conf;
		
		$exclude = false;
		
		// FROM SEND FORM
		if(!empty($conf->global->DOC2PROJECT_PREVUE_BEFORE_CONVERT)){
		    // Check if line is selected
		    $linecheckbox = GETPOST('doc2projectline');
		    // var_dump(array( !empty($linecheckbox), !isset($linecheckbox[$line->id]) ));
		    if(!empty($linecheckbox) && !isset($linecheckbox[$line->id])){
		        return true;
		    }
		}
		
		if (!empty($conf->global->DOC2PROJECT_DO_NOT_CONVERT_SERVICE_WITH_PRICE_ZERO) && $line->subprice == 0) return   true;
		if (!empty($conf->global->DOC2PROJECT_DO_NOT_CONVERT_SERVICE_WITH_QUANTITY_ZERO) && $line->qty == 0) return   true;

		// FROM CONFIG : PRODUCT REF
		$TExclude = explode(';', $conf->global->DOC2PROJECT_EXCLUDED_PRODUCTS);
		if (!empty($conf->global->DOC2PROJECT_EXCLUDED_PRODUCTS) && in_array($line->ref, $TExclude)) return  true;
		
		// Subtotal
		if (empty($conf->global->DOC2PROJECT_CREATE_TASK_WITH_SUBTOTAL) && $conf->subtotal->enabled && $line->product_type == 9) return  true;
		
		return $exclude;
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
		
		global $conf,$langs,$db,$user,$hookmanager;

		$hookmanager->initHooks(array('doc2projecttaskcard'));

		if (!class_exists('Project')) dol_include_once('/projet/class/project.class.php');
		if (!class_exists('Task')) dol_include_once('/projet/class/task.class.php');
		
		if(empty($object->thirdparty)) $object->fetch_thirdparty();
		
		$project = new Project($db);
		
		$action = 'createProject';
		$reshook = $hookmanager->executeHooks('createProject', array('project' => &$project), $object, $action);
		
		if (!empty($hookmanager->resArray))
		{
			$project=&$hookmanager->resArray[0];
			return $project; // £project est donnée par référence et il doit avoir été soit create ou fetch
		}
		else
		{
			if (!empty($object->fk_project))
			{
				$r = $project->fetch($object->fk_project);
				
				if (!empty($conf->global->DOC2PROJECT_SET_PROJECT_DRAFT)) { $res = $project->setStatut(0); $project->statut = 0; }
				
				if($project->id>0) return $project;
				else return false;
			}

			$langs->load('doc2project@doc2project');

			$project = new Project($db);
			
			// ref is still PROV if coming from VALIDATE trigger
			if(preg_match('/^[\(]?PROV/i', $object->ref)) {
				$object->ref = $object->newref;
			}

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
			$project->date_start	 = (!empty($object->date_livraison))?$object->date_livraison:dol_now();
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
			    setEventMessage($langs->transnoentitiesnoconv('Doc2ProjectErrorCreateProject', $r.$project->error), 'errors');
			}
		}
		
		
		return false;
	}
	
	public static function lineToTask(&$object,&$line, &$project,&$start,&$end,$fk_parent=0,$isParent=false,$fk_workstation=0,$story='') {
		
		global $conf,$langs,$db,$user;
		
		$product = new Product($db);
		if (!empty($line->fk_product)) $product->fetch($line->fk_product);
		
		// GET fk_workstation from Line
		if(empty($fk_workstation) && !empty($line->array_options['options_fk_workstation'])) {
			$fk_workstation = $line->array_options['options_fk_workstation'];
		}
		
		// GET fk_workstation from Object
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
		
		if (!empty($conf->global->DOC2PROJECT_TASK_NAME)) $label = strtr($conf->global->DOC2PROJECT_TASK_NAME, array('{product_ref}' => $line->ref, '{product_label}' => $line->product_label));
		else $label = !empty($line->product_label) ? $line->product_label : $line->desc;
		
//var_dump($defaultref, $label,  $project->id);exit;		
		self::createOneTask( $project->id, $defaultref, $label, $line->desc, $start, $end, $fk_task_parent, $durationInSec, $line->total_ht,$fk_workstation,$line,$story, $line->rowid, $object->element);
		
		
	}
	
	
	
	
	public static function parseLines(&$object,&$project,&$start,&$end)
	{
		global $conf,$langs,$db,$user,$TStory;
		
		if (empty($TStory)){
		    $TStory = self::getAllStoriesFromProject($project->id);
		}
		dol_include_once('/subtotal/class/subtotal.class.php');
		
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
		
		$linesImported = 0;
		$linesExcluded =0;
		$linesImportError =0;
		
		$story = '';
		// CREATION DES TACHES PAR RAPPORT AUX LIGNES DE LA COMMANDE
		foreach($object->lines as &$line)
		{
		    
		    if(!empty($conf->global->DOC2PROJECT_CREATE_SPRINT_FROM_TITLE) && !empty($conf->subtotal->enabled) && TSubtotal::isTitle($line)){
				$story = TSubtotal::getTitleLabel($line);
				self::add_story($TStory,$story,$project->id);
			}
			
			// EXCLUDED LINES
			if(self::isExclude($line)){
			    $linesExcluded ++;
			    continue;
			}
			
			$linesImported++;

			if ($line->product_type == 9)
			{
				if ($line->qty >= 1 && $line->qty <= 10) // TITRE
				{
					$index = $line->qty - 1; // -1 pcq je veux savoir si un id task existe sur un niveau parent
					$fk_task_parent = isset($TTask_id_parent[$index]) && !empty($TTask_id_parent[$index]) ? $TTask_id_parent[$index] : 0;
					
					$label = !empty($line->product_label) ? $line->product_label : $line->label;
					$desc =  !empty($line->description) ? $line->description : $line->desc;
					
					$fk_task_parent = self::createOneTask($project->id, $conf->global->DOC2PROJECT_TASK_REF_PREFIX.$line->rowid, $label, $desc, '', '', $fk_task_parent, '', '', 0,'',$story,$line->rowid, $object->element);
						
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
				if(!empty($line->fk_product)) {
					define('INC_FROM_DOLIBARR',true);
					dol_include_once('/nomenclature/config.php');
					dol_include_once('/nomenclature/class/nomenclature.class.php');
					$nomenclature = new TNomenclature($db);
					$PDOdb = new TPDOdb($db);
					
					$nomenclature->loadByObjectId($PDOdb,$line->rowid, $object->element, false, $line->fk_product);//get lines of nomenclature
					if(!empty($nomenclature->TNomenclatureDet) || !empty($nomenclature->TNomenclatureWorkstation )){
						
						$lastCreateTask = self::nomenclatureToTask($nomenclature,$line,$object, $project, $start, $end,$story);
					}elseif( (!empty($line->fk_product) && $line->fk_product_type == 1)){
					    $lastCreateTask = self::lineToTask($object,$line,$project,$start,$end,0,false,0,$story);
					}
				}
			}	
			// => ligne de type service	=> ligne libre
			elseif( (!empty($line->fk_product) && $line->fk_product_type == 1) || (!empty($conf->global->DOC2PROJECT_ALLOW_FREE_LINE) && $line->fk_product === null) )
			{ // On ne créé que les tâches correspondant à des services
						
				if(!empty($conf->global->DOC2PROJECT_CREATE_TASK_FOR_VIRTUAL_PRODUCT) && !empty($conf->global->PRODUIT_SOUSPRODUITS) && !is_null($line->ref))
				{
					
					$s = new Product($db);
					$s->fetch($line->fk_product);
					$s->get_sousproduits_arbo();
					$TProdArbo = $s->get_arbo_each_prod();
				
					if(!empty($TProdArbo)){
				
						if(!empty($conf->global->DOC2PROJECT_CREATE_TASK_FOR_PARENT)){
							$fk_parent = self::lineToTask($object, $line,$project,$start,$end,0,true,0,$story);
				
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
				
									self::lineToTask($object,$line, $project, $start,$end,$fk_parent,false,$TWorkstation->rowid,$story);
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
					
											if(!self::lineToTask($object,$line, $project, $start,$end,$new_fk_parent,false,$TWorkstation->rowid,$story))
											{
											    $linesImportError ++;
											}
										}
									}
								}
							}
						}
					}else{
						
					    if(!self::lineToTask($object,$line,$project,$start,$end,0,false,0,$story)){
					        $linesImportError ++;
					    }
					}
				}
				else{
				    if(!self::lineToTask($object,$line,$project,$start,$end,0,false,0,$story)){
				        $linesImportError ++;
				    }
				}
			}
		}
		

		//var_dump(array($linesImported,$linesExcluded,$linesImportError ));
		//exit;

		if($conf->global->DOC2PROJECT_CREATE_SPRINT_FROM_TITLE && $conf->subtotal->enabled)
		{
			$project->statut=0;
			$project->array_options['options_stories'] = implode(',', $TStory);
			$project->update($user);
		}
	}
	

	public static function searchTask($fk_project,$label='', $story='')
	{
	    global $conf,$db;
	    
	    if( empty($label) && empty($story) ) return false;
	    
	    $sql = "SELECT";
	    $sql.= " t.rowid, t.ref";
	    $sql.= " FROM ".MAIN_DB_PREFIX."projet_task as t";
	    $sql.= " WHERE ";
	    
	    $filters=array();
	    
	    $filters[] = "t.fk_projet = '".intval($fk_project)."'";
	    
	    if (!empty($label)) {
	        $filters[] = "t.label = '".$db->escape($label)."'";
	    }
	    
	    
	    if (!empty($conf->global->DOC2PROJECT_GROUP_TASKS_BY_SPRINT) && !empty($story) && !empty(self::getStoryK($story))) {
	        $filters[] = "t.story_k = '".intval(self::getStoryK($story))."'";
	    }
	    $sql.= implode(' AND ', $filters);
	    
	    $sql.= ' LIMIT 1';
	    
	    $resql=$db->query($sql);
	    
	    if ($resql)
	    {
	        $num_rows = $db->num_rows($resql);
	        
	        if ($num_rows)
	        {
	            $obj = $db->fetch_object($resql);
	            return $obj;
	        }
	    }
	    
	    return 0;
	    
	}
	
	
	/*
	 * return 0 on error and task rowid on success
	 */
	public static function createOneTask($fk_project, $ref, $label='', $desc='', $start='', $end='', $fk_task_parent=0, $planned_workload='', $total_ht='', $fk_workstation = 0,$line='',$story='', $fk_origin='', $origin_type='')
	{
		global $conf,$langs,$db,$user,$hookmanager;

		$hookmanager->initHooks(array('doc2projecttaskcard'));
		
		$task = new Task($db);
		
		
		
		$action = 'createOneTask';
		$parameters = array('db' => &$db, 'fk_project' => $fk_project, 'ref' => $ref, 'label' => $label, 'desc' => $desc, 'start' => $start, 'end' => $end, 'fk_task_parent' => $fk_task_parent, 'planned_workload' => $planned_workload, 'total_ht' => $total_ht, 'fk_workstation' => $fk_workstation, 'line' => $line);
		$reshook = $hookmanager->executeHooks('createTask', $parameters, $task, $action);	
		
		if (!empty($hookmanager->resArray))
		{
			return $hookmanager->resArray[0]->id;
		}
		else
		{
		    
		    $story_k = self::getStoryK($story);
		    
		    $groupTask = (!empty($conf->global->DOC2PROJECT_GROUP_TASKS) || !empty($conf->global->DOC2PROJECT_GROUP_TASKS_BY_SPRINT))?true:false;
		    if($groupTask){
		        // search previous created task
		        $previousTask = self::searchTask($fk_project,$label, $story);
		    }
		    
		    if($groupTask && !empty($previousTask)){
		        $ref = $previousTask->ref;
		        $task->fetch($previousTask->rowid);
		    }else{
		        $groupTask = false;
		        $task->fetch('',$ref);
		    }
			
			
			
			if (!empty($line))
			{
				if (empty($line->array_options) && method_exists($line, 'fetch_optionals')) $line->fetch_optionals($line->id?$line->id:$line->rowid);
				if (!class_exists('ExtraFields')) require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';

				$extrafields = new ExtraFields($db);
				$extralabels=$extrafields->fetch_name_optionals_label($task->table_element);

				foreach ($extralabels as $key => $dummy)
				{
					if (!empty($line->array_options['options_'.$key])) $task->array_options['options_'.$key] = $line->array_options['options_'.$key];
				}
			}
			
			if($task->id>0) {
			    
			    $task->fk_project = $fk_project;
			    
			    if($groupTask)
			    {
			        
			        $story_k = self::getTaskStoryK($task);
			        $story = self::getStoryL($story);
			        
			        //var_dump(array($story_k, self::getStoryL($story_k)));
			        
			        
			        //var_dump(array($task->planned_workload / 3600 , $planned_workload / 3600, ($task->planned_workload + $planned_workload) / 3600));
			        $task->planned_workload = $task->planned_workload + $planned_workload;
			        $task->array_options['options_soldprice'] = $task->array_options['options_soldprice'] + $total_ht;
			        
			        // new planification calculation
			        self::includeNewStartEndDateToTask($task,$start, $end);
			        
			        
			    }
			    else 
			    {
			        // DEFAULT BEHAVIOR
			        $task->planned_workload = $planned_workload;
			        $task->array_options['options_soldprice'] = $total_ht;
			    }
			    
			    
				

				if($fk_workstation) $task->array_options['options_fk_workstation'] = $fk_workstation;
				
				$task->progress = (int)$task->progress;
				
				$action = 'updateOneTask';
				$parameters = array('db' => &$db, 'fk_project' => $fk_project, 'ref' => $ref, 'label' => $label, 'desc' => $desc, 'start' => $start, 'end' => $end, 'fk_task_parent' => $fk_task_parent, 'planned_workload' => $planned_workload, 'total_ht' => $total_ht, 'fk_workstation' => $fk_workstation, 'line' => $line);
				$reshook = $hookmanager->executeHooks('addMoreParams', $parameters, $task, $action);
				$task->update($user);
				if($conf->global->DOC2PROJECT_CREATE_SPRINT_FROM_TITLE && !is_null($story_k)){
					Doc2Project::setStoryK($db, $task->id, $story_k);
				}
				
				if(! empty($fk_origin)) {
					if($origin_type == 'propal') $task->add_object_linked('propaldet', $fk_origin);
					elseif($origin_type == 'commande') $task->add_object_linked('orderline', $fk_origin);
				}

				return $task->id;
			}
			else{

				$task->fk_project = $fk_project;
				$task->ref = $ref;
				$task->label = $label;
				$task->description = $desc;
				$task->date_c=dol_now();
				$task->date_start = $start;
				$task->date_end = $end;
				$task->fk_task_parent = (int)$fk_task_parent;
				$task->planned_workload = $planned_workload;
				$task->progress='0'; // Depuis doli 6.0, nécessité de renseigner 0 en chaîne car sinon le create task met null et la tâche n'apparaît pas dans l'ordo

				if($fk_workstation) $task->array_options['options_fk_workstation'] = $fk_workstation;
				$task->array_options['options_soldprice'] = $total_ht;
				
				$action = 'createOneTask';
				$parameters = array('db' => &$db, 'fk_project' => $fk_project, 'ref' => $ref, 'label' => $label, 'desc' => $desc, 'start' => $start, 'end' => $end, 'fk_task_parent' => $fk_task_parent, 'planned_workload' => $planned_workload, 'total_ht' => $total_ht, 'fk_workstation' => $fk_workstation, 'line' => $line);
				$reshook = $hookmanager->executeHooks('addMoreParams', $parameters, $task, $action);

				$r = $task->create($user);
				
				if ($r > 0) {
					if($conf->global->DOC2PROJECT_CREATE_SPRINT_FROM_TITLE && !is_null($story_k)){
						Doc2Project::setStoryK($db, $r, $story_k);
					}
					if(! empty($fk_origin)) {
						if($origin_type == 'propal') $task->add_object_linked('propaldet', $fk_origin);
						elseif($origin_type == 'commande') $task->add_object_linked('orderline', $fk_origin);
					}
					return $r;
				} else {
					dol_print_error($db);
				}

			}	
		}
		
		return 0;
	}
	
	public static function getAllStoriesFromProject($fk_projet)
	{
    	dol_include_once('/scrumboard/class/scrumboard.class.php');
    	$TStoryObj = new TStory();
    	$allTStory = $TStoryObj->getAllStoriesFromProject($fk_projet);
    	$TStory = array();
    	if(!empty($allTStory))
    	{
    	    foreach ($allTStory as $story)
    	    {
    	        $TStory[$story->storie_order] = $story->label;
    	    }
    	}
    	return $TStory;
	}
	
	public static function add_story(&$TStory,$story,$fk_projet){
	    if (!in_array($story, $TStory)){
	        
	        if(empty($TStory)) $TStory = self::getAllStoriesFromProject($fk_projet);
	        
	        dol_include_once('/scrumboard/class/scrumboard.class.php');
	        $PDOdb = new TPDOdb();
	        $object = new TStory();
	        $object->fk_projet = $fk_projet;
	        $object->label = $story;
	        $object->storie_order = count($TStory) + 1;
	        $id = $object->save($PDOdb);
	        if($id>0)
	        {
	            $TStory[$object->storie_order] = $story;
	        }
	    }
	}
	
	//Sprint scrumboard
	public static function setStoryK($db,$id, $nbstory)
	{
	    $sql="UPDATE ".MAIN_DB_PREFIX."projet_task SET story_k=".$nbstory." WHERE rowid=".$id;
	    $resql = $db->query($sql);
	    if($resql) return 1;
	    return 0;
	}
	
	public static function getTaskStoryK($task)
	{
	    global $db;
	    if(!empty($task->id))
	    {
	        $sql="SELECT story_k FROM ".MAIN_DB_PREFIX."projet_task  WHERE rowid=".$task->id;
	        $resql = $db->query($sql);
	        
	        if($resql){
	            $obj = $db->fetch_object($resql);
	            return $obj->story_k ;
	        }
	    }
	    
	    return 0;
	}
	
	public static function getStoryK($story) {
		global $conf, $TStory;
		
		if($conf->global->DOC2PROJECT_CREATE_SPRINT_FROM_TITLE && !empty($story)) {
			$key = array_search($story, $TStory);
			
			if ($key !== false) return $key; // décalage suite 
		}
		
		return null;
	}
	
	public static function getStoryL($story_k) {
	    global $conf, $TStory;
	    
	    if($conf->global->DOC2PROJECT_CREATE_SPRINT_FROM_TITLE && !empty($story_k)) {
	        if(isset($TStory[$story_k-1])) return $TStory[$story_k-1];
	    }
	    
	    return null;
	}
	
	/* Converti une ligne de nomenclature en tache.
	 * $detailsNomenclature => resultat de getDetails() de la classe nomenclature 
	 * $line => ligne courante (propaldet/orderdet)
	 * $object => objet courant (propal/order)
	 * 
	 */
	public static function nomenclatureToTask($curentNomenclature,$line,$object, $project, $start, $end,$stories='')
	{
	    global $db,$conf;
		
	    if(is_object($curentNomenclature) && get_class($curentNomenclature) == 'TNomenclature')
	    {
	        $detailsNomenclature=$curentNomenclature->getDetails($line->qty);
	    }
	    else
	    {
	        $detailsNomenclature=$curentNomenclature;
	        $curentNomenclature=false;
	    }
	    
	    
		foreach ($detailsNomenclature as &$lineNomen)
		{
			//Conversion du tableau en objet
			$lineNomenclature = new stdClass();
			foreach ($lineNomen as $key => $value)
			{
				$lineNomenclature->$key = $value;
			}
			
			$product = new Product($db);
			$product->fetch($lineNomenclature->fk_product);
			//On prend les services les plus bas pour créer les taches
			
			if (( $product->type == 1) && (TNomenclature::noProductOfThisType($lineNomen['childs'],1) || empty($lineNomenclature->childs)))
			{
				//Le calcul des quantités est déjà fait grâce à getDetails
				$lineNomenclature->product_label = $line->product_label.' - '.$product->label; //To difference tasks label
				
				$lineNomenclature->desc = $product->description;
				$nomenclature = new TNomenclature($db);
				$PDOdb = new TPDOdb($db);
				
				$nomenclature->loadByObjectId($PDOdb, $lineNomenclature->rowid,$object->element, false, $lineNomenclature->fk_product);
				if (!empty($nomenclature->TNomenclatureWorkstation[0]->rowid))
				{
					$idWorkstation = $nomenclature->TNomenclatureWorkstation[0]->rowid;
				}
				else
				{
					$idWorkstation = 0;
				}
				
				$lineNomenclature->rowid = $lineNomenclature->rowid.'-'.$lineNomenclature->fk_product.'-'.$line->rowid; //To difference tasks ref
				$fk_ParentTask = self::lineToTask($object, $lineNomenclature, $project, $start, $end, 0, false, $idWorkstation, $stories);
				
			}elseif(!empty($lineNomenclature->childs)){
			    $fk_ParentTask = self::nomenclatureToTask($lineNomenclature->childs, $line,$object, $project, $start, $end,$stories);
			}
		}
		
		// RECUPERATION DES WORKSTATIONS
		if(!empty($conf->workstation->enabled) && !empty($conf->global->DOC2PROJECT_WITH_WORKSTATION) && !empty($curentNomenclature) )
		{

		    dol_include_once('/workstation/class/workstation.class.php');
		    if(!empty($curentNomenclature->TNomenclatureWorkstation))
		    {
		        foreach ($curentNomenclature->TNomenclatureWorkstation as &$wsn)
		        {
		            $defaultref='';
		            if(!empty($conf->global->DOC2PROJECT_TASK_REF_PREFIX)) {
		                $defaultref = $conf->global->DOC2PROJECT_TASK_REF_PREFIX.$line->rowid.$wsn->workstation->rowid;
		            }
		            
		            $durationInSec = $line->qty * $wsn->nb_hour * 3600;
		            $label = $wsn->workstation->name;
		            self::createOneTask( $project->id, $defaultref, $label, $line->desc, $start, $end, $fk_task_parent, $durationInSec, $line->total_ht,$fk_workstation,$line,$stories);
		            
		        }
		    }
		}
	}

	
	public static function showLinesToParse(&$object)
	{
	    global $conf,$langs,$db,$user;
	    
	    $Tlines = array();
	    
	    // LOAD subtotal class if needed
	    if(!empty($conf->global->DOC2PROJECT_CREATE_SPRINT_FROM_TITLE)){
	        dol_include_once('/subtotal/class/subtotal.class.php');
	    }
	    
	    
	    print '<table id="tablelines" class="noborder" width="100%"><thead><tr class="liste_titre">
                <td class="linecoldescription">Description</td>
                <td class="linecolvat" align="right" width="80">TVA</td>
                <td class="linecoluht" align="right" width="80">P.U. HT</td>
                <td class="linecolqty" align="right">Qté</td>
                <td class="linecoluseunit" align="left">Unité</td>
                <td class="linecolcheckall" align="left"><input type="checkbox" class="linecheckboxtoggle"></td>
                </tr></thead><tbody>';
	   
	    // CREATION D'UNE TACHE GLOBAL POUR LA SAISIE DES TEMPS
	    if (!empty($conf->global->DOC2PROJECT_CREATE_GLOBAL_TASK))
	    {
	        print '<tr>';
	        print '<td colspan="6" ><strong>'.$langs->trans('Doc2ProjectGlobalTaskLabel').'</strong> - '.$langs->trans('Doc2ProjectGlobalTaskDesc').'</td>';
	        print '</tr>';
	    }

	    
	    $i = 0;
	    // CREATION DES TACHES PAR RAPPORT AUX LIGNES DE LA COMMANDE
	    foreach($object->lines as $iLine => &$line)
	    {
	        $i++;
	        $Tlines = array();
	        $backgroundColor = '';
	        $lineType = 'std'; // 'title', 'subtotal'
	        
	        // Excluded product
	        if(self::isExclude($line)) continue;
	        
	        // Dans le cas de sous total
	        if ($line->product_type == 9)
	        {
	            if (method_exists('TSubtotal', 'getTitleLabel')) $title = TSubtotal::getTitleLabel($line);
	            else {
	                $title = $line->label;
	                if (empty($title)) $title = !empty($line->description) ? $line->description : $line->desc;
	            }
	            
	            if ($line->qty >= 1 && $line->qty <= 10) // TITRE
	            {
	                $backgroundColor = '#eeffee';
	                $lineType = 'title';
	            }
	            else // SOUS-TOTAL
	            {
	                $backgroundColor = '#ddffdd';
	                $lineType = 'subtotal';
	            }
	        }
	        elseif (!empty($conf->global->DOC2PROJECT_USE_NOMENCLATURE_AND_WORKSTATION))
	        {
	            //Avec les postes de travails liés à la nomenclature
	            if(!empty($line->fk_product) || (!empty($conf->global->DOC2PROJECT_ALLOW_FREE_LINE) && $line->fk_product === null) ) {
	                define('INC_FROM_DOLIBARR',true);
	                $Tcrawl = self::nomenclatureProductDeepCrawl($line->rowid, $object->element,$line->fk_product,$line->qty);
	                if(!empty($Tcrawl))
	                { 
	                    $Tlines = array_merge($Tlines,$Tcrawl);
	                }
	            }
	            
	        }
	        else if( 
	                   (!empty($line->fk_product) && $line->fk_product_type == 1) // Line type service
	                || (!empty($conf->global->DOC2PROJECT_ALLOW_FREE_LINE) && $line->fk_product === null)  // Free line
	            )
	        { 
	            
	            // => ligne de type service	=> ligne libre
	            // On ne créé que les tâches correspondant à des services
	            
	            
	            if(!empty($conf->global->DOC2PROJECT_CREATE_TASK_FOR_VIRTUAL_PRODUCT) && !empty($conf->global->PRODUIT_SOUSPRODUITS) && !is_null($line->ref))
	            {
	                $s = new Product($db);
	                $s->fetch($line->fk_product);
	                $s->get_sousproduits_arbo();
	                $TProdArbo = $s->get_arbo_each_prod();
	                
	                if(!empty($TProdArbo)){
	                    
	                    if(!empty($conf->global->DOC2PROJECT_CREATE_TASK_FOR_PARENT)){
	                        if($conf->workstation->enabled && $conf->global->DOC2PROJECT_WITH_WORKSTATION){
	                            dol_include_once('/workstation/class/workstation.class.php');
	                            
	                            $Tids = TRequeteCore::get_id_from_what_you_want($PDOdb, MAIN_DB_PREFIX."workstation_product",array('fk_product'=>$line->fk_product));
	                            
	                            foreach ($Tids as $workstationProductid) {
	                                $Tcrawl = self::nomenclatureProductDeepCrawl($workstationProductid,'product',$workstationProductid,1);
	                                if(!empty($Tcrawl))
	                                {
	                                    $Tlines = array_merge($Tlines,$Tcrawl);
	                                }
	                            }
	                        }
	                    }
	                    
	                    foreach($TProdArbo as $prod){
	                        if($prod['type'] == 1){ //Uniquement les services
	                            $Tcrawl = self::nomenclatureProductDeepCrawl($prod['id'],'product',$prod['id'],$line->qty * $prod['nb']);
	                            if(!empty($Tcrawl))
	                            {
	                                $Tlines = array_merge($Tlines,$Tcrawl);
	                            }
	                        }
	                    }
	                }
	            }
	        }
	        
	        $backgroundColor = empty($backgroundColor)?'#f8f8f8':$backgroundColor;
	        print '<tr style="background: '.$backgroundColor.' !important;" >';
	        print '<td class="linecoldescription">';

            if(!empty($line->fk_product)){
                $product = new Product($db);
                if($product->fetch($line->fk_product) > 0){
                    print $product->getNomUrl(1).' - '.$product->label. ' ';
                }
            }
            print '<strong>'.$line->label.'</strong> ';
	        if(!empty($line->desc)){ print $line->desc; }
	        print '</td>';
	        print '<td class="linecolvat" align="right" width="80">';
	        if( $lineType != 'title' && $lineType != 'subtotal'){
	            print price($line->tva_tx);
	        }
	        print '</td>';
	        print '<td class="linecoluht" align="right" width="80">';
	        if( $lineType != 'title' && $lineType != 'subtotal'){
	            print price($line->subprice);
	        }
	        print '</td>';
	        print '<td class="linecolqty" align="right">';
	        if( $lineType != 'title' && $lineType != 'subtotal'){
	            print $line->qty;
	        }
	        print '</td>';
	        print '<td class="linecoluseunit" align="left"></td>';
	        print '<td class="linecolcheckall" align="left">';
	        
	        if(in_array($lineType, array('std', 'title')) )
	        {
	            print '<input type="checkbox" class="linecheckbox" name="doc2projectline['.$line->id.']" value="'.$line->id.'" ></td>';
	        }
	        
	        print '</tr>';
	        if(!empty($Tlines))
	        {
	            print '<tr  style="background:#fff  !important;"  ><td colspan="6" >';
	            //var_dump($Tlines);
	            self::taskViewToHtml($Tlines);
	            print '</td></tr>';
	        }
	    }
	    print '</tbody></table>';
	    
	    print '<input type="hidden"  />';
	    
	    if (ini_get('max_input_vars') < ($i*4))
	    {
	        print 'NEED CHANGE max_input_vars to biggeur value than '.($i*4);
	    }
	
	    
	}
	
	
	
	public static function taskViewToHtml($Tlines)
	{
	    global $db;
	    print '<ul>';
	    foreach ($Tlines as $i => $task)
	    {
	        $style = '';
	        if($task['element'] == 'workstation' && empty($task['infos']['object']->nb_hour)){
	            $style = 'text-decoration: line-through;';
	        }
	        
	        print '<li style="'.$style.'">';
	        
	        
	        if(!empty($task['fk_product']))
	        {
	            $product = new Product($db);
	            if($product->fetch($task['fk_product']) > 0)
	            {
	                $task['infos']['label'] = $product->getNomUrl(1) .' '.$product->label.' '.$task['infos']['label'];
	            }
	        }
	        elseif($task['element'] == 'workstation'){
	           print '<i class="fa fa-wrench"></i> ';
	        }
	       
	        $devNotes =  '';//$i.' :: '.$task['element'] .' ';
	        print '<strong>'.$devNotes. $task['infos']['label'].'</strong>';
	        if(!empty($task['infos']['desc'])){ print ' '.$task['infos']['desc']; }
	        
	        if($task['element'] == 'workstation')
	        {
	            print ' '.$task['infos']['object']->nb_hour.'H'; 
	        }
	        
	       
	        if(!empty($task['infos']['qty'])){
	            print ' x '.($task['infos']['qty']);
	        }
	        
	        if(!empty($task['children'])){ 
	            self::taskViewToHtml($task['children']);
	        }
	        print '</li>';
	    }
	    print '</ul>';
	}

	
	
	public static function  nomenclatureProductDeepCrawl($fk_element, $element, $fk_product,$qty = 1, $deep = 0, $maxDeep = 0){
	    global $db,$conf;
	    
	    $maxDeepConf = empty($conf->global->NOMENCLATURE_MAX_NESTED_LEVEL) ? 50 : $conf->global->NOMENCLATURE_MAX_NESTED_LEVEL;
	    $maxDeep = !empty($maxDeep)?$maxDeep:$maxDeepConf ;
	    
	    if($deep>$maxDeep){ return array(); }
	    
	    dol_include_once('/nomenclature/config.php');
	    dol_include_once('/nomenclature/class/nomenclature.class.php');
	    $nomenclature = new TNomenclature($db);
	    $PDOdb = new TPDOdb($db);
	    
	   
	    $nomenclature->loadByObjectId($PDOdb,$fk_element, $element, false, $fk_product, $qty); //get lines of nomenclature
	    
	    $Tlines= array();
	    
	    $i=0;
	    if(!empty($nomenclature->TNomenclatureDet)){
	        $detailsNomenclature=$nomenclature->getDetails($line->qty);
	        // PARCOURS DE LA NOMENCLATURE
	        foreach ($nomenclature->TNomenclatureDet as &$det)
	        {
	            $i++;
	            
	            $Tlines[$i] = array(
	                'element' => 'nomenclaturedet',
	                'id'      =>  $det->id,
	                'fk_product'=>$det->fk_product,
	                'infos'   => array(
	                    'label' => '',
	                    'desc' => '',
	                    'qty' => $qty * $det->qty,
	                    'object' => $det,
	                ),
	            );
	            
	            $childs = self::nomenclatureProductDeepCrawl($det->fk_product, 'product', $det->fk_product,$qty * $det->qty, $deep+1, $maxDeep);
	            
	            if(!empty($childs))
	            {
	                $Tlines[$i]['children'] = $childs;
	            }
	            
	        }
	        
	        // RECUPERATION DES WORKSTATIONS
	        if(!empty($conf->workstation->enabled) && !empty($conf->global->DOC2PROJECT_WITH_WORKSTATION) )
	        {
	            dol_include_once('/workstation/class/workstation.class.php');
    	        if(!empty($nomenclature->TNomenclatureWorkstation))
    	        {
    	            foreach ($nomenclature->TNomenclatureWorkstation as &$wsn)
    	            {
    	                
    	                $i++;
    	                $Tlines[$i]= array(
    	                    'element' => 'workstation',
    	                    'id'      => $wsn->workstation->rowid,
    	                    'infos'   => array(
    	                        'label' => $wsn->workstation->name,
    	                        'qty' => $qty * $det->qty,
    	                        'desc' => '',
    	                        'object' => $wsn,
    	                    ),
    	                );
    	                
    	            }
    	        }
	        }
	        
	    }
	    
	    return $Tlines;
	}
	
	/**
	* Count the number of working days between two dates.
	*
	* This function calculate the number of working days between two given dates
	*
	* @param   int  $start    Start date timestamp
	* @param   int  $end    Ending date timestamp
	* @return  integer           Number of working days ('zero' on error)
	*
	*/
	public static function getWorkdays($start, $end) {
	    global $conf;
	    
	    $defaultWorkingDays = explode('-',(isset($conf->global->MAIN_DEFAULT_WORKING_DAYS)?$conf->global->MAIN_DEFAULT_WORKING_DAYS:'1-5')); // yes, it's true dolibarr don't create a default '1-5' value so on fresh install of dolibarr this conf is empty. ENJOY!

	    $start = strtotime($date1);
	    $end   = strtotime($date2);
	    $workdays = 0;
	    for ($i = $start; $i <= $end; $i = strtotime("+1 day", $i)) {
	        $day = date("w", $i);  // 0=sun, 1=mon, ..., 6=sat
	        
	        if ($day >= defaultWorkingDays[0]  && $day <= defaultWorkingDays[1]) {
	                $workdays++;
	        }
	    }
	    return intval($workdays);
	}
	
	
	/*
	 * Include new start and end date to an existing task
	 * @param   object  $task    task
	 * @param   int  $start    Start date timestamp
	 * @param   int  $end    Ending date timestamp
	 * @return  null
	 */
	public static function includeNewStartEndDateToTask(&$task,$start, $end) {
	
    	// retrieve working days
    	$supTaskWorkdays =  self::getWorkdays($start, $end);
    	$currentTaskWorkdays = self::getWorkdays($task->date_start, $task->date_end);
    	$totalTaskWorkdays = $currentTaskWorkdays + $supTaskWorkdays;
    	
    	// Apply new start date
    	$newStart= $task->date_start;
    	if(!empty($task->date_start) && !empty($start)){
    	    $newStart = min($start, $task->date_start);
    	}
    	elseif(!empty($start)){
    	    $newStart = $start;
    	}
    	
    	// apply new end date
    	$newEnd = $task->date_end;
    	if(!empty($task->date_end) && !empty($end)){
    	    $newEnd = max($end, $task->date_end);
    	}
    	elseif(!empty($end)){
    	    $newEnd = $end;
    	}
    	
    	
    	$newTaskWorkdays =  self::getWorkdays($newStart, $newEnd);
    	if($newTaskWorkdays<$totalTaskWorkdays){
    	    // adapte end date to include supplemental days
    	    $missingWorkdays = $totalTaskWorkdays-$newTaskWorkdays;
    	    $newEnd = strtotime('+'.$missingWorkdays.' day', $newEnd);
    	}
    	
    	$task->date_start = $newStart;
    	$task->date_end = $newEnd;
	
	}
	
	
}
