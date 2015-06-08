<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Wordpress
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 */

class Fishpig_Wordpress_Model_Post_Category extends Fishpig_Wordpress_Model_Term
{
	public function _construct()
	{
		$this->_init('wordpress/post_category');
	}

	/**
	 * Retrieve the taxonomy type
	 *
	 * @return string
	 */
	public function getTaxonomy()
	{
		return 'category';
	}
	
	/**
	 * Returns the amount of posts related to this object
	 *
	 * @return int
	 */
    public function getPostCount()
    {
    	return $this->getItemCount();
    }

	/**
	 * Retrieve a collection of children terms
	 *
	 * @return Fishpig_Wordpress_Model_Mysql_Term_Collection
	 */
	public function getChildrenCategories()
	{
		return $this->getChildrenTerms();
	}
	
	/**
	 * Retrieve the parent category
	 *
	 * @return Fishpig_Wordpress_Model_Resource_Post_Category
	 */
	public function getParentCategory()
	{
		return $this->getParentTerm();
	}

	/**
	 * Retrieve the string that is prefixed to all category URI's
	 *
	 * @return string
	 */
	public function getUriPrefix()
	{
		$helper = Mage::helper('wordpress');

		if ($helper->isAddonInstalled('WordPressSEO')) {
			if (Mage::helper('wp_addon_wordpressseo')->canRemoveCategoryBase()) {
				return '';
			}
		}
		
		if ($helper->isPluginEnabled('No Category Base WPML') || $helper->isPluginEnabled('No Category Base')) {
			return '';
		}

		return ($base = trim($helper->getWpOption('category_base', 'category'), '/ ')) === ''
			? $this->getTaxonomyType()
			: $base;
	}
	
	/**
	 * Retrieve an image URL for the category
	 * This uses the Category Images plugin (http://wordpress.org/plugins/categories-images/)
	 *
	 * @return false|string
	 */
	public function getImageUrl()
	{
		return ($imageUrl = Mage::helper('wordpress')->getWpOption('z_taxonomy_image' . $this->getId()))
			 ? $imageUrl
			 : false;
	}
}
