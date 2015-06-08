<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Wordpress
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 */
	
	$this->startSetup();

	try {
		$select = $this->getConnection()
			->select()
			->from($this->getTable('wordpress_addon_cpt_type'), '*');
			
		$types = array();
		
		try {
			$results = $this->getConnection()->fetchAll($select);
		}
		catch (Exception $e) {
			$this->endSetup();
			return false;	
		}
		
		foreach($select as $type) {
			if (!$type['post_type']) {
				continue;
			}

			$types['_' . rand(100000, 999999) . rand(1000000, 9999999) . '_' . rand(100, 999)] = array(
				'type' => $type['post_type'],
				'name' => !empty($type['name']) ? $type['name'] : $type['singular_name'],
				'slug' => $type['slug'],
				'template_list' => $type['post_list_template'],
				'templat_post' => $type['post_view_template'],
			);				
		}
		
		$this->getConnection()->insert(
			$this->getTable('core_config_data'),
			array(
				'scope' => 'default',
				'scope_id' => 0,
				'path' => 'wordpress/extend/post_types',
				'value' => serialize($types),
			)
		);
	}
	catch (Exception $e) {
		Mage::helper('wordpress')->log($e);
		throw $e;
	}

	$this->endSetup();
