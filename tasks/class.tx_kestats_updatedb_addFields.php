<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2013 Jonathan Heilmann <mail@jonathan-heilmann.de>
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

//include language
$GLOBALS['LANG']->includeLLFile('EXT:ke_stats/tasks/locallang.xml');


/**
 * Extend scheduler-form for the 'ke_stats' extension.
 *
 * @author	Jonathan Heilmann <mail@jonathan-heilmann.de>
 * @package	TYPO3
 * @subpackage	tx_kestats
 */
class tx_kestats_updatedb_addFields implements tx_scheduler_AdditionalFieldProvider {
	public function getAdditionalFields(array &$taskInfo, $task, tx_scheduler_Module $parentObject) {
			//preset maxExecTime
		if (empty($taskInfo['maxExecTime'])) {
			if($parentObject->CMD == 'edit') {
				$taskInfo['maxExecTime'] = $task->maxExecTime;
			} else {
			   $taskInfo['maxExecTime'] = '';
			}
		}


		$additionalFields = array();
			//write the code for the field "maxExecTime"
		$fieldID = 'maxExecTime';
		$fieldCode = '<input type="input" name="tx_scheduler[maxExecTime]" id="'.$fieldID.'" value="'.$taskInfo['maxExecTime'].'">';
		$additionalFields[$fieldID] = array(
		   'code'     => $fieldCode,
		   'label'    => $GLOBALS['LANG']->getLL('ScFormMaxExecTime')
		);

		return $additionalFields;
	}

	public function validateAdditionalFields(array &$submittedData, tx_scheduler_Module $parentObject) {
		$submittedData['maxExecTime'] = trim($submittedData['maxExecTime']);
		return true;
	}

	public function saveAdditionalFields(array $submittedData, tx_scheduler_Task $task) {
		$task->maxExecTime = $submittedData['maxExecTime'];
	}
}
?>