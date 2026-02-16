<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="ct-admin-section">

    <h3><?php esc_html_e( 'Export Settings', 'ct-custom' ); ?></h3>
    <p class="description"><?php esc_html_e( 'Download all theme customizer settings and admin options as a JSON file.', 'ct-custom' ); ?></p>

    <form id="export_settings_form">
        <?php wp_nonce_field( 'admin_export_settings_nonce', 'admin_export_settings_nonce' ); ?>
        <button type="button" id="export_settings_btn" class="button button-primary">
            <?php esc_html_e( 'Export Settings', 'ct-custom' ); ?>
        </button>
    </form>

    <hr>

    <h3><?php esc_html_e( 'Import Settings', 'ct-custom' ); ?></h3>
    <p class="description"><?php esc_html_e( 'Upload a previously exported JSON file to restore settings. This will overwrite current values.', 'ct-custom' ); ?></p>

    <form id="import_settings_form">
        <?php wp_nonce_field( 'admin_import_settings_nonce', 'admin_import_settings_nonce' ); ?>

        <div class="ct-admin-field-row">
            <input type="file" id="import_settings_file" accept=".json" class="ct-admin-file-input">
        </div>

        <div id="import_file_info" class="ct-admin-file-info" style="display:none;">
            <span id="import_file_name"></span>
        </div>

        <button type="button" id="import_settings_btn" class="button button-primary" disabled>
            <?php esc_html_e( 'Import Settings', 'ct-custom' ); ?>
        </button>
    </form>

</div>
