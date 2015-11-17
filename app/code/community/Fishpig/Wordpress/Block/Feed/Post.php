<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Wordpress
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 */

class Fishpig_Wordpress_Block_Feed_Post extends Fishpig_Wordpress_Block_Feed_Abstract
{
	/**
	 * Generate the entries and add them to the RSS feed
	 *
	 * @param Zend_Feed_Writer_Feed $feed
	 * @return $this
	 */
	protected function _addEntriesToFeed($feed)
	{
		$posts = Mage::getSingleton('core/layout')->createBlock($this->getSourceBlock())
			->getPostCollection();

		$this->_prepareItemCollection($posts);

		foreach($posts as $post) {
			$entry = $feed->createEntry();
			
			if (!$post->getPostTitle()) {
				continue;
			}

			if (!($postDate = strtotime($post->getData('post_date_gmt')))) {
				continue;
			}

			$entry->setDateModified($postDate);
			
			$entry->setTitle($post->getPostTitle());
			$entry->setLink($post->getPermalink());

			$_author = $post->getAuthor();
			
			if ($_author->getUserEmail() && $_author->getDisplayName()) {
				$entry->addAuthor(array(
					'name' => $_author->getDisplayName(),
					'email' => $_author->getUserEmail(),
				));
			}
			
			$description = $this->displayExceprt() ? $post->getPostExcerpt() : $post->getPostContent();

			$entry->setDescription($description ? $description : '&nbsp;');
			
			foreach($post->getParentCategories() as $category) {
				$entry->addCategory(array(
					'term' => $category->getUrl(),
				));
			}
			
			$feed->addEntry($entry);
		}
	
		return $this;
	}

	/**
	 * Determine whether to display the excerpt
	 *
	 * @return bool
	 */
	public function displayExceprt()
	{
		return Mage::helper('wordpress')->getWpOption('rss_use_excerpt');
	}
}
