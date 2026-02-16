<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap ct-admin-page">

    <div class="ct-admin-header">
        <h1><?php esc_html_e( 'BS Custom Theme Settings', 'ct-custom' ); ?></h1>
    </div>

    <input type="radio" name="ct_admin_nav" id="rd_export_import" checked class="ct-admin-nav__radio">
    <input type="radio" name="ct_admin_nav" id="rd_contact" class="ct-admin-nav__radio">
    <input type="radio" name="ct_admin_nav" id="rd_languages" class="ct-admin-nav__radio">
    <input type="radio" name="ct_admin_nav" id="rd_email" class="ct-admin-nav__radio">
    <input type="radio" name="ct_admin_nav" id="rd_jwt" class="ct-admin-nav__radio">

    <div class="ct-admin-layout">
        <nav class="ct-admin-nav" aria-label="<?php esc_attr_e( 'Settings sections', 'ct-custom' ); ?>">
            <label for="rd_export_import" class="ct-admin-nav__item"><?php esc_html_e( 'Export / Import', 'ct-custom' ); ?></label>
            <label for="rd_contact" class="ct-admin-nav__item">
                <?php esc_html_e( 'Contact', 'ct-custom' ); ?>
                <span class="ct-admin-badge" id="ct_unread_badge" style="display:none;"></span>
            </label>
            <label for="rd_languages" class="ct-admin-nav__item"><?php esc_html_e( 'Languages', 'ct-custom' ); ?></label>
            <label for="rd_email" class="ct-admin-nav__item"><?php esc_html_e( 'Email', 'ct-custom' ); ?></label>
            <label for="rd_jwt" class="ct-admin-nav__item"><?php esc_html_e( 'JWT Auth', 'ct-custom' ); ?></label>
        </nav>

        <div class="ct-admin-panels">
            <div class="ct-admin-panel ct-admin-panel--export-import">
                <?php include_once get_template_directory() . '/template-admin/partials/export-import.php'; ?>
            </div>
            <div class="ct-admin-panel ct-admin-panel--contact">
                <?php include_once get_template_directory() . '/template-admin/partials/contact-messaging.php'; ?>
            </div>
            <div class="ct-admin-panel ct-admin-panel--languages">
                <?php include_once get_template_directory() . '/template-admin/partials/languages.php'; ?>
            </div>
            <div class="ct-admin-panel ct-admin-panel--email">
                <?php include_once get_template_directory() . '/template-admin/partials/email-settings.php'; ?>
            </div>
            <div class="ct-admin-panel ct-admin-panel--jwt">
                <?php include_once get_template_directory() . '/template-admin/partials/jwt-auth-settings.php'; ?>
            </div>
        </div>
    </div>

</div>
