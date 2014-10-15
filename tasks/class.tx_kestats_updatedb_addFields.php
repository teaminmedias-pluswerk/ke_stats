<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2013-2014 Jonathan Heilmann <mail@jonathan-heilmann.de>
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
class tx_kestats_updatedb_addFields implements \TYPO3\CMS\Scheduler\AdditionalFieldProviderInterface {

	/**
	 * Gets additional fields to render in the form to add/edit a task
	 *
	 * @param array $taskInfo Values of the fields from the add/edit task form
	 * @param \TYPO3\CMS\Scheduler\Task\AbstractTask $task The task object being edited. Null when adding a task!
	 * @param \TYPO3\CMS\Scheduler\Controller\SchedulerModuleController $schedulerModule Reference to the scheduler backend module
	 * @return array
	 */
	public function getAdditionalFields(array &$taskInfo, $task, \TYPO3\CMS\Scheduler\Controller\SchedulerModuleController $schedulerModule) {
			//preset maxExecTime
		if (empty($taskInfo['maxExecTime'])) {
			if($schedulerModule->CMD == 'edit') {
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

	/**
	 * Validates the additional fields' values
	 *
	 * @param array $submittedData An array containing the data submitted by the add/edit task form
	 * @aram \TYPO3\CMS\Scheduler\Controller\SchedulerModuleController $schedulerModule Reference to the scheduler backend module
	 * @return void
	 */
	public function validateAdditionalFields(array &$submittedData, \TYPO3\CMS\Scheduler\Controller\SchedulerModuleController $schedulerModule) {
		$submittedData['maxExecTime'] = trim($submittedData['maxExecTime']);
		return true;
	}

	/**
	 * Takes care of saving the additional fields' values in the task's object
	 *
	 * @param array $submittedData An array containing the data submitted by the add/edit task form
	 * @param \TYPO3\CMS\Scheduler\Task\AbstractTask $task Reference to the scheduler backend module
	 * @return void
	 */
	public function saveAdditionalFields(array $submittedData, \TYPO3\CMS\Scheduler\Task\AbstractTask $task) {
		$task->maxExecTime = $submittedData['maxExecTime'];
	}
}
?>