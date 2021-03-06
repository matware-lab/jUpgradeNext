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

use Jupgradenext\Upgrade\Upgrade;

/**
 * Upgrade class for banners clients
 *
 * @package		jUpgradeNext
 *
 * @since		2.5.2
 */
class Banners_clients extends Upgrade
{
	/**
	 * Setting the conditions hook
	 *
	 * @return	void
	 * @since	1.00
	 * @throws	Exception
	 */
	public static function getConditionsHook($container)
	{
		$conditions = array();

		$conditions['select'] = '`cid` AS id, `name`, 1 AS `state`, `contact`, `email`, `extrainfo`, `checked_out`, `checked_out_time`';

		$conditions['where'] = array();

		return $conditions;
	}
}
