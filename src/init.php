<?php
/**
 * Registers the editor assets and the plugin's blocks.
 *
 * @package ALPS\Gutenberg
 */

if (! defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/newspaper-posts/class-newspaper-posts-block.php';

require_once __DIR__ . '/latest-posts/class-latest-posts-block.php';
require_once __DIR__ . '/youtube-channel/class-youtube-channel-block.php';
require_once __DIR__ . '/book-showcase/class-book-showcase-block.php';
require_once __DIR__ . '/external-posts/class-external-posts-block.php';

function alps_gutenberg_blocks_init()
{
    $pluginFile = dirname(__DIR__) . '/plugin.php';

    // Lithuanian strings for the front end (languages/*.l10n.php, WordPress 6.5+).
    load_plugin_textdomain('alps-gutenberg-blocks', false, dirname(plugin_basename($pluginFile)) . '/languages');

    // Editor assets. Latest Posts is styled by the ALPS theme; YouTube Channel
    // Videos registers its own front-end files.
    wp_register_script(
        'alps-gb',
        plugins_url('dist/blocks.build.js', $pluginFile),
        ['wp-block-editor', 'wp-blocks', 'wp-components', 'wp-core-data', 'wp-data', 'wp-date', 'wp-element', 'wp-html-entities', 'wp-i18n', 'wp-server-side-render'],
        ALPS_GUTENBERG_VERSION,
        true
    );
    wp_set_script_translations('alps-gb', 'alps-gutenberg-blocks');

    wp_register_style(
        'alps-gb-editor',
        plugins_url('dist/blocks.editor.build.css', $pluginFile),
        ['wp-edit-blocks'],
        ALPS_GUTENBERG_VERSION
    );

    (new \ALPS\Gutenberg\Blocks\NewspaperPostsBlock())->init();
    (new \ALPS\Gutenberg\Blocks\LatestPostsBlock())->init();
    (new \ALPS\Gutenberg\Blocks\YouTubeChannelBlock())->init();
    (new \ALPS\Gutenberg\Blocks\BookShowcaseBlock())->init();
    (new \ALPS\Gutenberg\Blocks\ExternalPostsBlock())->init();
}
add_action('init', 'alps_gutenberg_blocks_init');
