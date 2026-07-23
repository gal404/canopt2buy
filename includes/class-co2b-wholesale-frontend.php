<?php
defined('ABSPATH') || exit;

/* ===== פרונט סיטונאות — בקרות, שורטקודים, AJAX, טופס ===== */
class CO2B_Wholesale_Frontend
{
    public static function init(): void
    {
        if (!CO2B_Wholesale::enabled()) {
            return;
        }

        /* בקרות inline */
        add_action('woocommerce_single_product_summary', [__CLASS__, 'inline_single'], 35);
        add_action('woocommerce_after_shop_loop_item', [__CLASS__, 'inline_loop'], 15);

        /* שורטקודים */
        add_shortcode('co2b_wholesale_catalog', [__CLASS__, 'shortcode_catalog']);
        add_shortcode('co2b_wholesale_cart', [__CLASS__, 'shortcode_cart']);

        /* כפתור עגלה צף */
        add_action('wp_footer', [__CLASS__, 'floating_button']);

        /* נכסים */
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);

        /* endpoints (wc_ajax — מאתחל session גם לאורחים) */
        foreach (['add', 'update', 'remove', 'totals', 'submit'] as $ep) {
            add_action('wc_ajax_co2b_ws_' . $ep, [__CLASS__, 'ajax_' . $ep]);
            add_action('wc_ajax_nopriv_co2b_ws_' . $ep, [__CLASS__, 'ajax_' . $ep]);
        }
    }

    /* ===== נכסים ===== */

    public static function enqueue_assets(): void
    {
        wp_enqueue_style('co2b-wholesale', CO2B_PLUGIN_URL . 'assets/css/wholesale.css', [], CO2B_VERSION);
        wp_enqueue_script('co2b-wholesale', CO2B_PLUGIN_URL . 'assets/js/wholesale.js', [], CO2B_VERSION, true);

        wp_localize_script('co2b-wholesale', 'CO2BW', [
            'ajax'  => [
                'add'    => WC_AJAX::get_endpoint('co2b_ws_add'),
                'update' => WC_AJAX::get_endpoint('co2b_ws_update'),
                'remove' => WC_AJAX::get_endpoint('co2b_ws_remove'),
                'totals' => WC_AJAX::get_endpoint('co2b_ws_totals'),
                'submit' => WC_AJAX::get_endpoint('co2b_ws_submit'),
            ],
            'nonce'      => wp_create_nonce(CO2B_Wholesale::NONCE),
            'cartUrl'    => self::cart_url(),
            'count'      => CO2B_Wholesale::count_items(),
            'strings'    => [
                'added'      => 'נוסף להזמנה הסיטונאית',
                'addToOrder' => 'הוסף להזמנה סיטונאית',
                'error'      => 'אירעה שגיאה, נסו שוב.',
                'confirmReq' => 'יש לאשר את נהלי ההובלה והפריקה.',
                'required'   => 'נא למלא את כל השדות המסומנים.',
            ],
        ]);
    }

    /* ===== בקרות inline ===== */

    public static function inline_single(): void
    {
        global $product;
        if (!$product instanceof WC_Product || !CO2B_Wholesale::is_orderable($product)) {
            return;
        }
        echo self::add_control_html($product->get_id(), false); // phpcs:ignore WordPress.Security.EscapeOutput
    }

    public static function inline_loop(): void
    {
        global $product;
        if (!$product instanceof WC_Product || !CO2B_Wholesale::is_orderable($product)) {
            return;
        }
        echo self::add_control_html($product->get_id(), true); // phpcs:ignore WordPress.Security.EscapeOutput
    }

    /* בקרת "הוסף להזמנה סיטונאית" — גרסה מלאה (עמוד מוצר) או קומפקטית (רשימה) */
    private static function add_control_html(int $pid, bool $compact): string
    {
        if ($compact) {
            return '<button type="button" class="co2b-ws-add-loop" data-co2b-add data-pid="' . esc_attr($pid) . '">'
                . self::cart_icon() . '<span>הוסף להזמנה סיטונאית</span></button>';
        }
        return '<div class="co2b-ws-control">'
            . '<div class="co2b-ws-qty" data-co2b-qty>'
            . '<button type="button" class="co2b-ws-step" data-co2b-dec aria-label="הפחת">−</button>'
            . '<input type="number" class="co2b-ws-qty-input" value="1" min="1" step="1" inputmode="numeric" aria-label="כמות">'
            . '<button type="button" class="co2b-ws-step" data-co2b-inc aria-label="הוסף">+</button>'
            . '</div>'
            . '<button type="button" class="co2b-ws-add" data-co2b-add data-pid="' . esc_attr($pid) . '">'
            . self::cart_icon() . '<span>הוסף להזמנה סיטונאית</span></button>'
            . '</div>';
    }

    /* ===== שורטקוד קטלוג ===== */

    public static function shortcode_catalog($atts = []): string
    {
        $ids = self::eligible_product_ids();
        if (!$ids) {
            return '<div class="co2b-ws-empty">אין כרגע מוצרים זמינים להזמנה סיטונאית.</div>';
        }

        $out = '<div class="co2b-ws-catalog">';
        foreach ($ids as $pid) {
            $p = wc_get_product($pid);
            if (!$p || !$p->is_visible()) {
                continue;
            }
            $img = $p->get_image_id() ? wp_get_attachment_image_url($p->get_image_id(), 'woocommerce_thumbnail') : wc_placeholder_img_src('woocommerce_thumbnail');
            $out .= '<div class="co2b-ws-card">'
                . '<a class="co2b-ws-card-media" href="' . esc_url(get_permalink($pid)) . '"><img src="' . esc_url($img) . '" alt="' . esc_attr($p->get_name()) . '" loading="lazy"></a>'
                . '<div class="co2b-ws-card-body">'
                . '<a class="co2b-ws-card-title" href="' . esc_url(get_permalink($pid)) . '">' . esc_html($p->get_name()) . '</a>'
                . '<div class="co2b-ws-card-price">' . wp_kses_post($p->get_price_html()) . '</div>'
                . self::add_control_html($pid, false)
                . '</div></div>';
        }
        $out .= '</div>';
        $out .= '<div class="co2b-ws-catalog-foot"><a class="co2b-ws-gocart" href="' . esc_url(self::cart_url()) . '">מעבר לסיכום ההזמנה</a></div>';
        return $out;
    }

    /* ===== שורטקוד עגלה + טופס ===== */

    public static function shortcode_cart($atts = []): string
    {
        $totals = CO2B_Wholesale::compute_totals();

        $out = '<div class="co2b-ws-cartwrap" id="co2b-ws-cartwrap">';
        $out .= '<div class="co2b-ws-cart" id="co2b-ws-cart">' . self::cart_lines_html($totals) . '</div>';
        $out .= '<div class="co2b-ws-summary" id="co2b-ws-summary">' . self::summary_html($totals) . '</div>';
        $out .= self::form_html();
        $out .= '</div>';
        return $out;
    }

    /* שורות העגלה */
    public static function cart_lines_html(array $totals): string
    {
        if (empty($totals['lines'])) {
            return '<div class="co2b-ws-empty">ההזמנה הסיטונאית ריקה. הוסיפו מוצרים מהקטלוג.</div>';
        }
        $rows = '';
        foreach ($totals['lines'] as $ln) {
            $rows .= '<div class="co2b-ws-row" data-pid="' . esc_attr($ln['id']) . '">'
                . '<a class="co2b-ws-row-media" href="' . esc_url($ln['permalink']) . '"><img src="' . esc_url($ln['image']) . '" alt="' . esc_attr($ln['name']) . '"></a>'
                . '<div class="co2b-ws-row-main">'
                . '<a class="co2b-ws-row-title" href="' . esc_url($ln['permalink']) . '">' . esc_html($ln['name']) . '</a>'
                . '<div class="co2b-ws-row-unit">' . wp_kses_post(wc_price($ln['unit'])) . ' ליחידה</div>'
                . '</div>'
                . '<div class="co2b-ws-qty co2b-ws-qty-sm" data-co2b-qty>'
                . '<button type="button" class="co2b-ws-step" data-co2b-dec aria-label="הפחת">−</button>'
                . '<input type="number" class="co2b-ws-qty-input co2b-ws-cart-qty" value="' . esc_attr($ln['qty']) . '" min="1" step="1" inputmode="numeric" aria-label="כמות">'
                . '<button type="button" class="co2b-ws-step" data-co2b-inc aria-label="הוסף">+</button>'
                . '</div>'
                . '<div class="co2b-ws-row-line">' . wp_kses_post(wc_price($ln['line'])) . '</div>'
                . '<button type="button" class="co2b-ws-remove" data-co2b-remove aria-label="הסר">×</button>'
                . '</div>';
        }
        return $rows;
    }

    /* סיכום עלויות + חיווי מינימום/הובלה */
    public static function summary_html(array $totals): string
    {
        $vat_rate = (float) $totals['vat_rate'];
        $ship = $totals['ship_applied']
            ? wp_kses_post(wc_price($totals['ship_gross']))
            : '<span class="co2b-ws-free">חינם</span>';

        $rows = '<div class="co2b-ws-sumrow"><span>סחורה (כולל מע"מ)</span><strong>' . wp_kses_post(wc_price($totals['goods'])) . '</strong></div>';
        $rows .= '<div class="co2b-ws-sumrow"><span>הובלה סיטונאית</span><strong>' . $ship . '</strong></div>';
        $rows .= '<div class="co2b-ws-sumrow co2b-ws-sumrow-muted"><span>מזה מע"מ (' . esc_html(rtrim(rtrim(number_format($vat_rate, 2), '0'), '.')) . '%)</span><span>' . wp_kses_post(wc_price($totals['vat'])) . '</span></div>';
        $rows .= '<div class="co2b-ws-sumrow co2b-ws-sumtotal"><span>סה"כ לתשלום</span><strong>' . wp_kses_post(wc_price($totals['grand'])) . '</strong></div>';

        $note = '';
        if (!empty($totals['lines']) && !$totals['meets_min']) {
            $note = '<div class="co2b-ws-min-note">מינימום להזמנה סיטונאית: ' . wp_kses_post(wc_price($totals['min']))
                . '. חסרים ' . wp_kses_post(wc_price(max(0, $totals['min'] - $totals['goods']))) . '.</div>';
        } elseif ($totals['ship_applied'] && $totals['goods'] > 0) {
            $note = '<div class="co2b-ws-ship-note">משלוח חינם בהזמנה מעל ' . wp_kses_post(wc_price($totals['free_threshold'])) . '.</div>';
        }

        return '<div class="co2b-ws-sumbox" data-meets-min="' . ($totals['meets_min'] ? '1' : '0') . '" data-empty="' . (empty($totals['lines']) ? '1' : '0') . '">'
            . $rows . $note . '</div>';
    }

    /* טופס פרטי לקוח + אישורי נהלים */
    private static function form_html(): string
    {
        $ship_terms   = trim((string) CO2B_Settings::get('wholesale_shipping_terms'));
        $unload_terms = trim((string) CO2B_Settings::get('wholesale_unloading_terms'));

        $terms_block = '';
        if ($ship_terms !== '' || $unload_terms !== '') {
            $terms_block = '<div class="co2b-ws-terms">';
            if ($ship_terms !== '') {
                $terms_block .= '<details class="co2b-ws-acc"><summary>נהלי הובלה</summary><div class="co2b-ws-acc-body">' . wp_kses_post(wpautop($ship_terms)) . '</div></details>';
            }
            if ($unload_terms !== '') {
                $terms_block .= '<details class="co2b-ws-acc"><summary>נהלי פריקה</summary><div class="co2b-ws-acc-body">' . wp_kses_post(wpautop($unload_terms)) . '</div></details>';
            }
            $terms_block .= '</div>';
        }

        return '<form class="co2b-ws-form" id="co2b-ws-form">'
            . '<h3 class="co2b-ws-form-title">פרטי ההזמנה</h3>'
            . '<div class="co2b-ws-grid">'
            . self::field('full_name', 'שם מלא', 'text', true)
            . self::field('phone', 'טלפון', 'tel', true)
            . self::field('email', 'אימייל', 'email', true)
            . self::field('invoice_name', 'שם לחשבונית', 'text', true)
            . self::field('address', 'כתובת מדויקת לאספקה', 'text', true, 'co2b-ws-col-2')
            . self::field('city', 'עיר', 'text', true)
            . '</div>'
            . $terms_block
            . '<label class="co2b-ws-check"><input type="checkbox" id="co2b-ack-shipping"> קראתי ואני מאשר/ת את נהלי ההובלה</label>'
            . '<label class="co2b-ws-check"><input type="checkbox" id="co2b-ack-unloading"> קראתי ואני מאשר/ת את נהלי הפריקה</label>'
            . '<div class="co2b-ws-form-error" id="co2b-ws-form-error" hidden></div>'
            . '<button type="submit" class="co2b-ws-submit" id="co2b-ws-submit">שליחת ההזמנה הסיטונאית</button>'
            . '</form>';
    }

    private static function field(string $id, string $label, string $type, bool $required, string $extra_class = ''): string
    {
        $req = $required ? ' <span class="co2b-ws-req">*</span>' : '';
        $attr = $required ? ' required' : '';
        return '<div class="co2b-ws-field ' . esc_attr($extra_class) . '">'
            . '<label for="co2b-f-' . esc_attr($id) . '">' . esc_html($label) . $req . '</label>'
            . '<input type="' . esc_attr($type) . '" id="co2b-f-' . esc_attr($id) . '" name="' . esc_attr($id) . '"' . $attr . '>'
            . '</div>';
    }

    /* ===== כפתור עגלה צף ===== */

    public static function floating_button(): void
    {
        if (is_admin()) {
            return;
        }
        $count = CO2B_Wholesale::count_items();
        printf(
            '<a class="co2b-ws-fab%s" href="%s" id="co2b-ws-fab" aria-label="הזמנה סיטונאית">%s<span class="co2b-ws-fab-badge" id="co2b-ws-fab-badge">%d</span></a>',
            $count > 0 ? ' is-visible' : '',
            esc_url(self::cart_url()),
            self::cart_icon(),
            (int) $count
        );
    }

    /* ===== AJAX ===== */

    public static function ajax_add(): void
    {
        self::verify();
        $pid = isset($_POST['pid']) ? (int) $_POST['pid'] : 0;
        $qty = isset($_POST['qty']) ? (int) $_POST['qty'] : 1;
        if (!$pid || !CO2B_Wholesale::add_item($pid, $qty)) {
            wp_send_json_error(['message' => 'לא ניתן להוסיף מוצר זה.']);
        }
        wp_send_json_success(['count' => CO2B_Wholesale::count_items()]);
    }

    public static function ajax_update(): void
    {
        self::verify();
        $pid = isset($_POST['pid']) ? (int) $_POST['pid'] : 0;
        $qty = isset($_POST['qty']) ? (int) $_POST['qty'] : 0;
        CO2B_Wholesale::set_item($pid, $qty);
        self::send_cart_fragment();
    }

    public static function ajax_remove(): void
    {
        self::verify();
        $pid = isset($_POST['pid']) ? (int) $_POST['pid'] : 0;
        CO2B_Wholesale::remove_item($pid);
        self::send_cart_fragment();
    }

    public static function ajax_totals(): void
    {
        self::verify();
        self::send_cart_fragment();
    }

    public static function ajax_submit(): void
    {
        self::verify();

        if (self::submit_throttled()) {
            wp_send_json_error(['message' => 'נשלחו יותר מדי בקשות. נסו שוב עוד רגע.'], 429);
        }

        $customer = self::sanitize_customer($_POST);
        if (!empty($customer['_errors'])) {
            wp_send_json_error(['message' => $customer['_errors']]);
        }

        $result = CO2B_Wholesale::create_order($customer);
        if (empty($result['success'])) {
            wp_send_json_error(['message' => $result['message']]);
        }
        wp_send_json_success([
            'message' => $result['message'],
            'number'  => $result['number'],
        ]);
    }

    /* ===== עזרי AJAX ===== */

    private static function verify(): void
    {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, CO2B_Wholesale::NONCE)) {
            wp_send_json_error(['message' => 'בקשה לא חוקית.'], 403);
        }
    }

    /* מניעת הצפה: קירור 20 שניות + מקסימום 10 שליחות לשעה לכל IP+session */
    private static function submit_throttled(): bool
    {
        $ip   = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'x';
        $sid  = WC()->session ? WC()->session->get_customer_id() : '';
        $base = md5($ip . '|' . $sid);

        if (get_transient('co2b_ws_cool_' . $base)) {
            return true;
        }
        set_transient('co2b_ws_cool_' . $base, 1, 20);

        $ckey = 'co2b_ws_cnt_' . $base;
        $cnt  = (int) get_transient($ckey);
        if ($cnt >= 10) {
            return true;
        }
        set_transient($ckey, $cnt + 1, HOUR_IN_SECONDS);
        return false;
    }

    private static function send_cart_fragment(): void
    {
        $totals = CO2B_Wholesale::compute_totals();
        wp_send_json_success([
            'count'       => $totals['count'],
            'cart_html'   => self::cart_lines_html($totals),
            'totals_html' => self::summary_html($totals),
            'meets_min'   => $totals['meets_min'],
        ]);
    }

    private static function sanitize_customer(array $src): array
    {
        $full  = sanitize_text_field(wp_unslash($src['full_name'] ?? ''));
        $email = sanitize_email(wp_unslash($src['email'] ?? ''));
        $phone = CO2B_Wholesale::normalize_phone((string) wp_unslash($src['phone'] ?? ''));
        $addr  = sanitize_text_field(wp_unslash($src['address'] ?? ''));
        $city  = sanitize_text_field(wp_unslash($src['city'] ?? ''));
        $inv   = sanitize_text_field(wp_unslash($src['invoice_name'] ?? ''));
        $ack_s = !empty($src['ack_shipping']);
        $ack_u = !empty($src['ack_unloading']);

        $errors = '';
        if ($full === '' || $addr === '' || $city === '' || $inv === '') {
            $errors = 'נא למלא את כל השדות המסומנים.';
        } elseif (!$email || !is_email($email)) {
            $errors = 'כתובת אימייל אינה תקינה.';
        } elseif (!$phone) {
            $errors = 'מספר טלפון אינו תקין.';
        } elseif (!$ack_s || !$ack_u) {
            $errors = 'יש לאשר את נהלי ההובלה והפריקה.';
        }

        /* פיצול שם מלא לשם פרטי/משפחה עבור WC */
        $first = $full;
        $last  = '';
        if (strpos($full, ' ') !== false) {
            $parts = explode(' ', $full, 2);
            $first = $parts[0];
            $last  = $parts[1];
        }

        return [
            'first_name'   => $first,
            'last_name'    => $last,
            'phone'        => $phone ?: '',
            'email'        => $email ?: '',
            'address'      => $addr,
            'city'         => $city,
            'invoice_name' => $inv,
            '_errors'      => $errors,
        ];
    }

    /* ===== עזרים ===== */

    private static function eligible_product_ids(): array
    {
        $s   = CO2B_Settings::all();
        $ids = array_map('intval', array_merge((array) $s['product_ids'], (array) $s['wholesale_product_ids']));

        /* קטגוריות מההגדרות + קטגוריות עם term meta חסום */
        $cat_ids = array_map('intval', array_merge((array) $s['category_ids'], (array) $s['wholesale_category_ids']));
        $meta_terms = get_terms([
            'taxonomy'   => 'product_cat',
            'fields'     => 'ids',
            'hide_empty' => false,
            'meta_query' => [['key' => CO2B_Blocker::TERM_META_BLOCKED, 'value' => 'yes']],
        ]);
        if (!is_wp_error($meta_terms)) {
            $cat_ids = array_merge($cat_ids, array_map('intval', $meta_terms));
        }
        $cat_ids = array_values(array_unique($cat_ids));

        if ($cat_ids) {
            $q = new WP_Query([
                'post_type'      => 'product',
                'post_status'    => 'publish',
                'posts_per_page' => 300,
                'fields'         => 'ids',
                'no_found_rows'  => true,
                'tax_query'      => [[
                    'taxonomy'         => 'product_cat',
                    'field'            => 'term_id',
                    'terms'            => $cat_ids,
                    'include_children' => true,
                ]],
            ]);
            $ids = array_merge($ids, $q->posts);
        }

        /* מוצרים עם צ'קבוקס חסום */
        $mq = new WP_Query([
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => 300,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_query'     => [['key' => CO2B_Blocker::META_BLOCKED, 'value' => 'yes']],
        ]);
        $ids = array_merge($ids, $mq->posts);

        $ids = array_values(array_unique(array_map('intval', $ids)));
        $ids = array_filter($ids, ['CO2B_Wholesale', 'is_orderable']);
        return array_values($ids);
    }

    private static function cart_url(): string
    {
        $pid = (int) CO2B_Settings::get('wholesale_cart_page_id');
        $url = $pid ? get_permalink($pid) : '';
        return $url ?: home_url('/');
    }

    private static function cart_icon(): string
    {
        return '<svg class="co2b-ws-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>';
    }
}
