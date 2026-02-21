<?php
/**
 * Title: Contact Point
 * Slug: ct-custom/contact-point
 * Categories: ct-custom
 * Keywords: contact, reach us, address, phone, social, icons
 * Description: A contact info block with title, company name, address, phone, fax, email, and social icons.
 * Viewport Width: 600
 */

$bs_contact   = \BSCustom\Template\Contact::instance();
$bs_cp_data   = $bs_contact->get_contact_point_data();
$bs_addr      = $bs_contact->build_address_lines( $bs_cp_data['address'] );
$bs_title     = esc_html( get_theme_mod( 'bs_reach_us_title', 'REACH US' ) );
$bs_company   = esc_html( get_bloginfo( 'name' ) );
$bs_phone     = $bs_cp_data['phone'];
$bs_fax       = $bs_cp_data['fax'];
$bs_email     = $bs_cp_data['email'];
?>
<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group">

<!-- wp:heading {"level":2,"style":{"typography":{"textTransform":"uppercase"}},"className":"section-title"} -->
<h2 class="wp-block-heading section-title" style="text-transform:uppercase"><?php echo $bs_title; ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"reach-us__company"} -->
<p class="reach-us__company"><?php echo $bs_company; ?></p>
<!-- /wp:paragraph -->

<?php if ( ! empty( $bs_addr['line1'] ) || ! empty( $bs_addr['line2'] ) ) : ?>
<!-- wp:paragraph {"className":"reach-us__address"} -->
<p class="reach-us__address"><?php echo $bs_addr['line1']; ?><br><?php echo $bs_addr['line2']; ?></p>
<!-- /wp:paragraph -->
<?php endif; ?>

<?php if ( ! empty( $bs_phone ) ) : ?>
<!-- wp:paragraph {"className":"reach-us__phone"} -->
<p class="reach-us__phone"><?php esc_html_e( 'Phone:', 'ct-custom' ); ?> <a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $bs_phone ) ); ?>"><?php echo esc_html( $bs_phone ); ?></a></p>
<!-- /wp:paragraph -->
<?php endif; ?>

<?php if ( ! empty( $bs_fax ) ) : ?>
<!-- wp:paragraph {"className":"reach-us__fax"} -->
<p class="reach-us__fax"><?php esc_html_e( 'Fax:', 'ct-custom' ); ?> <?php echo esc_html( $bs_fax ); ?></p>
<!-- /wp:paragraph -->
<?php endif; ?>

<?php if ( ! empty( $bs_email ) ) : ?>
<!-- wp:paragraph {"className":"reach-us__email"} -->
<p class="reach-us__email"><?php esc_html_e( 'Email:', 'ct-custom' ); ?> <a href="mailto:<?php echo esc_attr( $bs_email ); ?>"><?php echo esc_html( $bs_email ); ?></a></p>
<!-- /wp:paragraph -->
<?php endif; ?>

<!-- wp:shortcode -->
[bs_social_icons]
<!-- /wp:shortcode -->

</div>
<!-- /wp:group -->
