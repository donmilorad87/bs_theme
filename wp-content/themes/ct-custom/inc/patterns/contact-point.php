<?php
/**
 * Title: Contact Point
 * Slug: ct-custom/contact-point
 * Categories: ct-custom
 * Keywords: contact, reach us, address, phone, social, icons
 * Description: A contact info block with title, company name, address, phone, fax, email, and social icons.
 * Viewport Width: 600
 */

$ct_contact   = \CTCustom\Template\Contact::instance();
$ct_cp_data   = $ct_contact->get_contact_point_data();
$ct_addr      = $ct_contact->build_address_lines( $ct_cp_data['address'] );
$ct_title     = esc_html( get_theme_mod( 'ct_reach_us_title', 'REACH US' ) );
$ct_company   = esc_html( get_bloginfo( 'name' ) );
$ct_phone     = $ct_cp_data['phone'];
$ct_fax       = $ct_cp_data['fax'];
$ct_email     = $ct_cp_data['email'];
?>
<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group">

<!-- wp:heading {"level":2,"style":{"typography":{"textTransform":"uppercase"}},"className":"section-title"} -->
<h2 class="wp-block-heading section-title" style="text-transform:uppercase"><?php echo $ct_title; ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"reach-us__company"} -->
<p class="reach-us__company"><?php echo $ct_company; ?></p>
<!-- /wp:paragraph -->

<?php if ( ! empty( $ct_addr['line1'] ) || ! empty( $ct_addr['line2'] ) ) : ?>
<!-- wp:paragraph {"className":"reach-us__address"} -->
<p class="reach-us__address"><?php echo $ct_addr['line1']; ?><br><?php echo $ct_addr['line2']; ?></p>
<!-- /wp:paragraph -->
<?php endif; ?>

<?php if ( ! empty( $ct_phone ) ) : ?>
<!-- wp:paragraph {"className":"reach-us__phone"} -->
<p class="reach-us__phone"><?php esc_html_e( 'Phone:', 'ct-custom' ); ?> <a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $ct_phone ) ); ?>"><?php echo esc_html( $ct_phone ); ?></a></p>
<!-- /wp:paragraph -->
<?php endif; ?>

<?php if ( ! empty( $ct_fax ) ) : ?>
<!-- wp:paragraph {"className":"reach-us__fax"} -->
<p class="reach-us__fax"><?php esc_html_e( 'Fax:', 'ct-custom' ); ?> <?php echo esc_html( $ct_fax ); ?></p>
<!-- /wp:paragraph -->
<?php endif; ?>

<?php if ( ! empty( $ct_email ) ) : ?>
<!-- wp:paragraph {"className":"reach-us__email"} -->
<p class="reach-us__email"><?php esc_html_e( 'Email:', 'ct-custom' ); ?> <a href="mailto:<?php echo esc_attr( $ct_email ); ?>"><?php echo esc_html( $ct_email ); ?></a></p>
<!-- /wp:paragraph -->
<?php endif; ?>

<!-- wp:shortcode -->
[ct_social_icons]
<!-- /wp:shortcode -->

</div>
<!-- /wp:group -->
