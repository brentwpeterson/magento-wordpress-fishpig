<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Wordpress
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 */

abstract class Fishpig_Wordpress_Model_Resource_Post_Collection_Abstract extends Fishpig_Wordpress_Model_Resource_Collection_Abstract
{
	/**
	 * True if term tables have been joined
	 * This stops the term tables being joined repeatedly
	 *
	 * @var array()
	 */
	protected $_termTablesJoined = array();

	/**
	 * Store post types to be allowed in collection
	 *
	 * @var array
	 */
	protected $_postTypes = array();
	
	/**
	 * Map fields to make life easier
	 *
	 */
	protected function _construct()
	{
		parent::_construct();

		$this->_map['fields']['ID']   = 'main_table.ID';		
		$this->_map['fields']['post_type'] = 'main_table.post_type';
		$this->_map['fields']['post_status'] = 'main_table.post_status';
	}
	
	/**
	 * If post type filter not set, set one
	 *
	 * @return $this
	 */
	protected function _beforeLoad()
	{
		parent::_beforeLoad();

		if (!$this->hasPostTypeFilter()) {
			$this->_postTypes[] = $this->getNewEmptyItem()->getPostType();
		}
		
		
		if (count($this->_postTypes) === 1) {
			if ($this->_postTypes[0] !== '*') {
				$this->addFieldToFilter('post_type', $this->_postTypes[0]);
			}
		}
		else {
			$this->addFieldToFilter('post_type', array('in' => $this->_postTypes));
		}
		
		return $this;
	}
	
	/**
	 * Ensure that is any pages are in the collection, they are correctly cast
	 *
	 * @return $this
	 */
	protected function _afterLoad()
	{
		foreach($this->_items as $itemId => $object) {
			if ($object->getPostType() === 'page') {
				$page = Mage::getModel('wordpress/page')->load($itemId);
				
				if ($page->getId()) {
					$this->_items[$itemId] = $page;
				}
				else {
					unset($this->_items[$itemId]);
				}
			}
		}
		
		return parent::_afterLoad();
	}
	
	/**
	 * Add a post type filter to the collection
	 *
	 * @param string|array $postTypes
	 * @return $this
	 */
	public function addPostTypeFilter($postTypes)
	{
		if (!is_array($postTypes) && strpos($postTypes, ',') !== false) {
			$postTypes = explode(',', $postTypes);
		}

		$this->_postTypes = array_values(array_merge($this->_postTypes, (array)$postTypes));
		
		return $this;
	}
	
	/**
	 * Determine whether any post type filters exist
	 *
	 * @return bool
	 */
	public function hasPostTypeFilter()
	{
		return count($this->_postTypes) > 0;
	}
	
	/**
	 * Adds a published filter to collection
	 *
	 */
	public function addIsPublishedFilter()
	{
		return $this->addIsViewableFilter();
	}
	
	/**
	 * Filters the collection so that only posts that can be viewed are displayed
	 *
	 * @return $this
	 */
	public function addIsViewableFilter()
	{
		if (Mage::getSingleton('customer/session')->isLoggedIn()) {
			return $this->addStatusFilter(array('publish', 'private', 'protected'));
		}

		return $this->addStatusFilter(array('publish', 'protected'));		
	}
	
	/**
	 * Adds a filter to the status column
	 *
	 * @param string $status
	 */
	public function addStatusFilter($status)
	{
		$op = is_array($status) ? 'in' : 'eq';
		
		return $this->addFieldToFilter('post_status', array($op => $status));
	}
	
	/**
	 * Filter the collection by an author ID
	 *
	 * @param int $authorId
	 */
	public function addAuthorIdFilter($authorId)
	{
		return $this->addFieldToFilter('post_author', $authorId);
	}
	
	/**
	 * Orders the collection by post date
	 *
	 * @param string $dir
	 */
	public function setOrderByPostDate($dir = 'desc')
	{
		return $this->setOrder('post_date', $dir);
	}
	
	/**
	 * Orders the collection by comment count
	 *
	 * @param string $dir
	 */
	public function setOrderByCommentCount($dir = 'desc')
	{
		return $this->setOrder('comment_count', $dir);
	}
	
	/**
	 * Filter the collection by a date
	 *
	 * @param string $dateStr
	 */
	public function addPostDateFilter($dateStr)
	{
		if (!is_array($dateStr) && strpos($dateStr, '%') !== false) {
			$this->addFieldToFilter('post_date', array('like' => $dateStr));
		}
		else {
			$this->addFieldToFilter('post_date', $dateStr);
		}
		
		return $this;
	}
	
	/**
	 * Filters the collection by an array of words on the array of fields
	 *
	 * @param array $words - words to search for
	 * @param array $fields - fields to search
	 * @param string $operator
	 */
	public function addSearchStringFilter(array $words, array $fields)
	{
		if (count($words) > 0) {
			foreach($words as $word) {
				$conditions = array();

				foreach($fields as $key => $field) {
					$conditions[] = $this->getConnection()->quoteInto('`main_table`.`' . $field . '` LIKE ?', '%' . $word . '%');
				}

				$this->getSelect()->where(join(' ' . Zend_Db_Select::SQL_OR . ' ', $conditions));
			}
			
			$this->addFieldToFilter('post_password', '');
		}
		else {
			$this->getSelect()->where('1=2');
		}

		return $this;
	}
	
	/**
	 * Filters the collection by a term ID and type
	 *
	 * @param int|array $termId
	 * @param string $type
	 */
	public function addTermIdFilter($termId, $type)
	{
		$this->joinTermTables($type);
		
		if (is_array($termId)) {
			$this->getSelect()->where("`tax_{$type}`.`term_id` IN (?)", $termId);
		}
		else {
			$this->getSelect()->where("`tax_{$type}`.`term_id` = ?", $termId);
		}

		return $this;
	}
	
	/**
	 * Filters the collection by a term and type
	 *
	 * @param int|array $termId
	 * @param string $type
	 */
	public function addTermFilter($term, $type, $field = 'slug')
	{
		$this->joinTermTables($type);
		
		if (is_array($term)) {
			$this->getSelect()->where("`terms_{$type}`.`{$field}` IN (?)", $term);
		}
		else {
			$this->getSelect()->where("`terms_{$type}`.`{$field}` = ?", $term);
		}

		return $this;
	}

	/**
	 * Joins the category tables to the collection
	 * This allows filtering by category
	 */
	public function joinTermTables($type)
	{
		$type = strtolower(trim($type));
		
		if (!isset($this->_termTablesJoined[$type])) {
			$tableTax = $this->getTable('wordpress/term_taxonomy');
			$tableTermRel	 = $this->getTable('wordpress/term_relationship');
			$tableTerms = $this->getTable('wordpress/term');
			
			$this->getSelect()->join(array('rel_' . $type => $tableTermRel), "`rel_{$type}`.`object_id`=`main_table`.`ID`", '')
				->join(array('tax_' . $type => $tableTax), "`tax_{$type}`.`term_taxonomy_id`=`rel_{$type}`.`term_taxonomy_id` AND `tax_{$type}`.`taxonomy`='{$type}'", '')
				->join(array('terms_' . $type => $tableTerms), "`terms_{$type}`.`term_id` = `tax_{$type}`.`term_id`", '')
				->distinct();
			
			$this->_termTablesJoined[$type] = true;
		}

		return $this;
	}
	
	/**
	 * Add post parent ID filter
	 *
	 * @param int $postParentId
	 */
	public function addPostParentIdFilter($postParentId)
	{
		$this->getSelect()->where("main_table.post_parent=?", $postParentId);
		
		return $this;
	}
	
	/**
	 * Order the collection by the menu order field
	 *
	 * @param string $dir
	 * @return
	 */
	public function orderByMenuOrder($dir = 'asc')
	{
		$this->getSelect()->order('menu_order ' . $dir);
		
		return $this;
	}
}
