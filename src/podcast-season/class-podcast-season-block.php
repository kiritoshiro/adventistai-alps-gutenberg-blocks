<?php
namespace ALPS\Gutenberg\Blocks;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Server-side rendering for the Podcast Season block.
 *
 * The episodes of one season come from the podcast's RSS feed (the
 * itunes:season tag) and are cached in an option, so visitors never wait for
 * the feed: a stale list is served while WP-Cron refreshes it, and only the
 * very first render of a feed and season fetches it. The page carries one
 * <audio preload="none"> element, so no audio is downloaded until a visitor
 * presses play; view.js swaps the episode in that one player. The cover is
 * resized once by WP-Cron into uploads/alps-podcast/ (feed covers are often
 * 3000 px JPEGs of 1-2 MB) and is left out until that copy exists.
 */
class PodcastSeasonBlock
{
    /** Seconds a list is fresh, and the wait before retrying a failed fetch. */
    const FRESH = 3600;
    const RETRY = 600;

    /** Episodes kept per season, and the largest feed read (the 5 minutės feed is ~750 KB). */
    const MAX_EPISODES = 200;
    const MAX_FEED_BYTES = 10485760;

    /** Characters of each description kept in the cache. */
    const MAX_DESCRIPTION = 600;

    const REFRESH_HOOK = 'alps_gb_podcast_season_refresh';
    const COVER_HOOK = 'alps_gb_podcast_season_cover';
    const HANDLE = 'alps-gb-podcast-season';

    /** Covers show at up to 120 CSS px; 240 px covers 2x screens. */
    const COVER_DIR = 'alps-podcast';
    const COVER_SIZE = 240;
    const COVER_MAX_BYTES = 8388608;
    const COVER_TYPES = ['image/avif' => 'avif', 'image/webp' => 'webp', 'image/jpeg' => 'jpg'];

    const ITUNES = 'http://www.itunes.com/dtds/podcast-1.0.dtd';

    public function init()
    {
        $pluginFile = dirname(__DIR__, 2) . '/plugin.php';
        wp_register_style(self::HANDLE, plugins_url('dist/podcast-season.css', $pluginFile), [], ALPS_GUTENBERG_VERSION);
        wp_register_script(
            self::HANDLE,
            plugins_url('dist/podcast-season.js', $pluginFile),
            [],
            ALPS_GUTENBERG_VERSION,
            ['in_footer' => true, 'strategy' => 'defer']
        );
        register_block_type(__DIR__ . '/block.json', [
            'render_callback' => [$this, 'render'],
        ]);
        add_action(self::REFRESH_HOOK, [$this, 'refresh'], 10, 2);
        add_action(self::COVER_HOOK, [$this, 'saveCover']);
    }

    /** An http(s) feed address, or '' for anything else. */
    public static function feedUrl($value)
    {
        $url = is_string($value) ? trim(html_entity_decode($value, ENT_QUOTES, 'UTF-8')) : '';
        if ('' === $url || strlen($url) > 2048) {
            return '';
        }
        $url = esc_url_raw($url, ['http', 'https']);
        $host = '' !== $url ? wp_parse_url($url, PHP_URL_HOST) : '';
        return is_string($host) && '' !== $host ? $url : '';
    }

    /** A season number from 1 to 999, or 0 when the value is not one. */
    public static function season($value)
    {
        $season = is_numeric($value) ? (int) $value : 0;
        return $season >= 1 && $season <= 999 ? $season : 0;
    }

    /** Seconds from itunes:duration ("1:02:03", "06:40" or "400"); 0 when unknown. */
    public static function seconds($value)
    {
        $value = trim((string) $value);
        if (preg_match('/^\d+$/D', $value)) {
            return (int) $value;
        }
        if (! preg_match('/^(?:(\d+):)?(\d{1,2}):(\d{1,2})$/D', $value, $m)) {
            return 0;
        }
        return (int) $m[1] * 3600 + (int) $m[2] * 60 + (int) $m[3];
    }

    /** "1:02:03" or "6:40"; '' when unknown. */
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

    /** Plain text of a feed description, with paragraphs kept apart by a space. */
    public static function plainText($html)
    {
        $text = preg_replace('#<br\s*/?>|</(?:p|div|li|h[1-6])>#i', ' ', (string) $html);
        $text = html_entity_decode(wp_strip_all_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * The title as listed: without the series name typed in the settings and,
     * when the number is shown beside it, without a leading "1 – " or "1.".
     * The full title when nothing would be left.
     */
    public static function label($title, $prefix, $number)
    {
        $label = trim((string) $title);
        $prefix = trim((string) $prefix);
        if ('' !== $prefix) {
            $label = (string) preg_replace('/^' . preg_quote($prefix, '/') . '[\s.:,;|·–—-]*/iu', '', $label);
        }
        if (null !== $number) {
            $label = (string) preg_replace('/^' . (int) $number . '\s*[.:)–—-]\s*/u', '', $label);
        }
        $label = trim($label);
        return '' !== $label ? $label : trim((string) $title);
    }

    /**
     * Reads one season from a podcast feed.
     *
     * @return array ['title', 'cover', 'episodes']
     * @throws \RuntimeException When the feed cannot be read.
     */
    public static function fetch($feedUrl, $season)
    {
        if (! function_exists('simplexml_load_string')) {
            throw new \RuntimeException(__('this server cannot read XML (SimpleXML is missing)', 'alps-gutenberg-blocks')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- caught in refresh(); shown only through notice(), which escapes.
        }
        // wp_safe_remote_get() refuses local and private addresses.
        $response = wp_safe_remote_get($feedUrl, ['timeout' => 10, 'redirection' => 3, 'limit_response_size' => self::MAX_FEED_BYTES]);
        if (is_wp_error($response)) {
            throw new \RuntimeException($response->get_error_message()); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- caught in refresh(); shown only through notice(), which escapes.
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        if (200 !== $code) {
            /* translators: %d: HTTP status code */
            throw new \RuntimeException(sprintf(__('HTTP error %d', 'alps-gutenberg-blocks'), $code)); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- caught in refresh(); shown only through notice(), which escapes.
        }
        return self::parse((string) wp_remote_retrieve_body($response), $season);
    }

    /**
     * The season's episodes from RSS text, oldest first: by episode number,
     * then by date. Trailers and items without audio are skipped.
     *
     * @throws \RuntimeException When the text is not an RSS feed.
     */
    public static function parse($xml, $season)
    {
        $internal = libxml_use_internal_errors(true);
        // No LIBXML_NOENT: entities are not expanded, and LIBXML_NONET keeps the parser offline.
        $rss = '' !== trim($xml) ? simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA | LIBXML_COMPACT) : false;
        libxml_clear_errors();
        libxml_use_internal_errors($internal);
        if (! $rss instanceof \SimpleXMLElement || ! isset($rss->channel)) {
            throw new \RuntimeException(__('the address is not an RSS feed', 'alps-gutenberg-blocks')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- caught in refresh(); shown only through notice(), which escapes.
        }
        $channel = $rss->channel;
        $itunes = $channel->children(self::ITUNES);
        $cover = isset($itunes->image) ? (string) $itunes->image->attributes()->href : '';

        $episodes = [];
        foreach ($channel->item as $item) {
            $tags = $item->children(self::ITUNES);
            if ((int) $tags->season !== (int) $season || 'trailer' === strtolower(trim((string) $tags->episodeType))) {
                continue;
            }
            $audio = isset($item->enclosure) ? esc_url_raw(trim((string) $item->enclosure['url']), ['https', 'http']) : '';
            if ('' === $audio) {
                continue;
            }
            $time = strtotime((string) $item->pubDate);
            $number = trim((string) $tags->episode);
            $description = self::plainText((string) $item->description);
            if (function_exists('mb_substr') && mb_strlen($description) > self::MAX_DESCRIPTION) {
                $description = rtrim(mb_substr($description, 0, self::MAX_DESCRIPTION)) . '…';
            }
            $episodes[] = [
                'title'       => self::plainText((string) $item->title),
                'audio'       => $audio,
                'type'        => sanitize_mime_type((string) $item->enclosure['type']),
                'number'      => preg_match('/^\d{1,5}$/D', $number) ? (int) $number : null,
                'seconds'     => self::seconds((string) $tags->duration),
                'published'   => $time ? gmdate('c', $time) : '',
                'description' => $description,
                'image'       => isset($tags->image) ? esc_url_raw((string) $tags->image->attributes()->href, ['https', 'http']) : '',
            ];
        }
        usort($episodes, function ($a, $b) {
            $na = null === $a['number'] ? PHP_INT_MAX : $a['number'];
            $nb = null === $b['number'] ? PHP_INT_MAX : $b['number'];
            return $na === $nb ? strcmp($a['published'], $b['published']) : ($na < $nb ? -1 : 1);
        });
        $episodes = array_slice($episodes, 0, self::MAX_EPISODES);
        // The season's own artwork (the first episode's) before the podcast's.
        if ($episodes && '' !== $episodes[0]['image']) {
            $cover = $episodes[0]['image'];
        }
        foreach ($episodes as &$episode) {
            unset($episode['image']);
        }
        unset($episode);

        return [
            'title'    => self::plainText((string) $channel->title),
            'cover'    => esc_url_raw($cover, ['https', 'http']),
            'episodes' => $episodes,
        ];
    }

    private static function cacheKey($feedUrl, $season)
    {
        return 'alps_gb_pod_' . md5((string) wp_json_encode([$feedUrl, (int) $season]));
    }

    /**
     * Cached data for a season. A stale list is returned at once and refreshed
     * by WP-Cron; only a season with no list yet is fetched during the render.
     *
     * @return array ['title', 'cover', 'episodes', 'time'] or ['error' => string]
     */
    public function data($feedUrl, $season)
    {
        $key = self::cacheKey($feedUrl, $season);
        $cached = get_option($key);
        if (is_array($cached) && isset($cached['time'], $cached['episodes'])) {
            $args = [$feedUrl, (int) $season];
            if ($cached['time'] < time() - self::FRESH && ! wp_next_scheduled(self::REFRESH_HOOK, $args)) {
                wp_schedule_single_event(time(), self::REFRESH_HOOK, $args);
            }
            return $cached;
        }
        $error = get_transient($key . '_error');
        if (is_string($error) && '' !== $error) {
            return ['error' => $error];
        }
        return $this->refresh($feedUrl, $season);
    }

    /**
     * Fetches and stores a season's list. On failure an existing list is kept
     * and retried after RETRY seconds; with no list, the error is remembered
     * for RETRY seconds so page views do not keep fetching the feed.
     *
     * @param mixed $feedUrl Feed address (also a WP-Cron argument).
     * @param mixed $season  Season number.
     * @return array
     */
    public function refresh($feedUrl, $season)
    {
        $feedUrl = self::feedUrl($feedUrl);
        $season = self::season($season);
        if ('' === $feedUrl || ! $season) {
            return ['error' => __('no feed address or season', 'alps-gutenberg-blocks')];
        }
        $key = self::cacheKey($feedUrl, $season);
        $cached = get_option($key);
        try {
            $data = self::fetch($feedUrl, $season) + ['time' => time()];
        } catch (\RuntimeException $e) {
            if (! is_array($cached) || ! isset($cached['episodes'])) {
                set_transient($key . '_error', $e->getMessage(), self::RETRY);
                return ['error' => $e->getMessage()];
            }
            $data = $cached;
            $data['time'] = time() - self::FRESH + self::RETRY;
        }
        update_option($key, $data, false);
        delete_transient($key . '_error');
        if ('' !== $data['cover'] && ! self::coverUrl($data['cover']) && ! wp_next_scheduled(self::COVER_HOOK, [$key])) {
            wp_schedule_single_event(time(), self::COVER_HOOK, [$key]);
        }
        return $data;
    }

    /** [directory, base URL, file name without extension] for a cover, or null without uploads. */
    private static function coverPlace($source)
    {
        $uploads = wp_upload_dir(null, false);
        if (! empty($uploads['error']) || empty($uploads['basedir']) || empty($uploads['baseurl'])) {
            return null;
        }
        return [
            trailingslashit($uploads['basedir']) . self::COVER_DIR,
            trailingslashit($uploads['baseurl']) . self::COVER_DIR,
            md5((string) $source) . '-' . self::COVER_SIZE,
        ];
    }

    /** URL of the resized copy of a cover, or '' while there is none. */
    public static function coverUrl($source)
    {
        $place = '' !== (string) $source ? self::coverPlace($source) : null;
        if (! $place) {
            return '';
        }
        foreach (self::COVER_TYPES as $extension) {
            if (is_file($place[0] . '/' . $place[2] . '.' . $extension)) {
                return $place[1] . '/' . $place[2] . '.' . $extension;
            }
        }
        return '';
    }

    /**
     * WP-Cron: saves a COVER_SIZE square copy of a cached season's cover as
     * AVIF, WebP or JPEG, whichever the server's image library can write.
     *
     * @param mixed $key Cache key from cacheKey().
     */
    public function saveCover($key)
    {
        if (! is_string($key) || ! preg_match('/^alps_gb_pod_[a-f0-9]{32}$/D', $key)) {
            return;
        }
        $data = get_option($key);
        $source = is_array($data) && isset($data['cover']) && is_string($data['cover']) ? $data['cover'] : '';
        $place = '' !== $source ? self::coverPlace($source) : null;
        if (! $place || '' !== self::coverUrl($source)) {
            return;
        }
        $response = wp_safe_remote_get($source, ['timeout' => 15, 'redirection' => 3, 'limit_response_size' => self::COVER_MAX_BYTES]);
        if (is_wp_error($response) || 200 !== (int) wp_remote_retrieve_response_code($response)) {
            return;
        }
        $body = (string) wp_remote_retrieve_body($response);
        $info = strlen($body) >= 100 && strlen($body) < self::COVER_MAX_BYTES && function_exists('getimagesizefromstring') ? @getimagesizefromstring($body) : false;
        if (! is_array($info) || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true) || ! wp_mkdir_p($place[0])) {
            return;
        }
        $original = $place[0] . '/' . $place[2] . '.' . wp_generate_password(8, false) . '.tmp';
        if (false === file_put_contents($original, $body)) { // Fixed directory, generated name.
            return;
        }
        $editor = wp_get_image_editor($original);
        if (! is_wp_error($editor) && ! is_wp_error($editor->resize(self::COVER_SIZE, self::COVER_SIZE, true))) {
            foreach (self::COVER_TYPES as $mime => $extension) {
                if ($editor->supports_mime_type($mime)) {
                    // Saved beside the target and renamed, so a page never links a half-written file.
                    $saved = $editor->save($place[0] . '/' . $place[2] . '.part.' . $extension, $mime);
                    if (is_wp_error($saved) || empty($saved['path'])) {
                        break;
                    }
                    // A site's image_editor_output_format filter may have chosen another format.
                    $written = strtolower(pathinfo($saved['path'], PATHINFO_EXTENSION));
                    if (! in_array($written, self::COVER_TYPES, true) || ! rename($saved['path'], $place[0] . '/' . $place[2] . '.' . $written)) {
                        wp_delete_file($saved['path']);
                    }
                    break;
                }
            }
        }
        wp_delete_file($original);
    }

    /** A message for people who can edit the page; visitors get nothing. */
    private static function notice($message)
    {
        if (! current_user_can('edit_posts')) {
            return '';
        }
        return '<p class="alps-pod-notice" role="note">' . esc_html($message) . '</p>';
    }

    /**
     * One episode row: a link to the audio file (it plays in the browser
     * without JavaScript; view.js plays it in the block's player instead).
     */
    private static function item(array $episode, $index, $active, array $options)
    {
        $number = $options['numbers'] ? $episode['number'] : null;
        $label = self::label($episode['title'], $options['prefix'], $number);
        $duration = self::duration($episode['seconds']);
        $description = '';
        if ($options['words'] > 0 && '' !== $episode['description']) {
            $description = '<p class="alps-pod__description">' . esc_html(wp_trim_words($episode['description'], $options['words'], '…')) . '</p>';
        }
        return sprintf(
            '<li class="alps-pod__item"><a class="alps-pod__episode%1$s" href="%2$s"%3$s rel="nofollow" data-index="%4$d" data-title="%5$s" aria-label="%6$s"%7$s><span class="alps-pod__icon" aria-hidden="true"></span>%8$s<span class="alps-pod__name">%9$s</span>%10$s</a>%11$s</li>',
            $active ? ' is-active' : '',
            esc_url($episode['audio']),
            '' !== $episode['type'] ? ' type="' . esc_attr($episode['type']) . '"' : '',
            (int) $index,
            esc_attr($label),
            /* translators: %s: episode title */
            esc_attr(sprintf(__('Play: %s', 'alps-gutenberg-blocks'), $label)),
            $active ? ' aria-current="true"' : '',
            null !== $number ? '<span class="alps-pod__number">' . (int) $number . '</span>' : '',
            esc_html($label),
            '' !== $duration ? '<span class="alps-pod__duration">' . esc_html($duration) . '</span>' : '',
            $description
        );
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
        $feedUrl = self::feedUrl(isset($attributes['feedUrl']) ? $attributes['feedUrl'] : '');
        if ('' === $feedUrl) {
            return self::notice(__('Podcast season: enter the podcast RSS feed address.', 'alps-gutenberg-blocks'));
        }
        $season = self::season(isset($attributes['season']) ? $attributes['season'] : 1);
        if (! $season) {
            return self::notice(__('Podcast season: enter a season number from 1 to 999.', 'alps-gutenberg-blocks'));
        }
        $data = $this->data($feedUrl, $season);
        if (! empty($data['error'])) {
            /* translators: %s: reason, such as "HTTP error 404" */
            return self::notice(sprintf(__('Podcast season: the feed could not be loaded (%s).', 'alps-gutenberg-blocks'), $data['error']));
        }
        if (empty($data['episodes'])) {
            /* translators: %d: season number */
            return self::notice(sprintf(__('Podcast season: this feed has no episodes in season %d.', 'alps-gutenberg-blocks'), $season));
        }

        $episodes = $data['episodes'];
        if (isset($attributes['order']) && 'desc' === $attributes['order']) {
            $episodes = array_reverse($episodes);
        }
        $visible = isset($attributes['visible']) && is_numeric($attributes['visible']) ? max(1, min(self::MAX_EPISODES, (int) $attributes['visible'])) : 10;
        $words = isset($attributes['descriptionWords']) && is_numeric($attributes['descriptionWords']) ? max(5, min(100, (int) $attributes['descriptionWords'])) : 30;
        $options = [
            'prefix'  => isset($attributes['removePrefix']) && is_string($attributes['removePrefix']) ? $attributes['removePrefix'] : '',
            'numbers' => ! isset($attributes['showNumbers']) || ! empty($attributes['showNumbers']),
            'words'   => ! empty($attributes['showDescription']) ? $words : 0,
        ];
        $title = isset($attributes['title']) && is_string($attributes['title']) && '' !== trim($attributes['title']) ? trim($attributes['title']) : $data['title'];

        $cover = '';
        if (! isset($attributes['showCover']) || ! empty($attributes['showCover'])) {
            $coverUrl = self::coverUrl($data['cover']);
            if ('' !== $coverUrl) {
                $cover = '<img class="alps-pod__cover" src="' . esc_url($coverUrl) . '" width="' . self::COVER_SIZE . '" height="' . self::COVER_SIZE . '" alt="" loading="lazy" decoding="async">';
            } elseif ('' !== $data['cover']) {
                $key = self::cacheKey($feedUrl, $season);
                if (! wp_next_scheduled(self::COVER_HOOK, [$key])) {
                    wp_schedule_single_event(time(), self::COVER_HOOK, [$key]);
                }
            }
        }

        $items = '';
        $more = '';
        foreach ($episodes as $index => $episode) {
            $row = self::item($episode, $index, 0 === $index, $options);
            if ($index < $visible) {
                $items .= $row;
            } else {
                $more .= $row;
            }
        }
        $first = $episodes[0];
        $firstLabel = self::label($first['title'], $options['prefix'], $options['numbers'] ? $first['number'] : null);
        $heading = '' !== $title ? '<h2 class="alps-pod__title">' . esc_html($title) . '</h2>' : '';
        /* translators: %d: season number */
        $meta = sprintf(__('Season %d', 'alps-gutenberg-blocks'), $season)
            /* translators: %d: number of episodes */
            . ' · ' . sprintf(__('Episodes: %d', 'alps-gutenberg-blocks'), count($episodes));

        wp_enqueue_style(self::HANDLE);
        wp_enqueue_script(self::HANDLE);

        return sprintf(
            '<section %1$s data-alps-pod%2$s>'
            . '<header class="alps-pod__header">%3$s<div class="alps-pod__heading">%4$s<p class="alps-pod__meta">%5$s</p></div></header>'
            . '<div class="alps-pod__player"><p class="alps-pod__now">%6$s</p><audio class="alps-pod__audio" controls preload="none" src="%7$s" aria-label="%8$s"></audio></div>'
            . '<ol class="alps-pod__list" aria-label="%9$s">%10$s</ol>%11$s'
            . '</section>',
            get_block_wrapper_attributes(['class' => 'alps-pod']),
            ! isset($attributes['autoNext']) || ! empty($attributes['autoNext']) ? ' data-auto-next' : '',
            $cover,
            $heading,
            esc_html($meta),
            esc_html($firstLabel),
            esc_url($first['audio']),
            esc_attr__('Audio player', 'alps-gutenberg-blocks'),
            esc_attr__('Episodes', 'alps-gutenberg-blocks'),
            $items,
            '' !== $more
                /* translators: %d: number of episodes */
                ? '<details class="alps-pod__more"><summary>' . esc_html(sprintf(__('Show all episodes (%d)', 'alps-gutenberg-blocks'), count($episodes))) . '</summary><ol class="alps-pod__list" start="' . ($visible + 1) . '">' . $more . '</ol></details>'
                : ''
        );
    }
}
