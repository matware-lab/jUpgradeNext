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

namespace Jupgradenext\Schemas\v16;

use Jupgradenext\Upgrade\UpgradeHelper;
use Jupgradenext\Upgrade\UpgradeUsers;

/**
 * Upgrade class for Users
 *
 * This class takes the users from the existing site and inserts them into the new site.
 *
 * @since	1.0
 */
class Users extends UpgradeUsers
{
	/**
	 * Get the raw data for this part of the upgrade.
	 *
	 * @return	array	Returns a reference to the source data array.
	 * @since	1.0
	 * @throws	Exception
	 */
	public function &databaseHook($rows)
	{
		// Do some custom post processing on the list.
		foreach ($rows as &$row)
		{
			$row = (array) $row;

      // Chaging admin username and email
      if ($row['username'] == 'admin') {
        $row['username'] = $row['username'].'-old';
        $row['email'] = $row['email'].'-old';
      }
		}

		return $rows;
	}

	/**
	 * Get the raw data for this part of the upgrade.
	 *
	 * @return	array	Returns a reference to the source data array.
	 * @since	1.0
	 * @throws	Exception
	 */
	public function dataHook($rows)
	{
		// Do some custom post processing on the list.
		if (is_array($rows))
		{
			foreach ($rows as &$row)
			{
				$row = (array) $row;

				// Remove unused fields.
				unset($row['otpKey']);
				unset($row['otep']);
				unset($row['gid']);
				unset($row['usertype']);
			}
		}

		return $rows;
	}

	/*
	 * Fake method after hooks
	 *
	 * @return	void
	 * @since	1.00
	 * @throws	Exception
	 */
	public function afterHook()
	{
		// Updating the super user id to 10
		$query = $this->_db->getQuery(true);
		$query->update("#__users");
		$query->set("`id` = 2");
		$query->where("id = 2147483647");
		// Execute the query
		try {
			$this->_db->setQuery($query)->execute();
		} catch (Exception $e) {
			throw new Exception($e->getMessage());
		}

		// Updating the user_usergroup_map
		$query->clear();
		$query->update("#__user_usergroup_map");
		$query->set("`user_id` = 2");
		$query->where("`user_id` = 2147483647");
		// Execute the query
		try {
			$this->_db->setQuery($query)->execute();
		} catch (Exception $e) {
			throw new Exception($e->getMessage());
		}
	}
}
