<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

if (!defined ('KESTATS_EXTKEY')) {
	define('KESTATS_EXTKEY',$_EXTKEY);
}
if (!defined ('PATH_BE_KESTATS')) {
	define('PATH_BE_KESTATS', t3lib_extMgm::extPath(KESTATS_EXTKEY));
}

// add user TSconfig
t3lib_extMgm::addUserTSConfig('<INCLUDE_TYPOSCRIPT: source="FILE:EXT:ke_stats/userTSconfig.txt">');

t3lib_extMgm::addPItoST43($_EXTKEY,'pi1/class.tx_kestats_pi1.php','_pi1','',0);

$GLOBALS['TYPO3_CONF_VARS']['FE']['eID_include']['tx_kestats_filecounter'] = 'EXT:ke_stats/filecounter/index.php';
?>
