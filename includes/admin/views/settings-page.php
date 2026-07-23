<?php
defined('ABSPATH') || exit;

/**
 * עמוד ההגדרות — נטען מ-CO2B_Admin::render_page()
 *
 * @var array $settings ההגדרות המלאות
 * @var bool  $saved    האם בוצעה שמירה בבקשה הנוכחית
 */

$blocked_products_count   = count((array) $settings['product_ids']);
$blocked_categories_count = count((array) $settings['category_ids']);
?>
<div class="wrap co2b-wrap" dir="rtl">

    <?php if ($saved) : ?>
        <div class="notice notice-success is-dismissible"><p>✅ ההגדרות נשמרו בהצלחה.</p></div>
    <?php endif; ?>

    <div class="co2b-header">
        <div class="co2b-header-icon"><?php echo CO2B_Frontend::icon_svg(); // phpcs:ignore ?></div>
        <div class="co2b-header-titles">
            <h1>Cancel Option 2 Buy</h1>
            <p>חסימת רכישה אונליין למוצרים וקטגוריות נבחרים</p>
        </div>
        <span class="co2b-version">v<?php echo esc_html(CO2B_VERSION); ?></span>
    </div>

    <div class="co2b-chips">
        <span class="co2b-chip">🚫 <strong><?php echo (int) $blocked_products_count; ?></strong> מוצרים חסומים</span>
        <span class="co2b-chip">📂 <strong><?php echo (int) $blocked_categories_count; ?></strong> קטגוריות חסומות</span>
    </div>

    <form method="post">
        <?php wp_nonce_field(CO2B_Admin::NONCE_ACTION, CO2B_Admin::NONCE_FIELD); ?>

        <div class="co2b-card">
            <h2>🚫 מוצרים חסומים</h2>
            <p class="co2b-card-desc">מוצרים שנבחרו כאן יוצגו באתר כרגיל — אך ללא אפשרות הוספה לעגלה או רכישה.</p>
            <select name="co2b_product_ids[]" class="wc-product-search co2b-select" multiple
                    data-action="woocommerce_json_search_products"
                    data-placeholder="חפש מוצר לפי שם או מק&quot;ט…">
                <?php foreach ((array) $settings['product_ids'] as $pid) :
                    $co2b_product = wc_get_product($pid);
                    if (!$co2b_product) {
                        continue;
                    } ?>
                    <option value="<?php echo esc_attr($pid); ?>" selected>
                        <?php echo esc_html(wp_strip_all_tags($co2b_product->get_formatted_name())); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="co2b-card">
            <h2>📂 קטגוריות חסומות</h2>
            <p class="co2b-card-desc">כל המוצרים בקטגוריות שנבחרו — כולל תתי-הקטגוריות שלהן — ייחסמו לרכישה.</p>
            <select name="co2b_category_ids[]" class="wc-category-search co2b-select" multiple
                    data-placeholder="חפש קטגוריה…">
                <?php foreach ((array) $settings['category_ids'] as $cid) :
                    $co2b_term = get_term($cid, 'product_cat');
                    if (!$co2b_term || is_wp_error($co2b_term)) {
                        continue;
                    } ?>
                    <option value="<?php echo esc_attr($cid); ?>" selected><?php echo esc_html($co2b_term->name); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="co2b-card">
            <h2>💬 טקסט החיווי</h2>
            <p class="co2b-card-desc">הטקסט שיוצג במקום כפתור ההוספה לעגלה. השאר ריק לחזרה לברירת המחדל.</p>
            <textarea name="co2b_notice_text" id="co2b_notice_text" rows="2"
                      class="large-text"><?php echo esc_textarea($settings['notice_text']); ?></textarea>

            <div class="co2b-preview">
                <span class="co2b-preview-label">תצוגה מקדימה:</span>
                <div class="co2b-notice">
                    <?php echo CO2B_Frontend::icon_svg(); // phpcs:ignore ?>
                    <span class="co2b-notice-text" id="co2b_preview_text"
                          data-default="<?php echo esc_attr(CO2B_Settings::DEFAULT_NOTICE); ?>"><?php echo esc_html(CO2B_Settings::notice_text()); ?></span>
                </div>
            </div>
        </div>

        <div class="co2b-card">
            <h2>🔄 עדכונים אוטומטיים (GitHub)</h2>
            <p class="co2b-card-desc">
                טוקן גישה לריפו פרטי (Fine-grained PAT עם הרשאת Contents: Read).
                אם הריפו ציבורי — אפשר להשאיר ריק.
            </p>
            <input type="password" name="co2b_github_token" class="regular-text co2b-token" dir="ltr"
                   value="<?php echo esc_attr($settings['github_token']); ?>"
                   placeholder="github_pat_…" autocomplete="off">
        </div>

        <div class="co2b-info">
            💡 מוצר נחסם אם הוא מסומן <strong>באחד</strong> מהמקורות: העמוד הזה, צ'קבוקס "לא ניתן לרכישה אונליין"
            בעריכת המוצר, או צ'קבוקס בקטגוריה שלו (כולל קטגוריית-אב).
            מנהלי אתר ומנהלי חנות תמיד רואים את הכפתורים ויכולים לרכוש.
        </div>

        <p class="co2b-actions">
            <button type="submit" name="co2b_save" value="1" class="co2b-save-btn">שמור הגדרות</button>
        </p>
    </form>
</div>
