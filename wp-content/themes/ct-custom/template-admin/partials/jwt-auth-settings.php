<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$jwt_raw = get_option( 'bs_custom_jwt_auth', '{}' );
$jwt     = json_decode( $jwt_raw, true );

if ( ! is_array( $jwt ) ) {
    $jwt = array();
}

$jwt_secret           = isset( $jwt['secret'] ) ? $jwt['secret'] : '';
$jwt_expiration_hours = isset( $jwt['expiration_hours'] ) ? (int) $jwt['expiration_hours'] : 24;

$bs_user_management_enabled = get_theme_mod( 'bs_user_management_enabled', true );
if ( is_string( $bs_user_management_enabled ) ) {
    $normalized = strtolower( $bs_user_management_enabled );
    $bs_user_management_enabled = ! ( '0' === $bs_user_management_enabled || 'off' === $normalized || 'false' === $normalized );
}

?>
<div class="ct-admin-section">
    <h2><?php esc_html_e( 'JWT Authentication', 'ct-custom' ); ?></h2>
    <form id="jwt_auth_form" class="ct-admin-form" data-user-management-enabled="<?php echo esc_attr( $bs_user_management_enabled ? '1' : '0' ); ?>">
        <?php wp_nonce_field( 'admin_save_jwt_auth_nonce', 'admin_save_jwt_auth_nonce' ); ?>
        <?php if ( ! $bs_user_management_enabled ) : ?>
            <p class="ct-admin-hint"><?php esc_html_e( 'Enable Login / Registration to use JWT auth.', 'ct-custom' ); ?></p>
        <?php endif; ?>

        <div class="ct-admin-field">
            <label for="jwt_secret"><?php esc_html_e( 'JWT Secret', 'ct-custom' ); ?></label>
            <input type="password" name="jwt_secret" id="jwt_secret" class="jwtAuthInputs"
                   value="<?php echo esc_attr( $jwt_secret ); ?>" <?php disabled( ! $bs_user_management_enabled ); ?>/>
            <button type="button" id="generate_jwt_secret_btn" class="button" <?php disabled( ! $bs_user_management_enabled ); ?>><?php esc_html_e( 'Generate Secret', 'ct-custom' ); ?></button>
        </div>

        <div class="ct-admin-field">
            <label for="jwt_expiration_hours"><?php esc_html_e( 'Expiration (hours)', 'ct-custom' ); ?></label>
            <input type="number" name="jwt_expiration_hours" id="jwt_expiration_hours" class="jwtAuthInputs"
                   value="<?php echo esc_attr( $jwt_expiration_hours ); ?>" min="1" max="8760" <?php disabled( ! $bs_user_management_enabled ); ?>/>
        </div>
    </form>
</div>
