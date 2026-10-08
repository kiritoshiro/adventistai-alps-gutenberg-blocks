<?php
/**
 * PluginUpdater: "Check again" on Dashboard → Updates skips the release cache.
 * Run: php tests/updater.php
 */

namespace {
    $GLOBALS['transients'] = [];
    $GLOBALS['can'] = true;
    $GLOBALS['latest'] = '3.1.4';
    $GLOBALS['fetches'] = 0;

    function add_filter() {}
    function add_action() {}
    function get_transient($key) { return $GLOBALS['transients'][$key] ?? false; }
    function set_transient($key, $value, $ttl) { $GLOBALS['transients'][$key] = $value; }
    function delete_transient($key) { unset($GLOBALS['transients'][$key]); }
    function current_user_can($cap) { return 'update_plugins' === $cap && $GLOBALS['can']; }
    function is_wp_error($value) { return false; }
    function esc_url_raw($url) { return $url; }
    function wp_remote_retrieve_response_code($response) { return 200; }
    function wp_remote_retrieve_body($response) { return $response['body']; }
    function wp_remote_get($url, $args)
    {
        $GLOBALS['fetches']++;
        $v = $GLOBALS['latest'];
        return ['body' => json_encode([
            'tag_name' => 'v' . $v,
            'html_url' => 'https://github.com/kiritoshiro/adventistai-alps-gutenberg-blocks/releases/tag/v' . $v,
            'assets'   => [['name' => 'alps-gutenberg-blocks-v' . $v . '.zip', 'url' => 'https://api.github.com/repos/kiritoshiro/adventistai-alps-gutenberg-blocks/releases/assets/1']],
        ])];
    }

    require dirname(__DIR__) . '/updater.php';

    $failures = 0;
    function check($label, $condition)
    {
        global $failures;
        echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
        $failures += $condition ? 0 : 1;
    }

    function offered($updater)
    {
        $t = (object) ['checked' => ['alps-gutenberg-blocks/plugin.php' => '3.1.4'], 'response' => []];
        $t = $updater->checkUpdate($t);
        return $t->response['alps-gutenberg-blocks/plugin.php']->new_version ?? null;
    }

    $updater = new ALPS\Gutenberg\PluginUpdater('alps-gutenberg-blocks', '3.1.4');
    check('current release: nothing offered', null === offered($updater));

    // A release is published; the cached answer still says 3.1.4.
    $GLOBALS['latest'] = '3.1.5';
    $updater = new ALPS\Gutenberg\PluginUpdater('alps-gutenberg-blocks', '3.1.4');
    check('cache hides a new release', null === offered($updater) && 1 === $GLOBALS['fetches']);

    $updater->forceCheck();
    check('plain Updates screen keeps the cache', null === offered($updater) && 1 === $GLOBALS['fetches']);

    $_GET['force-check'] = '1';
    $GLOBALS['can'] = false;
    $updater->forceCheck();
    check('Check again needs update_plugins', null === offered($updater) && 1 === $GLOBALS['fetches']);

    $GLOBALS['can'] = true;
    $updater->forceCheck();
    check('Check again finds the new release', '3.1.5' === offered($updater) && 2 === $GLOBALS['fetches']);
    unset($_GET['force-check']);

    echo $failures ? "$failures check(s) failed\n" : "All checks passed\n";
    exit($failures ? 1 : 0);
}
