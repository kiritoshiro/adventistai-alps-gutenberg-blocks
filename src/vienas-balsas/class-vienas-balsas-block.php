<?php
namespace ALPS\Gutenberg\Blocks;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * "Vienas balsas" campaign panel: a heading with a link, one to three project
 * cards and an optional call-to-action card. Everything comes from the block's
 * attributes; there are no queries or remote requests.
 */
class VienasBalsasBlock
{
    const HANDLE = 'alps-gb-vienas-balsas';
    const MAX_CARDS = 3;
    // Card widths: one column on phones, two on tablets, three in the content column on desktops.
    const IMAGE_SIZES = '(max-width: 600px) calc(100vw - 2.5rem), (max-width: 1024px) 45vw, 320px';

    private static $defaults;

    public function init()
    {
        $pluginFile = dirname(__DIR__, 2) . '/plugin.php';
        wp_register_style(self::HANDLE, plugins_url('dist/vienas-balsas.css', $pluginFile), [], ALPS_GUTENBERG_VERSION);
        register_block_type(__DIR__ . '/block.json', ['render_callback' => [$this, 'render']]);
    }

    /** The defaults in block.json, the one source for attributes that are missing. */
    public static function defaults()
    {
        if (null === self::$defaults) {
            $meta = json_decode((string) file_get_contents(__DIR__ . '/block.json'), true);
            self::$defaults = [];
            foreach ((array) ($meta['attributes'] ?? []) as $key => $schema) {
                self::$defaults[$key] = $schema['default'] ?? null;
            }
        }
        return self::$defaults;
    }

    /** Plain text: a missing or non-scalar value gives the default; tags are removed and spaces collapsed. */
    private static function text($source, $key, $default = '')
    {
        $value = isset($source[$key]) && is_scalar($source[$key]) ? (string) $source[$key] : (string) $default;
        return trim((string) preg_replace('/\s+/u', ' ', wp_strip_all_tags($value)));
    }

    /**
     * An http(s) or site-relative address, ready for an href; anything else
     * (javascript:, data:, mailto:, protocol-relative //host, backslashes,
     * control characters) gives ''.
     */
    public static function url($value)
    {
        if (! is_scalar($value)) {
            return '';
        }
        $url = trim(html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ('' === $url || preg_match('/[\x00-\x1f\x7f\\\\]/', $url) || 0 === strpos($url, '//')) {
            return '';
        }
        if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', $url, $scheme)
            && (! in_array(strtolower($scheme[1]), ['http', 'https'], true) || ! preg_match('#^https?://[^/?\#]#i', $url))) {
            return '';
        }
        return esc_url($url, ['http', 'https']);
    }

    private static function flag($attributes, $key, $default)
    {
        $value = isset($attributes[$key]) && is_scalar($attributes[$key]) ? $attributes[$key] : $default;
        return in_array($value, [true, 1, '1', 'true'], true);
    }

    /** The heading level (2–6, default 2); card titles are one level lower. */
    public static function headingLevel($attributes)
    {
        $value = isset($attributes['headingLevel']) && is_numeric($attributes['headingLevel']) ? (int) $attributes['headingLevel'] : 2;
        return $value >= 2 && $value <= 6 ? $value : 2;
    }

    /**
     * One to three cards, each an array. A missing, broken or empty list gives
     * the default cards, so the panel never renders without a project card.
     */
    public static function cards($attributes)
    {
        $cards = isset($attributes['cards']) && is_array($attributes['cards']) ? array_values(array_filter($attributes['cards'], [self::class, 'isCard'])) : [];
        return array_slice($cards ?: self::defaults()['cards'], 0, self::MAX_CARDS);
    }

    /**
     * A saved card: an array with at least one card field (null, strings, numbers,
     * lists and empty arrays are not cards). Mirrors isCard() in edit.js.
     */
    public static function isCard($card)
    {
        return is_array($card) && (bool) array_intersect_key($card, array_flip(['imageId', 'eyebrow', 'title', 'text', 'url']));
    }

    public function render($attributes)
    {
        $attributes = is_array($attributes) ? $attributes : [];
        $defaults = self::defaults();
        $text = function ($key) use ($attributes, $defaults) {
            return self::text($attributes, $key, $defaults[$key]);
        };
        wp_enqueue_style(self::HANDLE);

        $heading = $text('heading');
        $level = self::headingLevel($attributes);
        // Card titles sit one level below the panel heading. Without a heading
        // they take its level, and the panel is a plain <div>, not an unnamed <section>.
        $cardTag = 'h' . ('' === $heading ? $level : min(6, $level + 1));
        $tag = '' === $heading ? 'div' : 'section';
        $cards = self::cards($attributes);
        $showCta = self::flag($attributes, 'showCta', true);
        $items = count($cards) + ($showCta ? 1 : 0);

        $wrapper = get_block_wrapper_attributes([
            'class' => 'alps-vb alps-vb--cards-' . count($cards) . ($showCta ? ' alps-vb--has-cta' : ''),
            'style' => sprintf('--alps-vb-cols:%d;--alps-vb-tablet-cols:%d;', max(1, min(3, $items)), max(1, min(2, $items))),
        ]);
        $html = '<' . $tag . ' ' . $wrapper . '>';

        $linkText = $text('linkText');
        $linkUrl = self::url($attributes['linkUrl'] ?? '');
        if ('' !== $heading || ('' !== $linkText && '' !== $linkUrl)) {
            $html .= '<div class="alps-vb__head">';
            if ('' !== $heading) {
                $html .= sprintf('<h%1$d class="alps-vb__heading">%2$s</h%1$d>', $level, esc_html($heading));
            }
            if ('' !== $linkText && '' !== $linkUrl) {
                $html .= '<a class="alps-vb__head-link" href="' . $linkUrl . '">' . esc_html($linkText) . '<span class="alps-vb__arrow" aria-hidden="true">→</span></a>';
            }
            $html .= '</div>';
        }

        $moreText = $text('moreText');
        $html .= '<div class="alps-vb__grid">';
        foreach ($cards as $card) {
            $title = self::text($card, 'title');
            $html .= '<div class="alps-vb__card">';
            $imageId = isset($card['imageId']) && is_numeric($card['imageId']) ? (int) $card['imageId'] : 0;
            // The image's alt text comes from its Media Library item.
            $image = $imageId > 0 ? wp_get_attachment_image($imageId, 'medium_large', false, [
                'class'    => 'alps-vb__img',
                'loading'  => 'lazy',
                'decoding' => 'async',
                'sizes'    => self::IMAGE_SIZES,
            ]) : '';
            if ($image) {
                $html .= '<div class="alps-vb__media">' . $image . '</div>';
            }
            $html .= '<div class="alps-vb__body">';
            $html .= self::paragraph('alps-vb__eyebrow', self::text($card, 'eyebrow'));
            if ('' !== $title) {
                $html .= '<' . $cardTag . ' class="alps-vb__title">' . esc_html($title) . '</' . $cardTag . '>';
            }
            $html .= self::paragraph('alps-vb__text', self::text($card, 'text'));
            $url = self::url($card['url'] ?? '');
            if ('' !== $moreText && '' !== $url) {
                // Screen readers hear "Daugiau: <card title>", which makes sense out of context.
                $hidden = '' !== $title ? '<span class="alps-vb__sr">: ' . esc_html($title) . '</span>' : '';
                $html .= '<a class="alps-vb__more" href="' . $url . '">' . esc_html($moreText) . $hidden . '<span class="alps-vb__arrow" aria-hidden="true">→</span></a>';
            }
            $html .= '</div></div>';
        }

        if ($showCta) {
            $ctaTitle = $text('ctaTitle');
            $html .= '<div class="alps-vb__cta">';
            $html .= self::paragraph('alps-vb__eyebrow', $text('ctaEyebrow'));
            if ('' !== $ctaTitle) {
                $html .= '<' . $cardTag . ' class="alps-vb__title">' . esc_html($ctaTitle) . '</' . $cardTag . '>';
            }
            $html .= self::paragraph('alps-vb__text', $text('ctaText'));
            $buttonText = $text('ctaButtonText');
            $ctaUrl = self::url($attributes['ctaUrl'] ?? '');
            if ('' !== $buttonText && '' !== $ctaUrl) {
                $html .= '<a class="alps-vb__button" href="' . $ctaUrl . '">' . esc_html($buttonText) . '</a>';
            }
            $html .= '</div>';
        }

        return $html . '</div></' . $tag . '>';
    }

    private static function paragraph($class, $text)
    {
        return '' === $text ? '' : '<p class="' . $class . '">' . esc_html($text) . '</p>';
    }
}
