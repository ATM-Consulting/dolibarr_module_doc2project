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
     * Execute a trigger action for supported business events.
     *
     * @param string    $action Event action code
     * @param Object    $object Current business object
     * @param User      $user   Current user
     * @param Translate $langs  Translation handler
     * @param Conf      $conf   Global configuration
     * @return int              <0 if KO, 0 if nothing done, >0 if OK
     */
    public function runTrigger($action, $object, $user, $langs, $conf)
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
		elseif ($action == 'LINEBILL_INSERT' && $object->product_type != 9 && GETPOST('origin', 'alpha') == 'commande'
			|| $action == 'LINEBILL_INSERT' && $object->product_type != 9 && GETPOST('origin', 'alpha') == 'propal'
			|| $action == 'LINEBILL_INSERT' && $object->product_type != 9 && GETPOST('origin', 'alpha') == 'facture')
		{
			//Récupération des %tages des tâches du projet pour les associer aux lignes de factures
			$facture = new Facture($db);
			$facture->fetch($object->fk_facture);

			$fk_originId = GETPOST('originid', 'int');
			$fk_origin = GETPOST('origin', 'alpha');

			if ($facture->type == Facture::TYPE_SITUATION) {
				if ($fk_origin == 'commande') {
					$commande = new Commande($db);
					$commande->fetch($fk_originId);

					$ref_task = getDolGlobalString('DOC2PROJECT_TASK_REF_PREFIX') . $object->origin_id;

					$sql = 'SELECT rowid, progress FROM ' . $db->prefix() . 'projet_task WHERE fk_projet = ' . $commande->fk_project . ' AND ref = "' . $db->escape($ref_task) . '"';
					$resql = $db->query($sql);

					if ($resql && $db->num_rows($resql) > 0) {
						$obj = $db->fetch_object($resql); //Attention le %tage de la tache doit être >= au %tage précédent
						$facture->updateline($object->id, $object->desc, $object->subprice, $object->qty, $object->remise_percent, $object->date_start, $object->date_end, $object->tva_tx, $object->localtax1_tx, $object->localtax2_tx, 'HT', $object->info_bits, $object->product_type, $object->fk_parent_line, $object->skip_update_total, $object->fk_fournprice, $object->pa_ht, $object->label, $object->special_code, $object->array_options, $obj->progress, $object->fk_unit);
					} else {
						dol_syslog($db->lasterror(), LOG_ERR);
						setEventMessages($db->lasterror(), null, 'errors');
					}
				}
			}

			if (getDolGlobalString('DOC2PROJECT_TASK_PROGRESS_DEPOSIT_INVOICE')) {
					$objectToInstanciate = ucfirst($fk_origin);

					$newObject = new $objectToInstanciate($db);
					$newObject->fetch($fk_originId);

					$ref_task = getDolGlobalString('DOC2PROJECT_TASK_REF_PREFIX') . $object->origin_id;

					if ($fk_origin == 'facture') {
						$sql = "SELECT fk_source, sourcetype FROM " . $db->prefix() . "element_element WHERE `fk_target` = " . intval($object->origin_id);
					} else {
						$sql = 'SELECT rowid FROM ' . $db->prefix() . 'projet_task WHERE fk_projet = ' . $newObject->fk_project . ' AND ref = "' . $db->escape($ref_task) . '"';
					}
					$resql = $db->query($sql);

					if ($resql && $db->num_rows($resql) > 0) {
						$obj = $db->fetch_object($resql);
						if ($fk_origin == 'facture') {
							$object->add_object_linked($obj->sourcetype, $obj->fk_source, $user);
						} else {
							$object->add_object_linked('task', $obj->rowid, $user);
							$object->add_object_linked($object->origin, $object->origin_id, $user);
						}
						// Besoin du rowid de l'objet d'origine pour le calcule du $ de progretion lors d'une facture standard
						$object->fk_parent_line = $object->origin_id;
						$object->update($user,1);
					} else {
						dol_syslog($db->lasterror(), LOG_ERR);
						setEventMessages($db->lasterror(), null, 'errors');
					}
			}
		}
		elseif ($action == 'LINEBILL_MODIFY' && getDolGlobalString('DOC2PROJECT_TASK_PROGRESS_DEPOSIT_INVOICE')){
			$facture = new Facture($db);
			$facture->fetch($object->fk_facture);

			include_once DOL_DOCUMENT_ROOT.'/projet/class/task.class.php';

			$sql = "SELECT * FROM " . $db->prefix() . "element_element WHERE `fk_target` = " . intval($object->id) . " AND sourcetype = 'task'";

			$resql = $db->query($sql);

			if ($resql->num_rows > 0) {
				$obj = $db->fetch_object($resql);
				$object->origin = $obj->sourcetype;
				$object->origin_id = $obj->fk_source;
				$object->fetch_origin();

				$task = new Task($db);
				$res = $task->fetch($object->origin_id);

				if ($res) {
					if ($facture->type == Facture::TYPE_SITUATION){
						$task->progress = $object->situation_percent;
					} elseif ($facture->type == Facture::TYPE_STANDARD) {

						$propalLine = new PropaleLigne($db);
						$res = $propalLine->fetch($object->fk_parent_line);

						if ($res) {
							$qtyTotal = $propalLine->qty;
							$qty = $object->qty;// la qty dans la facture
							$task->progress = round(($qty / $qtyTotal) * 100, 0);
						}
					}
					$task->update($user);
				}
			} else {
				dol_syslog($db->lasterror(), LOG_ERR);
				setEventMessages($db->lasterror(), null, 'errors');
			}

		}
		elseif ($action == 'BILL_VALIDATE' && getDolGlobalString('DOC2PROJECT_TASK_PROGRESS_DEPOSIT_INVOICE')) {

			dol_include_once('/projet/class/task.class.php');

			foreach ($object->lines as $line) {
				$sql = "SELECT * FROM " . $db->prefix() . "element_element WHERE `fk_target` = " . intval($line->id) . " AND sourcetype = 'task'";

				$resql = $db->query($sql);

				if ($resql->num_rows > 0) {
					$obj = $db->fetch_object($resql);
					$line->origin = $obj->sourcetype;
					$line->origin_id = $obj->fk_source;
					$line->fetch_origin();

					$task = new Task($db);
					$res = $task->fetch($line->origin_id);

					if ($res) {
						if ($object->type == Facture::TYPE_SITUATION){
							$task->progress = $line->situation_percent;
						} elseif ($object->type == Facture::TYPE_STANDARD) {

							$propalLine = new PropaleLigne($db);
							$res = $propalLine->fetch($line->fk_parent_line);

							if ($res) {
								$qtyTotal = $propalLine->qty;
								$qty = $line->qty;// la qty dans la facture
								$task->progress = round(($qty / $qtyTotal) * 100, 0);
							}
						}
						$task->update($user);
					}
				} else {
					dol_syslog($db->lasterror(), LOG_ERR);
					setEventMessages($db->lasterror(), null, 'errors');
				}
			}

		}

        return 0;
    }
}
