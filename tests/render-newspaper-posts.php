<?php
define('ABSPATH', __DIR__);
$GLOBALS['fixture_posts'] = [(object) ['ID' => 2]];
$GLOBALS['excerpt'] = 'News &amp; updates […]';
$GLOBALS['protected'] = false;
$GLOBALS['post'] = (object) ['ID' => 999];
class WP_Query { public $posts; public function __construct($args) { $GLOBALS['last_query'] = $args; $this->posts = $GLOBALS['fixture_posts']; } }
function absint($v) { return abs((int) $v); }
function sanitize_text_field($s) { return trim(strip_tags($s)); }
function wp_enqueue_style($s) { $GLOBALS['enqueued_style'] = $s; }
function get_block_wrapper_attributes($args) { return 'class="' . esc_attr($args['class']) . '"'; }
function get_permalink($id) { return 'https://example.test/?p=' . $id; }
function get_the_title($id) { return 'Title <script> & "quote"'; }
function has_post_thumbnail($id) { return true; }
function get_the_post_thumbnail($id, $size) { $GLOBALS['thumb_size'] = $size; return '<img src="image.jpg" alt="" width="300" height="200">'; }
function get_the_date($format, $id) { return $format === 'c' ? '2026-10-08T10:00:00+03:00' : 'October 8, 2026'; }
function post_password_required($id) { return $GLOBALS['protected']; }
function get_the_excerpt($id) { $GLOBALS['excerpt_id'] = $id; return $GLOBALS['excerpt']; }
function get_bloginfo($key) { return 'UTF-8'; }
function wp_strip_all_tags($s) { return strip_tags($s); }
function esc_html($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function esc_attr($s) { return esc_html($s); }
function esc_url($s) { return esc_html($s); }
function esc_html__($s, $d) { return esc_html($s); }
require dirname(__DIR__) . '/src/newspaper-posts/class-newspaper-posts-block.php';
$block = new \ALPS\Gutenberg\Blocks\NewspaperPostsBlock();
$checks = 0;
function check($condition, $label) { global $checks; ++$checks; if (! $condition) { fwrite(STDERR, 'FAIL ' . $label . "\n"); exit(1); } }
foreach ([1, true, ' TRUE ', 'yes', 'On'] as $value) { check($block::boolValue($value), 'truthy boolean'); }
foreach ([0, false, 'false', 'off', 'no', '', [], null] as $value) { check(! $block::boolValue($value), 'false boolean'); }
foreach ([0 => 1, -1 => 1, -7 => 7, 500 => 50] as $value => $expected) { check($block->queryArgs(['postsPerPage' => $value])['posts_per_page'] === $expected, 'bounded abs count'); }
$q = $block->queryArgs(['category' => ' <b>news</b> ']);
check($q['category_name'] === 'news' && $q['post_status'] === 'publish' && $q['ignore_sticky_posts'] && $q['no_found_rows'], 'category and query policy');
check(! isset($block->queryArgs([])['category_name']) && $block->queryArgs([])['posts_per_page'] === 5, 'defaults');
foreach (['[...]', '[…]', '...', '…', 'Continued', 'continued', '… [...] Continued'] as $ending) {
  $GLOBALS['excerpt'] = '<b>News &amp; updates</b> ' . $ending;
  $html = $block->render([]);
  check(strpos($html, '<p>News &amp; updates…</p>') !== false, 'cleanup ' . $ending);
  check(strpos($html, '&amp;amp;') === false, 'no double escaping');
}
$GLOBALS['excerpt'] = 'Continued inside ... a sentence';
check(strpos($block->render([]), 'Continued inside ... a sentence…') !== false, 'only trailing cleanup');
$GLOBALS['excerpt'] = ' [...] ';
check(strpos($block->render([]), 'post-excerpt') === false, 'empty cleaned excerpt');
$html = $block->render(['showDate' => 'false', 'showExcerpt' => 'off']);
check(strpos($html, '<time') === false && strpos($html, 'post-excerpt') === false, 'toggles');
$html = $block->render([]);
check(strpos($html, 'datetime="2026-10-08T10:00:00+03:00"') !== false, 'machine date');
check(strpos($html, '<script>') === false && strpos($html, '&lt;script&gt;') !== false, 'escaped title and thumbnail label');
check($GLOBALS['thumb_size'] === 'medium', 'medium thumbnail');
check($GLOBALS['post']->ID === 999 && $GLOBALS['excerpt_id'] === 2, 'global post preserved and correct excerpt');
$GLOBALS['protected'] = true; $GLOBALS['excerpt'] = 'Secret';
check(strpos($block->render([]), 'Secret') === false, 'protected excerpt');
$GLOBALS['fixture_posts'] = [];
check(strpos($block->render([]), 'Įrašų nerasta.') !== false, 'empty list');
check($GLOBALS['enqueued_style'] === 'alps-gb-newspaper-posts', 'style enqueued');
echo 'PASS: ' . $checks . " newspaper checks\n";
