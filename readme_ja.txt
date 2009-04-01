=== Twicon for WordPress ===
Contributors: wokamoto
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=9S8AJCY7XB8F4&lc=JP&item_name=WordPress%20Plugins&item_number=wp%2dplugins&currency_code=JPY&bn=PP%2dDonationsBF%3abtn_donate_SM%2egif%3aNonHosted
Tags: Avatar, twitter, comments
Requires at least: 2.5
Tested up to: 2.7.1
Stable tag: 1.2.7

コメントしてくれた人のメールアドレスから Twitter アイコン (Twicon) を表示します。

== Description ==

コメントしてくれた人のメールアドレスから Twitter アイコン (Twicon) を表示します。
Twitter アイコンが見つからない場合は、従来どおり Gravatars を表示します。

= Usage =
Twicon または Gravatar をテーマに加えるには、get_avatarと呼ばれる関数を利用します。この関数は、アバターの完全なイメージHTMLタグを返します。

この関数は、以下のように呼び出されます： 

`<?php echo get_avatar( $id_or_email, $size = '96', $default = '<path_to_url>' ); ?>`

参照 [Gravatar の使い方](http://wpdocs.sourceforge.jp/Gravatar_%E3%81%AE%E4%BD%BF%E3%81%84%E6%96%B9 "Gravatar の使い方 - WordPress Codex 日本語版")

== Installation ==

1. `/wp-content/plugins/` ディレクトリに `twicon-for-wordpress` ディレクトリを作成し、その中にプラグインファイルを格納してください。
　一般的には .zip から展開された twicon フォルダをそのままアップロードすれば OK です。
2. `/wp-content/` ディレクトリ以下に `cache/twicon` というディレクトリを作成し、書き込み権限を与えてください。
3. WordPress の "プラグイン" メニューから "Twicon for WordPress" を有効化してください。

== Frequently Asked Questions ==

以下の独自フィルタが使用できます。

= 特定のメールアドレスを Twitter ID に変換 =
例えば、WordPress に登録されている自分のメールアドレスと Twitter に登録されているメールアドレスが異なる場合などに使用してください。

`function set_twitter_id($twitter_id, $email = '') {
	if ( $email == 'hoge@example.com' )
		$twitter_id = 'hoge';
	return $twitter_id;
}
add_filter('twitter_id/twicon.php', 'set_twitter_id', 10, 2);`

= Twitter アカウント、Gravatars アカウントを持たないメールアドレスにもアバターを適用 =
`function set_profile_image_url($profile_image_url, $id_or_email = '') {
	if ( $id_or_email == 'fuga@example.com' )
		$profile_image_url = get_option('siteurl') . '/images/fuga.png';
	return $profile_image_url;
}
add_filter('profile_image_url/twicon.php', 'set_profile_image_url', 10, 2);`
