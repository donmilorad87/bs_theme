<?php

namespace CTCustom\Widgets;

use WP_Widget;
use CTCustom\Multilang\TranslationService;

class SocialIconsWidget extends WP_Widget {

    const MAX_ICONS = 50;

    public function __construct() {
        parent::__construct(
            'ct_social_icons',
            __( 'CT Custom: Social Icons', 'ct-custom' ),
            array(
                'description' => __( 'Displays social network icons from CT Custom Theme Settings.', 'ct-custom' ),
            )
        );
    }

    public function widget( $args, $instance ) {
        assert( is_array( $args ), 'Widget args must be an array' );
        assert( is_array( $instance ), 'Widget instance must be an array' );

        $networks_raw = get_option( 'ct_custom_social_networks', '[]' );
        $networks     = json_decode( stripslashes( $networks_raw ), true );

        if ( ! is_array( $networks ) || empty( $networks ) ) {
            return;
        }

        echo $args['before_widget'];

        if ( ! empty( $instance['title'] ) ) {
            echo $args['before_title'] . esc_html( TranslationService::resolve_raw( $instance['title'] ) ) . $args['after_title'];
        }

        echo '<div class="widget-social-icons">';
        echo '<div class="social-icons" role="list" aria-label="' . esc_attr__( 'Social Networks', 'ct-custom' ) . '">';

        $icon_count = 0;

        foreach ( $networks as $network ) {
            if ( $icon_count >= self::MAX_ICONS ) {
                break;
            }
            $icon_count++;

            $name     = isset( $network['name'] ) ? $network['name'] : '';
            $url      = isset( $network['url'] ) ? $network['url'] : '';
            $icon_id  = isset( $network['icon_id'] ) ? absint( $network['icon_id'] ) : 0;
            $icon_url = isset( $network['icon_url'] ) ? $network['icon_url'] : '';

            if ( empty( $url ) ) {
                continue;
            }

            echo '<a href="' . esc_url( $url ) . '"'
                . ' target="_blank"'
                . ' rel="noopener noreferrer"'
                . ' title="' . esc_attr( $name ) . '"'
                . ' role="listitem">';

            if ( $icon_id && function_exists( 'ct_custom_get_attachment_image' ) ) {
                echo ct_custom_get_attachment_image( $icon_id, 'thumbnail', array(
                    'alt'     => esc_attr( $name ),
                    'loading' => 'lazy',
                ) );
            } elseif ( $icon_url ) {
                echo '<img src="' . esc_url( $icon_url ) . '" alt="' . esc_attr( $name ) . '" loading="lazy">';
            }

            echo '</a>';
        }

        if ( get_theme_mod( 'ct_social_share_enabled', true ) ) {
            echo '<button'
                . ' type="button"'
                . ' class="share-button share-with-friend"'
                . ' data-url="' . esc_attr( home_url( '/' ) ) . '"'
                . ' data-title="' . esc_attr( get_bloginfo( 'name' ) ) . '"'
                . ' data-text="' . esc_attr( get_bloginfo( 'description' ) ) . '"'
                . ' title="' . esc_attr__( 'Share with a friend', 'ct-custom' ) . '"'
                . ' role="listitem"'
                . ' aria-label="' . esc_attr__( 'Share with a friend', 'ct-custom' ) . '">'
                . '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true">'
                . '<path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92s2.92-1.31 2.92-2.92-1.31-2.92-2.92-2.92z"/>'
                . '</svg>'
                . '</button>';
        }

        echo '</div>';
        echo '</div>';

        echo $args['after_widget'];
    }

    public function form( $instance ) {
        assert( is_array( $instance ) || empty( $instance ), 'Instance must be an array or empty' );
        assert( is_object( $this ), 'Widget must be initialized' );

        $title = isset( $instance['title'] ) ? $instance['title'] : '';
        ?>
        <div class="ct-wtp-field">
            <label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">
                <?php esc_html_e( 'Title:', 'ct-custom' ); ?>
            </label>
            <div class="ct-wtp">
                <input
                    class="widefat ct-wtp__target"
                    id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
                    name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
                    type="text"
                    value="<?php echo esc_attr( $title ); ?>"
                >
                <button type="button" class="button ct-wtp__pick-btn"><?php esc_html_e( 'Pick Key', 'ct-custom' ); ?></button>
                <div class="ct-wtp__dropdown" style="display:none;">
                    <input type="text" class="ct-wtp__search widefat" placeholder="<?php esc_attr_e( 'Search keys...', 'ct-custom' ); ?>">
                    <ul class="ct-wtp__key-list"></ul>
                </div>
            </div>
        </div>
        <p class="description">
            <?php esc_html_e( 'Icons are managed in CT Custom Theme > Social Networks settings.', 'ct-custom' ); ?>
        </p>
        <?php
    }

    public function update( $new_instance, $old_instance ) {
        assert( is_array( $new_instance ), 'New instance must be an array' );
        assert( is_array( $old_instance ), 'Old instance must be an array' );

        $instance          = array();
        $instance['title'] = sanitize_text_field( $new_instance['title'] );

        return $instance;
    }
}
