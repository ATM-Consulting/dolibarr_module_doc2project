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
				$link = $_SERVER["PHP_SELF"] . '?id=' . $object->id . '&action=create_project&from=doc2project&type='.$object->element;
				if(!empty($conf->global->DOC2PROJECT_PREVUE_BEFORE_CONVERT)){ $link = '#'; }
				$label = empty($object->fk_project) ? $langs->trans('CreateProjectAndTasks') : $langs->trans('CreateTasksInProject');
				print '<div class="inline-block divButAction"><a class="butAction" id="doc2project_create_project" href="' . $link . '">' . $label . '</a></div>';
				
				if(!empty($conf->global->DOC2PROJECT_PREVUE_BEFORE_CONVERT)){
				    if(in_array('propalcard',explode(':',$parameters['context']))){
				        $objectUrl = DOL_URL_ROOT.'/comm/propal/card.php?id='.$object->id;
				    }
				    else {
				        $objectUrl = $object->getNomUrl(0,'',0,1);
				    }
				?>
				<script type="text/javascript">
				$(document).ready(function(){
					$('#doc2project_create_project').click(function(event) {
						event.preventDefault(); // prevent default url redirrection
						
						var htmlLines;
						var page = "<?php echo dol_buildpath('/doc2project/scripts/interface.php?get=convertToProjectLines&element='.$object->element.'&id='.$object->id,2) ; ?>";
						var formId = "ajaxloaded_tablelinesform_<?php echo $object->element; ?>_<?php echo $object->id; ?>";
				        $.get(page, function (data) {
				        	htmlLines = $(data) ;//.find('#tablelines') ;
				        });

				        var $dialog = $('<form id="' + formId + '" action="<?php print $objectUrl; ?>"  method="post" ></form>')
				        .load( page , function() {

				        	$("#" + formId + " #tablelines").prop("id", "ajaxloaded_tablelines"); // change id attribute

				        	$("#" + formId + "  .linecheckbox,#" + formId + " .linecheckboxtoggle").prop("checked", true); // checked by default 

					        // reload checkbox toggle function
				            $("#" + formId + " .linecheckboxtoggle").click(function(){
				        		var checkBoxes = $("#" + formId + " .linecheckbox");
				        		checkBoxes.prop("checked", this.checked);
				        	});


				        })
				        .html(htmlLines)
				        .dialog({
				            autoOpen: false,
				            modal: true,
				            height: $(window).height()*0.8 ,//retrieve 80% of current window width
				            width: $(window).width()*0.8,//retrieve 80% of current window height
				            title: "<?php echo html_entity_decode($label); ?>",
				            buttons: {
				                    "<?php echo html_entity_decode($label); ?>": function() {
				                      	$( this ).dialog( "close" );
			    	      	        	$("#" + formId).submit();
				                    },
				                    "<?php echo $langs->trans('Cancel'); ?>": function() {
				                      $( this ).dialog( "close" );
				                    }
				            }
				        });
				        
				        $dialog.dialog('open');


						
					});
				});
				</script>
				<?php }
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
			dol_include_once('/doc2project/class/doc2project.class.php');
			
			$PDOdb = new TPDOdb;

			// CREATION OU CHARGEMENT DU PROJET
			$project = Doc2Project::createProject($object); // La méthode fetch déjà le projet s'il existe
			
			if (!empty($project->id))
			{
				$start = strtotime('today'); // La 1ère tâche démarre à la même date que la date de début du projet
				$end = '';

				//Doc2Project::parseLines($object, $project, $start,$end);

				// LIEN OBJECT / PROJECT
				$project->date_end = $end;
				if($resetProjet) $project->statut = 0;
				$project->update($user);

				$object->setProject($project->id);
				if($conf->global->DOC2PROJECT_AUTO_AFFECT_PROJECTLEADER) $project->add_contact($user->id,'PROJECTLEADER','internal');
				//exit;
				header('Location:'.dol_buildpath('/projet/tasks.php?id='.$project->id,1));
			}
			else
			{
				setEventMessage($langs->trans('Doc2ProjectErrorCanNotFetchProject'));
			}
			
		}

		return 0;
	}

	
}
