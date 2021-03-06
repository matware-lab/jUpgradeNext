<?php
/**
 * jUpgradeNext
 *
 * @version $Id:
 * @package jUpgradeNext
 * @copyright Copyright (C) 2004 - 2018 Matware. All rights reserved.
 * @author Matias Aguirre
 * @email maguirre@matware.com.ar
 * @link http://www.matware.com.ar/
 * @license GNU General Public License version 2 or later; see LICENSE
 */

namespace Jupgradenext\Schemas\v36;

use Joomla\Registry\Registry;

use Jupgradenext\Upgrade\UpgradeHelper;
use Jupgradenext\Upgrade\Upgrade;

/**
 * Upgrade class for modules
 *
 * This class takes the modules from the existing site and inserts them into the new site.
 *
 * @since	1.0
 */
class Modules extends Upgrade
{
	/**
	 * Set the conditions hook
	 *
	 * @return	void
	 * @since	1.0
	 * @throws	Exception
	 */
	public static function getConditionsHook($container)
	{
		$conditions = array();

		$conditions['select'] = "`id`, `title`, `content`, `ordering`, `position`,"
			." `checked_out`, `checked_out_time`, `published`, `module`,"
			." `access`, `showtitle`, `params`, `client_id`";

		$conditions['where'][] = "client_id = 0";
		$conditions['where'][] = "module IN ('mod_breadcrumbs', 'mod_footer', 'mod_mainmenu', 'mod_menu', 'mod_related_items', 'mod_stats', 'mod_wrapper', 'mod_archive', 'mod_custom', 'mod_latestnews', 'mod_mostread', 'mod_search', 'mod_syndicate', 'mod_banners', 'mod_feed', 'mod_login', 'mod_newsflash', 'mod_random_image', 'mod_whosonline' )";

		return $conditions;
	}

	/**
	 * Method to do pre-processes modifications before migrate
	 *
	 * @return	boolean	Returns true if all is fine, false if not.
	 * @since	1.0
	 * @throws	Exception
	 */
	public function beforeHook()
	{
		$query = $this->_db->getQuery(true);
		$query->select('id');
		$query->from($this->_db->quoteName("#__modules"));
		$query->order('id DESC');
		$query->setLimit(1);
		$this->_db->setQuery($query);

		try {
			$modules_id = $this->_db->loadResult();
		} catch (Exception $e) {
			throw new Exception($e->getMessage());
		}

		if ($modules_id > 86)
		{
			// Update the modules step
			$this->updateStep('modules');

			// Update the modules_menu step
			$this->updateStep('modules_menu');
		}

		// Cleanup the modules for 'site' unused modules
		$query->clear();
		$query->delete()->from('#__modules')->where('client_id = 0');

		try {
			$this->_db->setQuery($query)->execute();
		} catch (Exception $e) {
			throw new Exception($e->getMessage());
		}
	}

	/**
	 * Update the status of one step
	 *
	 * @param		string  $name  The name of the table to update
	 *
	 * @return	none
	 *
	 * @since	1.0
	 */
	public function updateStep ($name)
	{
		// Get the JQuery object
		$query = $this->_db->getQuery(true);
		$query->clear();

		$query->update('#__jupgradepro_steps')->set('status = 2')->where('name = \''.$name.'\'');
		try {
			$this->_db->setQuery($query)->execute();
		} catch (Exception $e) {
			throw new Exception($e->getMessage());
		}
	}

	/**
	 * Sets the data in the destination database.
	 *
	 * @return	void
	 * @since	1.0
	 * @throws	Exception
	 */
	public function dataHook($rows = null)
	{
		// Getting the source table
		$table = $this->getSourceTable();

		$total = count($rows);

		//
		foreach ($rows as &$row)
		{
			// Fix incorrect dates
			$names = array('checked_out_time');
			$row = $this->fixIncorrectDate((array)$row, $names);

			if (empty($row->language))
			{
				$row['language'] = "*";
			}
		}

		return $rows;
	}
}
