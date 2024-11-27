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
	/**
	 * @var DOLIDB $db
	 */
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

		if ($action == 'ORDER_VALIDATE' && getDolGlobalInt('DOC2PROJECT_VALID_PROJECT_ON_VALID_ORDER'))
		{
            if(!defined('INC_FROM_DOLIBARR')) define('INC_FROM_DOLIBARR', true);
			dol_include_once('/doc2project/config.php');
			dol_include_once('/projet/class/project.class.php');
			dol_include_once('/projet/class/task.class.php');
			dol_include_once('/doc2project/class/doc2project.class.php');

			// Petit hack pour simuler la provenance de doc2project ("from" et "type" sont défini en paramètre sur le lien de création en manuel)
			$_REQUEST['from'] = 'doc2project';
			$_REQUEST['type'] = 'commande';

			if($project = Doc2Project::createProject($object)) {

				$start = strtotime('today'); // La 1ère tâche démarre à la même date que la date de début du projet
				$end = '';
				Doc2Project::parseLines($object, $project, $start, $end);
				$project->setValid($user);

			}
		}
		else if ($action == 'SHIPPING_VALIDATE' && getDolGlobalInt('DOC2PROJECT_CLOTURE_PROJECT_ON_VALID_EXPEDITION'))
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

			if ($facture->type == Facture::TYPE_SITUATION)
			{
				$fk_commande = GETPOST('originid', 'int');

				$commande = new Commande($db);
				$commande->fetch($fk_commande);

				$ref_task = getDolGlobalString('DOC2PROJECT_TASK_REF_PREFIX') . $object->origin_id;

				//[PH] OVER Badtrip - ne cherche pas à load la liste des taches via un objet ça sert à rien pour le moment ...
				$sql = 'SELECT rowid, progress FROM '.MAIN_DB_PREFIX.'projet_task WHERE fk_projet = '.$commande->fk_project.' AND ref = "'.$db->escape($ref_task).'"';
				$resql = $db->query($sql);

				if ($resql && $db->num_rows($resql) > 0)
				{
					$obj = $db->fetch_object($resql); //Attention le %tage de la tache doit être >= au %tage précédent
					$facture->updateline($object->id, $object->desc, $object->subprice, $object->qty, $object->remise_percent, $object->date_start, $object->date_end, $object->tva_tx, $object->localtax1_tx, $object->localtax2_tx, 'HT', $object->info_bits, $object->product_type, $object->fk_parent_line, $object->skip_update_total, $object->fk_fournprice, $object->pa_ht, $object->label, $object->special_code, $object->array_options, $obj->progress, $object->fk_unit);
				}
			}
		}
		elseif ($action == 'LINEBILL_INSERT' && (GETPOST('origin', 'alpha') == 'commande' || GETPOST('origin', 'alpha') == 'propal') && $object instanceof FactureLigne) {

			$object->fk_parent_line = $object->origin_id;
			$object->update($user,1);
		}
		elseif ($action == 'BILL_CREATE' && $object->type == Facture::TYPE_SITUATION ){
				include_once DOL_DOCUMENT_ROOT."/compta/facture/class/facture.class.php";

				$sql = 'SELECT rowid ';
				$sql .= 'FROM '.$this->db->prefix().'facture f ';
				$sql .= 'WHERE f.situation_cycle_ref = '.$object->situation_cycle_ref .' AND situation_counter = 1';
				$resql = $db->query($sql);
			if ($resql && $db->num_rows($resql) > 0) {
				$obj = $db->fetch_object($resql);
				$facture = new Facture($db);

				if ($facture->fetch($obj->rowid)) {
					$facture->fetch_lines();

					if (!empty($facture->lines) && !empty($object->lines)) {
						$factureIndex = 0;

						foreach ($object->lines as $key => $lineObject) {
							if (isset($facture->lines[$factureIndex])) {
								$lineFacture = $facture->lines[$factureIndex];

								$lineObject->fk_parent_line = $lineFacture->fk_parent_line;
								$lineObject->update($user, 1);
								dol_syslog("Mise à jour de fk_parent_line pour la ligne $key : ".$lineObject->fk_parent_line);

								$factureIndex++; // Passer à la ligne suivante de $facture
							} else {
								dol_syslog("Plus de lignes disponibles dans la facture !");
								break; // Sortir de la boucle si on a épuisé les lignes de $facture
							}
						}
					} else {
						dol_syslog("Les lignes de la facture ou de l'objet sont vides !");
					}
				} else {
					dol_syslog('Impossible de charger la facture');
				}
			}
		}
		elseif ($action == 'BILL_VALIDATE' && !empty(getDolGlobalString('DOC2PROJECT_UPDATE_PROGRESS_TASK')))
		{
			include_once DOL_DOCUMENT_ROOT."/projet/class/task.class.php";

			$sql = ' SELECT f.fk_parent_line,fac.type, f.situation_percent';
			$sql .= ' FROM '.$this->db->prefix().'facturedet f';
			$sql .= " JOIN ".$this->db->prefix()."facture fac ON fac.rowid = f.fk_facture";
			$sql .= ' WHERE f.fk_facture = '.$object->fk_element;

			$resql = $db->query($sql);
			var_dump($sql);exit();
			if ($resql && $db->num_rows($resql) > 0) {
				while ($obj = $db->fetch_object($resql)){
					if ($obj->type == Facture::TYPE_SITUATION){
						if (!empty($obj->fk_parent_line)) {
							$sql2 = ' SELECT el.fk_target ';
							$sql2 .= ' FROM ' . $this->db->prefix() . 'element_element el ';
							$sql2 .= " WHERE el.fk_source = " . intval($obj->fk_parent_line) . " AND el.sourcetype = 'propaldet' AND el.targettype = 'project_task'";
							$resql2 = $db->query($sql2);

							if (!$resql2) {
								dol_syslog("Erreur SQL : " . $db->lasterror(), LOG_ERR);
							} else {
								$obj2 = $db->fetch_object($resql2);
								$staticTask = new Task($this->db);
								$staticTask->fetch($obj2->fk_target);
//								var_dump($obj->situation_percent);exit();
								$staticTask->progress = $obj->situation_percent;
//								var_dump($staticTask->progress);exit();

//								var_dump($staticTask->progress);exit();

								$staticTask->update($user, 1);
							}
						} else {
							dol_syslog('fk_parent_line est vide', LOG_ERR);
						}
					}elseif ($obj->type == Facture::TYPE_STANDARD){

					}

				}
			}
		}

        return 0;
    }
}
