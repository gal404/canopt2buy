<?php
defined('ABSPATH') || exit;

/**
 * רשימת הזמנות סיטונאיות — נטען מ-CO2B_Wholesale_Admin::render_page()
 *
 * @var WC_Order[] $orders הזמנות העמוד הנוכחי
 * @var int        $total  סה"כ הזמנות
 * @var int        $pages  מספר עמודים
 * @var int        $paged
 * @var string     $msg
 */

$statuses = CO2B_Wholesale::statuses();

$notices = [
    'status'   => ['success', '✅ הסטטוס עודכן.'],
    'note'     => ['success', '✅ ההערה נוספה.'],
    'deleted'  => ['success', '🗑️ ההזמנה הועברה לאשפה.'],
    'notfound' => ['error', '❌ ההזמנה לא נמצאה.'],
];
?>
<div class="wrap co2b-ws-admin" dir="rtl">

    <?php if (isset($notices[$msg])) : ?>
        <div class="notice notice-<?php echo esc_attr($notices[$msg][0]); ?> is-dismissible"><p><?php echo esc_html($notices[$msg][1]); ?></p></div>
    <?php endif; ?>

    <div class="co2b-wsa-header">
        <div class="co2b-wsa-header-titles">
            <h1>הזמנות סיטונאיות</h1>
            <p>בקשות הזמנה שהתקבלו מהחנות — ללא חיוב, לטיפול ידני.</p>
        </div>
        <span class="co2b-wsa-total"><?php echo (int) $total; ?> הזמנות</span>
    </div>

    <?php if (empty($orders)) : ?>
        <div class="co2b-wsa-empty">אין עדיין הזמנות סיטונאיות.</div>
    <?php else : ?>
        <table class="wp-list-table widefat striped co2b-wsa-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>תאריך</th>
                    <th>לקוח</th>
                    <th>טלפון</th>
                    <th>סה"כ (כולל מע"מ)</th>
                    <th>סטטוס</th>
                    <th>פעולות</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $o) :
                    $unread   = $o->get_meta(CO2B_Wholesale::META_UNREAD) === 'yes';
                    $view_url = admin_url('admin.php?page=' . CO2B_Wholesale_Admin::MENU_SLUG . '&action=view&id=' . $o->get_id());
                    $st_key   = $o->get_status();
                    $st_label = $statuses[$st_key] ?? wc_get_order_status_name($st_key);
                    ?>
                    <tr class="<?php echo $unread ? 'co2b-wsa-unread' : ''; ?>">
                        <td data-colname="#"><a href="<?php echo esc_url($view_url); ?>"><strong>#<?php echo esc_html($o->get_order_number()); ?></strong></a><?php echo $unread ? ' <span class="co2b-wsa-dot" title="חדש"></span>' : ''; ?></td>
                        <td data-colname="תאריך"><?php echo esc_html($o->get_date_created() ? $o->get_date_created()->date_i18n('d/m/Y H:i') : ''); ?></td>
                        <td data-colname="לקוח"><?php echo esc_html($o->get_formatted_billing_full_name()); ?></td>
                        <td data-colname="טלפון" dir="ltr"><?php echo esc_html($o->get_billing_phone()); ?></td>
                        <td data-colname="סה&quot;כ"><?php echo wp_kses_post(wc_price($o->get_total())); ?></td>
                        <td data-colname="סטטוס"><span class="co2b-wsa-status co2b-wsa-status-<?php echo esc_attr($st_key); ?>"><?php echo esc_html($st_label); ?></span></td>
                        <td data-colname="פעולות" class="co2b-wsa-actions">
                            <a class="button button-small" href="<?php echo esc_url($view_url); ?>">צפייה</a>
                            <form method="post" style="display:inline" onsubmit="return confirm('להעביר את ההזמנה לאשפה?');">
                                <?php wp_nonce_field('co2b_ws_manage_' . $o->get_id()); ?>
                                <input type="hidden" name="co2b_action" value="delete">
                                <input type="hidden" name="order_id" value="<?php echo (int) $o->get_id(); ?>">
                                <button type="submit" class="button button-small button-link-delete">מחיקה</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($pages > 1) : ?>
            <div class="co2b-wsa-pagination tablenav">
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links([
                        'base'      => add_query_arg('paged', '%#%'),
                        'format'    => '',
                        'current'   => $paged,
                        'total'     => $pages,
                        'prev_text' => '‹',
                        'next_text' => '›',
                    ]);
                    ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
