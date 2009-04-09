<?php
/*
Plugin Name: Twicon for WordPress
Plugin URI: http://wppluginsj.sourceforge.jp/twicon/
Description: Let's show the Twitter avatar (Twicon) to your user with those comments of you in the Web site.
Author: wokamoto
Version: 1.3.0
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
 * comment.php
 ******************************************************************************
// Add your comment.php

<?php if (function_exists('twicon_input_box')) twicon_input_box(); ?>

 *****************************************************************************/

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
function set_profile_image_url($profile_image_url, $id_or_email = '', $size = 48) {
	if ( $id_or_email == 'fuga@example.com' )
		$profile_image_url = get_option('siteurl') . '/images/fuga.png';
	return $profile_image_url;
}
add_filter('profile_image_url/twicon.php', 'set_profile_image_url', 10, 3);
 *****************************************************************************/

/******************************************************************************
 * define
 *****************************************************************************/
define('TWICON_LINK_TWITTER', false);		// Twicon link Twitter (true or false)
define('TWICON_EXPIRED', 12);			// Request cache expired (hours)
define('TWICON_CACHE', true);			// Icon File Cache (true or false)
define('TWICON_CACHE_DIR', 'cache/twicon');	// Icon File Cache Directory
define('TWICON_LIST_PER_PAGE', 40);		// Comment Author Information
define('TWICON_ANALYZE', false);		// Twitter Information Search

define('TWICON_STATUS', 'http://twitter.com/users/');
define('TWICON_HOST',   's3.amazonaws.com');
define('TWICON_STATIC', 'static.twitter.com');

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
	var $plugin_name = 'twicon';
	var $plugin_ver  = '1.3.0';
	var $plugin_dir, $plugin_file;
	var $textdomain_name;

	var $avatars = array();
	var $emails  = array();
	var $options = array();
	var $cache = false;

	var $admin_hook = array();
	var $admin_action;

	var $_meta_value = array();
	var $_cache_path = '';
	var $_cache_url = '';
	var $_avatar_update = false;
	var $_suffix = array(
		 'mini'   => '_mini'
		,'normal' => '_normal'
		,'big'    => '_bigger'
		);
	var $_search_failure = array(
		 'twitter' => 0
		,'google' => 0
		,'yahoo' => 0
		,'msn' => 0
		);
	var $_twitter_info = array();

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

		$this->setPluginDir(__FILE__);
		$this->loadTextdomain('languages');

		list($this->avatars, $this->emails, $this->options) = $this->_init_options();
	}

	// init options
	function _init_options(){
		$avatars = (array) get_option('twicon');
		$emails  = (array) get_option('twicon emails');
		$options = (array) get_option('twicon options');

		if (count($emails) <= 0) {
			$options['expired'] = time() + 60 * 60;
			$emails = $this->_get_qc_twitter_info($emails);
			$emails = $this->_get_comment_authors($emails);
			ksort($emails);
			update_option('twicon emails',  $emails);
			update_option('twicon options', $options);
		}

		if (defined('TWICON_CACHE') && TWICON_CACHE) {
			$this->_cache_path = $this->contentDir( TWICON_CACHE_DIR );
			$this->_cache_url  = $this->contentUrl( TWICON_CACHE_DIR );
			$mode = 0777;
			if( !file_exists( dirname($this->_cache_path) ) )
				@mkdir( dirname($this->_cache_path), $mode );
			if( !file_exists($this->_cache_path) )
				@mkdir( $this->_cache_path, $mode );
			$this->cache = file_exists($this->_cache_path);
		}

		return array($avatars, $emails, $options);
	}

	//**************************************************************************************
	// plugin activation
	//**************************************************************************************
	function activation(){
	}

	//**************************************************************************************
	// plugin deactivation
	//**************************************************************************************
	function deactivation(){
		delete_option('twicon');
//		delete_option('twicon emails');
		delete_option('twicon options');
	}

	// setPluginDir
	function setPluginDir( $file ) {
		$file_path = $file;
		$filename = explode("/", $file_path);
		if(count($filename) <= 1) $filename = explode("\\", $file_path);
		$this->plugin_dir  = $filename[count($filename) - 2];
		$this->plugin_file = $filename[count($filename) - 1];
		unset($filename);
	}

	// loadTextdomain
	function loadTextdomain( $sub_dir = '' ) {
		global $wp_version;

		$this->textdomain_name = ( !empty($this->plugin_name) ? $this->plugin_name : $this->plugin_dir );
		$plugins_dir = trailingslashit( defined('PLUGINDIR') ? PLUGINDIR : 'wp-content/plugins' );
		$textdomain_dir = trailingslashit( trailingslashit($this->plugin_dir) . $sub_dir );

		if (version_compare($wp_version, "2.6", ">=") && defined('WP_PLUGIN_DIR'))
			load_plugin_textdomain($this->textdomain_name, false, $textdomain_dir);
		else
			load_plugin_textdomain($this->textdomain_name, $plugins_dir . $textdomain_dir);
	}

	// contentDir
	function contentDir($path = '') {
		return trailingslashit(trailingslashit(!defined('WP_CONTENT_DIR')
			? WP_CONTENT_DIR
			: trailingslashit(ABSPATH) . 'wp-content'
			) . preg_replace('/^\//', '', $path));
	}

	// contentUrl
	function contentUrl($path = '') {
		return trailingslashit(trailingslashit(!defined('WP_CONTENT_URL')
			? WP_CONTENT_URL
			: trailingslashit(get_option('siteurl')) . 'wp-content'
			) . preg_replace('/^\//', '', $path));
	}

	// pluginsDir
	function pluginsDir($path = '') {
		return trailingslashit($this->contentDir( 'plugins/' . preg_replace('/^\//', '', $path) ));
	}

	// pluginsUrl
	function pluginsUrl($path = '') {
		return trailingslashit($this->contentUrl( 'plugins/' . preg_replace('/^\//', '', $path) ));
	}

	// Function updateAvatars
	function updateAvatars() {
		if ( $this->_avatar_update )
			update_option('twicon', $this->avatars);
	}

	// Function getAvatar
	function getAvatar($avatar, $id_or_email, $size = '96', $default = '', $alt = false) {
		global $pagenow;

		$avatar = str_replace("'", '"', $avatar);
		$avatar = preg_replace(
			  '/^(<img [^>]*) (height|width)=[\'"]([\d]+)[\'"] (height|width)=[\'"]([\d]+)[\'"]([^>]*\/>)$/i'
			, '$1 style="$2:$3px;$4:$5px;"$6'
			, $avatar);

		if($pagenow == 'options-discussion.php')
			return $avatar;

		$result = $this->_get_twicon_url($id_or_email, $size);

		// If User has twitter
		if( isset($result['profile_image_url']) && $result['profile_image_url'] !== false && !empty($result['profile_image_url']) ) {
			$safe_alt = ( false === $alt ? '': attribute_escape( $alt ));

			$img_url     = $result['profile_image_url'];
			$expired     = $result['expiration_date'];
			$name        = (isset($result['name']) ? $result['name'] : '');
			$twitter_url = (isset($result['twitter_url']) ? $result['twitter_url'] : '');

			if ( strpos($img_url, TWICON_STATIC) === false ) {
				if ( $this->cache ) {
					$cache_file_name = $this->_cache_file_name($img_url, $size);
					if ( $this->_cache_file_exists($this->_cache_path . $cache_file_name, $expired) )
						$img_url = $this->_cache_url . $cache_file_name;
					else
						$img_url = $this->pluginsUrl( basename( dirname(__FILE__) ) )
							. basename(__FILE__)
							. '?url=' . base64_encode($img_url)
							. '&amp;size=' . $size;
				}

				$avatar = preg_replace('/^(<img.*src=[\'"])[^\'"]*([\'"].*\/>)$/i', '$1' . $img_url . '$2', $avatar);
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

		if ($image === false && file_exists($cache_file)) {
			$image = imagecreatefrompng($cache_file);
		} else {
			$id = $this->_get_avatar_id($img_url);
			list($profile_image_url, $name, $screen_name, $twitter_url, $url) = $this->getTwitterInfo($id);
			$expiration_date   = $this->_expiration_date();
			$this->avatars[$id] = compact('profile_image_url', 'expiration_date', 'name', 'twitter_url');
			update_option('twicon', $this->avatars);

			$cache_file = $this->_cache_path . $this->_cache_file_name($profile_image_url, $img_size);
			$image = $this->_get_resize_image($profile_image_url, $img_size, $cache_file);
		}

		if ($image !== false) {
			header('Content-Type: image/png');
			header('Expires: '.gmdate('D, d M Y H:i:s', $expired).' GMT');
			imagepng($image);
			imagedestroy($image);
		} else {
			header("HTTP/1.0 404 Not Found");
		}
	}

	function _request($url, $timeout = 5) {
		$snoopy = new Snoopy;
		$snoopy->read_timeout = $timeout;
		$snoopy->timed_out = true;
		$snoopy->fetch($url);
		$response  = $snoopy->results;
		$http_code = $snoopy->response_code;
		unset($snoopy);

		return (strpos($http_code, '200') !== FALSE ? $response : false);
	}

	// Function getTwitterInfo
	function getTwitterInfo($id, $request_url = '') {
		$profile_image_url = false;
		$name              = '';
		$screen_name       = '';
		$twitter_url       = '';
		$url               = '';

		$twitter_ID        = ( strpos($id, '@') === false ? $id : '' );
		if ( !empty($twitter_ID) && $this->_search_failure['twitter'] < 5 ) {
			$request_url = ( empty($request_url)
				? ( !empty($twitter_ID) ? TWICON_STATUS . "show/{$twitter_ID}.xml" : '' )
				: $request_url
				);

			if ( isset($this->_twitter_info[$twitter_ID]) ) {
				$twitter_info = $this->_twitter_info[$twitter_ID];
				list($profile_image_url, $name, $screen_name, $twitter_url, $author_url) = $twitter_info;
			}

			if ( !empty($request_url) && ( empty($profile_image_url) || $profile_image_url === false ) ) {
				$response = $this->_request($request_url, 15);
				if($response !== FALSE) {
					if (preg_match_all('/<(name|screen_name|profile_image_url|url)>([^<]*)<\/(name|screen_name|profile_image_url|url)>/i', $response, $matches, PREG_SET_ORDER)) {
						foreach ((array) $matches as $match) {
							switch (strtolower($match[1])) {
							case 'name':
								$name = $match[2];
								break;
							case 'screen_name':
								$screen_name = $match[2];
								$twitter_url = 'http://twitter.com/' . $screen_name;
								break;
							case 'profile_image_url':
								$profile_image_url = $match[2];
								break;
							case 'url':
								$url = $match[2];
								break;
							}
						}
						unset($match);
					}
					unset($matches);
					$this->_search_failure['twitter'] = 0;
				} else {
					$this->_search_failure['twitter']++;
				}
				$twitter_info = array($profile_image_url, $name, $screen_name, $twitter_url, $url);
				$this->_twitter_info[$twitter_ID] = $twitter_info;
			}

		} else {
			$twitter_info = array($profile_image_url, $name, $screen_name, $twitter_url, $url);
		}

		return $twitter_info;
	}

	// _get_avatar_id
	function _get_avatar_id($img_url) {
		$id = '';
		$find_url = substr($img_url, 0, strrpos($img_url, '_'));
		foreach($this->avatars as $key => $avatar) {
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

		return (!empty($id) && isset($this->avatars[$id])
			? $this->avatars[$id]['expiration_date']
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
		$result = array(
			 'profile_image_url' => false
			,'expiration_date'   => $this->_expiration_date()
			);

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
		if ( empty($email) )
			return $result;

		$twitter_id = $this->_get_twitter_id($post_id, $comment_id, $email);
		$id = ( $twitter_id !== false ? $twitter_id : '' );
		$id_or_email = ( !empty($id) ? $twitter_id : $email );
		$result = $this->_get_twitter_status($id, $id_or_email, $size);

		return $result;
	}

	// Function _get_twitter_id
	function _get_twitter_id($post_id, $comment_id, $email){
		$twitter_id = ( isset($this->emails[$email])
			? $this->emails[$email]['twitter_id']
			: false
			);

		if ( $twitter_id === false ) {
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
		}

		return apply_filters('twitter_id/twicon.php', $twitter_id, $email);
	}

	// Function _get_twitter_id_from_search_engine
	function _get_twitter_id_from_search_engine($request_url, $url, $exclude_ID){
		$pattern = '/href=[\'"]http:\/\/([^\.]*\.)?twitter\.com\/([^\'"\/]*)[\'"]/i';

		$twitter_ID = '';
		$response = $this->_request($request_url);
		$url = trim(untrailingslashit($url));

		if (count($exclude_ID) <= 0) {
			$exclude_ID = array(
				 'hoge'
				,'info'
				,'webmaster'
				);
		}

		if($response !== FALSE) {
			echo " ... ";
			if (preg_match_all($pattern, $response, $matches, PREG_SET_ORDER)) {
				foreach ((array) $matches as $match) {
					$author_url  = '';
					$screen_name = '';
					$twitter_ID  = str_replace('/', '', strtolower($match[2]));
					if ( !empty($twitter_ID) && array_search($twitter_ID, $exclude_ID) === false && preg_match("/^[!-~]+$/" , $twitter_ID) ) {
						$exclude_ID[] = $twitter_ID;
						list($profile_image_url, $name, $screen_name, $twitter_url, $author_url) = $this->getTwitterInfo($twitter_ID);
						echo "$twitter_ID, ";
						$author_url = trim(untrailingslashit($author_url));
					}
					$twitter_ID = (!empty($author_url) && $url == $author_url ? $screen_name : '');
					if (!empty($twitter_ID)) break;
				}
				unset($match);
			}
			echo "<br />\n";
			unset($matches);
			return array($twitter_ID, $exclude_ID);
		} else {
			echo "Failure!<br />\n";
			return array(false, $exclude_ID);
		}
	}

	// Function _get_twitter_id_from_author_info
	function _get_twitter_id_from_author_info($author, $email, $url, $deep_search = false){
		if ( isset($this->emails[$email]) && $this->emails[$email]['twitter_id'] !== false )
			return ($this->emails[$email]['twitter_id']);

		$twitter_ID = '';
		$url = untrailingslashit('http://' != $url ? $url : '');
		$email = ( !empty($email) && preg_match('/^([a-z0-9+_]|\-|\.)+@(([a-z0-9_]|\-)+\.)+[a-z]{2,6}$/i', $email) ? $email : '');

		$exclude_ID = array(
			 'hoge'
			,'info'
			,'webmaster'
			);

		echo "<div>\n";
		if ( !empty($url) && !empty($email) ) {
			echo "<p><strong>*** $author - $url ***</strong> <br />\n";

			if ( !$deep_search ) {
				// Author Name Check
				if ( empty($twitter_ID) ) {
					$twitter_ID = preg_replace('/^([^ ]*).*$/', '$1', $author );
					if ( !empty($twitter_ID) && array_search($twitter_ID, $exclude_ID) === false && preg_match("/^[!-~]+$/" , $twitter_ID) ) {
						$exclude_ID[] = $twitter_ID;
						list($profile_image_url, $name, $screen_name, $twitter_url, $author_url) = $this->getTwitterInfo($twitter_ID);
						$author_url = untrailingslashit(trim($author_url));
						$twitter_ID = (!empty($author_url) && $url == $author_url ? $screen_name : '');
					} else {
						$twitter_ID = '';
					}
				}

				// Mail Address Check
				if ( empty($twitter_ID) ) {
					$twitter_ID = preg_replace( '/@.*$/' , '', $email);
					if ( !empty($twitter_ID) && array_search($twitter_ID, $exclude_ID) === false && preg_match("/^[!-~]+$/" , $twitter_ID) ) {
						$exclude_ID[] = $twitter_ID;
						list($profile_image_url, $name, $screen_name, $twitter_url, $author_url) = $this->getTwitterInfo($twitter_ID);
						$author_url = untrailingslashit(trim($author_url));
						$twitter_ID = (!empty($author_url) && $url == $author_url ? $screen_name : '');
					} else {
						$twitter_ID = '';
					}
				}

			} else {
				$search_url = $url;
				$search_url = str_replace('http://www.', '', $search_url);
				$search_url = str_replace('http://', '', $search_url);
				$search_url = str_replace('/', '+', $search_url);
				$search_word = 'site%3Atwitter.com'
					. '+' . str_replace(' ', '+', $this->_utf8_uri_encode($author))
					. '+' . substr($search_url, 0, 10);

				// Google Search
				if ( empty($twitter_ID) && $this->_search_failure['google'] < 5 ) {
					echo "Google: ";
					$request_url = 
						  'http://www.google.com/search'
						. '?hl=' . (defined('WPLANG') ? WPLANG : 'ja')
						. '&q=' . $search_word;
					list($twitter_ID, $exclude_ID) = $this->_get_twitter_id_from_search_engine($request_url, $url, $exclude_ID);
					if ($twitter_ID !== false) {
						$this->_search_failure['google'] = 0;
					} else {
						$twitter_ID = '';
						$this->_search_failure['google']++;
					}
				}

				// Yahoo Search
				if ( empty($twitter_ID) && $this->_search_failure['yahoo'] < 5 ) {
					echo "Yahoo!: ";
					$request_url =
						  'http://search.yahoo.com/search'
						. '?ei=' . get_option('blog_charset')
						. '&p=' . $search_word;
					list($twitter_ID, $exclude_ID) = $this->_get_twitter_id_from_search_engine($request_url, $url, $exclude_ID);
					if ($twitter_ID !== false) {
						$this->_search_failure['yahoo'] = 0;
					} else {
						$twitter_ID = '';
						$this->_search_failure['yahoo']++;
					}
				}

				// MSN Search
				if ( empty($twitter_ID) && $this->_search_failure['msn'] < 5 ) {
					echo "MSN: ";
					$request_url =
						  'http://search.msn.com/results.aspx'
						. '?q=' . $search_word;
					list($twitter_ID, $exclude_ID) = $this->_get_twitter_id_from_search_engine($request_url, $url, $exclude_ID);
					if ($twitter_ID !== false) {
						$this->_search_failure['msn'] = 0;
					} else {
						$twitter_ID = '';
						$this->_search_failure['msn']++;
					}
				}

			}
			if (!empty($twitter_ID)) echo "Hit! -- $name ($twitter_ID) - $url<br />\n";
			echo "</p>\n";
		}
		echo "</div>\n";
		unset($exclude_ID);

		return ( !empty($twitter_ID) ? $twitter_ID : false );
	}

	// Function _get_qc_twitter_info
	function _get_qc_twitter_info($twicon_emails) {
		global $wpdb;

		if (!is_array($twicon_emails))
			$twicon_emails = array();
		if (!defined('QC_NOTIFY_TWITTER'))
			return $twicon_emails;

		$meta_list = $wpdb->get_results(
			"SELECT post_id, meta_key, meta_value"
			. " FROM {$wpdb->postmeta}"
			. " WHERE meta_key = '" . QC_NOTIFY_TWITTER . "'"
			. " ORDER BY post_id"
			, ARRAY_A);

		foreach ( (array) $meta_list as $metarow) {
			$mpid = (int) $metarow['post_id'];
			$mkey = $metarow['meta_key'];
			$mval = maybe_unserialize(maybe_unserialize($metarow['meta_value']));
			if ( is_array($mval) ) {
				foreach ($mval as $twitter_ID => $comments) {
					if ( !empty($twitter_ID) ) {
						foreach ($comments as $cid) {
							$comment = get_comment($cid);
							$author  = $comment->comment_author;
							$email   = $comment->comment_author_email;
							$url     = $comment->comment_author_url;
							$twicon_emails[$email]['author']     = $author;
							$twicon_emails[$email]['email']      = $email;
							$twicon_emails[$email]['url']        = $url;
							$twicon_emails[$email]['twitter_id'] = (!empty($twitter_ID) ? $twitter_ID : false);
							unset($comment);
						}
					}
				}
				unset($comments);
			}
			unset($mval);
		}
		unset($metarow); unset($meta_list);

		return $twicon_emails;
	}

	// Function _get_comment_authors
	function _get_comment_authors($emails) {
		global $wpdb;

		if (!is_array($emails))
			$emails = array();

		$wk_emails = array();
		foreach ($emails as $key => $val) {
			if ( $val['twitter_id'] !== false && !empty($val['twitter_id']) && $val['count'] > 0 ) {
				$wk_emails[$key] = $val;
				$wk_emails[$key]['count'] = 0;
			}
		}
		$emails = $wk_emails;

		$meta_list = $wpdb->get_results(
			  "SELECT"
			. "   comment_author_email"
			. "  ,comment_author_url"
			. "  ,MAX(comment_author) as comment_author"
			. "  ,COUNT(comment_ID) as comment_count"
			. "  ,MAX(comment_post_ID) as comment_post_ID"
			. "  ,MAX(comment_ID) as comment_ID"
			. "  ,MAX(comment_date) as comment_date"
			. " FROM"
			. "  {$wpdb->comments}"
			. " WHERE"
			. "  comment_type != 'trackback'"
			. "  and comment_type != 'pingback'"
//			. "  and comment_author != ''"
			. "  and ( comment_author_email != '' or comment_author_url != '' )"
			. "  and comment_approved = 1"
			. " GROUP BY"
			. "  comment_author_email"
			. " ,comment_author_url"
			, ARRAY_A);

		foreach ( (array) $meta_list as $meta_row) {
			$author = trim($meta_row['comment_author']);
			$email  = trim($meta_row['comment_author_email']);
			$url    = trim('http://' != $meta_row['comment_author_url'] ? $meta_row['comment_author_url'] : '');
			$count  = (int) $meta_row['comment_count'];

			$ptime  = date('G', strtotime( $meta_row['comment_date'] ) );
			$ptime  = ( abs(time() - $ptime) < 86400
				? sprintf( __('%s ago'), human_time_diff( $ptime ) )
				: mysql2date(__('Y/m/d \a\t g:i A'), $meta_row['comment_date'] )
				);
			$post_id = $meta_row['comment_post_ID'];
			$comment_id = $meta_row['comment_ID'];
			$plink  = get_permalink($post_id) . '#comment-' . $comment_id;
			$last   = "<a href=\"{$plink}\">{$ptime}</a>";

			$key_url= str_replace('http://', '', str_replace('http://www.', '', untrailingslashit($url)));
			$key    = (!empty($email) ? $email : $key_url);

			if ( !empty($key)) {
				$twitter_ID = $this->_get_twitter_id($post_id, $comment_id, $email);
				$emails[$key]['author']       = $author;
				$emails[$key]['email']        = $email;
				$emails[$key]['url']          = $url;
				$emails[$key]['twitter_id']   = (!empty($twitter_ID) ? $twitter_ID : false);
				$emails[$key]['count']        = (isset($emails[$key]['count']) ? $emails[$key]['count'] : 0) + $count;
				$emails[$key]['last_comment'] = $last;
			}
		}
		unset($meta_row); unset($meta_list);

		return $emails;
	}

	// Function _get_twicon_emails
	function _get_twicon_emails($twicon_emails, $deep_search = false) {
		$meta_list = (!is_array($twicon_emails)
			? $this->_get_comment_authors($twicon_emails)
			: $twicon_emails
			);

		foreach ( (array) $meta_list as $key => $meta_row) {
			$author = $meta_row['author'];
			$email  = $meta_row['email'];
			$url    = $meta_row['url'];
			if ( ! (isset($twicon_emails[$email]) && $twicon_emails[$email]['twitter_id'] !== false) ) {
				$twitter_ID = $this->_get_twitter_id_from_author_info($author, $email, $url, $deep_search);
				$twicon_emails[$key]['author']     = $author;
				$twicon_emails[$key]['email']      = $email;
				$twicon_emails[$key]['url']        = $url;
				$twicon_emails[$key]['twitter_id'] = (!empty($twitter_ID) ? $twitter_ID : false);
			}
		}
		unset($meta_row); unset($meta_list);

		return $twicon_emails;
	}

	// Function _get_twitter_status
	function _get_twitter_status($id, $id_or_email = '', $size = '96'){
		$profile_image_url = false;
		$expiration_date   = $this->_expiration_date();
		$name              = '';
		$twitter_url       = '';
		if (empty($id_or_email)) $id_or_email = $id;

		if ( !empty($id) ) {
			if ( isset($this->avatars[$id]) && isset($this->avatars[$id]['expiration_date']) && time() < $this->avatars[$id]['expiration_date'] ) {
				$profile_image_url = $this->_unhtmlentities(isset($this->avatars[$id]['profile_image_url']) ? $this->avatars[$id]['profile_image_url'] : '');
				$expiration_date   = $this->avatars[$id]['expiration_date'];
				$name              = (isset($this->avatars[$id]['name']) ? $this->avatars[$id]['name'] : '');
				$twitter_url       = (isset($this->avatars[$id]['twitter_url']) ? $this->avatars[$id]['twitter_url'] : '');
				if ( empty($profile_image_url) || $profile_image_url === false ) {
					list($profile_image_url, $name, $screen_name, $twitter_url, $url) = $this->getTwitterInfo($id);
					$this->_avatar_update = true;
				}

			} else {
				list($profile_image_url, $name, $screen_name, $twitter_url, $url) = $this->getTwitterInfo($id);
				$this->_avatar_update = true;
			}

			if ( strpos($profile_image_url, TWICON_HOST) === false )
				$profile_image_url = false;

			if ( $profile_image_url !== false ) {
				if ( strpos($profile_image_url, TWICON_STATIC) === false ) {
					if ( preg_match('/^(https?:\/\/.*)(_[^\/\._]*)(\.(jpe?g|gif|png))$/i', $profile_image_url, $matches) ) {
						$size = (int) ( is_numeric($size) ? $size : '96' );
						if ($size <= 24)
							$suffix = $this->_suffix['mini'];
						elseif ($size <= 48)
							$suffix = $this->_suffix['normal'];
						else
							$suffix = $this->_suffix['big'];
						$profile_image_url = $matches[1] . $suffix . $matches[3];
					}
					unset($matches);
					$profile_image_url = str_replace('https://' . TWICON_HOST .'/', 'http://' . TWICON_HOST . '/', $profile_image_url);

					// Icon Image URL percent encoding
					$image_url_encode  = trim($this->_utf8_uri_encode($profile_image_url));
					if ( !empty($image_url_encode) && $profile_image_url != $image_url_encode )
						$profile_image_url = str_replace(array($this->_suffix['mini'], $this->_suffix['normal'], $this->_suffix['big']), '', $image_url_encode);
				} else {
					$profile_image_url = false;
				}
			}
		}
		$profile_image_url = apply_filters('profile_image_url/twicon.php', $profile_image_url, $id_or_email, $size);

		$result = compact('profile_image_url', 'expiration_date', 'name', 'twitter_url');
		$this->avatars[$id] = $result;

		return $result;
	}

	// Function _get_resize_image
	function _get_resize_image($img_url, $img_size = 96, $cache_file = '') {
		$imgbin = $this->_request($img_url, 10);
		if($imgbin === false) return false;

		$img_resized = imagecreatetruecolor($img_size, $img_size);
		$bgc = imagecolorallocate($img_resized, 255, 255, 255);
		imagefilledrectangle($img_resized, 0, 0, $img_size, $img_size, $bgc);

		$img = @imagecreatefromstring($imgbin);
		if($img === false)
			return ( !file_exists($cache_file) ? $img_resized : false );

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

	// Function _unhtmlentities
	function _unhtmlentities($string){
		$encoding = strtolower(mb_detect_encoding($string));
		if (!preg_match("/^utf/", $encoding) and $encoding != 'ascii')
			return $string;

		$excluded_hex = $string;
		if (preg_match("/&#[xX][0-9a-zA-Z]{2,8};/", $string))
			$excluded_hex = preg_replace("/&#[xX]([0-9a-zA-Z]{2,8});/e", "'&#'.hexdec('$1').';'", $string);

		return mb_decode_numericentity($excluded_hex, array(0x0, 0x10000, 0, 0xfffff), "utf-8");
	}

	// ==================================================
	// based on utf8_uri_encode() at formatting.php from WordPress 2.7.1
	function _utf8_uri_encode( $utf8_string, $length = 0 ) {
		$unicode = '';
		$values = array();
		$num_octets = 1;
		$unicode_length = 0;

		$string_length = strlen( $utf8_string );
		for ($i = 0; $i < $string_length; $i++ ) {

			$value = ord( $utf8_string[ $i ] );

			if ( $value < 128 ) {
				if ( $length && ( $unicode_length >= $length ) )
					break;
				$unicode .= chr($value);
				$unicode_length++;
			} else {
				if ( count( $values ) == 0 ) $num_octets = ( $value < 224 ) ? 2 : 3;

				$values[] = $value;

				if ( $length && ( $unicode_length + ($num_octets * 3) ) > $length )
					break;
				if ( count( $values ) == $num_octets ) {
					if ($num_octets == 3) {
						$unicode .= '%' . dechex($values[0]) . '%' . dechex($values[1]) . '%' . dechex($values[2]);
						$unicode_length += 9;
					} else {
						$unicode .= '%' . dechex($values[0]) . '%' . dechex($values[1]);
						$unicode_length += 6;
					}

					$values = array();
					$num_octets = 1;
				}
			}
		}

		return $unicode;
	}

	// Set Twitter Info
	function setTwitterInfo($comment_id, $comment_approved = '') {
		global $wpdb, $comment_author_twitter_ID;

		if (empty($comment_approved)) $comment_approved = '1';
		if ('1' != $comment_approved) return;

		$comment = get_comment($comment_id);
		$comment_author = $comment->comment_author;
		$comment_author_email = $comment->comment_author_email;
		$comment_author_url = $comment->comment_author_url;
		if (!isset($comment_author_twitter_ID))
			$comment_author_twitter_ID = $wpdb->escape(trim($_POST['twitterID']));

		if (!empty($comment_author_twitter_ID)) {
			setcookie('comment_author_twitter_' . COOKIEHASH, $comment_author_twitter_ID, time() + 30000000, COOKIEPATH, COOKIE_DOMAIN);
			$this->emails[$comment_author_email]['author']     = $comment_author;
			$this->emails[$comment_author_email]['email']      = $comment_author_email;
			$this->emails[$comment_author_email]['url']        = $comment_author_url;
			$this->emails[$comment_author_email]['twitter_id'] = $comment_author_twitter_ID;
			update_option('twicon emails', $this->emails);
		}
	}

	// Add Admin Menu
	function addAdminMenu() {
		$parent = 'edit-comments.php';
		$page_title = __('Comment Authors Info', $this->textdomain_name);
		$menu_title = $page_title;
		$file = plugin_basename(__FILE__);
		// User Level Permission -- Subscriber = 0,Contributor = 1,Author = 2,Editor= 7,Administrator = 9
		$capability = 7;
		$this->admin_action =
			  trailingslashit(get_bloginfo('wpurl')) . 'wp-admin/'
			. $parent . '?page=' . $file;
		$this->admin_hook[$parent] = add_submenu_page($parent, $page_title, $menu_title, $capability, $file, array($this,'optionPage'));
	}

	function optionPage() {
		$analyze = ( defined('TWICON_ANALYZE') && TWICON_ANALYZE && isset($_POST['analyze']) );
		$update  = ( time() > $this->options['expired'] || $analyze );

		echo "<div class=\"wrap\">\n";

		// Get Comment Author List
		if ( $update ) {
			$this->emails = $this->_get_qc_twitter_info($this->emails);
			$this->emails = $this->_get_comment_authors($this->emails);
			ksort($this->emails);
		}

		// Analyze
		if ( $analyze )
			$this->emails = $this->_get_twicon_emails($this->emails, isset($_POST['deep']));

		// Options Update
		if ( $update ) {
			$this->options['expired'] = time() + 60 * 60;
			update_option('twicon emails', $this->emails);
			update_option('twicon options', $this->options);
		}

		// Listing
		if ( !$analyze ) {

		// Page Links
		$page = ( isset( $_GET['apage'] )
			? abs( (int) $_GET['apage'] )
			: 1);
		$total = count($this->emails);
		$comment_info_per_page = apply_filters('comment_info_per_page/twicon.php', (defined('TWICON_LIST_PER_PAGE') ? TWICON_LIST_PER_PAGE : 50));
		$start = ($page - 1) * $comment_info_per_page;

		$page_links = paginate_links( array(
			  'base' => add_query_arg( 'apage', '%#%' )
			, 'format' => ''
			, 'prev_text' => __('&laquo;')
			, 'next_text' => __('&raquo;')
			, 'total' => ceil($total / $comment_info_per_page)
			, 'current' => $page
		));

		$page_links_text = sprintf( '<span class="displaying-num">' . __( 'Displaying %s&#8211;%s of %s' ) . '</span>%s',
			number_format_i18n( $start + 1 ),
			number_format_i18n( min( $page * $comment_info_per_page, $total ) ),
			number_format_i18n( $total ),
			$page_links
		);

		$emails = array_slice($this->emails, $start, $comment_info_per_page);

?>
<h2><?php _e('Comment Authors Info', $this->textdomain_name); ?></h2>

<div class="tablenav">
<?php if ( $page_links ) echo "<div class=\"tablenav-pages\">$page_links_text</div>"; ?><br class="clear" />
</div>

<div class="clear"></div>
<table class="widefat comments fixed" cellspacing="0">
<thead>
	<tr>
	<th scope="col" id="author" class="manage-column column-author" style=""><?php _e('Author'); ?></th>
	<th scope="col" id="email" class="manage-column column-email" style=""><?php _e('E-mail'); ?></th>
	<th scope="col" id="url" class="manage-column column-url" style=""><?php _e('URL'); ?></th>
	<th scope="col" id="twitter_id" class="manage-column column-twitter" style=""><?php _e('Twitter ID', $this->textdomain_name); ?></th>
	<th scope="col" id="count" class="manage-column column-count" style="width:100px"><?php _e('Count', $this->textdomain_name); ?></th>
	<th scope="col" id="last-comment" class="manage-column column-comment" style=""><?php _e('Last Comment', $this->textdomain_name); ?></th>
	</tr>

</thead>

<tfoot>
	<tr>
	<th scope="col" class="manage-column column-author" style=""><?php _e('Author'); ?></th>
	<th scope="col" class="manage-column column-email" style=""><?php _e('E-mail'); ?></th>
	<th scope="col" class="manage-column column-url" style=""><?php _e('URL'); ?></th>
	<th scope="col" class="manage-column column-twitter" style=""><?php _e('Twitter ID', $this->textdomain_name); ?></th>
	<th scope="col" class="manage-column column-count" style=""><?php _e('Count', $this->textdomain_name); ?></th>
	<th scope="col" class="manage-column column-comment" style=""><?php _e('Last Comment', $this->textdomain_name); ?></th>
	</tr>

</tfoot>

<tbody id="the-comment-list" class="list:comment">
<?php
			$count = 0;
			foreach ($emails as $key => $val)
				$count = $this->_comment_author_row($key, $val, $count);
?>
</tbody>
</table>

<div class="tablenav">
<?php if ( $page_links ) echo "<div class=\"tablenav-pages\">$page_links_text</div>"; ?>
<?php if ( defined('TWICON_ANALYZE') && TWICON_ANALYZE ) { ?>
<div class="alignleft actions">
<form method="post" id="analyze" action="<?php echo $this->admin_action; ?>">
<p style="margin-top:1em;">
<input type="submit" name="analyze" class="button button-primary" value="<?php _e('Twicon Search', $this->textdomain_name); ?>" />
<input type="checkbox" name="deep" value="on" style="margin-left:2em;" />&nbsp;<?php _e('Deep', $this->textdomain_name); ?>
</p>
</form>
</div>
<?php } ?>
<br class="clear" />
</div>

<?php
		}
		echo "</div>\n";
	}

	// _comment_author_row
	function _comment_author_row($key, $row, $row_num) {
		$avatar = get_avatar($key, 32);

		$author_name = $row['author'];

		$author_url = ( 'http://' != $row['url'] ? $row['url'] : '');
		$author_url_display = untrailingslashit($author_url);
		$author_url_display = str_replace('http://www.', '', $author_url_display);
		$author_url_display = str_replace('http://', '', $author_url_display);

		$author_email = mb_encode_numericentity($row['email'], array(0x0, 0x10000, 0, 0xfffff), get_option('blog_charset'));

		$twitter_id = trim($row['twitter_id'] !== false ? $row['twitter_id'] : '');

		$comment_count = (int) $row['count'];

		$last_comment = $row['last_comment'];

		if ($comment_count > 0) {
			$row_num++;

			echo "<tr id=\"author-{$row_num}\">";
			echo "<td class=\"author column-author\"><span style=\"margin-left:.5em;\">{$avatar}</span><strong>{$author_name}</strong></td>" ;
			echo "<td class=\"column-email\"><a href=\"mailto:{$author_email}\" title=\"" . sprintf( __('e-mail: %s' ), $author_email ) . "\">{$author_email}</a></td>" ;
			echo "<td class=\"column-url\"><a title=\"{$author_url}\" href=\"{$author_url}\">{$author_url_display}</a></td>" ;
			echo "<td class=\"column-twitter\">";
			if (!empty($twitter_id))
				echo "<a title=\"{$twitter_id} on Twitter\" href=\"http://twitter.com/{$twitter_id}\">{$twitter_id}</a>";
			echo "</td>" ;
			echo "<td class=\"column-count\" style=\"text-align:right;\">{$comment_count}</td>" ;
			echo "<td class=\"column-comment\">{$last_comment}</td>" ;
			echo "</tr>\n" ;
		}
		return $row_num;
	}

	function getInputBox($size = 22, $tabindex = 4) {
		global $comment_author_twitter_ID;

		$out  =	"<p><input type=\"text\" name=\"twitterID\" id=\"twitterID\" value=\"{$comment_author_twitter_ID}\" size=\"{$size}\" tabindex=\"{$tabindex}\" />\n";
		$out .= "<label for=\"twitterID\"><small>" . __('Twitter ID', $this->textdomain_name) . "</label></small></label></p>\n";

		return $out;
	}
}

/******************************************************************************
 * Go Go Go!
 *****************************************************************************/
global $twicon, $comment_author_twitter_ID;

$twicon = new twiconController();

if ( strpos($_SERVER['PHP_SELF'], basename(__FILE__)) === false ) {
	// Add WordPress Filter
	add_filter('get_avatar', array(&$twicon, 'getAvatar'), 10, 5);
	add_action('shutdown',   array(&$twicon, 'updateAvatars'));

	if (is_admin()) {
		add_action('admin_menu', array(&$twicon,'addAdminMenu'));
	} else {
		add_action('comment_post',   array(&$twicon, 'setTwitterInfo'), 11, 2);
	}

	if (function_exists('register_activation_hook'))
		register_activation_hook(__FILE__, array(&$twicon, 'activation'));
	if (function_exists('register_deactivation_hook'))
		register_deactivation_hook(__FILE__, array(&$twicon, 'deactivation'));

} elseif ( $twicon->cache && isset($_GET['url']) ) {
	// Get Image from Cache
	$img_url  = base64_decode($_GET['url']);
	$img_size = (int) (isset($_GET['size']) ? stripslashes($_GET['size']) : 48);

	$twicon->getImage($img_url, $img_size);
}


/******************************************************************************
 * Twitter ID Input box
 *****************************************************************************/
function twicon_input_box($size = 22, $tabindex = 4) {
	global $twicon;

	echo $twicon->getInputBox($size, $tabindex);
}
?>