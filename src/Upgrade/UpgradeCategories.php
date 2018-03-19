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

namespace Jupgradenext\Upgrade;

use Joomla\CMS\Table\Category;
use Joomla\Event\Dispatcher;

use Jupgradenext\Upgrade\UpgradeHelper;

/**
 * Upgrade class for categories
 *
 * This class takes the categories banners from the existing site and inserts them into the new site.
 *
 * @since	1.2.2
 */
class UpgradeCategories extends Upgrade
{
	/**
	 * @var		string	The name of the section of the categories.
	 * @since	1.2.2
	 */
	public $section = '';

	/**
	 * @var		string	The name of the destination database table.
	 * @since	3.0.0
	 */
	protected $destination = '#__categories';

	/**
	 * @var		string	The key of the table
	 * @since	3.0.0
	 */
	protected $_tbl_key = 'id';

	/**
	 * Method to do pre-processes modifications before migrate
	 *
	 * @return	boolean	Returns true if all is fine, false if not.
	 * @since	3.2.0
	 * @throws	Exception
	 */
	public function beforeHook()
	{
		$query = $this->_db->getQuery(true);

		// Get the parameters with global settings
		$options = $this->container->get('sites')->getSite();

		if ($options['keep_ids'] == 1)
		{
			// Getting the categories
			$query->clear();
			$query->select("`id`, `parent_id`, `path`, `extension`, `title`, `alias`, `note`, `description`, `published`, `checked_out`, `checked_out_time`, `access`, `metadesc`, `metakey`, `metadata`, `params`, `created_user_id`, `modified_user_id`, `modified_time`, `hits`, `language`, `version`");
			$query->from("#__categories");
			$query->where("id > 1");
			$query->order('id ASC');
			$this->_db->setQuery($query);

			try {
				$categories = $this->_db->loadObjectList();
			} catch (RuntimeException $e) {
				throw new RuntimeException($e->getMessage());
			}

			foreach ($categories as $category)
			{
				$id = $category->id;
				unset($category->id);

				$this->_db->insertObject('#__jupgradepro_default_categories', $category);

				if (get_class($this) == "JUpgradeproCategories")
				{
					// Getting the categories table
					$table = JTable::getInstance('Category', 'JTable');
					// Load it before delete. Joomla bug?
					$table->load($id);
					// Delete
					$table->delete($id);
				}
			}
		}
	}

	/**
	 * Sets the data in the destination database.
	 *
	 * @return	void
	 * @since	0.5.6
	 * @throws	Exception
	 */
	public function dataHook($rows = null)
	{
		foreach ($rows as $category)
		{
			unset($category->id);
		}

		// Insert the categories
		foreach ($rows as $category)
		{
			$this->insertCategory($category);
		}

		return false;
	}

	/**
	 * The public entry point for the class.
	 *
	 * @return	void
	 * @since	0.5.6
	 * @throws	Exception
	 */
	public function upgrade($rows = false)
	{
		if (parent::upgrade()) {
			// Rebuild the categories table
			if (version_compare(UpgradeHelper::getVersion($this->container, 'origin_version'), '3.8', '<'))
			{
				$category = \Joomla\Table\Table::getInstance('Category', 'JTable', array('dbo' => $this->_db));
			}else{
				$category = new Category($this->_db);
			}

			if (!$category->rebuild()) {
				throw new RuntimeException($table->getError());
			}
		}
	}

	/**
	 * Inserts a category
	 *
	 * @access  public
	 * @param   row  An array whose properties match table fields
	 * @since	0.4.
	 */
	public function insertCategory($row, $parent = false)
	{
		// Get the parameters with global settings
		$options = $this->container->get('sites')->getSite();

		// Get the content table
		if (version_compare(UpgradeHelper::getVersion($this->container, 'origin_version'), '3.8', '<'))
		{
			//\JTable::addIncludePath(dirname(__FILE__));
			$category = \Joomla\Table\Table::getInstance('Category', 'JTable', array('dbo' => $this->_db));
		}else{
			$category = new Category($this->_db);
		}

		// Disable observers calls
		// @@ Prevent Joomla! 'Application Instantiation Error' when try to call observers
		// @@ See: https://github.com/joomla/joomla-cms/pull/3408
		//if (version_compare(UpgradeHelper::getVersion($this->container, 'origin_version'), '3.0', '>=')) {
			//$category->_observers->doCallObservers(false);
		//}

		// Get section and old id
		$oldlist = new \stdClass();
		$oldlist->section = !empty($row['extension']) ? $row['extension'] : 0;
		$oldlist->old = isset($row['old_id']) ? (int) $row['old_id'] : (int) $row['id'];
		unset($row['old_id']);

		// Setting the default rules
		$rules = array();
		$rules['core.create'] = $rules['core.delete'] = $rules['core.edit'] = $rules['core.edit.state'] = $rules['core.edit.own'] = '';
		$row['rules'] = $rules;

		// Fix language
		$row['language'] = !empty($row['language']) ? $row['language'] : '*';

		// Fix language
		$row['level'] = !empty($row['level']) ? $row['level'] : 1;

		// Check if path is correct
		$row['path'] = empty($row['path']) ? $row['alias'] : $row['path'];

		// Get alias from title if its empty
		if ($row['alias'] == "") {
			$row['alias'] = JFilterOutput::stringURLSafe($row['title']);
		}

		// Check if has duplicated aliases
		$alias = $this->getAlias('#__categories', $row['alias']);

		// Prevent MySQL duplicate error
		// @@ Duplicate entry for key 'idx_client_id_parent_id_alias_language'
		$row['alias'] = (!empty($alias)) ? $alias."-".rand(0, 999999) : $row['alias'];

		// Remove the default id if keep ids parameters is not enabled
		if ($options['keep_ids'] != 1)
		{
			// Unset id
			unset($row['id']);

			// Save the parent if old installation is 2.5 or greater
			if (version_compare(UpgradeHelper::getVersion($this->container, 'external_version'), '1.5', '>') && $row['parent_id'] != 1)
			{
				$oldlist->section = $row['parent_id'];
			}else{
				$parent = 1;
			}
		}else if ($options['keep_ids'] == 1){

			// Save section id if old Joomla! version is 1.0
			if (version_compare(UpgradeHelper::getVersion($this->container, 'external_version'), '1.0', '=') && isset($row['section']))
			{
				$oldlist->section = $row['section'];
			}else	if (version_compare(UpgradeHelper::getVersion($this->container, 'external_version'), '1.5', '>') && $row['parent_id'] != 1) {
				$oldlist->section = $row['parent_id'];
			}
		}

		// Correct extension
		if (isset($row['extension'])) {
			if (is_numeric($row['extension']) || $row['extension'] == "" || $row['extension'] == "category") {
				$row['extension'] = "com_content";
			}
		}

		// Fixing extension name if it's section
		if ($row['extension'] == 'com_section') {
			unset($row['id']);
			$row['extension'] = "com_content";
			$parent = 1;
		}

		// If has parent made $path and get parent id
		if ($parent !== false) {
			// Setting the location of the new category
			$category->setLocation($parent, 'last-child');
		}else{
			$category->setLocation( 1, 'last-child');
		}

		// Bind data to save category
		try
		{
			$category->bind($row);
		}
		catch (RuntimeException $e)
		{
			throw new RuntimeException($e->getMessage());
		}

		// Insert the category
		try
		{
			$category->store();
		}
		catch (RuntimeException $e)
		{
			throw new RuntimeException($e->getMessage());
		}

		// Get new id
		$oldlist->new = (int) $category->id;
		$oldlist->table = '#__categories';

		// Insert the row backup
		try
		{
			$this->_db->insertObject('#__jupgradepro_old_ids', $oldlist);
		}
		catch (RuntimeException $e)
		{
			throw new RuntimeException($e->getMessage());
		}

	 	return true;
	}

	/**
	 * Insert existing categories saved in cleanup step
	 *
	 * @return	void
	 * @since	3.0
	 */
	protected function insertExisting()
	{
		// Getting the database instance
		$db = JFactory::getDbo();

		// Getting the data
		$query = $db->getQuery(true);
		$query->select('*');
		$query->from('#__jupgradepro_default_categories');
		$query->order('id ASC');
		$db->setQuery($query);
		$categories = $db->loadAssocList();

		foreach ($categories as $category) {
			// Unset id
			$category['id'] = 0;

			// Looking for parent
			$parent = 1;
			$explode = explode("/", $category['path']);

			if (count($explode) > 1) {

				// Getting the data
				$query = $db->getQuery(true);
				$query->select('id');
				$query->from('#__categories');
				$query->where("path = '{$explode[0]}'");
				$query->order('id ASC');
				$query->setLimit(1);

				$db->setQuery($query);
				$parent = $db->loadResult();
			}

			// Inserting the category
			$this->insertCategory($category, $parent);
		}

	}

	/**
	 * Return the root id.
	 *
	 * @return	int	The last root id
	 * @since		3.2.2
	 * @throws	Exception
	 */
	public function getRootNextId()
	{
		$query = $this->_db->getQuery(true);
		$query->select("`id` + 1");
		$query->from("#__categories");
		$query->where("id > 1");
		$query->order('id DESC');
		$query->setLimit(1);
		$this->_db->setQuery($query);

		return (int) $this->_db->loadResult();
	}

	/**
	 * Get the saved first category
	 *
	 * @return object An object with the first category.
	 * @since		3.3.0
	 * @throws	Exception
	 */
	public function getFirstCategory()
	{
		// Get the correct equipment
		$query = $this->_db->getQuery(true);
		// Select some values
		$query->select("*");
		// Set the from table
		$query->from($this->_db->qn('#__jupgradepro_default_categories') . ' as c');
		// Conditions
		$query->where("c.root_id = 1");
		// Limit and order
		$query->order('id DESC');
		$query->setLimit(1);
		// Retrieve the data.
		return $this->_db->setQuery($query)->loadAssoc();
	}
}
