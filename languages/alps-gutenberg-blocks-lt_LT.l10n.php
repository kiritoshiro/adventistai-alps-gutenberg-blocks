<?php
// Lithuanian strings for the server-rendered parts of the plugin, in the PHP
// translation format WordPress 6.5+ loads directly (no .mo file needed).
// tests/render-youtube-channel.php checks that every PHP string is listed.
if (! defined('ABSPATH')) {
	exit;
}

return [
	'domain'       => 'alps-gutenberg-blocks',
	'language'     => 'lt_LT',
	'plural-forms' => 'nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && (n%100<10 || n%100>=20) ? 1 : 2);',
	'messages'     => [
		'No books found.' => 'Knygų nerasta.',
		'Untitled book' => 'Knyga be pavadinimo',
		'No cover' => 'Nėra viršelio',
		'YouTube channel block'                                                => 'YouTube kanalo blokas',
		'YouTube Data API key'                                                 => 'YouTube Data API raktas',
		'That is not a YouTube Data API key. The previous key was kept.'       => 'Tai ne YouTube Data API raktas. Paliktas ankstesnis raktas.',
		'Used by the YouTube Channel Videos block on the server only; visitors never see it. Use a key restricted to the YouTube Data API v3. If empty, the WP YouTube plugin\'s key is used.' => 'Jį naudoja tik serveris YouTube kanalo vaizdo įrašų blokui; lankytojai jo nemato. Naudokite raktą, apribotą YouTube Data API v3. Jei laukas tuščias, naudojamas WP YouTube įskiepio raktas.',
		'In use: %s.'                                                          => 'Naudojamas: %s.',
		'No key is set.'                                                       => 'Raktas nenustatytas.',
		'the ALPS_YOUTUBE_API_KEY constant in wp-config.php'                   => 'konstanta ALPS_YOUTUBE_API_KEY faile wp-config.php',
		'Settings → Media'                                                     => 'Nustatymai → Medija',
		'the WPY_YOUTUBE_API_KEY constant in wp-config.php'                    => 'konstanta WPY_YOUTUBE_API_KEY faile wp-config.php',
		'the WP YouTube plugin settings'                                       => 'WP YouTube įskiepio nustatymai',
		'key …%1$s from %2$s'                                                  => 'raktas …%1$s, nustatytas: %2$s',
		'YouTube did not respond'                                              => 'YouTube neatsakė',
		'channel not found'                                                    => 'kanalas nerastas',
		'no API key or channel'                                                => 'nėra API rakto arba kanalo',
		'no videos found'                                                      => 'nerasta vaizdo įrašų',
		'YouTube channel: enter a channel link, @handle or channel ID.'        => 'YouTube kanalas: įveskite kanalo nuorodą, @vardą arba kanalo ID.',
		'YouTube channel: add a YouTube Data API key under Settings → Media.'  => 'YouTube kanalas: įrašykite YouTube Data API raktą skiltyje Nustatymai → Medija.',
		'YouTube channel: the videos could not be loaded (%1$s; %2$s).'        => 'YouTube kanalas: nepavyko įkelti vaizdo įrašų (%1$s; %2$s).',
		'This key has a website restriction in Google Cloud. Set "Application restrictions" to None, or add %s to its websites.' => 'Šis raktas Google Cloud apribotas svetainėmis. „Application restrictions“ nustatykite į None arba pridėkite %s prie leidžiamų svetainių.',
		'Play: %s'                                                             => 'Paleisti: %s',
		'Previous videos'                                                      => 'Ankstesni vaizdo įrašai',
		'Next videos'                                                          => 'Tolesni vaizdo įrašai',
		'YouTube video player'                                                 => 'YouTube vaizdo grotuvas',
		'%s on social media'                                                   => '%s socialiniuose tinkluose',
		'Watch on YouTube'                                                     => 'Žiūrėti „YouTube“',
		'More videos'                                                          => 'Kiti vaizdo įrašai',
	],
];
