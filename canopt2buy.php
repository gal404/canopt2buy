<?php
/**
 * Plugin Name: Cancel Option 2 Buy
 * Description: חסימת רכישה אונליין למוצרים/קטגוריות נבחרים + מסלול הזמנה סיטונאית שאינו מחייב. המוצר מוצג רגיל אך לא ניתן להוספה לעגלה, למעט מנהלי חנות.
 * Version: 1.2.4
 * Author: Gal Ben Baruch
 * Requires Plugins: woocommerce
 * Requires PHP: 7.4
 * Text Domain: canopt2buy
 */

if (!defined('ABSPATH')) exit;

/* ===== קבועים ===== */
define('CO2B_VERSION', '1.2.4');
define('CO2B_PLUGIN_FILE', __FILE__);
define('CO2B_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CO2B_PLUGIN_URL', plugin_dir_url(__FILE__));

/* ===== תאימות HPOS + Checkout Blocks ===== */
add_action('before_woocommerce_init', function () {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});

/* ===== טעינת קבצים ===== */
require_once CO2B_PLUGIN_DIR . 'includes/class-co2b-settings.php';
require_once CO2B_PLUGIN_DIR . 'includes/class-co2b-blocker.php';
require_once CO2B_PLUGIN_DIR . 'includes/class-co2b-frontend.php';
require_once CO2B_PLUGIN_DIR . 'includes/class-co2b-wholesale.php';
require_once CO2B_PLUGIN_DIR . 'includes/class-co2b-wholesale-frontend.php';
require_once CO2B_PLUGIN_DIR . 'includes/class-co2b-updater.php';
if (is_admin()) {
    require_once CO2B_PLUGIN_DIR . 'includes/admin/class-co2b-admin.php';
    require_once CO2B_PLUGIN_DIR . 'includes/admin/class-co2b-wholesale-admin.php';
}

/* ===== אתחול ===== */
add_action('plugins_loaded', function () {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>התוסף "Cancel Option 2 Buy" דורש את WooCommerce פעיל.</p></div>';
        });
        return;
    }
    CO2B_Blocker::init();
    CO2B_Frontend::init();
    CO2B_Wholesale::init();
    CO2B_Wholesale_Frontend::init();
    CO2B_Updater::init();
    if (is_admin()) {
        CO2B_Admin::init();
        CO2B_Wholesale_Admin::init();

        /* ריפוי עצמי בעדכון גרסה — יוצר עמודים חסרים גם כשלא הייתה הפעלה מחדש */
        add_action('admin_init', function () {
            if (get_option('co2b_version') !== CO2B_VERSION) {
                CO2B_Wholesale::ensure_pages();
                update_option('co2b_version', CO2B_VERSION);
            }
        });
    }
});

/* ===== אקטיבציה — יצירת עמודי הסיטונאות ===== */
register_activation_hook(__FILE__, function () {
    CO2B_Wholesale::ensure_pages();
    update_option('co2b_version', CO2B_VERSION);
});
