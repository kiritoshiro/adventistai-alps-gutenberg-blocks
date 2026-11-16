<?php
// Standalone renderer regressions for Vienas balsas; real WordPress/editor checks are separate.
define('ABSPATH', __DIR__ . '/');
define('ALPS_GUTENBERG_VERSION', 'test');
$GLOBALS['enqueued'] = [];
$GLOBALS['images'] = [];
function wp_register_style($h, $url, $deps, $v) { $GLOBALS['registeredStyle'] = [$h, $url]; }
function plugins_url($path, $plugin) { return 'https://example.test/' . $path; }
function register_block_type($path, $args) { $GLOBALS['registeredBlock'] = [$path, $args]; }
function wp_enqueue_style($h) { $GLOBALS['enqueued'][] = $h; }
function get_block_wrapper_attributes($attrs) { return 'class="wp-block-alps-gutenberg-blocks-vienas-balsas ' . esc_attr($attrs['class']) . '" style="' . esc_attr($attrs['style']) . '"'; }
// Attachment 7 is an image whose Media Library alt text is "Mama & kūdikis"; 8 is not an image.
function wp_get_attachment_image($id, $size, $icon, $attrs) {
    $GLOBALS['images'][] = [$id, $size, $attrs];
    return 7 === $id ? '<img src="https://example.test/m.jpg" alt="Mama &amp; kūdikis" class="' . esc_attr($attrs['class']) . '" loading="lazy" srcset="https://example.test/m-300.jpg 300w" sizes="' . esc_attr($attrs['sizes']) . '">' : '';
}
function wp_strip_all_tags($s) { return trim(strip_tags($s)); }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_attr($s) { return esc_html($s); }
// Close to WordPress: keep allowed characters, refuse other protocols, http:// for scheme-less hosts.
function esc_url($url, $protocols = null) {
    $url = preg_replace('|[^a-z0-9-~+_.?#=!&;,/:%@$\|*\'()\[\]\x80-\xff]|i', '', str_replace(' ', '%20', $url));
    if ('' === $url) { return ''; }
    if (strpos($url, ':') === false && ! in_array($url[0], ['/', '#', '?'], true)) { $url = 'http://' . $url; }
    if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', $url, $m) && ! in_array(strtolower($m[1]), $protocols ?: ['http', 'https'], true)) { return ''; }
    return esc_attr($url);
}
require dirname(__DIR__) . '/src/vienas-balsas/class-vienas-balsas-block.php';
use ALPS\Gutenberg\Blocks\VienasBalsasBlock;
$block = new VienasBalsasBlock();
$failures = 0;
$checks = 0;
function check($label, $ok) { global $failures, $checks; ++$checks; $failures += $ok ? 0 : 1; echo ($ok ? 'PASS ' : 'FAIL ') . $label . "\n"; }

$block->init();
check('registers metadata and its own stylesheet', basename($GLOBALS['registeredBlock'][0]) === 'block.json' && $GLOBALS['registeredStyle'] === ['alps-gb-vienas-balsas', 'https://example.test/dist/vienas-balsas.css']);

// Defaults (WordPress passes them from block.json; a direct call reads the same file).
$html = $block->render([]);
check('only the block stylesheet is enqueued', $GLOBALS['enqueued'] === ['alps-gb-vienas-balsas']);
check('default heading is an h2', strpos($html, '<h2 class="alps-vb__heading">Vienas balsas 2027</h2>') !== false);
check('two default project cards with h3 titles', substr_count($html, 'class="alps-vb__card"') === 2 && strpos($html, '<h3 class="alps-vb__title">Marijos sūnaus gyvenimas</h3>') !== false && strpos($html, '<h3 class="alps-vb__title">Biblijinis išgelbėjimo planas</h3>') !== false);
check('default texts', strpos($html, 'Klausykite trumpų knygos skyrių santraukų.') !== false && strpos($html, '>Audio santraukos</p>') !== false && strpos($html, '>Schema</p>') !== false);
check('call-to-action card on by default', strpos($html, 'class="alps-vb__cta"') !== false && strpos($html, 'Įgarsinkite santrauką') !== false && strpos($html, '„Marijos sūnaus gyvenimas“') !== false);
check('no links until addresses are set', strpos($html, '<a ') === false);
check('no image without a Media Library item', strpos($html, '<img') === false && $GLOBALS['images'] === []);
check('layout: three columns, two on tablets', strpos($html, 'alps-vb--cards-2 alps-vb--has-cta') !== false && strpos($html, '--alps-vb-cols:3;--alps-vb-tablet-cols:2;') !== false);

// Edited content with links and images.
$attrs = [
    'heading'       => 'Vienas balsas 2027',
    'headingLevel'  => 3,
    'linkText'      => 'Kaip prisidėti',
    'linkUrl'       => '/vienas-balsas/kaip-prisideti/',
    'moreText'      => 'Daugiau',
    'cards'         => [
        ['imageId' => 7, 'eyebrow' => 'Audio santraukos', 'title' => 'Marijos sūnaus gyvenimas', 'text' => 'Tekstas', 'url' => 'https://adventistai.lt/audio/'],
        ['imageId' => '8', 'eyebrow' => 'Schema', 'title' => 'Planas', 'text' => '', 'url' => '?p=12#schema'],
    ],
    'ctaButtonText' => 'Registruotis',
    'ctaUrl'        => 'https://forms.example.org/registracija?x=1&y=2',
];
$html = $block->render($attrs);
check('heading level setting; card titles one level lower', strpos($html, '<h3 class="alps-vb__heading">') !== false && substr_count($html, '<h4 class="alps-vb__title">') === 3);
check('heading link with a decorative arrow', strpos($html, '<a class="alps-vb__head-link" href="/vienas-balsas/kaip-prisideti/">Kaip prisidėti<span class="alps-vb__arrow" aria-hidden="true">→</span></a>') !== false);
check('"Daugiau" names its card for screen readers', strpos($html, '<a class="alps-vb__more" href="https://adventistai.lt/audio/">Daugiau<span class="alps-vb__sr">: Marijos sūnaus gyvenimas</span><span class="alps-vb__arrow" aria-hidden="true">→</span></a>') !== false);
check('relative query/fragment address kept', strpos($html, 'href="?p=12#schema"') !== false);
check('button link escaped', strpos($html, '<a class="alps-vb__button" href="https://forms.example.org/registracija?x=1&amp;y=2">Registruotis</a>') !== false);
check('image from the Media Library with its alt text, lazy and responsive', strpos($html, '<div class="alps-vb__media"><img src="https://example.test/m.jpg" alt="Mama &amp; kūdikis"') !== false && $GLOBALS['images'][0][1] === 'medium_large' && $GLOBALS['images'][0][2]['loading'] === 'lazy' && strpos($GLOBALS['images'][0][2]['sizes'], '(max-width: 600px)') === 0);
check('non-image attachment leaves no empty frame', substr_count($html, 'alps-vb__media') === 1 && $GLOBALS['images'][1][0] === 8);
check('empty card text leaves no empty paragraph', strpos($html, '<p class="alps-vb__text"></p>') === false);

// URLs: only http(s) and relative addresses.
foreach ([
    'javascript:alert(1)', 'JaVaScRiPt:alert(1)', ' javascript:alert(1)', "java\tscript:alert(1)", 'javascript&colon;alert(1)', 'javascript&#58;alert(1)',
    'data:text/html,<script>alert(1)</script>', 'vbscript:x', 'mailto:a@b.lt', 'ftp://example.org/', '//evil.example/', '/\\evil.example/', '\\\\evil.example',
    'https:evil.example', 'http:/evil.example', "https://exa\x01mple.org/", "https://example.org/\x7f/", [], ['https://example.org/'], null, false,
] as $bad) {
    check('rejected URL ' . json_encode($bad), VienasBalsasBlock::url($bad) === '');
}
foreach ([
    'https://adventistai.lt/x/' => 'https://adventistai.lt/x/', 'HTTP://Example.org' => 'HTTP://Example.org', '/kaip-prisideti/' => '/kaip-prisideti/',
    '#forma' => '#forma', '?page_id=5' => '?page_id=5', ' https://example.org/a b ' => 'https://example.org/a%20b',
    'https://example.org/?a=1&amp;b=2' => 'https://example.org/?a=1&amp;b=2',
] as $good => $expected) {
    check('allowed URL ' . $good, VienasBalsasBlock::url($good) === $expected);
}

// Hostile and broken attributes.
$html = $block->render([
    'heading'      => '<script>alert(1)</script>Antraštė <b>"x"</b>',
    'headingLevel' => '1; DROP',
    'linkText'     => ['x'],
    'linkUrl'      => 'javascript:alert(1)',
    'moreText'     => '<img src=x onerror=alert(1)>Daugiau',
    'cards'        => [
        'not a card',
        ['imageId' => 'abc', 'title' => '"><svg onload=alert(1)>', 'eyebrow' => ['x'], 'text' => "a\n\n  b", 'url' => 'data:text/html,x'],
        ['title' => 'Antra', 'url' => 'https://example.org/" onmouseover="alert(1)'],
        ['title' => 'Trečia'], ['title' => 'Ketvirta'],
    ],
    'showCta'      => 'false',
    'ctaUrl'       => 'https://example.org/',
]);
check('markup is stripped or escaped', strpos($html, '<script') === false && strpos($html, '<svg') === false && strpos($html, '<img') === false && strpos($html, 'onerror') === false);
check('heading text kept as plain text', strpos($html, 'Antraštė &quot;x&quot;') !== false);
check('invalid heading level falls back to h2', strpos($html, '<h2 class="alps-vb__heading">') !== false);
check('non-scalar link text uses the default; bad link URL drops the link', strpos($html, 'alps-vb__head-link') === false);
check('javascript and data URLs never reach an href', stripos($html, 'javascript:') === false && stripos($html, 'data:') === false);
check('quotes in URLs cannot break out of the attribute', strpos($html, '" onmouseover=') === false);
check('at most three cards; non-array cards skipped', substr_count($html, 'class="alps-vb__card"') === 3 && strpos($html, 'Ketvirta') === false && strpos($html, 'not a card') === false);
check('whitespace collapsed in texts', strpos($html, '>a b</p>') !== false);
check('call-to-action card can be switched off', strpos($html, 'alps-vb__cta') === false && strpos($html, 'alps-vb--has-cta') === false);
check('three cards without the call-to-action card: three columns', strpos($html, 'alps-vb--cards-3"') !== false && strpos($html, '--alps-vb-cols:3;--alps-vb-tablet-cols:2;') !== false);
$html = $block->render(['cards' => [['title' => 'Viena']]]);
check('one card plus the call-to-action card: two columns', strpos($html, '--alps-vb-cols:2;--alps-vb-tablet-cols:2;') !== false && strpos($html, 'alps-vb--cards-1 alps-vb--has-cta') !== false);
$html = $block->render(['cards' => 'broken', 'showCta' => false, 'heading' => '', 'linkUrl' => '/x/']);
check('a broken card list falls back to the defaults', substr_count($html, 'class="alps-vb__card"') === 2);
check('empty heading: no empty heading element, link row still shown', strpos($html, 'alps-vb__heading') === false && strpos($html, 'alps-vb__head-link') !== false);
// Review finding 2: without a section heading, card titles take the configured level
// (no H2 → H3 gap) and the panel is a <div>, not an unnamed <section>.
check('empty heading: card titles use the configured level (h2)', substr_count($html, '<h2 class="alps-vb__title">') === 2 && strpos($html, '<h3') === false);
check('empty heading: wrapper is a div, not a section', strpos($html, '<div class="wp-block-alps-gutenberg-blocks-vienas-balsas alps-vb') === 0 && strpos($html, '<section') === false && substr($html, -6) === '</div>');
$html = $block->render(['heading' => " \n <b></b> ", 'headingLevel' => 4]);
check('whitespace-only heading at level 4: card and call-to-action titles are h4', substr_count($html, '<h4 class="alps-vb__title">') === 3 && strpos($html, '<h5') === false && strpos($html, '<section') === false);
$html = $block->render(['headingLevel' => 3]);
check('with a heading the wrapper is a section and titles are one level lower', strpos($html, '<section class="wp-block-alps-gutenberg-blocks-vienas-balsas alps-vb') === 0 && substr($html, -10) === '</section>' && strpos($html, '<h3 class="alps-vb__heading">') !== false && substr_count($html, '<h4 class="alps-vb__title">') === 3);
// Review finding 3: at least one project card, whatever the saved list holds.
$GLOBALS['enqueued'] = [];
$html = $block->render(['cards' => [], 'showCta' => false]);
check('an empty card list renders the default cards, never an empty panel', substr_count($html, 'class="alps-vb__card"') === 2 && strpos($html, 'Marijos sūnaus gyvenimas') !== false && strpos($html, 'alps-vb--cards-2"') !== false && strpos($html, 'alps-vb--cards-0') === false);
check('an empty card list without the call-to-action card still has columns', strpos($html, '--alps-vb-cols:2;--alps-vb-tablet-cols:2;') !== false);
$html = $block->render(['cards' => ['x', 5, null, true], 'showCta' => false]);
check('a list with no usable card renders the default cards', substr_count($html, 'class="alps-vb__card"') === 2 && strpos($html, 'alps-vb--cards-0') === false);
check('cards() never returns an empty list', count(VienasBalsasBlock::cards(['cards' => []])) === 2 && count(VienasBalsasBlock::cards([])) === 2);
// Re-review: entries that are not cards are dropped, the same as isCard() in edit.js.
foreach (['[null]' => [null], '["broken"]' => ['broken'], '[[]]' => [[]], '[[1, 2]]' => [[1, 2]], '[{"foo":1}]' => [['foo' => 1]], '[false, 0, ""]' => [false, 0, '']] as $label => $list) {
    $html = $block->render(['cards' => $list]);
    check('cards ' . $label . ' render the two default cards', substr_count($html, 'class="alps-vb__card"') === 2 && strpos($html, 'Biblijinis išgelbėjimo planas') !== false && strpos($html, 'broken') === false);
}
$cards = VienasBalsasBlock::cards(['cards' => [null, 'broken', ['title' => 'Tikra'], [], ['url' => '/x/']]]);
check('mixed list keeps only the real cards, in order', count($cards) === 2 && $cards[0]['title'] === 'Tikra' && $cards[1]['url'] === '/x/');
check('isCard() needs an array with a card field', VienasBalsasBlock::isCard(['imageId' => 0]) && ! VienasBalsasBlock::isCard([]) && ! VienasBalsasBlock::isCard(null) && ! VienasBalsasBlock::isCard('x') && ! VienasBalsasBlock::isCard(['other' => 1]));
check('null attributes are safe', strpos($block->render(null), 'Vienas balsas 2027') !== false);

echo "$checks checks, $failures failures\n";
exit($failures ? 1 : 0);
