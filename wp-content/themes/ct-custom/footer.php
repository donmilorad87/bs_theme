<?php
/**
 * The template for displaying the footer
 *
 * @package BS_Custom
 */

extract( bs_custom_get_footer_data() );
$bs_theme_toggle_enabled  = get_theme_mod( 'bs_theme_toggle_enabled', true );
$bs_theme_toggle_position = get_theme_mod( 'bs_theme_toggle_position', 'header' );
$bs_theme_toggle_position = is_string( $bs_theme_toggle_position ) ? $bs_theme_toggle_position : 'header';
$bs_lang_switcher_position = get_theme_mod( 'bs_lang_switcher_position', 'top_header' );
$bs_lang_switcher_position = is_string( $bs_lang_switcher_position ) ? $bs_lang_switcher_position : 'top_header';
$bs_lang_switcher_enabled  = function_exists( 'bs_is_multilingual' ) && bs_is_multilingual();
$bs_auth_links_position = get_theme_mod( 'bs_auth_links_position', 'top_header' );
$bs_auth_links_position = is_string( $bs_auth_links_position ) ? $bs_auth_links_position : 'top_header';
$bs_auth_links_enabled  = function_exists( 'bs_user_management_enabled' ) && bs_user_management_enabled();
?>

            </div><!-- .ct-layout__content -->
            <?php bs_sidebar_render( 'right' ); ?>
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
                                $bs_lang_sidebar = 'footer-column-' . $i . '-' . $current_language;
                                if ( is_active_sidebar( $bs_lang_sidebar ) ) {
                                    dynamic_sidebar( $bs_lang_sidebar );
                                }
                                ?>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ( ! empty( $bs_theme_toggle_enabled ) && 'footer' === $bs_theme_toggle_position ) : ?>
            <div class="ct-footer-control-wrap ct-footer-control-wrap--top df jcc">
                <?php bs_custom_render_theme_toggle( 'theme-toggle--standalone' ); ?>
            </div>
        <?php endif; ?>
        <?php if ( $bs_lang_switcher_enabled && 'footer' === $bs_lang_switcher_position ) : ?>
            <div class="ct-footer-control-wrap ct-footer-control-wrap--top df jcc">
                <?php bs_custom_render_language_switcher(); ?>
            </div>
        <?php endif; ?>
        <?php if ( $bs_auth_links_enabled && 'footer' === $bs_auth_links_position ) : ?>
            <div class="ct-footer-control-wrap ct-footer-control-wrap--top df jcc">
                <?php bs_custom_render_auth_links(); ?>
            </div>
        <?php endif; ?>

        <div class="site-footer__copyright tac fs14">
            <div class="ct-container">
                <?php
                $bs_copyright_menu_location = bs_get_menu_location( 'footer-copyright-menu' );
                if ( has_nav_menu( $bs_copyright_menu_location ) ) :
                ?>
                    <nav class="ct-footer-copyright-nav mb8" role="navigation" aria-label="<?php esc_attr_e( 'Footer Menu', 'ct-custom' ); ?>">
                        <?php
                        wp_nav_menu( array(
                            'theme_location' => $bs_copyright_menu_location,
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
                <?php if ( ! empty( $bs_theme_toggle_enabled ) && 'bottom_footer' === $bs_theme_toggle_position ) : ?>
                    <div class="ct-footer-control-wrap ct-footer-control-wrap--bottom df jcc">
                        <?php bs_custom_render_theme_toggle( 'theme-toggle--standalone' ); ?>
                    </div>
                <?php endif; ?>
                <?php if ( $bs_lang_switcher_enabled && 'bottom_footer' === $bs_lang_switcher_position ) : ?>
                    <div class="ct-footer-control-wrap ct-footer-control-wrap--bottom df jcc">
                        <?php bs_custom_render_language_switcher(); ?>
                    </div>
                <?php endif; ?>
                <?php if ( $bs_auth_links_enabled && 'bottom_footer' === $bs_auth_links_position ) : ?>
                    <div class="ct-footer-control-wrap ct-footer-control-wrap--bottom df jcc">
                        <?php bs_custom_render_auth_links(); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </footer>

    <?php
    $bs_btt_enabled  = get_theme_mod( 'bs_back_to_top_enabled', true );
    $bs_btt_position = get_theme_mod( 'bs_back_to_top_position', 'right' );
    $bs_btt_label    = get_theme_mod( 'bs_back_to_top_label', '' );
    $bs_btt_icon_id  = get_theme_mod( 'bs_back_to_top_icon', 0 );
    $bs_btt_class    = ( 'left' === $bs_btt_position ) ? 'ct-back-to-top--left' : 'ct-back-to-top--right';
    if ( ! $bs_btt_enabled ) {
        $bs_btt_class .= ' ct-back-to-top--disabled';
    }
    if ( $bs_btt_label ) {
        $bs_btt_class .= ' ct-back-to-top--has-label';
    }
    $bs_btt_class .= ' ct-back-to-top--in-float';
    $bs_btt_aria = $bs_btt_label ? $bs_btt_label : __( 'Back to top', 'ct-custom' );
    $bs_btt_markup = '';
    ob_start();
    ?>
    <button id="ct-back-to-top" class="ct-back-to-top df aic jcc cp p0 <?php echo esc_attr( $bs_btt_class ); ?>" aria-label="<?php echo esc_attr( $bs_btt_aria ); ?>">
        <span class="ct-back-to-top__icon df aic jcc">
            <?php if ( $bs_btt_icon_id ) : ?>
                <?php echo wp_get_attachment_image( $bs_btt_icon_id, 'thumbnail', false, array( 'aria-hidden' => 'true', 'loading' => 'lazy' ) ); ?>
            <?php else : ?>
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="18 15 12 9 6 15"></polyline></svg>
            <?php endif; ?>
        </span>
        <?php if ( $bs_btt_label ) : ?>
            <span class="ct-back-to-top__label fs12"><?php echo esc_html( $bs_btt_label ); ?></span>
        <?php endif; ?>
    </button>
    <?php
    $bs_btt_markup = ob_get_clean();

    $bs_theme_toggle_markup = '';
    if ( ! empty( $bs_theme_toggle_enabled ) ) {
        ob_start();
        bs_custom_render_theme_toggle( 'theme-toggle--standalone' );
        $bs_theme_toggle_markup = ob_get_clean();
    }

    $bs_lang_switcher_markup = '';
    if ( $bs_lang_switcher_enabled ) {
        ob_start();
        bs_custom_render_language_switcher();
        $bs_lang_switcher_markup = ob_get_clean();
    }

    $bs_auth_links_markup = '';
    if ( $bs_auth_links_enabled ) {
        ob_start();
        bs_custom_render_auth_links();
        $bs_auth_links_markup = ob_get_clean();
    }

    $float_top_left = '';
    $float_top_right = '';
    $float_bottom_left = '';
    $float_bottom_right = '';

    if ( ! empty( $bs_theme_toggle_enabled ) ) {
        if ( 'floating_top_left' === $bs_theme_toggle_position ) {
            $float_top_left .= $bs_theme_toggle_markup;
        } elseif ( 'floating_top_right' === $bs_theme_toggle_position ) {
            $float_top_right .= $bs_theme_toggle_markup;
        } elseif ( 'floating_bottom_left' === $bs_theme_toggle_position ) {
            $float_bottom_left .= $bs_theme_toggle_markup;
        } elseif ( 'floating_bottom_right' === $bs_theme_toggle_position ) {
            $float_bottom_right .= $bs_theme_toggle_markup;
        }
    }

    if ( $bs_lang_switcher_enabled ) {
        if ( 'floating_top_left' === $bs_lang_switcher_position ) {
            $float_top_left .= $bs_lang_switcher_markup;
        } elseif ( 'floating_top_right' === $bs_lang_switcher_position ) {
            $float_top_right .= $bs_lang_switcher_markup;
        } elseif ( 'floating_bottom_left' === $bs_lang_switcher_position ) {
            $float_bottom_left .= $bs_lang_switcher_markup;
        } elseif ( 'floating_bottom_right' === $bs_lang_switcher_position ) {
            $float_bottom_right .= $bs_lang_switcher_markup;
        }
    }

    if ( $bs_auth_links_enabled ) {
        if ( 'floating_top_left' === $bs_auth_links_position ) {
            $float_top_left .= $bs_auth_links_markup;
        } elseif ( 'floating_top_right' === $bs_auth_links_position ) {
            $float_top_right .= $bs_auth_links_markup;
        } elseif ( 'floating_bottom_left' === $bs_auth_links_position ) {
            $float_bottom_left .= $bs_auth_links_markup;
        } elseif ( 'floating_bottom_right' === $bs_auth_links_position ) {
            $float_bottom_right .= $bs_auth_links_markup;
        }
    }

    if ( 'left' === $bs_btt_position ) {
        $float_bottom_left .= $bs_btt_markup;
    } else {
        $float_bottom_right .= $bs_btt_markup;
    }

    if ( '' !== $float_top_left ) {
        echo '<div class="ct-floating-container ct-floating-container--top-left df fdc">' . $float_top_left . '</div>';
    }
    if ( '' !== $float_top_right ) {
        echo '<div class="ct-floating-container ct-floating-container--top-right df fdc">' . $float_top_right . '</div>';
    }
    if ( '' !== $float_bottom_left ) {
        echo '<div class="ct-floating-container ct-floating-container--bottom-left df fdc">' . $float_bottom_left . '</div>';
    }
    if ( '' !== $float_bottom_right ) {
        echo '<div class="ct-floating-container ct-floating-container--bottom-right df fdc">' . $float_bottom_right . '</div>';
    }
    ?>

</div><!-- #page -->

<?php wp_footer(); ?>

</body>
</html>
