<?php
/* <one line to give the program's name and a brief idea of what it does.>
 * Copyright (C) 2013 ATM Consulting <support@atm-consulting.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * 	\file		core/triggers/interface_99_modMyodule_Mytrigger.class.php
 * 	\ingroup	doctag
 * 	\brief		Sample trigger
 * 	\remarks	You can create other triggers by copying this one
 * 				- File name should be either:
 * 					interface_99_modMymodule_Mytrigger.class.php
 * 					interface_99_all_Mytrigger.class.php
 * 				- The file must stay in core/triggers
 * 				- The class name must be InterfaceMytrigger
 * 				- The constructor method must be named InterfaceMytrigger
 * 				- The name property name must be Mytrigger
 */

/**
 * Trigger class
 */
class InterfaceDoc2Projecttrigger
{

    private $db;

    /**
     * Constructor
     *
     * 	@param		DoliDB		$db		Database handler
     */
    public function __construct(&$db)
    {
        $this->db = &$db;

        $this->name = preg_replace('/^Interface/i', '', get_class($this));
        $this->family = "demo";
        $this->description = "Triggers of this module are empty functions."
            . "They have no effect."
            . "They are provided for tutorial purpose only.";
        // 'development', 'experimental', 'dolibarr' or version
        $this->version = 'development';
        $this->picto = 'doctag@doctag';
    }

    /**
     * Trigger name
     *
     * 	@return		string	Name of trigger file
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Trigger description
     *
     * 	@return		string	Description of trigger file
     */
    public function getDesc()
    {
        return $this->description;
    }

    /**
     * Trigger version
     *
     * 	@return		string	Version of trigger file
     */
    public function getVersion()
    {
        global $langs;
        $langs->load("admin");

        if ($this->version == 'development') {
            return $langs->trans("Development");
        } elseif ($this->version == 'experimental')

                return $langs->trans("Experimental");
        elseif ($this->version == 'dolibarr') return DOL_VERSION;
        elseif ($this->version) return $this->version;
        else {
            return $langs->trans("Unknown");
        }
    }

    /**
     * Function called when a Dolibarrr business event is done.
     * All functions "run_trigger" are triggered if file
     * is inside directory core/triggers
     *
     * 	@param		string		$action		Event action code
     * 	@param		Object		$object		Object
     * 	@param		User		$user		Object user
     * 	@param		Translate	$langs		Object langs
     * 	@param		conf		$conf		Object conf
     * 	@return		int						<0 if KO, 0 if no triggered ran, >0 if OK
     */
    public function run_trigger($action, $object, $user, $langs, $conf)
    {
    	global $db;
        // Put here code you want to execute when a Dolibarr business events occurs.
        // Data and type of action are stored into $object and $action
        // Users
        
    //    exit($action);
        
        if ($action === 'TASK_TIMESPENT_CREATE') {
        	
			if((float)DOL_VERSION<=3.5) {
				$ttId = (int)$this->db->last_insert_id(MAIN_DB_PREFIX."projet_task_time");
				
				$resql = $this->db->query('SELECT thm FROM '.MAIN_DB_PREFIX.'user WHERE rowid = '.$object->timespent_fk_user);
				$res =  $this->db->fetch_object($resql);
				$thm = $res->thm;
			}
			else{
				$u=new User($this->db);
                $u->fetch($object->timespent_fk_user);
                $thm = $u->thm;
			}
			
			$this->db->commit();

			$sql = "UPDATE ".MAIN_DB_PREFIX."projet_task_time SET thm=".(double)$thm."  WHERE rowid=".$ttId;
			$this->db->query($sql);
			
			dol_syslog(
                "Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
            );
			
        } 
		else if($action==='USER_MODIFY') {
			
			if((float)DOL_VERSION>=3.6) {
				$object->thm = price2num( GETPOST('thm') );
	           	$object->update($user,1);
			}
			else{
				$thm = price2num( GETPOST('thm') );
				$this->db->query('UPDATE '.MAIN_DB_PREFIX.'user SET thm = '.$thm.' WHERE rowid = '.$object->id);
			}
		}
		else if ($action == 'ORDER_VALIDATE' && !empty($conf->global->DOC2PROJECT_VALID_PROJECT_ON_VALID_ORDER))
		{
			if (empty($conf->projet->enabled)) return 0;

			if (!class_exists('Project')) dol_include_once('/projet/class/project.class.php');
			if (!class_exists('Task')) dol_include_once('/projet/class/task.class.php');
		
			if (!empty($object->fk_project))
			{
				$project = new Project($db);
				$r = $project->fetch($object->fk_project);
				if ($r > 0) return 0;
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
					$this->_createTask($db, $object, $project, $user, $conf);
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
	
		}
		else if ($action == 'SHIPPING_VALIDATE' && !empty($conf->global->DOC2PROJECT_CLOTURE_PROJECT_ON_VALID_EXPEDITION))
		{
			if ($object->origin == 'commande' && !empty($object->origin_id))
			{
				$langs->load('doc2project@doc2project');
				
				$commande = new Commande($db);
				$r = $commande->fetch($object->origin_id);
				
				if ($r > 0)
				{
					dol_include_once('/projet/class/project.class.php');
					$project = new Project($db);
					$r = $project->fetch($commande->fk_project);
					
					if ($r > 0)
					{
						if ($project->statut == 0) setEventMessage($langs->transnoentitiesnoconv('Doc2ProjectErrorProjectCantBeClose', $project->ref), 'errors');
						elseif ($project->statut == 2) setEventMessage($langs->transnoentitiesnoconv('Doc2ProjectProjectAlreadyClose', $project->ref));
						else
						{
							$r = $project->setClose($user);
							if ($r <= 0 || empty($r)) setEventMessage($langs->transnoentitiesnoconv('Doc2ProjectErrorProjectCantBeClose', $project->ref), 'errors');
							else setEventMessage($langs->transnoentitiesnoconv('Doc2ProjectProjectAsBeenClose', $project->ref));
						}
					}
					else setEventMessage($langs->transnoentitiesnoconv('Doc2ProjectErrorProjectNotFound'), 'errors');
			
				}
				else setEventMessage($langs->transnoentitiesnoconv('Doc2ProjectErrorCommandeNotFound'), 'errors');
				
			}
			
		}

        return 0;
    }

	private function _createTask(&$db, &$object, &$project, &$user, &$conf)
	{
		$TServiceToTask = array(
			1 => '1_SABLAGE'
			,2 => '2_ANTICORROSION'
			,3 => '3_PEINTURE'
			,4 => '4_VERNIS'
		);
		
		$TServiceLoaded = array();
		$TTaskCreated = array();
		$durationInSec = $start = $end = '';
		
		// CREATION DES TACHES
		foreach($object->lines as $line) 
		{
			if(!empty($line->fk_product) && $line->fk_product_type == 1) 
			{ // On ne créé que les tâches correspondant à des services
			
				$ref_service = $line->product_ref;
				$label = $line->product_label;
				
				$explodeRef = explode('_', $ref_service); //On doit avoir que des chiffres (exemple : 123 ou 12 ou 1234)
				$explodeRef = $explodeRef[0];
				
				for ($i = 0; $i < strlen($explodeRef); $i++)
				{
				 	$num = $explodeRef[$i];
					
					if (!isset($TServiceToTask[$num])) continue;
					
					$ref = $TServiceToTask[$num];
					
					if (!isset($TServiceLoaded[$ref]))
					{
						$service = new Product($db); //Un service est un objet Product
						$s = $service->fetch(null, $ref);
						if ($s > 0) $TServiceLoaded[$ref] = $service;
					}
					else {
						$service = &$TServiceLoaded[$ref];
						$s = 1;
					}
					
					if ($s > 0)
					{
						if (!isset($TTaskCreated[$ref]))
						{ 	//Nouvelle tâche
							$task = new Task($db);
							
							$task->fk_project = $project->id;
							$task->ref = $conf->global->DOC2PROJECT_TASK_REF_PREFIX.$line->rowid;
							$task->label = $ref;
							$task->description = $label;
							
							$task->date_start = $start;
							$task->date_end = $end;
							$task->fk_task_parent = 0;
							$task->planned_workload = $durationInSec;
							
							$task->create($user);
						
							$TTaskCreated[$ref] = $task;	
						}
						else 
						{	//Update tâche
							$task = &$TTaskCreated[$ref];
							$task->description .= "\n".$label;
							$task->planned_workload = 'null';
							$task->date_start = $start;
							$task->date_end = $end;
							$task->progress = 0;
							$task->update($user);
						}
						
					}
				}
			
			}
		}

	}
}