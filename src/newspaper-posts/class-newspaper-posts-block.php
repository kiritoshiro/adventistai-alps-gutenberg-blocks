<?php
namespace ALPS\Gutenberg\Blocks;

if (! defined('ABSPATH')) {
    exit;
}

/** Newspaper-style published posts, rendered afresh on each request. */
class NewspaperPostsBlock
{
    const HANDLE = 'alps-gb-newspaper-posts';

    public function init()
    {
        wp_register_style(self::HANDLE, plugins_url('dist/newspaper-posts.css', dirname(__DIR__, 2) . '/plugin.php'), [], ALPS_GUTENBERG_VERSION);
        register_block_type(__DIR__ . '/block.json', ['render_callback' => [$this, 'render']]);
    }

    /** Accept the same common boolean values as the original shortcode. */
    public static function boolValue($value)
    {
        return is_scalar($value) && in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    public function queryArgs($attributes)
    {
        $count = $attributes['postsPerPage'] ?? 5;
        $args = [
            'post_type' => 'post',
            'posts_per_page' => max(1, min(50, is_scalar($count) ? absint($count) : 5)),
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC',
            'ignore_sticky_posts' => true,
            'no_found_rows' => true,
        ];
        $category = $attributes['category'] ?? '';
        $category = is_scalar($category) ? trim(sanitize_text_field((string) $category)) : '';
        if ('' !== $category) {
            $args['category_name'] = $category;
        }
        return $args;
    }

    public function render($attributes)
    {
        $attributes = is_array($attributes) ? $attributes : [];
        $query = new \WP_Query($this->queryArgs($attributes));
        wp_enqueue_style(self::HANDLE);
        $output = '<div ' . get_block_wrapper_attributes(['class' => 'custom-posts-list-wrapper alps-newspaper-posts']) . '>';
        if (! $query->posts) {
            return $output . '<p>' . esc_html__('Įrašų nerasta.', 'alps-gutenberg-blocks') . '</p></div>';
        }
        $showDate = self::boolValue($attributes['showDate'] ?? true);
        $showExcerpt = self::boolValue($attributes['showExcerpt'] ?? true);

        // Explicit post IDs preserve the enclosing page/query and its global post.
        foreach ($query->posts as $post) {
            $id = (int) $post->ID;
            $url = get_permalink($id);
            $title = get_the_title($id);
            $output .= '<article class="custom-post-item">';
            if (has_post_thumbnail($id)) {
                $output .= '<a href="' . esc_url($url) . '" class="post-thumbnail" aria-label="' . esc_attr($title) . '">';
                $output .= get_the_post_thumbnail($id, 'medium');
                $output .= '</a>';
            }
            $output .= '<h3 class="post-title"><a href="' . esc_url($url) . '">' . esc_html($title) . '</a></h3>';
            if ($showDate) {
                $output .= '<time class="post-date" datetime="' . esc_attr(get_the_date('c', $id)) . '">' . esc_html(get_the_date('', $id)) . '</time>';
            }
            if ($showExcerpt && ! post_password_required($id)) {
                $excerpt = html_entity_decode(wp_strip_all_tags(get_the_excerpt($id)), ENT_QUOTES | ENT_HTML5, get_bloginfo('charset') ?: 'UTF-8');
                $excerpt = preg_replace('/(?:\s*(?:\[(?:\.\.\.|…)]|\.\.\.|…|continued))+\s*$/iu', '', trim($excerpt));
                if (is_string($excerpt) && '' !== $excerpt) {
                    $output .= '<div class="post-excerpt"><p>' . esc_html($excerpt) . '…</p></div>';
                }
            }
            $output .= '<div class="clear" aria-hidden="true"></div></article>';
        }
        return $output . '</div>';
    }
}
