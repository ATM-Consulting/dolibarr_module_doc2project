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
		elseif ($action == 'LINEBILL_INSERT' && $object->product_type != 9 && GETPOST('origin', 'alpha') == 'commande') 
		{
			//Récupération des %tages des tâches du projet pour les associer aux lignes de factures
			$facture = new Facture($db);
			$facture->fetch($object->fk_facture);
				
			$fk_commande = GETPOST('originid', 'int');
			
			$commande = new Commande($db);
			$commande->fetch($fk_commande);
			
			//[PH] OVER Badtrip - ne cherche pas à load la liste des taches via un objet ça sert à rien pour le moment ...
			$sql = 'SELECT rowid, progress FROM '.MAIN_DB_PREFIX.'projet_task WHERE fk_projet = '.$commande->fk_project.' AND label = "'.( !empty($object->label) ? $db->escape($object->label) : $db->escape($object->desc) ).'"';
			$resql = $db->query($sql);
			
			if ($resql && $db->num_rows($resql) > 0)
			{
				$obj = $db->fetch_object($resql); //Attention le %tage de la tache doit être >= au %tage précédent
				$facture->updateline($object->id, $object->desc, $object->subprice, $object->qty, $object->remise_percent, $object->date_start, $object->date_end, $object->tva_tx, $object->localtax1_tx, $object->localtax2_tx, 'HT', $object->info_bits, $object->product_type, $object->fk_parent_line, $object->skip_update_total, $object->fk_fournprice, $object->pa_ht, $object->label, $object->special_code, $object->array_options, $obj->progress, $object->fk_unit);
			}
			
		}

        return 0;
    }

	private function _createTask(&$db, &$object, &$project, &$user, &$conf)
	{
		global $langs;
		
		// CREATION D'UNE TACHE GLOBAL POUR LA SAISIE DES TEMPS
		if (!empty($conf->global->DOC2PROJECT_CREATE_GLOBAL_TASK))
		{
			$this->_createOneTask($db, $user, $project->id, $conf->global->DOC2PROJECT_TASK_REF_PREFIX.'GLOBAL', $langs->trans('Doc2ProjectGlobalTaskLabel'), $langs->trans('Doc2ProjectGlobalTaskDesc'));
		}
		
		$last_title_line = false;
		$TTask_parent = array();
		$fk_task_parent = 0;
		// CREATION DES TACHES PAR RAPPORT AUX LIGNES DE LA COMMANDE
		foreach($object->lines as $line) 
		{
			if (!empty($conf->global->DOC2PROJECT_CREATE_TASK_WITH_SUBTOTAL) && $conf->subtotal->enabled && $line->product_type == 9 && $line->qty >= 1 && $line->qty <= 10) null; // Si la conf
			else if ($line->product_type == 9) continue;
			
			if ($line->product_type == 9)
			{
				$label = !empty($line->product_label) ? $line->product_label : $line->desc;
				$fk_task_parent = $this->_createOneTask($db, $user, $project->id, $conf->global->DOC2PROJECT_TASK_REF_PREFIX.$line->rowid, $label, '', '', '', $fk_task_parent);
				
				$last_title_line = $line; // Problème sur la hiérarchie
			}
			// => ligne de type service											=> ligne libre
			elseif( (!empty($line->fk_product) && $line->fk_product_type == 1) || (!empty($conf->global->DOC2PROJECT_USE_NOMENCLATURE_AND_WORKSTATION) && $line->fk_product === null) ) 
			{ // On ne créé que les tâches correspondant à des services
				$product = new Product($db);
				if (!empty($line->fk_product)) $product->fetch($line->fk_product);
				
				$durationInSec = $start = $end = '';
				if (!empty($product->duration_value))
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
				$this->_createOneTask($db, $user, $project->id, $conf->global->DOC2PROJECT_TASK_REF_PREFIX.$line->rowid, $label, $line->desc, $start, $end, $fk_task_parent, $durationInSec, $line->total_ht);
				
			}
		}
	}

	private function _createOneTask(&$db, &$user, $fk_project, $ref, $label='', $desc='', $start='', $end='', $fk_task_parent=0, $planned_workload='', $total_ht='')
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