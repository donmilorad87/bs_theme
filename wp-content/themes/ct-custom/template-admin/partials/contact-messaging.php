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

$pointers_raw = get_option( 'bs_custom_contact_pointers', '[]' );
$pointers     = json_decode( $pointers_raw, true );

if ( ! is_array( $pointers ) ) {
	$pointers = array();
}

assert( is_array( $pointers ), 'Pointers must be an array' );

$rest_url = esc_url( rest_url( 'ct-auth/v1' ) );
?>
<div class="ct-admin-section ct-contact-admin" data-rest-url="<?php echo $rest_url; ?>">

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
	<div class="ct-contact-panel ct-contact-panel--active" id="ct_contact_messages_panel">
		<div class="ct-contact-filters">
			<select id="ct_contact_pointer_filter" class="ct-admin-input ct-admin-input--select">
				<option value=""><?php esc_html_e( 'All Pointers', 'ct-custom' ); ?></option>
				<?php
				$max_opts = 20;
				$opt_count = 0;
				foreach ( $pointers as $p ) :
					if ( $opt_count >= $max_opts ) {
						break;
					}
					$opt_count++;
					?>
					<option value="<?php echo esc_attr( $p['slug'] ); ?>"><?php echo esc_html( $p['label'] ); ?></option>
				<?php endforeach; ?>
			</select>
			<select id="ct_contact_status_filter" class="ct-admin-input ct-admin-input--select">
				<option value="all"><?php esc_html_e( 'All', 'ct-custom' ); ?></option>
				<option value="unread"><?php esc_html_e( 'Unread', 'ct-custom' ); ?></option>
				<option value="read"><?php esc_html_e( 'Read', 'ct-custom' ); ?></option>
			</select>
		</div>

		<div id="ct_contact_messages_list" class="ct-contact-messages">
			<p class="ct-admin-no-items"><?php esc_html_e( 'Loading messages...', 'ct-custom' ); ?></p>
		</div>

		<div id="ct_contact_pagination" class="ct-contact-pagination"></div>
	</div>

	<!-- Configuration sub-tab -->
	<div class="ct-contact-panel" id="ct_contact_config_panel" style="display:none;">
		<h3><?php esc_html_e( 'Contact Pointers', 'ct-custom' ); ?></h3>
		<p class="description"><?php esc_html_e( 'Pointers route messages to specific email addresses. The form uses the pointer slug to determine where to send notifications.', 'ct-custom' ); ?></p>

		<form id="add_contact_pointer_form" class="ct-admin-form ct-pointer-form">
			<h4 id="pointer_form_heading" class="ct-pointer-form__heading"><?php esc_html_e( 'Add New Pointer', 'ct-custom' ); ?></h4>
			<div class="ct-admin-field">
				<label for="pointer_slug"><?php esc_html_e( 'Slug', 'ct-custom' ); ?></label>
				<input type="text" id="pointer_slug" name="pointer_slug" placeholder="contact_us" required>
				<span class="ct-pointer-form__error" id="pointer_slug_error"></span>
			</div>
			<div class="ct-admin-field">
				<label for="pointer_label"><?php esc_html_e( 'Label', 'ct-custom' ); ?></label>
				<input type="text" id="pointer_label" name="pointer_label" placeholder="Contact Us" required>
				<span class="ct-pointer-form__error" id="pointer_label_error"></span>
			</div>
			<div class="ct-admin-field">
				<label for="pointer_emails"><?php esc_html_e( 'Emails (comma separated)', 'ct-custom' ); ?></label>
				<input type="text" id="pointer_emails" name="pointer_emails" placeholder="admin@example.com, support@example.com" required>
				<span class="ct-pointer-form__error" id="pointer_emails_error"></span>
			</div>

			<div class="ct-admin-form-actions">
				<button type="button" class="button button-primary" id="add_pointer_btn" disabled><?php esc_html_e( 'Add Pointer', 'ct-custom' ); ?></button>
				<button type="button" class="button button-primary" id="save_pointer_edit_btn" style="display:none;"><?php esc_html_e( 'Save Edit', 'ct-custom' ); ?></button>
				<button type="button" class="button ct-admin-cancel-edit" id="cancel_pointer_edit_btn" style="display:none;"><?php esc_html_e( 'Cancel Edit', 'ct-custom' ); ?></button>
			</div>

			<?php wp_nonce_field( 'admin_save_contact_pointers_nonce', 'admin_save_contact_pointers_nonce' ); ?>
		</form>

		<div id="contact_pointers_list" data-pointers="<?php echo esc_attr( wp_json_encode( $pointers ) ); ?>">
			<?php if ( empty( $pointers ) ) : ?>
				<p class="ct-admin-no-items"><?php esc_html_e( 'No pointers configured yet.', 'ct-custom' ); ?></p>
			<?php else : ?>
				<ul class="ct-admin-pointer-list">
					<?php
					$max_display = 20;
					$display_count = 0;
					foreach ( $pointers as $index => $pointer ) :
						if ( $display_count >= $max_display ) {
							break;
						}
						$display_count++;
						?>
						<li class="ct-admin-pointer-item">
							<span class="ct-admin-pointer-num"><?php echo (int) ( $index + 1 ); ?>.</span>
							<span class="ct-admin-pointer-slug"><?php echo esc_html( $pointer['slug'] ); ?></span>
							<span class="ct-admin-pointer-label"><?php echo esc_html( $pointer['label'] ); ?></span>
							<span class="ct-admin-pointer-emails"><?php echo esc_html( implode( ', ', $pointer['emails'] ) ); ?></span>
							<div class="ct-admin-pointer-actions">
								<button type="button" class="button ct-admin-edit-pointer" data-index="<?php echo (int) $index; ?>"><?php esc_html_e( 'Edit', 'ct-custom' ); ?></button>
								<button type="button" class="button ct-admin-remove-pointer" data-index="<?php echo (int) $index; ?>"><?php esc_html_e( 'Remove', 'ct-custom' ); ?></button>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</div>

	<?php wp_nonce_field( 'admin_get_contact_messages_count_nonce', 'admin_get_contact_messages_count_nonce' ); ?>

</div>
