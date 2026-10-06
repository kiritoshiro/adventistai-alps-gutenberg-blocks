<?php
namespace ALPS\Gutenberg\Blocks;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Server-side rendering for the ALPS Latest Posts block.
 *
 * Block attributes come from post content, which anyone who can edit posts
 * controls, so every attribute is validated or escaped before use.
 */
class LatestPostsBlock
{
    /** Upper bound on posts per block; the editor offers the same maximum. */
    const MAX_POSTS = 100;

    const ORDERS = ['asc', 'desc'];
    const ORDER_BY = ['date', 'title'];

    public function init()
    {
        register_block_type(__DIR__ . '/block.json', [
            'render_callback' => [$this, 'render'],
        ]);
    }

    /**
     * Query arguments built only from validated attributes.
     *
     * @param array $attributes Block attributes.
     * @return array
     */
    public function queryArgs($attributes)
    {
        $count = isset($attributes['postsToShow']) ? (int) $attributes['postsToShow'] : 4;
        $order = isset($attributes['order']) ? strtolower((string) $attributes['order']) : 'desc';
        $orderBy = isset($attributes['orderBy']) ? (string) $attributes['orderBy'] : 'date';

        $args = [
            'numberposts' => min(self::MAX_POSTS, max(1, $count)),
            'post_type'   => 'post',
            'post_status' => 'publish',
            'order'       => in_array($order, self::ORDERS, true) ? $order : 'desc',
            'orderby'     => in_array($orderBy, self::ORDER_BY, true) ? $orderBy : 'date',
        ];

        $categories = isset($attributes['categories']) ? self::ids(explode(',', (string) $attributes['categories'])) : [];
        if ($categories) {
            $args['category'] = implode(',', $categories);
        }

        $tags = isset($attributes['tags']) && is_array($attributes['tags']) ? self::ids($attributes['tags']) : [];
        if ($tags) {
            $args['tag__in'] = $tags;
        }

        return $args;
    }

    /**
     * Renders the block on server.
     *
     * @param array $attributes The block attributes.
     *
     * @return string Returns the post content with latest posts added.
     */
    public function render($attributes)
    {
        $attributes = is_array($attributes) ? $attributes : [];
        $flag = function ($key) use ($attributes) {
            return ! empty($attributes[$key]);
        };

        $id = get_queried_object_id();
        $sidebarHidden = 'true' === get_post_meta($id, 'hide_sidebar', true);
        $isGrid = isset($attributes['postLayout']) && 'grid' === $attributes['postLayout'];

        $recent_posts = wp_get_recent_posts($this->queryArgs($attributes));

        $list_items_markup = '';

        // The labels are RichText values (may hold entities and inline markup).
        $headingTitle = wp_kses_post((string) ($attributes['title'] ?? ''));
        $headingLinkLabel = wp_kses_post((string) ($attributes['linkLabel'] ?? ''));
        $headingLinkUrl = esc_url((string) ($attributes['linkUrl'] ?? ''));

        if ($headingTitle) {
            // An empty "see all" link is skipped instead of printing <a href=""></a>.
            $headingLink = '' === trim(wp_strip_all_tags($headingLinkLabel)) ? '' : <<<HTML

  <a href="$headingLinkUrl" class="c-block__heading-link u-theme--color--base u-theme--link-hover--dark" style="border-bottom: 0">$headingLinkLabel</a>
HTML;
            $list_items_markup .= <<<HTML
<div class="c-block__heading u-theme--border-color--darker">
  <h3 class="c-block__heading-title u-theme--color--darker">$headingTitle</h3>$headingLink
</div>
HTML;
        }

        $readMoreLabel = wp_kses_post((string) ($attributes['readMoreLabel'] ?? ''));
        if ('' === $readMoreLabel) {
            $readMoreLabel = esc_html__('Read More', 'alps-gutenberg-blocks');
        }

        foreach ($recent_posts as $post) {
            $post_id = (int) $post['ID'];
            $title = get_the_title($post_id);
            $link = get_permalink($post_id);
            $category = $this->categoryName($post_id);

            $thumb_id = $this->imageId($post_id);
            $image = ($thumb_id && ! $flag('hideImage')) ? $this->imageSources($thumb_id) : null;
            $hasImage = null !== $image;

            if ($isGrid) {
                $block_class = "c-block__stacked c-media-block__stacked l-grid-wrap l-grid--7-col l-grid-item--3-col l-grid-item--m--2-col l-grid-item--xl--1-col";
                $block_img_class = "l-grid-item--3-col l-grid-item--m--2-col l-grid-item--xl--1-col u-padding--zero--sides";
                $block_content_class = "l-grid-item--3-col l-grid-item--m--2-col l-grid-item--xl--1-col u-border--left";
                if (! $hasImage) {
                    $block_content_class .= " u-padding--zero--top";
                }
                $block_title_class = "u-theme--color--dark u-font--primary--s";
                $block_meta_class = "u-theme--color--base u-font--secondary--xs";
            } elseif ($sidebarHidden) {
                if ($flag('alignRight')) {
                    if ($hasImage) {
                        $block_class = "c-block--reversed c-media-block--reversed l-grid-wrap l-grid-wrap--6-of-7 l-grid--7-col";
                        $block_img_class = "l-grid-item l-grid-item--2-col l-grid-item--m--1-col u-padding--zero--sides u-space--zero--right";
                        $block_content_class = "l-grid-item l-grid-item--4-col l-grid-item--m--3-col u-flex--justify-start u-border--left";
                    } else {
                        $block_class = "c-block--reversed c-media-block--reversed l-grid--7-col l-grid-wrap l-standard-break";
                        $block_img_class = "u-hide";
                        $block_content_class = "l-grid-item l-grid-item--6-col l-grid-item--m--4-col u-flex--justify-start u-border--left";
                    }
                } else {
                    $block_class = "c-media-block__row c-block__row l-grid--7-col l-grid-wrap l-large-break";
                    if ($hasImage) {
                        $block_img_class = "l-grid-item l-grid-item--2-col l-grid-item--m--1-col u-padding--zero--sides";
                        $block_content_class = "l-grid-item l-grid-item--4-col l-grid-item--m--3-col u-flex--justify-start";
                    } else {
                        $block_img_class = "u-hide";
                        $block_content_class = "l-grid-item l-grid-item--6-col l-grid-item--m--4-col u-flex--justify-start u-border--left";
                    }
                }
                $block_title_class = "u-theme--color--darker u-font--primary--m";
                $block_meta_class = "u-theme--color--base";
            } else {
                if ($flag('alignRight')) {
                    $block_class = "c-block--reversed c-media-block--reversed l-grid--7-col l-grid-wrap l-large-break";
                    if ($hasImage) {
                        $block_img_class = "l-grid-item l-grid-item--2-col l-grid-item--m--1-col u-padding--zero--sides";
                        $block_content_class = "l-grid-item l-grid-item--4-col l-grid-item--m--3-col l-grid-item--xl--2-col u-flex--justify-start u-border--left";
                    } else {
                        $block_img_class = "u-hide";
                        $block_content_class = "l-grid-item l-grid-item--4-col l-grid-item--m--3-col l-grid-item--l--2-col u-flex--justify-start u-border--left";
                    }
                } else {
                    $block_class = "c-media-block__row c-block__row l-grid--7-col l-grid-wrap l-standard-break";
                    if ($hasImage) {
                        $block_img_class = "l-grid-item l-grid-item--2-col l-grid-item--m--1-col l-grid-item--xl--1-col u-padding--zero--sides u-space--zero--right";
                        $block_content_class = "l-grid-item l-grid-item--4-col l-grid-item--m--3-col l-grid-item--xl--2-col u-flex--justify-start";
                    } else {
                        $block_img_class = "u-hide";
                        $block_content_class = "l-grid-item l-grid-item--6-col l-grid-item--m--4-col l-grid-item--xl--3-col u-flex--justify-start u-border--left";
                    }
                }
                $block_title_class = "u-theme--color--darker u-font--primary--m";
                $block_meta_class = "u-theme--color--base";
            }
            $block_group_class = "u-flex--justify-start";

            $list_items_markup .= sprintf(
                '<div class="c-media-block c-block %1$s">',
                esc_attr($block_class)
            );

            if ($hasImage) {
                $list_items_markup .= sprintf(
                    '<div class="c-media-block__image c-block__image %1$s">
					<div class="c-block__image-wrap ">
						<picture class="picture">
							<source srcset="%5$s" media="(min-width: 900px)">
							<source srcset="%4$s" media="(min-width: 500px)">
							<img itemprop="image" srcset="%3$s" alt="%2$s" loading="lazy" decoding="async">
						</picture>
					</div>
				</div>',
                    esc_attr($block_img_class),
                    esc_attr($image['alt']),
                    esc_url($image['s']),
                    esc_url($image['m']),
                    esc_url($image['l'])
                );
            }

            $list_items_markup .= sprintf(
                '<div class="c-media-block__content c-block__content u-spacing %1$s">
				<div class="u-spacing c-block__group c-media-block__group %2$s">
					<div class="u-spacing u-width--100p">
						<h3 class="c-media-block__title c-block__title %3$s">
							<a class="c-block__title-link u-theme--link-hover--dark" href="%4$s">%5$s</a>
						</h3>',
                esc_attr($block_content_class),
                esc_attr($block_group_class),
                esc_attr($block_title_class),
                esc_url($link),
                esc_html($title)
            );

            if (! $flag('hideExcerpt')) {
                $list_items_markup .= sprintf(
                    '<p class="c-block__description">%1$s</p>',
                    esc_html($this->excerpt($post_id, $hasImage ? 100 : 200))
                );
            }

            if (! $flag('hideButton')) {
                $list_items_markup .= sprintf(
                    '<a href="%1$s" class="c-block__button o-button o-button--outline">%2$s<span class="u-icon u-icon--m u-path-fill--base u-space--half--left"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" aria-hidden="true" focusable="false"><path d="M18.29,8.59l-3.5-3.5L13.38,6.5,15.88,9H.29v2H15.88l-2.5,2.5,1.41,1.41,3.5-3.5L19.71,10Z" fill="#9b9b9b"></path></svg></span></a>',
                    esc_url($link),
                    $readMoreLabel
                );
            }

            $list_items_markup .= "</div>\n";

            if (! $flag('hideCategoryName') || ! $flag('hidePostDate')) {
                $list_items_markup .= sprintf(
                    '<div class="c-media-block__meta c-block__meta %1$s">',
                    esc_attr($block_meta_class)
                );

                if (! $flag('hideCategoryName')) {
                    $list_items_markup .= sprintf(
                        '<span class="c-block__category u-text-transform--upper">%1$s</span>',
                        esc_html($category)
                    );
                }

                if (! $flag('hidePostDate')) {
                    $list_items_markup .= sprintf(
                        '<time datetime="%1$s" class="c-block__date u-text-transform--upper">%2$s</time>',
                        esc_attr(get_the_date('c', $post_id)),
                        esc_html(get_the_date('', $post_id))
                    );
                }

                $list_items_markup .= "</div>\n";
            }

            $list_items_markup .= "</div></div>\n";
            $list_items_markup .= "</div>\n";
        }

        $classes = [];
        if (! empty($attributes['className'])) {
            foreach (preg_split('/\s+/', (string) $attributes['className']) as $name) {
                $name = sanitize_html_class($name);
                if ('' !== $name) {
                    $classes[] = $name;
                }
            }
        }

        if ($isGrid) {
            $classes[] = 'is-grid l-section__block-row l-section__block-row--6-col';
            $classes[] = $sidebarHidden
                ? 'l-large-break u-shift--left--1-col--medium-xlarge'
                : 'l-standard-break u-shift--left--1-col--medium-large';

            return sprintf(
                '<section class="c-section c-section__blocks %1$s">
				<div class="l-grid-item u-padding--zero--sides u-flex">%2$s</div>
			</section>',
                esc_attr(implode(' ', $classes)),
                $list_items_markup
            );
        }

        $classes[] = 'is-list u-spacing--double';

        return sprintf(
            '<section class="c-section c-section__blocks %1$s">%2$s</section>',
            esc_attr(implode(' ', $classes)),
            $list_items_markup
        );
    }

    /**
     * Positive integer IDs from a list of strings or numbers.
     *
     * @param array $values Raw values.
     * @return int[]
     */
    private static function ids($values)
    {
        $ids = [];
        foreach ($values as $value) {
            if (is_scalar($value) && preg_match('/^\s*\d+\s*$/', (string) $value)) {
                $value = (int) $value;
                if ($value > 0) {
                    $ids[] = $value;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * The listed post's primary (Yoast) or first category name.
     *
     * @param int $post_id Post ID.
     * @return string
     */
    private function categoryName($post_id)
    {
        $categories = get_the_category($post_id);
        if (! $categories) {
            return '';
        }

        if (class_exists('\WPSEO_Primary_Term')) {
            $primary = (new \WPSEO_Primary_Term('category', $post_id))->get_primary_term();
            $term = $primary ? get_term((int) $primary, 'category') : null;
            if ($term instanceof \WP_Term) {
                return $term->name;
            }
        }

        return $categories[0]->name;
    }

    /**
     * The theme's header image, else the featured image.
     *
     * @param int $post_id Post ID.
     * @return int Attachment ID, or 0.
     */
    private function imageId($post_id)
    {
        $header = absint(get_post_meta($post_id, 'header_background_image', true));
        if ($header && wp_attachment_is_image($header)) {
            return $header;
        }

        return (int) get_post_thumbnail_id($post_id);
    }

    /**
     * Image URLs for the theme's 16x9 sizes, falling back to "large".
     *
     * @param int $thumb_id Attachment ID.
     * @return array|null s, m, l URLs and alt text; null when the image is missing.
     */
    private function imageSources($thumb_id)
    {
        $fallback = wp_get_attachment_image_url($thumb_id, 'large');
        $sources = [];
        foreach (['s', 'm', 'l'] as $size) {
            $url = wp_get_attachment_image_url($thumb_id, 'horiz__16x9--' . $size);
            $sources[$size] = $url ? $url : $fallback;
        }

        if (! $sources['s']) {
            return null;
        }

        $sources['alt'] = (string) get_post_meta($thumb_id, '_wp_attachment_image_alt', true);

        return $sources;
    }

    /**
     * Plain-text excerpt of the listed post, cut to $length characters.
     *
     * Password-protected posts get no excerpt, so their content never leaks.
     *
     * @param int $post_id Post ID.
     * @param int $length  Maximum characters.
     * @return string
     */
    private function excerpt($post_id, $length)
    {
        if (post_password_required($post_id)) {
            return '';
        }

        $text = get_the_excerpt($post_id);
        $text = html_entity_decode(wp_strip_all_tags((string) $text, true), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace('/\s+/u', ' ', $text));

        if (mb_strlen($text) > $length) {
            $text = rtrim(mb_substr($text, 0, $length)) . '…';
        }

        return $text;
    }
}
