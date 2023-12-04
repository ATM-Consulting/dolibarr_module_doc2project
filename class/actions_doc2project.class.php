<?php

require_once __DIR__ . '/../backport/v19/core/class/commonhookactions.class.php';

class ActionsDoc2Project extends doc2project\RetroCompatCommonHookActions
{
	// Affichage du bouton d'action => 3.6 uniquement.....
	function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
	{
		global $conf,$langs,$db,$user;

		if($user->hasRight('projet', 'all', 'creer') &&
			((in_array('propalcard',explode(':',$parameters['context'])) && getDolGlobalInt('DOC2PROJECT_DISPLAY_ON_PROPOSAL') && $object->statut == 2)
			|| (in_array('ordercard',explode(':',$parameters['context'])) && getDolGlobalInt('DOC2PROJECT_DISPLAY_ON_ORDER') && $object->statut == 1))
		)
		{
			if((float)DOL_VERSION>=3.6) {
				$langs->load('doc2project@doc2project');
				$link = $_SERVER["PHP_SELF"] . '?id=' . $object->id . '&action=create_project&from=doc2project&type='.$object->element;
				if(getDolGlobalInt('DOC2PROJECT_PREVUE_BEFORE_CONVERT')){ $link = '#'; }
				$label = empty($object->fk_project) ? $langs->trans('CreateProjectAndTasks') : $langs->trans('CreateTasksInProject');
				print '<div class="inline-block divButAction"><a class="butAction" id="doc2project_create_project" href="' . $link . '">' . $label . '</a></div>';

				// afficher les tâches liées aux lignes de document
				if (getDolGlobalInt('DOC2PROJECT_DISPLAY_LINKED_TASKS'))
				{
					$jsonObjectData =array();

					dol_include_once('/doc2project/lib/doc2project.lib.php');
					foreach($object->lines as $i => $line)
					{
						$jsonObjectData[$line->id] = new stdClass();
						$jsonObjectData[$line->id]->id = $line->id;
						$tasksForLine = getTasksForLine($line);
						$jsonObjectData[$line->id]->LinkedTask = empty($tasksForLine) ? '' : implode('<br>', $tasksForLine);
					}
					?>

					<script type="application/javascript">
						$( document ).ready(function() {

							var jsonObjectData = <?php print json_encode($jsonObjectData) ; ?> ;

							// ADD NEW COLS
							$("#tablelines tr").each(function( index ) {

								$colSpanBase = 1; // nombre de colonnes ajoutées

								if($( this ).hasClass( "liste_titre" ))
								{
									// PARTIE TITRE
									$('<td class="linecoltasks" style="width: 100px"><?php print $langs->transnoentities('LinkedTasks'); ?></td>').insertBefore($( this ).find("th.linecoldescription,td.linecoldescription"));
								}
								else if($( this ).data( "product_type" ) == "9"){
									$( this ).find("td[colspan]:first").attr('colspan',    parseInt($( this ).find("td[colspan]:first").attr('colspan')) + 1  );
								}
								else
								{
									// PARTIE LIGNE
									var nobottom = '';
									if($( this ).hasClass( "liste_titre_create" ) || $( this ).attr("data-element") == "extrafield" ){
										nobottom = ' nobottom ';
									}

									// New columns
									$('<td class="linecoltasks' + nobottom + '" style="width: 100px"></td>').insertBefore($( this ).find("td.linecoldescription"));


									if($( this ).hasClass( "liste_titre_create" )){
										$( this ).find("td.linecoledit").attr('colspan',    parseInt($( this ).find("td.linecoledit").attr('colspan')) + $colSpanBase  );
									}

								}
							});

							// Affichage des données
							$.each(jsonObjectData, function(i, item) {
								$("#row-" + jsonObjectData[i].id + " .linecoltasks:first").html(jsonObjectData[i].LinkedTask);
								console.log("#row-" + jsonObjectData[i].id);
							});

						});
					</script>
					<?php

				}

				if(getDolGlobalInt('DOC2PROJECT_PREVUE_BEFORE_CONVERT')){
				    // Print la partie JS nécessaire à la popin
				    dol_include_once('/doc2project/lib/doc2project.lib.php');
				    printJSPopinBeforeAddTasksInProject($parameters, $object, $action, $hookmanager,$label);
				}
			}
		}

		return 0;
	}

	function formObjectOptions($parameters, &$object, &$action, $hookmanager) {

		global $langs,$db,$user,$conf;
		if($user->hasRight('projet', 'all', 'creer') &&
			((in_array('propalcard',explode(':',$parameters['context'])) && getDolGlobalInt('DOC2PROJECT_DISPLAY_ON_PROPOSAL') && $object->statut == 2)
			|| (in_array('ordercard',explode(':',$parameters['context'])) && getDolGlobalInt('DOC2PROJECT_DISPLAY_ON_ORDER') && $object->statut == 1))
			&& (float)DOL_VERSION < 3.6
		)
		{
			$langs->load('doc2project@doc2project');
			$link = $_SERVER["PHP_SELF"] . '?id=' . $object->id . '&action=create_project&from=doc2project&type='.$object->element;
			$label = empty($object->fk_project) ? $langs->trans('CreateProjectAndTasks') : $langs->trans('CreateTasksInProject');
			?>
			<script type="text/javascript">
				$(document).ready(function(){
					$('.tabsAction').append('<?php echo '<div class="inline-block divButAction"><a class="butAction" id="doc2project_create_project" href="' . $link . '">' . $label . '</a></div>'; ?>');
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

					$otherExpenses+=$f->total_ht;

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


			if (version_compare(DOL_VERSION, '18.0.0' , '<'))
			{
				$sql = "SELECT SUM(tt.task_duration) as duration_effective, SUM(tt.thm * tt.task_duration/3600) as costprice";
				$sql.= " FROM ".MAIN_DB_PREFIX."projet_task_time tt LEFT JOIN ".MAIN_DB_PREFIX."projet_task t ON (t.rowid=tt.fk_task)";
			}
			else
			{
				$sql = "SELECT SUM(tt.element_duration) as duration_effective, SUM(tt.thm * tt.element_duration/3600) as costprice";
				$sql.= " FROM ".MAIN_DB_PREFIX."element_time tt LEFT JOIN ".MAIN_DB_PREFIX."projet_task t ON (t.rowid=tt.fk_element AND tt.elementtype = 'task')";
			}
			$sql.= " WHERE t.fk_projet=".$object->id;

			$resultset = $db->query($sql);
			$obj=$db->fetch_object($resultset);


			$marge = $propalTotal - $obj->costprice - $otherExpenses;

			?>
			<tr>
				<td><?php echo $langs->trans('DurationEffective'); ?> (Jours Homme)</td>
				<td><?php echo convertSecondToTime( $obj->duration_effective,'all',getDolGlobalInt('DOC2PROJECT_NB_HOURS_PER_DAY') * 60 * 60) ?></td>

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
				if (version_compare(DOL_VERSION, '18.0.0', '<'))
				{
					$sql = "SELECT SUM(task_duration) as duration_effective, SUM(thm * task_duration/3600) as costprice";
					$sql.= " FROM ".MAIN_DB_PREFIX."projet_task_time WHERE fk_task=".$object->id;
				}
				else
				{
					$sql = "SELECT SUM(element_duration) as duration_effective, SUM(thm * element_duration/3600) as costprice";
					$sql.= " FROM ".MAIN_DB_PREFIX."element_time WHERE elementtype = 'task' AND fk_element = ".$object->id;
				}

				$resultset = $db->query($sql);
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

			if((float)DOL_VERSION>=4.0) { //TODO check version à partir de laquelle c'est dispo
				null;
			}
			else{
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

	}

	function doActions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf,$langs,$db,$user;

		if($user->hasRight('projet', 'all', 'creer') && $action == 'create_project' &&
			((in_array('propalcard',explode(':',$parameters['context'])) && $object->statut == 2)
			|| (in_array('ordercard',explode(':',$parameters['context'])) && $object->statut == 1))
		)
		{
			$langs->load('doc2project@doc2project');

			if(!defined('INC_FROM_DOLIBARR')) define('INC_FROM_DOLIBARR', true);
			dol_include_once('/doc2project/config.php');
			dol_include_once('/projet/class/project.class.php');
			dol_include_once('/projet/class/task.class.php');
			dol_include_once('/doc2project/class/doc2project.class.php');

			$PDOdb = new TPDOdb;

			// CREATION OU CHARGEMENT DU PROJET
			$project = Doc2Project::createProject($object); // La méthode fetch déjà le projet s'il existe

			if (!empty($project->id))
			{
				$start = strtotime('today'); // La 1ère tâche démarre à la même date que la date de début du projet
				$end = '';

				$TlinesInfos = Doc2Project::parseLines($object, $project, $start,$end);
                // un peu d'info c'est mieux que rien. Pour les détails par contre on peut se gratter
                if(!empty(getDolGlobalInt('DOC2PROJECT_DEBUGCREATETASK'))) {
                    $TmsgsDef = array(
                        'linesActuallyAdded' => 'mesgs', // plus fiable que linesImported
                        'linesExcluded' => 'warnings',
                        'linesImportError' => 'errors'
                    );
                    $linesThatHaveInfos = 0;
                    foreach ($TmsgsDef as $info => $level) {
                        if (! empty($TlinesInfos[$info])) {
                            $linesThatHaveInfos += $TlinesInfos[$info];
                            $msgTradKey = 'Doc2ProjectTaskCreationMessage_' . $level;
                            $msg = $langs->trans($msgTradKey, $TlinesInfos[$info]);
                            setEventMessage($msg, $level);
                        }
                    }
                    if($linesThatHaveInfos < 1) setEventMessage($langs->trans('Doc2ProjectTaskCreationMessage_NoTasksCreated'), 'warnings');
                }


				// LIEN OBJECT / PROJECT
				$project->date_end = $end;
                // il vient d'où celui-là on sait pas ???
				if(!empty($resetProjet)) $project->statut = 0;
				$ret = $project->update($user);
                if($ret < 0) setEventMessage($langs->trans('Doc2ProjectErrorUpdateProject'), 'errors');

				if (getDolGlobalInt('DOC2PROJECT_VALIDATE_CREATED_PROJECT')) $project->setValid($user);

				//$object->setProject($project->id);
				if(getDolGlobalInt('DOC2PROJECT_AUTO_AFFECT_PROJECTLEADER')) $project->add_contact($user->id,'PROJECTLEADER','internal');
				//exit;
				header('Location:'.dol_buildpath('/projet/tasks.php?id='.$project->id,1));
			}
			else
			{
				setEventMessage($langs->trans('Doc2ProjectErrorCanNotFetchProject'),'errors');
			}

		}

		return 0;
	}

	/**
	 * afterCreateProject
	 *
	 * @param array()		   $parameters	  Hook metadatas (context, etc...)
	 * @param CommonObject    &$object        The object being processed (e.g., an invoice, proposal, etc...)
	 * @param string          &$action        The current action (usually create, edit, or null)
	 * @param HookManager      $hookmanager   Hook manager instance to allow calling another hook
	 * @return int                            Returns < 0 on error, 0 on success, 1 to replace standard code
	 */
	function afterCreateProject($parameters, &$object, &$action, $hookmanager): int
	{
		global $conf, $user;
		if ($action == 'afterCreateProject' && !empty($conf->global->DOC2PROJECT_ADD_USAGE_TASK_ON_PROJECT)){
			$project = new Project($this->db);
			if ($project->fetch($parameters['project']->id) > 0){
				$project->usage_task = 1;
				if ($project->update($user, 1) >= 0) {
					return 0;
				}
				else {
					setEventMessage($this->db->lasterror());
					return -1;
				}
			}else {
				setEventMessage($this->db->lasterror());
				return -1;
			}
		}
		return 0;
	}
}
