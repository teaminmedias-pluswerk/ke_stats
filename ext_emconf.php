<?php

/***************************************************************
 * Extension Manager/Repository config file for ext "ke_stats".
 *
 * Auto generated 19-08-2014 19:13
 *
 * Manual updates:
 * Only the data in the array - everything else is removed by next
 * writing. "version" and "dependencies" must not be touched!
 ***************************************************************/

$EM_CONF[$_EXTKEY] = array (
	'title' => 'Statistics',
	'description' => 'Statistics for TYPO3: pageviews/visits, live visitor tracking and extension statistics. Supports fe_users, languages and page types. Can count file accesses. Support for tt_news and commerce is built in. Easy to adapt to other extensions. Needs PHP 5.3.',
	'category' => 'module',
	'version' => '1.2.1',
	'state' => 'stable',
	'uploadfolder' => 0,
	'createDirs' => '',
	'clearcacheonload' => 0,
	'author' => 'Christian Buelter (kennziffer.com)',
	'author_email' => 'buelter@kennziffer.com',
	'author_company' => 'www.kennziffer.com GmbH',
	'constraints' =>
	array (
		'depends' =>
		array (
			'php' => '5.3.0-0.0.0',
			'typo3' => '6.2.0-6.2.99',
		),
		'conflicts' =>
		array (
		),
		'suggests' =>
		array (
		),
	),
);

