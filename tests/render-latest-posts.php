<?php
/**
 * Render and validation checks for LatestPostsBlock, with WordPress functions
 * stubbed. Run: php tests/render-latest-posts.php
 */

define('ABSPATH', __DIR__ . '/');

$GLOBALS['posts'] = [];
$GLOBALS['meta'] = [];
$GLOBALS['last_query'] = null;

class WP_Term
{
    public $name;
    public function __construct($name) { $this->name = $name; }
}

function get_queried_object_id() { return 1; }
function get_post_meta($id, $key, $single = false) { return $GLOBALS['meta'][$id][$key] ?? ''; }
function wp_get_recent_posts($args) { $GLOBALS['last_query'] = $args; return array_map(function ($id) { return ['ID' => $id]; }, array_keys($GLOBALS['posts'])); }
function get_the_title($id) { return $GLOBALS['posts'][$id]['title']; }
function get_permalink($id) { return 'https://example.test/?p=' . $id; }
function get_the_category($id) { return [new WP_Term('News')]; }
function get_term($id, $taxonomy) { return null; }
function wp_attachment_is_image($id) { return 99 === $id; }
function get_post_thumbnail_id($id) { return $GLOBALS['posts'][$id]['thumb'] ?? 0; }
function wp_get_attachment_image_url($id, $size) { return 'large' === $size ? "https://example.test/img-$id-large.jpg" : (str_ends_with($size, '--s') ? "https://example.test/img-$id-s.jpg" : false); }
function get_the_excerpt($id) { return $GLOBALS['posts'][$id]['excerpt']; }
function post_password_required($id) { return ! empty($GLOBALS['posts'][$id]['protected']); }
function get_the_date($format, $id) { return 'c' === $format ? '2026-10-06T10:00:00+00:00' : 'October 6, 2026'; }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_html__($s, $d) { return esc_html($s); }
function esc_url($s) { $s = (string) $s; return preg_match('#^https?://#', $s) ? htmlspecialchars($s, ENT_QUOTES, 'UTF-8') : ''; }
function wp_kses_post($s) { return preg_replace('#<(script|iframe)\b.*?</\1>|\son\w+="[^"]*"#is', '', (string) $s); }
function wp_strip_all_tags($s, $breaks = false) { return trim(strip_tags(preg_replace('#<(script|style)\b.*?</\1>#is', '', (string) $s))); }
function sanitize_html_class($c) { return preg_replace('/[^A-Za-z0-9_-]/', '', (string) $c); }
function absint($v) { return abs((int) $v); }
function register_block_type($path, $args) {}

require dirname(__DIR__) . '/src/latest-posts/class-latest-posts-block.php';

$failures = 0;
function check($label, $condition)
{
    global $failures;
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $failures += $condition ? 0 : 1;
}

$block = new \ALPS\Gutenberg\Blocks\LatestPostsBlock();

// Query arguments: bounded count, allow-listed order, numeric IDs only.
$q = $block->queryArgs(['postsToShow' => 100000, 'order' => 'ASC', 'orderBy' => 'title', 'categories' => '3, 7', 'tags' => ['5', '5', 9]]);
check('postsToShow is capped at 100', 100 === $q['numberposts']);
check('order is lower-cased and allowed', 'asc' === $q['order'] && 'title' === $q['orderby']);
check('category and tag IDs are kept', '3,7' === $q['category'] && [5, 9] === $q['tag__in']);
$q = $block->queryArgs(['postsToShow' => -1, 'order' => 'desc; DROP', 'orderBy' => 'rand', 'categories' => '1 OR 1=1,abc', 'tags' => ['x', -4, [1]]]);
check('postsToShow -1 becomes 1, not "all posts"', 1 === $q['numberposts']);
check('unknown order/orderby fall back to desc/date', 'desc' === $q['order'] && 'date' === $q['orderby']);
check('non-numeric category and tag values are dropped', ! isset($q['category']) && ! isset($q['tag__in']));
$q = $block->queryArgs([]);
check('defaults: 4 published posts by date', 4 === $q['numberposts'] && 'publish' === $q['post_status'] && 'post' === $q['post_type']);

// Rendering.
$GLOBALS['posts'] = [
    10 => ['title' => 'Plain <b>title</b>', 'excerpt' => '<p>Tom &amp; Jerry&#8217;s &hellip; ' . str_repeat('ąčęėįšųūž ', 30) . '</p>', 'thumb' => 50],
    11 => ['title' => 'Secret', 'excerpt' => 'There is no excerpt because this is a protected post.', 'protected' => true],
];
$GLOBALS['meta'][10]['header_background_image'] = '99';
$html = $block->render([
    'title' => 'Heading<script>alert(1)</script>',
    'linkLabel' => '',
    'linkUrl' => 'javascript:alert(1)',
    'className' => 'custom"><script>x</script> second',
    'readMoreLabel' => '',
    'postsToShow' => 2,
]);
check('heading script is removed', false === strpos($html, '<script>alert(1)'));
check('empty "see all" link is not printed', false === strpos($html, 'c-block__heading-link'));
check('custom classes are sanitized and spaced', false !== strpos($html, 'c-section__blocks customscriptxscript second is-list u-spacing--double'));
check('post title is escaped', false !== strpos($html, 'Plain &lt;b&gt;title&lt;/b&gt;'));
check('header image (attachment 99) is used', false !== strpos($html, 'img-99-s.jpg'));
check('missing image sizes fall back to "large"', false !== strpos($html, 'img-99-large.jpg'));
check('excerpt entities are decoded once, not shown as &amp;amp;', false !== strpos($html, 'Tom &amp; Jerry’s …') && false === strpos($html, '&amp;amp;') && false === strpos($html, '&amp;hellip;'));
preg_match_all('#<p class="c-block__description">(.*?)</p>#s', $html, $m);
check('excerpt is cut to 100 characters plus an ellipsis (multibyte safe)', 101 === mb_strlen(html_entity_decode($m[1][0], ENT_QUOTES, 'UTF-8')));
check('protected post has an empty excerpt', '' === $m[1][1]);
check('default button label is "Read More"', false !== strpos($html, 'o-button--outline">Read More<span'));
check('no image markup for a post without an image', 1 === substr_count($html, '<picture'));

$html = $block->render(['title' => 'All', 'linkLabel' => 'See <em>all</em>', 'linkUrl' => 'https://example.test/all', 'postLayout' => 'grid', 'readMoreLabel' => 'Skaityti<img src=x onerror="alert(1)">', 'hideExcerpt' => true, 'hideCategoryName' => true, 'hidePostDate' => true]);
check('"see all" link is printed with a safe URL', false !== strpos($html, '<a href="https://example.test/all" class="c-block__heading-link'));
check('grid layout classes are kept', false !== strpos($html, 'c-section__blocks is-grid l-section__block-row l-section__block-row--6-col l-standard-break'));
check('button label keeps text but loses event handlers', false !== strpos($html, '>Skaityti<img src=x>') && false === strpos($html, 'onerror'));
check('hidden excerpt/meta are not printed', false === strpos($html, 'c-block__description') && false === strpos($html, 'c-block__meta'));

echo $failures ? "$failures check(s) failed\n" : "All checks passed\n";
exit($failures ? 1 : 0);
