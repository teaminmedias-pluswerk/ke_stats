<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

if (!defined ('KESTATS_EXTKEY')) {
	define('KESTATS_EXTKEY',$_EXTKEY);
}
if (!defined ('PATH_BE_KESTATS')) {
	define('PATH_BE_KESTATS', \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath(KESTATS_EXTKEY));
}

	// add page TSconfig, hide stat table in the backend
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig('<INCLUDE_TYPOSCRIPT: source="FILE:EXT:ke_stats/pageTSconfig.txt">');

	// add plugin
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPItoST43($_EXTKEY,'pi1/class.tx_kestats_pi1.php','_pi1','',0);

	// add eID script for counte file accesses
$GLOBALS['TYPO3_CONF_VARS']['FE']['eID_include']['tx_kestats_filecounter'] = 'EXT:ke_stats/filecounter/index.php';

	// use hook in EXT:scheduler to add cronjob
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks']['tx_kestats_updatedb'] = array(
    'extension'        => $_EXTKEY,
    'title'            => 'Update database table',
    'description'      => 'Task to update the statistics database table',
    'additionalFields' => 'tx_kestats_updatedb_addFields'
);
?>
