<?php
/**
 * Checks for PodcastSeasonBlock with WordPress and the feed stubbed.
 * Run: php tests/render-podcast-season.php
 */

define('ABSPATH', __DIR__ . '/');
define('ALPS_GUTENBERG_VERSION', 'test');

$GLOBALS['options'] = [];
$GLOBALS['transients'] = [];
$GLOBALS['scheduled'] = [];
$GLOBALS['calls'] = [];
$GLOBALS['feed'] = null;
$GLOBALS['editor'] = false;
$GLOBALS['enqueued'] = [];
$GLOBALS['actions'] = [];

function get_option($key, $default = false) { return array_key_exists($key, $GLOBALS['options']) ? $GLOBALS['options'][$key] : $default; }
function update_option($key, $value, $autoload = null) { $GLOBALS['options'][$key] = $value; $GLOBALS['autoload'][$key] = $autoload; return true; }
function get_transient($key) { return $GLOBALS['transients'][$key] ?? false; }
function set_transient($key, $value, $ttl) { $GLOBALS['transients'][$key] = $value; return true; }
function delete_transient($key) { unset($GLOBALS['transients'][$key]); return true; }
function wp_next_scheduled($hook, $args) { return in_array([$hook, $args], $GLOBALS['scheduled'], false) ? time() : false; }
function wp_schedule_single_event($time, $hook, $args) { $GLOBALS['scheduled'][] = [$hook, $args]; return true; }
function current_user_can($cap) { return $GLOBALS['editor']; }
class WP_Error { public $message; function __construct($m) { $this->message = $m; } function get_error_message() { return $this->message; } }
function is_wp_error($v) { return $v instanceof WP_Error; }
function wp_safe_remote_get($url, $args) {
    $GLOBALS['calls'][] = $url;
    $GLOBALS['args'] = $args;
    return call_user_func($GLOBALS['feed'], $url);
}
function wp_remote_retrieve_response_code($r) { return $r['code']; }
function wp_remote_retrieve_body($r) { return $r['body']; }
function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }
// Like core: the fragment stays last and an existing key is replaced.
function add_query_arg($key, $value, $url) {
    $parts = explode('#', $url, 2);
    $base = explode('?', $parts[0], 2);
    parse_str($base[1] ?? '', $query);
    $query[$key] = $value;
    return $base[0] . '?' . http_build_query($query) . (isset($parts[1]) ? '#' . $parts[1] : '');
}
function wp_json_encode($v) { return json_encode($v); }
function wp_strip_all_tags($s) { return trim(strip_tags((string) $s)); }
function sanitize_mime_type($s) { return preg_replace('/[^-+*.a-zA-Z0-9\/]/', '', $s); }
function esc_url_raw($s, $protocols = null) {
    $s = trim((string) $s);
    if ('' === $s) { return ''; }
    // Like core's esc_url(): an address without a scheme gets http:// in front.
    if (false === strpos($s, ':') && ! in_array($s[0], ['/', '#', '?'], true) && ! preg_match('/^[a-z0-9-]+?\.php/i', $s)) { $s = 'http://' . $s; }
    return preg_match('#^https?://[^\s"<>]+$#i', $s) ? $s : '';
}
define('DAY_IN_SECONDS', 86400);
function __($s, $d) { return $s; }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_html__($s, $d) { return esc_html($s); }
function esc_attr__($s, $d) { return esc_attr($s); }
function esc_url($s) { return htmlspecialchars(esc_url_raw($s), ENT_QUOTES, 'UTF-8'); }
function wp_trim_words($s, $n, $more) { $w = preg_split('/\s+/', trim($s)); return implode(' ', array_slice($w, 0, $n)) . (count($w) > $n ? $more : ''); }
function wp_enqueue_style($h) { $GLOBALS['enqueued'][] = "style:$h"; }
function wp_enqueue_script($h) { $GLOBALS['enqueued'][] = "script:$h"; }
function wp_register_style(...$a) { $GLOBALS['registered'][] = $a; }
function wp_register_script(...$a) { $GLOBALS['registered'][] = $a; }
function plugins_url($path, $file) { return 'https://example.test/wp-content/plugins/alps-gutenberg-blocks/' . $path; }
function register_block_type($path, $args) { $GLOBALS['blockType'] = [$path, $args]; }
function add_action($hook, $callback, $priority = 10, $args = 1) { $GLOBALS['actions'][$hook] = [$callback, $args]; }
function get_block_wrapper_attributes($extra) { return 'class="wp-block-alps-gutenberg-blocks-podcast-season ' . $extra['class'] . '"'; }
define('COVER_BASE', sys_get_temp_dir() . '/alps-pod-test-' . getmypid());
function wp_upload_dir($time = null, $create = true) { return ['error' => false, 'basedir' => COVER_BASE, 'baseurl' => 'https://example.test/wp-content/uploads']; }
function trailingslashit($s) { return rtrim($s, '/') . '/'; }
function wp_mkdir_p($dir) { return is_dir($dir) || mkdir($dir, 0777, true); }
function wp_generate_password($length, $special) { return substr(md5((string) mt_rand()), 0, $length); }
function wp_delete_file($file) { @unlink($file); }

// An image editor that can write WebP only, or what $GLOBALS['format'] maps it to.
class FakeEditor
{
    public $file;
    public $size;
    function __construct($file) { $this->file = $file; }
    function resize($w, $h, $crop) { $this->size = [$w, $h, $crop]; $GLOBALS['resized'] = $this->size; return true; }
    function supports_mime_type($mime) { return 'image/webp' === $mime; }
    function save($path, $mime) {
        if (! empty($GLOBALS['format'])) {
            $path = preg_replace('/\.\w+$/', '.' . $GLOBALS['format'], $path);
        }
        file_put_contents($path, 'resized');
        return ['path' => $path];
    }
}
function wp_get_image_editor($file) { $GLOBALS['editorFile'] = $file; return is_file($file) ? new FakeEditor($file) : new WP_Error('missing'); }

require dirname(__DIR__) . '/src/podcast-season/class-podcast-season-block.php';
use ALPS\Gutenberg\Blocks\PodcastSeasonBlock as Block;

$failures = 0;
function check($label, $condition)
{
    global $failures;
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $failures += $condition ? 0 : 1;
}

/** RSS shaped like Spotify for Creators' feed (anchor.fm). */
function episode($title, $season, $number, $date, $extra = '')
{
    $audio = 'https://anchor.fm/s/430eecd0/podcast/play/1/https%3A%2F%2Fd3ctxlq1ktw2nl.cloudfront.net%2Fe' . $season . '-' . $number . '.m4a';
    return "<item><title><![CDATA[$title]]></title>"
        . '<description><![CDATA[<p>Kodėl Jėzus vadinamas Emanueliu?</p><p>Autorė: Elena Vait &amp; kiti</p>]]></description>'
        . "<pubDate>$date</pubDate>"
        . "<enclosure url=\"$audio\" length=\"12630226\" type=\"audio/x-m4a\"/>"
        . '<itunes:duration>00:06:40</itunes:duration>'
        . ('' !== (string) $season ? "<itunes:season>$season</itunes:season>" : '')
        . ('' !== (string) $number ? "<itunes:episode>$number</itunes:episode>" : '')
        . '<itunes:episodeType>full</itunes:episodeType>'
        . $extra . '</item>';
}
function feed($items, $prolog = '')
{
    return '<?xml version="1.0" encoding="UTF-8"?>' . $prolog
        . '<rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd"><channel>'
        . '<title><![CDATA[5 minutės]]></title>'
        . '<itunes:image href="https://cdn.example/podcast.jpg"/>'
        . implode('', $items) . '</channel></rss>';
}
$items = [
    episode('Marijos Sūnaus gyvenimas. 3 – Laikų pilnatvė', 3, 3, 'Thu, 29 Oct 2026 14:00:00 GMT'),
    episode('Marijos Sūnaus gyvenimas. 1 – Dievas su mumis (santrauka)', 3, 1, 'Thu, 15 Oct 2026 14:00:00 GMT', '<itunes:image href="https://cdn.example/season3.jpg"/>'),
    episode('Kova ir Drąsa. 365 Dievo planas', '', '', 'Fri, 23 Aug 2024 02:01:00 GMT'),
    episode('Marijos Sūnaus gyvenimas. 2 – Išrinktoji tauta', 3, 2, 'Thu, 22 Oct 2026 14:00:00 GMT'),
    str_replace('>full<', '>trailer<', episode('Season 3 trailer', 3, '', 'Wed, 14 Oct 2026 14:00:00 GMT')),
    episode('Kova ir Drąsa. 1 Kad būtume padrąsinti', 2, 1, 'Mon, 21 Aug 2023 08:30:24 GMT'),
    '<item><title>No audio</title><itunes:season>3</itunes:season><itunes:episode>9</itunes:episode></item>',
    str_replace('https://anchor.fm', 'javascript:alert(1)//', episode('Bad audio', 3, 8, 'Thu, 05 Nov 2026 14:00:00 GMT')),
];
$xml = feed($items);
$GLOBALS['feed'] = function ($url) use (&$xml) { return ['code' => 200, 'body' => $xml]; };
const FEED = 'https://anchor.fm/s/430eecd0/podcast/rss';

// Input.
check('feed address', FEED === Block::feedUrl(' ' . FEED . ' '));
check('non-http addresses rejected', '' === Block::feedUrl('javascript:alert(1)') && '' === Block::feedUrl('ftp://x.example/rss') && '' === Block::feedUrl(['x']) && '' === Block::feedUrl(''));
check('season bounds', 3 === Block::season('3') && 0 === Block::season(0) && 0 === Block::season(1000) && 0 === Block::season('abc') && 0 === Block::season(null));
check('durations', 400 === Block::seconds('00:06:40') && 400 === Block::seconds('6:40') && 400 === Block::seconds('400') && 3723 === Block::seconds('1:02:03') && 0 === Block::seconds('soon'));
check('decimal durations', 401 === Block::seconds('400.5') && 400 === Block::seconds('6:40.2'));
check('duration labels', '6:40' === Block::duration(400) && '1:02:03' === Block::duration(3723) && '' === Block::duration(0));
check('labels: prefix and number removed', 'Dievas su mumis (santrauka)' === Block::label('Marijos Sūnaus gyvenimas. 1 – Dievas su mumis (santrauka)', 'Marijos Sūnaus gyvenimas', 1));
check('labels: prefix is case-insensitive', '1 – Dievas su mumis' === Block::label('MARIJOS SŪNAUS GYVENIMAS. 1 – Dievas su mumis', 'Marijos Sūnaus gyvenimas.', null));
check('labels: number kept when numbers are hidden or differ', '12 apaštalų' === Block::label('12 apaštalų', '', 1) && '1 – Dievas' === Block::label('1 – Dievas', '', null));
check('labels: a verse reference keeps its number', '3:16 Jono evangelija' === Block::label('3:16 Jono evangelija', '', 3) && '151. Mesijas' === Block::label('151. Mesijas', '', 149));
check('labels: never empty', 'Marijos Sūnaus gyvenimas' === Block::label('Marijos Sūnaus gyvenimas', 'Marijos Sūnaus gyvenimas', null));
check('labels: prefix with regex characters is literal', 'Tekstas' === Block::label('(1/2) Tekstas', '(1/2)', null));

// Parsing.
$season = Block::parse($xml, 3);
$titles = array_column($season['episodes'], 'title');
check('only season 3, no trailer or items without safe audio', 3 === count($season['episodes']));
check('ordered by episode number', [1, 2, 3] === array_column($season['episodes'], 'number'));
check('titles decoded', 'Marijos Sūnaus gyvenimas. 1 – Dievas su mumis (santrauka)' === $titles[0]);
check('description as plain text, paragraphs apart', 'Kodėl Jėzus vadinamas Emanueliu? Autorė: Elena Vait & kiti' === $season['episodes'][0]['description']);
check('episode fields', 400 === $season['episodes'][0]['seconds'] && 'audio/x-m4a' === $season['episodes'][0]['type'] && '2026-10-15T14:00:00+00:00' === $season['episodes'][0]['published']);
check('season artwork preferred over the podcast cover', 'https://cdn.example/season3.jpg' === $season['cover'] && '5 minutės' === $season['title']);
check('podcast cover when the season has none', 'https://cdn.example/podcast.jpg' === Block::parse($xml, 2)['cover']);
check('episodes without numbers sort last by date', ['A', 'B'] === array_column(Block::parse(feed([episode('B', 4, '', 'Thu, 22 Oct 2026 14:00:00 GMT'), episode('A', 4, '', 'Thu, 15 Oct 2026 14:00:00 GMT')]), 4)['episodes'], 'title'));
$long = Block::parse(feed([str_replace('Kodėl', str_repeat('ž', 900), episode('Long', 5, 1, 'Thu, 15 Oct 2026 14:00:00 GMT'))]), 5);
check('descriptions capped', mb_strlen($long['episodes'][0]['description']) <= Block::MAX_DESCRIPTION + 1);
$secret = sys_get_temp_dir() . '/alps-pod-secret-' . getmypid() . '.txt';
file_put_contents($secret, 'TOP-SECRET-VALUE');
$xxe = feed([episode('&xxe;', 6, 1, 'Thu, 15 Oct 2026 14:00:00 GMT')], '<!DOCTYPE rss [<!ENTITY xxe SYSTEM "file://' . str_replace('\\', '/', $secret) . '">]>');
$parsed = null;
try { $parsed = Block::parse(str_replace('<![CDATA[&xxe;]]>', '&xxe;', $xxe), 6); } catch (RuntimeException $e) { $parsed = null; }
check('external entities are not read', false === strpos((string) json_encode($parsed), 'TOP-SECRET-VALUE'));
unlink($secret);
foreach (['', 'not xml', '<html><body>Hi</body></html>'] as $bad) {
    $thrown = false;
    try { Block::parse($bad, 3); } catch (RuntimeException $e) { $thrown = 'the address is not an RSS feed' === $e->getMessage(); }
    check('not a feed: ' . ('' === $bad ? '(empty)' : $bad), $thrown);
}

$odd = Block::parse(feed([
    episode('Schemeless', 8, 1, 'Thu, 15 Oct 2026 14:00:00 GMT'),
    episode('Duplicate tags', 8, 2, 'Thu, 22 Oct 2026 14:00:00 GMT', '<itunes:episode>99</itunes:episode><itunes:season>1</itunes:season>'),
]), 8);
check('first of duplicate tags wins', [1, 2] === array_column($odd['episodes'], 'number'));
$schemeless = Block::parse(feed([str_replace('https://anchor.fm/s/430eecd0/podcast/play/1/https%3A%2F%2Fd3ctxlq1ktw2nl.cloudfront.net%2F', '', episode('Relative', 9, 1, 'Thu, 15 Oct 2026 14:00:00 GMT'))]), 9);
check('an enclosure without a scheme is skipped, not turned into http://e9-1.m4a', [] === $schemeless['episodes']);
$many = [];
for ($n = 1; $n <= Block::MAX_EPISODES + 5; ++$n) {
    $many[] = episode("E$n", 10, $n, gmdate('D, d M Y H:i:s', 1700000000 + $n * 86400) . ' GMT');
}
$long = Block::parse(feed($many), 10);
check('a season over the cap keeps its newest episodes', Block::MAX_EPISODES === count($long['episodes']) && 6 === $long['episodes'][0]['number'] && Block::MAX_EPISODES + 5 === end($long['episodes'])['number']);
$plain = str_replace('<itunes:image href="https://cdn.example/podcast.jpg"/>', '<image><url>https://cdn.example/rss-image.png</url></image>', feed([episode('A', 11, 1, 'Thu, 15 Oct 2026 14:00:00 GMT')]));
check('RSS <image> when there is no itunes:image', 'https://cdn.example/rss-image.png' === Block::parse($plain, 11)['cover']);
$latin = mb_convert_encoding(str_replace('encoding="UTF-8"', 'encoding="ISO-8859-13"', feed([episode('Šventykla ąžuolas', 12, 1, 'Thu, 15 Oct 2026 14:00:00 GMT')])), 'ISO-8859-13', 'UTF-8');
check('a feed in another encoding is read as UTF-8', 'Šventykla ąžuolas' === Block::parse($latin, 12)['episodes'][0]['title']);
$laughs = '<!DOCTYPE rss [<!ENTITY a "aaaaaaaaaa">' . implode('', array_map(function ($i) { return '<!ENTITY a' . $i . ' "' . str_repeat('&a' . ($i > 1 ? $i - 1 : '') . ';', 10) . '">'; }, range(1, 9))) . ']>';
$bomb = str_replace('<![CDATA[Bomb]]>', '&a9;', feed([episode('Bomb', 13, 1, 'Thu, 15 Oct 2026 14:00:00 GMT')], $laughs));
$memory = memory_get_peak_usage();
$result = null;
try { $result = Block::parse($bomb, 13); } catch (RuntimeException $e) { $result = 'rejected'; }
check('entity expansion (billion laughs) is not amplified', memory_get_peak_usage() - $memory < 50000000 && ('rejected' === $result || strlen((string) json_encode($result)) < 1000000));

// Init.
$block = new Block();
$block->init();
check('block registered from block.json with a render callback', 'podcast-season/block.json' === substr(str_replace('\\', '/', $GLOBALS['blockType'][0]), -strlen('podcast-season/block.json')) && is_file($GLOBALS['blockType'][0]) && is_callable($GLOBALS['blockType'][1]['render_callback']));
check('front-end script is deferred in the footer', ['in_footer' => true, 'strategy' => 'defer'] === end($GLOBALS['registered'])[4]);
check('cron hooks', isset($GLOBALS['actions'][Block::REFRESH_HOOK], $GLOBALS['actions'][Block::COVER_HOOK]) && 2 === $GLOBALS['actions'][Block::REFRESH_HOOK][1]);

// Editor notices; visitors see nothing.
$GLOBALS['editor'] = true;
check('no feed: editor notice', false !== strpos($block->render([]), 'enter the podcast RSS feed address'));
check('bad season: editor notice', false !== strpos($block->render(['feedUrl' => FEED, 'season' => 0]), 'season number'));
$GLOBALS['editor'] = false;
check('no feed: nothing for visitors', '' === $block->render(['feedUrl' => 'javascript:alert(1)']));
check('no notice CSS or JS loaded for visitors', [] === $GLOBALS['enqueued']);

// Render.
$attributes = ['feedUrl' => FEED, 'season' => 3, 'title' => 'Marijos Sūnaus gyvenimas', 'removePrefix' => 'Marijos Sūnaus gyvenimas', 'visible' => 2];
$html = $block->render($attributes);
check('first render fetches the feed once, bounded', 1 === count($GLOBALS['calls']) && 10 === $GLOBALS['args']['timeout'] && Block::MAX_FEED_BYTES === $GLOBALS['args']['limit_response_size']);
check('anchor.fm feed fetched past the CDN cache', 1 === preg_match('#^' . preg_quote(FEED, '#') . '\?alps_gb_t=\d+$#D', $GLOBALS['calls'][0]) && 'no-cache' === $GLOBALS['args']['headers']['Cache-Control']);
// The hour can turn between fetch() and the check, so either bucket passes, and the other checks ignore the number.
$hour = intdiv(time(), Block::FRESH);
$fetched = function ($url) { $GLOBALS['calls'] = []; Block::fetch($url, 3); return preg_replace('/alps_gb_t=\d+/', 'alps_gb_t=N', $GLOBALS['calls'][0], -1, $n) . ($n > 1 ? ' (twice)' : ''); };
$GLOBALS['calls'] = [];
Block::fetch(FEED, 3);
check('the cache query changes every FRESH seconds', in_array($GLOBALS['calls'][0], [FEED . '?alps_gb_t=' . $hour, FEED . '?alps_gb_t=' . ($hour + 1)], true));
check('feed query kept, cache query added before the fragment', FEED . '?format=rss&alps_gb_t=N#top' === $fetched(FEED . '?format=rss#top'));
check('a cache query already in the address is replaced', FEED . '?alps_gb_t=N' === $fetched(FEED . '?alps_gb_t=1'));
check('anchor.fm subdomains get the cache query', 'https://www.anchor.fm/rss?alps_gb_t=N' === $fetched('https://www.anchor.fm/rss'));
check('other feeds keep their address (signed or strict feeds)', 'https://cdn.example.org/rss?sig=abc' === $fetched('https://cdn.example.org/rss?sig=abc') && 'https://notanchor.fm/rss' === $fetched('https://notanchor.fm/rss') && 'no-cache' === $GLOBALS['args']['headers']['Cache-Control']);
$GLOBALS['calls'] = [FEED];
check('list cached in an option that is not autoloaded', 1 === count($GLOBALS['options']) && false === reset($GLOBALS['autoload']));
check('one audio player that downloads nothing until play', 1 === substr_count($html, '<audio') && false !== strpos($html, 'preload="none"'));
check('player starts on episode 1', preg_match('#<audio[^>]+src="https://anchor\.fm/[^"]+e3-1\.m4a"#', $html) && false !== strpos($html, '<p class="alps-pod__now">Dievas su mumis (santrauka)</p>'));
check('episodes are links to their audio (work without JavaScript), not followed by bots', 3 === substr_count($html, 'class="alps-pod__episode') && 3 === substr_count($html, 'rel="nofollow"'));
check('first episode active', 1 === substr_count($html, 'aria-current="true"') && false !== strpos($html, 'alps-pod__episode is-active" href="https://anchor.fm/s/430eecd0/podcast/play/1/https%3A%2F%2Fd3ctxlq1ktw2nl.cloudfront.net%2Fe3-1.m4a"'));
check('links read "Play: number title duration" (no aria-label hiding them)', false === strpos($html, 'aria-label="Play') && 3 === substr_count($html, '<span class="alps-pod__action">Play:</span>') && false !== strpos($html, 'data-play="Play:" data-pause="Pause:"'));
check('lists keep list semantics without bullets', 2 === substr_count($html, '<ol class="alps-pod__list" role="list"'));
check('numbers, labels and durations', false !== strpos($html, '<span class="alps-pod__number">2</span><span class="alps-pod__name">Išrinktoji tauta</span><span class="alps-pod__duration">6:40</span>'));
check('heading and season line', false !== strpos($html, '<h2 class="alps-pod__title">Marijos Sūnaus gyvenimas</h2>') && false !== strpos($html, 'Season 3 · Episodes: 3'));
check('episodes past "visible" in a native <details>', 1 === substr_count($html, '<details class="alps-pod__more">') && false !== strpos($html, 'Show all episodes (3)</summary><ol class="alps-pod__list" role="list">') && 1 === preg_match('#<details.*Laikų pilnatvė.*</details>#s', $html));
check('autoplay of the next episode on by default', false !== strpos($html, 'data-alps-pod data-auto-next data-play='));
check('no descriptions by default', false === strpos($html, 'alps-pod__description'));
check('front-end files enqueued where the block renders', ['style:alps-gb-podcast-season', 'script:alps-gb-podcast-season'] === $GLOBALS['enqueued']);
check('no cover until the resized copy exists; the copy is scheduled', false === strpos($html, '<img') && 1 === count(array_filter($GLOBALS['scheduled'], function ($e) { return Block::COVER_HOOK === $e[0]; })));
check('nothing from the feed host is loaded on the page but the audio file', 0 === preg_match('#<(img|script|link|iframe)[^>]+cdn\.example#', $html));

$html = $block->render($attributes);
check('second render uses the cache', 1 === count($GLOBALS['calls']));

$html = $block->render(array_merge($attributes, ['order' => 'desc', 'showNumbers' => false, 'autoNext' => false, 'showDescription' => true, 'descriptionWords' => 6, 'visible' => 50]));
check('newest first starts the player on the newest', false !== strpos($html, '<p class="alps-pod__now">3 – Laikų pilnatvė</p>'));
check('numbers hidden keep the number in the title', false === strpos($html, 'alps-pod__number') && false !== strpos($html, '>1 – Dievas su mumis (santrauka)<'));
check('no autoplay attribute when off', false === strpos($html, 'data-auto-next'));
check('descriptions trimmed', false !== strpos($html, '<p class="alps-pod__description">Kodėl Jėzus vadinamas Emanueliu? Autorė: Elena…</p>'));
check('no "Show all" when everything fits', false === strpos($html, '<details'));
$html = $block->render(['feedUrl' => FEED, 'season' => 3, 'visible' => 99999, 'descriptionWords' => -4, 'showDescription' => true, 'order' => ['x'], 'title' => ['x'], 'removePrefix' => ['x']]);
check('hostile attributes are bounded', false === strpos($html, '<details') && false !== strpos($html, '<p class="alps-pod__description">Kodėl Jėzus vadinamas Emanueliu? Autorė:…</p>') && false !== strpos($html, '<h2 class="alps-pod__title">5 minutės</h2>'));

// Escaping.
$xml = feed([episode('"><script>alert(1)</script> & <b>bold</b>', 7, 1, 'Thu, 15 Oct 2026 14:00:00 GMT')]);
$html = $block->render(['feedUrl' => FEED, 'season' => 7, 'title' => '<img src=x onerror=alert(1)>']);
check('feed and attribute text escaped', false === strpos($html, '<script>') && false === strpos($html, '<b>') && false === strpos($html, '<img src=x') && false !== strpos($html, '&lt;img src=x onerror=alert(1)&gt;'));

// Stale lists: served at once, refreshed by WP-Cron.
$key = array_keys($GLOBALS['options'])[0];
$GLOBALS['options'][$key]['time'] = time() - Block::FRESH - 1;
$GLOBALS['calls'] = [];
$GLOBALS['scheduled'] = [];
$html = $block->render($attributes);
check('stale list served without fetching', [] === $GLOBALS['calls'] && false !== strpos($html, 'Dievas su mumis'));
check('stale list refresh scheduled once', 1 === count(array_filter($GLOBALS['scheduled'], function ($e) { return Block::REFRESH_HOOK === $e[0] && [FEED, 3] === $e[1]; })));
$block->render($attributes);
check('no duplicate refresh', 1 === count(array_filter($GLOBALS['scheduled'], function ($e) { return Block::REFRESH_HOOK === $e[0]; })));

// Failures.
$xml = feed($items);
$GLOBALS['feed'] = function ($url) { return ['code' => 503, 'body' => '']; };
$data = $block->refresh(FEED, 3);
check('a failed refresh keeps the old list and retries later', 3 === count($data['episodes']) && $data['time'] < time() - Block::FRESH + Block::RETRY + 2);
$GLOBALS['calls'] = [];
$GLOBALS['editor'] = true;
$html = $block->render(['feedUrl' => 'https://other.example/rss', 'season' => 3]);
check('a new feed that fails shows the reason to editors', false !== strpos($html, 'the feed could not be loaded (HTTP error 503)'));
$block->render(['feedUrl' => 'https://other.example/rss', 'season' => 3]);
check('the failure is remembered, not refetched on every view', 1 === count($GLOBALS['calls']));
$GLOBALS['feed'] = function ($url) { return new WP_Error('<b>timeout</b>'); };
$html = $block->render(['feedUrl' => 'https://third.example/rss', 'season' => 3]);
check('network errors escaped', false !== strpos($html, '(&lt;b&gt;timeout&lt;/b&gt;)'));
$GLOBALS['feed'] = function ($url) use (&$xml) { return ['code' => 200, 'body' => $xml]; };
check('empty season: editor notice', false !== strpos($block->render(['feedUrl' => 'https://fourth.example/rss', 'season' => 9]), 'no episodes in season 9'));
$GLOBALS['editor'] = false;
check('empty season: nothing for visitors', '' === $block->render(['feedUrl' => 'https://fourth.example/rss', 'season' => 9]));
check('invalid cron arguments do nothing', isset($block->refresh('javascript:x', 3)['error']) && isset($block->refresh(FEED, 'x')['error']));

$big = str_repeat(' ', Block::MAX_FEED_BYTES);
$GLOBALS['feed'] = function ($url) use (&$big) { return ['code' => 200, 'body' => $big]; };
$GLOBALS['editor'] = true;
check('a feed cut at the size limit says so', false !== strpos($block->render(['feedUrl' => 'https://huge.example/rss', 'season' => 3]), 'the feed is larger than 10 MB'));
$GLOBALS['editor'] = false;
unset($big);
$GLOBALS['feed'] = function ($url) use (&$xml) { return ['code' => 200, 'body' => $xml]; };
$GLOBALS['scheduled'] = [];
$block->refresh(FEED, 3);
check('a refresh asks for the cover copy once', [[Block::COVER_HOOK, [$key]]] === array_values(array_filter($GLOBALS['scheduled'], function ($e) { return Block::COVER_HOOK === $e[0]; })));

// Cover: resized once into uploads, then used.
$GLOBALS['feed'] = function ($url) {
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
    return ['code' => 200, 'body' => $png . str_repeat("\0", 200)];
};
$block->saveCover('../../etc/passwd');
$block->saveCover('alps_gb_pod_nothex');
check('bad cover keys ignored', ! is_dir(COVER_BASE));
$block->saveCover($key);
$name = md5('https://cdn.example/season3.jpg') . '-240';
check('cover resized to 240 px square and saved as WebP', [240, 240, true] === $GLOBALS['resized'] && is_file(COVER_BASE . "/alps-podcast/$name.webp"));
check('downloaded original removed', [] === glob(COVER_BASE . '/alps-podcast/*.tmp') && [] === glob(COVER_BASE . '/alps-podcast/*.part.*'));
$html = $block->render($attributes);
check('cover shown from uploads, lazy and sized', false !== strpos($html, '<img class="alps-pod__cover" src="https://example.test/wp-content/uploads/alps-podcast/' . $name . '.webp" width="240" height="240" alt="" loading="lazy" decoding="async">'));
$html = $block->render($attributes + ['showCover' => false]);
check('cover can be turned off', false === strpos($html, '<img'));
$GLOBALS['calls'] = [];
$block->saveCover($key);
check('an existing copy is not downloaded again', [] === $GLOBALS['calls']);
unlink(COVER_BASE . "/alps-podcast/$name.webp");
$GLOBALS['format'] = 'avif';
$block->saveCover($key);
check('a site that converts output formats keeps the right extension', is_file(COVER_BASE . "/alps-podcast/$name.avif"));
unlink(COVER_BASE . "/alps-podcast/$name.avif");
$GLOBALS['feed'] = function ($url) { return ['code' => 200, 'body' => '<html>' . str_repeat('x', 200) . '</html>']; };
$block->saveCover($key);
check('a non-image cover is not saved', [] === glob(COVER_BASE . '/alps-podcast/*'));
$coverError = 'alps_gb_pod_cover_' . md5('https://cdn.example/season3.jpg');
check('a failed cover is remembered for a day', isset($GLOBALS['transients'][$coverError]));
$GLOBALS['calls'] = [];
$GLOBALS['scheduled'] = [];
$block->render($attributes);
$block->refresh(FEED, 3);
$GLOBALS['calls'] = [];
$block->saveCover($key);
check('a failed cover is not scheduled or downloaded again on every view', [] === array_filter($GLOBALS['scheduled'], function ($e) { return Block::COVER_HOOK === $e[0]; }) && [] === $GLOBALS['calls']);
unset($GLOBALS['transients'][$coverError]);
$GLOBALS['format'] = '';
$GLOBALS['feed'] = function ($url) {
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
    return ['code' => 200, 'body' => $png . str_repeat("\0", 200)];
};
$old = COVER_BASE . '/alps-podcast/' . md5('old') . '-240.webp';
$recent = COVER_BASE . '/alps-podcast/' . md5('recent') . '-240.webp';
$stray = COVER_BASE . '/alps-podcast/notes.txt';
foreach ([$old, $recent, $stray] as $file) { file_put_contents($file, 'x'); }
touch($old, time() - 100 * DAY_IN_SECONDS);
touch($stray, time() - 100 * DAY_IN_SECONDS);
$block->saveCover($key);
check('saving a cover removes copies unused for 90 days, keeps recent ones and other files', ! is_file($old) && is_file($recent) && is_file($stray) && is_file(COVER_BASE . "/alps-podcast/$name.webp"));
foreach (glob(COVER_BASE . '/alps-podcast/*') as $file) { unlink($file); }
@rmdir(COVER_BASE . '/alps-podcast');
@rmdir(COVER_BASE);

// Every translatable PHP string has a Lithuanian translation.
$source = file_get_contents(dirname(__DIR__) . '/src/podcast-season/class-podcast-season-block.php');
preg_match_all("/(?:__|esc_html__|esc_attr__)\\('((?:[^'\\\\]|\\\\.)*)'/", $source, $m);
$translations = include dirname(__DIR__) . '/languages/alps-gutenberg-blocks-lt_LT.l10n.php';
$missing = array_diff(array_map('stripslashes', $m[1]), array_keys($translations['messages']));
check('all ' . count($m[1]) . ' PHP strings have a Lithuanian translation' . ($missing ? ': missing ' . implode(' | ', $missing) : ''), ! $missing && count($m[1]) > 10);

echo $failures ? "$failures check(s) failed\n" : "All checks passed\n";
exit($failures ? 1 : 0);
