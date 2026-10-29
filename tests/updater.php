<?php
/**
 * PluginUpdater: "Check again" on Dashboard → Updates skips the release cache;
 * a release is offered only with a SHA-256 digest, and its download is
 * verified against it, with the token kept to api.github.com.
 * Run: php tests/updater.php
 */

namespace {
    const REPO = 'kiritoshiro/adventistai-alps-gutenberg-blocks';
    const TOKEN = 'github_pat_test_only';
    define('ALPS_GUTENBERG_GITHUB_TOKEN', TOKEN);

    $GLOBALS['transients'] = [];
    $GLOBALS['can'] = true;
    $GLOBALS['latest'] = '3.1.4';
    $GLOBALS['fetches'] = 0;
    $GLOBALS['zip'] = "PK\x03\x04" . str_repeat('package bytes ', 50);
    $GLOBALS['served'] = $GLOBALS['zip'];
    $GLOBALS['asset'] = [];
    $GLOBALS['location'] = 'https://release-assets.githubusercontent.com/github-production-release-asset/1?sig=x';
    $GLOBALS['requests'] = [];

    class WP_Error
    {
        public $code;
        public function __construct($code = '', $message = '') { $this->code = $code; }
    }

    function add_filter() {}
    function add_action() {}
    function get_transient($key) { return $GLOBALS['transients'][$key] ?? false; }
    function set_transient($key, $value, $ttl) { $GLOBALS['transients'][$key] = $value; }
    function delete_transient($key) { unset($GLOBALS['transients'][$key]); }
    function current_user_can($cap) { return 'update_plugins' === $cap && $GLOBALS['can']; }
    function is_wp_error($value) { return $value instanceof WP_Error; }
    function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }
    function wp_remote_retrieve_response_code($response) { return $response['code']; }
    function wp_remote_retrieve_body($response) { return $response['body']; }
    function wp_remote_retrieve_header($response, $name) { return $response['headers'][$name] ?? ''; }
    function wp_tempnam($name) { return tempnam(sys_get_temp_dir(), 'alps'); }
    function wp_delete_file($file) { @unlink($file); }

    /** The GitHub API, its asset redirect and the download host. */
    function wp_remote_get($url, $args)
    {
        $GLOBALS['requests'][] = [$url, $args['headers'] ?? [], $args['redirection'] ?? null];
        if ('https://api.github.com/repos/' . REPO . '/releases/latest' === $url) {
            $GLOBALS['fetches']++;
            $v = $GLOBALS['latest'];
            $asset = $GLOBALS['asset'] + [
                'id'                   => 681755886,
                'name'                 => 'alps-gutenberg-blocks-v' . $v . '.zip',
                'state'                => 'uploaded',
                'url'                  => 'https://api.github.com/repos/' . REPO . '/releases/assets/681755886',
                'browser_download_url' => 'https://github.com/' . REPO . '/releases/download/v' . $v . '/alps-gutenberg-blocks-v' . $v . '.zip',
                'size'                 => strlen($GLOBALS['zip']),
                'digest'               => 'sha256:' . hash('sha256', $GLOBALS['zip']),
            ];
            return ['code' => 200, 'body' => json_encode([
                'tag_name' => 'v' . $v,
                'html_url' => 'https://github.com/' . REPO . '/releases/tag/v' . $v,
                'assets'   => [array_filter($asset, function ($value) { return null !== $value; })],
            ])];
        }
        if ('https://api.github.com/repos/' . REPO . '/releases/assets/681755886' === $url) {
            return ['code' => 302, 'headers' => ['location' => $GLOBALS['location']], 'body' => ''];
        }
        file_put_contents($args['filename'], $GLOBALS['served']);
        return ['code' => 200, 'headers' => [], 'body' => ''];
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
        return $t->response['alps-gutenberg-blocks/plugin.php'] ?? null;
    }

    function version($updater)
    {
        $offer = offered($updater);
        return $offer ? $offer->new_version : null;
    }

    /** A fresh updater with an empty release cache. */
    function fresh()
    {
        $GLOBALS['transients'] = [];
        return new ALPS\Gutenberg\PluginUpdater('alps-gutenberg-blocks', '3.1.4');
    }

    $updater = new ALPS\Gutenberg\PluginUpdater('alps-gutenberg-blocks', '3.1.4');
    check('current release: nothing offered', null === version($updater));

    // A release is published; the cached answer still says 3.1.4.
    $GLOBALS['latest'] = '3.1.5';
    $updater = new ALPS\Gutenberg\PluginUpdater('alps-gutenberg-blocks', '3.1.4');
    check('cache hides a new release', null === version($updater) && 1 === $GLOBALS['fetches']);

    $updater->forceCheck();
    check('plain Updates screen keeps the cache', null === version($updater) && 1 === $GLOBALS['fetches']);

    $_GET['force-check'] = '1';
    $GLOBALS['can'] = false;
    $updater->forceCheck();
    check('Check again needs update_plugins', null === version($updater) && 1 === $GLOBALS['fetches']);

    $GLOBALS['can'] = true;
    $updater->forceCheck();
    check('Check again finds the new release', '3.1.5' === version($updater) && 2 === $GLOBALS['fetches']);
    unset($_GET['force-check']);

    // Only a release whose asset GitHub lists with a SHA-256 digest is offered.
    $package = 'https://github.com/' . REPO . '/releases/download/v3.1.5/alps-gutenberg-blocks-v3.1.5.zip';
    check('the package is the release download URL', $package === offered(fresh())->package);
    $lookup = $GLOBALS['requests'][count($GLOBALS['requests']) - 1];
    check('the release lookup follows no redirect', 0 === $lookup[2]);
    $GLOBALS['asset'] = ['digest' => null];
    check('no digest: nothing offered', null === offered(fresh()));
    $GLOBALS['asset'] = ['digest' => 'sha256:' . str_repeat('A', 64)];
    check('malformed digest: nothing offered', null === offered(fresh()));
    $GLOBALS['asset'] = ['digest' => 'sha1:' . str_repeat('a', 40)];
    check('other algorithm: nothing offered', null === offered(fresh()));
    $GLOBALS['asset'] = ['browser_download_url' => 'https://evil.test/alps-gutenberg-blocks-v3.1.5.zip'];
    check('download on another host: nothing offered', null === offered(fresh()));
    $GLOBALS['asset'] = ['url' => 'https://api.github.com/repos/someone/else/releases/assets/681755886'];
    check('asset of another repository: nothing offered', null === offered(fresh()));
    $GLOBALS['asset'] = ['state' => 'starter'];
    check('asset still uploading: nothing offered', null === offered(fresh()));
    $GLOBALS['asset'] = ['size' => 30000000];
    check('oversized asset: nothing offered', null === offered(fresh()));
    $GLOBALS['asset'] = [];

    // The download: token to api.github.com only, one redirect to GitHub's host, digest checked.
    $updater = fresh();
    $GLOBALS['requests'] = [];
    $file = $updater->download(false, $package);
    check('a matching package is installed', is_string($file) && $GLOBALS['zip'] === file_get_contents($file));
    @unlink($file);
    check('two requests: the API asset, then the download host', 3 === count($GLOBALS['requests'])
        && 0 === strpos($GLOBALS['requests'][1][0], 'https://api.github.com/repos/' . REPO . '/releases/assets/')
        && $GLOBALS['location'] === $GLOBALS['requests'][2][0]);
    check('the token goes to api.github.com', 'Bearer ' . TOKEN === ($GLOBALS['requests'][1][1]['Authorization'] ?? ''));
    check('the token is not sent to the download host', ! isset($GLOBALS['requests'][2][1]['Authorization']) && false === strpos(json_encode($GLOBALS['requests'][2]), TOKEN));
    check('neither request follows redirects itself', 0 === $GLOBALS['requests'][1][2] && 0 === $GLOBALS['requests'][2][2]);

    $GLOBALS['served'] = "PK\x03\x04" . str_repeat('tampered byte ', 50);
    $GLOBALS['served'] = substr($GLOBALS['served'], 0, strlen($GLOBALS['zip']));
    $temp = count(glob(sys_get_temp_dir() . '/alps*'));
    check('a package with another SHA-256 is refused', is_wp_error($updater->download(false, $package)));
    check('and its temporary file removed', count(glob(sys_get_temp_dir() . '/alps*')) === $temp);
    $GLOBALS['served'] = $GLOBALS['zip'] . 'x';
    check('a package of another size is refused', is_wp_error($updater->download(false, $package)));
    $GLOBALS['served'] = $GLOBALS['zip'];

    foreach (['https://evil.test/asset.zip', 'http://release-assets.githubusercontent.com/a', 'https://user:pass' . '@objects.githubusercontent.com/a', // Split so the secret scanner doesn't take it for a real credential. 'https://release-assets.githubusercontent.com:8443/a', 'https://release-assets.githubusercontent.com.evil.test/a'] as $location) {
        $GLOBALS['location'] = $location;
        $GLOBALS['requests'] = [];
        $result = $updater->download(false, $package);
        check('redirect to ' . $location . ' refused, not requested', is_wp_error($result) && 1 === count($GLOBALS['requests']));
    }
    $GLOBALS['location'] = 'https://objects.githubusercontent.com/github-production-release-asset/1?sig=x';
    $file = $updater->download(false, $package);
    check('the older download host works too', is_string($file));
    @unlink($file);

    $GLOBALS['requests'] = [];
    check('other plugins\' packages are left alone', false === $updater->download(false, 'https://downloads.wordpress.org/plugin/x.zip') && ! $GLOBALS['requests']);
    check('an earlier handler\'s answer is kept', 'handled' === $updater->download('handled', $package));
    check('an unverified package of this repository is refused', is_wp_error($updater->download(false, 'https://github.com/' . REPO . '/releases/download/v9.9.9/alps-gutenberg-blocks-v9.9.9.zip')));
    check('the API asset URL earlier versions offered is refused', is_wp_error($updater->download(false, 'https://api.github.com/repos/' . REPO . '/releases/assets/681755886')));

    // A release cached by an earlier version (no digest) is not used.
    $GLOBALS['transients'] = ['alps-gutenberg-blocks_github_release' => ['version' => '3.1.9', 'package' => 'https://api.github.com/repos/' . REPO . '/releases/assets/1', 'html_url' => '', 'published_at' => '', 'notes' => '']];
    $updater = new ALPS\Gutenberg\PluginUpdater('alps-gutenberg-blocks', '3.1.4');
    check('a release cached without a digest is fetched again', '3.1.5' === version($updater));

    echo $failures ? "$failures check(s) failed\n" : "All checks passed\n";
    exit($failures ? 1 : 0);
}
