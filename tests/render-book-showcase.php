<?php
// Standalone renderer regressions; real WordPress/editor checks are separate.
define('ABSPATH', __DIR__ . '/');
define('ALPS_GUTENBERG_VERSION', 'test');
$GLOBALS['fixturePosts'] = [(object) ['ID' => 1], (object) ['ID' => 2], (object) ['ID' => 3]];
$GLOBALS['post'] = (object) ['ID' => 8909];
$GLOBALS['captions'] = [11 => 'Caption <b>one</b> "quoted"', 12 => ''];
$GLOBALS['enqueued'] = [];
class WP_Query {
    public $posts;
    public function __construct($args) { $GLOBALS['queryArgs'] = $args; $this->posts = $GLOBALS['fixturePosts']; }
}
function sanitize_title($value) { return strtolower(trim(preg_replace('/[^a-zA-Z0-9_-]/', '', $value))); }
function sanitize_hex_color($v) { return preg_match('/^#(?:[a-f0-9]{3}|[a-f0-9]{6})$/i', $v) ? $v : null; }
function wp_register_style($h, $url, $deps, $v) { $GLOBALS['registeredStyle'] = $h; }
function plugins_url($path, $plugin) { return 'https://example.test/' . $path; }
function register_block_type($path, $args) { $GLOBALS['registeredBlock'] = [$path, $args]; }
function wp_enqueue_style($h) { $GLOBALS['enqueued'][] = $h; }
function get_block_wrapper_attributes($attrs) { return 'class="wp-block-alps-gutenberg-blocks-book-showcase ' . esc_attr($attrs['class']) . '" style="' . esc_attr($attrs['style']) . '"'; }
function get_post_thumbnail_id($id) { return $id === 3 ? 0 : $id + 10; }
function get_the_title($id) { return "Post $id <unsafe>"; }
function wp_get_attachment_caption($id) { return $GLOBALS['captions'][$id] ?? ''; }
function wp_get_attachment_image($id, $size, $icon, $attrs) { $GLOBALS['imageAttrs'][] = [$id, $size, $attrs]; return '<img src="https://example.test/cover-' . $id . '.jpg" alt="' . esc_attr($attrs['alt']) . '" loading="lazy" width="400" height="600" srcset="https://example.test/small.jpg 200w, https://example.test/large.jpg 400w">'; }
function get_permalink($id) { return "https://example.test/book-$id/"; }
function wp_strip_all_tags($s) { return trim(strip_tags($s)); }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_attr($s) { return esc_html($s); }
function esc_url($s) { return esc_attr($s); }
function __($s, $domain) { return $s; }
function esc_html__($s, $domain) { return esc_html($s); }
require dirname(__DIR__) . '/src/book-showcase/class-book-showcase-block.php';
$block = new ALPS\Gutenberg\Blocks\BookShowcaseBlock();
$failures = 0;
$checks = 0;
function check($label, $ok) { global $failures, $checks; ++$checks; $failures += $ok ? 0 : 1; echo ($ok ? 'PASS ' : 'FAIL ') . $label . "\n"; }
$block->init();
check('registers metadata and style', basename($GLOBALS['registeredBlock'][0]) === 'block.json' && $GLOBALS['registeredStyle'] === 'alps-gb-book-showcase');
$args = $block->queryArgs([]);
check('same query defaults as original', $args['category_name'] === 'pdf-knygos' && $args['posts_per_page'] === -1 && $args['orderby'] === 'title' && $args['order'] === 'ASC');
check('published posts only; no pagination counts or sticky reordering', $args['post_type'] === 'post' && $args['post_status'] === 'publish' && $args['no_found_rows'] && $args['ignore_sticky_posts']);
$args = $block->queryArgs(['category' => 'audio-knygos', 'showAll' => false, 'count' => 6, 'orderBy' => 'modified', 'order' => 'desc']);
check('settings drive the query', $args['category_name'] === 'audio-knygos' && $args['posts_per_page'] === 6 && $args['orderby'] === 'modified' && $args['order'] === 'DESC');
check('limited count capped', $block->queryArgs(['showAll' => false, 'count' => 999])['posts_per_page'] === 100);
check('limited count at least one', $block->queryArgs(['showAll' => false, 'count' => -1])['posts_per_page'] === 1);
check('empty category cannot list all posts', $block->queryArgs(['category' => ''])['post__in'] === [0]);
$args = $block->queryArgs(['category' => [], 'count' => [], 'orderBy' => 'rand; DROP TABLE', 'order' => 'invalid', 'showAll' => 'false']);
check('hostile query attributes use safe defaults', $args['category_name'] === 'pdf-knygos' && $args['posts_per_page'] === 12 && $args['orderby'] === 'title' && $args['order'] === 'ASC');
$html = $block->render([]);
check('all covers rendered with original classes', substr_count($html, 'class="book-showcase-item"') === 3 && strpos($html, 'book-showcase-grid') !== false);
check('caption text escaped and stripped', strpos($html, 'Caption one &quot;quoted&quot;') !== false && strpos($html, '<b>') === false);
check('empty caption falls back to post title', strpos($html, '>Post 2</div>') !== false);
check('missing cover stays local', strpos($html, 'book-showcase-missing') !== false && strpos($html, 'placeholder.com') === false);
check('image uses responsive WordPress markup and medium_large', $GLOBALS['imageAttrs'][0][1] === 'medium_large' && strpos($html, 'srcset=') !== false);
check('decorative cover avoids repeating the accessible link name', $GLOBALS['imageAttrs'][0][2]['alt'] === '');
check('only block stylesheet enqueued', $GLOBALS['enqueued'] === ['alps-gb-book-showcase']);
check('original global post preserved', $GLOBALS['post']->ID === 8909);
check('default responsive settings', strpos($html, '--book-desktop-columns:4;--book-tablet-columns:3;--book-small-columns:2;--book-gap:1.5em;--book-max-width:1400px;--book-accent:#C2A25B;') !== false);
$html = $block->render(['showTitles' => false, 'animate' => false, 'titleSource' => 'post']);
check('hidden titles keep accessible book names', strpos($html, 'book-showcase-title') === false && strpos($html, 'aria-label="Post 1"') !== false);
check('post title mode ignores caption', strpos($html, 'Caption one') === false);
check('animation can be disabled', strpos($html, 'alps-book-showcase--animated') === false);
$html = $block->render(['desktopColumns' => 99, 'tabletColumns' => -1, 'smallColumns' => [], 'gap' => 'NaN', 'maxWidth' => '999999', 'accentColor' => '#fff; background:url(javascript:alert(1))']);
check('layout values clamped and nonscalars rejected', strpos($html, '--book-desktop-columns:6;--book-tablet-columns:1;--book-small-columns:2;--book-gap:1.5em;--book-max-width:1800px;--book-accent:#C2A25B;') !== false);
check('CSS injection rejected', strpos($html, 'javascript:') === false);
$GLOBALS['fixturePosts'] = [];
$html = $block->render(['emptyMessage' => '<img src=x onerror=alert(1)>No <b>titles</b>']);
check('custom empty message strips markup', strpos($html, '>No titles</p>') !== false && strpos($html, 'onerror') === false);
check('default empty message translated', strpos($block->render([]), '>No books found.</p>') !== false);
check('invalid render attributes safe', strpos($block->render(null), 'book-showcase-empty') !== false);
echo "$checks checks, $failures failures\n";
exit($failures ? 1 : 0);
