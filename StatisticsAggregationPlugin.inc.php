<?php

/**
 * @file StatisticsAggregationPlugin.inc.php
 *
 * Copyright (c) 2003-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class StatisticsAggregationPlugin
 * @ingroup plugins_generic_statisticsAggregation
 *
 * @brief Statistics Aggregation for Synergies/SUSHI plugin class
 */

// $Id$


import('classes.plugins.GenericPlugin');

class StatisticsAggregationPlugin extends GenericPlugin {
	/**
	 * Called as a plugin is registered to the registry
	 * @param $category String Name of category plugin was registered to
	 * @return boolean True iff plugin initialized successfully; if false,
	 * 	the plugin will not be registered.
	 */
	function register($category, $path) {
		$success = parent::register($category, $path);
		if (!Config::getVar('general', 'installed')) return false;
		$this->addLocaleData();
		if ($success) {
			HookRegistry::register('TemplateManager::display', array(&$this, 'callbackInsertSA'));
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Get the name of this plugin. The name must be unique within
	 * its category, and should be suitable for part of a filename
	 * (ie short, no spaces, and no dependencies on cases being unique).
	 * @return String name of plugin
	 */
	function getName() {
		return 'StatisticsAggregationPlugin';
	}

	function getDisplayName() {
		return Locale::translate('plugins.generic.statisticsAggregation.displayName');
	}

	function getDescription() {
		return Locale::translate('plugins.generic.statisticsAggregation.description');
	}

	/**
	 * Set the page's breadcrumbs, given the plugin's tree of items
	 * to append.
	 * @param $subclass boolean
	 */
	function setBreadcrumbs($isSubclass = false) {
		$templateMgr =& TemplateManager::getManager();
		$pageCrumbs = array(
			array(
				Request::url(null, null, 'user'),
				'navigation.user'
			),
			array(
				Request::url(null, null, 'manager'),
				'user.role.manager'
			)
		);
		if ($isSubclass) $pageCrumbs[] = array(
			Request::url(null, null, 'manager', 'plugins'),
			'manager.plugins'
		);

		$templateMgr->assign('pageHierarchy', $pageCrumbs);
	}

	/**
	 * Display verbs for the management interface.
	 */
	function getManagementVerbs() {
		$verbs = array();
		$verbs[] = array('readme', Locale::translate('plugins.generic.statisticsAggregation.manager.readme'));
		if ($this->getEnabled()) {
			$verbs[] = array('disable', Locale::translate('manager.plugins.disable'));
			$verbs[] = array('settings', Locale::translate('plugins.generic.statisticsAggregation.manager.settings'));
		} else {
			$verbs[] = array('enable', Locale::translate('manager.plugins.enable'));
		}
		return $verbs;
	}

	/**
	 * Determine whether or not this plugin is enabled.
	 */
	function getEnabled() {
		$conference =& Request::getConference();
		if (!$conference) return false;
		return $this->getSetting($conference->getId(), 0, 'enabled');
	}

	/**
	 * Set the enabled/disabled state of this plugin
	 */
	function setEnabled($enabled) {
		$conference =& Request::getConference();
		if ($conference) {
			$this->updateSetting($conference->getId(), 0, 'enabled', $enabled ? true : false);
			return true;
		}
		return false;
	}

	/**
	 * build the statistics
	 */
	function callbackInsertSA($hookName, $params) {

		if ($this->getEnabled()) {


			$templateMgr =& $params[0];
			$template =& $params[1];

			$conference =& Request::getConference();

			if (!empty($conference) && ! Request::isBot()) {

				$statisticsAggregationSiteId = $this->getSetting($conference->getId(), 0, 'statisticsAggregationSiteId');

				switch ($template) {
					case 'paper/paper.tpl':
					case 'paper/interstitial.tpl':
					case 'paper/pdfInterstitial.tpl':
					// Log the request as an paper view.

					$galley = $templateMgr->get_template_vars('galley');
                                        $paper = $templateMgr->get_template_vars('paper');
                                        // we do some tests and checks to see if we finally end up with a Paper object
					if ($paper == null) {
						if ($galley != null) {
							$paperDao =& DAORegistry::getDAO('PaperDAO');
							$paper =& $paperDao->getPaper($galley->getPaperId());
						}
					}

					$statsArray = $this->buildStatsArray($galley, $paper);
					$this->sendData($statsArray, $statisticsAggregationSiteId);

					break;
					default:
						$statsArray = $this->buildStatsArray(null, null); // regular page view, no galley or article
						if ($statsArray['rp'] != 'manager' && $template != 'rt/rt.tpl') { // do not accumulate stats for journal management pages or research tool bar
							$this->sendData($statsArray, $statisticsAggregationSiteId);
						}
					break;
				}
			}
		}
		return false;
	}

	/**
	 * @brief encodes the statsArray into JSON and sends it up to the aggregation server through a Socket object.
	 * @param Array $statsArray the array containing information about the page requested.
	 * @param String $statisticsAggregationSiteId the Hash Code for this Conference.
	 */
	function sendData($statsArray, $statisticsAggregationSiteId) {
		$this->import('JSONEncoder');
		$encoder = new JSONEncoder();
		$encoder->setAdditionalAttributes($statsArray);
		$jsonString = $encoder->getString();

		$this->import('StatisticsSocket');
		$statisticsSocket = new StatisticsSocket();
		$statisticsSocket->setJSONString($jsonString);
		$statisticsSocket->setSiteId($statisticsAggregationSiteId);
	}

	/**
	 * @brief examines the context of the current request and assembles an array containing the relevant bits of info we want to collect
	 * @param Object $galley the galley object (HTML or PDF, usually) for this page view, null if a regular non-article page.
	 * @param Article $paper the article object representing the current article being viewed, null if a regular non-article page.
	 * @return Array $statsArray the array of our information.
	 */
	function buildStatsArray($galley, $paper) {

		$statsArray = array();

		if ($galley) {
			if ($galley->isPdfGalley()) {
				$statsArray['mt'] = 'PDF';
			} else if ($galley->isHTMLGalley()) { // this seems to always return false in PaperGalley.inc.php
				$statsArray['mt'] = 'HTML';
			}
		} else if ($paper) {
			$statsArray['mt'] = 'ABSTRACT';
		} else {
			$statsArray['mt'] = '';
		}

		$statsArray['ip'] =& Request::getRemoteAddr();
		$statsArray['rp'] =& Request::getRequestedPage();
		$statsArray['ua'] = $_SERVER["HTTP_USER_AGENT"];
		$statsArray['ts'] = date('d/M/Y:H:i:s O', time());
		if ($paper) {
			$statsArray['title'] = $paper->getLocalizedTitle();
			$statsArray['authors'] = $paper->getAuthorString();
		} else {
			$statsArray['title'] = '';
		}
		$statsArray['pr'] =& Request::getProtocol();
		$statsArray['host'] =& Request::getServerHost();

		if (isset($_SERVER['HTTP_REFERER']) && $this->isRemoteReferer($statsArray['pr'] . '://' . $statsArray['host'], $_SERVER['HTTP_REFERER'])) {
			$statsArray['ref'] = $_SERVER['HTTP_REFERER'];
		} else {
			$statsArray['ref'] = '';
		}
		$statsArray['uri'] =& Request::getRequestPath();
		return $statsArray;
	}

	/**
	 * @brief determines if a referring document is coming from an off-site location.
	 * @param $docHost the base host of this request (e.g., http://your.journals.site).
	 * @param $referer the full referring document, if there was one.
	 * @return boolean true if the referring document has a different base domain.
	 */
	function isRemoteReferer($docHost, $referer) {
		if (!preg_match("{^" . quotemeta($docHost) . "}", $referer)) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Extend the {url ...} smarty to support this plugin.
	 */
	function smartyPluginUrl($params, &$smarty) {
		$path = array($this->getCategory(), $this->getName());
		if (is_array($params['path'])) {
			$params['path'] = array_merge($path, $params['path']);
		} elseif (!empty($params['path'])) {
			$params['path'] = array_merge($path, array($params['path']));
		} else {
			$params['path'] = $path;
		}

		if (!empty($params['id'])) {
			$params['path'] = array_merge($params['path'], array($params['id']));
			unset($params['id']);
		}
		return $smarty->smartyUrl($params, $smarty);
	}

 	/*
 	 * Execute a management verb on this plugin
 	 * @param $verb string
 	 * @param $args array
	 * @param $message string Location for the plugin to put a result msg
 	 * @return boolean
 	 */
	function manage($verb, $args, &$message) {
		$templateMgr =& TemplateManager::getManager();
		$templateMgr->register_function('plugin_url', array(&$this, 'smartyPluginUrl'));
		$conference =& Request::getConference();

		switch ($verb) {

			case 'enable':
				$this->setEnabled(true);
				$returner = false;
				$message = Locale::translate('plugins.generic.statisticsAggregation.enabled');
				break;
			case 'disable':
				$this->setEnabled(false);
				$returner = false;
				$message = Locale::translate('plugins.generic.statisticsAggregation.disabled');
				break;

			case 'getNewHash':
				$emailAddress = Request::getUserVar('email');
				$conferenceTitle =& $conference->getConferenceTitle();
				$primaryLocale =& $conference->getPrimaryLocale();

				$conferenceTitle = preg_replace("{/}", " ", $conferenceTitle);

				if ($emailAddress != '')  {
					$jsonResult = file_get_contents('http://warhammer.hil.unb.ca/index.php/getNewHash/0/' . urlencode($emailAddress) . '/' . urlencode($conferenceTitle) . '/' . urlencode($primaryLocale));
					echo $jsonResult;
					return true;
				} else {
					return false;
				}
			case 'settings':

				$this->import('StatisticsAggregationSettingsForm');
				$form = new StatisticsAggregationSettingsForm($this, $conference->getId());
				if (Request::getUserVar('save')) {
					$form->readInputData();
					if ($form->validate()) {
						$form->execute();
						Request::redirect(null, null, 'manager', 'plugins');
					} else {
						$this->setBreadCrumbs(true);
						$form->display();
					}
				} else {
					$this->setBreadCrumbs(true);
					$form->initData();
					$form->display();
				}
				return true;
			case 'readme':
				Request::redirectUrl('http://warhammer.hil.unb.ca/readme.html');
				return true;
			default:
				// Unknown management verb
				assert(false);
				return false;
		}
	}
}
?>
