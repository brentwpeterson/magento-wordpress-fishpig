<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Wordpress
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 */

class Fishpig_Wordpress_Model_Resource_Post_Collection extends Fishpig_Wordpress_Model_Resource_Post_Collection_Abstract
{
	/**
	 * Name prefix of events that are dispatched by model
	 *
	 * @var string
	*/
	protected $_eventPrefix = 'wordpress_post_collection';
	
	/**
	 * Name of event parameter
	 *
	 * @var string
	*/
	protected $_eventObject = 'posts';
	
	/**
	 * Custom field join flags
	 *
	 * @var array string
	 */
	protected $_joinedCustomFields = array();
	
	/**
	 * Set the resource
	 *
	 * @return void
	 */
	public function _construct()
	{
		$this->_init('wordpress/post');
	}
	
	/**
	 * Add the permalink data before loading the collection
	 *
	 * @return $this
	 */
	protected function _beforeLoad()
	{
		parent::_beforeLoad();

		if (in_array('post', $this->_postTypes)) {
			if ($sql = $this->getResource()->getPermalinkSqlColumn()) {
				$this->getSelect()->columns(array('permalink' => $sql));
			}
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
		parent::_afterLoad();

		$this->getResource()->preparePosts($this->_items);		

		return $this;
	}
	
	/**
	 * Filters the collection by an array of post ID's and category ID's
	 * When filtering by a category ID, all posts from that category will be returned
	 * If you change the param $operator to AND, only posts that are in a category specified in
	 * $categoryIds and $postIds will be returned
	 *
	 * @param mixed $postIds
	 * @param mixed $categoryIds
	 * @param string $operator
	 */
	public function addCategoryAndPostIdFilter($postIds, $categoryIds, $operator = 'OR')
	{
		if (!is_array($postIds)) {
			$postIds = array($postIds);
		}
		
		if (!is_array($categoryIds)) {
			$categoryIds = array($categoryIds);
		}

		if (count($categoryIds) > 0) {
			$this->joinTermTables('category');
		}
		
		$readAdapter = Mage::helper('wordpress/database')->getReadAdapter();

		$postSql = $readAdapter->quoteInto("`main_table`.`ID` IN (?)", $postIds);
		$categorySql = $readAdapter->quoteInto("`tax_category`.`term_id` IN (?)", $categoryIds);
		
		if (count($postIds) > 0 && count($categoryIds) > 0) {
			$this->getSelect()->where("{$postSql} {$operator} {$categorySql}");
		}
		else if (count($postIds) > 0) {
			$this->getSelect()->where("{$postSql}");
		}
		else if (count($categoryIds) > 0) {
			$this->getSelect()->where("{$categorySql}");	
		}

		return $this;	
	}


	/**
	 * Filters the collection by a category slug
	 *
	 * @param string $categorySlug
	 */
	public function addCategorySlugFilter($categorySlug)
	{
		return $this->joinTermTables('category')
			->addFieldToFilter('terms_category.slug', $categorySlug);
	}

	/**
	  * Filter the collection by a category ID
	  *
	  * @param int $categoryId
	  * @return $this
	  */
	public function addCategoryIdFilter($categoryId)
	{
		return $this->addTermIdFilter($categoryId, 'category');
	}
	
	/**
	  * Filter the collection by a tag ID
	  *
	  * @param int $categoryId
	  * @return $this
	  */
	public function addTagIdFilter($tagId)
	{
		return $this->addTermIdFilter($tagId, 'post_tag');
	}
	
	/**
	 * Filters the collection with an archive date
	 * EG: 2010/10
	 *
	 * @param string $archiveDate
	 */
	public function addArchiveDateFilter($archiveDate, $isDaily = false)
	{
		if ($isDaily) {
			$this->getSelect()->where("`main_table`.`post_date` LIKE ?", str_replace("/", "-", $archiveDate)." %");
		}
		else {
			$this->getSelect()->where("`main_table`.`post_date` LIKE ?", str_replace("/", "-", $archiveDate)."-%");
		}
			
		return $this;	
	}
	
	/**
	 * Join a custom field to the query
	 *
	 * @param string $field
	 * @param string $joinType = 'join'
	 * @return $this
	 */
	public function joinCustomField($field, $joinType = 'join')
	{
		if (trim($joinType) === '') {
			$joinType = 'join';
		}

		if (!isset($this->_joinedCustomFields[$field])) {
			$this->_joinedCustomFields[$field] = true;
			
			$alias = '_custom_field_' . $field;
			
			$this->getSelect()
				->$joinType(
					array($alias => $this->getTable('wordpress/post_meta')),
					"`{$alias}`.`post_id` = `main_table`.`ID` AND " . $this->getConnection()->quoteInto("`{$alias}`.`meta_key`=?", $field),
					array($field => 'meta_value')
				);
		}
		
		return $this;
	}
	
	/**
	 * Add a custom field to the WHERE portion
	 *
	 * @param string $field
	 * @param mixed $value
	 * @param string $operator = '='
	 * @param string $join = 'join'
	 * @return $this
	 */
	public function addCustomFieldFilter($field, $value, $operator = '=', $join = 'join')
	{
		$this->joinCustomField($field, $join)
			->getSelect()->where(sprintf('`_custom_field_%s`.`meta_value` %s (?)', $field, $operator), $value);
		
		return $this;
	}
	
	/**
	 * Add sticky posts to the filter
	 *
	 * @param bool $isSticky = true
	 * @return $this
	 */
	public function addStickyPostsToCollection()
	{
		if (($sticky = trim(Mage::helper('wordpress')->getWpOption('sticky_posts'))) !== '') {
			$stickyIds = unserialize($sticky);
			
			if (count($stickyIds) > 0) {
				$select = Mage::helper('wordpress/database')->getReadAdapter()
					->select()
					->from($this->getTable('wordpress/post'), new Zend_Db_Expr(1))
					->where('main_table.ID IN (?)', $stickyIds)
					->limit(1);
				
				$this->getSelect()
					->columns(array('is_sticky' => '(' . $select . ')'))
					->order('is_sticky DESC');
			}
		}
		
		return $this;
	}
}
