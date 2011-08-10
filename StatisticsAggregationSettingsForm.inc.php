<?php

/**
 * @file StatisticsAggregationSettingsForm.inc.php
 *
 * Copyright (c) 2003-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class StatisticsAggregationSettingsForm
 * @ingroup plugins_generic_statisticsAggregation
 *
 * @brief Form for journal managers to modify Statistics Aggregation plugin settings
 */

// $Id$


import('lib.pkp.classes.form.Form');

class StatisticsAggregationSettingsForm extends Form {

	/** @var $conferenceId int */
	var $conferenceId;

	/** @var $plugin object */
	var $plugin;

	/**
	 * Constructor
	 * @param $plugin object
	 * @param $conferenceId int
	 */
	function StatisticsAggregationSettingsForm(&$plugin, $conferenceId) {
		$this->conferenceId = $conferenceId;
		$this->plugin =& $plugin;

		parent::Form($plugin->getTemplatePath() . 'settingsForm.tpl');

		$this->addCheck(new FormValidator($this, 'statisticsAggregationSiteId', 'required', 'plugins.generic.statisticsAggregation.manager.settings.statisticsAggregationSiteIdRequired'));
		$this->addCheck(new FormValidatorEmail($this, 'statisticsAggregationSiteEmail', 'required', 'plugins.generic.statisticsAggregation.manager.settings.statisticsAggregationSiteEmailRequired'));
		$this->addCheck(new FormValidatorEmail($this, 'statisticsAggregationSiteEmailConfirm', 'required', 'plugins.generic.statisticsAggregation.manager.settings.statisticsAggregationSiteEmailConfirmRequired'));
		$this->addCheck(new FormValidatorInSet($this, 'statisticsAggregationSiteEmailConfirm', 'required', 'plugins.generic.statisticsAggregation.manager.settings.emailsMustMatch', array(Request::getUserVar('statisticsAggregationSiteEmail'))));
	}

	/**
	 * Initialize form data.
	 */
	function initData() {
		$conferenceId = $this->conferenceId;
		$plugin =& $this->plugin;

		$this->_data = array(
			'statisticsAggregationSiteId' => $plugin->getSetting($conferenceId, 0, 'statisticsAggregationSiteId'),
			'statisticsAggregationSiteEmail' => $plugin->getSetting($conferenceId, 0, 'statisticsAggregationSiteEmail'),
			'statisticsAggregationSiteEmailConfirm' => $plugin->getSetting($conferenceId, 0, 'statisticsAggregationSiteEmailConfirm'),
			'disableForm' => ($plugin->getSetting($this->journalId, 'statisticsAggregationSiteId') != '') ? true : false
		);
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		$this->readUserVars(array('statisticsAggregationSiteId', 'statisticsAggregationSiteEmail', 'statisticsAggregationSiteEmailConfirm'));
	}

	/**
	 * Save settings.
	 */
	function execute() {
		$plugin =& $this->plugin;
		$conferenceId = $this->conferenceId;

		$plugin->updateSetting($conferenceId, 0, 'statisticsAggregationSiteId', trim($this->getData('statisticsAggregationSiteId'), "\"\';"), 'string');
		$plugin->updateSetting($conferenceId, 0, 'statisticsAggregationSiteEmail', trim($this->getData('statisticsAggregationSiteEmail'), "\"\';"), 'string');
	}
}

?>
