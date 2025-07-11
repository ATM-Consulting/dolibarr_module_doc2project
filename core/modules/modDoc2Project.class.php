<?php
/* Copyright (C) 2015      ATM Consulting <support@atm-consulting.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *    \defgroup   doc2project     Module Doc2Project
 *  \brief      Example of a module descriptor.
 *                Such a file must be copied into htdocs/doc2project/core/modules directory.
 *  \file       htdocs/doc2project/core/modules/modDoc2Project.class.php
 *  \ingroup    doc2project
 *  \brief      Description and activation file for module Doc2Project
 */
include_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';

require_once __DIR__ . '/../../class/Doc2ProjectTools.php';

/**
 *  Description and activation class for module Doc2Project
 */
class modDoc2Project extends DolibarrModules
{
	/**
	 *   Constructor. Define names, constants, directories, boxes, permissions
	 *
	 * @param DoliDB $db Database handler
	 */
	function __construct($db)
	{
		global $langs, $conf;

		$this->db = $db;

		// Id for module (must be unique).
		// Use here a free id (See in Home -> System information -> Dolibarr for list of used modules id).
		$this->numero = 104250; // 104000 to 104999 for ATM CONSULTING
		$this->editor_name = 'ATM Consulting';
		$this->editor_url = 'https://www.atm-consulting.fr';
		// Key text used to identify module (for permissions, menus, etc...)
		$this->rights_class = 'doc2project';

		// Family can be 'crm','financial','hr','projects','products','ecm','technic','other'
		// It is used to group modules in module setup page
		$this->family = "projects";
		// Module label (no space allowed), used if translation string 'ModuleXXXName' not found (where XXX is value of numeric property 'numero' of module)
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		// Module description, used if translation string 'ModuleXXXDesc' not found (where XXX is value of numeric property 'numero' of module)
		$this->description = "Convert a proposal or customer order to a project";
		// Possible values for version are: 'development', 'experimental', 'dolibarr' or version

		$this->version = '3.8.1';

		// Url to the file with your last numberversion of this module
		require_once __DIR__ . '/../../class/techatm.class.php';
		$this->url_last_version = \doc2project\TechATM::getLastModuleVersionUrl($this);
		// Key used in llx_const table to save module status enabled/disabled (where MYMODULE is value of property name of module in uppercase)
		$this->const_name = 'MAIN_MODULE_' . strtoupper($this->name);
		// Where to store the module in setup page (0=common,1=interface,2=others,3=very specific)
		$this->special = 0;
		// Name of image file used for this module.
		// If file is in theme/yourtheme/img directory under name object_pictovalue.png, use this->picto='pictovalue'
		// If file is in module/img directory under name object_pictovalue.png, use this->picto='pictovalue@module'
		$this->picto = 'project';

		// Defined all module parts (triggers, login, substitutions, menus, css, etc...)
		// for default path (eg: /doc2project/core/xxxxx) (0=disable, 1=enable)
		// for specific path of parts (eg: /doc2project/core/modules/barcode)
		// for specific css file (eg: /doc2project/css/doc2project.css.php)
		//$this->module_parts = array(
		//                        	'triggers' => 0,                                 	// Set this to 1 if module has its own trigger directory (core/triggers)
		//							'login' => 0,                                    	// Set this to 1 if module has its own login method directory (core/login)
		//							'substitutions' => 0,                            	// Set this to 1 if module has its own substitution function file (core/substitutions)
		//							'menus' => 0,                                    	// Set this to 1 if module has its own menus handler directory (core/menus)
		//							'theme' => 0,                                    	// Set this to 1 if module has its own theme directory (theme)
		//                        	'tpl' => 0,                                      	// Set this to 1 if module overwrite template dir (core/tpl)
		//							'barcode' => 0,                                  	// Set this to 1 if module has its own barcode directory (core/modules/barcode)
		//							'models' => 0,                                   	// Set this to 1 if module has its own models directory (core/modules/xxx)
		//							'css' => array('/doc2project/css/doc2project.css.php'),	// Set this to relative path of css file if module has its own css file
		//							'js' => array('/doc2project/js/doc2project.js'),          // Set this to relative path of js file if module must load a js on all pages
		//							'hooks' => array('hookcontext1','hookcontext2')  	// Set here all hooks context managed by module
		//							'dir' => array('output' => 'othermodulename'),      // To force the default directories names
		//							'workflow' => array('WORKFLOW_MODULE1_YOURACTIONTYPE_MODULE2'=>array('enabled'=>'isModEnabled("module1") && isModEnabled("module2")', 'picto'=>'yourpicto@doc2project')) // Set here all workflow context managed by module
		//                        );
		$this->module_parts = [
			'triggers' => 1,
			'hooks' => ['propalcard', 'ordercard', 'projecttaskcard', 'projectcard', 'usercard', 'invoicecard', 'projecttaskcard']
		];

		// Data directories to create when module is enabled.
		// Example: this->dirs = array("/doc2project/temp");
		$this->dirs = [];

		// Config pages. Put here list of php page, stored into doc2project/admin directory, to use to setup module.
		$this->config_page_url = ["doc2project_setup.php@doc2project"];

		// Dependencies
		$this->hidden = false;            // A condition to hide module
		$this->depends = [];        // List of modules id that must be enabled if this module is enabled
		$this->requiredby = [];    // List of modules id to disable if this one is disabled
		$this->conflictwith = [];    // List of modules id this module is in conflict with
		$this->phpmin = [7, 0];                    // Minimum version of PHP required by module
		$this->need_dolibarr_version = [16, 0];    // Minimum version of Dolibarr required by module
		$this->langfiles = ["doc2project@doc2project"];

		// Constants
		// List of particular constants to add when module is enabled (key, 'chaine', value, desc, visible, 'current' or 'allentities', deleteonunactive)
		// Example: $this->const=array(0=>array('MYMODULE_MYNEWCONST1','chaine','myvalue','This is a constant to add',1),
		//                             1=>array('MYMODULE_MYNEWCONST2','chaine','myvalue','This is another constant to add',0, 'current', 1)
		// );
		$this->const = [
			['DOC2PROJECT_DISPLAY_ON_PROPOSAL', 'chaine', '0', 'Display function on proposal card', 1],
			['DOC2PROJECT_DISPLAY_ON_ORDER', 'chaine', '0', 'Display function on order card', 1],
			['DOC2PROJECT_AUTO_ON_PROPOSAL_CLOSE', 'chaine', '0', 'Launch function when proposal is closed signed', 1],
			['DOC2PROJECT_AUTO_ON_ORDER_VALIDATE', 'chaine', '0', 'Launch function when order is validated', 1],
			['DOC2PROJECT_NB_HOURS_PER_DAY', 'chaine', '7', 'Used to convert service duration in hours', 1],
			['DOC2PROJECT_TASK_REF_PREFIX', 'chaine', 'TA', 'Prefix for task reference, will be used with proposal or order line ID to be unique', 1]
		];

		// Array to add new pages in new tabs
		// Example: $this->tabs = array('objecttype:+tabname1:Title1:mylangfile@doc2project:$user->rights->doc2project->read:/doc2project/mynewtab1.php?id=__ID__',  	// To add a new tab identified by code tabname1
		//                              'objecttype:+tabname2:Title2:mylangfile@doc2project:$user->rights->othermodule->read:/doc2project/mynewtab2.php?id=__ID__',  	// To add another new tab identified by code tabname2
		//                              'objecttype:-tabname:NU:conditiontoremove');                                                     						// To remove an existing tab identified by code tabname
		// where objecttype can be
		// 'categories_x'	  to add a tab in category view (replace 'x' by type of category (0=product, 1=supplier, 2=customer, 3=member)
		// 'contact'          to add a tab in contact view
		// 'contract'         to add a tab in contract view
		// 'group'            to add a tab in group view
		// 'intervention'     to add a tab in intervention view
		// 'invoice'          to add a tab in customer invoice view
		// 'invoice_supplier' to add a tab in supplier invoice view
		// 'member'           to add a tab in fundation member view
		// 'opensurveypoll'	  to add a tab in opensurvey poll view
		// 'order'            to add a tab in customer order view
		// 'order_supplier'   to add a tab in supplier order view
		// 'payment'		  to add a tab in payment view
		// 'payment_supplier' to add a tab in supplier payment view
		// 'product'          to add a tab in product view
		// 'propal'           to add a tab in propal view
		// 'project'          to add a tab in project view
		// 'stock'            to add a tab in stock view
		// 'thirdparty'       to add a tab in third party view
		// 'user'             to add a tab in user view
		$this->tabs = [];

		// Dictionaries
		if (!isModEnabled('doc2project')) {
			$conf->doc2project = new stdClass();
			$conf->doc2project->enabled = 0;
		}
		$this->dictionaries = [];
		/* Example:
		if (! isModEnabled("doc2project")) $conf->doc2project->enabled=0;	// This is to avoid warnings
		$this->dictionaries=array(
			'langs'=>'mylangfile@doc2project',
			'tabname'=>array($this->db->prefix()."table1",$this->db->prefix()."table2",$this->db->prefix()."table3"),		// List of tables we want to see into dictonnary editor
			'tablib'=>array("Table1","Table2","Table3"),													// Label of tables
			'tabsql'=>array('SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.$this->db->prefix().'table1 as f','SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.$this->db->prefix().'table2 as f','SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.$this->db->prefix().'table3 as f'),	// Request to select fields
			'tabsqlsort'=>array("label ASC","label ASC","label ASC"),																					// Sort order
			'tabfield'=>array("code,label","code,label","code,label"),																					// List of fields (result of select to show dictionary)
			'tabfieldvalue'=>array("code,label","code,label","code,label"),																				// List of fields (list of fields to edit a record)
			'tabfieldinsert'=>array("code,label","code,label","code,label"),																			// List of fields (list of fields for insert)
			'tabrowid'=>array("rowid","rowid","rowid"),																									// Name of columns with primary key (try to always name it 'rowid')
			'tabcond'=>array(isModEnabled("doc2project"),isModEnabled("doc2project"),isModEnabled("doc2project"))												// Condition to show each dictionary
		);
		*/

		// Boxes
		// Add here list of php file(s) stored in core/boxes that contains class to show a box.
		$this->boxes = [];            // List of boxes
		// Example:
		//$this->boxes=array(array(0=>array('file'=>'myboxa.php','note'=>'','enabledbydefaulton'=>'Home'),1=>array('file'=>'myboxb.php','note'=>''),2=>array('file'=>'myboxc.php','note'=>'')););

		// Permissions
		$this->rights = [];        // Permission array used by this module
		$r = 0;

		// Add here list of permission defined by an id, a label, a boolean and two constant strings.
		// Example:
		// $this->rights[$r][0] = $this->numero + $r;	// Permission id (must not be already used)
		// $this->rights[$r][1] = 'Permision label';	// Permission label
		// $this->rights[$r][3] = 1; 					// Permission by default for new user (0/1)
		// $this->rights[$r][4] = 'level1';				// In php code, permission will be checked by test if ($user->hasRight("permkey", "level1", "level2"))
		// $this->rights[$r][5] = 'level2';				// In php code, permission will be checked by test if ($user->hasRight("permkey", "level1", "level2"))
		// $r++;
		$this->rights[$r][0] = $this->numero + $r;    // Permission id (must not be already used)
		$this->rights[$r][1] = $langs->trans('Doc2ProjectViewStats');    // Permission label
		$this->rights[$r][3] = 0;                    // Permission by default for new user (0/1)
		$this->rights[$r][4] = 'read';                // In php code, permission will be checked by test if ($user->hasRight("permkey", "level1", "level2"))
		$r++;


		// Main menu entries
		$this->menu = [];            // List of menus to add
		$r = 0;

		// Add here entries to declare new menus
		//
		// Example to declare a new Top Menu entry and its Left menu entry:
		$this->menu[$r] = ['fk_menu' => "fk_mainmenu=project",                            // Put 0 if this is a top menu
			'type' => 'left',                            // This is a Top menu entry
			'titre' => 'Doc2Project',
			'mainmenu' => 'project',
			'leftmenu' => 'doc2project',
			'url' => '/doc2project/rapport.php',
			'langs' => 'mylangfile@doc2project',            // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 166,
			'enabled' => 'isModEnabled("doc2project")',    // Define condition to show or hide menu entry. Use 'isModEnabled("doc2project")' if entry must be visible if module is enabled.
			'perms' => '$user->hasRight(\'doc2project\',\'read\')',                            // Use 'perms'=>'$user->hasRight("doc2project", "level1", "level2")' if you want your menu with a permission rules
			'target' => '',
			'user' => 2];                                // 0=Menu for internal users, 1=external users, 2=both
		$r++;

		$this->menu[$r] = ['fk_menu' => "fk_mainmenu=project,fk_leftmenu=doc2project",                            // Put 0 if this is a top menu
			'type' => 'left',                            // This is a Top menu entry
			'titre' => 'statistiques',
			'mainmenu' => '',
			'leftmenu' => 'statistiques',
			'url' => '/doc2project/rapport.php',
			'langs' => 'mylangfile@doc2project',            // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 167,
			'enabled' => 'isModEnabled("doc2project")',    // Define condition to show or hide menu entry. Use 'isModEnabled("doc2project")' if entry must be visible if module is enabled.
			'perms' => '$user->hasRight(\'doc2project\',\'read\')',                            // Use 'perms'=>'$user->hasRight("doc2project", "level1", "level2")' if you want your menu with a permission rules
			'target' => '',
			'user' => 2];                                // 0=Menu for internal users, 1=external users, 2=both
		$r++;
		//
		// Example to declare a Left Menu entry into an existing Top menu entry:
		// $this->menu[$r]=array(	'fk_menu'=>'fk_mainmenu=xxx',		    // Use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
		//							'type'=>'left',			                // This is a Left menu entry
		//							'titre'=>'Doc2Project left menu',
		//							'mainmenu'=>'xxx',
		//							'leftmenu'=>'doc2project',
		//							'url'=>'/doc2project/pagelevel2.php',
		//							'langs'=>'mylangfile@doc2project',	        // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
		//							'position'=>100,
		//							'enabled'=>'$conf->doc2project->enabled',  // Define condition to show or hide menu entry. Use '$conf->doc2project->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
		//							'perms'=>'1',			                // Use 'perms'=>'$user->hasRight("doc2project", "level1", "level2")' if you want your menu with a permission rules
		//							'target'=>'',
		//							'user'=>2);				                // 0=Menu for internal users, 1=external users, 2=both
		// $r++;


		// Exports
		$r = 1;

		// Example:
		// $this->export_code[$r]=$this->rights_class.'_'.$r;
		// $this->export_label[$r]='CustomersInvoicesAndInvoiceLines';	// Translation key (used only if key ExportDataset_xxx_z not found)
		// $this->export_enabled[$r]='1';                               // Condition to show export in list (ie: '$user->id==3'). Set to 1 to always show when module is enabled.
		// $this->export_permission[$r]=array(array("facture","facture","export"));
		// $this->export_fields_array[$r]=array('s.rowid'=>"IdCompany",'s.nom'=>'CompanyName','s.address'=>'Address','s.zip'=>'Zip','s.town'=>'Town','s.fk_pays'=>'Country','s.phone'=>'Phone','s.siren'=>'ProfId1','s.siret'=>'ProfId2','s.ape'=>'ProfId3','s.idprof4'=>'ProfId4','s.code_compta'=>'CustomerAccountancyCode','s.code_compta_fournisseur'=>'SupplierAccountancyCode','f.rowid'=>"InvoiceId",'f.facnumber'=>"InvoiceRef",'f.datec'=>"InvoiceDateCreation",'f.datef'=>"DateInvoice",'f.total'=>"TotalHT",'f.total_ttc'=>"TotalTTC",'f.tva'=>"TotalVAT",'f.paye'=>"InvoicePaid",'f.fk_statut'=>'InvoiceStatus','f.note'=>"InvoiceNote",'fd.rowid'=>'LineId','fd.description'=>"LineDescription",'fd.price'=>"LineUnitPrice",'fd.tva_tx'=>"LineVATRate",'fd.qty'=>"LineQty",'fd.total_ht'=>"LineTotalHT",'fd.total_tva'=>"LineTotalTVA",'fd.total_ttc'=>"LineTotalTTC",'fd.date_start'=>"DateStart",'fd.date_end'=>"DateEnd",'fd.fk_product'=>'ProductId','p.ref'=>'ProductRef');
		// $this->export_entities_array[$r]=array('s.rowid'=>"company",'s.nom'=>'company','s.address'=>'company','s.zip'=>'company','s.town'=>'company','s.fk_pays'=>'company','s.phone'=>'company','s.siren'=>'company','s.siret'=>'company','s.ape'=>'company','s.idprof4'=>'company','s.code_compta'=>'company','s.code_compta_fournisseur'=>'company','f.rowid'=>"invoice",'f.facnumber'=>"invoice",'f.datec'=>"invoice",'f.datef'=>"invoice",'f.total'=>"invoice",'f.total_ttc'=>"invoice",'f.tva'=>"invoice",'f.paye'=>"invoice",'f.fk_statut'=>'invoice','f.note'=>"invoice",'fd.rowid'=>'invoice_line','fd.description'=>"invoice_line",'fd.price'=>"invoice_line",'fd.total_ht'=>"invoice_line",'fd.total_tva'=>"invoice_line",'fd.total_ttc'=>"invoice_line",'fd.tva_tx'=>"invoice_line",'fd.qty'=>"invoice_line",'fd.date_start'=>"invoice_line",'fd.date_end'=>"invoice_line",'fd.fk_product'=>'product','p.ref'=>'product');
		// $this->export_sql_start[$r]='SELECT DISTINCT ';
		// $this->export_sql_end[$r]  =' FROM ('.$this->db->prefix().'facture as f, '.$this->db->prefix().'facturedet as fd, '.$this->db->prefix().'societe as s)';
		// $this->export_sql_end[$r] .=' LEFT JOIN '.$this->db->prefix().'product as p on (fd.fk_product = p.rowid)';
		// $this->export_sql_end[$r] .=' WHERE f.fk_soc = s.rowid AND f.rowid = fd.fk_facture';
		// $this->export_sql_order[$r] .=' ORDER BY s.nom';
		// $r++;
	}

	/**
	 *        Function called when module is enabled.
	 *        The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
	 *        It also creates data directories
	 *
	 * @param string $options Options when enabling module ('', 'noboxes')
	 * @return     int                1 if OK, 0 if KO
	 */
	function init($options = '')
	{
		global $langs;

		$sql = [];

		$result = $this->_load_tables('/doc2project/sql/');

		$langs->load('doc2project@doc2prtoject');

		dol_include_once('/core/class/extrafields.class.php');
		$extrafields = new ExtraFields($this->db);

		$res = $extrafields->addExtraField('soldprice', $langs->trans('SoldPrice'), 'double', 0, '24,4', 'projet_task');

		$extrafields = new ExtraFields($this->db);
		$param = ['options' => [1 => "Commercial", 2 => "Developpement", 3 => "Direction de projet", 4 => "Comptabilité"]];
		$res = $extrafields->addExtraField('categorie', 'Catégorie', 'select', 0, 0, 'projet', 0, '', '', $param);

		// Extra fields for Task
		$extrafields->addExtraField('fk_product', 'Product', 'link', 50, '', 'projet_task', 0, 0, '', ['options' => ['Product:product/class/product.class.php' => null]], 1, '', 5, 0, '', '', 'doc2project@doc2project', "isModEnabled('doc2project')");

		//*********************************
		// ******* MISE A JOUR BDD ********
		//*********************************

		if ($this->needUpdate('3.2.1')) {
			/** Mise à jour de la structure de la table llx_projet_task_extrafields **/
			$sqlUpdate = 'ALTER TABLE ' . $this->db->prefix() . 'projet_task_extrafields MODIFY COLUMN soldprice DOUBLE (24,4)';
			$this->db->query($sqlUpdate);

			/** Mise à jour de la colonne size de la table llx_extrafields **/
			$sqlUpdate = "UPDATE " . $this->db->prefix() . "extrafields SET size = '24,4' WHERE name = 'soldprice' AND elementtype = 'projet_task'";
			$this->db->query($sqlUpdate);
		}

		if ($this->needUpdate('3.8.0')) {
			if (!Doc2ProjectTools::addProductIdOnTasks()) {
				setEventMessage($langs->trans('DOC2PROJECT_FK_PRODUCT_ERROR_SQL', $this->db->lasterror()), 'errors');
				$this->error = $this->db->lasterror();
				$this->errors[] = $this->db->lasterror();
				return -1;
			} else {
				setEventMessage('DOC2PROJECT_FK_PRODUCT_ADDED_SUCCESSFULLY', 'mesgs');
			}
		}

		//*************************************
		// ******* FIN MISE A JOUR BDD ********
		//*************************************

		// Stock le numéro de version installé
		dolibarr_set_const($this->db, 'DOC2PROJECT_MOD_LAST_RELOAD_VERSION', $this->version, 'chaine', 0, '', 0);

		return $this->_init($sql, $options);
	}

	/**
	 *        Function called when module is disabled.
	 *      Remove from database constants, boxes and permissions from Dolibarr database.
	 *        Data directories are not deleted
	 *
	 * @param string $options Options when enabling module ('', 'noboxes')
	 * @return     int                1 if OK, 0 if KO
	 */
	function remove($options = '')
	{
		$sql = [];

		return $this->_remove($sql, $options);
	}

	/**
	 * Compare
	 *
	 * @param string $targetVersion numéro de version pour lequel il faut faire la comparaison
	 * @return bool
	 */
	public function needUpdate(string $targetVersion): bool
	{
		if (empty(getDolGlobalString('DOC2PROJECT_MOD_LAST_RELOAD_VERSION'))) {
			return true;
		}

		if (versioncompare(explode('.', $targetVersion), explode('.', getDolGlobalString('DOC2PROJECT_MOD_LAST_RELOAD_VERSION'))) > 0) {
			return true;
		}

		return false;
	}

}
