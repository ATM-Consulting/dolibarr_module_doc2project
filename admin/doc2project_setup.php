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
    0,
    "project"
);

$ok = $conf->propal->enabled || $conf->commande->enabled;

if($ok) {
	// Setup page goes here
	$form=new Form($db);
	$var=false;
	print '<table class="noborder" width="100%">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans("Parameters").'</td>'."\n";
	print '<td align="center" width="20">&nbsp;</td>';
	print '<td align="center" width="100">'.$langs->trans("Value").'</td>'."\n";
	
	// Display convert button on proposal
	if($conf->propal->enabled) {
		$var=!$var;
		print '<tr '.$bc[$var].'>';
		print '<td>'.$langs->trans("DisplayOnProposal").'</td>';
		print '<td align="center" width="20">&nbsp;</td>';
		print '<td align="right" width="300">';
		print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
		print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
		print '<input type="hidden" name="action" value="set_DOC2PROJECT_DISPLAY_ON_PROPOSAL">';
		print $form->selectyesno("DOC2PROJECT_DISPLAY_ON_PROPOSAL",$conf->global->DOC2PROJECT_DISPLAY_ON_PROPOSAL,1);
		print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
		print '</form>';
		print '</td></tr>';
	}
	
	// Display convert button on order
	if($conf->commande->enabled) {
		$var=!$var;
		print '<tr '.$bc[$var].'>';
		print '<td>'.$langs->trans("DisplayOnOrder").'</td>';
		print '<td align="center" width="20">&nbsp;</td>';
		print '<td align="right" width="300">';
		print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
		print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
		print '<input type="hidden" name="action" value="set_DOC2PROJECT_DISPLAY_ON_ORDER">';
		print $form->selectyesno("DOC2PROJECT_DISPLAY_ON_ORDER",$conf->global->DOC2PROJECT_DISPLAY_ON_ORDER,1);
		print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
		print '</form>';
		print '</td></tr>';
	}

	// Task prefix
	$var=!$var;
	print '<tr '.$bc[$var].'>';
	print '<td>'.$langs->trans("TaskRefPrefix").'</td>';
	print '<td align="center" width="20">&nbsp;</td>';
	print '<td align="right" width="300">';
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print '<input type="hidden" name="action" value="set_DOC2PROJECT_TASK_REF_PREFIX">';
	print '<input type="text" class="flat" name="DOC2PROJECT_TASK_REF_PREFIX" value="'.$conf->global->DOC2PROJECT_TASK_REF_PREFIX.'">';
	print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
	print '</form>';
	print '</td></tr>';
	
	// Nb hour a day
	$var=!$var;
	print '<tr '.$bc[$var].'>';
	print '<td>'.$langs->trans("NbHoursPerDay").'</td>';
	print '<td align="center" width="20">&nbsp;</td>';
	print '<td align="right" width="300">';
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print '<input type="hidden" name="action" value="set_DOC2PROJECT_NB_HOURS_PER_DAY">';
	print '<input type="text" size="3" class="flat" name="DOC2PROJECT_NB_HOURS_PER_DAY" value="'.$conf->global->DOC2PROJECT_NB_HOURS_PER_DAY.'">';
	print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
	print '</form>';
	print '</td></tr>';
	
	
	$var=!$var;
	print '<tr '.$bc[$var].'>';
	print '<td>'.$langs->trans("Doc2ProjectValidateProjectOnValidateOrder").'</td>';
	print '<td align="center" width="20">&nbsp;</td>';
	print '<td align="right" width="300">';
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print '<input type="hidden" name="action" value="set_DOC2PROJECT_VALID_PROJECT_ON_VALID_ORDER">';
	print $form->selectyesno('DOC2PROJECT_VALID_PROJECT_ON_VALID_ORDER', $conf->global->DOC2PROJECT_VALID_PROJECT_ON_VALID_ORDER, 1);
	print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
	print '</form>';
	print '</td></tr>';
	
	$var=!$var;
	print '<tr '.$bc[$var].'>';
	print '<td>'.$langs->trans("Doc2ProjectClotureProjectOnValidateExpedition").'</td>';
	print '<td align="center" width="20">&nbsp;</td>';
	print '<td align="right" width="300">';
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print '<input type="hidden" name="action" value="set_DOC2PROJECT_CLOTURE_PROJECT_ON_VALID_EXPEDITION">';
	print $form->selectyesno('DOC2PROJECT_CLOTURE_PROJECT_ON_VALID_EXPEDITION', $conf->global->DOC2PROJECT_CLOTURE_PROJECT_ON_VALID_EXPEDITION, 1);
	print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
	print '</form>';
	print '</td></tr>';
	
	$var=!$var;
	print '<tr '.$bc[$var].'>';
	print '<td>'.$langs->trans("Doc2ProjectCreateTaskForParent").'</td>';
	print '<td align="center" width="20">&nbsp;</td>';
	print '<td align="right" width="300">';
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print '<input type="hidden" name="action" value="set_DOC2PROJECT_CREATE_TASK_FOR_PARENT">';
	print $form->selectyesno('DOC2PROJECT_CREATE_TASK_FOR_PARENT', $conf->global->DOC2PROJECT_CREATE_TASK_FOR_PARENT, 1);
	print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
	print '</form>';
	print '</td></tr>';
	
	$var=!$var;
	print '<tr '.$bc[$var].'>';
	print '<td>'.$langs->trans("Doc2ProjectCreateTaskForVirtualProduct").'</td>';
	print '<td align="center" width="20">&nbsp;</td>';
	print '<td align="right" width="300">';
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print '<input type="hidden" name="action" value="set_DOC2PROJECT_CREATE_TASK_FOR_VIRTUAL_PRODUCT">';
	print $form->selectyesno('DOC2PROJECT_CREATE_TASK_FOR_VIRTUAL_PRODUCT', $conf->global->DOC2PROJECT_CREATE_TASK_FOR_VIRTUAL_PRODUCT, 1);
	print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
	print '</form>';
	print '</td></tr>';
	/*
	$var=!$var;
	print '<tr '.$bc[$var].'>';
	print '<td>'.$langs->trans("Doc2ProjectCreateTaskForParent").'</td>';
	print '<td align="center" width="20">&nbsp;</td>';
	print '<td align="right" width="300">';
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print '<input type="hidden" name="action" value="set_DOC2PROJECT_CREATE_TASK_FOR_PARENT">';
	print $form->selectyesno('DOC2PROJECT_CREATE_TASK_FOR_PARENT', $conf->global->DOC2PROJECT_CREATE_TASK_FOR_PARENT, 1);
	print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
	print '</form>';
	print '</td></tr>';*/

	$var=!$var;
	print '<tr '.$bc[$var].'>';
	print '<td>'.$langs->trans("Doc2ProjectUseNomenclatureAndWorkstation").'</td>';
	print '<td align="center" width="20">&nbsp;</td>';
	print '<td align="right" width="300">';
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print '<input type="hidden" name="action" value="set_DOC2PROJECT_USE_NOMENCLATURE_AND_WORKSTATION">';
	print $form->selectyesno('DOC2PROJECT_USE_NOMENCLATURE_AND_WORKSTATION', $conf->global->DOC2PROJECT_USE_NOMENCLATURE_AND_WORKSTATION, 1);
	print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
	print '</form>';
	print '</td></tr>';
	
	$var=!$var;
	print '<tr '.$bc[$var].'>';
	print '<td>'.$langs->trans("Doc2ProjectAllowFreeLine").'</td>';
	print '<td align="center" width="20">&nbsp;</td>';
	print '<td align="right" width="300">';
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print '<input type="hidden" name="action" value="set_DOC2PROJECT_ALLOW_FREE_LINE">';
	print $form->selectyesno('DOC2PROJECT_ALLOW_FREE_LINE', $conf->global->DOC2PROJECT_ALLOW_FREE_LINE, 1);
	print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
	print '</form>';
	print '</td></tr>';
	
	$var=!$var;
	print '<tr '.$bc[$var].'>';
	print '<td>'.$langs->trans("Doc2ProjectUpdateProgressOfSituationInvoice").'</td>';
	print '<td align="center" width="20">&nbsp;</td>';
	print '<td align="right" width="300">';
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print '<input type="hidden" name="action" value="set_DOC2PROJECT_UPDATE_PROGRESS_SITUATION_INVOICE">';
	print $form->selectyesno('DOC2PROJECT_UPDATE_PROGRESS_SITUATION_INVOICE', $conf->global->DOC2PROJECT_UPDATE_PROGRESS_SITUATION_INVOICE, 1);
	print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
	print '</form>';
	print '</td></tr>';
	
	$var=!$var;
	print '<tr '.$bc[$var].'>';
	print '<td>'.$langs->trans("Doc2ProjectCreateGlobalTask").'</td>';
	print '<td align="center" width="20">&nbsp;</td>';
	print '<td align="right" width="300">';
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print '<input type="hidden" name="action" value="set_DOC2PROJECT_CREATE_GLOBAL_TASK">';
	print $form->selectyesno('DOC2PROJECT_CREATE_GLOBAL_TASK', $conf->global->DOC2PROJECT_CREATE_GLOBAL_TASK, 1);
	print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
	print '</form>';
	print '</td></tr>';
	
	$var=!$var;
	print '<tr '.$bc[$var].'>';
	print '<td>'.$form->textwithpicto($langs->trans("Doc2ProjectCreateTaskWithSubtotal"), $langs->transnoentitiesnoconv("Doc2ProjectCreateTaskWithSubtotalTooltip")).'</td>';
	print '<td align="center" width="20">&nbsp;</td>';
	print '<td align="right" width="300">';
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print '<input type="hidden" name="action" value="set_DOC2PROJECT_CREATE_TASK_WITH_SUBTOTAL">';
	print $form->selectyesno('DOC2PROJECT_CREATE_TASK_WITH_SUBTOTAL', $conf->global->DOC2PROJECT_CREATE_TASK_WITH_SUBTOTAL, 1);
	print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
	print '</form>';
	print '</td></tr>';
	
	$var=!$var;
	print '<tr '.$bc[$var].'>';
	print '<td>'.$form->textwithpicto($langs->trans("Doc2ProjectAutoAffectProjectLeader"), $langs->transnoentitiesnoconv("Doc2ProjectAutoAffectProjectLeader")).'</td>';
	print '<td align="center" width="20">&nbsp;</td>';
	print '<td align="right" width="300">';
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print '<input type="hidden" name="action" value="set_DOC2PROJECT_AUTO_AFFECT_PROJECTLEADER">';
	print $form->selectyesno('DOC2PROJECT_AUTO_AFFECT_PROJECTLEADER', $conf->global->DOC2PROJECT_AUTO_AFFECT_PROJECTLEADER, 1);
	print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
	print '</form>';
	print '</td></tr>';
	
	if($conf->workstation->enabled){
		
		$var=!$var;
		print '<tr '.$bc[$var].'>';
		print '<td>'.$form->textwithpicto($langs->trans("Doc2projectWithWorkstation"), $langs->transnoentitiesnoconv("Doc2projectWithWorkstation")).'</td>';
		print '<td align="center" width="20">&nbsp;</td>';
		print '<td align="right" width="300">';
		print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
		print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
		print '<input type="hidden" name="action" value="set_DOC2PROJECT_WITH_WORKSTATION">';
		print $form->selectyesno('DOC2PROJECT_WITH_WORKSTATION', $conf->global->DOC2PROJECT_WITH_WORKSTATION, 1);
		print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
		print '</form>';
		print '</td></tr>';
	}

	// Excluded products
	$var=!$var;
	print '<tr '.$bc[$var].'>';
	print '<td>'.$form->textwithpicto($langs->trans("Doc2ProjectExcludedProducts"), $langs->transnoentitiesnoconv("Doc2ProjectExcludedProductsDesc")).'</td>';
	print '<td align="center" width="20">&nbsp;</td>';
	print '<td align="right" width="300">';
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print '<input type="hidden" name="action" value="set_DOC2PROJECT_EXCLUDED_PRODUCTS">';
	print '<input type="text" class="flat" name="DOC2PROJECT_EXCLUDED_PRODUCTS" value="'.$conf->global->DOC2PROJECT_EXCLUDED_PRODUCTS.'">';
	print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
	print '</form>';
	print '</td></tr>';
	
} else {
	print $langs->trans('ModuleNeedProposalOrOrderModule');
}

print '</table>';

dol_fiche_end();

llxFooter();

$db->close();
