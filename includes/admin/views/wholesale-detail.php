<?php
defined('ABSPATH') || exit;

/**
 * פירוט הזמנה סיטונאית — נטען מ-CO2B_Wholesale_Admin::render_page()
 *
 * @var WC_Order $order
 */

$back_url = admin_url('admin.php?page=' . CO2B_Wholesale_Admin::MENU_SLUG);
$edit_url = $order->get_edit_order_url();
?>
<div class="wrap co2b-ws-admin" dir="rtl">

    <a class="co2b-wsa-back" href="<?php echo esc_url($back_url); ?>">→ חזרה לרשימה</a>

    <div class="co2b-wsa-header">
        <div class="co2b-wsa-header-titles">
            <h1>הזמנה סיטונאית #<?php echo esc_html($order->get_order_number()); ?></h1>
            <p><?php echo esc_html($order->get_date_created() ? $order->get_date_created()->date_i18n('d/m/Y H:i') : ''); ?></p>
        </div>
        <a class="button" href="<?php echo esc_url($edit_url); ?>">פתח בעורך ההזמנות של WooCommerce</a>
    </div>

    <div class="co2b-wsa-cards">

        <div class="co2b-wsa-card">
            <h2>👤 פרטי הלקוח</h2>
            <table class="co2b-wsa-kv">
                <tr><th>שם מלא</th><td><?php echo esc_html($order->get_formatted_billing_full_name()); ?></td></tr>
                <tr><th>טלפון</th><td dir="ltr"><?php echo esc_html($order->get_billing_phone()); ?></td></tr>
                <tr><th>אימייל</th><td dir="ltr"><?php echo esc_html($order->get_billing_email()); ?></td></tr>
                <tr><th>כתובת לאספקה</th><td><?php echo esc_html(trim($order->get_billing_address_1() . ', ' . $order->get_billing_city(), ', ')); ?></td></tr>
                <tr><th>שם לחשבונית</th><td><?php echo esc_html($order->get_meta(CO2B_Wholesale::META_INVOICE)); ?></td></tr>
                <tr><th>אישור נהלי הובלה</th><td><?php echo $order->get_meta(CO2B_Wholesale::META_ACK_SHIP) === 'yes' ? '✓ אושר' : '—'; ?></td></tr>
                <tr><th>אישור נהלי פריקה</th><td><?php echo $order->get_meta(CO2B_Wholesale::META_ACK_UNLOAD) === 'yes' ? '✓ אושר' : '—'; ?></td></tr>
            </table>
        </div>

        <div class="co2b-wsa-card">
            <h2>💰 סיכום</h2>
            <table class="co2b-wsa-kv">
                <tr><th>נטו</th><td><?php echo wp_kses_post(wc_price((float) $order->get_meta(CO2B_Wholesale::META_NET))); ?></td></tr>
                <tr><th>מע"מ (<?php echo esc_html($order->get_meta(CO2B_Wholesale::META_VAT_RATE)); ?>%)</th><td><?php echo wp_kses_post(wc_price((float) $order->get_meta(CO2B_Wholesale::META_VAT))); ?></td></tr>
                <tr><th>הובלה</th><td><?php echo wp_kses_post(wc_price((float) $order->get_meta(CO2B_Wholesale::META_SHIP))); ?></td></tr>
                <tr class="co2b-wsa-kv-total"><th>סה"כ (כולל מע"מ)</th><td><?php echo wp_kses_post(wc_price($order->get_total())); ?></td></tr>
            </table>
        </div>
    </div>

    <div class="co2b-wsa-card">
        <h2>🛒 פריטים</h2>
        <table class="wp-list-table widefat striped co2b-wsa-table">
            <thead><tr><th>מוצר</th><th>כמות</th><th>סכום</th></tr></thead>
            <tbody>
                <?php foreach ($order->get_items() as $item) : ?>
                    <tr>
                        <td data-colname="מוצר"><?php echo esc_html($item->get_name()); ?></td>
                        <td data-colname="כמות"><?php echo (int) $item->get_quantity(); ?></td>
                        <td data-colname="סכום"><?php echo wp_kses_post(wc_price($item->get_total())); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
