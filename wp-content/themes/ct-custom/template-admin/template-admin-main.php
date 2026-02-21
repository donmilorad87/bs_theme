<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$bs_user_management_enabled = get_theme_mod( 'bs_user_management_enabled', true );
if ( is_string( $bs_user_management_enabled ) ) {
    $normalized = strtolower( $bs_user_management_enabled );
    $bs_user_management_enabled = ! ( '0' === $bs_user_management_enabled || 'off' === $normalized || 'false' === $normalized );
}
$bs_email_enabled = get_option( 'bs_email_enabled', 'on' );
if ( is_string( $bs_email_enabled ) ) {
    $normalized = strtolower( $bs_email_enabled );
    $bs_email_enabled = ! ( '0' === $bs_email_enabled || 'off' === $normalized || 'false' === $normalized );
}
$bs_languages_enabled = get_theme_mod( 'bs_languages_enabled', true );
if ( is_string( $bs_languages_enabled ) ) {
    $normalized = strtolower( $bs_languages_enabled );
    $bs_languages_enabled = ! ( '0' === $bs_languages_enabled || 'off' === $normalized || 'false' === $normalized );
}
?>
<div class="wrap ct-admin-page">

    <div class="ct-admin-header">
        <h1><?php esc_html_e( 'BS Custom Theme Settings', 'ct-custom' ); ?></h1>
    </div>

    <input type="radio" name="bs_admin_nav" id="rd_general" checked class="ct-admin-nav__radio">
    <input type="radio" name="bs_admin_nav" id="rd_export_import" class="ct-admin-nav__radio">
    <input type="radio" name="bs_admin_nav" id="rd_contact" class="ct-admin-nav__radio">
    <input type="radio" name="bs_admin_nav" id="rd_languages" class="ct-admin-nav__radio" <?php disabled( ! $bs_languages_enabled ); ?>>
    <input type="radio" name="bs_admin_nav" id="rd_email" class="ct-admin-nav__radio" <?php disabled( ! $bs_email_enabled ); ?>>
    <?php if ( $bs_user_management_enabled ) : ?>
        <input type="radio" name="bs_admin_nav" id="rd_jwt" class="ct-admin-nav__radio">
    <?php endif; ?>
    <input type="radio" name="bs_admin_nav" id="rd_seo" class="ct-admin-nav__radio">

    <div class="ct-admin-layout">
        <nav class="ct-admin-nav" aria-label="<?php esc_attr_e( 'Settings sections', 'ct-custom' ); ?>">
            <label for="rd_general" class="ct-admin-nav__item"><?php esc_html_e( 'General', 'ct-custom' ); ?></label>
            <label for="rd_export_import" class="ct-admin-nav__item"><?php esc_html_e( 'Export / Import', 'ct-custom' ); ?></label>
            <label for="rd_contact" class="ct-admin-nav__item">
                <?php esc_html_e( 'Contact', 'ct-custom' ); ?>
                <span class="ct-admin-badge" id="bs_unread_badge" style="display:none;"></span>
            </label>
            <label for="rd_languages" class="ct-admin-nav__item" <?php echo ! $bs_languages_enabled ? 'style="display:none;"' : ''; ?>><?php esc_html_e( 'Languages', 'ct-custom' ); ?></label>
            <label for="rd_email" class="ct-admin-nav__item" <?php echo ! $bs_email_enabled ? 'style="display:none;"' : ''; ?>><?php esc_html_e( 'Email', 'ct-custom' ); ?></label>
            <?php if ( $bs_user_management_enabled ) : ?>
                <label for="rd_jwt" class="ct-admin-nav__item"><?php esc_html_e( 'JWT Auth', 'ct-custom' ); ?></label>
            <?php endif; ?>
            <label for="rd_seo" class="ct-admin-nav__item"><?php esc_html_e( 'SEO', 'ct-custom' ); ?></label>
        </nav>

        <div class="ct-admin-panels">
            <div class="ct-admin-panel ct-admin-panel--general">
                <?php include_once get_template_directory() . '/template-admin/partials/general-settings.php'; ?>
            </div>
            <div class="ct-admin-panel ct-admin-panel--export-import">
                <?php include_once get_template_directory() . '/template-admin/partials/export-import.php'; ?>
            </div>
            <div class="ct-admin-panel ct-admin-panel--contact">
                <?php include_once get_template_directory() . '/template-admin/partials/contact-messaging.php'; ?>
            </div>
            <div class="ct-admin-panel ct-admin-panel--languages" <?php echo ! $bs_languages_enabled ? 'style="display:none;"' : ''; ?>>
                <?php include_once get_template_directory() . '/template-admin/partials/languages.php'; ?>
            </div>
            <div class="ct-admin-panel ct-admin-panel--email" <?php echo ! $bs_email_enabled ? 'style="display:none;"' : ''; ?>>
                <?php include_once get_template_directory() . '/template-admin/partials/email-settings.php'; ?>
            </div>
            <?php if ( $bs_user_management_enabled ) : ?>
                <div class="ct-admin-panel ct-admin-panel--jwt">
                    <?php include_once get_template_directory() . '/template-admin/partials/jwt-auth-settings.php'; ?>
                </div>
            <?php endif; ?>
            <div class="ct-admin-panel ct-admin-panel--seo">
                <?php include_once get_template_directory() . '/template-admin/partials/seo-settings.php'; ?>
            </div>
        </div>
    </div>

</div>
