<?php
defined('ABSPATH') || exit;

/**
 * פירוט הזמנה סיטונאית — נטען מ-CO2B_Wholesale_Admin::render_page()
 *
 * @var WC_Order $order
 * @var array    $notes  wc_get_order_notes()
 * @var string   $msg
 */

$back_url  = admin_url('admin.php?page=' . CO2B_Wholesale_Admin::MENU_SLUG);
$edit_url  = $order->get_edit_order_url();
$statuses  = CO2B_Wholesale::statuses();
$cur       = $order->get_status();
$order_id  = $order->get_id();

/* פירוק נטו/מע"מ — משתמש בערכים שנשמרו; להזמנה שאומצה ידנית גוזר מהסה"כ */
$w_net  = (float) $order->get_meta(CO2B_Wholesale::META_NET);
$w_vat  = (float) $order->get_meta(CO2B_Wholesale::META_VAT);
$w_ship = (float) $order->get_meta(CO2B_Wholesale::META_SHIP);
$w_rate = $order->get_meta(CO2B_Wholesale::META_VAT_RATE);
$w_rate = ($w_rate !== '' && $w_rate !== null) ? (float) $w_rate : (float) CO2B_Settings::get('wholesale_vat');
$w_grand = (float) $order->get_total();
if ($w_net <= 0 && $w_grand > 0) {
    $w_net = round($w_grand / (1 + $w_rate / 100), 2);
    $w_vat = round($w_grand - $w_net, 2);
}

$notices = [
    'status' => ['success', '✅ הסטטוס עודכן.'],
    'note'   => ['success', '✅ ההערה נוספה.'],
];
?>
<div class="wrap co2b-ws-admin" dir="rtl">

    <?php if (isset($notices[$msg])) : ?>
        <div class="notice notice-<?php echo esc_attr($notices[$msg][0]); ?> is-dismissible"><p><?php echo esc_html($notices[$msg][1]); ?></p></div>
    <?php endif; ?>

    <a class="co2b-wsa-back" href="<?php echo esc_url($back_url); ?>">→ חזרה לרשימה</a>

    <div class="co2b-wsa-header">
        <div class="co2b-wsa-header-titles">
            <h1>הזמנה סיטונאית #<?php echo esc_html($order->get_order_number()); ?></h1>
            <p><?php echo esc_html($order->get_date_created() ? $order->get_date_created()->date_i18n('d/m/Y H:i') : ''); ?>
                · סטטוס נוכחי: <strong><?php echo esc_html($statuses[$cur] ?? wc_get_order_status_name($cur)); ?></strong></p>
        </div>
        <a class="button" href="<?php echo esc_url($edit_url); ?>">עריכה מלאה בעורך WooCommerce</a>
    </div>

    <div class="co2b-wsa-cards">

        <div class="co2b-wsa-card">
            <h2>🚦 עדכון סטטוס</h2>
            <form method="post" class="co2b-wsa-statusform">
                <?php wp_nonce_field('co2b_ws_manage_' . $order_id); ?>
                <input type="hidden" name="co2b_action" value="status">
                <input type="hidden" name="order_id" value="<?php echo (int) $order_id; ?>">
                <select name="new_status">
                    <?php if (!array_key_exists($cur, $statuses)) : ?>
                        <option value="" selected>— <?php echo esc_html(wc_get_order_status_name($cur)); ?> (בחר שלב) —</option>
                    <?php endif; ?>
                    <?php foreach ($statuses as $key => $label) : ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($cur, $key); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="button button-primary">עדכן סטטוס</button>
            </form>
            <p class="co2b-wsa-hint">שינוי כאן מתעדכן גם בעורך ההזמנות של WooCommerce (ולהיפך).</p>
        </div>

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
                <tr><th>נטו</th><td><?php echo wp_kses_post(wc_price($w_net)); ?></td></tr>
                <tr><th>מע"מ (<?php echo esc_html(rtrim(rtrim(number_format($w_rate, 2), '0'), '.')); ?>%)</th><td><?php echo wp_kses_post(wc_price($w_vat)); ?></td></tr>
                <tr><th>הובלה</th><td><?php echo wp_kses_post(wc_price($w_ship)); ?></td></tr>
                <tr class="co2b-wsa-kv-total"><th>סה"כ (כולל מע"מ)</th><td><?php echo wp_kses_post(wc_price($w_grand)); ?></td></tr>
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

    <div class="co2b-wsa-card">
        <h2>📝 הערות</h2>
        <div class="co2b-wsa-notes">
            <?php if (empty($notes)) : ?>
                <p class="co2b-wsa-hint">אין הערות עדיין.</p>
            <?php else : ?>
                <?php foreach ($notes as $note) : ?>
                    <div class="co2b-wsa-note <?php echo $note->customer_note ? 'is-customer' : ''; ?>">
                        <div class="co2b-wsa-note-body"><?php echo wp_kses_post(wpautop($note->content)); ?></div>
                        <div class="co2b-wsa-note-meta"><?php echo esc_html($note->added_by . ' · ' . $note->date_created->date_i18n('d/m/Y H:i')); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <form method="post" class="co2b-wsa-noteform">
            <?php wp_nonce_field('co2b_ws_manage_' . $order_id); ?>
            <input type="hidden" name="co2b_action" value="note">
            <input type="hidden" name="order_id" value="<?php echo (int) $order_id; ?>">
            <textarea name="note" rows="2" placeholder="הוספת הערה פנימית להזמנה…" required></textarea>
            <button type="submit" class="button button-primary">הוסף הערה</button>
        </form>
    </div>

    <div class="co2b-wsa-card co2b-wsa-danger">
        <h2>🗑️ מחיקה</h2>
        <p class="co2b-wsa-hint">העברת ההזמנה לאשפה (ניתן לשחזר מעורך ההזמנות של WooCommerce).</p>
        <form method="post" onsubmit="return confirm('להעביר את ההזמנה לאשפה?');">
            <?php wp_nonce_field('co2b_ws_manage_' . $order_id); ?>
            <input type="hidden" name="co2b_action" value="delete">
            <input type="hidden" name="order_id" value="<?php echo (int) $order_id; ?>">
            <button type="submit" class="button button-link-delete">העבר לאשפה</button>
        </form>
    </div>
</div>
