<?php
namespace ALPS\Gutenberg;

/**
 * Offers updates from this fork's GitHub releases.
 *
 * Only releases of kiritoshiro/adventistai-alps-gutenberg-blocks are used, so
 * upstream (cdn.adventist.org) releases are never offered over the fork. The
 * repository is public, so no token is needed. A fine-grained, read-only token
 * in wp-config.php is optional; it raises the GitHub API rate limit and is
 * only ever sent to api.github.com:
 * define( 'ALPS_GUTENBERG_GITHUB_TOKEN', 'github_pat_...' );
 *
 * A release is offered only when GitHub lists a SHA-256 digest for its ZIP,
 * and the download is installed only when it matches that digest.
 */
class PluginUpdater
{
    const REPOSITORY = 'kiritoshiro/adventistai-alps-gutenberg-blocks';

    /** Largest package accepted; a release is about 70 KB. */
    const MAX_PACKAGE_SIZE = 20971520;

    /** Hosts GitHub redirects a release-asset download to (signed URLs, no token needed). */
    const DOWNLOAD_HOSTS = ['release-assets.githubusercontent.com', 'objects.githubusercontent.com'];

    private $name;
    private $version;
    private $cacheKey;
    private $cacheTtl = 3600;
    private $errorTtl = 600;

    /** @var array|null Latest release; an empty array caches a failed lookup. */
    private $release = null;

    public function __construct($name, $version)
    {
        $this->name     = $name;
        $this->version  = $version;
        // The suffix changes whenever the cached release shape does.
        $this->cacheKey = $name . '_github_release_v2';
    }

    public function init()
    {
        add_filter('plugins_api', [$this, 'pluginInfo'], 20, 3);
        add_filter('site_transient_update_plugins', [$this, 'checkUpdate']);
        add_filter('upgrader_pre_download', [$this, 'download'], 10, 3);
        add_action('upgrader_process_complete', [$this, 'afterUpdate'], 10, 2);
        add_action('load-update-core.php', [$this, 'forceCheck'], 9);
    }

    /**
     * "Check again" on Dashboard → Updates (force-check=1) only forces the core
     * check; drop the cached release so the newest one is fetched on that page.
     */
    public function forceCheck()
    {
        if (empty($_GET['force-check']) || !current_user_can('update_plugins')) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only cache refresh.
            return;
        }
        delete_transient($this->cacheKey);
        $this->release = null;
    }

    public function pluginInfo($res, $action, $args)
    {
        if ('plugin_information' !== $action || !isset($args->slug) || $this->name !== $args->slug) {
            return $res;
        }

        $release = $this->latestRelease();
        if (!$release) {
            return $res;
        }

        $info = new \stdClass();
        $info->name          = 'ALPS Gutenberg Blocks';
        $info->slug          = $this->name;
        $info->version       = $release['version'];
        $info->download_link = $release['package'];
        $info->homepage      = $release['html_url'];
        $info->last_updated  = $release['published_at'];
        $info->sections      = [
            'description' => 'Creates custom blocks in Gutenberg specific to the ALPS v3 theme.',
            'changelog'   => wpautop(esc_html($release['notes'])),
        ];

        return $info;
    }

    public function checkUpdate($transient)
    {
        if (!is_object($transient) || empty($transient->checked)) {
            return $transient;
        }

        $release = $this->latestRelease();
        if ($release && version_compare($this->version, $release['version'], '<')) {
            $res = new \stdClass();
            $res->slug        = $this->name;
            $res->plugin      = $this->name . '/plugin.php';
            $res->new_version = $release['version'];
            $res->url         = $release['html_url'];
            $res->package     = $release['package'];
            $transient->response[$res->plugin] = $res;
        }

        return $transient;
    }

    /**
     * Downloads the package WordPress was offered, and returns it only when
     * its size, ZIP header and SHA-256 match what GitHub lists for the asset
     * (a release without a digest is never offered; see fetchLatestRelease()).
     * The asset is requested from the API, the only request that may carry
     * the optional token. Its one redirect must go to a DOWNLOAD_HOSTS URL,
     * which is requested without the token and may not redirect again.
     *
     * @return mixed $reply for other packages, else the file path or a WP_Error.
     */
    public function download($reply, $package, $upgrader = null)
    {
        if (false !== $reply || !is_string($package) || !$this->isOwnPackage($package)) {
            return $reply;
        }
        $release = $this->latestRelease();
        if (!$release || $package !== $release['package']) {
            return new \WP_Error('alps_gb_update_unverified', 'The ALPS Gutenberg Blocks release could not be verified with GitHub. Check for updates again later.');
        }

        if (!function_exists('wp_tempnam')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        $file = wp_tempnam($this->name . '.zip');
        if (!$file) {
            return new \WP_Error('alps_gb_update_tempfile', 'Could not create a temporary file for the update.');
        }
        $options = [
            'timeout'             => 60,
            'redirection'         => 0,
            'reject_unsafe_urls'  => true,
            'stream'              => true,
            'filename'            => $file,
            'limit_response_size' => $release['size'] + 1,
            'headers'             => $this->headers('application/octet-stream'),
        ];
        $response = wp_remote_get('https://api.github.com/repos/' . self::REPOSITORY . '/releases/assets/' . $release['asset_id'], $options);
        $code = is_wp_error($response) ? 0 : (int) wp_remote_retrieve_response_code($response);
        if ($code >= 300 && $code < 400) {
            $location = wp_remote_retrieve_header($response, 'location');
            if (!$this->isDownloadHost($location)) {
                wp_delete_file($file);
                return new \WP_Error('alps_gb_update_redirect', 'GitHub sent the update download to an unexpected host.');
            }
            unset($options['headers']);
            $response = wp_remote_get($location, $options);
            $code = is_wp_error($response) ? 0 : (int) wp_remote_retrieve_response_code($response);
        }

        clearstatcache(true, $file);
        if (200 !== $code
            || !is_file($file)
            || filesize($file) !== $release['size']
            || "PK\x03\x04" !== file_get_contents($file, false, null, 0, 4)
            || !hash_equals($release['digest'], (string) hash_file('sha256', $file))) {
            wp_delete_file($file);
            return new \WP_Error('alps_gb_update_invalid', 'The update package could not be downloaded or failed its size, ZIP or SHA-256 check, so it was not installed.');
        }

        return $file;
    }

    public function afterUpdate($upgrader_object, $options)
    {
        if (isset($options['action'], $options['type']) && 'update' === $options['action'] && 'plugin' === $options['type']) {
            delete_transient($this->cacheKey);
            $this->release = null;
        }
    }

    /** A package URL in this repository's releases (also the API asset URLs earlier versions offered). */
    private function isOwnPackage($url)
    {
        return 0 === strpos($url, 'https://github.com/' . self::REPOSITORY . '/releases/download/')
            || 0 === strpos($url, 'https://api.github.com/repos/' . self::REPOSITORY . '/releases/assets/');
    }

    /** An https URL on one of DOWNLOAD_HOSTS, without credentials or a port. */
    private function isDownloadHost($url)
    {
        $parts = is_string($url) ? wp_parse_url($url) : false;
        return is_array($parts)
            && 'https' === ($parts['scheme'] ?? '')
            && in_array(strtolower($parts['host'] ?? ''), self::DOWNLOAD_HOSTS, true)
            && !isset($parts['user']) && !isset($parts['pass']) && !isset($parts['port']);
    }

    /**
     * @return array|null version, package, asset_id, size, digest, html_url, published_at and notes.
     */
    private function latestRelease()
    {
        if (null !== $this->release) {
            return $this->release ?: null;
        }

        $cached = get_transient($this->cacheKey);
        if (is_array($cached)) {
            $this->release = $cached;
            return $this->release ?: null;
        }

        $this->release = $this->fetchLatestRelease();
        set_transient($this->cacheKey, $this->release, $this->release ? $this->cacheTtl : $this->errorTtl);

        return $this->release ?: null;
    }

    private function fetchLatestRelease()
    {
        $response = wp_remote_get('https://api.github.com/repos/' . self::REPOSITORY . '/releases/latest', [
            'timeout'             => 10,
            'redirection'         => 0,
            'limit_response_size' => 1048576,
            'headers'             => $this->headers('application/vnd.github+json'),
        ]);
        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            return [];
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($data) || empty($data['tag_name']) || !empty($data['draft']) || !empty($data['prerelease'])) {
            return [];
        }

        if (!preg_match('/^v(\d+\.\d+\.\d+)$/D', (string) $data['tag_name'], $match)) {
            return [];
        }
        $version = $match[1];

        // Uploaded by .github/workflows/publish.yml. GitHub lists a digest for
        // every uploaded asset; a release without a valid one is not offered.
        $tag       = 'v' . $version;
        $assetName = $this->name . '-' . $tag . '.zip';
        $package   = 'https://github.com/' . self::REPOSITORY . '/releases/download/' . $tag . '/' . $assetName;
        $asset     = null;
        foreach ((array) ($data['assets'] ?? []) as $candidate) {
            if (is_array($candidate) && $assetName === ($candidate['name'] ?? null)) {
                $asset = $candidate;
                break;
            }
        }
        $id     = $asset['id'] ?? 0;
        $size   = $asset['size'] ?? 0;
        $digest = $asset['digest'] ?? '';
        if (!$asset
            || 'uploaded' !== ($asset['state'] ?? 'uploaded')
            || !is_int($id) || $id <= 0
            || 'https://api.github.com/repos/' . self::REPOSITORY . '/releases/assets/' . $id !== ($asset['url'] ?? '')
            || $package !== ($asset['browser_download_url'] ?? '')
            || !is_int($size) || $size <= 0 || $size > self::MAX_PACKAGE_SIZE
            || !is_string($digest) || !preg_match('/^sha256:[a-f0-9]{64}$/D', $digest)) {
            return [];
        }

        return [
            'version'      => $version,
            'package'      => $package,
            'asset_id'     => $id,
            'size'         => $size,
            'digest'       => substr($digest, 7),
            'html_url'     => 'https://github.com/' . self::REPOSITORY . '/releases/tag/' . $tag,
            'published_at' => isset($data['published_at']) ? (string) $data['published_at'] : '',
            'notes'        => isset($data['body']) ? (string) $data['body'] : '',
        ];
    }

    /** GitHub API headers with the optional token; used only for requests to api.github.com. */
    private function headers($accept)
    {
        $headers = [
            'Accept'               => $accept,
            'X-GitHub-Api-Version' => '2022-11-28',
        ];
        $token = $this->token();
        if ($token) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }
        return $headers;
    }

    private function token()
    {
        return defined('ALPS_GUTENBERG_GITHUB_TOKEN') ? trim((string) ALPS_GUTENBERG_GITHUB_TOKEN) : '';
    }
}
