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

namespace Jupgradenext\Schemas\v15;

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
	/*
	 * Fake method after hooks
	 *
	 * @return	void
	 * @since	1.00
	 * @throws	Exception
	 */
	public function afterHook()
	{
	}

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

			if (version_compare(UpgradeHelper::getVersion($this->container, 'origin_version'), '3.5', '>=')) {
				$row['lastResetTime'] = $row['registerDate'];
			}

			$row['params'] = $this->convertParams($row['params']);
		}

		return $rows;
	}

	/**
	 * Sets the data in the destination database.
	 *
	 * @return	void
	 * @since	1.0
	 * @throws	Exception
	 */
	public function dataHook($rows)
	{
		$origin_version = UpgradeHelper::getVersion($this->container, 'origin_version');

		// Do some custom post processing on the list.
		foreach ($rows as &$row)
		{
			$row = (array) $row;

			// Fix incorrect dates
			$names = array('lastvisitDate', 'registerDate');
			$row = $this->fixIncorrectDate($row, $names);

			if (version_compare($origin_version, '1.0', '>=')) {
				unset($row['usertype']);
				if (isset($row['uid'])) {
					unset($row['uid']);
				}
			}

			// Chaging admin username and email
			if ($row['username'] == 'admin') {
				$row['username'] = $row['username'].'-old';
				$row['email'] = $row['email'].'-old';
			}

			// Remove unused fields.
			unset($row['gid']);
		}

		return $rows;
	}

	/**
	 * A hook to be able to modify params prior as they are converted to JSON.
	 *
	 * @param	object	$object	A reference to the parameters as an object.
	 *
	 * @return	void
	 * @since	1.0
	 * @throws	Exception
	 */
	protected function convertParamsHook(&$object)
	{
		$object->timezone = 'UTC';
	}
}
