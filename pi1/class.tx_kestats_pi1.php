<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2007 Christian Bülter <buelter@kennziffer.com>
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

require_once(PATH_tslib.'class.tslib_pibase.php');
require_once(PATH_BE_KESTATS.'inc/browsers.inc.php');
require_once(PATH_BE_KESTATS.'inc/robots.inc.php');
require_once(PATH_BE_KESTATS.'inc/search_engines.inc.php');
require_once(PATH_BE_KESTATS.'inc/operating_systems.inc.php');
require_once(PATH_BE_KESTATS.'inc/constants.inc.php');

/**
 * Plugin 'statistics counter' for the 'ke_stats' extension.
 *
 * @author	Christian Bülter <buelter@kennziffer.com>
 * @package	TYPO3
 * @subpackage	tx_kestats
 */
class tx_kestats_pi1 extends tslib_pibase {
	// Same as class name
	var $prefixId = 'tx_kestats_pi1';		
	// Path to this script relative to the extension dir.
	var $scriptRelPath = 'pi1/class.tx_kestats_pi1.php';	
	// The extension key.
	var $extKey = 'ke_stats';	
	var $tableName = 'tx_kestats_statdata';
	var $statData = array();
	var $timeFields='year,month';
	// keep the tracking entries only a certain number of days, delete them after that
	var $keepTrackingEntriesDays = 60;

	// for debugging purposes:
	var $debug_email = '';
	var $debug_mail_if_unknown = 0;
	var $debug_mail_queries = 0;
	var $debug_queries = array();

	/**
	 * The main method of the PlugIn.
	 * This method is called on every page rendering and collect the stistical data.
	 * Include it into your page header.
	 * Returns an empty string.
	 *
	 * @param	string		$content: The PlugIn content
	 * @param	array		$conf: The PlugIn configuration
	 * @return	
	 */
	function main($content,$conf)	{/*{{{*/
		$this->conf = $conf;
		$this->browsers = $GLOBALS['browsers'];
		$this->robots = $GLOBALS['robots'];
		$this->search_engines = $GLOBALS['search_engines'];
		$this->operating_systems = $GLOBALS['operating_systems'];
		$this->now = time();
		$lcObj=t3lib_div::makeInstance('tslib_cObj');

		// ignore this page?
		if ($this->conf['ignorePages'] && t3lib_div::inList($this->conf['ignorePages'],$GLOBALS['TSFE']->id)) {
			return '';
		}

		// ignore calls without user agent where the remote address is like the server address
		// this is necessary to ignore certain types of calls, for example ajax calls to typo3 pages
		if ( trim(t3lib_div::getIndpEnv('HTTP_USER_AGENT')) == ''
			&& isset($_SERVER['SERVER_ADDR']) 
			&& $_SERVER['SERVER_ADDR'] == t3lib_div::getIndpEnv('REMOTE_ADDR')) {
			return '';
		}

		// get the data
		$this->getData();

		// the data of the counted element (in this case, the current page)
		$element_uid = $GLOBALS['TSFE']->id;
		$element_pid = $GLOBALS['TSFE']->page['pid'];
		$element_type = $GLOBALS['TSFE']->type;

		// get "real" pagetitle (not touched by any extension)
		// $element_title = $GLOBALS['TSFE']->page['title'];
		$element_title = $GLOBALS['TSFE']->rootLine[sizeof($GLOBALS['TSFE']->rootLine)-1]['title'];
		$element_language = t3lib_div::_GP('L') ? intval(t3lib_div::_GP('L')) : 0;

		// get the extension-manager configuration 
		$this->extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['ke_stats']);
		if (!isset($this->extConf['enableIpLogging'])) {
			$this->extConf['enableIpLogging'] = 0;
		}
		if (!isset($this->extConf['enableTracking'])) {
			$this->extConf['enableTracking'] = 0;
		}

		//***********************************************
		// Count PAGE IMPRESSIONS
		// (only from real visitors / humans)
		//***********************************************
		
		// Count this page impression taking into account if it is a human visitor or a robot.
		if ($this->conf['enableStatisticsPages']) {
			if (!$this->statData['is_robot']) {
				$this->increaseCounter(CATEGORY_PAGES,'element_uid,element_pid,element_language,element_type,year,month',$element_title,$element_uid,$element_pid,$element_language,$element_type);

				// Count overall page impressions per time period
				$this->increaseCounter(CATEGORY_PAGES_OVERALL_DAY_OF_MONTH,'element_uid,element_title,year,month',sprintf('%02d',$this->statData['day']),$element_uid);
				$this->increaseCounter(CATEGORY_PAGES_OVERALL_DAY_OF_WEEK,'element_uid,element_title,year,month',$this->statData['day_of_week'],$element_uid);
				$this->increaseCounter(CATEGORY_PAGES_OVERALL_HOUR_OF_DAY,'element_uid,element_title,year,month',sprintf('%02d',$this->statData['hour']),$element_uid);

				// Count this page impression for a logged-in fe-user.
				if (is_array($GLOBALS['TSFE']->fe_user->user)) {
					$this->increaseCounter(CATEGORY_PAGES_FEUSERS,'element_uid,element_pid,element_language,element_type,year,month',$element_title,$element_uid,$element_pid,$element_language,$element_type);
				}
			}
		}

		//***********************************************
		// Count VISITS
		//***********************************************
		
		if ($this->conf['enableStatisticsPages']) {
			if (!$this->statData['is_robot']) {
				// Count this visitor (session), if it not has been counted before.
				$sessionData = $GLOBALS['TSFE']->fe_user->getKey('ses',$this->prefixId);
				if ($sessionData <> 'logged') {
					$this->increaseCounter(CATEGORY_VISITS_OVERALL,'element_uid,year,month','',$element_uid);

					// Count overall visits per time period
					$this->increaseCounter(CATEGORY_VISITS_OVERALL_DAY_OF_MONTH,'element_uid,element_title,year,month',sprintf('%02d',$this->statData['day']),$element_uid);
					$this->increaseCounter(CATEGORY_VISITS_OVERALL_DAY_OF_WEEK,'element_uid,element_title,year,month',$this->statData['day_of_week'],$element_uid);
					$this->increaseCounter(CATEGORY_VISITS_OVERALL_HOUR_OF_DAY,'element_uid,element_title,year,month',sprintf('%02d',$this->statData['hour']),$element_uid);

					$GLOBALS['TSFE']->fe_user->setKey('ses',$this->prefixId,'logged');
				}

				// Count the visit of a logged-in fe-user.
				if (is_array($GLOBALS['TSFE']->fe_user->user)) {
					$sessionData = $GLOBALS['TSFE']->fe_user->getKey('ses',$this->prefixId.'-fe_user');
					if ($sessionData <> 'logged') {
						$this->increaseCounter(CATEGORY_VISITS_OVERALL_FEUSERS,'element_uid,year,month','',$element_uid);

						// Count overall visits of a logged-in user per time period
						$this->increaseCounter(CATEGORY_VISITS_OVERALL_FEUSERS_DAY_OF_MONTH,'element_uid,element_title,year,month',sprintf('%02d',$this->statData['day']),$element_uid);
						$this->increaseCounter(CATEGORY_VISITS_OVERALL_FEUSERS_DAY_OF_WEEK,'element_uid,element_title,year,month',$this->statData['day_of_week'],$element_uid);
						$this->increaseCounter(CATEGORY_VISITS_OVERALL_FEUSERS_HOUR_OF_DAY,'element_uid,element_title,year,month',sprintf('%02d',$this->statData['hour']),$element_uid);

						$GLOBALS['TSFE']->fe_user->setKey('ses',$this->prefixId.'-fe_user','logged');
					}
				}
			}
		}

		//***********************************************
		// TRACK Visitor
		//***********************************************

		if (!$this->statData['is_robot'] && $this->extConf['enableTracking']) {

			// get the uid of the initial entry
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid',$this->tableName,'element_title=\''.$GLOBALS['TSFE']->fe_user->id.'\'');
			$this->debug_queries[] = $GLOBALS['TYPO3_DB']->SELECTquery('uid',$this->tableName,'element_title=\''.$GLOBALS['TSFE']->fe_user->id.'\'');

			if ($GLOBALS['TYPO3_DB']->sql_num_rows($res) == 0) {
				// this is the first hit of this user
				// so create the initial entry
				$this->increaseCounter(CATEGORY_TRACKING_INITIAL,'element_uid,element_title,year,month',$GLOBALS['TSFE']->fe_user->id,$element_uid,0,$element_language,$element_type,STAT_TYPE_TRACKING);

				// get the uid of the initial entry
				// don't use sql_insert_id, because there may have been more insert operations in between
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid',$this->tableName,'element_title=\''.$GLOBALS['TSFE']->fe_user->id.'\'');
				$this->debug_queries[] = $GLOBALS['TYPO3_DB']->SELECTquery('uid',$this->tableName,'element_title=\''.$GLOBALS['TSFE']->fe_user->id.'\'');
				$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
				$parent_uid = $row['uid'];

				// track some more info about the visitor
				// track browser
				$this->increaseCounter(CATEGORY_TRACKING_BROWSER,'element_uid,element_title,year,month',$this->statData['user_agent_name'],$element_uid,0,0,0,STAT_TYPE_TRACKING,$parent_uid);

				// track operating system
				$this->increaseCounter(CATEGORY_TRACKING_OPERATING_SYSTEM,'element_uid,element_title,year,month',$this->statData['operating_system'],$element_uid,0,0,0,STAT_TYPE_TRACKING,$parent_uid);

				// track ip addresse if ip-logging is enabled
				if ($this->extConf['enableIpLogging']) {
					$this->increaseCounter(CATEGORY_TRACKING_IP_ADRESS,'element_uid,element_title,year,month',$this->statData['remote_addr'],$element_uid,0,0,0,STAT_TYPE_TRACKING,$parent_uid);
				}

				// track referer and search string
				if (!empty($this->statData['http_referer'])) {
					if ($this->statData['referer_is_search_engine']) {
						$this->increaseCounter(CATEGORY_TRACKING_REFERER,'element_uid,element_title,year,month',$this->statData['referer_name'],$element_uid,0,0,0,STAT_TYPE_TRACKING,$parent_uid);
						// track search strings
						$this->increaseCounter(CATEGORY_TRACKING_SEARCH_STRING,'element_uid,element_title,year,month',$this->getSearchwordFromReferer($this->statData['http_referer']),$element_uid,0,0,0,STAT_TYPE_TRACKING,$parent_uid);
					} else {
						// track only external sites
						// TODO: make the list of hostnames, that won't be counted extendable via typoscript.
						$refererHost = $this->getHostnameWithoutWWW($this->statData['referer_name']);
						$currentHost = $this->getHostnameWithoutWWW($this->statData['http_host']);
						if ($refererHost <> $currentHost) {
							$this->increaseCounter(CATEGORY_TRACKING_REFERER,'element_uid,element_title,year,month',$this->statData['http_referer'],$element_uid,0,0,0,STAT_TYPE_TRACKING,$parent_uid);
						}
					}
				}
			} else {
				$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
				$parent_uid = $row['uid'];
				// update the time stamp of the initial entry in order to make this visitor appear at the top of the list
				$res = $GLOBALS['TYPO3_DB']->exec_UPDATEquery($this->tableName,'uid = '.$parent_uid,array('tstamp' => $this->now));
				$this->debug_queries[] = $GLOBALS['TYPO3_DB']->UPDATEquery($this->tableName,'uid = '.$parent_uid,array('tstamp' => $this->now));
			}

			// TRACK this visitor (page view)
			$this->increaseCounter(CATEGORY_TRACKING_PAGES,'element_uid,element_pid,element_language,element_type,year,month',$element_title,$element_uid,$element_pid,$element_language,$element_type,STAT_TYPE_TRACKING,$parent_uid);

			// delete older tracking entries
			$where = 'type = \''.STAT_TYPE_TRACKING.'\' AND tstamp < '. ($this->now - $this->keepTrackingEntriesDays * 24 * 60 * 60);
			$res = $GLOBALS['TYPO3_DB']->exec_DELETEquery($this->tableName,$where);
			$this->debug_queries[] = $GLOBALS['TYPO3_DB']->DELETEquery($this->tableName,$where);
			//debug ('deleting tracking entries older than '.strftime('%d.%m.%y %R',($this->now - $this->keepTrackingEntriesDays * 24 * 60 * 60)));
			//debug($GLOBALS['TYPO3_DB']->sql_affected_rows.' were affected by delete-query.');
		}

		//***********************************************
		// Count DETAILS
		//***********************************************

		if ($this->conf['enableStatisticsPages']) {
			if (!$this->statData['is_robot']) {
				// count browsers
				$this->increaseCounter(CATEGORY_BROWSERS,'element_uid,element_title,year,month',$this->statData['user_agent_name'],$element_uid);

				// count operating systems
				$this->increaseCounter(CATEGORY_OPERATING_SYSTEMS,'element_uid,element_title,year,month',$this->statData['operating_system'],$element_uid);

				// count ip addresse if ip-logging is enabled
				if ($this->extConf['enableIpLogging']) {
					$this->increaseCounter(CATEGORY_IP_ADRESSES,'element_uid,element_title,year,month',$this->statData['remote_addr'],$element_uid);
				}

				// count also unknown user agents, for debugging purposes
				if ($this->statData['user_agent_name'] == UNKNOWN_USER_AGENT) {
					$this->increaseCounter(CATEGORY_UNKNOWN_USER_AGENTS,'element_uid,element_title,year,month',$this->statData['http_user_agent'],$element_uid);
				}

				// count referers
				if (!empty($this->statData['http_referer'])) {
					if ($this->statData['referer_is_search_engine']) {
						$this->increaseCounter(CATEGORY_REFERERS_SEARCHENGINES,'element_uid,element_title,year,month',$this->statData['referer_name'],$element_uid);
						// count search strings
						$this->increaseCounter(CATEGORY_SEARCH_STRINGS,'element_uid,element_title,year,month',$this->getSearchwordFromReferer($this->statData['http_referer']),$element_uid);
					} else {
						// count only external sites
						// TODO: make the list of hostnames, that won't be counted extendable via typoscript.
						$refererHost = $this->getHostnameWithoutWWW($this->statData['referer_name']);
						$currentHost = $this->getHostnameWithoutWWW($this->statData['http_host']);
						if ($refererHost <> $currentHost) {
							$this->increaseCounter(CATEGORY_REFERERS_EXTERNAL_WEBSITES,'element_uid,element_title,year,month',$this->statData['http_referer'],$element_uid);
						}
					}
				}
			} else {
				// count robots
				$this->increaseCounter(CATEGORY_ROBOTS,'element_uid,element_title,year,month',$this->statData['user_agent_name'],$element_uid);
			}
		}

		//***********************************************
		// Count EXTENSIONS
		// (only from real visitors / humans)
		//***********************************************

		/*
		// example:
		registerExtension.tt_news = News
		registerExtension.tt_news.table = tt_news
		registerExtension.tt_news.titleField = title
		registerExtension.tt_news.uidField = uid
		registerExtension.tt_news.pidField = pid
		registerExtension.tt_news.uidParameter = tt_news
		registerExtension.tt_news.uidParameterWrap = tx_ttnews
		 */

		if (!$this->statData['is_robot'] && $this->conf['enableStatisticsExtensions']) {
			// get the extension configurations
			$extConfList = array();
			if (is_array($this->conf['registerExtension.'])) {
				foreach ($this->conf['registerExtension.'] as $key => $value) {
					if (!is_array($value)) {
						$extConfList[$key] = array();
						$extConfList[$key]['name'] = $value;
						foreach ($this->conf['registerExtension.'][$key.'.'] as $confKey => $confValue) {
							$extConfList[$key][$confKey] = $confValue;
						}
					}
				}

				// do the counting for each extension
				foreach ($extConfList as $extKey => $extConf) {
					// get the element uid
					if (!empty($extConf['uidParameterWrap'])) {
						$extPiVars = t3lib_div::_GET($extConf['uidParameterWrap']);
						$element_uid = $extPiVars[$extConf['uidParameter']];
					} else {
						$element_uid = t3lib_div::_GET($extConf['uidParameter']);
					}

					// count this element if a single view uid is given
					if (!empty($element_uid)) {
						// is there a TCA entry for this table?
						if (is_array($GLOBALS['TCA'][$extConf['table']])) {
							// the data of the counted element (in this case, the extension record)
							$where = 'uid='.$element_uid;
							$where .= $lcObj->enableFields($extConf['table']);
							$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*',$extConf['table'],$where);
							$this->debug_queries[] = $GLOBALS['TYPO3_DB']->SELECTquery('*',$extConf['table'],$where);
							if ($GLOBALS['TYPO3_DB']->sql_num_rows($res) > 0) {
								$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
								$element_pid = $row[$extConf['pidField']]; 
								$element_title = $row[$extConf['titleField']]; 
								$element_type = $GLOBALS['TSFE']->type;
								$element_language = t3lib_div::_GP('L') ? intval(t3lib_div::_GP('L')) : 0;
								$category = $extKey;
								$this->increaseCounter($category,'element_uid,element_pid,element_language,element_type,year,month',$element_title,$element_uid,$element_pid,$element_language,$element_type,STAT_TYPE_EXTENSION);
							}
						}
					}
				}
			}
		}

		if ($this->debug_mail_queries) {
			$this->debugMail($this->debug_queries);
		}
		
		return '';
	}/*}}}*/

	/**
	 * returns a hostname without 'www.' in the beginning
	 * 
	 * @param string $hostname 
	 * @return string
	 */
	function getHostnameWithoutWWW($hostname) {/*{{{*/
		if (substr($hostname,0,strlen('www.')) == 'www.') {
			$hostname = substr($hostname,strlen('www.'));
		}
		return $hostname;
	}/*}}}*/

	/**
	 * Increases a statistics counter for the given $category.
	 * If no counter exists that matches all fields the $compareFieldList, a new one is created.
	 * $compareFieldList is a comma-separated list.
	 * 
	 * @param string $category 
	 * @param string $compareFieldList 
	 * @param int $element_uid 
	 * @param int $element_pid 
	 * @param string $element_title 
	 * @param int $element_language 
	 * @return void
	 */
	function increaseCounter($category,$compareFieldList,$element_title='',$element_uid=0,$element_pid=0,$element_language=0,$element_type=0,$stat_type=STAT_TYPE_PAGES,$parent_uid=0) {/*{{{*/
		$statEntry = $this->getStatEntry($category,$compareFieldList,$element_uid,$element_pid,$element_title,$element_language,$element_type,$stat_type,$parent_uid);
		// create a new entry if the data is unique, or this entry referers to another (user tracking)
		if (count($statEntry) == 0 || $parent_uid > 0) {
			// generate new counter
			$insertFields = array();
			$insertFields['type'] = $stat_type;
			$insertFields['category'] = $category;
			$insertFields['element_uid'] = $element_uid;
			$insertFields['element_pid'] = $element_pid;
			$insertFields['element_title'] = $element_title;
			$insertFields['element_language'] = $element_language;
			$insertFields['element_type'] = $element_type;
			$insertFields['parent_uid'] = $parent_uid;
			$insertFields['tstamp'] = $this->now;
			$insertFields['crdate'] = $this->now;
			$insertFields['counter'] = 1;
			// Set only the time fields which are necessary for this category (those which are in the $compareFieldList)
			foreach (explode(',',$this->timeFields ) as $field) {
				if (in_array($field,explode(',',$compareFieldList))) {
					$insertFields[$field] = $this->statData[$field];
				} else {
					$insertFields[$field] = -1;
				}

			}
			$res = $GLOBALS['TYPO3_DB']->exec_INSERTquery($this->tableName,$insertFields);
			$this->debug_queries[] = $GLOBALS['TYPO3_DB']->INSERTquery($this->tableName,$insertFields);
			unset($insertFields);
		} else {
			// increase existing counter
			$updateFields = array();
			$updateFields['counter'] = $statEntry['counter'] + 1;
			$updateFields['tstamp'] = $this->now;
			$where_clause = 'uid = '.$statEntry['uid'];
			$res = $GLOBALS['TYPO3_DB']->exec_UPDATEquery($this->tableName,$where_clause,$updateFields);
			$this->debug_queries[] = $GLOBALS['TYPO3_DB']->UPDATEquery($this->tableName,$where_clause,$updateFields);
			unset($updateFields);
		}
	}/*}}}*/

	/**
	 * Returns the UID of an enty in the data table matching the $compareFieldList (comma-separated list). 
	 * If there is no matching Entry, it returns -1.
	 * 
	 * @param mixed $category 
	 * @param mixed $compareFieldList 
	 * @param int $element_uid 
	 * @param int $element_pid 
	 * @param string $element_title 
	 * @param int $element_language 
	 * @param int $element_type 
	 * @return void
	 */
	function getStatEntry($category,$compareFieldList,$element_uid=0,$element_pid=0,$element_title='',$element_language=0,$element_type=0,$stat_type=STAT_TYPE_PAGES) {/*{{{*/
		$statEntry = array();
		$compareData = $this->statData;
		$compareData['element_uid'] = $element_uid;
		$compareData['element_pid'] = $element_pid;
		$compareData['element_title'] = $element_title;
		$compareData['element_language'] = $element_language;
		$compareData['element_type'] = $element_type;

		$where_clause = ' type=\''.$stat_type.'\'';
		$where_clause .= ' AND category=\''.$category.'\'';
		foreach (explode(',',$compareFieldList) as $field) {
			// is the field a string field, or an integer?
			if (in_array($field,array('element_title','type'))) {
				// string field
				$where_clause .= ' AND '.$field.'=\''.$compareData[$field].'\'';
			} else {
				// integer field
				$where_clause .= ' AND '.$field.'='.$compareData[$field];
			}

		}
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid,counter',$this->tableName,$where_clause);
		$this->debug_queries[] = $GLOBALS['TYPO3_DB']->SELECTquery('uid,counter',$this->tableName,$where_clause);

		// any results?
		if ($GLOBALS['TYPO3_DB']->sql_num_rows($res)) {
			$statEntry = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
		}

		return $statEntry;
	}/*}}}*/

	/**
	 * sanitizeData 
	 *
	 * sanitizeData
	 * 
	 * @param string $data 
	 * @access public
	 * @return string
	 */
	function sanitizeData($data='') {/*{{{*/
		return htmlspecialchars($data, ENT_QUOTES);
	}/*}}}*/

	/**
	 * Collect all the needed statistical data for the different categories 
	 * and sanitize the input!
	 * 
	 * @return 0
	 */
	function getData() {/*{{{*/
		// get the environment data
		$this->statData['http_host'] = $this->sanitizeData(t3lib_div::getIndpEnv('HTTP_HOST'));
		$this->statData['http_referer'] = $this->sanitizeData(t3lib_div::getIndpEnv('HTTP_REFERER'));
		$this->statData['http_user_agent'] = $this->sanitizeData(trim(t3lib_div::getIndpEnv('HTTP_USER_AGENT')));
		$this->statData['remote_addr'] = $this->sanitizeData(t3lib_div::getIndpEnv('REMOTE_ADDR'));
		$this->statData['request_uri'] = $this->sanitizeData(t3lib_div::getIndpEnv('REQUEST_URI'));

		// collect the time information
		$this->statData['year'] = date('Y',$this->now);
		$this->statData['month'] = date('n',$this->now);
		$this->statData['day'] = date('j',$this->now);
		$this->statData['day_of_week'] = date('w',$this->now);
		$this->statData['hour'] = date('G',$this->now);

		// check, if the visitor is a robot
		// and get the short name of the robot or of the browser
		$this->statData['is_robot'] = 0;
		$this->statData['user_agent_name'] = UNKNOWN_USER_AGENT;
		if (empty($this->statData['http_user_agent'])) {
			$this->statData['http_user_agent'] = EMPTY_USER_AGENT;
			// treat empty user agents as robots
			$this->statData['is_robot'] = 1;
		} else {
			foreach ($this->robots as $robotKey => $robotName) {
				if (strstr($this->statData['http_user_agent'],$robotKey)) {
					$this->statData['is_robot'] = 1;
					if ($this->statData['user_agent_name'] == UNKNOWN_USER_AGENT) $this->statData['user_agent_name'] = $robotName;
				}
			}
			if (!$this->statData['is_robot']) {
				foreach ($this->browsers as $browserKey => $browserName) {
					if (strstr($this->statData['http_user_agent'],$browserKey) && ($this->statData['user_agent_name'] == UNKNOWN_USER_AGENT)) $this->statData['user_agent_name'] = $browserName;
				}
			}
		}

		// get the referer data
		$refererName = $statline['http_referer'];
		if ($this->statData['http_user_agent'] == "") {
			$this->statData['referer_name'] = 'direct';
		} else {
			$urlParts = parse_url($this->statData['http_referer']);
			$this->statData['referer_name'] = $urlParts['host'];
		}
		$maxStrLen = $this->conf['maxRefererNameLength'] ? intval($this->conf['maxRefererNameLength']) : 50;
		if (strlen($this->statData['referer_name']) > $maxStrLen) $this->statData['referer_name'] = substr($this->statData['referer_name'], 0, $maxStrLen).'...';

		// is the referer a search engine or a normal website?
		$this->statData['referer_is_search_engine'] = 0;
		foreach ($this->search_engines as $search_engine_key => $search_engine_name) {
			if (strstr($this->statData['referer_name'],$search_engine_key)) {
				$this->statData['referer_is_search_engine'] = 1;
			}
		}

		// get the operating system
		$this->statData['operating_system'] = UNKNOWN_OPERATING_SYSTEM;
		foreach ($this->operating_systems as $operating_system_key => $operating_system_name) {
			if (strstr($this->statData['http_user_agent'],$operating_system_key) && ($this->statData['operating_system'] == UNKNOWN_OPERATING_SYSTEM)) {
				$this->statData['operating_system'] = $operating_system_name;
			}
		}

		// DEBUG
		// mail debugging information
		// send mail if operating system is unknown
		if (!$this->statData['is_robot']) {

			if ($this->statData['operating_system'] == UNKNOWN_OPERATING_SYSTEM && $this->statData['http_user_agent']!=EMPTY_USER_AGENT && $this->debug_mail_if_unknown) {
				$this->debugMail($this->statData,'[ke_stats] Unknown Operating System');
			}
			// send mail if user agent is unknown
			if ($this->debug_mail_if_unknown && $this->statData['user_agent_name'] == UNKNOWN_USER_AGENT && $this->statData['http_user_agent']!=EMPTY_USER_AGENT) {
				$this->debugMail($this->statData,'[ke_stats] Unknown User Agent');
			}
		}

		return 0;
		}/*}}}*/


	/**
	 * Extracts a search string from a given referer.
	 * 
	 * @param string $uri 
	 * @param string $charset 
	 * @return string
	 */
	function getSearchwordFromReferer($uri,$charset="UTF-8") {/*{{{*/
		// Google & MSN: "q="
		// Yahoo: "p="
		$searchWordParamBegin = array('q=','p=');
		$qs = parse_url($uri);
		$query_str = urldecode(trim($qs["query"]));

		// restore ampersands (for google) &amp; --> &
		$query_str = str_replace('&amp;', '&', $query_str);

		$query_arr = explode("&", $query_str);
		$anz = count($query_arr);
		for($i=0;$i<$anz;$i++) {
			$paramBegin = substr($query_arr[$i],0,2);
			if (in_array($paramBegin,$searchWordParamBegin)) {
				$keys = substr($query_arr[$i],2,strlen($query_arr[$i]));
			}
			$lng = strpos($query_arr[$i],"ie=");
			if($lng) {
				$charset = strtoupper(substr($query_arr[$i],3,strlen($query_arr[$i])));
			}
		}
		if($charset == "UTF-8") {
			$keywords = utf8_decode($keys);
		}
		$trans = array (':' => "", '"' => "", "'" => "", "<" => "", ">" => "", " -" => "", "(" => "", ")" => "", "~" => "", "*" => "");
		return strtr($keywords, $trans);
	}/*}}}*/

	/**
	 * debugMail 
	 *
	 * Sends a html mail with debug information
	 * 
	 * @param string $content 
	 * @param string $subject 
	 * @access public
	 * @return void
	 */
	function debugMail($content='',$subject = 'TYPO3 Debug Mail') {/*{{{*/
		if (is_array($content)) {
			$content = t3lib_div::view_array($content);
		}

		$header = "MIME-Version: 1.0\n";
		$header .= "Content-type: text/html; charset=utf-8\n";
		$header .= "From: ke_stats DEBUG\n"; 

		mail($this->debug_email,$subject,$content,$header);
	}/*}}}*/
}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ke_stats/pi1/class.tx_kestats_pi1.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ke_stats/pi1/class.tx_kestats_pi1.php']);
}

?>
