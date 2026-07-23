<?php
defined('ABSPATH') || exit;

/* ===== אדמין — עמוד הגדרות + צ'קבוקסים במוצר ובקטגוריה ===== */
class CO2B_Admin
{
    const MENU_SLUG    = 'canopt2buy';
    const NONCE_ACTION = 'co2b_save_settings';
    const NONCE_FIELD  = 'co2b_nonce';

    public static function init(): void
    {
        add_action('admin_menu', [__CLASS__, 'register_menu']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets'], 20);

        /* צ'קבוקס במוצר (לשונית "כללי") */
        add_action('woocommerce_product_options_general_product_data', [__CLASS__, 'product_checkbox']);
        add_action('woocommerce_admin_process_product_object', [__CLASS__, 'save_product_checkbox']);

        /* צ'קבוקס בקטגוריה */
        add_action('product_cat_add_form_fields', [__CLASS__, 'category_add_field']);
        add_action('product_cat_edit_form_fields', [__CLASS__, 'category_edit_field']);
        add_action('created_product_cat', [__CLASS__, 'save_category_field']);
        add_action('edited_product_cat', [__CLASS__, 'save_category_field']);
    }

    /* ===== תפריט ועמוד ההגדרות ===== */

    public static function register_menu(): void
    {
        add_submenu_page(
            'woocommerce',
            'חסימת רכישה אונליין',
            'חסימת רכישה',
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
        /* ה-select המשופר של WC נרשם ע"י WC בכל עמודי האדמין — צריך רק enqueue */
        wp_enqueue_script('wc-enhanced-select');
        wp_enqueue_style('woocommerce_admin_styles');

        /* עיצוב הכרטיס מהפרונט — עבור התצוגה המקדימה */
        wp_enqueue_style('co2b-frontend', CO2B_PLUGIN_URL . 'assets/css/frontend.css', [], CO2B_VERSION);
        wp_enqueue_style('co2b-admin', CO2B_PLUGIN_URL . 'assets/css/admin.css', [], CO2B_VERSION);
        wp_enqueue_script('co2b-admin', CO2B_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], CO2B_VERSION, true);
    }

    public static function render_page(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('אין הרשאה');
        }

        $saved = false;
        if (isset($_POST['co2b_save'])) {
            check_admin_referer(self::NONCE_ACTION, self::NONCE_FIELD);
            self::save_settings();
            $saved = true;
        }

        $settings = CO2B_Settings::all();
        require CO2B_PLUGIN_DIR . 'includes/admin/views/settings-page.php';
    }

    private static function save_settings(): void
    {
        $product_ids = array_values(array_unique(array_filter(
            array_map('intval', (array) ($_POST['co2b_product_ids'] ?? []))
        )));
        $category_ids = array_values(array_unique(array_filter(
            array_map('intval', (array) ($_POST['co2b_category_ids'] ?? []))
        )));

        $notice = trim(wp_kses_post(wp_unslash($_POST['co2b_notice_text'] ?? '')));
        if ($notice === '') {
            $notice = CO2B_Settings::DEFAULT_NOTICE;
        }
        $token = sanitize_text_field(wp_unslash($_POST['co2b_github_token'] ?? ''));

        /* ===== שדות סיטונאות ===== */
        $ws_product_ids = array_values(array_unique(array_filter(
            array_map('intval', (array) ($_POST['co2b_ws_product_ids'] ?? []))
        )));
        $ws_category_ids = array_values(array_unique(array_filter(
            array_map('intval', (array) ($_POST['co2b_ws_category_ids'] ?? []))
        )));
        $confirm = trim(wp_kses_post(wp_unslash($_POST['co2b_ws_confirm'] ?? '')));
        if ($confirm === '') {
            $confirm = CO2B_Settings::DEFAULT_CONFIRM;
        }

        CO2B_Settings::update([
            'product_ids'  => $product_ids,
            'category_ids' => $category_ids,
            'notice_text'  => $notice,
            'github_token' => $token,

            'wholesale_enabled'         => !empty($_POST['co2b_ws_enabled']),
            'wholesale_product_ids'     => $ws_product_ids,
            'wholesale_category_ids'    => $ws_category_ids,
            'wholesale_vat'             => max(0, (float) ($_POST['co2b_ws_vat'] ?? 18)),
            'wholesale_min'             => max(0, (float) ($_POST['co2b_ws_min'] ?? 1000)),
            'wholesale_free_ship'       => max(0, (float) ($_POST['co2b_ws_free_ship'] ?? 4500)),
            'wholesale_ship_net'        => max(0, (float) ($_POST['co2b_ws_ship_net'] ?? 195)),
            'wholesale_ship_product_id' => (int) ($_POST['co2b_ws_ship_product_id'] ?? 0),
            'wholesale_shipping_terms'  => sanitize_textarea_field(wp_unslash($_POST['co2b_ws_shipping_terms'] ?? '')),
            'wholesale_unloading_terms' => sanitize_textarea_field(wp_unslash($_POST['co2b_ws_unloading_terms'] ?? '')),
            'wholesale_alert_email'     => sanitize_email(wp_unslash($_POST['co2b_ws_alert_email'] ?? '')),
            'wholesale_confirm_text'    => $confirm,
        ]);
    }

    /* ===== צ'קבוקס במוצר ===== */

    public static function product_checkbox(): void
    {
        woocommerce_wp_checkbox([
            'id'          => CO2B_Blocker::META_BLOCKED,
            'label'       => 'לא ניתן לרכישה אונליין',
            'description' => 'הצג חיווי במקום כפתור הוספה לעגלה (מנהלים עדיין יכולים לרכוש)',
        ]);
    }

    /* WC שומר את האובייקט מיד אחרי ה-hook — אין צורך ב-save() (תואם HPOS/CRUD) */
    public static function save_product_checkbox($product): void
    {
        $product->update_meta_data(
            CO2B_Blocker::META_BLOCKED,
            isset($_POST[CO2B_Blocker::META_BLOCKED]) ? 'yes' : 'no'
        );
    }

    /* ===== צ'קבוקס בקטגוריה ===== */

    public static function category_add_field(): void
    {
        ?>
        <div class="form-field">
            <label>
                <input type="hidden" name="co2b_blocked_present" value="1">
                <input type="checkbox" name="co2b_blocked" value="yes">
                לא ניתן לרכישה אונליין
            </label>
            <p class="description">מוצרים בקטגוריה זו (כולל תתי-קטגוריות) יוצגו עם חיווי במקום כפתור קנייה.</p>
        </div>
        <?php
    }

    public static function category_edit_field($term): void
    {
        $checked = get_term_meta($term->term_id, CO2B_Blocker::TERM_META_BLOCKED, true) === 'yes';
        ?>
        <tr class="form-field">
            <th scope="row"><label for="co2b_blocked">לא ניתן לרכישה אונליין</label></th>
            <td>
                <input type="hidden" name="co2b_blocked_present" value="1">
                <label>
                    <input type="checkbox" id="co2b_blocked" name="co2b_blocked" value="yes" <?php checked($checked); ?>>
                    הצג חיווי במקום כפתור קנייה למוצרי הקטגוריה (כולל תתי-קטגוריות)
                </label>
            </td>
        </tr>
        <?php
    }

    public static function save_category_field($term_id): void
    {
        /* נשמר רק כשהטופס באמת הכיל את השדה — מגן מפני Quick Edit שמאפס */
        if (!isset($_POST['co2b_blocked_present'])) {
            return;
        }
        if (!current_user_can('manage_product_terms')) {
            return;
        }
        update_term_meta(
            $term_id,
            CO2B_Blocker::TERM_META_BLOCKED,
            isset($_POST['co2b_blocked']) ? 'yes' : 'no'
        );
    }
}
