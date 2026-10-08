<?php
namespace ALPS\Gutenberg\Blocks;

if (! defined('ABSPATH')) {
    exit;
}

/** Dynamic replacement for the PDF books snippet. Never changes the global post. */
class BookShowcaseBlock
{
    const HANDLE = 'alps-gb-book-showcase';

    public function init()
    {
        $pluginFile = dirname(__DIR__, 2) . '/plugin.php';
        wp_register_style(self::HANDLE, plugins_url('dist/book-showcase.css', $pluginFile), [], ALPS_GUTENBERG_VERSION);
        register_block_type(__DIR__ . '/block.json', ['render_callback' => [$this, 'render']]);
    }

    /** Non-scalar attributes are rejected before string or number conversion. */
    private static function value($attributes, $key, $default)
    {
        return isset($attributes[$key]) && is_scalar($attributes[$key]) ? $attributes[$key] : $default;
    }

    private static function flag($attributes, $key, $default = true)
    {
        $value = self::value($attributes, $key, $default);
        return in_array($value, [true, 1, '1', 'true'], true);
    }

    private static function number($attributes, $key, $default, $min, $max)
    {
        $value = self::value($attributes, $key, $default);
        return is_numeric($value) && is_finite((float) $value) ? min($max, max($min, (float) $value)) : $default;
    }

    public function queryArgs($attributes)
    {
        $attributes = is_array($attributes) ? $attributes : [];
        $category = sanitize_title((string) self::value($attributes, 'category', 'pdf-knygos'));
        $orderBy = (string) self::value($attributes, 'orderBy', 'title');
        $order = strtoupper((string) self::value($attributes, 'order', 'ASC'));
        $args = [
            'post_type'           => 'post',
            'post_status'         => 'publish',
            'category_name'       => $category,
            'posts_per_page'      => self::flag($attributes, 'showAll') ? -1 : (int) self::number($attributes, 'count', 12, 1, 100),
            'orderby'             => in_array($orderBy, ['title', 'date', 'modified'], true) ? $orderBy : 'title',
            'order'               => in_array($order, ['ASC', 'DESC'], true) ? $order : 'ASC',
            'ignore_sticky_posts' => true,
            'no_found_rows'       => true,
        ];
        // An empty category must not accidentally list every site post.
        if ('' === $category) {
            $args['post__in'] = [0];
        }
        return $args;
    }

    public function render($attributes)
    {
        $attributes = is_array($attributes) ? $attributes : [];
        wp_enqueue_style(self::HANDLE);
        $query = new \WP_Query($this->queryArgs($attributes));
        $class = 'alps-book-showcase book-showcase-grid';
        if (self::flag($attributes, 'animate')) {
            $class .= ' alps-book-showcase--animated';
        }
        $accent = sanitize_hex_color((string) self::value($attributes, 'accentColor', '#C2A25B')) ?: '#C2A25B';
        $style = sprintf(
            '--book-desktop-columns:%d;--book-tablet-columns:%d;--book-small-columns:%d;--book-gap:%sem;--book-max-width:%dpx;--book-accent:%s;',
            (int) self::number($attributes, 'desktopColumns', 4, 1, 6),
            (int) self::number($attributes, 'tabletColumns', 3, 1, 4),
            (int) self::number($attributes, 'smallColumns', 2, 1, 3),
            self::number($attributes, 'gap', 1.5, 0.5, 3),
            (int) self::number($attributes, 'maxWidth', 1400, 600, 1800),
            $accent
        );
        $wrapper = get_block_wrapper_attributes(['class' => $class, 'style' => $style]);
        $html = '<div ' . $wrapper . '>';
        foreach ($query->posts as $post) {
            $id = (int) $post->ID;
            $imageId = (int) get_post_thumbnail_id($id);
            $title = get_the_title($id);
            if ('caption' === self::value($attributes, 'titleSource', 'caption') && $imageId) {
                $caption = wp_get_attachment_caption($imageId);
                if ('' !== trim((string) $caption)) {
                    $title = $caption;
                }
            }
            $title = trim(html_entity_decode(wp_strip_all_tags((string) $title), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ('' === $title) {
                $title = __('Untitled book', 'alps-gutenberg-blocks');
            }
            // The link always has the book title as its accessible name; its cover is decorative.
            $image = $imageId ? wp_get_attachment_image($imageId, 'medium_large', false, ['alt' => '', 'loading' => 'lazy']) : '';
            if (! $image) {
                $image = '<span class="book-showcase-missing">' . esc_html__('No cover', 'alps-gutenberg-blocks') . '</span>';
            }
            $html .= '<div class="book-showcase-item"><a href="' . esc_url(get_permalink($id)) . '" aria-label="' . esc_attr($title) . '">';
            $html .= '<div class="book-showcase-cover">' . $image . '</div>';
            if (self::flag($attributes, 'showTitles')) {
                $html .= '<div class="book-showcase-title">' . esc_html($title) . '</div>';
            }
            $html .= '</a></div>';
        }
        if (! $query->posts) {
            $empty = trim(wp_strip_all_tags((string) self::value($attributes, 'emptyMessage', '')));
            $html .= '<p class="book-showcase-empty">' . esc_html('' === $empty ? __('No books found.', 'alps-gutenberg-blocks') : $empty) . '</p>';
        }
        return $html . '</div>';
    }
}
