<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Wordpress
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 */

class Fishpig_Wordpress_Helper_Shortcode_Youtube extends Fishpig_Wordpress_Helper_Shortcode_Abstract
{
	/**
	 * Retrieve the shortcode tag
	 *
	 * @return string
	 */
	public function getTag()
	{
		return 'youtube';
	}
	
	/**
	 * Apply the Vimeo short code
	 *
	 * @param string &$content
	 * @param Fishpig_Wordpress_Model_Post_Abstract $object
	 * @return void
	 */	
	protected function _apply(&$content, Fishpig_Wordpress_Model_Post_Abstract $object)
	{
		if (preg_match_all('/\[youtube=(.*)\]/iU', $content, $matches)) {
			foreach($matches[1] as $key => $match) {
				$content = str_replace($matches[0][$key], sprintf('[%s url=%s]', $this->getTag(), str_replace('&', ' ', $match)), $content);
			}		
		}

		if (($shortcodes = $this->_getShortcodes($content)) !== false) {
			foreach($shortcodes as $shortcode) {
				$params = $shortcode->getParams();
				$code = substr($params->getUrl(), strpos($params->getUrl(), '?v=')+3);
				
				$urlParams = $params->getData();				
				unset($urlParams['url']);
				
				$url = 'http://www.youtube.com/v/' . $code . '?fs=1&amp;hl=en_US&amp;' . http_build_query($urlParams, '', '&amp;');

				if (!$params->getW() && !$params->getH()) {
					$sizes = array('width' => 480, 'height' => 385);
				}
				else if ($params->getW() && $params->getH()) {
					$sizes = array('width' => $params->getW(), 'height' => $params->getH());
				}
				else if (!$params->getW()) {
					$sizes = array('height' => $params->getH());
				}
				else {
					$sizes = array('width' => $params->getW());
				}
				
				$sizeStr = '';
				
				foreach($sizes as $key => $value) {
					$sizeStr .= ' ' . $key . '="' . $value . '"';
				}

				$html = sprintf($this->_getHtmlString(), $sizeStr, $url, $url, $sizeStr);

				$content = str_replace($shortcode->getHtml(), $html, $content);
			}
		}
	}
	
	/**
	 * Retrieve the HTML pattern for the Vimeo
	 *
	 * @return string
	 */
	protected function _getHtmlString()
	{
		return '	<object %s>
		<param name="movie" value="%s"></param>
		<param name="allowFullScreen" value="true"></param>
		<param name="allowscriptaccess" value="always"></param>
		<embed src="%s" type="application/x-shockwave-flash" wmode="transparent" allowscriptaccess="always" allowfullscreen="true" %s></embed>
	</object>';
	}

	/**
	 * Retrieve the content that goes between the shortcode tag and parsed URL
	 *
	 * @return string
	 */
	public function getConvertedUrlsMiddle()
	{
		return '=';
	}


	
	/**
	 * Retrieve the regex pattern for the raw URL's
	 *
	 * @return string
	 */
	public function getRawUrlRegex()
	{
		return '[\r\n]{1}(http:\/\/www.youtube.com\/watch\?v=[a-z0-9_-]{1,})[\r\n]{1}';
	}
}
