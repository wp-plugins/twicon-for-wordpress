=== Twicon for WordPress ===
Contributors: wokamoto
Tags: Avatar, twitter, comments
Requires at least: 2.5
Tested up to: 2.7.1
Stable tag: 1.2.3

コメントしてくれた人のメールアドレスから Twitter アイコン (Twicon) を表示します。

== Description ==

コメントしてくれた人のメールアドレスから Twitter アイコン (Twicon) を表示します。
Twitter アイコンが見つからない場合は、従来どおり Gravatars を表示します。

== Installation ==

1. `/wp-content/plugins/` ディレクトリに `twicon` ディレクトリを作成し、その中にプラグインファイルを格納してください。
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
