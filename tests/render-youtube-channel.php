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
function is_wp_error($v) { return false; }
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
