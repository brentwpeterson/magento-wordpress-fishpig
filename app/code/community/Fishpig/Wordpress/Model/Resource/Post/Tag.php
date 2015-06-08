<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Wordpress
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 */

class Fishpig_Wordpress_Model_Resource_Post_Tag extends Fishpig_Wordpress_Model_Resource_Term
{
	public function _construct()
	{
		$this->_init('wordpress/post_tag', 'term_id');
	}
	
	/**
	 * Retrieve an array of ID's to be used in the tag cloud
	 *
	 * @return array|false
	 */
	public function getCloudTagIds()
	{
		$tags = Mage::getResourceModel('wordpress/post_tag_collection')
			->addOrderByCount()
			->setPageSize(20)
			->setCurPage(1);
		
		$tags->getSelect()->setPart('columns', array());
		$tags->getSelect()->columns(array('main_table.term_id'));		

		return $this->_getReadAdapter()->fetchCol($tags->getSelect());
	}
}
