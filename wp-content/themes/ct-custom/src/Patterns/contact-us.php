<?php
/**
 * Title: Contact Us
 * Slug: ct-custom/contact-us
 * Categories: ct-custom
 * Keywords: contact, form, contact us, message
 * Description: A contact form section with a customizable title and the theme contact form.
 * Viewport Width: 800
 */

$bs_title = esc_html( get_theme_mod( 'bs_contact_us_title', 'CONTACT US' ) );
?>
<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group">

<!-- wp:heading {"level":2,"style":{"typography":{"textTransform":"uppercase"}},"className":"section-title"} -->
<h2 class="wp-block-heading section-title" style="text-transform:uppercase"><?php echo $bs_title; ?></h2>
<!-- /wp:heading -->

<!-- wp:shortcode -->
[bs_contact_form]
<!-- /wp:shortcode -->

</div>
<!-- /wp:group -->
