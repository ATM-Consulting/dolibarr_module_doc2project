<?php 

require('../config.php');
/*if(!empty($conf->global->DOC2PROJECT_CREATE_TASK_WITH_SUBTOTAL)){
    dol_include_once('/subtotal/class/subtotal.class.php');
}*/
dol_include_once('/projet/class/project.class.php');
dol_include_once('/projet/class/task.class.php');
dol_include_once('/doc2project/class/doc2project.class.php');

$langs->load('doc2project@doc2project');

$get = GETPOST('get');

if($get == 'convertToProjectLines' )
{
    $element = GETPOST('element');
    $id = GETPOST('id');
    
    if(!empty($element) && !empty($id) )
    {
        
        $object = false;
        if($element=='propal'){
            dol_include_once('/comm/propal/class/propal.class.php');
            $object = new Propal($db);
        }
        elseif ($element=='commande'){
            dol_include_once('/commande/class/commande.class.php');
            $object = new Commande($db);
        }
        
        if($object->fetch($id) > 0)
        {
            doc2project::showLinesToParse($object);
            
            print '<input type="hidden" name="id" value="'.$id.'" />';
            print '<input type="hidden" name="type" value="'.$element.'" />';
            print '<input type="hidden" name="action" value="create_project" />';
            
        }
        
    }
    
}