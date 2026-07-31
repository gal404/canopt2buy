<?php
defined('ABSPATH') || exit;

/* ===== ליבת הזמנה סיטונאית — סטטוס, זכאות, עגלת session, חישוב, יצירת הזמנה ===== */
class CO2B_Wholesale
{
    const STATUS      = 'co2b-whsale';    // כפי ש-get_status() מחזיר (בלי wc-)
    const STATUS_FULL = 'wc-co2b-whsale'; // מפתח פילטרים ושאילתות (עם wc-)
    const WS_KEY      = 'co2b_wholesale_cart';
    const NONCE       = 'co2b_ws';
    const TRANSIENT_UNREAD = 'co2b_unread_count';

    /* מטא של ההזמנה */
    const META_FLAG      = '_co2b_wholesale';
    const META_UNREAD    = '_co2b_unread';
    const META_NOTIFIED  = '_co2b_notified';
    const META_VAT_RATE  = '_co2b_vat_rate';
    const META_NET       = '_co2b_net';
    const META_VAT       = '_co2b_vat';
    const META_SHIP      = '_co2b_shipping_gross';
    const META_INVOICE   = '_co2b_invoice_name';
    const META_ACK_SHIP  = '_co2b_ack_shipping';
    const META_ACK_UNLOAD = '_co2b_ack_unloading';

    /** @var array קאש זכאות לבקשה */
    private static $eligible_cache = [];

    public static function init(): void
    {
        /* הסטטוסים נרשמים תמיד — כדי שהזמנות קיימות יוצגו נכון גם אם המודול כובה */
        add_filter('woocommerce_register_shop_order_post_statuses', [__CLASS__, 'register_post_status']);
        add_filter('wc_order_statuses', [__CLASS__, 'add_order_status']);

        /* התראה על הזמנה סיטונאית חדשה (רץ גם בשליחה מהפרונט) */
        add_action('woocommerce_order_status_changed', [__CLASS__, 'on_status_changed'], 20, 4);

        /* איפוס ספירת "לא נקרא" במחיקה/אשפה/שחזור (HPOS + legacy) */
        foreach (['woocommerce_trash_order', 'woocommerce_delete_order', 'woocommerce_untrash_order',
                  'trashed_post', 'untrashed_post', 'before_delete_post'] as $hook) {
            add_action($hook, [__CLASS__, 'flush_unread']);
        }
    }

    /* ===== סטטוסי הזרימה של הזמנה סיטונאית (בלי קידומת wc-) ===== */
    public static function statuses(): array
    {
        return [
            'co2b-whsale'    => 'סיטונאית: חדשה',
            'co2b-handling'  => 'סיטונאית: בטיפול',
            'co2b-warehouse' => 'סיטונאית: נשלח למחסן',
            'co2b-shipping'  => 'סיטונאית: יצא להובלה',
            'co2b-delivered' => 'סיטונאית: הגיע ללקוח',
        ];
    }

    /* מפתחות הסטטוסים עם קידומת wc- (לשאילתות wc_get_orders) */
    public static function status_keys_prefixed(): array
    {
        return array_map(function ($k) {
            return 'wc-' . $k;
        }, array_keys(self::statuses()));
    }

    public static function flush_unread($id = 0): void
    {
        delete_transient(self::TRANSIENT_UNREAD);
    }

    public static function enabled(): bool
    {
        return (bool) CO2B_Settings::get('wholesale_enabled');
    }

    public static function flush_cache(): void
    {
        self::$eligible_cache = [];
    }

    /* יצירת עמודי הקטלוג והעגלה אם חסרים — נקרא באקטיבציה וגם בעדכון גרסה
       (מרפא התקנות שעודכנו בלי הפעלה מחדש). חייב לרוץ אחרי init של סוגי הפוסטים. */
    public static function ensure_pages(): void
    {
        $map = [
            'wholesale_catalog_page_id' => ['title' => 'קטלוג סיטונאי', 'shortcode' => '[co2b_wholesale_catalog]'],
            'wholesale_cart_page_id'    => ['title' => 'הזמנה סיטונאית', 'shortcode' => '[co2b_wholesale_cart]'],
        ];
        $changes = [];
        foreach ($map as $key => $data) {
            $existing = (int) CO2B_Settings::get($key);
            if ($existing && get_post_status($existing) === 'publish') {
                continue;
            }
            $page_id = wp_insert_post([
                'post_title'   => $data['title'],
                'post_content' => $data['shortcode'],
                'post_status'  => 'publish',
                'post_type'    => 'page',
            ]);
            if ($page_id && !is_wp_error($page_id)) {
                $changes[$key] = (int) $page_id;
            }
        }
        if ($changes) {
            CO2B_Settings::update($changes);
        }
    }

    /* ===== רישום סטטוס ===== */

    public static function register_post_status($statuses)
    {
        foreach (self::statuses() as $key => $label) {
            $statuses['wc-' . $key] = [
                'label'                     => $label,
                'public'                    => false,
                'exclude_from_search'       => false,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                'label_count'               => _n_noop(
                    $label . ' <span class="count">(%s)</span>',
                    $label . ' <span class="count">(%s)</span>'
                ),
            ];
        }
        return $statuses;
    }

    public static function add_order_status($statuses)
    {
        $ours = [];
        foreach (self::statuses() as $key => $label) {
            $ours['wc-' . $key] = $label;
        }
        $out = [];
        foreach ($statuses as $key => $label) {
            $out[$key] = $label;
            if ($key === 'wc-processing') {
                $out += $ours;
            }
        }
        foreach ($ours as $key => $label) {
            if (!isset($out[$key])) {
                $out[$key] = $label;
            }
        }
        return $out;
    }

    /* ===== זכאות (איחוד: חסום ∪ בחירה סיטונאית) ===== */

    public static function is_eligible($product_or_id): bool
    {
        $pid = CO2B_Blocker::resolve_parent_id($product_or_id);
        if (!$pid) {
            return false;
        }
        if (isset(self::$eligible_cache[$pid])) {
            return self::$eligible_cache[$pid];
        }
        $eligible = self::compute_eligible($pid);
        self::$eligible_cache[$pid] = $eligible;
        return $eligible;
    }

    /* ניתן להזמנה בפועל — זכאי + מחיר חיובי + לא grouped/variable
       (grouped: הילדים נמכרים בנפרד; variable: מחיר האב הוא המינימום ולא מדויק) */
    public static function is_orderable($product_or_id): bool
    {
        if (!self::is_eligible($product_or_id)) {
            return false;
        }
        $p = $product_or_id instanceof WC_Product
            ? $product_or_id
            : wc_get_product(CO2B_Blocker::resolve_parent_id($product_or_id));
        if (!$p || $p->is_type('grouped') || $p->is_type('variable')) {
            return false;
        }
        $price = $p->get_price();
        return $price !== '' && (float) $price > 0;
    }

    private static function compute_eligible(int $pid): bool
    {
        /* כל מוצר חסום זכאי לסיטונאות */
        if (CO2B_Blocker::is_blocked($pid)) {
            return true;
        }
        /* בחירה סיטונאית נפרדת — מוצרים */
        $wp_ids = array_map('intval', (array) CO2B_Settings::get('wholesale_product_ids'));
        if (in_array($pid, $wp_ids, true)) {
            return true;
        }
        /* בחירה סיטונאית נפרדת — קטגוריות (כולל אבות) */
        $wc_ids = array_map('intval', (array) CO2B_Settings::get('wholesale_category_ids'));
        if ($wc_ids) {
            $effective = CO2B_Blocker::effective_term_ids($pid);
            if ($effective && array_intersect($effective, $wc_ids)) {
                return true;
            }
        }
        return false;
    }

    /* ===== עגלת session ===== */

    public static function get_cart(): array
    {
        if (!WC()->session) {
            return [];
        }
        $cart = WC()->session->get(self::WS_KEY);
        return is_array($cart) ? $cart : [];
    }

    private static function save_cart(array $cart): void
    {
        if (!WC()->session) {
            return;
        }
        $clean = [];
        foreach ($cart as $id => $qty) {
            $id  = (int) $id;
            $qty = (int) $qty;
            if ($id > 0 && $qty > 0) {
                $clean[$id] = $qty;
            }
        }
        WC()->session->set(self::WS_KEY, $clean);
        /* אורחים: מכריח שליחת עוגיית session כדי שהעגלה לא תיעלם */
        WC()->session->set_customer_session_cookie(true);
    }

    public static function add_item(int $pid, int $qty): bool
    {
        if (!self::is_orderable($pid)) {
            return false;
        }
        $cart = self::get_cart();
        $cart[$pid] = (isset($cart[$pid]) ? (int) $cart[$pid] : 0) + max(1, $qty);
        self::save_cart($cart);
        return true;
    }

    public static function set_item(int $pid, int $qty): void
    {
        $cart = self::get_cart();
        if ($qty <= 0) {
            unset($cart[$pid]);
        } else {
            $cart[$pid] = $qty;
        }
        self::save_cart($cart);
    }

    public static function remove_item(int $pid): void
    {
        $cart = self::get_cart();
        unset($cart[$pid]);
        self::save_cart($cart);
    }

    public static function clear_cart(): void
    {
        if (WC()->session) {
            WC()->session->set(self::WS_KEY, []);
        }
    }

    public static function count_items(?array $items = null): int
    {
        $items = $items ?? self::get_cart();
        $n = 0;
        foreach ($items as $qty) {
            $n += (int) $qty;
        }
        return $n;
    }

    /* ===== חישוב סכומים — מקור אמת יחיד ===== */

    public static function compute_totals(?array $items = null): array
    {
        $items = $items ?? self::get_cart();
        $dp    = wc_get_price_decimals();
        $vat   = (float) CO2B_Settings::get('wholesale_vat');
        $min   = (float) CO2B_Settings::get('wholesale_min');
        $freeT = (float) CO2B_Settings::get('wholesale_free_ship');

        $goods = 0.0;
        $lines = [];
        foreach ($items as $id => $qty) {
            $id  = (int) $id;
            $qty = max(1, (int) $qty);
            $p   = wc_get_product($id);
            if (!$p || !self::is_orderable($p)) {
                continue;
            }
            $unit = (float) $p->get_price();
            $line = round($unit * $qty, $dp);
            $goods += $line;

            $img = $p->get_image_id() ? wp_get_attachment_image_url($p->get_image_id(), 'thumbnail') : wc_placeholder_img_src('thumbnail');
            $lines[] = [
                'id'    => $id,
                'name'  => $p->get_name(),
                'qty'   => $qty,
                'unit'  => $unit,
                'line'  => $line,
                'image' => $img,
                'permalink' => get_permalink($id),
            ];
        }
        $goods = round($goods, $dp);

        $meets_min    = $goods >= $min;
        $ship_applied = ($goods > 0 && $goods <= $freeT);
        $ship_gross   = $ship_applied ? self::shipping_gross($vat, $dp) : 0.0;
        $grand        = round($goods + $ship_gross, $dp);
        $net          = $grand > 0 ? round($grand / (1 + $vat / 100), $dp) : 0.0;
        $vat_amt      = round($grand - $net, $dp);

        return [
            'lines'          => $lines,
            'goods'          => $goods,
            'ship_gross'     => $ship_gross,
            'grand'          => $grand,
            'net'            => $net,
            'vat'            => $vat_amt,
            'ship_applied'   => $ship_applied,
            'meets_min'      => $meets_min,
            'min'            => $min,
            'free_threshold' => $freeT,
            'vat_rate'       => $vat,
            'count'          => self::count_items($items),
        ];
    }

    private static function shipping_gross(float $vat, int $dp): float
    {
        $ship_pid = (int) CO2B_Settings::get('wholesale_ship_product_id');
        if ($ship_pid) {
            $sp = wc_get_product($ship_pid);
            if ($sp) {
                return round((float) $sp->get_price(), $dp); // מחיר המוצר כבר כולל מע"מ
            }
        }
        $net = (float) CO2B_Settings::get('wholesale_ship_net');
        return round($net * (1 + $vat / 100), $dp);
    }

    /* ===== יצירת ההזמנה — בלי שער, בלי מלאי, מע"מ-inclusive ===== */

    public static function create_order(array $customer): array
    {
        $items  = self::get_cart();
        $totals = self::compute_totals($items);

        if (empty($totals['lines'])) {
            return ['success' => false, 'message' => 'העגלה ריקה.'];
        }
        if (!$totals['meets_min']) {
            return ['success' => false, 'message' => sprintf('מינימום להזמנה סיטונאית: %s.', wp_strip_all_tags(wc_price($totals['min'])))];
        }

        $order = wc_create_order(['customer_id' => get_current_user_id()]);
        if (is_wp_error($order)) {
            return ['success' => false, 'message' => 'שגיאה ביצירת ההזמנה.'];
        }

        /* שורות מוצר — הסכום (gross) מועבר ישירות ל-add_product עם איפוס מס שורה.
           קריטי: אין להשתמש ב-get_item()+set_total, כי get_item טוען מופע נפרד מה-DB
           ש-calculate_totals מתעלם ממנו (הסכום היה נופל חזרה ל-excluding_tax). */
        foreach ($totals['lines'] as $ln) {
            $p = wc_get_product($ln['id']);
            if (!$p) {
                continue;
            }
            $order->add_product($p, $ln['qty'], [
                'subtotal' => $ln['line'],
                'total'    => $ln['line'],
                'taxes'    => ['total' => [], 'subtotal' => []],
            ]);
        }

        /* שורת הובלה — מוצר מקושר (עדיף) או שורת חיוב */
        if ($totals['ship_applied'] && $totals['ship_gross'] > 0) {
            $ship_pid = (int) CO2B_Settings::get('wholesale_ship_product_id');
            $sp       = $ship_pid ? wc_get_product($ship_pid) : null;
            if ($sp) {
                $order->add_product($sp, 1, [
                    'subtotal' => $totals['ship_gross'],
                    'total'    => $totals['ship_gross'],
                    'taxes'    => ['total' => [], 'subtotal' => []],
                ]);
            } else {
                $fee = new WC_Order_Item_Fee();
                $fee->set_name('הובלה סיטונאית');
                $fee->set_amount($totals['ship_gross']);
                $fee->set_total($totals['ship_gross']);
                $fee->set_tax_status('none');
                $fee->set_taxes(['total' => []]);
                $order->add_item($fee);
            }
        }

        /* פרטי לקוח */
        $order->set_billing_first_name($customer['first_name']);
        $order->set_billing_last_name($customer['last_name']);
        $order->set_billing_phone($customer['phone']);
        $order->set_billing_email($customer['email']);
        $order->set_billing_address_1($customer['address']);
        $order->set_billing_city($customer['city']);
        $order->set_billing_country('IL');
        $order->set_shipping_first_name($customer['first_name']);
        $order->set_shipping_last_name($customer['last_name']);
        $order->set_shipping_address_1($customer['address']);
        $order->set_shipping_city($customer['city']);
        $order->set_shipping_country('IL');

        /* מטא */
        $order->update_meta_data(self::META_FLAG, 'yes');
        $order->update_meta_data(self::META_UNREAD, 'yes');
        $order->update_meta_data(self::META_VAT_RATE, $totals['vat_rate']);
        $order->update_meta_data(self::META_NET, $totals['net']);
        $order->update_meta_data(self::META_VAT, $totals['vat']);
        $order->update_meta_data(self::META_SHIP, $totals['ship_gross']);
        $order->update_meta_data(self::META_INVOICE, $customer['invoice_name']);
        $order->update_meta_data(self::META_ACK_SHIP, 'yes');
        $order->update_meta_data(self::META_ACK_UNLOAD, 'yes');

        $order->set_created_via('co2b_wholesale');
        $order->add_order_note('הזמנה סיטונאית שנוצרה דרך התוסף. אישור נהלי הובלה ופריקה: כן.');
        $order->set_status(self::STATUS, 'הזמנה סיטונאית חדשה');
        $order->calculate_totals(false); // בלי מנוע מס — רק סיכום שורות
        $order->save();

        self::clear_cart();

        return [
            'success'  => true,
            'order_id' => $order->get_id(),
            'number'   => $order->get_order_number(),
            'message'  => CO2B_Settings::confirm_text(),
        ];
    }

    /* ===== התראה על הזמנה חדשה ===== */

    public static function on_status_changed($order_id, $old, $new, $order = null): void
    {
        if (!$order instanceof WC_Order) {
            $order = wc_get_order($order_id);
        }
        if (!$order) {
            return;
        }

        $is_ws   = array_key_exists($new, self::statuses());
        $flagged = $order->get_meta(self::META_FLAG) === 'yes';

        /* אימוץ: הזמנה רגילה שהועברה ידנית לסטטוס סיטונאי (דרך עורך WooCommerce)
           הופכת להזמנה סיטונאית מנוהלת — בלי מייל (המנהל יזם את השינוי). */
        if ($is_ws && !$flagged) {
            $order->update_meta_data(self::META_FLAG, 'yes');
            $order->update_meta_data(self::META_NOTIFIED, 'yes');
            $order->save_meta_data();
            delete_transient(self::TRANSIENT_UNREAD);
            return;
        }

        if (!$flagged) {
            return; // לא הזמנה סיטונאית
        }
        delete_transient(self::TRANSIENT_UNREAD);

        if ($new === self::STATUS) {
            /* סטטוס התחלתי (חדשה) — התראה חד-פעמית */
            if ($order->get_meta(self::META_NOTIFIED) !== 'yes') {
                $order->update_meta_data(self::META_NOTIFIED, 'yes');
                $order->save_meta_data();
                self::send_admin_email($order);
            }
        } elseif ($is_ws) {
            /* התקדמה לשלב מאוחר יותר — כבר לא "חדשה/לא נקראה" */
            if ($order->get_meta(self::META_UNREAD) === 'yes') {
                $order->update_meta_data(self::META_UNREAD, 'no');
                $order->save_meta_data();
            }
        }
    }

    private static function send_admin_email(WC_Order $order): void
    {
        $to = CO2B_Settings::alert_email();
        if (!$to) {
            return;
        }
        $lines = [];
        foreach ($order->get_items() as $item) {
            $lines[] = '• ' . $item->get_name() . ' × ' . $item->get_quantity();
        }
        $body = "התקבלה הזמנה סיטונאית חדשה (#" . $order->get_order_number() . ")\n\n"
            . "שם: " . $order->get_formatted_billing_full_name() . "\n"
            . "טלפון: " . $order->get_billing_phone() . "\n"
            . "אימייל: " . $order->get_billing_email() . "\n"
            . "כתובת: " . $order->get_billing_address_1() . ', ' . $order->get_billing_city() . "\n"
            . "שם לחשבונית: " . $order->get_meta(self::META_INVOICE) . "\n\n"
            . "פריטים:\n" . implode("\n", $lines) . "\n\n"
            . "סה\"כ (כולל מע\"מ): " . wp_strip_all_tags(wc_price($order->get_total())) . "\n\n"
            . "לצפייה: " . admin_url('admin.php?page=co2b-wholesale&action=view&id=' . $order->get_id());

        wp_mail($to, 'הזמנה סיטונאית חדשה #' . $order->get_order_number(), $body);
    }

    /* ===== ספירת "לא נקרא" + סימון נקרא ===== */

    public static function unread_count(): int
    {
        $cached = get_transient(self::TRANSIENT_UNREAD);
        if ($cached !== false) {
            return (int) $cached;
        }
        /* סינון ה"לא נקרא" ב-PHP — meta_query ב-wc_get_orders אינו אמין ב-HPOS */
        $count = 0;
        foreach (self::get_orders() as $o) {
            if ($o->get_meta(self::META_UNREAD) === 'yes') {
                $count++;
            }
        }
        set_transient(self::TRANSIENT_UNREAD, $count, 300);
        return $count;
    }

    public static function mark_read(WC_Order $order): void
    {
        if ($order->get_meta(self::META_UNREAD) === 'yes') {
            $order->update_meta_data(self::META_UNREAD, 'no');
            $order->save_meta_data();
            delete_transient(self::TRANSIENT_UNREAD);
        }
    }

    /* מזהי ההזמנות הסיטונאיות — סינון לפי סטטוס בלבד.
       חשוב: אין להסתמך על meta_query ב-wc_get_orders — ב-HPOS הוא מתעלם
       ומחזיר את כל ההזמנות. סינון לפי מטא נעשה תמיד ב-PHP. */
    public static function get_order_ids(): array
    {
        $ids = wc_get_orders([
            'status' => self::status_keys_prefixed(),
            'limit'  => -1,
            'return' => 'ids',
        ]);
        $ids = array_map('intval', (array) $ids);
        rsort($ids, SORT_NUMERIC); // החדשות ראשונות
        return $ids;
    }

    /* הזמנות סיטונאיות מאומתות (WC_Order[]) — עם אימות ב-PHP */
    public static function get_orders(array $ids = null): array
    {
        $ids = $ids ?? self::get_order_ids();
        $out = [];
        foreach ($ids as $id) {
            $o = wc_get_order($id);
            if ($o && self::is_managed($o)) {
                $out[] = $o;
            }
        }
        return $out;
    }

    /* הזמנה מנוהלת ע"י התוסף — סומנה כסיטונאית או נמצאת באחד מסטטוסי הסיטונאות */
    public static function is_managed($order): bool
    {
        if (!$order instanceof WC_Order) {
            return false;
        }
        return $order->get_meta(self::META_FLAG) === 'yes'
            || array_key_exists($order->get_status(), self::statuses());
    }

    /* אימוץ הזמנה שהומרה ידנית לסטטוס סיטונאי — מסמן אותה כסיטונאית (בלי מייל) */
    public static function adopt(WC_Order $order): void
    {
        if ($order->get_meta(self::META_FLAG) !== 'yes') {
            $order->update_meta_data(self::META_FLAG, 'yes');
            if ($order->get_meta(self::META_NOTIFIED) !== 'yes') {
                $order->update_meta_data(self::META_NOTIFIED, 'yes');
            }
            $order->save_meta_data();
        }
    }

    /* ===== נרמול טלפון ישראלי ל-E.164 (מחזיר null אם לא תקין) ===== */

    public static function normalize_phone(string $raw): ?string
    {
        $digits = preg_replace('/\D/', '', $raw);
        if ($digits === '') {
            return null;
        }
        if (strpos($digits, '0') === 0 && (strlen($digits) === 9 || strlen($digits) === 10)) {
            $digits = '972' . substr($digits, 1);
        }
        if (preg_match('/^9725\d{8}$/', $digits)) {
            return $digits; // נייד
        }
        if (preg_match('/^972[23489]\d{7}$/', $digits)) {
            return $digits; // קווי
        }
        return null;
    }
}
