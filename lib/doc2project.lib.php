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
 *	\file		lib/doc2project.lib.php
 *	\ingroup	doc2project
 *	\brief		This file is an example module library
 *				Put some comments here
 */

function doc2projectAdminPrepareHead()
{
    global $langs, $conf;

    $langs->load("doc2project@doc2project");

    $h = 0;
    $head = array();

    $head[$h][0] = dol_buildpath("/doc2project/admin/doc2project_setup.php", 1);
    $head[$h][1] = $langs->trans("Parameters");
    $head[$h][2] = 'settings';
    $h++;
    $head[$h][0] = dol_buildpath("/doc2project/admin/doc2project_about.php", 1);
    $head[$h][1] = $langs->trans("About");
    $head[$h][2] = 'about';
    $h++;

    // Show more tabs from modules
    // Entries must be declared in modules descriptor with line
    //$this->tabs = array(
    //	'entity:+tabname:Title:@doc2project:/doc2project/mypage.php?id=__ID__'
    //); // to add new tab
    //$this->tabs = array(
    //	'entity:-tabname:Title:@doc2project:/doc2project/mypage.php?id=__ID__'
    //); // to remove a tab
    complete_head_from_modules($conf, $langs, $object, $head, $h, 'doc2project');

    return $head;
}




function showLinesToParse(&$object)
{
    global $conf,$langs,$db,$user;
    
    $Tlines = array();
    
    // LOAD subtotal class if needed
    if(!empty($conf->global->DOC2PROJECT_CREATE_SPRINT_FROM_TITLE)){
        dol_include_once('/subtotal/class/subtotal.class.php');
    }
    
    
    print '<table id="tablelines" class="noborder" width="100%"><thead><tr class="liste_titre">
                <td class="linecoldescription">Description</td>
                <td class="linecolvat" align="right" width="80">TVA</td>
                <td class="linecoluht" align="right" width="80">P.U. HT</td>
                <td class="linecolqty" align="right">Qté</td>
                <td class="linecoluseunit" align="left">Unité</td>
                <td class="linecolcheckall" align="left"><input type="checkbox" class="linecheckboxtoggle"></td>
                </tr></thead><tbody>';
    
    // CREATION D'UNE TACHE GLOBAL POUR LA SAISIE DES TEMPS
    if (!empty($conf->global->DOC2PROJECT_CREATE_GLOBAL_TASK))
    {
        print '<tr>';
        print '<td colspan="6" ><strong>'.$langs->trans('Doc2ProjectGlobalTaskLabel').'</strong> - '.$langs->trans('Doc2ProjectGlobalTaskDesc').'</td>';
        print '</tr>';
    }
    
    
    $i = 0;
    // CREATION DES TACHES PAR RAPPORT AUX LIGNES DE LA COMMANDE
    foreach($object->lines as $iLine => &$line)
    {
        $i++;
        $Tlines = array();
        $backgroundColor = '';
        $lineType = 'std'; // 'title', 'subtotal'
        
        // Excluded product
        if(Doc2Project::isExclude($line)) continue;
        
        // Dans le cas de sous total
        if ($line->product_type == 9)
        {
            if (method_exists('TSubtotal', 'getTitleLabel')) $title = TSubtotal::getTitleLabel($line);
            else {
                $title = $line->label;
                if (empty($title)) $title = !empty($line->description) ? $line->description : $line->desc;
            }
            
            if ($line->qty >= 1 && $line->qty <= 10) // TITRE
            {
                $backgroundColor = '#eeffee';
                $lineType = 'title';
            }
            else // SOUS-TOTAL
            {
                $backgroundColor = '#ddffdd';
                $lineType = 'subtotal';
            }
        }
        elseif (!empty($conf->global->DOC2PROJECT_USE_NOMENCLATURE_AND_WORKSTATION))
        {
            //Avec les postes de travails liés à la nomenclature
            if(!empty($line->fk_product) || (!empty($conf->global->DOC2PROJECT_ALLOW_FREE_LINE) && $line->fk_product === null) ) {
                define('INC_FROM_DOLIBARR',true);
                $Tcrawl = nomenclatureProductDeepCrawl($line->rowid, $object->element,$line->fk_product,$line->qty);
                if(!empty($Tcrawl))
                {
                    $Tlines = array_merge($Tlines,$Tcrawl);
                }
            }
            
        }
        else if(
            (!empty($line->fk_product) && $line->fk_product_type == 1) // Line type service
            || (!empty($conf->global->DOC2PROJECT_ALLOW_FREE_LINE) && $line->fk_product === null)  // Free line
            )
        {
            
            // => ligne de type service	=> ligne libre
            // On ne créé que les tâches correspondant à des services
            
            
            if(!empty($conf->global->DOC2PROJECT_CREATE_TASK_FOR_VIRTUAL_PRODUCT) && !empty($conf->global->PRODUIT_SOUSPRODUITS) && !is_null($line->ref))
            {
                $s = new Product($db);
                $s->fetch($line->fk_product);
                $s->get_sousproduits_arbo();
                $TProdArbo = $s->get_arbo_each_prod();
                
                if(!empty($TProdArbo)){
                    
                    if(!empty($conf->global->DOC2PROJECT_CREATE_TASK_FOR_PARENT)){
                        if($conf->workstation->enabled && $conf->global->DOC2PROJECT_WITH_WORKSTATION){
                            dol_include_once('/workstation/class/workstation.class.php');
                            
                            $Tids = TRequeteCore::get_id_from_what_you_want($PDOdb, MAIN_DB_PREFIX."workstation_product",array('fk_product'=>$line->fk_product));
                            
                            foreach ($Tids as $workstationProductid) {
                                $Tcrawl = nomenclatureProductDeepCrawl($workstationProductid,'product',$workstationProductid,1);
                                if(!empty($Tcrawl))
                                {
                                    $Tlines = array_merge($Tlines,$Tcrawl);
                                }
                            }
                        }
                    }
                    
                    foreach($TProdArbo as $prod){
                        if($prod['type'] == 1){ //Uniquement les services
                            $Tcrawl = nomenclatureProductDeepCrawl($prod['id'],'product',$prod['id'],$line->qty * $prod['nb']);
                            if(!empty($Tcrawl))
                            {
                                $Tlines = array_merge($Tlines,$Tcrawl);
                            }
                        }
                    }
                }
            }
        }
        
        $backgroundColor = empty($backgroundColor)?'#f8f8f8':$backgroundColor;
        print '<tr style="background: '.$backgroundColor.' !important;" >';
        print '<td class="linecoldescription">';
        
        if(!empty($line->fk_product)){
            $product = new Product($db);
            if($product->fetch($line->fk_product) > 0){
                print $product->getNomUrl(1).' - '.$product->label. ' ';
            }
        }
        print '<strong>'.$line->label.'</strong> ';
        if(!empty($line->desc)){ print $line->desc; }
        print '</td>';
        print '<td class="linecolvat" align="right" width="80">';
        if( $lineType != 'title' && $lineType != 'subtotal'){
            print price($line->tva_tx);
        }
        print '</td>';
        print '<td class="linecoluht" align="right" width="80">';
        if( $lineType != 'title' && $lineType != 'subtotal'){
            print price($line->subprice);
        }
        print '</td>';
        print '<td class="linecolqty" align="right">';
        if( $lineType != 'title' && $lineType != 'subtotal'){
            print $line->qty;
        }
        print '</td>';
        print '<td class="linecoluseunit" align="left"></td>';
        print '<td class="linecolcheckall" align="left">';
        
        if(in_array($lineType, array('std', 'title')) )
        {
            print '<input type="checkbox" class="linecheckbox" name="doc2projectline['.$line->id.']" value="'.$line->id.'" ></td>';
        }
        
        print '</tr>';
        if(!empty($Tlines))
        {
            print '<tr  style="background:#fff  !important;"  ><td colspan="6" >';
            //var_dump($Tlines);
            taskViewToHtml($Tlines);
            print '</td></tr>';
        }
    }
    print '</tbody></table>';
    
    print '<input type="hidden"  />';
    
    if (ini_get('max_input_vars') < ($i*4))
    {
        print 'NEED CHANGE max_input_vars to biggeur value than '.($i*4);
    }
    
    
}



function taskViewToHtml($Tlines)
{
    global $db;
    print '<ul>';
    foreach ($Tlines as $i => $task)
    {
        $style = '';
        if($task['element'] == 'workstation' && empty($task['infos']['object']->nb_hour)){
            $style = 'text-decoration: line-through;';
        }
        
        print '<li style="'.$style.'">';
        
        
        if(!empty($task['fk_product']))
        {
            $product = new Product($db);
            if($product->fetch($task['fk_product']) > 0)
            {
                $task['infos']['label'] = $product->getNomUrl(1) .' '.$product->label.' '.$task['infos']['label'];
            }
        }
        elseif($task['element'] == 'workstation'){
            print '<i class="fa fa-wrench"></i> ';
        }
        
        $devNotes =  '';//$i.' :: '.$task['element'] .' ';
        print '<strong>'.$devNotes. $task['infos']['label'].'</strong>';
        if(!empty($task['infos']['desc'])){ print ' '.$task['infos']['desc']; }
        
        if($task['element'] == 'workstation')
        {
            print ' '.$task['infos']['object']->nb_hour.'H';
        }
        
        
        if(!empty($task['infos']['qty'])){
            print ' x '.($task['infos']['qty']);
        }
        
        if(!empty($task['children'])){
            taskViewToHtml($task['children']);
        }
        print '</li>';
    }
    print '</ul>';
}


function  nomenclatureProductDeepCrawl($fk_element, $element, $fk_product,$qty = 1, $deep = 0, $maxDeep = 0){
    global $db,$conf;
    
    $maxDeepConf = empty($conf->global->NOMENCLATURE_MAX_NESTED_LEVEL) ? 50 : $conf->global->NOMENCLATURE_MAX_NESTED_LEVEL;
    $maxDeep = !empty($maxDeep)?$maxDeep:$maxDeepConf ;
    
    if($deep>$maxDeep){ return array(); }
    
    dol_include_once('/nomenclature/config.php');
    dol_include_once('/nomenclature/class/nomenclature.class.php');
    $nomenclature = new TNomenclature($db);
    $PDOdb = new TPDOdb($db);
    
    
    $nomenclature->loadByObjectId($PDOdb,$fk_element, $element, false, $fk_product, $qty); //get lines of nomenclature
    
    $Tlines= array();
    
    $i=0;
    if(!empty($nomenclature->TNomenclatureDet)){
        $detailsNomenclature=$nomenclature->getDetails($line->qty);
        // PARCOURS DE LA NOMENCLATURE
        foreach ($nomenclature->TNomenclatureDet as &$det)
        {
            $i++;
            
            $Tlines[$i] = array(
                'element' => 'nomenclaturedet',
                'id'      =>  $det->id,
                'fk_product'=>$det->fk_product,
                'infos'   => array(
                    'label' => '',
                    'desc' => '',
                    'qty' => $qty * $det->qty,
                    'object' => $det,
                ),
            );
            
            $childs = nomenclatureProductDeepCrawl($det->fk_product, 'product', $det->fk_product,$qty * $det->qty, $deep+1, $maxDeep);
            
            if(!empty($childs))
            {
                $Tlines[$i]['children'] = $childs;
            }
            
        }
        
        // RECUPERATION DES WORKSTATIONS
        if(!empty($conf->workstation->enabled) && !empty($conf->global->DOC2PROJECT_WITH_WORKSTATION) )
        {
            dol_include_once('/workstation/class/workstation.class.php');
            if(!empty($nomenclature->TNomenclatureWorkstation))
            {
                foreach ($nomenclature->TNomenclatureWorkstation as &$wsn)
                {
                    
                    $i++;
                    $Tlines[$i]= array(
                        'element' => 'workstation',
                        'id'      => $wsn->workstation->rowid,
                        'infos'   => array(
                            'label' => $wsn->workstation->name,
                            'qty' => $qty * $det->qty,
                            'desc' => '',
                            'object' => $wsn,
                        ),
                    );
                    
                }
            }
        }
        
    }
    
    return $Tlines;
}



/**
 * Count the number of working days between two dates.
 *
 * This function calculate the number of working days between two given dates
 *
 * @param   int  $start    Start date timestamp
 * @param   int  $end    Ending date timestamp
 * @return  integer           Number of working days ('zero' on error)
 *
 */
function getWorkdays($start, $end) {
    global $conf;
    
    $defaultWorkingDays = explode('-',(isset($conf->global->MAIN_DEFAULT_WORKING_DAYS)?$conf->global->MAIN_DEFAULT_WORKING_DAYS:'1-5')); // yes, it's true dolibarr don't create a default '1-5' value so on fresh install of dolibarr this conf is empty. ENJOY!
    
    $start = strtotime($date1);
    $end   = strtotime($date2);
    $workdays = 0;
    for ($i = $start; $i <= $end; $i = strtotime("+1 day", $i)) {
        $day = date("w", $i);  // 0=sun, 1=mon, ..., 6=sat
        
        if ($day >= defaultWorkingDays[0]  && $day <= defaultWorkingDays[1]) {
            $workdays++;
        }
    }
    return intval($workdays);
}



function printJSPopinBeforeAddTasksInProject($parameters, &$object, &$action, $hookmanager,$label)
{
    global $conf,$langs;
    
    //$langs->load('doc2project@doc2project');
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
	        
	        $dialog.dialog('open').tooltip({
				show: { collision: "flipfit", effect:'toggle', delay:50 },
				hide: { delay: 50 },
				tooltipClass: "mytooltip",
				content: function () {
	  				return $(this).prop('title');		/* To force to get title as is */
					}
			});
			
		});
	});
	</script>
	<?php 
}

	
