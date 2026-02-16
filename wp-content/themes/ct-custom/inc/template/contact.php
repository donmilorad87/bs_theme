<?php
/**
 * Contact template functions — global wrapper functions.
 *
 * Thin backward-compatible wrappers that delegate to CT_Contact.
 *
 * @package CT_Custom
 */

use CTCustom\Template\Contact;

/**
 * Gather all data needed by the contact page template.
 *
 * @return array
 */
function ct_custom_get_contact_template_data() {
	return Contact::instance()->get_contact_template_data();
}

/**
 * Render the Contact / Reach Us section.
 */
function ct_custom_render_contact_section() {
	Contact::instance()->render_contact_section();
}

/**
 * Render social icons markup.
 *
 * @param array $networks Array of network entries.
 */
function ct_custom_render_social_icons_markup( $networks ) {
	Contact::instance()->render_social_icons_markup( $networks );
}
