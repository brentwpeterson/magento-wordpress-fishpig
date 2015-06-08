<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Wordpress
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 */

class Fishpig_Wordpress_Block_Category_Feed extends Fishpig_Wordpress_Block_Feed_Post_Abstract
{
	/**
	 * Allow subclasses to filter products
	 *
	 * @return $this
	 */
	protected function _prepareItemCollection($collection)
	{
		if (($category = Mage::registry('wordpress_category')) !== null) {
			$collection->addCategoryIdFilter(
				$category->getId()
			);
		}
		
		return parent::_prepareItemCollection($collection);
	}
}
