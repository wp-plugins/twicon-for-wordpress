=== Twicon for WordPress ===
Contributors: wokamoto
Tags: Avatar, twitter, comments
Requires at least: 2.5
Tested up to: 2.7.1
Stable tag: 1.2.3

�R�����g���Ă��ꂽ�l�̃��[���A�h���X���� Twitter �A�C�R�� (Twicon) ��\�����܂��B

== Description ==

�R�����g���Ă��ꂽ�l�̃��[���A�h���X���� Twitter �A�C�R�� (Twicon) ��\�����܂��B
Twitter �A�C�R����������Ȃ��ꍇ�́A�]���ǂ��� Gravatars ��\�����܂��B

== Installation ==

1. `/wp-content/plugins/` �f�B���N�g���� `twicon` �f�B���N�g�����쐬���A���̒��Ƀv���O�C���t�@�C�����i�[���Ă��������B
�@��ʓI�ɂ� .zip ����W�J���ꂽ twicon �t�H���_�����̂܂܃A�b�v���[�h����� OK �ł��B
2. `/wp-content/` �f�B���N�g���ȉ��� `cache/twicon` �Ƃ����f�B���N�g�����쐬���A�������݌�����^���Ă��������B
3. WordPress �� "�v���O�C��" ���j���[���� "Twicon for WordPress" ��L�������Ă��������B

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
