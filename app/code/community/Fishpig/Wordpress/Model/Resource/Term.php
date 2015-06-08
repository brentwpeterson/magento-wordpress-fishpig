<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Wordpress
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 */

class Fishpig_Wordpress_Model_Resource_Term extends Fishpig_Wordpress_Model_Resource_Abstract
{
	/**
	 * Cache for term URI's
	 * sorted by taxonomoy
	 *
	 * @var array
	 */
	static $_uriCache = array();
	
	public function _construct()
	{
		$this->_init('wordpress/term', 'term_id');
	}
	
	/**
	 * Custom load SQL to combine required tables
	 *
	 * @param string $field
	 * @param string|int $value
	 * @param Mage_Core_Model_Abstract $object
	 */
	protected function _getLoadSelect($field, $value, $object)
	{
		$select = $this->_getReadAdapter()->select()
			->from(array('main_table' => $this->getMainTable()));
		
		if (strpos($field, '.') !== false) {
			$select->where($field . '=?', $value);
		}
		else {
			$select->where("main_table.{$field}=?", $value);
		}
			
		$select->join(
			array('taxonomy' => $this->getTable('wordpress/term_taxonomy')),
			'`main_table`.`term_id` = `taxonomy`.`term_id`',
			array('term_taxonomy_id', 'taxonomy', 'description', 'count', 'parent')
		);
		
		if ($object->getTaxonomy()) {
			$select->where('taxonomy.taxonomy=?', $object->getTaxonomy());
		}

		return $select->limit(1);
	}
	
	/**
	 * Loads a category by an array of slugs
	 * The array should be the order of slugs found in the URI
	 * The whole slug array must match (including parent relationsips)
	 *
	 * @param array $slugs
	 * @param Fishpig_Wordpress_Model_Term $object
	 * @return false
	 */
	public function loadBySlugs(array $slugs, Fishpig_Wordpress_Model_Term $object)
	{
		$slugs = array_reverse($slugs);
		$primarySlug = array_shift($slugs);

		try {
			$object->loadBySlug($primarySlug);
			
			if ($object->getId()) {
				$category = $object;
				
				foreach($slugs as $slug) {
					$parent = Mage::getModel($object->getResourceName())->loadBySlug($slug);
					
					if ($parent->getId() !== $category->getParent()) {
						throw new Exception('This path just ain\'t right, bro!');
					}
					
					$category->setParentTerm($parent);
					$category = $parent;
				}

				if (!$category->getParentId()) {
					return true;
				}
			}
		}
		catch (Exception $e) {}
	
		$object->setData(array())->setId(null);

		return false;
	}
	
	/**
	 * Retrieve the URI for $term
	 *
	 * @param Fishpig_Wordpress_Model_Term $term
	 * @return false|string
	 */
	public function getTermUri(Fishpig_Wordpress_Model_Term $term)
	{
		return $this->getUriById($term->getId(), $term->getTaxonomyType());
	}

	/**
	 * Retrieve the URI for $term
	 *
	 * @param Fishpig_Wordpress_Model_Term $term
	 * @return false|string
	 */
	public function getUriById($termId, $taxonomy)
	{
		if (($uris = $this->getUrisByTaxonomy($taxonomy)) !== false) {
			return isset($uris[$termId])
				? $uris[$termId]
				: false;
		}

		return false;	
	}
	
	/**
	 * Retrieve an array of URI's for the given taxonomy
	 *
	 * @param string $taxonomy
	 * @return array|false
	 */
	public function getUrisByTaxonomy($taxonomy)
	{
		if (isset(self::$_uriCache[$taxonomy])) {
			return self::$_uriCache[$taxonomy];
		}
		
		self::$_uriCache[$taxonomy] = false;
		
		$select = $this->_getReadAdapter()
			->select()
			->from(array('term' => $this->getMainTable()), array('id' => 'term_id', 'url_key' => 'slug'))
			->join(
				array('tax' => $this->getTable('wordpress/term_taxonomy')),
				$this->_getReadAdapter()->quoteInto("tax.term_id = term.term_id AND tax.taxonomy = ?", $taxonomy),
				'parent'
			);

		if ($results = $this->_getReadAdapter()->fetchAll($select)) {
			if ((bool)Mage::getConfig()->getNode('wordpress/legacy/disable_term_hierarchy')) {
				foreach($results as $key => $result) {
					$results[$key]['parent'] = null;
				}
			}

			self::$_uriCache[$taxonomy] = Mage::helper('wordpress/router')->generateRoutesFromArray($results);
		}

		return self::$_uriCache[$taxonomy];
	}
	
	/**
	 * Determine whether $post is in the current term
	 *
	 * @param Fishpig_Wordpress_Model_Term $term
	 * @param int|Fishpig_Wordpress_Model_Post_Abstract $object
	 * @return bool
	 */
	public function containsPost($term, $object)
	{
		$objectId = is_object($object) ? $object->getId() : $object;
		
		if (!$objectId || $term->getItemCount() === 0) {
			return false;
		}

		$select = $this->_getReadAdapter()
			->select()
				->from($this->getTable('wordpress/term_relationship'), 'object_id')
				->where('term_taxonomy_id = ?', $term->getTermTaxonomyId())
				->where('object_id = ?', $objectId)
				->limit(1);

		return $this->_getReadAdapter()->fetchOne($select) !== false;
	}
}
