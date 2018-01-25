<?php
/**
 * jUpgradeNext
 *
 * @version $Id:
 * @package jUpgradeNext
 * @copyright Copyright (C) 2004 - 2016 Matware. All rights reserved.
 * @author Matias Aguirre
 * @email maguirre@matware.com.ar
 * @link http://www.matware.com.ar/
 * @license GNU General Public License version 2 or later; see LICENSE
 */

namespace Jupgradenext\Models;

use Joomla\Model\AbstractModel;

use Jupgradenext\Drivers\Drivers;
use Jupgradenext\Steps\Steps;
use Jupgradenext\Upgrade\Upgrade;
use Jupgradenext\Upgrade\UpgradeHelper;
use Jupgradenext\Models\Sites;

/**
 * JUpgradeproModelChecks Model
 *
 * @package		jUpgradePro
 */
class Checks extends ModelBase
{
	/**
	 * Initial checks in jUpgradePro
	 *
	 * @return	none
	 * @since	1.2.0
	 */
	function checks()
	{
		// Get the parameters with global settings
		$options = $this->container->get('sites')->getSite();
		$optionsDb = $this->container->get('sites')->getSiteDboConfig();

		// Get the origin site Joomla! version
		$origin_version = $this->container->get('origin_version');

		// Define tables array
		$old_columns = array();

		// Check for bad configurations
		if ($options['method'] == "restful") {

			if (empty($options['restful.hostname']) || empty($options['restful.username']) || empty($options['restful.password']) || empty($options['restful.key']) ) {
				throw new \RuntimeException('COM_JUPGRADEPRO_ERROR_REST_CONFIG');
			}

			if ($options['restful.hostname']== 'http://www.example.org/' || $options['restful.hostname']== '' ||
					$options['restful.username']== '' || $options['restful.password']== '' || $options['restful.key']== '') {
				throw new \RuntimeException('COM_JUPGRADEPRO_ERROR_REST_CONFIG');
			}

			// Initialize the driver to check the RESTful connection
			$driver = Drivers::getInstance($this->container);

			// Check if Restful and plugin are fine
			$code = $driver->requestRest('check');

			switch ($code) {
				case 401:
					throw new \RuntimeException('COM_JUPGRADEPRO_ERROR_REST_501');
				case 402:
					throw new \RuntimeException('COM_JUPGRADEPRO_ERROR_REST_502');
				case 403:
					throw new \RuntimeException('COM_JUPGRADEPRO_ERROR_REST_503');
				case 405:
					throw new \RuntimeException('COM_JUPGRADEPRO_ERROR_REST_505');
				case 406:
					throw new \RuntimeException('COM_JUPGRADEPRO_ERROR_REST_506');
			}

			// Get the database parameters
			$this->old_tables = json_decode($driver->requestRest('tableslist'));
			$this->old_prefix = substr($old_tables[10], 0, strpos($old_tables[10], '_')+1);

			// Get component version
			$ext_version = $this->container->get('version');

			// Compare the versions
			if (trim($code) != $ext_version)
			{
				throw new \RuntimeException('COM_JUPGRADEPRO_ERROR_VERSION_NOT_MATCH');
			}

		}

		// Check for bad configurations
		if ($options['method'] == "database")
		{
			if ($optionsDb['db_hostname']== '' || $optionsDb['db_username']== ''
			  || $optionsDb['db_name']== '' || $optionsDb['db_prefix']== '' )
			{
				throw new \RuntimeException('COM_JUPGRADEPRO_ERROR_DATABASE_CONFIG');
			}

			// Get the database parameters
			$this->old_tables = $this->container->get('external')->getTableList();
			$this->old_prefix = $optionsDb['db_prefix'];
		}


		// Check the external site Joomla! version
		$external_version = $this->checkSite();

		// Check if the version is fine
		if (empty($external_version) || empty($origin_version)) {
			throw new \RuntimeException('COM_JUPGRADEPRO_ERROR_NO_VERSION');
		}

		// Save the versions to database
		$this->setVersion('old', $external_version);
		$this->setVersion('new', $origin_version);

		// Checking tables
		$tables = $this->container->get('db')->getTableList();

		// Check if the tables exists if not populate install.sql
		$tablesComp = array();
		$tablesComp[] = 'categories';
		$tablesComp[] = 'default_categories';
		$tablesComp[] = 'default_menus';
		$tablesComp[] = 'errors';
		$tablesComp[] = 'extensions';
		$tablesComp[] = 'extensions_tables';
		$tablesComp[] = 'files_images';
		$tablesComp[] = 'files_media';
		$tablesComp[] = 'files_templates';
		$tablesComp[] = 'menus';
		$tablesComp[] = 'modules';
		$tablesComp[] = 'steps';

		foreach ($tablesComp as $table) {
			if (!in_array($this->container->get('db')->getPrefix() . 'jupgradepro_' . $table, $tables)) {
				if (UpgradeHelper::isCli()) {
					print("\n\033[1;37m-------------------------------------------------------------------------------------------------\n");
					print("\033[1;37m|  \033[0;34m	Installing jUpgradePro tables\n");
				}

				UpgradeHelper::populateDatabase($this->container->get('db'), JPATH_COMPONENT_ADMINISTRATOR.'/sql/install.sql');
				break;
			}
		}

		// Define the message array
		$message = array();
		$message['status'] = "ERROR";

		// Check if all jupgrade tables are there
		$query = $this->container->get('db')->getQuery(true);
		$query->select('COUNT(id)');
		$query->from("`#__jupgradepro_steps`");
		$this->container->get('db')->setQuery($query);
		$nine = $this->container->get('db')->loadResult();

		if ($nine < 10) {
			throw new \RuntimeException('COM_JUPGRADEPRO_ERROR_TABLE_STEPS_NOT_VALID');
		}

		// Check safe_mode_gid
		if (@ini_get('safe_mode_gid') && @ini_get('safe_mode')) {
			throw new \RuntimeException('COM_JUPGRADEPRO_ERROR_DISABLE_SAFE_GID');
		}

		// Convert the params to array
		$core_skips = json_decode($options['skips']);
		$flag = false;

		// Check is all skips is set
		foreach ($core_skips as $k => $v) {
			$core = substr($k, 0, 9);
			$name = substr($k, 10, 18);

			if ($core == "skip_core") {
				if ($v == 0) {
					$flag = true;
				}
			}

			if ($core == "skip_exte") {
				if ($v == 0) {
					$flag = true;
				}
			}
		}

		if ($flag === false) {
			throw new \RuntimeException('COM_JUPGRADEPRO_ERROR_SKIPS_ALL');
		}

		// Checking tables
		if ($core_skips->skip_core_contents != 1 && $options['keep_ids']== 1) {
			$query->clear();
			$query->select('COUNT(id)');
			$query->from("`#__content`");
			$this->container->get('db')->setQuery($query);
			$content_count = $this->container->get('db')->loadResult();

			if ($content_count > 0) {
				throw new \RuntimeException('COM_JUPGRADEPRO_ERROR_DATABASE_CONTENT');
			}
		}

		// Checking tables
		if ($core_skips->skip_core_users != 1) {
			$query->clear();
			$query->select('COUNT(id)');
			$query->from("`#__users`");
			$this->container->get('db')->setQuery($query);
			$users_count = $this->container->get('db')->loadResult();

			if ($users_count > 1) {
				throw new \RuntimeException('COM_JUPGRADEPRO_ERROR_DATABASE_USERS');
			}
		}

		// Checking tables
		if ($core_skips->skip_core_categories != 1 && $options['keep_ids'] == 1) {
			$query->clear();
			$query->select('COUNT(id)');
			$query->from("`#__categories`");
			$this->container->get('db')->setQuery($query);
			$categories_count = $this->container->get('db')->loadResult();

			if ($categories_count > 7) {
				throw new \RuntimeException('COM_JUPGRADEPRO_ERROR_DATABASE_CATEGORIES');
			}
		}

		// Done checks
		if (!UpgradeHelper::isCli())
			$this->returnError (200, "[[g;white;]|] [[g;orange;]✓] Checks done.");
	}

	/**
	 * Set old site Joomla! version
	 *
	 * @return	none
	 * @since	3.2.0
	 */
	public function setVersion ($site, $version)
	{
		// Set the ols site version
		$query = $this->container->get('db')->getQuery(true);
		$query->update('#__jupgradepro_version')->set("{$site} = '{$version}'");
		$this->container->get('db')->setQuery($query)->execute();
	}

	/**
	 * Check if one column exists
	 *
	 * @return	none
	 * @since	3.8.0
	 */
	public function checkColumn ($table, $column)
	{
		if (!in_array($table, $this->old_tables))
		{
			return false;
		}

		if ($this->external)
		{
			$columns = $this->external->getTableColumns($table);

			return array_key_exists($column, $columns) ? true : false;

		}else {
			return false;
		}
	}

	/**
	 * Check the Joomla! version from tables
	 *
	 * @return	version	The Joomla! version
	 * @since	3.2.0
	 */
	public function checkOldVersion ($external = null)
	{
		// Set default
		$version = false;

		// Trim the prefix value
		$this->prefix = trim($this->old_prefix);

		// Set the tables to search
		$j10 = "{$this->old_prefix}bannerfinish";
		$j15 = "{$this->old_prefix}core_acl_aro";
		$j25 = "{$this->old_prefix}update_categories";
		$j30 = "{$this->old_prefix}assets";
		$j31 = "{$this->old_prefix}content_types";
		$j32 = $j33 = "{$this->old_prefix}postinstall_messages";
		$j34 = "{$this->old_prefix}redirect_links";
		$j35 = "{$this->old_prefix}utf8_conversion";
		$j36 = "{$this->old_prefix}menu_types";
		$j37 = "{$this->old_prefix}fields";
		$j38 = "{$this->old_prefix}fields_groups";

		// Check the correct version
		if (in_array($j10, $this->old_tables))
		{
			$version = "1.0";
		}
		else if(in_array($j15, $this->old_tables))
		{
			$version = "1.5";
		}
		else if(in_array($j30, $this->old_tables) && !in_array($j25, $this->old_tables) && !in_array($j31, $this->old_tables))
		{
			$version = "3.0";
		}
		else if(in_array($j31, $this->old_tables) && !in_array($j32, $this->old_tables))
		{
			$version = "3.1";
		}
		else if($this->checkColumn($j33, 'requireReset', $external))
		{
			$version = "3.3";
		}
		else if($this->checkColumn($j34, 'header', $external))
		{
			$version = "3.4";
		}
		else if(in_array($j35, $this->old_tables))
		{
			$version = "3.5";
		}
		else if(in_array($j32, $this->old_tables))
		{
			$version = "3.2";
		}
		else if($this->checkColumn($j36, 'asset_id', $external))
		{
			$version = "3.6";
		}
		else if(in_array($j37, $this->old_tables))
		{
			$version = "3.7";
		}
		else if($this->checkColumn($j38, 'params', $external))
		{
			$version = "3.8";
		}
		else if(in_array($j25, $this->old_tables) || in_array($j30, $this->old_tables))
		{
			$version = "2.5";
		}

		return $version;
	}

	/**
	 * checkSite
	 *
	 * @return	bool True if sun is shining
	 * @since	  3.8
	 */
	public function checkSite ()
	{
		// Get external driver
		$this->external = $this->container->get('external');

		// Get the database parameters
		$this->old_tables = $this->external->getTableList();
		$this->old_prefix = $this->external->getPrefix();

		$version = $this->checkOldVersion($this->external);

		if ($version != false)
		{
			return $version;
		}else{
			return false;
		}

	}

} // end class
