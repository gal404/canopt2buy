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

$catalog_url = $settings['wholesale_catalog_page_id'] ? get_permalink($settings['wholesale_catalog_page_id']) : '';
$cart_url    = $settings['wholesale_cart_page_id'] ? get_permalink($settings['wholesale_cart_page_id']) : '';
?>
<div class="wrap co2b-wrap" dir="rtl">

    <?php if ($saved) : ?>
        <div class="notice notice-success is-dismissible"><p>✅ ההגדרות נשמרו בהצלחה.</p></div>
    <?php endif; ?>

    <div class="co2b-header">
        <div class="co2b-header-icon"><?php echo CO2B_Frontend::icon_svg(); // phpcs:ignore ?></div>
        <div class="co2b-header-titles">
            <h1>Cancel Option 2 Buy</h1>
            <p>חסימת רכישה אונליין + הזמנות סיטונאיות</p>
        </div>
        <span class="co2b-version">v<?php echo esc_html(CO2B_VERSION); ?></span>
    </div>

    <div class="co2b-tabs" id="co2b-tabs">
        <button type="button" class="co2b-tab is-active" data-tab="block">🚫 חסימת רכישה</button>
        <button type="button" class="co2b-tab" data-tab="wholesale">📦 הזמנה סיטונאית</button>
    </div>

    <form method="post">
        <?php wp_nonce_field(CO2B_Admin::NONCE_ACTION, CO2B_Admin::NONCE_FIELD); ?>

        <!-- ===== טאב: חסימת רכישה ===== -->
        <div class="co2b-panel is-active" data-panel="block">

            <div class="co2b-chips">
                <span class="co2b-chip">🚫 <strong><?php echo (int) $blocked_products_count; ?></strong> מוצרים חסומים</span>
                <span class="co2b-chip">📂 <strong><?php echo (int) $blocked_categories_count; ?></strong> קטגוריות חסומות</span>
            </div>

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
        </div>

        <!-- ===== טאב: הזמנה סיטונאית ===== -->
        <div class="co2b-panel" data-panel="wholesale">

            <div class="co2b-card">
                <label class="co2b-switch">
                    <input type="checkbox" name="co2b_ws_enabled" value="1" <?php checked(!empty($settings['wholesale_enabled'])); ?>>
                    <span>הפעל מסלול הזמנה סיטונאית</span>
                </label>
                <p class="co2b-card-desc">מאפשר ללקוחות לאסוף מוצרים זכאים לתוך הזמנה סיטונאית שאינה מחייבת (רשימת בקשה).</p>
            </div>

            <div class="co2b-card">
                <h2>🧴 מוצרים סיטונאיים נוספים</h2>
                <p class="co2b-card-desc">כל המוצרים החסומים זכאים אוטומטית. כאן ניתן להוסיף מוצרים סיטונאיים <strong>נוספים</strong> שאינם חסומים.</p>
                <select name="co2b_ws_product_ids[]" class="wc-product-search co2b-select" multiple
                        data-action="woocommerce_json_search_products"
                        data-placeholder="חפש מוצר…">
                    <?php foreach ((array) $settings['wholesale_product_ids'] as $pid) :
                        $wp = wc_get_product($pid);
                        if (!$wp) {
                            continue;
                        } ?>
                        <option value="<?php echo esc_attr($pid); ?>" selected><?php echo esc_html(wp_strip_all_tags($wp->get_formatted_name())); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="co2b-card">
                <h2>📂 קטגוריות סיטונאיות נוספות</h2>
                <select name="co2b_ws_category_ids[]" class="wc-category-search co2b-select" multiple
                        data-placeholder="חפש קטגוריה…">
                    <?php foreach ((array) $settings['wholesale_category_ids'] as $cid) :
                        $wt = get_term($cid, 'product_cat');
                        if (!$wt || is_wp_error($wt)) {
                            continue;
                        } ?>
                        <option value="<?php echo esc_attr($cid); ?>" selected><?php echo esc_html($wt->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="co2b-card">
                <h2>💵 מע"מ, מינימום וספים</h2>
                <p class="co2b-card-desc">כל המחירים כוללים מע"מ. הספים נמדדים על סכום הסחורה כולל מע"מ, לפני הובלה.</p>
                <div class="co2b-fields">
                    <label class="co2b-f">אחוז מע"מ
                        <input type="number" name="co2b_ws_vat" step="0.01" min="0" value="<?php echo esc_attr($settings['wholesale_vat']); ?>">
                    </label>
                    <label class="co2b-f">מינימום להזמנה (₪, כולל מע"מ)
                        <input type="number" name="co2b_ws_min" step="1" min="0" value="<?php echo esc_attr($settings['wholesale_min']); ?>">
                    </label>
                    <label class="co2b-f">סף הובלה חינם (₪, כולל מע"מ)
                        <input type="number" name="co2b_ws_free_ship" step="1" min="0" value="<?php echo esc_attr($settings['wholesale_free_ship']); ?>">
                    </label>
                </div>
            </div>

            <div class="co2b-card">
                <h2>🚚 עלות הובלה</h2>
                <p class="co2b-card-desc">אם נבחר מוצר הובלה — מחירו (כולל מע"מ) הוא עלות ההובלה ומתווסף כשורה בהזמנה. אחרת ישמש הסכום הנטו + מע"מ.</p>
                <div class="co2b-fields">
                    <label class="co2b-f">סכום הובלה נטו (₪, ללא מע"מ)
                        <input type="number" name="co2b_ws_ship_net" step="0.01" min="0" value="<?php echo esc_attr($settings['wholesale_ship_net']); ?>">
                    </label>
                    <label class="co2b-f co2b-f-wide">מוצר הובלה מקושר (אופציונלי)
                        <select name="co2b_ws_ship_product_id" class="wc-product-search" style="width:100%"
                                data-action="woocommerce_json_search_products" data-allow_clear="true"
                                data-placeholder="בחר מוצר הובלה…">
                            <?php $sp = $settings['wholesale_ship_product_id'] ? wc_get_product($settings['wholesale_ship_product_id']) : null;
                            if ($sp) : ?>
                                <option value="<?php echo esc_attr($sp->get_id()); ?>" selected><?php echo esc_html(wp_strip_all_tags($sp->get_formatted_name())); ?></option>
                            <?php endif; ?>
                        </select>
                    </label>
                </div>
            </div>

            <div class="co2b-card">
                <h2>📋 נהלים לאישור הלקוח</h2>
                <p class="co2b-card-desc">יוצגו בטופס ההזמנה; הלקוח חייב לאשר את שניהם לפני שליחה.</p>
                <label class="co2b-f co2b-f-block">נהלי הובלה
                    <textarea name="co2b_ws_shipping_terms" rows="3" class="large-text"><?php echo esc_textarea($settings['wholesale_shipping_terms']); ?></textarea>
                </label>
                <label class="co2b-f co2b-f-block">נהלי פריקה
                    <textarea name="co2b_ws_unloading_terms" rows="3" class="large-text"><?php echo esc_textarea($settings['wholesale_unloading_terms']); ?></textarea>
                </label>
            </div>

            <div class="co2b-card">
                <h2>✉️ התראות ואישור</h2>
                <div class="co2b-fields">
                    <label class="co2b-f co2b-f-wide">מייל להתראה על הזמנה חדשה
                        <input type="email" name="co2b_ws_alert_email" dir="ltr" class="regular-text"
                               value="<?php echo esc_attr($settings['wholesale_alert_email']); ?>"
                               placeholder="<?php echo esc_attr(get_option('admin_email')); ?>">
                    </label>
                </div>
                <label class="co2b-f co2b-f-block">טקסט אישור ללקוח (לאחר שליחה)
                    <input type="text" name="co2b_ws_confirm" class="large-text"
                           value="<?php echo esc_attr($settings['wholesale_confirm_text']); ?>">
                </label>
            </div>

            <div class="co2b-info">
                💡 עמודי הסיטונאות (נוצרו אוטומטית):
                <?php if ($catalog_url) : ?><a href="<?php echo esc_url($catalog_url); ?>" target="_blank">קטלוג סיטונאי</a><?php endif; ?>
                <?php if ($cart_url) : ?> · <a href="<?php echo esc_url($cart_url); ?>" target="_blank">עגלת סיטונאות</a><?php endif; ?>
                . בנוסף, בקרת "הוסף להזמנה סיטונאית" מופיעה אוטומטית בעמודי המוצר וברשימות עבור מוצרים זכאים.
            </div>
        </div>

        <p class="co2b-actions">
            <button type="submit" name="co2b_save" value="1" class="co2b-save-btn">שמור הגדרות</button>
        </p>
    </form>
</div>
