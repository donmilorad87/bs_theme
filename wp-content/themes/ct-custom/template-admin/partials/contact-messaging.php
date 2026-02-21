<?php
/**
 * Admin Partial: Contact Messaging
 *
 * Sub-tabs for Messages and Configuration (pointers).
 *
 * @package BS_Custom
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BSCustom\Cpt\ContactFormCpt;

$forms = ContactFormCpt::get_forms();
if ( ! is_array( $forms ) ) {
	$forms = array();
}

$rest_url = esc_url( rest_url( 'ct-auth/v1' ) );
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
?>
<div class="ct-admin-section ct-contact-admin"
     data-rest-url="<?php echo $rest_url; ?>"
     data-user-management-enabled="<?php echo esc_attr( $bs_user_management_enabled ? '1' : '0' ); ?>"
     data-email-enabled="<?php echo esc_attr( $bs_email_enabled ? '1' : '0' ); ?>">

	<!-- Sub-tab navigation -->
	<div class="ct-contact-tabs">
		<button type="button" class="ct-contact-tabs__btn ct-contact-tabs__btn--active" data-tab="messages">
			<?php esc_html_e( 'Messages', 'ct-custom' ); ?>
		</button>
		<button type="button" class="ct-contact-tabs__btn" data-tab="config">
			<?php esc_html_e( 'Configuration', 'ct-custom' ); ?>
		</button>
	</div>

	<!-- Messages sub-tab -->
	<div class="ct-contact-panel ct-contact-panel--active" id="bs_contact_messages_panel">
		<div class="ct-contact-filters">
			<select id="bs_contact_form_filter" class="ct-admin-input ct-admin-input--select">
				<option value=""><?php esc_html_e( 'All Forms', 'ct-custom' ); ?></option>
				<?php
				$max_opts = 50;
				$opt_count = 0;
				foreach ( $forms as $form ) :
					if ( $opt_count >= $max_opts ) {
						break;
					}
					$opt_count++;
					?>
					<option value="<?php echo esc_attr( (string) $form['id'] ); ?>"><?php echo esc_html( $form['title'] ); ?></option>
				<?php endforeach; ?>
			</select>
			<select id="bs_contact_status_filter" class="ct-admin-input ct-admin-input--select">
				<option value="all"><?php esc_html_e( 'All', 'ct-custom' ); ?></option>
				<option value="unread"><?php esc_html_e( 'Unread', 'ct-custom' ); ?></option>
				<option value="read"><?php esc_html_e( 'Read', 'ct-custom' ); ?></option>
			</select>
		</div>

		<div id="bs_contact_messages_list" class="ct-contact-messages">
			<p class="ct-admin-no-items"><?php esc_html_e( 'Loading messages...', 'ct-custom' ); ?></p>
		</div>

		<div id="bs_contact_pagination" class="ct-contact-pagination"></div>
	</div>

	<!-- Configuration sub-tab -->
	<div class="ct-contact-panel" id="bs_contact_config_panel" style="display:none;">
		<h3><?php esc_html_e( 'Contact Forms', 'ct-custom' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'Build reusable contact forms with custom fields, conditional logic, file uploads, and captcha. Use the shortcode to place a form in the editor.', 'ct-custom' ); ?>
		</p>

		<div class="ct-admin-form-builder" id="bs_contact_form_builder" data-forms="<?php echo esc_attr( wp_json_encode( $forms ) ); ?>"
		     data-email-enabled="<?php echo esc_attr( $bs_email_enabled ? '1' : '0' ); ?>">
			<div class="ct-admin-form-builder__list">
				<button type="button" class="button button-primary" id="bs_contact_form_add"><?php esc_html_e( 'Add New Form', 'ct-custom' ); ?></button>
				<div id="bs_contact_form_list" class="ct-admin-form-builder__items"></div>
			</div>
			<div class="ct-admin-form-builder__editor is-hidden" id="bs_contact_form_editor_wrap">
				<form id="bs_contact_form_editor" class="ct-admin-form">
					<?php wp_nonce_field( 'admin_save_contact_form_nonce', 'admin_save_contact_form_nonce' ); ?>
					<input type="hidden" name="form_id" id="bs_contact_form_id" value="">

					<div class="ct-admin-field">
						<label for="bs_contact_form_title"><?php esc_html_e( 'Form Name', 'ct-custom' ); ?></label>
						<input type="text" id="bs_contact_form_title" name="form_title" placeholder="<?php esc_attr_e( 'Contact Us', 'ct-custom' ); ?>">
					</div>
					<div class="ct-admin-field" id="bs_contact_form_shortcode_field">
						<label for="bs_contact_form_shortcode"><?php esc_html_e( 'Shortcode', 'ct-custom' ); ?></label>
						<input type="text" id="bs_contact_form_shortcode" readonly value="">
					</div>

					<div class="ct-admin-field">
						<label for="bs_contact_form_emails"><?php esc_html_e( 'Recipient Emails (comma separated)', 'ct-custom' ); ?></label>
						<input type="text" id="bs_contact_form_emails" name="form_emails" placeholder="admin@example.com, support@example.com">
					</div>

					<div class="ct-admin-field ct-admin-field--toggle">
						<label><?php esc_html_e( 'Logged-in users only', 'ct-custom' ); ?></label>
						<label class="ct-seo-toggle">
							<input type="checkbox" id="bs_contact_form_logged_in" class="ct-seo-toggle__input">
							<span class="ct-seo-toggle__slider"></span>
						</label>
					</div>

					<div class="ct-admin-field ct-admin-field--toggle">
						<label><?php esc_html_e( 'Enable Captcha', 'ct-custom' ); ?></label>
						<label class="ct-seo-toggle">
							<input type="checkbox" id="bs_contact_form_captcha" class="ct-seo-toggle__input">
							<span class="ct-seo-toggle__slider"></span>
						</label>
					</div>

					<div class="ct-admin-field ct-admin-field--toggle">
						<label><?php esc_html_e( 'Enable File Uploads', 'ct-custom' ); ?></label>
						<label class="ct-seo-toggle">
							<input type="checkbox" id="bs_contact_form_uploads" class="ct-seo-toggle__input">
							<span class="ct-seo-toggle__slider"></span>
						</label>
					</div>

					<div class="ct-admin-field">
						<label for="bs_contact_form_upload_storage"><?php esc_html_e( 'Upload Storage', 'ct-custom' ); ?></label>
						<select id="bs_contact_form_upload_storage">
							<option value="wordpress"><?php esc_html_e( 'WordPress Media', 'ct-custom' ); ?></option>
							<option value="s3"><?php esc_html_e( 'AWS S3', 'ct-custom' ); ?></option>
						</select>
					</div>

					<div class="ct-admin-field">
						<label for="bs_contact_form_s3_bucket"><?php esc_html_e( 'AWS_S3_BUCKET', 'ct-custom' ); ?></label>
						<input type="text" id="bs_contact_form_s3_bucket" placeholder="bucket.example.com/path/">
					</div>
					<div class="ct-admin-field">
						<label for="bs_contact_form_s3_key"><?php esc_html_e( 'AWS_ACCESS_KEY_ID', 'ct-custom' ); ?></label>
						<input type="text" id="bs_contact_form_s3_key">
					</div>
					<div class="ct-admin-field">
						<label for="bs_contact_form_s3_secret"><?php esc_html_e( 'AWS_SECRET_ACCESS_KEY', 'ct-custom' ); ?></label>
						<input type="password" id="bs_contact_form_s3_secret">
					</div>

					<div class="ct-admin-field ct-admin-field--stack">
						<label><?php esc_html_e( 'Fields', 'ct-custom' ); ?></label>
						<div id="bs_contact_fields_list" class="ct-admin-form-builder__fields"></div>
						<button type="button" class="button" id="bs_contact_field_add"><?php esc_html_e( 'Add Field', 'ct-custom' ); ?></button>
					</div>

					<div class="ct-admin-form-actions">
						<button type="button" class="button button-primary" id="bs_contact_form_save"><?php esc_html_e( 'Save Form', 'ct-custom' ); ?></button>
						<button type="button" class="button" id="bs_contact_form_cancel" style="display:none;"><?php esc_html_e( 'Cancel', 'ct-custom' ); ?></button>
						<button type="button" class="button" id="bs_contact_form_delete"><?php esc_html_e( 'Delete Form', 'ct-custom' ); ?></button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<?php wp_nonce_field( 'admin_get_contact_messages_count_nonce', 'admin_get_contact_messages_count_nonce' ); ?>

</div>
