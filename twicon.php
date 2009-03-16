<?php
/*
Plugin Name: Twicon for WordPress
Plugin URI: http://wppluginsj.sourceforge.jp/twicon/
Description: Let's show the Twitter avatar (Twicon) to your user with those comments of you in the Web site.
Author: wokamoto
Version: 1.2.4
Author URI: http://dogmap.jp/

License:
 Released under the GPL license
  http://www.gnu.org/copyleft/gpl.html

  Copyright 2009 wokamoto (email : wokamoto1973@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/******************************************************************************
 * Filter Sample
 ******************************************************************************

// E-mail to Twitter ID
function set_twitter_id($twitter_id, $email = '') {
	if ( $email == 'hoge@example.com' )
		$twitter_id = 'hoge';
	return $twitter_id;
}
add_filter('twitter_id/twicon.php', 'set_twitter_id', 10, 2);

// Set profile image
function set_profile_image_url($profile_image_url, $id_or_email = '') {
	if ( $id_or_email == 'fuga@example.com' )
		$profile_image_url = get_option('siteurl') . '/images/fuga.png';
	return $profile_image_url;
}
add_filter('profile_image_url/twicon.php', 'set_profile_image_url', 10, 2);
 *****************************************************************************/


/******************************************************************************
 * define
 *****************************************************************************/
define('TWICON_EXPIRED', 12);		// Request cache expired (hours)
define('TWICON_CACHE', true);		// Icon File Cache (true or false)
define('TWICON_LINK_TWITTER', false);	// Twicon link Twitter (true or false)


/******************************************************************************
 * Require wp-load.php or wp-config.php
 *****************************************************************************/
if( !function_exists('get_option') ) {
	$path = (defined('ABSPATH') ? ABSPATH : dirname(dirname(dirname(dirname(__FILE__)))) . '/');
	require_once(file_exists($path.'wp-load.php') ? $path.'wp-load.php' : $path.'wp-config.php');
}


/******************************************************************************
 * twiconController Class
 *****************************************************************************/
class twiconController {
	var $_avatars = array();
	var $_meta_value = array();
	var $_cache_path = '';
	var $_cache_url = '';
	var $_option_update = false;

	/**********************************************************
	* Constructor
	***********************************************************/
	function twiconController() {
		$this->__construct();
	}
	function __construct() {
		// Require Class Snoopy
		if (!class_exists('Snoopy'))
			require_once(ABSPATH . WPINC . '/class-snoopy.php');

		$this->_avatars = get_option('twicon');

		if (defined('TWICON_CACHE') && TWICON_CACHE) {
			$this->_cache_path = $this->contentDir('/cache/twicon/');
			$this->_cache_url  = $this->contentUrl('/cache/twicon/');
			if( !file_exists($this->contentDir('/cache/')) )
				@mkdir($this->contentDir('/cache/'), 0777);
			if( !file_exists($this->_cache_path) )
				@mkdir($this->_cache_path, 0777);
			$this->_cache = file_exists($this->_cache_path);
		}
	}

	// contentDir
	function contentDir($path = '') {
		return (!defined('WP_CONTENT_DIR')
			? WP_CONTENT_DIR
			: ABSPATH . 'wp-content'
			) . $path;
	}

	// contentUrl
	function contentUrl($path = '') {
		return (!defined('WP_CONTENT_URL')
			? WP_CONTENT_URL
			: get_option('siteurl') . '/wp-content'
			) . $path;
	}

	// pluginsDir
	function pluginsDir($path = '') {
		return $this->contentDir( '/plugins' . $path );
	}

	// pluginsUrl
	function pluginsUrl($path = '') {
		return $this->contentUrl( '/plugins' . $path );
	}

	// Function updateAvatars
	function updateAvatars() {
		if ( $this->_option_update )
			update_option('twicon', $this->_avatars);
	}

	// Function getAvatar
	function getAvatar($avatar, $id_or_email, $size = '96', $default = '', $alt = false) {
		global $pagenow;

		if($pagenow == 'options-discussion.php')
			return $avatar;

		$result = $this->_get_twicon_url($id_or_email, $size);

		// If User has twitter
		if( isset($result['profile_image_url']) && $result['profile_image_url'] !== false && !empty($result['profile_image_url']) ) {
			$safe_alt = ( false === $alt ? '': attribute_escape( $alt ));

			$image       = $result['profile_image_url'];
			$expired     = $result['expiration_date'];
			$name        = (isset($result['name']) ? $result['name'] : '');
			$twitter_url = (isset($result['twitter_url']) ? $result['twitter_url'] : '');
			$avatar      = str_replace("'", '"', $avatar);

			if ( strpos($image, 'static.twitter.com') === false ) {
				if ( defined('TWICON_CACHE') && TWICON_CACHE ) {
					$cache_file_name = $this->_cache_file_name($image, $size);
					if ( $this->_cache_file_exists($this->_cache_path . $cache_file_name, $expired) )
						$image = $this->_cache_url . $cache_file_name;
					else
						$image = $this->pluginsUrl(
							  '/' . basename(dirname(__FILE__))
							. '/' . basename(__FILE__)
							. '?url=' . base64_encode($image)
							. '&amp;size=' . $size
							);
				}

				$avatar = preg_replace('/^(<img.*src=[\'"])[^\'"]*([\'"].*\/>)$/i', '$1'.$image.'$2', $avatar);
				if (!empty($name))
					$avatar = preg_replace('/^(<img.*alt=[\'"])[^\'"]*([\'"].*\/>)$/i', '$1'.$name.'$2', $avatar);
			}

			if ( ( is_admin() || (defined('TWICON_LINK_TWITTER') && TWICON_LINK_TWITTER) ) && !empty($twitter_url) )
				$avatar = "<a href=\"{$twitter_url}\" title=\"{$name}\">{$avatar}</a>";

		}

		return $avatar;
	}

	// Function getImage
	function getImage($img_url, $img_size){
		if(parse_url($img_url) === false)
			die();
		if(is_numeric($img_size) === false || $img_size > 96)
			die();

		$expired = $this->_expiration_date($img_url);
		$cache_file = $this->_cache_path . $this->_cache_file_name($img_url, $img_size);

		if( $this->_cache_file_exists($cache_file, $expired) )
			$image = imagecreatefrompng($cache_file);
		else
			$image = $this->_get_resize_image($img_url, $img_size, $cache_file);

		header('Content-Type: image/png');
		header('Expires: '.gmdate('D, d M Y H:i:s', $expired).' GMT');
		imagepng($image);
		imagedestroy($image);
	}

	// _get_avatar_id
	function _get_avatar_id($img_url) {
		$id = '';
		$find_url = substr($img_url, 0, strrpos($img_url, '_'));
		foreach($this->_avatars as $key => $avatar) {
			$search_url = substr($avatar['profile_image_url'], 0, strrpos($avatar['profile_image_url'], '_'));
			if($search_url == $find_url) {
				$id = $key;
				break;
			}
		}
		unset($avatar);
		return $id;
	}

	// Function _expiration_date
	function _expiration_date($id_or_url = ''){
		if (empty($id_or_url))
			$id = '';
		elseif (parse_url($id_or_url) !== false)
			$id = $this->_get_avatar_id($id_or_url);
		else
			$id = $id_or_url;

		return (!empty($id) && isset($this->_avatars[$id])
			? $this->_avatars[$id]['expiration_date']
			: time() + TWICON_EXPIRED * 60 * 60
			);
	}

	// _cache_file_name
	function _cache_file_name($img_url, $img_size) {
		return md5($img_url . $img_size) . '.png';
	}

	// _cache_file_exists
	function _cache_file_exists($cache_file = '',  $expired = '') {
		if ( empty($cache_file) )
			return false;

		if ( empty($expired) )
			$expired = $this->_expiration_date();

		return ( file_exists($cache_file) && filemtime($cache_file) < $expired );
	}

	// Function _get_twicon_url
	function _get_twicon_url($id_or_email, $size = '96'){
		$result['profile_image_url'] = false;
		$result['expiration_date'] = $this->_expiration_date();

		$email = '';
		$post_id = '';
		$comment_id = '';

		if ( is_object($id_or_email) ) {
			 // No avatar for pingbacks or trackbacks
			if ( isset($id_or_email->comment_type) && '' != $id_or_email->comment_type && 'comment' != $id_or_email->comment_type )
				return $result;

			if ( !empty($id_or_email->user_id) ) {
				$id = (int) $id_or_email->user_id;
				$user = get_userdata($id);
				if ( $user )
					$email = $user->user_email;
				unset($user);
			} elseif ( !empty($id_or_email->comment_author_email) ) {
				$email = $id_or_email->comment_author_email;
			}

			if ( !empty($id_or_email->comment_post_ID) )
				$post_id = $id_or_email->comment_post_ID;

			if ( !empty($id_or_email->comment_ID) )
				$comment_id = $id_or_email->comment_ID;

		} elseif ( is_numeric($id_or_email) ) {
			$id = (int) $id_or_email;
			$user = get_userdata($id);
			if ( $user )
				$email = $user->user_email;

		} else {
			$email = $id_or_email;

		}

		// No avatar
		if (empty($email))
			return $result;

		$id = '';
		$twitter_id = $this->_get_twitter_id($post_id, $comment_id, $email);
		if ( $twitter_id === false ) {
			$id = $email;
			$request = 'http://twitter.com/users/show/show.xml?email='.urlencode($id);
		} else {
			$id = $twitter_id;
			$request = 'http://twitter.com/users/show/'.$id.'.xml';
		}

		if (!empty($id))
			$result = $this->_get_twitter_status($id, $request, $size);

		return $result;
	}

	// Function _get_twitter_id
	function _get_twitter_id($post_id, $comment_id, $email){
		if ( empty($post_id) || empty($comment_id) || !defined('QC_NOTIFY_TWITTER') ) {
			$twitter_id = false;
		} else {
			if ( !isset($this->_meta_value[$post_id]) )
				$this->_meta_value[$post_id] = maybe_unserialize(get_post_meta($post_id, QC_NOTIFY_TWITTER, true));

			$twitter_id = false;
			if ( is_array($this->_meta_value[$post_id]) ) {
				foreach ($this->_meta_value[$post_id] as $key => $val) {
					if (!empty($key) && in_array($comment_id, $val, false)) {
						$twitter_id = $key;
						break;
					}
				}
				unset($val);
			}
		}

		return apply_filters('twitter_id/twicon.php', $twitter_id, $email);
	}

	// Function _get_twitter_status
	function _get_twitter_status($id, $url, $size = '96'){
		$profile_image_url = false;
		$expiration_date   = $this->_expiration_date();
		$name              = '';
		$twitter_url       = '';

		if ( isset($this->_avatars[$id]) && isset($this->_avatars[$id]['expiration_date']) && time() < $this->_avatars[$id]['expiration_date'] ) {
			$profile_image_url = (isset($this->_avatars[$id]['profile_image_url']) ? $this->_avatars[$id]['profile_image_url'] : '');
			$expiration_date   = $this->_avatars[$id]['expiration_date'];
			$name              = (isset($this->_avatars[$id]['name']) ? $this->_avatars[$id]['name'] : '');
			$twitter_url       = (isset($this->_avatars[$id]['twitter_url']) ? $this->_avatars[$id]['twitter_url'] : '');

		} else {
			$snoopy = new Snoopy;
			$snoopy->read_timeout = 30;
			$snoopy->timed_out = true;
			$snoopy->submit($url);
			$response = $snoopy->results;
			$http_code = $snoopy->response_code;
			unset($snoopy);

			if(strpos($http_code, '200') !== FALSE) {
				if (preg_match_all('/<(name|screen_name|profile_image_url)>([^<]*)<\/(name|screen_name|profile_image_url)>/i', $response, $matches, PREG_SET_ORDER)) {
					foreach ((array) $matches as $match) {
						switch (strtolower($match[1])) {
						case 'name':
							$name = $match[2];
							break;
						case 'screen_name':
							$twitter_url = 'http://twitter.com/' . $match[2];
							break;
						case 'profile_image_url':
							$profile_image_url = $match[2];
							break;
						}
					}
					unset($match);
				}
				unset($matches);
			}

			$this->_option_update = true;


		}

		$size = (int) ( is_numeric($size) ? $size : '96' );
		if ( defined('TWICON_CACHE') && TWICON_CACHE ) {
			$suffix = '_bigger';
		} else {
			if ($size <= 24)
				$suffix = '_mini';
			elseif ($size <= 48)
				$suffix = '_normal';
			else
				$suffix = '_bigger';
		}
		if ( strpos($profile_image_url, 'static.twitter.com') === false ) {
			if ( preg_match('/^(https?:\/\/.*)(_[^\/\._]*)(\.(jpe?g|gif|png))$/i', $profile_image_url, $matches) )
				$profile_image_url = $matches[1] . $suffix . $matches[3];
			unset($matches);
		}

		$profile_image_url = apply_filters('profile_image_url/twicon.php', $profile_image_url, $id);
		$result = compact('profile_image_url', 'expiration_date', 'name', 'twitter_url');
		$this->_avatars[$id] = $result;

		return $result;
	}

	// Function _get_resize_image
	function _get_resize_image($img_url, $img_size = 96, $cache_file = '') {
		$img_resized = imagecreatetruecolor($img_size, $img_size);
		$bgc = imagecolorallocate($img_resized, 255, 255, 255);
		imagefilledrectangle($img_resized, 0, 0, $img_size, $img_size, $bgc);

		$snoopy = new Snoopy;
		$snoopy->read_timeout = 5;
		$snoopy->timed_out = true;
		$snoopy->fetch($img_url);
		$imgbin = $snoopy->results;
		$http_code = $snoopy->response_code;
		unset($snoopy);

		if(strpos($http_code, '200') === false)
			return $img_resized;

		$img = @imagecreatefromstring($imgbin);
		if($img === false)
			return $img_resized;

		$img_width = imagesx($img);
		$img_height = imagesx($img);
		imagecopyresampled(
			$img_resized,
			$img,
			0, 0, 0, 0,
			$img_size, $img_size,
			$img_width, $img_height);

		@imagepng($img_resized, $cache_file);

		return $img_resized;
	}
}


/******************************************************************************
 * Go Go Go!
 *****************************************************************************/
global $twicon;

$twicon = new twiconController();

if ( strpos($_SERVER['PHP_SELF'], basename(__FILE__)) === false ) {
	// Add WordPress Filter
	add_filter('get_avatar', array(&$twicon, 'getAvatar'), 10, 5);
	add_action('shutdown',   array(&$twicon, 'updateAvatars'));

} elseif ( defined('TWICON_CACHE') && TWICON_CACHE && isset($_GET['url']) ) {
	// Get Image from Cache
	$img_url  = base64_decode($_GET['url']);
	$img_size = (int) (isset($_GET['size']) ? stripslashes($_GET['size']) : 48);

	$twicon->getImage($img_url, $img_size);
}
?>