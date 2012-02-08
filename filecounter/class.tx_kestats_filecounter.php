<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2010-2011 Christian Bülter <buelter@kennziffer.com>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

class tx_kestats_filecounter {
	public $messages = array(
		'backend_tabname' => 'Downloads',
		'file_not_found' => 'File not found: '
	);

	/**
	 * __construct
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {
		$this->initTYPO3();
	}

	/**
	 * initTYPO3
	 *
	 * init the TYPO3 Frontend
	 *
	 * @access protected
	 * @return void
	 */
	 function initTYPO3() {

			// *********************
			// Libraries included
			// *********************
		require_once(PATH_tslib.'class.tslib_fe.php');
		require_once(PATH_tslib.'class.tslib_content.php');
		require_once(PATH_t3lib.'class.t3lib_page.php');
		require_once(PATH_t3lib.'class.t3lib_userauth.php');
		require_once(PATH_tslib.'class.tslib_feuserauth.php');
		require_once(PATH_t3lib.'class.t3lib_tstemplate.php');
		require_once(PATH_t3lib.'class.t3lib_cs.php');


			// ***********************************
			// Create $TSFE object (TSFE = TypoScript Front End)
			// Connecting to database
			// ***********************************
			
		$TYPO3_CONF_VARS = array();
		$id = intval(t3lib_div::_GP('id'));
		$type = intval(t3lib_div::_GP('type'));
		$no_cache = intval(t3lib_div::_GP('no_cache'));
		$TSFE = t3lib_div::makeInstance('tslib_fe', $TYPO3_CONF_VARS, $id, $type, $no_cache);

			// initialize the database
		$TSFE->connectToDB();

			// initialize the TCA
		$TSFE->includeTCA();

			// init fe user
		$this->feUserObj = tslib_eidtools::initFeUser(); // Initialize FE user object

			// create "fake TSFE" so that enable fields can use user group settings
		$GLOBALS['TSFE'] = $TSFE;
		$GLOBALS['TSFE']->gr_list = $this->feUserObj->user['usergroup'];

			// init page
		$this->page = t3lib_div::makeInstance('t3lib_pageSelect');

			// extension configuration
		$this->extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['ke_stats']);
	}

	/**
	 *
	 */
	public function countFile() {
		$request = t3lib_div::getIndpEnv('REQUEST_URI');
		if($test == 'url') {
			$file = realpath($_SERVER['DOCUMENT_ROOT'] . urldecode($request));
		} else {
			$file = realpath($_SERVER['DOCUMENT_ROOT'] . $request);
		}

		// get fileinfomations if possible
		if($fileinfo = $this->getFileInfo($file)) {
			
			// Must be set in order to use ke_stats
			$GLOBALS['TSFE']->config['config']['language'] = 0;

			if(t3lib_extMgm::isLoaded('ke_stats')) {
				$keStatsObj = t3lib_div::getUserObj('EXT:ke_stats/pi1/class.tx_kestats_pi1.php:tx_kestats_pi1');
				$keStatsObj->initApi();

					// don't count access from robots
				if(!$keStatsObj->statData['is_robot']) {

					$fields['category']         = $this->messages['backend_tabname'];
					$fields['compareFieldList'] = 'element_uid,element_title,year,month';
					$fields['elementTitle']     = $fileinfo['file'];
					$fields['elementUid']       = 0;
					$fields['elementPid']       = $this->extConf['fileAccessCountOnPage'] ? intval($this->extConf['fileAccessCountOnPage']) : 0;
					$fields['elementLanguage']  = $GLOBALS['TSFE']->sys_page->sys_language_uid;
					$fields['elementType']      = 0;
					$fields['statType']         = 'extension';
					$fields['parentUid']        = 0;
					$fields['additionalData']   = '';
					$fields['counter']          = 1;
					
					// hook for individual modifications of the statistical filedata
					if(is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_stats']['modifyFileDataBeforeQueue'])) {
						foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_stats']['modifyFileDataBeforeQueue'] as $_classRef) {
							$_procObj = &t3lib_div::getUserObj($_classRef);
							$_procObj->modifyFileDataBeforeQueue($fields, $fileinfo, $keStatsObj, $this);
						}
					}

					$keStatsObj->increaseCounter(
						$fields['category'],
						$fields['compareFieldList'],
						$fields['elementTitle'],
						$fields['elementUid'],
						$fields['elementPid'],
						$fields['elementLanguage'],
						$fields['elementType'],
						$fields['statType'],
						$fields['parentUid'],
						$fields['additionalData'],
						$fields['counter']
					);
				}
				unset($keStatsObj);
			}
			
			header('HTTP/1.1 200 OK');
			header('Status: 200 OK');

			// Download Bug IE SSL
			header('Pragma: anytextexeptno-cache', true);

			header('Content-Type: application/' . $fileinfo['fileext']);
			header('Content-Disposition: inline; filename="' . $fileinfo['file'] . '"');

			readfile($file);
		} else {
			header("HTTP/1.0 404 Not Found");
			echo $this->messages['file_not_found'] . $file;
		}
    }

    /**
	 * Try to get all available file informations
	 *
	 * @param string $file
	 * @return array
	 */
	public function getFileInfo($file) {
		// check if file is available
		if(is_file($file)) {
			$fileinfo = t3lib_div::split_fileref($file);
			$fileinfo['file'] = $this->cleanFileName($fileinfo['file']);
			$fileinfo['dirInfo'] = $this->getDirInfo($file);

			return $fileinfo;
		} else {
			return false;
		}
	}

	/**
	 * removes or replaces special entities from filename
	 *
	 * @param string $file
	 * @return string cleaned filename
	 */
	public function cleanFileName($file) {
		$file = strip_tags($file);
		$file = htmlspecialchars($file);

		return $file;
	}

	/**
	 * explodes the dirname into seperate pieces
	 *
	 * @param string $path
	 * @return array seperated dirname parts
	 */
	public function getDirInfo($file) {
		$pathArray = array();
		$pathArray['fullPath'] = $fullPath = t3lib_div::dirname($file) . '/'; // /var/www/projects/myProject/fileadmin/user_upload/
		$pathArray['rootPath'] = $rootPath = str_replace(PATH_site, '', $fullPath); // /fileadmin/user_upload/
		
		// hook for adding some more path modifications
		// this is useful, if you use foldernames as year or language and you want to save this data, too
		if(is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_stats']['modifyPathArray'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ke_stats']['modifyPathArray'] as $_classRef) {
				$_procObj = &t3lib_div::getUserObj($_classRef);
				$_procObj->modifyPathArray($file, $pathArray, $this);
			}
		}
		
		return $pathArray;
	}
}