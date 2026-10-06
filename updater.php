<?php
namespace ALPS\Gutenberg;

/**
 * Offers updates from this fork's GitHub releases.
 *
 * Only releases of kiritoshiro/adventistai-alps-gutenberg-blocks are used, so
 * upstream (cdn.adventist.org) releases are never offered over the fork. The
 * repository is public, so no token is needed. A fine-grained, read-only token
 * in wp-config.php is optional; it raises the GitHub API rate limit:
 * define( 'ALPS_GUTENBERG_GITHUB_TOKEN', 'github_pat_...' );
 */
class PluginUpdater
{
    const REPOSITORY = 'kiritoshiro/adventistai-alps-gutenberg-blocks';

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
        $this->cacheKey = $name . '_github_release';
    }

    public function init()
    {
        add_filter('plugins_api', [$this, 'pluginInfo'], 20, 3);
        add_filter('site_transient_update_plugins', [$this, 'checkUpdate']);
        add_filter('http_request_args', [$this, 'assetRequestArgs'], 10, 2);
        add_action('upgrader_process_complete', [$this, 'afterUpdate'], 10, 2);
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
     * Prepare requests to this repository's release-asset endpoint, the package URL.
     *
     * The endpoint only returns the ZIP (via a redirect) when asked for
     * application/octet-stream; otherwise it returns JSON metadata. The
     * optional token is added for this endpoint only.
     */
    public function assetRequestArgs($args, $url)
    {
        $host = wp_parse_url($url, PHP_URL_HOST);
        $path = wp_parse_url($url, PHP_URL_PATH);

        if ('api.github.com' === $host && is_string($path) && 0 === strpos($path, '/repos/' . self::REPOSITORY . '/releases/assets/')) {
            $args['headers'] = isset($args['headers']) && is_array($args['headers']) ? $args['headers'] : [];
            $args['headers']['Accept']               = 'application/octet-stream';
            $args['headers']['X-GitHub-Api-Version'] = '2022-11-28';

            $token = $this->token();
            if ($token) {
                $args['headers']['Authorization'] = 'Bearer ' . $token;
            }
        }

        return $args;
    }

    public function afterUpdate($upgrader_object, $options)
    {
        if (isset($options['action'], $options['type']) && 'update' === $options['action'] && 'plugin' === $options['type']) {
            delete_transient($this->cacheKey);
            $this->release = null;
        }
    }

    /**
     * @return array|null version, package, html_url, published_at and notes.
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
        $headers = [
            'Accept'               => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => '2022-11-28',
        ];
        $token = $this->token();
        if ($token) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        $response = wp_remote_get('https://api.github.com/repos/' . self::REPOSITORY . '/releases/latest', [
            'timeout' => 10,
            'headers' => $headers,
        ]);
        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            return [];
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($data) || empty($data['tag_name']) || !empty($data['draft']) || !empty($data['prerelease'])) {
            return [];
        }

        if (!preg_match('/^v(\d+\.\d+\.\d+)$/', (string) $data['tag_name'], $match)) {
            return [];
        }
        $version = $match[1];

        // Built by devtools/scripts/plugin/release.js.
        $assetName = $this->name . '-v' . $version . '.zip';
        $package   = '';
        foreach ((array) ($data['assets'] ?? []) as $asset) {
            if (isset($asset['name'], $asset['url']) && $assetName === $asset['name']) {
                $package = esc_url_raw($asset['url']);
                break;
            }
        }
        if (!$package) {
            return [];
        }

        return [
            'version'      => $version,
            'package'      => $package,
            'html_url'     => isset($data['html_url']) ? esc_url_raw($data['html_url']) : 'https://github.com/' . self::REPOSITORY,
            'published_at' => isset($data['published_at']) ? (string) $data['published_at'] : '',
            'notes'        => isset($data['body']) ? (string) $data['body'] : '',
        ];
    }

    private function token()
    {
        return defined('ALPS_GUTENBERG_GITHUB_TOKEN') ? trim((string) ALPS_GUTENBERG_GITHUB_TOKEN) : '';
    }
}
