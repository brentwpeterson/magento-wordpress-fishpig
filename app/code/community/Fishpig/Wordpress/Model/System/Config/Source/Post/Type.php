<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Wordpress
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 */

class Fishpig_Wordpress_Model_System_Config_Source_Post_Type
{
	protected $_types = null;

	public function toOptionArray()
	{
		$options = array(
			array('value' => '', 'label' => Mage::helper('adminhtml')->__('-- Please Select --')),
		);
		
		foreach($this->_getPostTypes() as $type) {
			$options[] = array('value' => $type, 'label' => $type);
		}
		
		return $options;
	}
	
	protected function _getPostTypes()
	{
		if (is_null($this->_types)) {
			$db = Mage::helper('wordpress/database');
			
			if ($db->getReadAdapter()) {
				$select = $db->getReadAdapter()
					->select()
					->distinct()
					->from(Mage::getResourceModel('wordpress/post')->getMainTable(), 'post_type')
					->where('post_type NOT IN (?)', $this->getAllIgnorePostTypes());
	
				$this->_types = (array)$db->getReadAdapter()->fetchCol($select);
			}
		}
		
		return (array)$this->_types;
	}

	public function getAllIgnorePostTypes()
	{
		return array_merge($this->getDefaultPostTypes(), $this->getNonCustomPostTypes());
	}
	
	public function getDefaultPostTypes()
	{
		return array(
			'post',
			'page',
			'attachment',
			'nav_menu_item',
			'revision',
			'draft',
		);
	}
	
	public function getNonCustomPostTypes()
	{
		return array(
			'acf',
			'wpcf7_contact_form',
		);
	}
}


