<?php
defined('ABSPATH') || exit;

/* ===== תצוגה בפרונט — כרטיס חיווי במקום כפתור קנייה ===== */
class CO2B_Frontend
{
    public static function init(): void
    {
        /* עמוד מוצר — החלפת אזור ההוספה לעגלה בחיווי (עדיפות 29, רגע לפני 30 של WC) */
        add_action('woocommerce_single_product_summary', [__CLASS__, 'maybe_replace_single_add_to_cart'], 29);

        /* עמודי רשימה (חנות/קטגוריה/related) — החלפת כפתור הלולאה */
        add_filter('woocommerce_loop_add_to_cart_link', [__CLASS__, 'filter_loop_add_to_cart'], 10, 2);
        add_filter('flatsome_woocommerce_shop_loop_add_to_cart_link', [__CLASS__, 'filter_loop_add_to_cart'], 10, 2);

        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    public static function enqueue_assets(): void
    {
        wp_enqueue_style('co2b-frontend', CO2B_PLUGIN_URL . 'assets/css/frontend.css', [], CO2B_VERSION);
    }

    /* אייקון חנות (Lucide "store", מוטמע inline — ללא בקשות חיצוניות) */
    public static function icon_svg(): string
    {
        return '<svg class="co2b-notice-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"'
            . ' stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . '<path d="m2 7 4.41-4.41A2 2 0 0 1 7.83 2h8.34a2 2 0 0 1 1.42.59L22 7"/>'
            . '<path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/>'
            . '<path d="M15 22v-4a2 2 0 0 0-2-2h-2a2 2 0 0 0-2 2v4"/>'
            . '<path d="M2 7h20"/>'
            . '<path d="M22 7v3a2 2 0 0 1-2 2 2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 16 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 12 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 8 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 4 12a2 2 0 0 1-2-2V7"/>'
            . '</svg>';
    }

    /* כרטיס החיווי */
    public static function notice_html(bool $compact = false): string
    {
        $class = 'co2b-notice' . ($compact ? ' co2b-notice-loop' : '');
        return '<div class="' . esc_attr($class) . '" role="note">'
            . self::icon_svg()
            . '<span class="co2b-notice-text">' . esc_html(CO2B_Settings::notice_text()) . '</span>'
            . '</div>';
    }

    public static function maybe_replace_single_add_to_cart(): void
    {
        global $product;
        if (!$product instanceof WC_Product || !CO2B_Blocker::is_blocked_for_current_user($product)) {
            return;
        }
        /* הסרת callback שטרם רץ באותו hook נתמכת ב-WP_Hook */
        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);
        echo self::notice_html(); // phpcs:ignore WordPress.Security.EscapeOutput -- מנוקה ב-notice_html()
    }

    public static function filter_loop_add_to_cart($html, $product)
    {
        if ($product instanceof WC_Product && CO2B_Blocker::is_blocked_for_current_user($product)) {
            return self::notice_html(true);
        }
        return $html;
    }
}
