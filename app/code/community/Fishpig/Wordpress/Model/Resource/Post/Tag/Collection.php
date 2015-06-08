<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Wordpress
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 */

class Fishpig_Wordpress_Model_Resource_Post_Tag_Collection extends Fishpig_Wordpress_Model_Resource_Term_Collection
{
	public function _construct()
	{
		$this->_init('wordpress/post_tag');
	}

	/**
	 * Perform the joins necessary to create a full category record
	 */
	protected function _initSelect()
	{
		parent::_initSelect();
		
		$this->getSelect()->where('taxonomy.taxonomy=?', 'post_tag');
		
		return $this->getSelect();
	}
			
	/**
	 * Filter the collection so that only tags in the cloud
	 * are returned
	 *
	 */
	public function addTagCloudFilter()
	{
		$this->addFieldToFilter('main_table.term_id', array('in' => Mage::getResourceModel('wordpress/post_tag')->getCloudTagIds()));
		
		return $this;
	}
	
	/**
	 * Order the terms by the count field
	 *
	 * @param string $dir
	 * @return $this
	 */
	public function addOrderByCount($dir = 'desc')
	{
		return $this->addOrderByItemCount($dir);
	}
}
