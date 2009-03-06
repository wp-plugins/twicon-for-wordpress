=== Twicon for WordPress ===
Contributors: wokamoto
Tags: Avatar, twitter, comments
Requires at least: 2.5
Tested up to: 2.7.1
Stable tag: 1.2.3

Let's show the Twitter avatar (Twicon) to your user with those comments of you in the Web site.

== Description ==

Let's show the Twitter avatar (Twicon) to your user with those comments of you in the Web site.
When the Twitter avatar (Twicon) doesn't exist, Gravatar is displayed. 

== Installation ==

1. Upload the entire `twicon` folder to the `/wp-content/plugins/` directory.
2. Please make directory `twicon` under the `/wp-content/cache/` directory.
3. Activate the plugin through the 'Plugins' menu in WordPress.

== Frequently Asked Questions ==

Original filter can be set. 

= E-mail to Twitter ID =
`function set_twitter_id($twitter_id, $email = '') {
	if ( $email == 'hoge@example.com' )
		$twitter_id = 'hoge';
	return $twitter_id;
}
add_filter('twitter_id/twicon.php', 'set_twitter_id', 10, 2);`

= Set profile image =
`function set_profile_image_url($profile_image_url, $id_or_email = '') {
	if ( $id_or_email == 'fuga@example.com' )
		$profile_image_url = get_option('siteurl') . '/images/fuga.png';
	return $profile_image_url;
}
add_filter('profile_image_url/twicon.php', 'set_profile_image_url', 10, 2);`
