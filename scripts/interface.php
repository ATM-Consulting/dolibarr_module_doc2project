<?php 

require('../config.php');
/*if(!empty($conf->global->DOC2PROJECT_CREATE_TASK_WITH_SUBTOTAL)){
    dol_include_once('/subtotal/class/subtotal.class.php');
}*/


$get = GETPOST('get');

if($get == 'convertToProjectLines' )
{
    print 'Well it\'s interface';
}