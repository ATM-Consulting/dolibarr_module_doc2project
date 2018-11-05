<?php

function reportPrepareHead($type='report') {
	global $user,$langs;
	
	$langs->load('report@report');
	
	switch ($type) {
		case 'report':
				$tab = array(
							array( dol_buildpath('/report/report.php',1), $langs->trans('Reports'),'report')
						);
				if(defined('ALTERNATE_HOME')){
					$tab = array_merge(
								$tab,
								array(array(dol_buildpath('/report/report_'.ALTERNATE_HOME.'.php',1), $langs->trans(ALTERNATE_HOME),ALTERNATE_HOME))
						  );
				}
				return $tab;
			break;
		case 'ALTERNATE_HOME':
				return array(
					array(dol_buildpath('/report/report.php',1), $langs->trans('Reports'),'report'),
					array(dol_buildpath('/report/report_'.ALTERNATE_HOME.'.php',1), $langs->trans(ALTERNATE_HOME),ALTERNATE_HOME)
				);
			break;
	}
}
