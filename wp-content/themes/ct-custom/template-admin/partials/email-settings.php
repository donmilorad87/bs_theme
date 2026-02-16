<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$email_config_raw = get_option( 'ct_custom_email_config', '{}' );
$email_config     = json_decode( $email_config_raw, true );

if ( ! is_array( $email_config ) ) {
    $email_config = array();
}

$ec_host       = isset( $email_config['host'] ) ? $email_config['host'] : '';
$ec_port       = isset( $email_config['port'] ) ? (int) $email_config['port'] : 587;
$ec_username   = isset( $email_config['username'] ) ? $email_config['username'] : '';
$ec_encryption = isset( $email_config['encryption'] ) ? $email_config['encryption'] : 'tls';
$ec_from_email = isset( $email_config['from_email'] ) ? $email_config['from_email'] : '';
$ec_from_name  = isset( $email_config['from_name'] ) ? $email_config['from_name'] : '';
?>
<div class="ct-admin-section">
    <h2><?php esc_html_e( 'SMTP Configuration', 'ct-custom' ); ?></h2>
    <form id="email_config_form" class="ct-admin-form">
        <?php wp_nonce_field( 'admin_save_email_config_nonce', 'admin_save_email_config_nonce' ); ?>

        <div class="ct-admin-field">
            <label for="ec_host"><?php esc_html_e( 'SMTP Host', 'ct-custom' ); ?></label>
            <input type="text" name="ec_host" id="ec_host" class="emailConfigInputs"
                   value="<?php echo esc_attr( $ec_host ); ?>" placeholder="smtp.example.com" />
        </div>

        <div class="ct-admin-field">
            <label for="ec_port"><?php esc_html_e( 'SMTP Port', 'ct-custom' ); ?></label>
            <input type="number" name="ec_port" id="ec_port" class="emailConfigInputs"
                   value="<?php echo esc_attr( $ec_port ); ?>" min="1" max="65535" />
        </div>

        <div class="ct-admin-field">
            <label for="ec_username"><?php esc_html_e( 'SMTP Username', 'ct-custom' ); ?></label>
            <input type="text" name="ec_username" id="ec_username" class="emailConfigInputs"
                   value="<?php echo esc_attr( $ec_username ); ?>" />
        </div>

        <div class="ct-admin-field">
            <label for="ec_password"><?php esc_html_e( 'SMTP Password', 'ct-custom' ); ?></label>
            <input type="password" name="ec_password" id="ec_password" class="emailConfigInputs"
                   value="" placeholder="<?php esc_attr_e( 'Enter password', 'ct-custom' ); ?>" />
            <?php if ( ! empty( $email_config['password'] ) ) : ?>
                <p class="description"><?php esc_html_e( 'Password is saved. Leave empty to keep current.', 'ct-custom' ); ?></p>
            <?php endif; ?>
        </div>

        <div class="ct-admin-field">
            <label for="ec_encryption"><?php esc_html_e( 'Encryption', 'ct-custom' ); ?></label>
            <select name="ec_encryption" id="ec_encryption" class="emailConfigInputs">
                <option value="tls" <?php selected( $ec_encryption, 'tls' ); ?>><?php esc_html_e( 'TLS', 'ct-custom' ); ?></option>
                <option value="ssl" <?php selected( $ec_encryption, 'ssl' ); ?>><?php esc_html_e( 'SSL', 'ct-custom' ); ?></option>
                <option value="none" <?php selected( $ec_encryption, 'none' ); ?>><?php esc_html_e( 'None', 'ct-custom' ); ?></option>
            </select>
        </div>

        <div class="ct-admin-field">
            <label for="ec_from_email"><?php esc_html_e( 'From Email', 'ct-custom' ); ?></label>
            <input type="email" name="ec_from_email" id="ec_from_email" class="emailConfigInputs"
                   value="<?php echo esc_attr( $ec_from_email ); ?>" />
        </div>

        <div class="ct-admin-field">
            <label for="ec_from_name"><?php esc_html_e( 'From Name', 'ct-custom' ); ?></label>
            <input type="text" name="ec_from_name" id="ec_from_name" class="emailConfigInputs"
                   value="<?php echo esc_attr( $ec_from_name ); ?>" />
        </div>
    </form>
</div>
