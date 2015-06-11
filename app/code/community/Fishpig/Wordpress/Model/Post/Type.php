<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Wordpress
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 */
 
class Fishpig_Wordpress_Model_Post_Type extends Mage_Core_Model_Abstract
{
	static $_uriCache = array();
	
	/**
	 * Determine whether post type uses GUID links
	 *
	 * @return bool
	 */
	public function useGuidLinks()
	{
		return trim($this->getData('rewrite/slug')) === '';
	}
	
	/**
	 * Determine whether the post type is a built-in type
	 *
	 * @return bool
	 */
	public function isDefault()
	{
		return (int)$this->_getData('_builtin') === 1;
	}
	
	/**
	 * Get the permalink structure as a string
	 *
	 * @return string
	 */
	public function getPermalinkStructure()
	{
		$structure = ltrim(str_replace('index.php/', '', ltrim($this->getData('rewrite/slug'), ' -/')), '/');
		
		if (!$this->isDefault() && strpos($structure, '%postname%') === false) {
			$structure = rtrim($structure, '/') . '/%postname%/';
		}
		
		return $structure;
	}
	
	/**
	 * Retrieve the permalink structure in array format
	 *
	 * @return false|array
	 */
	public function getExplodedPermalinkStructure()
	{
		$structure = $this->getPermalinkStructure();
		$parts = preg_split("/(\/|-)/", $structure, -1, PREG_SPLIT_DELIM_CAPTURE);
		$structure = array();

		foreach($parts as $part) {
			if ($result = preg_split("/(%[a-zA-Z0-9_]{1,}%)/", $part, -1, PREG_SPLIT_DELIM_CAPTURE)) {
				$results = array_filter(array_unique($result));

				foreach($results as $result) {
					array_push($structure, $result);
				}
			}
			else {
				$structure[] = $part;
			}
		}
		
		return $structure;
	}

	/**
	 * Determine whether the permalink has a trailing slash
	 *
	 * @return bool
	 */
	public function permalinkHasTrainingSlash()
	{
		return !$this->isDefault() || substr($this->getData('rewrite/slug'), -1) == '/';
	}

	/**
	 * Retrieve the URL to the cpt page
	 *
	 * @return string
	 */
	public function getUrl()
	{
		return Mage::helper('wordpress')->getUrl($this->getArchiveSlug() . '/');
	}
	
	/**
	 * Retrieve the post collection for this post type
	 *
	 * @return Fishpig_Wordpress_Model_Resource_Post_Collection
	 */
	public function getPostCollection()
	{
		return Mage::getResourceModel('wordpress/post_collection')->addPostTypeFilter($this->getPostType());
	}

	/**
	 * Get the archive slug for the post type
	 *
	 * @return string
	 */	
	public function getSlug()
	{
		return $this->getData('rewrite/slug');
	}
	
	/**
	 * Get the archive slug for the post type
	 *
	 * @return string
	 */
	public function getArchiveSlug()
	{
		if ((int)$this->getHasArchive() !== 1) {
			return false;
		}
		
		if ($this->hasArchiveSlug()) {
			return $this->_getData('archive_slug');
		}
		
		$slug = $this->getSlug();

		$this->setArchiveSlug(
			strpos($slug, '%') !== false
			? trim(substr($slug, 0, strpos($slug, '%')), '/')
			: trim($slug, '/')
		);

		return $this->_getData('archive_slug');
	}
	
	/**
	 * Determine whether $taxonomy is supported by the post type
	 *
	 * @param string $taxonomy
	 * @return bool
	 */
	public function isTaxonomySupported($taxonomy)
	{
		return $this->getTaxonomies()
			? in_array($taxonomy, $this->getTaxonomies())
			: false;
	}
	
	/**
	 * Get the name of the post type
	 *
	 * @return string
	 */
	public function getName()
	{
		return $this->getData('labels/name');
	}
	
	public function isHierarchical()
	{
		return (int)$this->getData('hierarchical') === 1;
	}
	
	public function getAllRoutes()
	{
		if (!$this->isHierarchical()) {
			return false;
		}
		
		if (isset(self::$_uriCache[$this->getPostType()])) {
			return self::$_uriCache[$this->getPostType()];
		}
		
		if (!($db = Mage::helper('wordpress/app')->getDbConnection())) {
			return false;
		}

		$select = $db->select()
			->from(array('term' => Mage::getSingleton('core/resource')->getTableName('wordpress/post')), array(
				'id' => 'ID',
				'url_key' =>  'post_name', 
				'parent' => 'post_parent'
			))
			->where('post_type=?', $this->getPostType())
			->where('post_status=?', 'publish');
				
		self::$_uriCache[$this->getPostType()] = Mage::helper('wordpress/router')->generateRoutesFromArray(
			$db->fetchAll($select)
		);
		
		return self::$_uriCache[$this->getPostType()];
	}
}
