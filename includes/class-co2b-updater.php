<?php
defined('ABSPATH') || exit;

/* ===== עדכון אוטומטי מ-GitHub =====
   הריפו עצמאי (שורש הריפו = שורש התוסף) — בניגוד ל-WA-Store-Notify אין צורך
   ב-release assets; zip המקור של GitHub תקין.
   שחרור גרסה: העלאת Version בכותרת canopt2buy.php + push ל-main. */
class CO2B_Updater
{
    const REPO_URL = 'https://github.com/gal404/canopt2buy/';

    public static function init(): void
    {
        $lib = CO2B_PLUGIN_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';
        if (!file_exists($lib)) {
            return;
        }
        require_once $lib;

        $updateChecker = \YahnisElsts\PluginUpdateChecker\v5p7\PucFactory::buildUpdateChecker(
            self::REPO_URL,
            CO2B_PLUGIN_FILE,
            'canopt2buy'
        );
        $updateChecker->setBranch('main');

        /* טוקן נדרש רק לריפו פרטי — בלעדיו העדכון עובד לריפו ציבורי */
        $token = (string) CO2B_Settings::get('github_token');
        if ($token !== '') {
            $updateChecker->setAuthentication($token);
        }
    }
}
