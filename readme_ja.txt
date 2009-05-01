=== Twicon for WordPress ===
Contributors: wokamoto
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=9S8AJCY7XB8F4&lc=JP&item_name=WordPress%20Plugins&item_number=wp%2dplugins&currency_code=JPY&bn=PP%2dDonationsBF%3abtn_donate_SM%2egif%3aNonHosted
Tags: Avatar, twitter, comments
Requires at least: 2.5
Tested up to: 2.7.1
Stable tag: 1.3.0

�R�����g���Ă��ꂽ�l��Twitter�A�J�E���g����Twitter�A�C�R��(Twicon)��\�����܂��B

== Description ==
�R�����g���Ă��ꂽ�l��Twitter�A�J�E���g����Twitter�A�C�R��(Twicon)��\�����܂��B
Twitter �A�C�R����������Ȃ��ꍇ�́A�]���ǂ��� Gravatars ��\�����܂��B

= Usage =
Twicon �܂��� Gravatar ���e�[�}�ɉ�����ɂ́Aget_avatar�ƌĂ΂��֐��𗘗p���܂��B���̊֐��́A�A�o�^�[�̊��S�ȃC���[�WHTML�^�O��Ԃ��܂��B

���̊֐��́A�ȉ��̂悤�ɌĂяo����܂��F 

`<?php echo get_avatar( $id_or_email, $size = '96', $default = '<path_to_url>' ); ?>`

�Q�� [Gravatar �̎g����](http://wpdocs.sourceforge.jp/Gravatar_%E3%81%AE%E4%BD%BF%E3%81%84%E6%96%B9 "Gravatar �̎g���� - WordPress Codex ���{���")

= Localization =
"Twicon for WordPress" ���e����ɖ|�󂵂Ă������������X�Ɋ��ӂ����߂āB

* Belorussian (by) - <a href="http://www.fatcow.com" title="Marcis Gasuns" rel="nofollow">Marcis Gasuns</a>
* Japanese (ja) - <a href="http://dogmap.jp/" title="Wataru OKAMOTO">Wataru OKAMOTO</a> (plugin author)

== Installation ==

1. `/wp-content/plugins/` �f�B���N�g���� `twicon-for-wordpress` �f�B���N�g�����쐬���A���̒��Ƀv���O�C���t�@�C�����i�[���Ă��������B
�@��ʓI�ɂ� .zip ����W�J���ꂽ twicon �t�H���_�����̂܂܃A�b�v���[�h����� OK �ł��B
2. `/wp-content/` �f�B���N�g���ȉ��� `cache/twicon` �Ƃ����f�B���N�g�����쐬���A�������݌�����^���Ă��������B
3. WordPress �� "�v���O�C��" ���j���[���� "Twicon for WordPress" ��L�������Ă��������B
4. �e�[�}�� `comment.php` �Ɉȉ��̃e���v���[�g�^�O��ǉ����Ă��������B
`<?php if (function_exists('twicon_input_box')) twicon_input_box(); ?>`

== Frequently Asked Questions ==

�ȉ��̓Ǝ��t�B���^���g�p�ł��܂��B

= ����̃��[���A�h���X�� Twitter ID �ɕϊ� =
�Ⴆ�΁AWordPress �ɓo�^����Ă��鎩���̃��[���A�h���X�� Twitter �ɓo�^����Ă��郁�[���A�h���X���قȂ�ꍇ�ȂǂɎg�p���Ă��������B

`function set_twitter_id($twitter_id, $email = '') {
	if ( $email == 'hoge@example.com' )
		$twitter_id = 'hoge';
	return $twitter_id;
}
add_filter('twitter_id/twicon.php', 'set_twitter_id', 10, 2);`

= Twitter �A�J�E���g�AGravatars �A�J�E���g�������Ȃ����[���A�h���X�ɂ��A�o�^�[��K�p =
`function set_profile_image_url($profile_image_url, $id_or_email = '') {
	if ( $id_or_email == 'fuga@example.com' )
		$profile_image_url = get_option('siteurl') . '/images/fuga.png';
	return $profile_image_url;
}
add_filter('profile_image_url/twicon.php', 'set_profile_image_url', 10, 2);`
