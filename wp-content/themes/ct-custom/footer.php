<?php
/**
 * The template for displaying the footer
 *
 * @package CT_Custom
 */

extract( ct_custom_get_footer_data() );
?>

            </div><!-- .ct-layout__content -->
            <?php ct_sidebar_render( 'right' ); ?>
        </div><!-- .ct-layout -->
    </div><!-- #content -->

    <footer id="colophon" class="site-footer" role="contentinfo">
        <?php if ( $has_footer_widgets ) : ?>
            <div class="site-footer__widgets" aria-label="<?php esc_attr_e( 'Footer Widgets', 'ct-custom' ); ?>">
                <div class="ct-container">
                    <div class="site-footer__columns site-footer__columns--<?php echo esc_attr( $footer_columns ); ?>">
                        <?php for ( $i = 1; $i <= $footer_columns; $i++ ) : ?>
                            <div class="site-footer__column">
                                <?php
                                $ct_lang_sidebar = 'footer-column-' . $i . '-' . $current_language;
                                if ( is_active_sidebar( $ct_lang_sidebar ) ) {
                                    dynamic_sidebar( $ct_lang_sidebar );
                                }
                                ?>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="site-footer__copyright tac fs14">
            <div class="ct-container">
                <?php
                $ct_copyright_menu_location = ct_get_menu_location( 'footer-copyright-menu' );
                if ( has_nav_menu( $ct_copyright_menu_location ) ) :
                ?>
                    <nav class="ct-footer-copyright-nav" role="navigation" aria-label="<?php esc_attr_e( 'Footer Menu', 'ct-custom' ); ?>">
                        <?php
                        wp_nav_menu( array(
                            'theme_location' => $ct_copyright_menu_location,
                            'depth'          => 1,
                            'container'      => false,
                            'menu_class'     => 'ct-footer-copyright-menu df fww jcc m0 p0',
                            'fallback_cb'    => false,
                        ) );
                        ?>
                    </nav>
                    <hr class="ct-footer-copyright-separator" aria-hidden="true">
                <?php endif; ?>
                <p class="ct-footer-copyright m0">
                    <?php echo wp_kses_post( $footer_copyright ); ?>
                </p>
            </div>
        </div>
    </footer>

    <?php
    $ct_btt_enabled  = get_theme_mod( 'ct_back_to_top_enabled', true );
    $ct_btt_position = get_theme_mod( 'ct_back_to_top_position', 'right' );
    $ct_btt_label    = get_theme_mod( 'ct_back_to_top_label', '' );
    $ct_btt_icon_id  = get_theme_mod( 'ct_back_to_top_icon', 0 );
    $ct_btt_class    = ( 'left' === $ct_btt_position ) ? 'ct-back-to-top--left' : 'ct-back-to-top--right';
    if ( ! $ct_btt_enabled ) {
        $ct_btt_class .= ' ct-back-to-top--disabled';
    }
    if ( $ct_btt_label ) {
        $ct_btt_class .= ' ct-back-to-top--has-label';
    }
    $ct_btt_aria = $ct_btt_label ? $ct_btt_label : __( 'Back to top', 'ct-custom' );
    ?>
    <button id="ct-back-to-top" class="ct-back-to-top df aic jcc cp p0 <?php echo esc_attr( $ct_btt_class ); ?>" aria-label="<?php echo esc_attr( $ct_btt_aria ); ?>">
        <span class="ct-back-to-top__icon df aic jcc">
            <?php if ( $ct_btt_icon_id ) : ?>
                <?php echo wp_get_attachment_image( $ct_btt_icon_id, 'thumbnail', false, array( 'aria-hidden' => 'true', 'loading' => 'lazy' ) ); ?>
            <?php else : ?>
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="18 15 12 9 6 15"></polyline></svg>
            <?php endif; ?>
        </span>
        <?php if ( $ct_btt_label ) : ?>
            <span class="ct-back-to-top__label"><?php echo esc_html( $ct_btt_label ); ?></span>
        <?php endif; ?>
    </button>

</div><!-- #page -->

<?php wp_footer(); ?>

</body>
</html>
