<?php
/* Data rectification script:
 *
 * Copyright (C) 2020 ATM Consulting <support@atm-consulting.fr>
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

require('../config.php');

include_once(DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php');

function showRectificationInterface() {
	global $db, $conf, $langs;
	?>
	<div class="tabs">
		<div id="t0" class="tabsElem tab inline-block tabactive"></div>
	</div>

	<!-- 1re partie : CA services d’intégration -->
	<div class="tabBar t0" id="t0content">
		<form method="post" action="">
			<input type="hidden" name="activeTab" value="<?php echo (GETPOST('activeTab') ?: 't0') ?>"/>
			<button
				class="button applyBtn"
				name="action"
				value="assignSoureTypes"
				id="assignSourceTypes">
				Laisser ou modifier
			</button>
			<button class="button" name="action" value="default" id="" style="margin-bottom: 0.5em;">
				<?php echo $langs->trans('Find') ?>
			</button>
			<table class="noborder maintable fixelellink">
				<?php
				// row templates for the table
				$headRowTemplate = '<tr>'
								   . '<th class="center">Link ID</th>'
								   . '<th class="center">Task Ref (Project Ref)</th>'
								   . '<th class="center">Task Label</th>'
								   . '<th class="center">Current Source Type</th>'
								   . '<th class="center">Select correct source type</th>'
								   . '<th class="center">Product</th>'
								   . '<th class="center">Document</th>'
								   . '<th class="center">Project</th>'
								   . '<th class="center">Which is it?</th>'
								   . '</tr>';
				$right_row_template = '<tr>'
								. '<td class="" rowspan="2"> %s </td>'
								. '<td class="" rowspan="2"> %s </td>'
								. '<td class="" rowspan="2"> %s </td>'
								. '<td class="" rowspan="2"> %s </td>'
								. '<td class=""> %s </td>'
								. '<td class=""> %s </td>'
								. '<td class=""> %s </td>'
								. '<td class=""> %s </td>'
								. '<td class=""> %s </td>'
								. '</tr>';
				$wrong_row_template = '<tr class="wrong">'
								. '<td class=""> %s </td>'
								. '<td class=""> %s </td>'
								. '<td class=""> %s </td>'
								. '<td class=""> %s </td>'
								. '<td class=""> %s </td>'
								. '</tr>';
				$emptyRow = '<tr><td colspan="6">%s</td></tr>';

				// load the data
				$data = _getTasksWithAmbiguousLink();
				printf($headRowTemplate);

				if ($data['error']) {
					printf($emptyRow, $data['error']);
				} elseif (count($data['db_rows']) === 0) {
					printf($emptyRow, $langs->trans('QueryReturnedNoRows'));
				} else {
					foreach ($data['db_rows'] as $obj) {
						$taskURL = DOL_MAIN_URL_ROOT . '/projet/tasks/task.php?id=' . (int)$obj->taskId;

						$wrong_type = array();
						$right_type = array();

						if ($obj->elelSrcType === 'orderline') {
							// si c’est bien une ligne de commande comme indiqué
							$right_type['product'] = $obj->cdetProductName;
							$right_type['document'] = '<a href="' . DOL_MAIN_URL_ROOT . '/commande/card.php?id=' . $obj->cId . '">' . $obj->cRef . '</a>';
							$right_type['project'] = '<a href="' . DOL_MAIN_URL_ROOT . '/project/card.php?id=' . $obj->cProjectId . '">' . $obj->cProjectRef . '</a>';
							// si c’est en réalité une ligne de nomenclature DE COMMANDE.
							$wrong_type['product'] = $obj->ndetProductName;
							$wrong_type['document'] = '<a href="' . DOL_MAIN_URL_ROOT . '/commande/card.php?id=' . $obj->n_cId . '">' . $obj->n_cRef . '</a>';
							$wrong_type['project'] = '<a href="' . DOL_MAIN_URL_ROOT . '/project/card.php?id=' . $obj->n_cProjectId . '">' . $obj->n_cProjectRef . '</a>';

							if (
								// la ligne de nomenclature n’est pas liée à une commande
								$obj->n_cId === null
								// ou bien le projet de la tâche cible est différent du projet de la commande de la ligne de nomenclature mais égal à celui de la commande
								// de la ligne de commande
								|| ($obj->projectId == $obj->cProjectId && $obj->projectId != $obj->n_cProjectId)) {

								$wrong_type['selected'] = false; $right_type['selected'] = true;
							} elseif (
								// le projet de la tâche cible est le même que celui de la commande de la ligne de nomenclature et est différent de celui de la commande de
								// la ligne de commande
								$obj->projectId != $obj->cProjectId && $obj->projectId == $obj->n_cProjectId
							) {
								$wrong_type['selected'] = true; $right_type['selected'] = false;
							}

						} elseif ($obj->elelSrcType === 'propaldet') {
							// si c’est bien une ligne de propal comme indiqué
							$right_type['product'] = $obj->pdetProductName;
							$right_type['document'] = '<a href="' . DOL_MAIN_URL_ROOT . '/comm/propal/card.php?id=' . $obj->pId . '">' . $obj->pRef . '</a>';
							$right_type['project'] = '<a href="' . DOL_MAIN_URL_ROOT . '/project/card.php?id=' . $obj->pProjectId . '">' . $obj->pProjectRef . '</a>';
							// si c’est en réalité une ligne de nomenclature DE PROPAL.
							$wrong_type['product'] = $obj->ndetProductName;
							$wrong_type['document'] = '<a href="' . DOL_MAIN_URL_ROOT . '/comm/propal/card.php?id=' . $obj->n_pId . '">' . $obj->n_pRef . '</a>';
							$wrong_type['project'] = '<a href="' . DOL_MAIN_URL_ROOT . '/project/card.php?id=' . $obj->n_pProjectId . '">' . $obj->n_pProjectRef . '</a>';

							if (
								// la ligne de nomenclature n’est pas liée à une proposition
								$obj->n_pId === null
								// ou bien le projet de la tâche cible est différent du projet de la proposition de la ligne de nomenclature mais égal à celui de la proposition
								// de la ligne de proposition
								|| ($obj->projectId == $obj->pProjectId && $obj->projectId != $obj->n_pProjectId)) {

								$wrong_type['selected'] = false; $right_type['selected'] = true;
							} elseif (
								// le projet de la tâche cible est le même que celui de la proposition de la ligne de nomenclature et est différent de celui de la proposition de
								// la ligne de proposition
								$obj->projectId != $obj->pProjectId && $obj->projectId == $obj->n_pProjectId
							) {
								$wrong_type['selected'] = true; $right_type['selected'] = false;
							}
						}
						printf(
							$right_row_template,
							$obj->elelId,
							'<a href="' . $taskURL . '">' . $obj->taskRef . '</a>'
								. ' (<a href="' . DOL_MAIN_URL_ROOT . '/project/card.php?id=' . $obj->projectId . '">' . $obj->projectRef . '</a>)',
							$obj->taskLabel,
							$obj->elelSrcType,
							'<input type="radio" ' . ($right_type['selected'] ? 'checked' : '') . ' name="elel' . $obj->elelId . '"/>' . $obj->elelSrcType,
							$right_type['product'],
							$right_type['document'],
							$right_type['project'],
							''
						);
						printf(
							$wrong_row_template,
							'<input type="radio" ' . ($wrong_type['selected'] ? 'checked' : '') . ' name="elel' . $obj->elelId . '"/>' . 'nomenclaturedet',
							$wrong_type['product'],
							$wrong_type['document'],
							$wrong_type['project'],
							''
						);
					}
				}
				?>
			</table>
		</form>
	</div>
	<?php
	// –––––––––––––––––––––––––––––––––––– Javascript
	?>
	<script type="text/javascript">
		(function() {
			let nTabs = 2;
			let getCheckAllCallback = function(suffix) {
				return function(ev) {
					let TOppyCheckbox = $('input[name^=oppy' + suffix + ']:not([disabled])');
					if (ev.target.checked) {
						TOppyCheckbox.prop('checked', true);
					} else {
						TOppyCheckbox.prop('checked', false);
					}
				};
			};
			let getApplyCallback = function(suffix, confirmMessage) {
				return function(ev) {
					let TOppyChecked = $('input[name^=oppy' + suffix + ']:checked');

					if (TOppyChecked.length === 0) {
						$.jnotify('Aucune OPPY sélectionnée', 'warning');
						ev.preventDefault();
					} else if (!confirm(confirmMessage.replace(/%d/g, TOppyChecked.length))
					) {
						ev.preventDefault();
					}
				};
			};

			let getTabActivatorCallback = function(tabId) {
				// /*   */  return function(ev){};   /*    */
				return function(ev) {
					for (let i = 0; i < nTabs; i++) {
						$('#t' + i).removeClass('tabactive');
						$('.t' + i).hide();
					}
					$('input[name^="activeTab"]').val('t' + tabId);
					$('#t' + tabId).addClass('tabactive');
					$('.t' + tabId).show();
				};
			};

			$('#checkAllCA').change(getCheckAllCallback('CA'));
			$('button#applyCA').click(getApplyCallback(
				'CA',
				'Confirmez-vous le remplacement du CA services d’intégration pour les %d OPPY sélectionnées ?'
			));

			$('#checkAllREA').change(getCheckAllCallback('REA'));
			$('button#applyREA').click(getApplyCallback(
				'REA',
				'Confirmez-vous l’ajout d’une tâche REA pour compléter le temps REA total des %d OPPY sélectionnées ?'
			));


			let activeTab = "<?php echo (GETPOST('activeTab') ?: '0') ?>";
			for (let i = 0; i < nTabs; i++) {
				$('#t' + i).click(getTabActivatorCallback(i));
			}
			getTabActivatorCallback(parseInt(activeTab.replace(/^t(\d+)/, '$1')))();
		})();
	</script>

	<?php
}

/**
 * @return array
 */
function _getTasksWithAmbiguousLink() {
	$sql = /** @lang SQL */
		'SELECT'
		. '        elel.rowid               AS elelId,'          //
		. '        elel.sourcetype          AS elelSrcType,'     //
		. '        task.rowid               AS taskId,'          // tâche cible
		. '        task.ref                 AS taskRef,'         // tâche cible
		. '        task.label               AS taskLabel,'       // tâche cible
		. '        project.rowid            AS projectId,'       // projet de la tâche cible
		. '        project.ref              AS projectRef,'      // projet de la tâche cible
		. '        ndet_product.label       AS ndetProductName,' // produit de ligne de nomenclature
		. '        pdet_product.label       AS pdetProductName,' // produit de la ligne de propal si correct
		. '        cdet_product.label       AS cdetProductName,' // produit de la ligne de commande si correct
		. '        commande.rowid           AS cId,'             // commande si correct
		. '        commande.ref             AS cRef,'            // commande si correct
		. '        propal.rowid             AS pId,'             // proposition si correct
		. '        propal.ref               AS pRef,'            // proposition si correct
//		. '        n.fk_object              AS n_targetId,'      // à quoi est rattachée la nomenclature parente (@see n_cId / n_pId)
		. '        n_commande.rowid         AS n_cId,'           // commande si faux
		. '        n_commande.ref           AS n_cRef,'          // commande si faux
		. '        n_propal.rowid           AS n_pId,'           // propale si faux
		. '        n_propal.ref             AS n_pRef,'          // propale si faux
		. '        n.object_type            AS n_objtype,'       // à quel type d’objet est rattachée la nomenclature parente
		. '        c_project.ref            AS cProjectRef,'      // projet parent de la commande si la source est une ligne de commande
		. '        c_project.rowid          AS cProjectId,'       // projet parent de la commande si la source est une ligne de commande
		. '        p_project.ref            AS pProjectRef,'      // projet parent de la propal si la source est une ligne de propal
		. '        p_project.rowid          AS pProjectId,'       // projet parent de la propal si la source est une ligne de propal
		. '        n_c_project.ref          AS n_cProjectRef,'    // projet parent de la commande si la source est une ligne de nomenclature de ligne de commande
		. '        n_c_project.rowid        AS n_cProjectId,'     // projet parent de la commande si la source est une ligne de nomenclature de ligne de commande
		. '        n_p_project.ref          AS n_pProjectRef,'    // projet parent de la propal si la source est une ligne de nomenclature de ligne de propal
		. '        n_p_project.rowid        AS n_pProjectId'     // projet parent de la propal si la source est une ligne de nomenclature de ligne de propal
		. '        FROM '.MAIN_DB_PREFIX.'element_element AS elel'
		. '             LEFT JOIN '.MAIN_DB_PREFIX.'nomenclaturedet AS ndet ON elel.fk_source       = ndet.rowid'
		. '             LEFT JOIN '.MAIN_DB_PREFIX.'product AS ndet_product ON ndet.fk_product      = ndet_product.rowid'
		. '             LEFT JOIN '.MAIN_DB_PREFIX.'nomenclature AS n       ON ndet.fk_nomenclature = n.rowid'
		. '             LEFT JOIN '.MAIN_DB_PREFIX.'commande AS n_commande  ON n.object_type        = \'commande\' AND n.fk_object = n_commande.rowid'
		. '             LEFT JOIN '.MAIN_DB_PREFIX.'propal AS n_propal      ON n.object_type        = \'propal\'   AND n.fk_object = n_propal.rowid'
		. '             LEFT JOIN '.MAIN_DB_PREFIX.'commandedet     cdet    ON elel.fk_source       = cdet.rowid AND elel.sourcetype = \'orderline\''
		. '             LEFT JOIN '.MAIN_DB_PREFIX.'product AS cdet_product ON cdet.fk_product      = cdet_product.rowid'
		. '             LEFT JOIN '.MAIN_DB_PREFIX.'commande AS commande    ON cdet.fk_commande     = commande.rowid'
		. '             LEFT JOIN '.MAIN_DB_PREFIX.'propaldet AS    pdet    ON elel.fk_source       = pdet.rowid AND elel.sourcetype = \'propaldet\''
		. '             LEFT JOIN '.MAIN_DB_PREFIX.'product AS pdet_product ON pdet.fk_product      = pdet_product.rowid'
		. '             LEFT JOIN '.MAIN_DB_PREFIX.'propal   AS propal      ON pdet.fk_propal       = propal.rowid'
		. '             LEFT JOIN '.MAIN_DB_PREFIX.'projet_task AS  task    ON elel.fk_target       = task.rowid'
		. '             LEFT JOIN '.MAIN_DB_PREFIX.'projet AS  project      ON task.fk_projet       = project.rowid'
		. '             LEFT JOIN '.MAIN_DB_PREFIX.'projet AS  c_project    ON commande.fk_projet   = c_project.rowid'
		. '             LEFT JOIN '.MAIN_DB_PREFIX.'projet AS  p_project    ON propal.fk_projet     = p_project.rowid'
		. '             LEFT JOIN '.MAIN_DB_PREFIX.'projet AS  n_c_project  ON n_commande.fk_projet = n_c_project.rowid'
		. '             LEFT JOIN '.MAIN_DB_PREFIX.'projet AS  n_p_project  ON n_propal.fk_projet   = n_p_project.rowid'
		. ' WHERE elel.targettype = \'project_task\''
		. '   AND elel.sourcetype IN (\'orderline\', \'propaldet\')'
		. '   AND ndet.rowid IS NOT NULL'
		. '   AND (cdet.rowid IS NOT NULL OR pdet.rowid IS NOT NULL)'
		. ' ORDER BY elel.rowid DESC'
//		. ' LIMIT 300;'
	;
	return getSQLResults($sql);
}

/**
 * Retourne le jeu de données complet retourné par la requête $sql, en cas d’échec, la clé 'error' contiendra
 * le détail.
 *
 * @param string $sql
 * @return array  Keys: 'error', 'num_rows', 'db_rows'
 */
function getSQLResults($sql) {
	global $db;
	$data = array(
		'error' => null,
		'num_rows' => 0,
		'db_rows' => array(),
	);
	$resql = $db->query($sql);
	if (!$resql) {
		$data['error'] = $db->lasterror();
		return $data;
	}
	$data['num_rows'] = $num_rows = $db->num_rows($resql);
	for ($i = 0; $i < $num_rows; $i++) {
		$data['db_rows'][] = $obj = $db->fetch_object($resql);
	}
	return $data;
}

llxHeader();
if (!$user->admin) {
	printf('Page accessible au compte administrateur uniquement.');
	exit;
}
?>
	<style>
		.center          { text-align: center; }
		.left            { text-align: left; }
		.right           { text-align: right; }
		.tab             { padding: 1em; color: blue; font-weight: bold; cursor: pointer; text-align: center; }
		.applyBtn        { margin-bottom: 0.5em; }
		.datepicker-span { display: inline-block; margin: 0.2em 0.4em; padding: 0.2em; border: solid #eef 1px; }
		table.noborder > tbody > tr > td {
			border-left:  1px solid #efefef;
		}
		table.noborder > tbody > tr > td:last-child {
			border-right: 1px solid #efefef;
		}
		table.fixelellink.maintable > tbody > tr:nth-child(even) > td {
			border-top: 2px solid black;
		}
		table.subtable   { display: inline-block; }
		table.subtable > tbody > tr > td { border: none !important; }
	</style>
<?php

$action = GETPOST('action', 'aZ09');

function applyComputed($type) {
	$TOppy = GETPOST('oppy' . $type, 'array');
	if (empty($TOppy)) return;
	if ($type === 'CA')  return applyComputedCA($TOppy);
	if ($type === 'REA') return applyComputedREA($TOppy);
}

switch (GETPOST('action')) {
	case 'applyComputedCA':
		applyComputed('CA');
		break;
	case 'applyComputedREA':
		applyComputed('REA');
		break;
	default:
		// nothing special
}
showRectificationInterface();

llxFooter();

