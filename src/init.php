<?php
/**
 * Registers the editor assets and the ALPS Latest Posts block.
 *
 * @package ALPS\Gutenberg
 */

if (! defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/latest-posts/class-latest-posts-block.php';

function alps_gutenberg_blocks_init()
{
    $pluginFile = dirname(__DIR__) . '/plugin.php';

    // Editor-only assets; the front end is styled by the ALPS theme.
    wp_register_script(
        'alps-gb',
        plugins_url('dist/blocks.build.js', $pluginFile),
        ['wp-block-editor', 'wp-blocks', 'wp-components', 'wp-core-data', 'wp-data', 'wp-date', 'wp-element', 'wp-html-entities', 'wp-i18n'],
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

    (new \ALPS\Gutenberg\Blocks\LatestPostsBlock())->init();
}
add_action('init', 'alps_gutenberg_blocks_init');
