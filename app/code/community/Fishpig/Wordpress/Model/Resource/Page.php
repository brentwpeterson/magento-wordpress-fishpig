<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Wordpress
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 */

class Fishpig_Wordpress_Model_Resource_Page extends Fishpig_Wordpress_Model_Resource_Post_Abstract
{
	/**
	 * Page URI cache
	 *
	 * @var null|array
	 */
	static $_uriCache = null;
	
	public function _construct()
	{
		$this->_init('wordpress/page', 'ID');
	}

	/**
	 * Alter default load select so only pages (and not posts) are returned
	 *
	 * @param string $field
	 * @param string $value
	 * @param Mage_Core_Model_Abstract $object
	 * @return Varien_Db_Select
	*/
	protected function _getLoadSelect($field, $value, $object)
	{
		$select = parent::_getLoadSelect($field, $value, $object)
			->where("`post_type`=?", 'page');

		return $select;
	}
	
	/**
	 * Retrieve a pages parent page
	 *
	 * @param Fishpig_Wordpress_Model_Page $page
	 * @return false|Fishpig_Wordpress_Model_Page
	 */
	public function getParentPage(Fishpig_Wordpress_Model_Page $page)
	{
		if ($page->getPostParent()) {
			$parent = Mage::getModel('wordpress/page')->load($page->getPostParent());
			
			return $parent->getId()
				? $parent
				: false;
		}
	
		return false;
	}
	
	/**
	 * Determine whether the given page has any children pages
	 *
	 * @param Fishpig_Wordpress_Model_Page $page
	 * @return bool
	 */
	public function hasChildren(Fishpig_Wordpress_Model_Page $page)
	{
		$select = $this->_getReadAdapter()
			->select()
			->from($this->getMainTable(), 'ID')
			->where('post_parent=?', $page->getId())
			->where('post_type=?', 'page')
			->where('post_status=?', 'publish')
			->limit(1);
			
		return $this->_getReadAdapter()->fetchOne($select) !== false;
	}
	
	/**
	 * Retrieve the current pages children pages as a collection
	 *
	 * @param Fishpig_Wordpress_Model_Page $page
	 * @return Fishpig_Wordpress_Model_Mysql_Page_Collection
	 */
	public function getChildrenPages(Fishpig_Wordpress_Model_Page $page)
	{
		return $page->getCollection()
			->addPostParentIdFilter($page->getId())
			->orderByMenuOrder();
	}
	
	/**
	 * Retrieve the permalink for a pge
	 *
	 * @param Fishpig_Wordpress_Model_Page $page
	 * @return string
	 */
	public function getPermalink(Fishpig_Wordpress_Model_Page $page)
	{
		if ($uris = $this->getAllUris()) {
			if (isset($uris[$page->getId()])) {
				return Mage::helper('wordpress')->getUrl($uris[$page->getId()] . '/');
			}
		}
		
		return Mage::helper('wordpress')->getUrl() . '?page_id=' . $page->getId();
	}
	
	/**
	 * Retrieve all possible page URIs
	 *
	 * @return array
	 */
	public function getAllUris()
	{
		if (is_array(self::$_uriCache)) {
			return self::$_uriCache;	
		}
		
		$select = $this->_getReadAdapter()
			->select()
			->from(array('term' => $this->getMainTable()), array('id' => 'ID','url_key' =>  'post_name', 'parent' => 'post_parent'))
			->where('post_type=?', 'page')
			->where('post_status=?', 'publish');
			
		self::$_uriCache = Mage::helper('wordpress/router')->generateRoutesFromArray(
			$this->_getReadAdapter()->fetchAll($select)
		);
		
		return self::$_uriCache;
	}
}
