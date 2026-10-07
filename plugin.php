<?php
/**
 * Plugin Name: ALPS Gutenberg Blocks
 * Plugin URI: https://github.com/kiritoshiro/adventistai-alps-gutenberg-blocks
 * Description: The ALPS Latest Posts and YouTube Channel Videos blocks for the ALPS v3 theme.
 * Author: Seventh-day Adventist Church
 * Author URI: https://adventist.io/themes
 * Version: 3.1.1
 * Requires at least: 6.3
 * Requires PHP: 7.4
 * Text Domain: alps-gutenberg-blocks
 * Update URI: https://github.com/kiritoshiro/adventistai-alps-gutenberg-blocks
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define('ALPS_GUTENBERG_VERSION', '3.1.1');
define('ALPS_GUTENBERG_NAME', 'alps-gutenberg-blocks');

require_once __DIR__ . '/updater.php';
// Updates come from this fork's GitHub releases, never from upstream's CDN.
// The Update URI header also stops WordPress.org offering a same-named plugin.
(new \ALPS\Gutenberg\PluginUpdater(ALPS_GUTENBERG_NAME, ALPS_GUTENBERG_VERSION))->init();

require_once __DIR__ . '/src/init.php';
