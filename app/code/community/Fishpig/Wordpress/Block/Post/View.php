<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Wordpress
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 */

class Fishpig_Wordpress_Block_Post_View extends Fishpig_Wordpress_Block_Post_Abstract
{
	/**
	  * Returns the HTML for the comments block
	  *
	  * @return string
	  */
	public function getCommentsHtml()
	{
		return $this->getChildHtml('comments');
	}

	/**
	 * Setup the comments block
	 *
	 */
	protected function _beforeToHtml()
	{
		if ($this->getChild('comments')) {
			$this->getChild('comments')->setPost($this->getPost());
		}
		
		if ($this->getPost()->getPostViewTemplate()) {
			$this->setTemplate($this->getPost()->getPostViewTemplate());
		}

		return parent::_beforeToHtml();
	}
}
