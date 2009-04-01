=== Twicon for WordPress ===
Contributors: wokamoto
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=9S8AJCY7XB8F4&lc=JP&item_name=WordPress%20Plugins&item_number=wp%2dplugins&currency_code=JPY&bn=PP%2dDonationsBF%3abtn_donate_SM%2egif%3aNonHosted
Tags: Avatar, twitter, comments
Requires at least: 2.5
Tested up to: 2.7.1
Stable tag: 1.2.7

Let's show the Twitter avatar (Twicon) to your user with those comments of you in the Web site.

== Description ==

Let's show the Twitter avatar (Twicon) to your user with those comments of you in the Web site.
When the Twitter avatar (Twicon) doesn't exist, Gravatar is displayed. 

= Usage =
The function to add Twicon or Gravatars to your theme is called: get_avatar. The function returns a complete image HTML tag of the Avatar.

The function is called as follows: 

`<?php echo get_avatar( $id_or_email, $size = '96', $default = '<path_to_url>' ); ?>`

See more. [Using Gravatars](http://codex.wordpress.org/Using_Gravatars "Using Gravatars < WordPress Codex")

== Installation ==

1. Upload the entire `twicon-for-wordpress` folder to the `/wp-content/plugins/` directory.
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
