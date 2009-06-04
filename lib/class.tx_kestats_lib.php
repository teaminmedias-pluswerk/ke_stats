<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009 Christian Bülter <buelter@kennziffer.com>
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

/**
 * Shared library 'ke_stats' extension.
 *
 * @author	Christian Bülter <buelter@kennziffer.com>
 * @package	TYPO3
 * @subpackage	tx_kestats
 */
class tx_kestats_lib {
	var $statData = array();
	var $tableName = 'tx_kestats_statdata';
	var $timeFields = 'year,month';

	/**
	 * tx_kestats_lib 
	 *
	 * Constructor
	 * 
	 * @access public
	 * @return void
	 */
	function tx_kestats_lib() {/*{{{*/
		$this->now = time();
		$this->extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['ke_stats']);
		$this->extConf['asynchronousDataRefreshing'] = $this->extConf['asynchronousDataRefreshing'] ? 1 : 0;
	}/*}}}*/

	/**
	 * Increases a statistics counter for the given $category.
	 * $compareFieldList is a comma-separated list.
	 *
	 * Takes into account if asynchronous data refreshing is activated and
	 * stores the data either in a queue table or updates it directly.
	 * 
	 * @param string $category 
	 * @param string $compareFieldList 
	 * @param string $element_title 
	 * @param int $element_uid 
	 * @param int $element_pid 
	 * @param int $element_language 
	 * @param int $element_type 
	 * @param string $stat_type 
	 * @param int $parent_uid 
	 * @access public
	 * @return void
	 */
	function increaseCounter($category, $compareFieldList, $element_title='', $element_uid=0, $element_pid=0, $element_language=0, $element_type=0, $stat_type=STAT_TYPE_PAGES, $parent_uid=0) {/*{{{*/

		// if asynchronous data refreshing is activated, store the the data
		// which should be counted at this point into a queue table. If not,
		// process the data (update the counter).
		if (!$this->extConf['asynchronousDataRefreshing']) {

			$this->updateStatisticsTable(
				$category,
				$compareFieldList,
				$element_title,
				$element_uid,
				$element_pid,
				$element_language,
				$element_type,
				$stat_type,
				$parent_uid);

		} else {

			$dataArray = array(
					'category' => $category,
					'compareFieldList' => $compareFieldList,
					'element_title' => $element_title,
					'element_uid' => $element_uid,
					'element_pid' => $element_pid,
					'element_language' => $element_language,
					'element_type' => $element_type,
					'stat_type' => $stat_type,
					'parent_uid' => $parent_uid
					);

			$res = $GLOBALS['TYPO3_DB']->exec_INSERTquery('tx_kestats_queue',array('tstamp' => $this->now, 'data' => serialize($dataArray), 'generaldata' => serialize($this->statData)));

		}
	}/*}}}*/

	/**
	 * Increases a statistics counter. 
	 * If no counter exists that matches all fields the $compareFieldList, a new one is created.
	 * 
	 * @param string $data 
	 * @access public
	 * @return void
	 */
	function updateStatisticsTable($category,$compareFieldList,$element_title='',$element_uid=0,$element_pid=0,$element_language=0,$element_type=0,$stat_type=STAT_TYPE_PAGES,$parent_uid=0) {/*{{{*/
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
			unset($insertFields);
		} else {
			// increase existing counter
			$updateFields = array();
			$updateFields['counter'] = $statEntry['counter'] + 1;
			$updateFields['tstamp'] = $this->now;
			$where_clause = 'uid = '.$statEntry['uid'];
			$res = $GLOBALS['TYPO3_DB']->exec_UPDATEquery($this->tableName,$where_clause,$updateFields);
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

		// any results?
		if ($GLOBALS['TYPO3_DB']->sql_num_rows($res)) {
			$statEntry = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
		}

		return $statEntry;
	}/*}}}*/

	/**
	 * getOldestQueueEntry 
	 * find and return the oldest entry in the queue table
	 * 
	 * @access public
	 * @return array or false
	 */
	function getOldestQueueEntry() {/*{{{*/
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_kestats_queue', '1=1', '', 'tstamp ASC', '1');
		if ($GLOBALS['TYPO3_DB']->sql_num_rows($res) > 0) {
			$result = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
		} else {
			$result = false;
		}
		return $result;
	}/*}}}*/

}
?>
