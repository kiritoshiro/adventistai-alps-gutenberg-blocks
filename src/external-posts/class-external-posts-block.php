<?php
namespace ALPS\Gutenberg\Blocks;

if (! defined('ABSPATH')) { exit; }

/** External RSS/Atom lists and cards, adapted from Darius's GPL-2.0+ plugin 4.1.3. */
class ExternalPostsBlock
{
    const HANDLE = 'alps-gb-external-posts';

    public function init()
    {
        wp_register_style(self::HANDLE, plugins_url('dist/external-posts.css', dirname(__DIR__, 2) . '/plugin.php'), [], ALPS_GUTENBERG_VERSION);
        register_block_type(__DIR__ . '/block.json', ['render_callback' => [$this, 'render']]);
        // Preserve the standalone plugin's handler when it is active.
        if (! shortcode_exists('external_posts')) {
            add_shortcode('external_posts', [$this, 'shortcode']);
        }
    }

    public function render($attributes)
    {
        $attributes = is_array($attributes) ? $attributes : [];
        $atts = [];
        foreach (['feeds'=>'feeds', 'number'=>'number', 'cacheMinutes'=>'cache_min', 'layout'=>'layout', 'excerptLength'=>'excerpt_len', 'imageSize'=>'img_size', 'debug'=>'debug'] as $key=>$target) {
            if (isset($attributes[$key]) && is_scalar($attributes[$key])) { $atts[$target] = $attributes[$key]; }
        }
        return '<div ' . get_block_wrapper_attributes(['class'=>'alps-external-posts']) . '>' . $this->shortcode($atts) . '</div>';
    }

    /**
     * Convert a site homepage into the conventional WordPress feed URL.
     * Existing feed paths are left unchanged.
     *
     * @param string $url Supplied URL.
     * @return string
     */
    public function normalizeFeedUrl( $url ) {
        $url = is_scalar($url) ? trim( html_entity_decode( (string) $url, ENT_QUOTES, 'UTF-8' ) ) : '';

        if ( '' === $url ) {
            return '';
        }

        $url = esc_url_raw( $url, array( 'http', 'https' ) );
        if ( '' === $url ) {
            return '';
        }

        $parts = wp_parse_url( $url );
        if ( false === $parts || empty( $parts['host'] ) ) {
            return '';
        }

        $path = isset( $parts['path'] ) ? $parts['path'] : '';
        if (('' === $path || '/' === $path) && empty($parts['query'])) {
            $query = isset($parts['query']) ? '?' . $parts['query'] : '';
            $base = preg_replace('/[?#].*$/', '', $url);
            $url = untrailingslashit($base) . '/feed/' . $query;
        }

        return $url;
    }

    /**
     * Find a usable image in a feed item without relying on non-standard
     * SimplePie methods.
     *
     * @param object $item SimplePie item.
     * @return string
     */
    public function itemImage( $item ) {
        if ( method_exists( $item, 'get_enclosures' ) ) {
            $enclosures = $item->get_enclosures();
            if ( is_array( $enclosures ) ) {
                foreach ( $enclosures as $enclosure ) {
                    $type = method_exists( $enclosure, 'get_type' ) ? (string) $enclosure->get_type() : '';
                    $link = method_exists( $enclosure, 'get_link' ) ? (string) $enclosure->get_link() : '';
                    if ( $link && ( 0 === strpos( $type, 'image/' ) || preg_match( '/\.(?:jpe?g|png|gif|webp|avif)(?:\?|$)/i', $link ) ) ) {
                        return esc_url_raw( $link );
                    }
                }
            }
        }

        // Media RSS: media:thumbnail and media:content.
        if ( method_exists( $item, 'get_item_tags' ) ) {
            $media_namespace = 'http://search.yahoo.com/mrss/';
            foreach ( array( 'thumbnail', 'content' ) as $tag_name ) {
                $tags = $item->get_item_tags( $media_namespace, $tag_name );
                if ( is_array( $tags ) ) {
                    foreach ( $tags as $tag ) {
                        if ( ! empty( $tag['attribs']['']['url'] ) ) {
                            return esc_url_raw( $tag['attribs']['']['url'] );
                        }
                    }
                }
            }
        }

        $content = '';
        if ( method_exists( $item, 'get_content' ) ) {
            $content = (string) $item->get_content();
        }
        if ( '' === $content && method_exists( $item, 'get_description' ) ) {
            $content = (string) $item->get_description();
        }

        if ( preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $match ) ) {
            return esc_url_raw( html_entity_decode( $match[1], ENT_QUOTES, 'UTF-8' ) );
        }

        return '';
    }

    /**
     * Fetch one feed while disabling SimplePie's separate 12-hour cache.
     * The shortcode uses its own cache so cache_min behaves as requested.
     *
     * @param string $feed_url Feed URL.
     * @return SimplePie\SimplePie|WP_Error
     */
    public function fetchFeed( $feed_url ) {
        $disable_simplepie_cache = static function ( $feed ) {
            if ( is_object( $feed ) && method_exists( $feed, 'enable_cache' ) ) {
                $feed->enable_cache( false );
                $feed->set_timeout(5);
            }
        };

        add_action( 'wp_feed_options', $disable_simplepie_cache, 10, 1 );
        try {
            return fetch_feed($feed_url);
        } finally {
            remove_action('wp_feed_options', $disable_simplepie_cache, 10);
        }
    }

    /**
     * Render [external_posts].
     *
     * @param array|string $atts Shortcode attributes.
     * @return string
     */
    public function shortcode( $atts ) {
        wp_enqueue_style(self::HANDLE);
        $atts = is_array($atts) ? array_filter($atts, 'is_scalar') : [];
        $attributes = shortcode_atts(
            array(
                'feeds'       => '',
                'number'      => 5,
                'cache_min'   => 30,
                'layout'      => 'list',
                'excerpt_len' => 15,
                'img_size'    => 120,
                'debug'       => '0',
            ),
            $atts,
            'external_posts'
        );

        $number      = max( 1, min( 20, absint( $attributes['number'] ) ) );
        $cache_min   = max( 1, min( 1440, absint( $attributes['cache_min'] ) ) );
        $excerpt_len = max( 1, min( 100, absint( $attributes['excerpt_len'] ) ) );
        $img_size    = max( 40, min( 500, absint( $attributes['img_size'] ) ) );
        $layout      = 'cards' === strtolower( trim( (string) $attributes['layout'] ) ) ? 'cards' : 'list';
        $debug       = in_array( strtolower( (string) $attributes['debug'] ), array( '1', 'true', 'yes', 'on' ), true );

        $raw_urls = preg_split( '/[\r\n,]+/', substr((string) $attributes['feeds'], 0, 8192) );
        $feed_urls = array();

        foreach ( $raw_urls as $raw_url ) {
            $feed_url = $this->normalizeFeedUrl( $raw_url );
            if ( $feed_url && ! in_array( $feed_url, $feed_urls, true ) ) {
                $feed_urls[] = $feed_url;
                if (count($feed_urls) >= 8) { break; }
            }
        }

        if ( empty( $feed_urls ) ) {
            return '<p class="alps-epa-message alps-epa-error">' . esc_html__( 'No valid feed URL was supplied.', 'alps-gutenberg-blocks' ) . '</p>';
        }

        $cache_data = array(
            'feeds'       => $feed_urls,
            'number'      => $number,
            'cache_min'   => $cache_min,
            'layout'      => $layout,
            'excerpt_len' => $excerpt_len,
            'img_size'    => $img_size,
            'debug_admin' => ( $debug && current_user_can( 'manage_options' ) ) ? 1 : 0,
            'version'     => ALPS_GUTENBERG_VERSION,
        );
        $cache_key = 'alps_epa_' . md5( wp_json_encode( $cache_data ) );

        $cached_html = get_transient( $cache_key );
        if ( false !== $cached_html ) {
            return $cached_html;
        }

        require_once ABSPATH . WPINC . '/feed.php';

        $sections = array();
        $errors   = array();

        foreach ( $feed_urls as $feed_url ) {
            $feed = $this->fetchFeed( $feed_url );

            if ( is_wp_error( $feed ) ) {
                $errors[] = sprintf(
                    '%s — %s',
                    $feed_url,
                    $feed->get_error_message()
                );
                continue;
            }

            $item_count = (int) $feed->get_item_quantity( $number );
            $items      = $item_count > 0 ? $feed->get_items( 0, $item_count ) : array();

            if ( empty( $items ) ) {
                $errors[] = sprintf( '%s — %s', $feed_url, __( 'The feed contains no readable items.', 'alps-gutenberg-blocks' ) );
                continue;
            }

            $feed_title = trim( html_entity_decode(wp_strip_all_tags((string) $feed->get_title()), ENT_QUOTES | ENT_HTML5, 'UTF-8') );
            if ( '' === $feed_title ) {
                $feed_title = preg_replace( '/^www\./i', '', (string) wp_parse_url( $feed_url, PHP_URL_HOST ) );
            }

            $site_link = esc_url_raw( (string) $feed->get_link() );
            if ( '' === $site_link ) {
                $site_link = $feed_url;
            }

            ob_start();
            ?>
            <section class="alps-epa-feed">
                <h2 class="alps-epa-feed-title">
                    <a href="<?php echo esc_url( $site_link ); ?>" target="_blank" rel="noopener noreferrer">
                        <?php echo esc_html( $feed_title ); ?>
                    </a>
                </h2>

                <?php if ( 'cards' === $layout ) : ?>
                    <ul class="alps-epa-cards">
                        <?php foreach ( $items as $item ) :
                            $permalink = esc_url_raw( (string) $item->get_permalink() );
                            $title     = trim( html_entity_decode(wp_strip_all_tags((string) $item->get_title()), ENT_QUOTES | ENT_HTML5, 'UTF-8') );
                            $image     = $this->itemImage( $item );
                            $description = method_exists( $item, 'get_description' ) ? (string) $item->get_description() : '';
                            $excerpt = wp_trim_words( html_entity_decode(wp_strip_all_tags($description, true), ENT_QUOTES | ENT_HTML5, 'UTF-8'), $excerpt_len, '…' );
                            ?>
                            <li class="alps-epa-card">
                                <?php if ( $image ) : ?>
                                    <a class="alps-epa-thumb" href="<?php echo esc_url( $permalink ); ?>" target="_blank" rel="noopener noreferrer" tabindex="-1" aria-hidden="true">
                                        <img src="<?php echo esc_url( $image ); ?>" alt="" width="<?php echo esc_attr( $img_size ); ?>" height="<?php echo esc_attr( $img_size ); ?>" loading="lazy" decoding="async">
                                    </a>
                                <?php endif; ?>
                                <div class="alps-epa-text">
                                    <h3><a href="<?php echo esc_url( $permalink ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $title ); ?></a></h3>
                                    <?php if ( '' !== $excerpt ) : ?>
                                        <p><?php echo esc_html( $excerpt ); ?></p>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <ul class="alps-epa-posts">
                        <?php foreach ( $items as $item ) : ?>
                            <li>
                                <a href="<?php echo esc_url( (string) $item->get_permalink() ); ?>" target="_blank" rel="noopener noreferrer">
                                    <?php echo esc_html( html_entity_decode(wp_strip_all_tags((string) $item->get_title()), ENT_QUOTES | ENT_HTML5, 'UTF-8') ); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
            <?php
            $sections[] = ob_get_clean();
        }

        if ( empty( $sections ) ) {
            $html = '<div class="alps-epa-message alps-epa-error"><strong>' . esc_html__( 'External posts could not be loaded.', 'alps-gutenberg-blocks' ) . '</strong>';

            if ( current_user_can( 'manage_options' ) ) {
                $html .= '<br><span>' . esc_html__( 'Feed errors:', 'alps-gutenberg-blocks' ) . '</span><ul>';
                foreach ( $errors as $error ) {
                    $html .= '<li>' . esc_html( $error ) . '</li>';
                }
                $html .= '</ul>';
            }

            $html .= '</div>';
            return $html;
        }

        $section_count = count( $sections );
        $wrapper_classes = array(
            'alps-epa-feeds',
            'alps-epa-count-' . $section_count,
            'alps-epa-layout-' . $layout,
        );

        $html = '<div class="' . esc_attr( implode( ' ', $wrapper_classes ) ) . '" style="--alps-epa-image-size:' . esc_attr($img_size) . 'px">' . implode( '', $sections ) . '</div>';

        if ( $debug && current_user_can( 'manage_options' ) && ! empty( $errors ) ) {
            $html .= '<div class="alps-epa-message alps-epa-warning"><strong>' . esc_html__( 'Some feeds failed:', 'alps-gutenberg-blocks' ) . '</strong><ul>';
            foreach ( $errors as $error ) {
                $html .= '<li>' . esc_html( $error ) . '</li>';
            }
            $html .= '</ul></div>';
        }

        set_transient( $cache_key, $html, $cache_min * MINUTE_IN_SECONDS );

        return $html;
    }

}
