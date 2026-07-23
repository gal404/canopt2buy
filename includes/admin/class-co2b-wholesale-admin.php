<?php
defined('ABSPATH') || exit;

/* ===== אדמין סיטונאות — רשימה, פירוט, באדג', באנר, מטא-בוקס ===== */
class CO2B_Wholesale_Admin
{
    const MENU_SLUG = 'co2b-wholesale';

    public static function init(): void
    {
        add_action('admin_menu', [__CLASS__, 'register_menu'], 20);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets'], 20);
        add_action('admin_notices', [__CLASS__, 'orders_screen_banner']);
        add_action('add_meta_boxes', [__CLASS__, 'add_metabox']);
    }

    /* ===== תפריט + באדג' ===== */

    public static function register_menu(): void
    {
        $n     = CO2B_Wholesale::unread_count();
        $badge = $n ? ' <span class="awaiting-mod update-plugins count-' . (int) $n . '"><span class="pending-count">' . (int) $n . '</span></span>' : '';

        add_submenu_page(
            'woocommerce',
            'הזמנות סיטונאיות',
            'הזמנות סיטונאיות' . $badge,
            'manage_woocommerce',
            self::MENU_SLUG,
            [__CLASS__, 'render_page']
        );
    }

    public static function enqueue_assets($hook): void
    {
        if ($hook !== 'woocommerce_page_' . self::MENU_SLUG) {
            return;
        }
        wp_enqueue_style('co2b-wholesale-admin', CO2B_PLUGIN_URL . 'assets/css/wholesale-admin.css', [], CO2B_VERSION);
    }

    /* ===== ניתוב רשימה / פירוט ===== */

    public static function render_page(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('אין הרשאה');
        }

        $action = isset($_GET['action']) ? sanitize_key($_GET['action']) : '';
        $id     = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        if ($action === 'view' && $id) {
            $order = wc_get_order($id);
            if ($order && $order->get_meta(CO2B_Wholesale::META_FLAG) === 'yes') {
                CO2B_Wholesale::mark_read($order);
                require CO2B_PLUGIN_DIR . 'includes/admin/views/wholesale-detail.php';
                return;
            }
            echo '<div class="wrap"><div class="notice notice-error"><p>הזמנה לא נמצאה.</p></div></div>';
            return;
        }

        $paged = max(1, isset($_GET['paged']) ? (int) $_GET['paged'] : 1);
        $per   = 20;
        $query = wc_get_orders([
            'status'   => CO2B_Wholesale::STATUS_FULL,
            'limit'    => $per,
            'page'     => $paged,
            'paginate' => true,
            'orderby'  => 'date',
            'order'    => 'DESC',
        ]);

        require CO2B_PLUGIN_DIR . 'includes/admin/views/wholesale-list.php';
    }

    /* ===== באנר במסך ההזמנות ===== */

    public static function orders_screen_banner(): void
    {
        if (!current_user_can('manage_woocommerce') || !self::is_orders_screen()) {
            return;
        }
        $n = CO2B_Wholesale::unread_count();
        if ($n < 1) {
            return;
        }

        $latest = wc_get_orders([
            'status'     => CO2B_Wholesale::STATUS_FULL,
            'limit'      => 1,
            'orderby'    => 'date',
            'order'      => 'DESC',
            'meta_query' => [['key' => CO2B_Wholesale::META_UNREAD, 'value' => 'yes']],
        ]);
        $detail = '';
        if (!empty($latest)) {
            $o = $latest[0];
            $detail = sprintf(
                ' האחרונה: %s, %s.',
                esc_html($o->get_formatted_billing_full_name()),
                esc_html($o->get_billing_phone())
            );
        }

        printf(
            '<div class="notice notice-info is-dismissible"><p>📦 <strong>%d</strong> הזמנות סיטונאיות חדשות ממתינות.%s <a href="%s">מעבר לניהול ההזמנות הסיטונאיות ←</a></p></div>',
            (int) $n,
            $detail, // כבר עבר escape
            esc_url(admin_url('admin.php?page=' . self::MENU_SLUG))
        );
    }

    private static function is_orders_screen(): bool
    {
        $screen = get_current_screen();
        if (!$screen) {
            return false;
        }
        $ids = ['edit-shop_order', 'shop_order'];
        if (function_exists('wc_get_page_screen_id')) {
            $ids[] = wc_get_page_screen_id('shop-order'); // HPOS: woocommerce_page_wc-orders
        }
        return in_array($screen->id, $ids, true);
    }

    /* ===== מטא-בוקס בעריכת ההזמנה ===== */

    public static function add_metabox(): void
    {
        $screen = function_exists('wc_get_page_screen_id') ? wc_get_page_screen_id('shop-order') : 'shop_order';
        add_meta_box('co2b_wholesale_box', 'פרטי הזמנה סיטונאית', [__CLASS__, 'render_metabox'], $screen, 'side', 'high');
    }

    public static function render_metabox($post_or_order): void
    {
        $order = ($post_or_order instanceof WC_Order) ? $post_or_order : wc_get_order($post_or_order->ID ?? 0);
        if (!$order || $order->get_meta(CO2B_Wholesale::META_FLAG) !== 'yes') {
            echo '<p style="color:#64748b">הזמנה זו אינה סיטונאית.</p>';
            return;
        }
        $rows = [
            'שם לחשבונית'      => $order->get_meta(CO2B_Wholesale::META_INVOICE),
            'אישור נהלי הובלה' => $order->get_meta(CO2B_Wholesale::META_ACK_SHIP) === 'yes' ? '✓ כן' : '—',
            'אישור נהלי פריקה' => $order->get_meta(CO2B_Wholesale::META_ACK_UNLOAD) === 'yes' ? '✓ כן' : '—',
            'נטו'              => wp_strip_all_tags(wc_price((float) $order->get_meta(CO2B_Wholesale::META_NET))),
            'מע"מ (' . esc_html($order->get_meta(CO2B_Wholesale::META_VAT_RATE)) . '%)' => wp_strip_all_tags(wc_price((float) $order->get_meta(CO2B_Wholesale::META_VAT))),
            'הובלה'            => wp_strip_all_tags(wc_price((float) $order->get_meta(CO2B_Wholesale::META_SHIP))),
        ];
        echo '<table class="co2b-mb"><tbody>';
        foreach ($rows as $k => $v) {
            echo '<tr><th>' . esc_html($k) . '</th><td>' . esc_html($v) . '</td></tr>';
        }
        echo '</tbody></table>';
        echo '<style>.co2b-mb{width:100%;border-collapse:collapse}.co2b-mb th{text-align:right;color:#64748b;font-weight:600;padding:5px 0;vertical-align:top}.co2b-mb td{text-align:left;padding:5px 0;font-weight:600}</style>';
    }
}
