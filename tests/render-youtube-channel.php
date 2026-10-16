<?php
/**
 * Checks for YouTubeChannelBlock with WordPress and the YouTube Data API
 * stubbed. Run: php tests/render-youtube-channel.php
 */

define('ABSPATH', __DIR__ . '/');
define('ALPS_GUTENBERG_VERSION', 'test');

const KEY = 'AIzaTestOnlyKey_0123456789abcdef';
$GLOBALS['options'] = ['alps_gb_youtube_api_key' => KEY, 'date_format' => 'Y-m-d'];
$GLOBALS['transients'] = [];
$GLOBALS['scheduled'] = [];
$GLOBALS['calls'] = [];
$GLOBALS['api'] = null;
$GLOBALS['editor'] = false;
$GLOBALS['enqueued'] = [];

function get_option($key, $default = false) { return array_key_exists($key, $GLOBALS['options']) ? $GLOBALS['options'][$key] : $default; }
function update_option($key, $value, $autoload = null) { $GLOBALS['options'][$key] = $value; return true; }
function get_transient($key) { return $GLOBALS['transients'][$key] ?? false; }
function set_transient($key, $value, $ttl) { $GLOBALS['transients'][$key] = $value; return true; }
function delete_transient($key) { unset($GLOBALS['transients'][$key]); return true; }
function wp_next_scheduled($hook, $args) { return in_array([$hook, $args], $GLOBALS['scheduled'], false) ? time() : false; }
function wp_schedule_single_event($time, $hook, $args) { $GLOBALS['scheduled'][] = [$hook, $args]; return true; }
function current_user_can($cap) { return $GLOBALS['editor']; }
function add_query_arg($args, $url) { return $url . '?' . implode('&', array_map(function ($k, $v) { return $k . '=' . $v; }, array_keys($args), $args)); }
function wp_remote_get($url, $args) {
    $GLOBALS['calls'][] = $url;
    $GLOBALS['headers'] = $args['headers'] ?? null;
    $endpoint = basename(parse_url($url, PHP_URL_PATH));
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    return call_user_func($GLOBALS['api'], $endpoint, $query);
}
function wp_remote_retrieve_response_code($r) { return $r['code']; }
function wp_remote_retrieve_body($r) { return $r['body']; }
class WP_Error {}
function is_wp_error($v) { return $v instanceof WP_Error; }
function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }
function wp_json_encode($v) { return json_encode($v); }
function sanitize_text_field($s) { return trim(preg_replace('/\s+/', ' ', strip_tags((string) $s))); }
function wp_strip_all_tags($s) { return trim(strip_tags((string) $s)); }
function __($s, $d) { return $s; }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_html__($s, $d) { return esc_html($s); }
function esc_attr__($s, $d) { return esc_attr($s); }
function esc_url($s, $protocols = null) { $s = (string) $s; return preg_match('#^https?://#i', $s) ? htmlspecialchars($s, ENT_QUOTES, 'UTF-8') : ''; }
function wp_enqueue_style($h) { $GLOBALS['enqueued'][] = "style:$h"; }
function wp_enqueue_script($h) { $GLOBALS['enqueued'][] = "script:$h"; }
function get_block_wrapper_attributes($extra) { return 'class="wp-block-alps-gutenberg-blocks-youtube-channel ' . $extra['class'] . '"'; }
function wp_date($format, $timestamp) { return gmdate($format, $timestamp); }
function home_url($path = '') { return 'https://example.test' . $path; }
define('DAY_IN_SECONDS', 86400);
define('THUMBS_BASE', sys_get_temp_dir() . '/alps-ytc-test-' . getmypid());
function wp_upload_dir($time = null, $create = true) { return ['error' => false, 'basedir' => THUMBS_BASE, 'baseurl' => 'https://example.test/wp-content/uploads']; }
function trailingslashit($s) { return rtrim($s, '/') . '/'; }
function wp_mkdir_p($dir) { return is_dir($dir) || mkdir($dir, 0777, true); }
function wp_generate_password($length, $special) { return substr(md5((string) mt_rand()), 0, $length); }
function wp_delete_file($file) { @unlink($file); }

// WordPress's image editor: sizes by YouTube's image name, records what it was asked to do.
$GLOBALS['avif'] = false;
$GLOBALS['avifFail'] = false;
$GLOBALS['cropFail'] = [];
$GLOBALS['saveFail'] = false;
$GLOBALS['saveMime'] = null;
$GLOBALS['encodeSeconds'] = 0;
$GLOBALS['clock'] = 0;
$GLOBALS['edits'] = [];
$GLOBALS['formats'] = [];
$GLOBALS['filters'] = [];
function add_filter($hook, $callback, $priority = 10, $args = 1) { $GLOBALS['filters'][$hook][] = $callback; return true; }
function remove_filter($hook, $callback, $priority = 10) { $GLOBALS['filters'][$hook] = array_filter($GLOBALS['filters'][$hook] ?? [], function ($f) use ($callback) { return $f !== $callback; }); return true; }
function wp_image_editor_supports($args) { return $GLOBALS['avif'] && 'image/avif' === ($args['mime_type'] ?? ''); }
function wp_get_image_editor($file, $args = []) {
    $GLOBALS['edits'][] = basename($file) . ' → ' . ($args['output_mime_type'] ?? '');
    // The output formats WordPress would read here, on a site that maps JPEG to WebP.
    $formats = ['image/jpeg' => 'image/webp'];
    foreach ($GLOBALS['filters']['image_editor_output_format'] ?? [] as $filter) {
        $formats = $filter($formats, $file, 'image/jpeg');
    }
    $GLOBALS['formats'][] = $formats;
    return $GLOBALS['avifFail'] ? new WP_Error() : new FakeEditor($file);
}
// The run's clock: real time plus the seconds the fake encodes took.
eval('namespace ALPS\Gutenberg\Blocks; function microtime($asFloat = false) { return \microtime(true) + $GLOBALS["clock"]; }');
class FakeEditor
{
    public $file;
    public $crop = null;
    public function __construct($file) { $this->file = $file; }
    public function get_size()
    {
        preg_match('/-([a-z]+)\.jpg$/', $this->file, $m);
        $sizes = ['mqdefault' => [320, 180], 'hqdefault' => [480, 360], 'sddefault' => [640, 480], 'maxresdefault' => [1280, 720]];
        return ['width' => $sizes[$m[1]][0], 'height' => $sizes[$m[1]][1]];
    }
    public function crop($x, $y, $w, $h)
    {
        if (in_array(basename($this->file), $GLOBALS['cropFail'], true)) {
            return new WP_Error();
        }
        $this->crop = [$x, $y, $w, $h];
        return true;
    }
    public function set_quality($quality) { return true; }
    public function save($path, $mime)
    {
        if ($GLOBALS['saveFail']) {
            return new WP_Error();
        }
        // Like WordPress 6.1+: converting to another format resets the quality through the filter.
        $quality = 82;
        foreach ($GLOBALS['filters']['wp_editor_set_quality'] ?? [] as $filter) {
            $quality = $filter($quality, $mime);
        }
        $GLOBALS['saved'][basename($this->file)] = ['crop' => $this->crop, 'quality' => $quality, 'temporary' => basename($path)];
        $GLOBALS['clock'] += $GLOBALS['encodeSeconds'];
        file_put_contents($path, 'AVIF');
        return ['path' => $path, 'mime-type' => $GLOBALS['saveMime'] ?? $mime];
    }
}

require dirname(__DIR__) . '/src/youtube-channel/class-youtube-channel-block.php';
use ALPS\Gutenberg\Blocks\YouTubeChannelBlock as Block;

$failures = 0;
function check($label, $condition)
{
    global $failures;
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $failures += $condition ? 0 : 1;
}

// Channel input.
$id = 'UCSk51MbjSuTxdFQQEHfKYug';
check('channel ID', ['id' => $id] === Block::parseChannel($id));
check('@handle', ['handle' => 'TrijuAngeluStudija'] === Block::parseChannel(' @TrijuAngeluStudija '));
check('handle link with a tab path', ['handle' => 'TrijuAngeluStudija'] === Block::parseChannel('https://www.youtube.com/@TrijuAngeluStudija/videos'));
check('channel link', ['id' => $id] === Block::parseChannel("https://m.youtube.com/channel/$id/featured"));
check('non-ASCII handle', ['handle' => 'ąžuolas'] === Block::parseChannel('https://www.youtube.com/@%C4%85%C5%BEuolas'));
check('spoofed host rejected', [] === Block::parseChannel("https://youtube.com.evil.test/channel/$id"));
check('javascript: rejected', [] === Block::parseChannel("javascript:alert(1)//youtube.com/channel/$id"));
check('legacy /c/ link and short handle rejected', [] === Block::parseChannel('https://www.youtube.com/c/Name') && [] === Block::parseChannel('@ab'));
check('non-scalar rejected', [] === Block::parseChannel(['@TrijuAngeluStudija']));

// Durations.
check('ISO durations', 3723 === Block::seconds('PT1H2M3S') && 45 === Block::seconds('PT45S') && 86401 === Block::seconds('P1DT1S'));
check('bad durations are 0', 0 === Block::seconds('') && 0 === Block::seconds('1:00') && 0 === Block::seconds(null));
check('duration labels', '1:02:03' === Block::duration(3723) && '4:05' === Block::duration(245) && '' === Block::duration(0));

// Video filtering.
function item($id, $duration, $date, $extra = [])
{
    return array_replace_recursive([
        'id' => $id,
        'snippet' => [
            'title' => "Video $id",
            'publishedAt' => $date,
            'liveBroadcastContent' => 'none',
            'thumbnails' => [
                'medium' => ['url' => "https://i.ytimg.com/vi/$id/mqdefault.jpg"],
                'high' => ['url' => "https://i.ytimg.com/vi/$id/hqdefault.jpg"],
                'maxres' => ['url' => "https://i.ytimg.com/vi/$id/maxresdefault.jpg"],
            ],
        ],
        'contentDetails' => ['duration' => $duration],
        'status' => ['privacyStatus' => 'public', 'embeddable' => true],
    ], $extra);
}
check('Shorts-length video dropped', null === Block::video(item('aaaaaaaaaaa', 'PT2M59S', '2026-10-01T00:00:00Z'), true));
check('Shorts-length video kept when allowed', null !== Block::video(item('aaaaaaaaaaa', 'PT2M59S', '2026-10-01T00:00:00Z'), false));
check('live stream (no duration) kept', null !== Block::video(item('aaaaaaaaaaa', 'P0D', '2026-10-01T00:00:00Z', ['snippet' => ['liveBroadcastContent' => 'live']]), true));
check('upcoming premiere dropped', null === Block::video(item('aaaaaaaaaaa', 'P0D', '2026-10-01T00:00:00Z', ['snippet' => ['liveBroadcastContent' => 'upcoming']]), true));
check('private video dropped', null === Block::video(item('aaaaaaaaaaa', 'PT10M', '2026-10-01T00:00:00Z', ['status' => ['privacyStatus' => 'private']]), true));
check('non-embeddable video dropped', null === Block::video(item('aaaaaaaaaaa', 'PT10M', '2026-10-01T00:00:00Z', ['status' => ['embeddable' => false]]), true));
check('bad video ID dropped', null === Block::video(item('../etc/pass', 'PT10M', '2026-10-01T00:00:00Z'), true));
$v = Block::video(item('bbbbbbbbbbb', 'PT10M', '2026-10-01T00:00:00Z', ['snippet' => ['title' => '<b>Bold</b> title', 'thumbnails' => ['medium' => ['url' => 'https://evil.test/x.jpg'], 'high' => ['url' => 'https://i.ytimg.com/vi/ccccccccccc/hqdefault.jpg'], 'maxres' => ['url' => 'javascript:alert(1)']]]]), true);
check('thumbnails from other hosts or other videos fall back to mqdefault', [320 => 'https://i.ytimg.com/vi/bbbbbbbbbbb/mqdefault.jpg'] === $v['thumbs']);
check('title tags stripped', 'Bold title' === $v['title']);

// A channel: 3 uploads on page 1 (one a Short), 2 on page 2.
$uploads = 'UUSk51MbjSuTxdFQQEHfKYug';
$videos = [
    'v1aaaaaaaaa' => item('v1aaaaaaaaa', 'PT20M', '2026-10-05T10:00:00Z', ['snippet' => ['title' => 'Sermon <script>alert(1)</script> "quoted"']]),
    'v2aaaaaaaaa' => item('v2aaaaaaaaa', 'PT59S', '2026-10-06T10:00:00Z'),
    'v3aaaaaaaaa' => item('v3aaaaaaaaa', 'PT1H5M', '2026-10-01T10:00:00Z'),
    'v4aaaaaaaaa' => item('v4aaaaaaaaa', 'PT30M', '2026-10-07T09:00:00Z'),
    'v5aaaaaaaaa' => item('v5aaaaaaaaa', 'PT12M', '2026-09-20T10:00:00Z'),
];
$GLOBALS['api'] = function ($endpoint, $query) use ($uploads, $videos) {
    if (KEY !== ($query['key'] ?? '')) {
        return ['code' => 400, 'body' => '{"error":{"message":"API key not valid."}}'];
    }
    if ('channels' === $endpoint) {
        $ok = ($query['forHandle'] ?? '') === '@TrijuAngeluStudija' || ($query['id'] ?? '') === 'UCSk51MbjSuTxdFQQEHfKYug';
        return ['code' => 200, 'body' => json_encode(['items' => $ok ? [['snippet' => ['title' => 'Trijų Angelų Studija'], 'contentDetails' => ['relatedPlaylists' => ['uploads' => $uploads]]]] : []])];
    }
    if ('playlistItems' === $endpoint) {
        $page = empty($query['pageToken']) ? ['v1aaaaaaaaa', 'v2aaaaaaaaa', 'v3aaaaaaaaa'] : ['v4aaaaaaaaa', 'v5aaaaaaaaa'];
        $body = ['items' => array_map(function ($id) { return ['contentDetails' => ['videoId' => $id]]; }, $page)];
        if (empty($query['pageToken'])) {
            $body['nextPageToken'] = 'PAGE2';
        }
        return ['code' => 200, 'body' => json_encode($body)];
    }
    $ids = explode(',', $query['id']);
    return ['code' => 200, 'body' => json_encode(['items' => array_values(array_intersect_key($videos, array_flip($ids)))])];
};

$data = Block::fetch(['handle' => 'TrijuAngeluStudija'], true);
check('channel title read', 'Trijų Angelų Studija' === $data['title']);
check('Shorts removed, newest first across pages', ['v4aaaaaaaaa', 'v1aaaaaaaaa', 'v3aaaaaaaaa', 'v5aaaaaaaaa'] === array_column($data['videos'], 'id'));
check('both upload pages read', 5 === count($GLOBALS['calls']));

// Rendering.
$block = new Block();
$GLOBALS['calls'] = [];
$attributes = [
    'channel' => 'https://www.youtube.com/@TrijuAngeluStudija',
    'title' => '',
    'count' => 3,
    'links' => ['facebook' => 'https://www.facebook.com/3AStudija', 'x' => 'javascript:alert(1)', 'instagram' => ['bad']],
];
$html = $block->render($attributes);
check('first render fetches once (channel, 2 upload pages, 2 detail lookups)', 5 === count($GLOBALS['calls']));
check('channel name is the default title', false !== strpos($html, '<h2 class="alps-ytc__title">Trijų Angelų Studija</h2>'));
check('count limits the list', 3 === substr_count($html, 'class="alps-ytc__item"'));
check('nothing loads from YouTube before a click', false === stripos($html, '<iframe') && false === strpos($html, 'youtube.com/embed') && false === strpos($html, 'youtube-nocookie'));
check('the API key never reaches the page', false === strpos($html, KEY) && false === strpos($html, 'googleapis'));
check('video titles are stripped and escaped', false === strpos($html, '<script>') && false !== strpos($html, '>Sermon alert(1) &quot;quoted&quot;</span>'));
check('the newest video is the poster and the active card', false !== strpos($html, 'class="alps-ytc__poster" data-video="v4aaaaaaaaa"') && false !== strpos($html, 'alps-ytc__card is-active" data-video="v4aaaaaaaaa"'));
check('cards offer thumbnails up to 480 px', false !== strpos($html, 'v1aaaaaaaaa/hqdefault.jpg 480w') && false === strpos($html, 'v1aaaaaaaaa/maxresdefault.jpg'));
check('the poster stops at 640 px in a content column', false !== strpos($html, 'v4aaaaaaaaa/sddefault.jpg') || false !== strpos($html, 'v4aaaaaaaaa/hqdefault.jpg 480w') && false === strpos($html, 'v4aaaaaaaaa/maxresdefault.jpg'));
$savedEnqueued = $GLOBALS['enqueued'];
$wideHtml = $block->render($attributes + ['align' => 'wide']);
$GLOBALS['enqueued'] = $savedEnqueued;
check('a wide block offers the 1280 px poster', false !== strpos($wideHtml, 'v4aaaaaaaaa/maxresdefault.jpg 1280w'));
check('images are lazy and sized', false === strpos($html, 'loading="eager"') && substr_count($html, 'loading="lazy"') === substr_count($html, '<img') && false !== strpos($html, 'width="1280" height="720"'));
check('channel and safe profile links only', false !== strpos($html, 'href="https://www.youtube.com/@TrijuAngeluStudija"') && false !== strpos($html, 'https://www.facebook.com/3AStudija') && false === strpos($html, 'javascript:') && 2 === substr_count($html, 'alps-ytc__profile"'));
check('durations and machine-readable dates', false !== strpos($html, '>1:05:00<') && false !== strpos($html, '<time class="alps-ytc__date" datetime="2026-10-01T10:00:00Z">2026-10-01</time>'));
check('watch-on-YouTube fallback link', false !== strpos($html, 'href="https://www.youtube.com/watch?v=v4aaaaaaaaa"'));
check('front-end files enqueued only when rendered', ['style:alps-gb-youtube-channel', 'script:alps-gb-youtube-channel'] === $GLOBALS['enqueued']);

$GLOBALS['calls'] = [];
$block->render($attributes + ['title' => 'Mano kanalas']);
check('cached list: no API requests on later renders', 0 === count($GLOBALS['calls']));
$custom = $block->render(['title' => 'Mano <em>kanalas</em>'] + $attributes);
check('custom title is escaped', false !== strpos($custom, 'Mano &lt;em&gt;kanalas&lt;/em&gt;'));

// Main website: a visible address under the title.
check('no website, no website link', false === strpos($html, 'alps-ytc__site'));
$site = $block->render(['website' => ' https://www.TrijuAngeluStudija.lt/apie?x=1&y=2 '] + $attributes);
check('website shown under the title as its domain', false !== strpos($site, '<div class="alps-ytc__heading"><h2 class="alps-ytc__title">Trijų Angelų Studija</h2><a class="alps-ytc__site" href="https://www.TrijuAngeluStudija.lt/apie?x=1&amp;y=2" target="_blank" rel="noopener"><svg') && false !== strpos($site, '<span>trijuangelustudija.lt</span></a></div>'));
$site = $block->render(['website' => 'https://example.lt', 'websiteLabel' => 'Mūsų <b>svetainė</b>'] + $attributes);
check('custom website text is stripped and escaped', false !== strpos($site, '<span>Mūsų svetainė</span>'));
check('unsafe or empty website addresses are left out', false === strpos($block->render(['website' => 'javascript:alert(1)'] + $attributes), 'alps-ytc__site') && false === strpos($block->render(['website' => ['bad']] + $attributes), 'alps-ytc__site') && false === strpos($block->render(['website' => 'https://'] + $attributes), 'alps-ytc__site'));
check('an address without https:// is completed', ['https://example.lt/naujienos', 'example.lt'] === Block::website('example.lt/naujienos', '') && ['http://www.x.lt', 'x.lt'] === Block::website('http://www.x.lt', '') && ['https://example.lt:8080', 'example.lt'] === Block::website('example.lt:8080', '') && [] === Block::website('mailto:a@b.lt', ''));

// Stale list: served at once, refreshed by WP-Cron.
$key = 'alps_gb_ytc_' . md5(json_encode([['handle' => 'TrijuAngeluStudija'], true]));
$GLOBALS['options'][$key]['time'] = time() - 7200;
$GLOBALS['calls'] = [];
$html = $block->render($attributes);
check('stale list rendered without waiting for the API', 0 === count($GLOBALS['calls']) && false !== strpos($html, 'alps-ytc__poster'));
$refreshes = function () { return array_values(array_filter($GLOBALS['scheduled'], function ($event) { return 'alps_gb_youtube_channel_refresh' === $event[0]; })); };
check('stale list schedules one refresh', 1 === count($refreshes()));
$block->render($attributes);
check('the refresh is not scheduled twice', 1 === count($refreshes()));

// Thumbnails are copied to uploads/alps-ytc by WP-Cron and then served from there.
check('a fetched list schedules copying its thumbnails', in_array(['alps_gb_youtube_channel_thumbs', [$key]], $GLOBALS['scheduled'], true));
$jpeg = "\xFF\xD8\xFF\xC0\x00\x11\x08\x00\x10\x00\x10\x03\x01\x22\x00\x02\x11\x01\x03\x11\x01" . str_repeat("\x00", 120) . "\xFF\xD9";
$apiMock = $GLOBALS['api'];
$GLOBALS['api'] = function ($endpoint, $query) use ($jpeg) {
    return false !== strpos(end($GLOBALS['calls']), '/v1aaaaaaaaa/') ? ['code' => 200, 'body' => '<html>not an image</html>'] : ['code' => 200, 'body' => $jpeg];
};
$thumbDir = THUMBS_BASE . '/alps-ytc/';
$GLOBALS['calls'] = [];
$block->saveThumbs($key);
$saved = array_map('basename', glob($thumbDir . '*.jpg') ?: []);
sort($saved);
check('thumbnails saved under validated names', in_array('v4aaaaaaaaa-mqdefault.jpg', $saved, true) && in_array('v3aaaaaaaaa-hqdefault.jpg', $saved, true));
check('only from i.ytimg.com', [] === array_filter($GLOBALS['calls'], function ($url) { return 0 !== strpos($url, 'https://i.ytimg.com/vi/'); }));
check('1280 px only for the first video (the player of a wide block)', in_array('v4aaaaaaaaa-maxresdefault.jpg', $saved, true) && ! in_array('v3aaaaaaaaa-maxresdefault.jpg', $saved, true));
check('a response that is not a JPEG is not saved', ! in_array('v1aaaaaaaaa-mqdefault.jpg', $saved, true) && ! glob($thumbDir . '*.tmp'));
$GLOBALS['scheduled'] = [];
$html = $block->render($attributes);
check('saved thumbnails are served from the site', false !== strpos($html, 'https://example.test/wp-content/uploads/alps-ytc/v4aaaaaaaaa-mqdefault.jpg 320w') && false === strpos($html, 'i.ytimg.com/vi/v4aaaaaaaaa/'));
check('a missing one keeps YouTube\'s address and is copied again later', false !== strpos($html, 'https://i.ytimg.com/vi/v1aaaaaaaaa/mqdefault.jpg') && in_array(['alps_gb_youtube_channel_thumbs', [$key]], $GLOBALS['scheduled'], true));
$GLOBALS['calls'] = [];
$block->saveThumbs('alps_gb_ytc_../../wp-config');
$block->saveThumbs(['not a key']);
check('a WP-Cron argument that is not a cache key does nothing', ! $GLOBALS['calls']);
$GLOBALS['calls'] = [];
$block->saveThumbs($key);
check('only missing thumbnails are downloaded', ! array_filter($GLOBALS['calls'], function ($url) { return false === strpos($url, '/v1aaaaaaaaa/'); }));
touch($thumbDir . 'zzoldvideoz-mqdefault.jpg', time() - 100 * 86400);
touch($thumbDir . 'zznewvideoz-mqdefault.jpg', time() - 10 * 86400);
touch($thumbDir . 'v4aaaaaaaaa-mqdefault.jpg', time() - 100 * 86400);
$block->saveThumbs($key);
check('copies unused for 90 days are removed, recent and listed ones kept', ! is_file($thumbDir . 'zzoldvideoz-mqdefault.jpg') && is_file($thumbDir . 'zznewvideoz-mqdefault.jpg') && is_file($thumbDir . 'v4aaaaaaaaa-mqdefault.jpg'));
check('no AVIF copies where WordPress cannot write AVIF', ! glob($thumbDir . '*.avif') && ! $GLOBALS['edits']);

// AVIF copies, cropped to the 16:9 frame the block shows.
check('4:3 hq/sd images are cropped to their 16:9 middle', [0, 45, 480, 270] === Block::frame(480, 360) && [0, 60, 640, 360] === Block::frame(640, 480));
check('16:9 images are not cropped', null === Block::frame(320, 180) && null === Block::frame(1280, 720) && null === Block::frame(0, 0));
check('AVIF quality 60 unless the theme sets one', 60 === Block::avifQuality() && 70 === (function () { $GLOBALS['options']['_alps_image_avif_quality'] = '70'; return Block::avifQuality(); })() && 60 === (function () { $GLOBALS['options']['_alps_image_avif_quality'] = '0'; return Block::avifQuality(); })());
unset($GLOBALS['options']['_alps_image_avif_quality']);
$GLOBALS['avif'] = true;
$GLOBALS['calls'] = [];
$GLOBALS['saved'] = [];
$block->saveThumbs($key);
$avifs = array_map('basename', glob($thumbDir . 'v*.avif') ?: []);
sort($avifs);
check('the next run adds AVIF copies of the JPEGs already saved', ['v3aaaaaaaaa-hqdefault.avif', 'v3aaaaaaaaa-mqdefault.avif', 'v4aaaaaaaaa-hqdefault.avif', 'v4aaaaaaaaa-maxresdefault.avif', 'v4aaaaaaaaa-mqdefault.avif', 'v5aaaaaaaaa-hqdefault.avif', 'v5aaaaaaaaa-mqdefault.avif'] === $avifs);
check('without downloading them again', ! array_filter($GLOBALS['calls'], function ($url) { return false === strpos($url, '/v1aaaaaaaaa/'); }));
check('hqdefault loses its bars, mq/maxres are not cropped', [0, 45, 480, 270] === $GLOBALS['saved']['v4aaaaaaaaa-hqdefault.jpg']['crop'] && null === $GLOBALS['saved']['v4aaaaaaaaa-mqdefault.jpg']['crop'] && null === $GLOBALS['saved']['v4aaaaaaaaa-maxresdefault.jpg']['crop']);
check('AVIF written at quality 60 despite the format default', 60 === $GLOBALS['saved']['v4aaaaaaaaa-mqdefault.jpg']['quality'] && ! $GLOBALS['filters']['wp_editor_set_quality']);
check('AVIF written beside the target and renamed', (bool) preg_match('/^v4aaaaaaaaa-mqdefault\.[A-Za-z0-9]{8}\.tmp\.avif$/D', $GLOBALS['saved']['v4aaaaaaaaa-mqdefault.jpg']['temporary']) && ! glob($thumbDir . '*.tmp.avif'));

$GLOBALS['scheduled'] = [];
$html = $block->render($attributes);
$posterSizes = 'auto, (max-width: 640px) calc(100vw - 40px), (max-width: 1000px) calc(100vw - 80px), 920px';
check('poster: AVIF source with the same widths and sizes, local JPEG fallback', false !== strpos($html, '<button type="button" class="alps-ytc__poster" data-video="v4aaaaaaaaa" aria-label="Play: Video v4aaaaaaaaa"><picture><source type="image/avif" srcset="https://example.test/wp-content/uploads/alps-ytc/v4aaaaaaaaa-mqdefault.avif 320w, https://example.test/wp-content/uploads/alps-ytc/v4aaaaaaaaa-hqdefault.avif 480w" sizes="' . $posterSizes . '"><img src="https://example.test/wp-content/uploads/alps-ytc/v4aaaaaaaaa-mqdefault.jpg" srcset="https://example.test/wp-content/uploads/alps-ytc/v4aaaaaaaaa-mqdefault.jpg 320w, https://example.test/wp-content/uploads/alps-ytc/v4aaaaaaaaa-hqdefault.jpg 480w" sizes="' . $posterSizes . '" width="1280" height="720" alt="" loading="lazy" decoding="async"></picture>'));
check('cards sized to their column', false !== strpos($html, 'sizes="auto, (max-width: 640px) calc(80vw - 32px), (max-width: 768px) calc(50vw - 50px), (max-width: 1000px) calc((100vw - 120px) / 3), 294px"'));
check('no copy yet: plain <img> with YouTube\'s address', false !== strpos($html, '<span class="alps-ytc__thumb"><img src="https://i.ytimg.com/vi/v1aaaaaaaaa/mqdefault.jpg"') && false === strpos($html, 'v1aaaaaaaaa-mqdefault.avif'));
check('a <picture> for each image with copies (poster, 2 cards)', 3 === substr_count($html, '<picture>') && 3 === substr_count($html, '</picture>'));
$wideHtml = $block->render($attributes + ['align' => 'wide']);
check('wide block: AVIF up to 1280 px, sizes without the 1000 px limit', false !== strpos($wideHtml, 'v4aaaaaaaaa-maxresdefault.avif 1280w" sizes="auto, (max-width: 640px) calc(100vw - 40px), calc(100vw - 80px)"') && false !== strpos($wideHtml, '(max-width: 768px) calc(50vw - 50px), calc((100vw - 120px) / 3)"'));
unlink($thumbDir . 'v3aaaaaaaaa-hqdefault.avif');
$html = $block->render($attributes);
check('a width without an AVIF copy: no <picture>, so no screen gets a too small image', 2 === substr_count($html, '<picture>') && false !== strpos($html, '<span class="alps-ytc__thumb"><img src="https://example.test/wp-content/uploads/alps-ytc/v3aaaaaaaaa-mqdefault.jpg"') && false === strpos($html, 'v3aaaaaaaaa-mqdefault.avif'));
$GLOBALS['saved'] = [];
$block->saveThumbs($key);
check('a missing AVIF copy is made on the next run', ['v3aaaaaaaaa-hqdefault.jpg'] === array_keys($GLOBALS['saved']) && is_file($thumbDir . 'v3aaaaaaaaa-hqdefault.avif'));

array_map('unlink', glob($thumbDir . 'v*.avif') ?: []);
$GLOBALS['avifFail'] = true;
$GLOBALS['edits'] = [];
$block->saveThumbs($key);
check('no image editor for AVIF: not retried for every file in the run', ['v4aaaaaaaaa-mqdefault.jpg → image/avif'] === $GLOBALS['edits'] && ! glob($thumbDir . '*.avif'));
$GLOBALS['avifFail'] = false;

$GLOBALS['cropFail'] = ['v4aaaaaaaaa-hqdefault.jpg'];
$GLOBALS['formats'] = [];
$block->saveThumbs($key);
$avifs = array_map('basename', glob($thumbDir . 'v*.avif') ?: []);
sort($avifs);
check('one file failing mid-list skips that file only', ['v3aaaaaaaaa-hqdefault.avif', 'v3aaaaaaaaa-mqdefault.avif', 'v4aaaaaaaaa-maxresdefault.avif', 'v4aaaaaaaaa-mqdefault.avif', 'v5aaaaaaaaa-hqdefault.avif', 'v5aaaaaaaaa-mqdefault.avif'] === $avifs);
check('the editor is chosen for AVIF output despite the site\'s JPEG mapping', 7 === count($GLOBALS['formats']) && ! array_filter($GLOBALS['formats'], function ($f) { return 'image/avif' !== $f['image/jpeg']; }) && ! $GLOBALS['filters']['image_editor_output_format']);
$GLOBALS['cropFail'] = [];

array_map('unlink', glob($thumbDir . 'v*.avif') ?: []);
$GLOBALS['saveFail'] = true;
$GLOBALS['edits'] = [];
$block->saveThumbs($key);
check('3 failures in a row stop the run\'s conversions', 3 === count($GLOBALS['edits']) && ! glob($thumbDir . '*.avif'));
$GLOBALS['saveFail'] = false;

$GLOBALS['saveMime'] = 'image/jpeg';
$GLOBALS['edits'] = [];
$block->saveThumbs($key);
check('an editor writing another format: temporary file removed, conversions stopped', 1 === count($GLOBALS['edits']) && ! glob($thumbDir . '*.avif'));
$GLOBALS['saveMime'] = null;

$GLOBALS['encodeSeconds'] = 8;
$GLOBALS['saved'] = [];
$GLOBALS['scheduled'] = [];
$block->saveThumbs($key);
check('a run stops after about 20 seconds and continues later', 3 === count($GLOBALS['saved']) && 3 === count(glob($thumbDir . 'v*.avif') ?: []) && [['alps_gb_youtube_channel_thumbs', [$key]]] === $GLOBALS['scheduled']);
$GLOBALS['encodeSeconds'] = 0;
$GLOBALS['clock'] = 0;

$block->saveThumbs($key);
touch($thumbDir . 'v4aaaaaaaaa-mqdefault.Ab3dEf7h.tmp.avif', time() - 2 * 86400);
touch($thumbDir . 'v4aaaaaaaaa-mqdefault.jpg.Ab3dEf7h.tmp', time() - 2 * 86400);
touch($thumbDir . 'v4aaaaaaaaa-hqdefault.Zz9yXw8v.tmp.avif', time() - 3600);
touch($thumbDir . 'v4aaaaaaaaa-hqdefault.jpg.Zz9yXw8v.tmp', time() - 3600);
touch($thumbDir . 'v4aaaaaaaaa-mqdefault.Ab3dEf7.tmp.avif', time() - 2 * 86400);
touch($thumbDir . 'v4aaaaaaaaa-mqdefault.avif.Ab3dEf7h.tmp', time() - 2 * 86400);
touch($thumbDir . 'notes.tmp', time() - 2 * 86400);
$block->saveThumbs($key);
check('temporary files left for over a day are removed', ! is_file($thumbDir . 'v4aaaaaaaaa-mqdefault.Ab3dEf7h.tmp.avif') && ! is_file($thumbDir . 'v4aaaaaaaaa-mqdefault.jpg.Ab3dEf7h.tmp'));
check('recent temporary files and other names are kept', is_file($thumbDir . 'v4aaaaaaaaa-hqdefault.Zz9yXw8v.tmp.avif') && is_file($thumbDir . 'v4aaaaaaaaa-hqdefault.jpg.Zz9yXw8v.tmp') && is_file($thumbDir . 'v4aaaaaaaaa-mqdefault.Ab3dEf7.tmp.avif') && is_file($thumbDir . 'v4aaaaaaaaa-mqdefault.avif.Ab3dEf7h.tmp') && is_file($thumbDir . 'notes.tmp'));

$block->saveThumbs($key);
touch($thumbDir . 'zzoldvideoz-mqdefault.avif', time() - 100 * 86400);
touch($thumbDir . 'zznewvideoz-mqdefault.avif', time() - 10 * 86400);
touch($thumbDir . 'v4aaaaaaaaa-mqdefault.avif', time() - 100 * 86400);
touch($thumbDir . 'not-a-thumb.avif', time() - 100 * 86400);
touch($thumbDir . 'zzoldvideoz-hqdefault.jpg', time() - 100 * 86400);
$block->saveThumbs($key);
check('pruning removes old unused AVIF and JPEG copies alike', ! is_file($thumbDir . 'zzoldvideoz-mqdefault.avif') && ! is_file($thumbDir . 'zzoldvideoz-hqdefault.jpg') && is_file($thumbDir . 'zznewvideoz-mqdefault.avif') && is_file($thumbDir . 'v4aaaaaaaaa-mqdefault.avif'));
check('pruning leaves files with other names alone', is_file($thumbDir . 'not-a-thumb.avif'));
$GLOBALS['avif'] = false;
array_map('unlink', glob($thumbDir . '*') ?: []);
@rmdir($thumbDir);
@rmdir(THUMBS_BASE);
$GLOBALS['api'] = $apiMock;

// The API fails during the refresh: the old list stays.
$working = $GLOBALS['api'];
$GLOBALS['api'] = function () { return ['code' => 403, 'body' => '{"error":{"message":"Requests from referer <empty> are blocked."}}']; };
$result = $block->refresh(['handle' => 'TrijuAngeluStudija'], true);
check('failed refresh keeps the old list', 4 === count($result['videos']) && 4 === count($GLOBALS['options'][$key]['videos']));
check('failed refresh retries in 10 minutes', abs($GLOBALS['options'][$key]['time'] - (time() - 3600 + 600)) <= 2);

// No list yet and the API fails: editors see why, visitors see nothing, retries wait.
$attributes['channel'] = '@Nonexistent';
$GLOBALS['calls'] = [];
$GLOBALS['editor'] = true;
$notice = $block->render($attributes);
check('editor sees the API error, without the key', false !== strpos($notice, 'HTTP 403 Requests from referer  are blocked.') && false === strpos($notice, KEY));
check('editor sees which key was used (last 4 characters and where it is set)', false !== strpos($notice, 'key …' . substr(KEY, -4) . ' from Settings → Media'));
check('a referrer error explains the website restriction', false !== strpos($notice, 'website restriction') && false !== strpos($notice, 'https://example.test/*'));
check('requests carry the site address as Referer', 'https://example.test/' === $GLOBALS['headers']['Referer']);
$GLOBALS['calls'] = [];
$block->render($attributes);
check('after a failure, page views do not call the API again', 0 === count($GLOBALS['calls']));
$GLOBALS['options']['alps_gb_youtube_api_key'] = 'AIzaReplacementKey_0123456789abc';
$block->render($attributes);
check('a new key retries at once instead of waiting 10 minutes', count($GLOBALS['calls']) > 0);
$GLOBALS['options']['alps_gb_youtube_api_key'] = KEY;
$GLOBALS['editor'] = false;
check('visitors see nothing for a failed channel', '' === $block->render($attributes));
$GLOBALS['api'] = $working;
check('unknown channel reported', false !== strpos((function () use ($block) { $GLOBALS['editor'] = true; $GLOBALS['transients'] = []; return $block->render(['channel' => '@Unknownchannel']); })(), 'channel not found'));
check('no channel: editor notice', false !== strpos($block->render(['channel' => 'nonsense']), 'enter a channel link'));
$GLOBALS['editor'] = false;
check('no channel: nothing for visitors', '' === $block->render(['channel' => 'nonsense']));

// API key sources.
$GLOBALS['options']['alps_gb_youtube_api_key'] = '';
$GLOBALS['options']['wpy_youtube_api_key'] = 'AIzaWpYouTubeKey_0123456789abcd';
check('falls back to the WP YouTube key', 'AIzaWpYouTubeKey_0123456789abcd' === Block::apiKey());
$GLOBALS['options']['wpy_youtube_api_key'] = 'not a key';
check('invalid keys are ignored', '' === Block::apiKey());
$GLOBALS['editor'] = true;
check('no key: editor notice', false !== strpos($block->render(['channel' => '@TrijuAngeluStudija']), 'Settings → Media'));

// Every translatable PHP string has a Lithuanian translation.
$source = file_get_contents(dirname(__DIR__) . '/src/youtube-channel/class-youtube-channel-block.php');
preg_match_all("/(?:__|esc_html__|esc_attr__)\\('((?:[^'\\\\]|\\\\.)*)'/", $source, $m);
$translations = include dirname(__DIR__) . '/languages/alps-gutenberg-blocks-lt_LT.l10n.php';
$missing = array_diff(array_map('stripslashes', $m[1]), array_keys($translations['messages']));
check('all ' . count($m[1]) . ' PHP strings have a Lithuanian translation' . ($missing ? ': missing ' . implode(' | ', $missing) : ''), ! $missing && count($m[1]) > 10);

echo $failures ? "$failures check(s) failed\n" : "All checks passed\n";
exit($failures ? 1 : 0);
