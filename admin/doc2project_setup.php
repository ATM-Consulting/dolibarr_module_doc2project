<?php
/* <one line to give the program's name and a brief idea of what it does.>
 * Copyright (C) 2015 ATM Consulting <support@atm-consulting.fr>
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
 * 	\file		admin/doc2project.php
 * 	\ingroup	doc2project
 * 	\brief		This file is an example module setup page
 * 				Put some comments here
 */
// Dolibarr environment
$res = @include("../../main.inc.php"); // From htdocs directory
if (! $res) {
    $res = @include("../../../main.inc.php"); // From "custom" directory
}

// Libraries
require_once DOL_DOCUMENT_ROOT . "/core/lib/admin.lib.php";
require_once '../lib/doc2project.lib.php';

// Translations
$langs->load("doc2project@doc2project");

$newToken = function_exists('newToken') ? newToken() : $_SESSION['newtoken'];

// Access control
if (! $user->admin) {
    accessforbidden();
}

// Parameters
$action = GETPOST('action', 'alpha');

/*
 * Actions
 */
if (preg_match('/set_(.*)/',$action,$reg))
{
	$code=$reg[1];
	if (dolibarr_set_const($db, $code, GETPOST($code), 'chaine', 0, '', $conf->entity) > 0)
	{
		header("Location: ".$_SERVER["PHP_SELF"]);
		exit;
	}
	else
	{
		dol_print_error($db);
	}
}

if (preg_match('/del_(.*)/',$action,$reg))
{
	$code=$reg[1];
	if (dolibarr_del_const($db, $code, 0) > 0)
	{
		Header("Location: ".$_SERVER["PHP_SELF"]);
		exit;
	}
	else
	{
		dol_print_error($db);
	}
}

/*
 * View
 */
$page_name = "Doc2ProjectSetup";
llxHeader('', $langs->trans($page_name));

// Subheader
$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php">'
    . $langs->trans("BackToModuleList") . '</a>';
print_fiche_titre($langs->trans($page_name), $linkback);

// Configuration header
$head = doc2projectAdminPrepareHead();
dol_fiche_head(
    $head,
    'settings',
    $langs->trans("Module104250Name"),
    -1,
    "project"
);

$ok = $conf->propal->enabled || $conf->commande->enabled;

$abricotIsPresent = dol_buildpath('abricot/langs/fr_FR/abricot.lang');
if(empty($abricotIsPresent) || !file_exists($abricotIsPresent)){
	// TODO modifier l'url avec TechATM une fois que les redirection de docs sera en ligne
	print '<div class="warning" >'.$langs->trans('AbricotNeeded').' : <a href="http://wiki.atm-consulting.fr/index.php/Accueil#Abricot" target="_blank"><i class="fa fa-info"></i> Wiki</a></div>';
}


if($ok) {
	// Setup page goes here
	$form=new Form($db);
	$var=false;
	print '<table class="noborder liste" width="100%">';

	/**
	 * SOURCE DOCUMENTS PARAMETERS
	 */
	_print_title($langs->trans("Doc2ProjectDocumentsSourceParameters"));


	// Display convert button on proposal
	if($conf->propal->enabled) {
	    _print_on_off('DOC2PROJECT_DISPLAY_ON_PROPOSAL', $langs->trans('DisplayOnProposal'));
	}

	// Display convert button on order
	if($conf->commande->enabled) {
	    _print_on_off('DOC2PROJECT_DISPLAY_ON_ORDER', $langs->trans('DisplayOnOrder'));
	}

	// Display linked tasks on document lines
	_print_on_off('DOC2PROJECT_DISPLAY_LINKED_TASKS');

	/**
	 * PROJECT PARAMETERS
	 */
	_print_title($langs->trans("Doc2ProjectParameters"));


	$metas = array( 'placeholder'=> $langs->trans('Doc2ProjectTitle', '{refclient/ref}').' '.$langs->trans('DocConverted') );
	_print_input_form_part('DOC2PROJECT_TITLE_PROJECT', false, '', $metas, 'input', $langs->trans('DOC2PROJECT_TITLE_PROJECT_info'));

	// Affecter en tant que chef de projet l'utilisateur créant le projet depuis la propale, commande.
	_print_on_off('DOC2PROJECT_AUTO_AFFECT_PROJECTLEADER', $langs->trans('Doc2ProjectAutoAffectProjectLeader'));

	_print_on_off('DOC2PROJECT_SET_PROJECT_DRAFT');

	// Créer et valider un projet sur la validation d'une commande client
	_print_on_off('DOC2PROJECT_VALID_PROJECT_ON_VALID_ORDER', $langs->trans('Doc2ProjectValidateProjectOnValidateOrder'));

	_print_on_off('DOC2PROJECT_VALIDATE_CREATED_PROJECT');

	// Clôturer le projet sur la validation d'une expédition
	_print_on_off('DOC2PROJECT_CLOTURE_PROJECT_ON_VALID_EXPEDITION', $langs->trans('Doc2ProjectClotureProjectOnValidateExpedition'));

	/**
	 * TASK PARAMETERS
	 */
	_print_title($langs->trans("Doc2ProjectTasksParameters"));

	// Task prefix
	_print_input_form_part('DOC2PROJECT_TASK_REF_PREFIX', $langs->trans("TaskRefPrefix"));

	// Nb hour a day
	$metas = array(
	    'type' => 'number',
	    'min' => 0,
	    'step' => 0.01
    );
	_print_input_form_part('DOC2PROJECT_NB_HOURS_PER_DAY', $langs->trans("NbHoursPerDay"), '', $metas);

	// Calculer les dates des tâches ajoutées en fonction de la vélocité (Nombre d'heures journalier)
	_print_on_off('DOC2PROJECT_TASK_RECALC_DATE_BY_VELOCITY');



	// Créer la le service initial en tant que tâche parente des sous-produits associés
	_print_on_off('DOC2PROJECT_CREATE_TASK_FOR_PARENT', $langs->trans('Doc2ProjectCreateTaskForParent'));

	// Créer autant de tâche que de sous-services associés au service parent
	_print_on_off('DOC2PROJECT_CREATE_TASK_FOR_VIRTUAL_PRODUCT', $langs->trans('Doc2ProjectCreateTaskForVirtualProduct'));

	// Coupler l'utilisation du module à "Nomenclature" et "Workstation"
	_print_on_off('DOC2PROJECT_USE_NOMENCLATURE_AND_WORKSTATION', $langs->trans('Doc2ProjectUseNomenclatureAndWorkstation'));

	// Autoriser la création de tâche pour une ligne libre
	_print_on_off('DOC2PROJECT_ALLOW_FREE_LINE', $langs->trans('Doc2ProjectAllowFreeLine'));

	// Récupérer la progression des tâches pour les reporter dans les factures de situations
	_print_on_off('DOC2PROJECT_UPDATE_PROGRESS_SITUATION_INVOICE', $langs->trans('Doc2ProjectUpdateProgressOfSituationInvoice'));

	// Créer une tâche globale lors de la validation d'une commande
	_print_on_off('DOC2PROJECT_CREATE_GLOBAL_TASK', $langs->trans('Doc2ProjectCreateGlobalTask'));

	// Créer des tâches correspondant aux titres afin de garder une hiérarchie (module "Subtotal" requis)
	_print_on_off('DOC2PROJECT_CREATE_TASK_WITH_SUBTOTAL', $langs->trans('Doc2ProjectCreateTaskWithSubtotal'), '', $langs->trans("Doc2ProjectCreateTaskWithSubtotalTooltip"));


	// Créer les sprints en fonction des lignes titres contenus dans le document
	if($conf->subtotal->enabled && $conf->scrumboard->enabled){
	    _print_on_off('DOC2PROJECT_CREATE_SPRINT_FROM_TITLE', $langs->trans('Doc2ProjectCreateSprintFromTitle'));
	}

	// Créer autant de tâches qu'il y a de postes de travail associés au produit/service
	if($conf->workstationatm->enabled){
	    _print_on_off('DOC2PROJECT_WITH_WORKSTATION', $langs->trans('Doc2projectWithWorkstation'), '', $langs->trans('Doc2projectWithWorkstation'));
	}

	// Excluded products
	_print_input_form_part('DOC2PROJECT_EXCLUDED_PRODUCTS', $langs->trans('Doc2ProjectExcludedProducts'), '', array(), 'input', $langs->transnoentitiesnoconv("Doc2ProjectExcludedProductsDesc"));


	_print_on_off('DOC2PROJECT_DO_NOT_CONVERT_SERVICE_WITH_PRICE_ZERO', $langs->trans('Doc2ProjectDoNotConvertServiceWithPriceToZero'));

	_print_on_off('DOC2PROJECT_DO_NOT_CONVERT_SERVICE_WITH_QUANTITY_ZERO', $langs->trans('Doc2ProjectDoNotConvertServiceWithQuantityToZero'));

	_print_on_off('DOC2PROJECT_PREVUE_BEFORE_CONVERT', $langs->trans('Doc2ProjectPrevueBeforeConvert'));

	_print_on_off('DOC2PROJECT_GROUP_TASKS', $langs->trans('Doc2ProjectGroupTasks'));

	_print_on_off('DOC2PROJECT_GROUP_TASKS_BY_SPRINT', $langs->trans('Doc2ProjectGroupTasksBySprint'));

	_print_on_off('DOC2PROJECT_ALWAYS_ADD_THIRDPARTY_PROJECT_TITLE',$langs->trans('DOC2PROJECT_ALWAYS_ADD_THIRDPARTY_PROJECT_TITLE'), '',  $langs->transnoentitiesnoconv("DOC2PROJECT_ALWAYS_ADD_THIRDPARTY_PROJECT_TITLE_help"));

	// Conversion rule
	$metas = array(
	    'rows' => 5,
	    'cols' => 50
	);
	_print_input_form_part('DOC2PROJECT_CONVERSION_RULE', $langs->trans('Doc2ProjectConversionRule'), '', $metas, 'textarea', $langs->transnoentitiesnoconv("Doc2ProjectConversionRuleDesc"));

	_print_input_form_part('DOC2PROJECT_TASK_NAME', $langs->trans('DOC2PROJECT_TASK_NAME'), '', array(), 'input', $langs->transnoentitiesnoconv("DOC2PROJECT_TASK_NAME_HELP"));

	_print_on_off('DOC2PROJECT_USE_SPECIFIC_STORY_TO_CREATE_TASKS', $langs->trans('Doc2ProjectUseSpecificStoryToCreateTasks'));


    $var=!$var;
    print '<tr '.$bc[$var].'>';
    print '<td>'.$langs->trans("DOC2PROJECT_CONVERT_NOMENCLATUREDET_INTO_TASKS").'</td>';
    print '<td align="center" width="20">&nbsp;</td>';
    print '<td align="right" width="300">';
    print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
    print '<input type="hidden" name="token" value="'.$newToken.'">';
    print '<input type="hidden" name="action" value="set_DOC2PROJECT_CONVERT_NOMENCLATUREDET_INTO_TASKS">';
    $TVal = array(
        '' => ''
        ,'onlyTNomenclatureDet' => $langs->trans('d2p_onlyTNomenclatureDet')
        ,'onlyTNomenclatureWorkstation' => $langs->trans('d2p_onlyTNomenclatureWorkstation')
        ,'both' => $langs->trans('d2p_Both')
    );
    print Form::selectarray('DOC2PROJECT_CONVERT_NOMENCLATUREDET_INTO_TASKS', $TVal, $conf->global->DOC2PROJECT_CONVERT_NOMENCLATUREDET_INTO_TASKS);
    print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
    print '</form>';
    print '</td></tr>';


} else {
	print $langs->trans('ModuleNeedProposalOrOrderModule');
}

print '</table>';

dol_fiche_end(-1);

llxFooter();

$db->close();




function _print_title($title="")
{
    global $langs;
    print '<tr class="liste_titre">';
    print '<td>'.$langs->trans($title).'</td>'."\n";
    print '<td align="center" width="20">&nbsp;</td>';
    print '<td align="center" ></td>'."\n";
    print '</tr>';
}

function _print_on_off($confkey, $title = false, $desc ='', $help = false)
{
    global $var, $bc, $langs, $conf;
    $var=!$var;

    $form=new Form($db);

    print '<tr '.$bc[$var].'>';
    print '<td>';

    if(!empty($help)){
        print $form->textwithtooltip( ($title?$title:$langs->trans($confkey)) , $langs->trans($help),2,1,img_help(1,''));
    }
    else {
        print $title?$title:$langs->trans($confkey);
    }

    if(!empty($desc))
    {
        print '<br><small>'.$langs->trans($desc).'</small>';
    }
    print '</td>';
    print '<td align="center" width="20">&nbsp;</td>';
    print '<td align="right" width="300">';
    print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
    print '<input type="hidden" name="token" value="'.$newToken.'">';
    print '<input type="hidden" name="action" value="set_'.$confkey.'">';
    print ajax_constantonoff($confkey);
    print '</form>';
    print '</td></tr>';
}

function _print_input_form_part($confkey, $title = false, $desc ='', $metas = array(), $type='input', $help = false)
{
    global $var, $bc, $langs, $conf, $db;
    $var=!$var;

    $form=new Form($db);

    $defaultMetas = array(
        'name' => $confkey
    );

    if($type!='textarea'){
        $defaultMetas['type']   = 'text';
        $defaultMetas['value']  = $conf->global->{$confkey};
    }


    $metas = array_merge ($defaultMetas, $metas);
    $metascompil = '';
    foreach ($metas as $key => $values)
    {
        $metascompil .= ' '.$key.'="'.$values.'" ';
    }

    print '<tr '.$bc[$var].'>';
    print '<td>';

    if(!empty($help)){
        print $form->textwithtooltip( ($title?$title:$langs->trans($confkey)) , $langs->trans($help),2,1,img_help(1,''));
    }
    else {
        print $title?$title:$langs->trans($confkey);
    }

    if(!empty($desc))
    {
        print '<br><small>'.$langs->trans($desc).'</small>';
    }

    print '</td>';
    print '<td align="center" width="20">&nbsp;</td>';
    print '<td align="right" width="300">';
    print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
    print '<input type="hidden" name="token" value="'.$newToken.'">';
    print '<input type="hidden" name="action" value="set_'.$confkey.'">';
    if($type=='textarea'){
        print '<textarea '.$metascompil.'  >'.dol_htmlentities($conf->global->{$confkey}).'</textarea>';
    }
    else {
        print '<input '.$metascompil.'  />';
    }

    print '<input type="submit" class="butAction" value="'.$langs->trans("Modify").'">';
    print '</form>';
    print '</td></tr>';
}
