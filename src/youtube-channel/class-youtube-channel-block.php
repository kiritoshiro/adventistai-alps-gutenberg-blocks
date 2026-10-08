<?php
namespace ALPS\Gutenberg\Blocks;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Server-side rendering for the YouTube Channel Videos block.
 *
 * The video list comes from the YouTube Data API on the server and is cached in
 * an option, so the API key never reaches the browser and visitors make no API
 * requests. The page carries only thumbnails; view.js loads the YouTube player
 * after a visitor presses play. Stale lists are served while WP-Cron refreshes
 * them, so only the very first render of a channel waits for the API.
 */
class YouTubeChannelBlock
{
    /** Videos kept per channel; the editor offers the same maximum. */
    const MAX_VIDEOS = 25;

    /** Shorts can last up to 3 minutes and the Data API does not mark them. */
    const SHORTS_MAX_SECONDS = 180;

    /** Upload pages (50 each) searched for enough non-Shorts videos. */
    const MAX_PAGES = 4;

    /** Seconds a list is fresh, and the wait before retrying a failed fetch. */
    const FRESH = 3600;
    const RETRY = 600;

    const OPTION = 'alps_gb_youtube_api_key';
    const REFRESH_HOOK = 'alps_gb_youtube_channel_refresh';
    const THUMBS_HOOK = 'alps_gb_youtube_channel_thumbs';
    const HANDLE = 'alps-gb-youtube-channel';

    /**
     * Thumbnails are copied into uploads/alps-ytc/, a few at a time by
     * WP-Cron. YouTube serves them with a 2-hour cache lifetime, the site's
     * uploads with a long one, and the browser needs no extra connection.
     * Until a copy exists the page uses YouTube's address.
     */
    const THUMBS_DIR = 'alps-ytc';
    const THUMBS_PER_RUN = 40;
    const THUMBS_MAX_BYTES = 600000;

    /** Copies unused for this long are removed; a page still showing one copies it again. */
    const THUMBS_KEEP_DAYS = 90;

    /** Set while rendering when a thumbnail has no local copy yet. */
    private static $missingThumbs = false;

    const VIDEO_ID = '/^[A-Za-z0-9_-]{11}$/D';

    /** Linkable profiles besides the channel itself, in display order. */
    const NETWORKS = ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'x' => 'X'];

    public function init()
    {
        $pluginFile = dirname(__DIR__, 2) . '/plugin.php';
        wp_register_style(self::HANDLE, plugins_url('dist/youtube-channel.css', $pluginFile), [], ALPS_GUTENBERG_VERSION);
        wp_register_script(
            self::HANDLE,
            plugins_url('dist/youtube-channel.js', $pluginFile),
            [],
            ALPS_GUTENBERG_VERSION,
            ['in_footer' => true, 'strategy' => 'defer']
        );
        register_block_type(__DIR__ . '/block.json', [
            'render_callback' => [$this, 'render'],
        ]);
        wp_add_inline_script('alps-gb', 'window.alpsGbYouTube = ' . wp_json_encode(['hasKey' => '' !== self::apiKey(), 'maxVideos' => self::MAX_VIDEOS]) . ';', 'before');

        add_action(self::REFRESH_HOOK, [$this, 'refresh'], 10, 2);
        add_action(self::THUMBS_HOOK, [$this, 'saveThumbs']);
        add_action('admin_init', [$this, 'registerSetting']);
    }

    /**
     * The key in use and where it comes from: the ALPS_YOUTUBE_API_KEY
     * constant, this plugin's setting, then the WP YouTube plugin's constant
     * and setting. ['', ''] when none is valid.
     *
     * @return array [key, source]
     */
    public static function keySource()
    {
        $keys = [
            [defined('ALPS_YOUTUBE_API_KEY') ? constant('ALPS_YOUTUBE_API_KEY') : '', __('the ALPS_YOUTUBE_API_KEY constant in wp-config.php', 'alps-gutenberg-blocks')],
            [get_option(self::OPTION, ''), __('Settings → Media', 'alps-gutenberg-blocks')],
            [defined('WPY_YOUTUBE_API_KEY') ? constant('WPY_YOUTUBE_API_KEY') : '', __('the WPY_YOUTUBE_API_KEY constant in wp-config.php', 'alps-gutenberg-blocks')],
            [get_option('wpy_youtube_api_key', ''), __('the WP YouTube plugin settings', 'alps-gutenberg-blocks')],
        ];
        foreach ($keys as $entry) {
            if (is_string($entry[0]) && preg_match('/^[A-Za-z0-9_-]{20,128}$/D', $entry[0])) {
                return $entry;
            }
        }
        return ['', ''];
    }

    public static function apiKey()
    {
        return self::keySource()[0];
    }

    /** "…abcd from Settings → Media", so editors can tell which key was used without seeing it. */
    public static function keyLabel()
    {
        list($key, $source) = self::keySource();
        /* translators: 1: last four characters of the API key, 2: where the key is set */
        return '' === $key ? '' : sprintf(__('key …%1$s from %2$s', 'alps-gutenberg-blocks'), substr($key, -4), $source);
    }

    public function registerSetting()
    {
        register_setting('media', self::OPTION, [
            'type'              => 'string',
            'default'           => '',
            'sanitize_callback' => [$this, 'sanitizeKey'],
        ]);
        add_settings_section('alps_gb_youtube', __('YouTube channel block', 'alps-gutenberg-blocks'), '__return_false', 'media');
        add_settings_field('alps_gb_youtube_key', __('YouTube Data API key', 'alps-gutenberg-blocks'), [$this, 'keyField'], 'media', 'alps_gb_youtube', ['label_for' => 'alps-gb-youtube-key']);
    }

    public function sanitizeKey($value)
    {
        $value = is_string($value) ? trim($value) : '';
        if ('' === $value || preg_match('/^[A-Za-z0-9_-]{20,128}$/D', $value)) {
            return $value;
        }
        add_settings_error(self::OPTION, 'invalid', __('That is not a YouTube Data API key. The previous key was kept.', 'alps-gutenberg-blocks'));
        return (string) get_option(self::OPTION, '');
    }

    public function keyField()
    {
        printf(
            '<input id="alps-gb-youtube-key" name="%1$s" type="password" value="%2$s" class="regular-text" autocomplete="off" spellcheck="false">',
            esc_attr(self::OPTION),
            esc_attr((string) get_option(self::OPTION, ''))
        );
        echo '<p class="description">' . esc_html__('Used by the YouTube Channel Videos block on the server only; visitors never see it. Use a key restricted to the YouTube Data API v3. If empty, the WP YouTube plugin\'s key is used.', 'alps-gutenberg-blocks') . '</p>';
        $label = self::keyLabel();
        /* translators: %s: e.g. "key …abcd from Settings → Media" */
        echo '<p class="description"><strong>' . esc_html('' !== $label ? sprintf(__('In use: %s.', 'alps-gutenberg-blocks'), $label) : __('No key is set.', 'alps-gutenberg-blocks')) . '</strong></p>';
    }

    /**
     * ['id' => 'UC…'] or ['handle' => 'name'] from a channel ID, an @handle or
     * a youtube.com channel link; [] for anything else.
     *
     * @param mixed $value Channel attribute.
     * @return array
     */
    public static function parseChannel($value)
    {
        $value = trim(html_entity_decode(is_scalar($value) ? (string) $value : '', ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $handle = '[\p{L}\p{N}._-]{3,30}';
        if (preg_match('/^UC[A-Za-z0-9_-]{22}$/D', $value)) {
            return ['id' => $value];
        }
        if (preg_match('/^@(' . $handle . ')$/uD', $value, $m)) {
            return ['handle' => $m[1]];
        }
        $parts = wp_parse_url($value);
        $host = is_array($parts) && isset($parts['host']) ? strtolower($parts['host']) : '';
        $scheme = is_array($parts) && isset($parts['scheme']) ? strtolower($parts['scheme']) : '';
        if (! in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true) || ! in_array($scheme, ['http', 'https'], true)) {
            return [];
        }
        $path = rawurldecode(isset($parts['path']) ? $parts['path'] : '');
        if (preg_match('#^/channel/(UC[A-Za-z0-9_-]{22})(?:/|$)#D', $path, $m)) {
            return ['id' => $m[1]];
        }
        if (preg_match('#^/@(' . $handle . ')(?:/|$)#uD', $path, $m)) {
            return ['handle' => $m[1]];
        }
        return [];
    }

    /** Public URL of the channel. */
    public static function channelUrl(array $channel)
    {
        return isset($channel['id'])
            ? 'https://www.youtube.com/channel/' . $channel['id']
            : 'https://www.youtube.com/@' . rawurlencode($channel['handle']);
    }

    /** Seconds in an ISO 8601 duration such as PT1H2M3S; 0 for live streams or bad input. */
    public static function seconds($duration)
    {
        if (! is_string($duration) || ! preg_match('/^P(?:(\d+)D)?(?:T(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?)?$/D', $duration, $m)) {
            return 0;
        }
        $m = array_map('intval', array_pad($m, 5, 0));
        return $m[1] * 86400 + $m[2] * 3600 + $m[3] * 60 + $m[4];
    }

    /**
     * A playable video from a videos.list item, or null for private, upcoming,
     * non-embeddable and (optionally) Shorts-length videos.
     *
     * @param mixed $video         videos.list item.
     * @param bool  $excludeShorts Drop videos of 3 minutes or less.
     * @return array|null
     */
    public static function video($video, $excludeShorts)
    {
        if (! is_array($video) || ! isset($video['id']) || ! is_string($video['id']) || ! preg_match(self::VIDEO_ID, $video['id'])) {
            return null;
        }
        $id = $video['id'];
        $snippet = isset($video['snippet']) && is_array($video['snippet']) ? $video['snippet'] : [];
        $status = isset($video['status']) && is_array($video['status']) ? $video['status'] : [];
        $privacy = isset($status['privacyStatus']) ? $status['privacyStatus'] : '';
        if (! in_array($privacy, ['public', 'unlisted'], true) || (isset($status['embeddable']) && false === $status['embeddable'])) {
            return null;
        }
        if (isset($snippet['liveBroadcastContent']) && 'upcoming' === $snippet['liveBroadcastContent']) {
            return null;
        }
        $seconds = self::seconds(isset($video['contentDetails']['duration']) ? $video['contentDetails']['duration'] : '');
        if ($excludeShorts && $seconds > 0 && $seconds <= self::SHORTS_MAX_SECONDS) {
            return null;
        }
        // medium is 16:9; high and standard are 4:3 with black bars that object-fit: cover crops off.
        $thumbs = [];
        foreach (['medium' => 320, 'high' => 480, 'standard' => 640, 'maxres' => 1280] as $size => $width) {
            $url = isset($snippet['thumbnails'][$size]['url']) ? $snippet['thumbnails'][$size]['url'] : '';
            if (is_string($url) && preg_match('#^https://i\.ytimg\.com/vi/' . preg_quote($id, '#') . '/[a-z]+\.jpg$#D', $url)) {
                $thumbs[$width] = $url;
            }
        }
        if (! $thumbs) {
            $thumbs[320] = 'https://i.ytimg.com/vi/' . $id . '/mqdefault.jpg';
        }
        $published = isset($snippet['publishedAt']) && is_string($snippet['publishedAt']) ? strtotime($snippet['publishedAt']) : false;
        return [
            'id'        => $id,
            'title'     => sanitize_text_field(isset($snippet['title']) && is_string($snippet['title']) ? $snippet['title'] : ''),
            'published' => $published ? gmdate('Y-m-d\TH:i:s\Z', $published) : '',
            'seconds'   => $seconds,
            'thumbs'    => $thumbs,
        ];
    }

    /**
     * One Data API request. Throws with a short reason on failure; the reason
     * never contains the request URL, so the key cannot leak into messages.
     */
    private static function api($endpoint, array $query)
    {
        $query['key'] = self::apiKey();
        $url = add_query_arg(array_map('rawurlencode', $query), 'https://www.googleapis.com/youtube/v3/' . $endpoint);
        // The site's address as Referer, so a key restricted to this website works too.
        $response = wp_remote_get($url, ['timeout' => 8, 'headers' => ['Accept' => 'application/json', 'Referer' => home_url('/')]]);
        if (is_wp_error($response)) {
            throw new \RuntimeException(__('YouTube did not respond', 'alps-gutenberg-blocks')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- caught in refresh(); shown only through notice(), which escapes.
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        $data = json_decode((string) wp_remote_retrieve_body($response), true);
        if (200 !== $code || ! is_array($data)) {
            $message = is_array($data) && isset($data['error']['message']) && is_string($data['error']['message']) ? $data['error']['message'] : '';
            throw new \RuntimeException(trim('HTTP ' . $code . ' ' . mb_substr(wp_strip_all_tags($message), 0, 160))); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- caught in refresh(); shown only through notice(), which escapes.
        }
        return $data;
    }

    /**
     * The channel's title and newest videos from the Data API.
     *
     * @param array $channel       From parseChannel().
     * @param bool  $excludeShorts Drop videos of 3 minutes or less.
     * @return array ['title' => string, 'videos' => array]
     */
    public static function fetch(array $channel, $excludeShorts)
    {
        $lookup = isset($channel['id']) ? ['id' => $channel['id']] : ['forHandle' => '@' . $channel['handle']];
        $found = self::api('channels', ['part' => 'snippet,contentDetails'] + $lookup);
        $item = isset($found['items'][0]) && is_array($found['items'][0]) ? $found['items'][0] : [];
        $uploads = isset($item['contentDetails']['relatedPlaylists']['uploads']) ? $item['contentDetails']['relatedPlaylists']['uploads'] : '';
        if (! is_string($uploads) || ! preg_match('/^UU[A-Za-z0-9_-]{22}$/D', $uploads)) {
            throw new \RuntimeException(__('channel not found', 'alps-gutenberg-blocks')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- caught in refresh(); shown only through notice(), which escapes.
        }

        $videos = [];
        $token = '';
        for ($page = 0; $page < self::MAX_PAGES && count($videos) < self::MAX_VIDEOS; $page++) {
            $query = ['part' => 'contentDetails', 'playlistId' => $uploads, 'maxResults' => '50'];
            if ('' !== $token) {
                $query['pageToken'] = $token;
            }
            $list = self::api('playlistItems', $query);
            $ids = [];
            foreach (isset($list['items']) && is_array($list['items']) ? $list['items'] : [] as $entry) {
                $id = isset($entry['contentDetails']['videoId']) ? $entry['contentDetails']['videoId'] : '';
                if (is_string($id) && preg_match(self::VIDEO_ID, $id)) {
                    $ids[$id] = true;
                }
            }
            if ($ids) {
                $details = self::api('videos', ['part' => 'snippet,contentDetails,status', 'id' => implode(',', array_keys($ids)), 'maxResults' => '50']);
                foreach (isset($details['items']) && is_array($details['items']) ? $details['items'] : [] as $entry) {
                    $video = self::video($entry, $excludeShorts);
                    if ($video) {
                        $videos[$video['id']] = $video;
                    }
                }
            }
            $token = isset($list['nextPageToken']) && is_string($list['nextPageToken']) ? $list['nextPageToken'] : '';
            if ('' === $token) {
                break;
            }
        }

        // Newest first by publication date, not by when they were added to the uploads list.
        $videos = array_values($videos);
        usort($videos, function ($a, $b) {
            return strcmp($b['published'], $a['published']);
        });
        return [
            'title'  => sanitize_text_field(isset($item['snippet']['title']) && is_string($item['snippet']['title']) ? $item['snippet']['title'] : ''),
            'videos' => array_slice($videos, 0, self::MAX_VIDEOS),
        ];
    }

    private static function cacheKey(array $channel, $excludeShorts)
    {
        return 'alps_gb_ytc_' . md5((string) wp_json_encode([$channel, (bool) $excludeShorts]));
    }

    /** Where a failed fetch is remembered. Tied to the key, so a new key retries at once. */
    private static function errorKey($key)
    {
        return $key . '_error_' . substr(md5(self::apiKey()), 0, 8);
    }

    /**
     * Cached data for a channel. A stale list is returned at once and refreshed
     * by WP-Cron; only a channel with no list yet is fetched during the render.
     *
     * @return array ['title', 'videos', 'time'] or ['error' => string]
     */
    public function data(array $channel, $excludeShorts)
    {
        $key = self::cacheKey($channel, $excludeShorts);
        $cached = get_option($key);
        if (is_array($cached) && isset($cached['time'], $cached['videos'])) {
            $args = [$channel, (bool) $excludeShorts];
            if ($cached['time'] < time() - self::FRESH && ! wp_next_scheduled(self::REFRESH_HOOK, $args)) {
                wp_schedule_single_event(time(), self::REFRESH_HOOK, $args);
            }
            return $cached;
        }
        $error = get_transient(self::errorKey($key));
        if (is_string($error) && '' !== $error) {
            return ['error' => $error];
        }
        return $this->refresh($channel, $excludeShorts);
    }

    /**
     * Fetches and stores a channel's list. On failure an existing list is kept
     * and retried after RETRY seconds; with no list, the error is remembered
     * for RETRY seconds so page views do not keep calling the API.
     *
     * @param mixed $channel       From parseChannel() (or a WP-Cron argument).
     * @param mixed $excludeShorts Drop videos of 3 minutes or less.
     * @return array
     */
    public function refresh($channel, $excludeShorts)
    {
        $channel = self::parseChannel(is_array($channel) ? (isset($channel['id']) ? $channel['id'] : '@' . (isset($channel['handle']) ? $channel['handle'] : '')) : '');
        if (! $channel || '' === self::apiKey()) {
            return ['error' => __('no API key or channel', 'alps-gutenberg-blocks')];
        }
        $key = self::cacheKey($channel, $excludeShorts);
        $cached = get_option($key);
        try {
            $data = self::fetch($channel, (bool) $excludeShorts) + ['time' => time()];
        } catch (\RuntimeException $e) {
            if (! is_array($cached) || ! isset($cached['videos'])) {
                set_transient(self::errorKey($key), $e->getMessage(), self::RETRY);
                return ['error' => $e->getMessage()];
            }
            $data = $cached;
            $data['time'] = time() - self::FRESH + self::RETRY;
        }
        update_option($key, $data, false);
        delete_transient(self::errorKey($key));
        if (! wp_next_scheduled(self::THUMBS_HOOK, [$key])) {
            wp_schedule_single_event(time(), self::THUMBS_HOOK, [$key]);
        }
        return $data;
    }

    /**
     * Where a YouTube thumbnail URL is copied: [path, url], or null for any
     * other URL or when uploads are unavailable. The name comes from the
     * validated video ID and image name only.
     */
    private static function localThumb($url)
    {
        if (! is_string($url) || ! preg_match('#^https://i\.ytimg\.com/vi/([A-Za-z0-9_-]{11})/([a-z]+)\.jpg$#D', $url, $m)) {
            return null;
        }
        $uploads = wp_upload_dir(null, false);
        if (! empty($uploads['error']) || empty($uploads['basedir']) || empty($uploads['baseurl'])) {
            return null;
        }
        $name = $m[1] . '-' . $m[2] . '.jpg';
        return [
            trailingslashit($uploads['basedir']) . self::THUMBS_DIR . '/' . $name,
            trailingslashit($uploads['baseurl']) . self::THUMBS_DIR . '/' . $name,
        ];
    }

    /** The local copy's URL when it exists, else YouTube's (and the render schedules a copy). */
    private static function thumbUrl($url)
    {
        $local = self::localThumb($url);
        if ($local && is_file($local[0])) {
            return $local[1];
        }
        self::$missingThumbs = true;
        return $url;
    }

    /**
     * WP-Cron: copies the thumbnails of one cached list (the sizes the block
     * shows: up to 640 px, and 1280 px for the first video's player in a wide
     * block), THUMBS_PER_RUN at a time, then removes copies unused for
     * THUMBS_KEEP_DAYS.
     *
     * @param mixed $key Cache key from cacheKey().
     */
    public function saveThumbs($key)
    {
        if (! is_string($key) || ! preg_match('/^alps_gb_ytc_[a-f0-9]{32}$/D', $key)) {
            return;
        }
        $data = get_option($key);
        if (! is_array($data) || empty($data['videos']) || ! is_array($data['videos'])) {
            return;
        }
        $left = self::THUMBS_PER_RUN;
        $keep = [];
        foreach (array_values($data['videos']) as $index => $video) {
            $thumbs = is_array($video) && isset($video['thumbs']) && is_array($video['thumbs']) ? $video['thumbs'] : [];
            foreach ($thumbs as $width => $url) {
                $local = self::localThumb($url);
                if (! $local || ((int) $width > 640 && 0 !== $index)) {
                    continue;
                }
                $keep[basename($local[0])] = true;
                if (is_file($local[0])) {
                    continue;
                }
                if ($left-- <= 0) {
                    // The rest in the next run.
                    wp_schedule_single_event(time() + 60, self::THUMBS_HOOK, [$key]);
                    return;
                }
                self::download($url, $local[0]);
            }
        }
        self::prune($keep);
    }

    /** Saves a JPEG from i.ytimg.com; false when the response is not one. */
    private static function download($url, $path)
    {
        $response = wp_remote_get($url, ['timeout' => 8, 'redirection' => 0, 'limit_response_size' => self::THUMBS_MAX_BYTES]);
        if (is_wp_error($response) || 200 !== (int) wp_remote_retrieve_response_code($response)) {
            return false;
        }
        $body = (string) wp_remote_retrieve_body($response);
        $info = strlen($body) >= 100 && strlen($body) < self::THUMBS_MAX_BYTES && function_exists('getimagesizefromstring') ? @getimagesizefromstring($body) : false;
        if (! is_array($info) || IMAGETYPE_JPEG !== $info[2] || ! wp_mkdir_p(dirname($path))) {
            return false;
        }
        // Written beside the target and renamed, so a page never links a half-written file.
        $temporary = $path . '.' . wp_generate_password(8, false) . '.tmp';
        if (false === file_put_contents($temporary, $body)) { // Fixed directory, validated name.
            return false;
        }
        if (! rename($temporary, $path)) {
            wp_delete_file($temporary);
            return false;
        }
        return true;
    }

    /** Removes copies older than THUMBS_KEEP_DAYS that the list just saved does not use. */
    private static function prune(array $keep)
    {
        $uploads = wp_upload_dir(null, false);
        if (! empty($uploads['error']) || empty($uploads['basedir'])) {
            return;
        }
        $limit = time() - self::THUMBS_KEEP_DAYS * DAY_IN_SECONDS;
        foreach (glob(trailingslashit($uploads['basedir']) . self::THUMBS_DIR . '/*.jpg') ?: [] as $file) {
            if (! isset($keep[basename($file)]) && filemtime($file) < $limit) {
                wp_delete_file($file);
            }
        }
    }

    /** A message for people who can edit the page; visitors get nothing. */
    private static function notice($message)
    {
        if (! current_user_can('edit_posts')) {
            return '';
        }
        return '<p class="alps-ytc-notice" role="note">' . esc_html($message) . '</p>';
    }

    /** "1:02:03" or "4:05"; '' for live streams. */
    public static function duration($seconds)
    {
        $seconds = (int) $seconds;
        if ($seconds <= 0) {
            return '';
        }
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;
        return $h ? sprintf('%d:%02d:%02d', $h, $m, $s) : sprintf('%d:%02d', $m, $s);
    }

    /** <img> for a video's thumbnails, offering widths up to $maxWidth. */
    private static function image(array $video, $sizes, $maxWidth)
    {
        $candidates = [];
        foreach ($video['thumbs'] as $width => $url) {
            if ($width <= $maxWidth || ! $candidates) {
                $candidates[$width] = $url;
            }
        }
        ksort($candidates);
        $candidates = array_map([__CLASS__, 'thumbUrl'], $candidates);
        $srcset = [];
        foreach ($candidates as $width => $url) {
            $srcset[] = esc_url($url) . ' ' . (int) $width . 'w';
        }
        return sprintf(
            '<img src="%s" srcset="%s" sizes="%s" width="1280" height="720" alt="" loading="lazy" decoding="async">',
            esc_url(reset($candidates)),
            esc_attr(implode(', ', $srcset)),
            esc_attr($sizes)
        );
    }

    /** Inline brand icons: Simple Icons (CC0) for YouTube, Font Awesome Free 6 (CC BY 4.0) for the rest. */
    private static function icon($network)
    {
        $icons = [
            'youtube'   => ['0 0 24 24', 'M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z'],
            'facebook'  => ['0 0 320 512', 'M279.1 288l14.2-92.7h-88.9v-68.1c0-25.4 12.4-50.1 52.2-50.1H297V6.3S260.4 0 225.4 0c-73.2 0-121.1 44.4-121.1 124.7v70.6H22.9V288h81.4v224h100.2V288h74.6z'],
            'instagram' => ['0 0 448 512', 'M224.1 141c-63.6 0-114.9 51.3-114.9 114.9s51.3 114.9 114.9 114.9S339 319.5 339 255.9 287.7 141 224.1 141zm0 189.6c-41.1 0-74.7-33.5-74.7-74.7s33.5-74.7 74.7-74.7 74.7 33.5 74.7 74.7-33.6 74.7-74.7 74.7zm146.4-194.3c0 14.9-12 26.8-26.8 26.8-14.9 0-26.8-12-26.8-26.8s12-26.8 26.8-26.8 26.8 12 26.8 26.8zm76.1 27.2c-1.7-35.9-9.9-67.7-36.2-93.9-26.2-26.2-58-34.4-93.9-36.2-37-2.1-147.9-2.1-184.9 0-35.8 1.7-67.6 9.9-93.9 36.1S3.3 127.5 1.5 163.4c-2.1 37-2.1 147.9 0 184.9 1.7 35.9 9.9 67.7 36.2 93.9s58 34.4 93.9 36.2c37 2.1 147.9 2.1 184.9 0 35.9-1.7 67.7-9.9 93.9-36.2 26.2-26.2 34.4-58 36.2-93.9 2.1-37 2.1-147.8 0-184.8zM398.8 388c-7.8 19.6-22.9 34.7-42.6 42.6-29.5 11.7-99.5 9-132.1 9s-102.7 2.6-132.1-9c-19.6-7.8-34.7-22.9-42.6-42.6-11.7-29.5-9-99.5-9-132.1s-2.6-102.7 9-132.1c7.8-19.6 22.9-34.7 42.6-42.6 29.5-11.7 99.5-9 132.1-9s102.7-2.6 132.1 9c19.6 7.8 34.7 22.9 42.6 42.6 11.7 29.5 9 99.5 9 132.1s2.7 102.7-9 132.1z'],
            'tiktok'    => ['0 0 448 512', 'M448 209.9a210.1 210.1 0 0 1-122.8-39.2v178.7A162.6 162.6 0 1 1 185 188.3v89.9a74.6 74.6 0 1 0 52.2 71.2V0h88a121.2 121.2 0 0 0 1.9 22.2A122.2 122.2 0 0 0 381 102.2a121.4 121.4 0 0 0 67 20.1v87.6z'],
            'x'         => ['0 0 512 512', 'M389.2 48h70.6L305.6 224.2 487 464H345L233.8 318.6 106.5 464H35.8l164.9-188.5L26.8 48H172.4l100.5 132.9L389.2 48zm-24.8 373.8h39.1L151.1 88h-42l255.3 333.8z'],
            'play'      => ['0 0 24 24', 'M8 5v14l11-7z'],
            'prev'      => ['0 0 24 24', 'M15.41 7.41 14 6l-6 6 6 6 1.41-1.41L10.83 12z'],
            'next'      => ['0 0 24 24', 'M10 6 8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z'],
        ];
        return '<svg viewBox="' . $icons[$network][0] . '" aria-hidden="true" focusable="false"><path d="' . $icons[$network][1] . '"/></svg>';
    }

    /**
     * Renders the block on the server.
     *
     * @param array $attributes The block attributes.
     * @return string
     */
    public function render($attributes)
    {
        $attributes = is_array($attributes) ? $attributes : [];
        $channel = self::parseChannel(isset($attributes['channel']) ? $attributes['channel'] : '');
        if (! $channel) {
            return self::notice(__('YouTube channel: enter a channel link, @handle or channel ID.', 'alps-gutenberg-blocks'));
        }
        if ('' === self::apiKey()) {
            return self::notice(__('YouTube channel: add a YouTube Data API key under Settings → Media.', 'alps-gutenberg-blocks'));
        }
        $excludeShorts = ! isset($attributes['excludeShorts']) || ! empty($attributes['excludeShorts']);
        $data = $this->data($channel, $excludeShorts);
        if (empty($data['videos'])) {
            $reason = ! empty($data['error']) ? $data['error'] : __('no videos found', 'alps-gutenberg-blocks');
            /* translators: 1: reason, such as "HTTP 403 API key not valid.", 2: e.g. "key …abcd from Settings → Media" */
            $message = sprintf(__('YouTube channel: the videos could not be loaded (%1$s; %2$s).', 'alps-gutenberg-blocks'), $reason, self::keyLabel());
            if (false !== stripos($reason, 'referer')) {
                /* translators: %s: this site's address */
                $message .= ' ' . sprintf(__('This key has a website restriction in Google Cloud. Set "Application restrictions" to None, or add %s to its websites.', 'alps-gutenberg-blocks'), home_url('/*'));
            }
            return self::notice($message);
        }

        $count = isset($attributes['count']) && is_numeric($attributes['count']) ? (int) $attributes['count'] : 10;
        $videos = array_slice($data['videos'], 0, max(1, min(self::MAX_VIDEOS, $count)));
        $first = $videos[0];
        $title = isset($attributes['title']) && is_string($attributes['title']) && '' !== trim($attributes['title']) ? trim($attributes['title']) : $data['title'];
        $links = isset($attributes['links']) && is_array($attributes['links']) ? $attributes['links'] : [];

        wp_enqueue_style(self::HANDLE);
        wp_enqueue_script(self::HANDLE);

        /* translators: %s: video title */
        $playLabel = __('Play: %s', 'alps-gutenberg-blocks');
        $profiles = '<li><a class="alps-ytc__profile" href="' . esc_url(self::channelUrl($channel)) . '" target="_blank" rel="noopener" aria-label="YouTube" title="YouTube">' . self::icon('youtube') . '</a></li>';
        foreach (self::NETWORKS as $network => $name) {
            $url = isset($links[$network]) && is_string($links[$network]) ? esc_url(trim($links[$network]), ['https', 'http']) : '';
            if ('' !== $url) {
                $profiles .= '<li><a class="alps-ytc__profile" href="' . $url . '" target="_blank" rel="noopener" aria-label="' . esc_attr($name) . '" title="' . esc_attr($name) . '">' . self::icon($network) . '</a></li>';
            }
        }

        $cards = '';
        foreach ($videos as $index => $video) {
            $duration = self::duration($video['seconds']);
            $date = '' !== $video['published'] ? '<time class="alps-ytc__date" datetime="' . esc_attr($video['published']) . '">' . esc_html(wp_date(get_option('date_format'), strtotime($video['published']))) . '</time>' : '';
            $cards .= sprintf(
                '<li class="alps-ytc__item"><button type="button" class="alps-ytc__card%1$s" data-video="%2$s" data-title="%3$s"%4$s aria-label="%5$s"><span class="alps-ytc__thumb">%6$s<span class="alps-ytc__play" aria-hidden="true">%7$s</span>%8$s</span><span class="alps-ytc__card-title">%9$s</span>%10$s</button></li>',
                0 === $index ? ' is-active' : '',
                esc_attr($video['id']),
                esc_attr($video['title']),
                0 === $index ? ' aria-current="true"' : '',
                esc_attr(sprintf($playLabel, $video['title'])),
                self::image($video, '(max-width: 640px) 80vw, (max-width: 768px) 50vw, 300px', 480),
                self::icon('play'),
                '' !== $duration ? '<span class="alps-ytc__duration">' . esc_html($duration) . '</span>' : '',
                esc_html($video['title']),
                $date
            );
        }

        $nav = count($videos) > 1 ? sprintf(
            '<div class="alps-ytc__nav" hidden><button type="button" class="alps-ytc__arrow" data-step="-1" aria-label="%1$s" disabled>%2$s</button><span class="alps-ytc__count" aria-hidden="true"></span><button type="button" class="alps-ytc__arrow" data-step="1" aria-label="%3$s">%4$s</button></div>',
            esc_attr__('Previous videos', 'alps-gutenberg-blocks'),
            self::icon('prev'),
            esc_attr__('Next videos', 'alps-gutenberg-blocks'),
            self::icon('next')
        ) : '';

        $wide = isset($attributes['align']) && in_array($attributes['align'], ['wide', 'full'], true);
        $wrapper = get_block_wrapper_attributes(['class' => 'alps-ytc']);
        $poster = self::image($first, '(max-width: 1000px) 100vw, 920px', $wide ? 1280 : 640);
        // Copy the thumbnails this list still takes from YouTube (lists cached before 3.2.2, new videos).
        $key = self::cacheKey($channel, $excludeShorts);
        if (self::$missingThumbs && ! wp_next_scheduled(self::THUMBS_HOOK, [$key])) {
            wp_schedule_single_event(time(), self::THUMBS_HOOK, [$key]);
        }
        self::$missingThumbs = false;
        return sprintf(
            '<section %1$s data-alps-ytc data-iframe-title="%2$s">'
            . '<header class="alps-ytc__header">%3$s<ul class="alps-ytc__profiles" aria-label="%4$s">%5$s</ul></header>'
            . '<div class="alps-ytc__player"><button type="button" class="alps-ytc__poster" data-video="%6$s" aria-label="%7$s">%8$s<span class="alps-ytc__play alps-ytc__play--big" aria-hidden="true">%9$s</span></button></div>'
            . '<p class="alps-ytc__now"><span class="alps-ytc__now-title">%10$s</span> <a class="alps-ytc__watch" href="%11$s" target="_blank" rel="noopener">%12$s</a></p>'
            . '%13$s'
            . '</section>',
            $wrapper,
            esc_attr__('YouTube video player', 'alps-gutenberg-blocks'),
            '' !== $title ? '<h2 class="alps-ytc__title">' . esc_html($title) . '</h2>' : '',
            /* translators: %s: channel name */
            esc_attr(sprintf(__('%s on social media', 'alps-gutenberg-blocks'), '' !== $title ? $title : 'YouTube')),
            $profiles,
            esc_attr($first['id']),
            esc_attr(sprintf($playLabel, $first['title'])),
            // 640 px covers the block in a content column, also on 3x phones
            // (~300 CSS px); the 1280 px image (~250 KB) only for wide/full blocks.
            $poster,
            self::icon('play'),
            esc_html($first['title']),
            esc_url('https://www.youtube.com/watch?v=' . $first['id']),
            esc_html__('Watch on YouTube', 'alps-gutenberg-blocks'),
            count($videos) > 1 ? '<div class="alps-ytc__bar"><p class="alps-ytc__label">' . esc_html__('More videos', 'alps-gutenberg-blocks') . '</p>' . $nav . '</div><ul class="alps-ytc__track">' . $cards . '</ul>' : ''
        );
    }
}
