<?php
defined('ABSPATH') || exit;

/* ===== אכיפה בצד שרת — מקור אמת יחיד לחסימת רכישה ===== */
class CO2B_Blocker
{
    const META_BLOCKED      = '_co2b_blocked'; // post meta על מוצר (yes/no)
    const TERM_META_BLOCKED = 'co2b_blocked';  // term meta על קטגוריה (yes/no)

    /** @var array קאש לבקשה — לפי מזהה מוצר-אב */
    private static $blocked_cache = [];

    /** @var array|null מזהי ילדים של מוצרים מקובצים (grouped) חסומים — נבנה פעם אחת לבקשה */
    private static $grouped_children = null;

    public static function init(): void
    {
        /* חסימת רכישה — WC מסתיר כפתורים וחוסם הוספה לעגלה בכל הנתיבים:
           URL ישיר (?add-to-cart=), AJAX ישן (?wc-ajax=add_to_cart), טופס, order-again, Store API */
        add_filter('woocommerce_is_purchasable', [__CLASS__, 'filter_is_purchasable'], 10, 2);
        add_filter('woocommerce_variation_is_purchasable', [__CLASS__, 'filter_is_purchasable'], 10, 2);

        /* חגורת ביטחון — הודעת שגיאה בעברית במקום הגנרית של WC */
        add_filter('woocommerce_add_to_cart_validation', [__CLASS__, 'validate_add_to_cart'], 10, 4);

        /* Store API — עגלה/צ'קאאוט בבלוקים וקריאות fetch מהקונסול */
        add_action('woocommerce_store_api_validate_add_to_cart', [__CLASS__, 'store_api_validate'], 10, 2);
        add_action('woocommerce_store_api_validate_cart_item', [__CLASS__, 'store_api_validate'], 10, 2);

        /* טיהור עגלה — מוצר שנחסם אחרי שכבר נוסף (רץ גם באימות checkout) */
        add_action('woocommerce_check_cart_items', [__CLASS__, 'purge_blocked_cart_items'], 20);
    }

    public static function flush_cache(): void
    {
        self::$blocked_cache   = [];
        self::$grouped_children = null;
    }

    /* ===== בדיקת חסימה ===== */

    /* מנהל אתר / מנהל חנות עוקפים את החסימה */
    public static function user_can_bypass(): bool
    {
        return is_user_logged_in() && current_user_can('manage_woocommerce');
    }

    /* חסימה גולמית — ללא התחשבות בהרשאות */
    public static function is_blocked($product_or_id): bool
    {
        $pid = self::resolve_parent_id($product_or_id);
        if (!$pid) {
            return false;
        }
        if (isset(self::$blocked_cache[$pid])) {
            return self::$blocked_cache[$pid];
        }

        $blocked = self::compute_blocked($pid);
        self::$blocked_cache[$pid] = $blocked;
        return $blocked;
    }

    /* חסימה בפועל למשתמש הנוכחי */
    public static function is_blocked_for_current_user($product_or_id): bool
    {
        if (self::user_can_bypass()) {
            return false;
        }
        return self::is_blocked($product_or_id);
    }

    /* וריאציה → מוצר-אב; מקבל WC_Product או מזהה (?add-to-cart= עשוי להעביר מזהה וריאציה) */
    private static function resolve_parent_id($product_or_id): int
    {
        if ($product_or_id instanceof WC_Product) {
            return $product_or_id->is_type('variation')
                ? (int) $product_or_id->get_parent_id()
                : (int) $product_or_id->get_id();
        }
        $id = (int) $product_or_id;
        if ($id && get_post_type($id) === 'product_variation') {
            $id = (int) wp_get_post_parent_id($id);
        }
        return $id;
    }

    private static function compute_blocked(int $pid): bool
    {
        /* 1. נבחר בעמוד ההגדרות */
        $product_ids = array_map('intval', (array) CO2B_Settings::get('product_ids'));
        if (in_array($pid, $product_ids, true)) {
            return true;
        }

        /* 2. צ'קבוקס במוצר עצמו */
        if (get_post_meta($pid, self::META_BLOCKED, true) === 'yes') {
            return true;
        }

        /* 3. קטגוריות — כולל קטגוריות-אב (חסימת אב חוסמת גם תתי-קטגוריות) */
        $terms = wc_get_product_term_ids($pid, 'product_cat');
        if ($terms) {
            $effective = $terms;
            foreach ($terms as $tid) {
                $effective = array_merge($effective, get_ancestors($tid, 'product_cat', 'taxonomy'));
            }
            $effective = array_unique(array_map('intval', $effective));

            $category_ids = array_map('intval', (array) CO2B_Settings::get('category_ids'));
            if (array_intersect($effective, $category_ids)) {
                return true;
            }
            foreach ($effective as $tid) {
                if (get_term_meta($tid, self::TERM_META_BLOCKED, true) === 'yes') {
                    return true;
                }
            }
        }

        /* 4. ילד של מוצר מקובץ (grouped) חסום — הילדים נמכרים בנפרד ולכן חייבים לרשת את החסימה */
        if (isset(self::blocked_grouped_children()[$pid])) {
            return true;
        }

        return false;
    }

    /* בונה פעם אחת לבקשה את קבוצת מזהי הילדים של כל מוצר מקובץ חסום.
       הצבת [] לפני הלולאה עוצרת רקורסיה: is_blocked($gid) בתוך הלולאה יקרא שוב
       ל-blocked_grouped_children() אך יקבל את המערך (הריק/בבנייה) ולא ייכנס שוב. */
    private static function blocked_grouped_children(): array
    {
        if (self::$grouped_children !== null) {
            return self::$grouped_children;
        }
        self::$grouped_children = [];
        if (!function_exists('wc_get_products')) {
            return self::$grouped_children;
        }
        $grouped_ids = wc_get_products([
            'type'   => 'grouped',
            'status' => 'publish',
            'limit'  => -1,
            'return' => 'ids',
        ]);
        foreach ($grouped_ids as $gid) {
            if (!self::is_blocked($gid)) {
                continue;
            }
            $product = wc_get_product($gid);
            if ($product) {
                foreach ($product->get_children() as $child_id) {
                    self::$grouped_children[(int) $child_id] = true;
                }
            }
        }
        return self::$grouped_children;
    }

    /* ===== callbacks של אכיפה ===== */

    public static function filter_is_purchasable($purchasable, $product)
    {
        if ($purchasable && self::is_blocked_for_current_user($product)) {
            return false;
        }
        return $purchasable;
    }

    public static function validate_add_to_cart($passed, $product_id, $quantity, $variation_id = 0)
    {
        $check_id = $variation_id ?: $product_id;
        if ($passed && self::is_blocked_for_current_user($check_id)) {
            wc_add_notice(wp_strip_all_tags(CO2B_Settings::notice_text()), 'error');
            return false;
        }
        return $passed;
    }

    /* פרמטר שני: $request (בהוספה) או $cart_item (בפריט קיים) — לא נדרש */
    public static function store_api_validate($product, $unused = null): void
    {
        if (self::is_blocked_for_current_user($product)) {
            throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
                'co2b_product_blocked',
                wp_strip_all_tags(CO2B_Settings::notice_text()),
                403
            );
        }
    }

    public static function purge_blocked_cart_items(): void
    {
        if (self::user_can_bypass() || !WC()->cart) {
            return;
        }
        foreach (WC()->cart->get_cart() as $key => $item) {
            if (self::is_blocked($item['product_id'])) {
                WC()->cart->remove_cart_item($key);
                $product = wc_get_product($item['product_id']);
                $name    = $product ? $product->get_name() : '';
                wc_add_notice(
                    sprintf('המוצר "%s" הוסר מהעגלה — %s', $name, wp_strip_all_tags(CO2B_Settings::notice_text())),
                    'error'
                );
            }
        }
    }
}
