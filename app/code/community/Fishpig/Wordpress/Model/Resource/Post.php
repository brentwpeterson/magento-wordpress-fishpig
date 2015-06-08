<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Wordpress
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 */

class Fishpig_Wordpress_Model_Resource_Post extends Fishpig_Wordpress_Model_Resource_Post_Abstract
{
	/**
	 * Set the table and primary key
	 *
	 * @return void
	 */
	public function _construct()
	{
		$this->_init('wordpress/post', 'ID');
	}

	/**
	 * Custom load SQL
	 *
	 * @param string $field - field to match $value to
	 * @param string|int $value - $value to load record based on
	 * @param Mage_Core_Model_Abstract $object - object we're trying to load to
	 */
	protected function _getLoadSelect($field, $value, $object)
	{
		$select = parent::_getLoadSelect($field, $value, $object);

		if ($object->getPostType() === 'post') {
			if ($sql = $this->getPermalinkSqlColumn()) {
				$select->columns(array('permalink' => $sql));
			}
		}

		return $select;
	}
	
	/**
	 * Prepare a collection/array of posts
	 *
	 * @param mixed $posts
	 * @return $this
	 */
	public function preparePosts($posts)
	{
		$postIds = array();
		
		foreach($posts as $post) {
			if ($post->getId()) {
				$postIds[] = $post->getId();
			}
		}

		if ($results = $this->getParentCategoryIdsByPostIds($postIds)) {
			$categoryCache = array();
			
			$permalinkStructure = $post->getCustomPermalinkStructure()
				? $post->getCustomPermalinkStructure()
				: Mage::helper('wordpress/post')->getPermalinkStructure();

			$hasCategoryPermalink = strpos($permalinkStructure, '%category%') !== false;
			
			foreach($posts as $post) {
				foreach($results as $it => $result) {
					if ($post->getId() === $result['object_id']) {
						$categoryIds = explode(',', $result['category_ids']);
						 
						 $post->setCategoryIds($categoryIds);

						 if (isset($categoryCache[$categoryIds[0]])) {
							 $post->setParentCategory($categoryCache[$categoryIds[0]]);
						 }
						 else {
							$category = Mage::getModel('wordpress/post_category')->load($categoryIds[0]);
							
							if ($category->getId()) {
								$post->setParentCategory($category);
								
								$categoryCache[$categoryIds[0]] = $category;
							}
						}
						
						if ($hasCategoryPermalink) {
							if ($post->getParentCategory()) {
								$post->setPermalink(str_replace('%category%', $post->getParentCategory()->getUri(), $post->getData('permalink')));
							}
						}

						unset($results[$it]);
						break;
					}
				}
			}
		}

		return $this;
	}
	
	public function getParentCategoryIdsByPostIds($postIds, $getAllIds = true)
	{
		$select = $this->_getReadAdapter()->select()
			->distinct()
			->from(array('_relationship' => $this->getTable('wordpress/term_relationship')), 'object_id')
			->where('object_id IN (?)', $postIds)
			->join(
				array('_taxonomy' => $this->getTable('wordpress/term_taxonomy')),
				"`_taxonomy`.`term_taxonomy_id` = `_relationship`.`term_taxonomy_id` AND `_taxonomy`.`taxonomy`= 'category'",
				'*')
			->join(
				array('_term' => $this->getTable('wordpress/term')),
				"`_term`.`term_id` = `_taxonomy`.`term_id`",
				'name')
			->order('_term.name ASC');

		if (!$getAllIds) {
			$select->reset('columns')
				->columns(array('category_id' => '_term.term_id', 'object_id'))
				->limit(1);
				
			return $this->_getReadAdapter()->fetchAll($select);
		}
		
		$wrapper = $this->_getReadAdapter()
			->select()
				->from(array('squery' => new Zend_Db_Expr('(' . (string)$select . ')')))
				->group('squery.object_id')
				->reset('columns')
				->columns(array(
					'object_id',
					'category_ids' => new Zend_Db_Expr("GROUP_CONCAT(`squery`.`term_id` ORDER BY `squery`.`name` ASC)"
				)));

		return $this->_getReadAdapter()->fetchAll($wrapper);
	}
	
	/**
	 * Retrieve a collection of post tags
	 *
	 * @param Fishpig_Wordpress_Model_Post $post
	 * @return Fishpig_Wordpress_Model_Resource_Post_Tag_Collection
	 */
	public function getPostTags(Fishpig_Wordpress_Model_Post $post)
	{
		return Mage::getResourceModel('wordpress/post_tag_collection')->addPostIdFilter($post->getId());
	}
	
	/**
	 * Retrieve a collection of categories
	 *
	 * @param Fishpig_Wordpress_Model_Post $post
	 * @retrun Fishpig_Wordpress_Model_Post_Category_Collection
	 */
	public function getParentCategories(Fishpig_Wordpress_Model_Post $post)
	{
		return Mage::getResourceModel('wordpress/post_category_collection')->addFieldToFilter('main_table.term_id', array('in' => $post->getCategoryIds()));
	}
		
	/**
	 * Get the permalink SQL as a SQL string
	 *
	 * @return string
	 */
	public function getPermalinkSqlColumn()
	{
		$fields = $this->getPermalinkSqlFields();
		$tokens = Mage::helper('wordpress/post')->getExplodedPermalinkStructure();
		$sqlFields = array();

		foreach($tokens as $token) {
			if (substr($token, 0, 1) === '%' && isset($fields[trim($token, '%')])) {
				$sqlFields[] = $fields[trim($token, '%')];
			}
			else {
				$sqlFields[] = "'" . $token . "'";
			}
		}	

		if (count($sqlFields) > 0) {
			return 'CONCAT(' . implode(', ', $sqlFields) . ')';
		}
		
		return false;
	}
	
	/**
	 * Get permalinks by the URI
	 * Given a $uri, this will retrieve all permalinks that *could* match
	 *
	 * @param string $uri = ''
	 * @param array $postTypes = null
	 * @return false|array
	 */
	public function getPermalinksByUri($uri = '', $postTypes = null)
	{
		if (Mage::helper('wordpress/post')->permalinkHasTrainingSlash()) {
			$uri = rtrim($uri, '/') . '/';
		}

		$fields = $this->getPermalinkSqlFields();
		$tokens = Mage::helper('wordpress/post')->getExplodedPermalinkStructure();
		$filters = array();

		$lastToken = $tokens[count($tokens)-1];
		
		# Allow for trailing static strings (eg. .html)
		if (substr($lastToken, 0, 1) !== '%') {
			if (substr($uri, -strlen($lastToken)) !== $lastToken) {
				return false;
			}
			
			$uri = substr($uri, 0, -strlen($lastToken));
			
			array_pop($tokens);
		}

		for($i = 0; $i <= 1; $i++) {
			if ($i === 1) {
				$uri = implode('/', array_reverse(explode('/', $uri)));
				$tokens = array_reverse($tokens);
			}
			
			foreach($tokens as $key => $token) {
				if (substr($token, 0, 1) === '%') {
					if (!isset($fields[trim($token, '%')])) {
						break;
					}
					
					if (isset($tokens[$key+1]) && substr($tokens[$key+1], 0, 1) !== '%') {
						$filters[trim($token, '%')] = substr($uri, 0, strpos($uri, $tokens[$key+1]));
						$uri = substr($uri, strpos($uri, $tokens[$key+1]));
					}
					else if (!isset($tokens[$key+1])) {
						$filters[trim($token, '%')] = $uri;
						$uri = '';
					}
					else {
						return false;
					}
				}
				else if (substr($uri, 0, strlen($token)) === $token) {
					$uri = substr($uri, strlen($token));
				}
				else {
					return false;
				}
				
				unset($tokens[$key]);
			}
		}

		return $this->getPermalinks($filters, $postTypes);
	}
	
	/**
	 * Get an array of post ID's and permalinks
	 * $filters is applied but if empty, all permalinks are returned
	 *
	 * @param array $filters = array()
	 * @return array|false
	 */
	public function getPermalinks(array $filters = array(), $postTypes = null)
	{
		if (!$postTypes) {
			$postTypes = array('post');
		}
		
		$tokens = Mage::helper('wordpress/post')->getExplodedPermalinkStructure();
		$fields = $this->getPermalinkSqlFields();
		
		$select = $this->_getReadAdapter()
			->select()
			->from(array('main_table' => $this->getMainTable()), array('id' => 'ID', 'permalink' => $this->getPermalinkSqlColumn()))
			->where('post_type IN (?)', (array)$postTypes)
			->where('post_status IN (?)', array('publish', 'protected', 'private'));

		foreach($filters as $field => $value) {
			if (isset($fields[$field])) {
				$select->where($fields[$field] . '=?', urlencode($value));
			}
		}

		if ($results = $this->_getReadAdapter()->fetchAll($select)) {
			$routes = array();

			foreach($results as $result) {
				$routes[$result['id']] = urldecode($result['permalink']);
			
				if (in_array('%category%', $tokens)) {
					$categoryIds = $this->getParentCategoryIdsByPostIds(array_keys($routes), false);
					
					foreach($categoryIds as $key => $category) {
						if ($category['object_id'] == $result['id']) {
							$categorySlug = Mage::getResourceSingleton('wordpress/term')->getUriById($category['category_id'], 'category');
							
							$routes[$result['id']] = str_replace('%category%', $categorySlug, $result['permalink']);
							unset($categoryIds[$key]);
							break;
						}
					}
				}
			}

			return $routes;
		}
		
		return false;
	}
	
	/**
	 * Get the SQL data for the permalink
	 *
	 * @return array
	 */
	public function getPermalinkSqlFields()
	{
		return array(
			'year' => 'SUBSTRING(post_date_gmt, 1, 4)',
			'monthnum' => 'SUBSTRING(post_date_gmt, 6, 2)',
			'day' => 'SUBSTRING(post_date_gmt, 9, 2)',
			'hour' => 'SUBSTRING(post_date_gmt, 12, 2)',
			'minute' => 'SUBSTRING(post_date_gmt, 15, 2)',
			'second' => 'SUBSTRING(post_date_gmt, 18, 2)',
			'post_id' => 'ID', 
			'postname' => 'post_name',
			'author' => 'post_author',
		);
	}
}
