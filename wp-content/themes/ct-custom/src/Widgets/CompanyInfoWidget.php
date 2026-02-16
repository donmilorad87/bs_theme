<?php

namespace BSCustom\Widgets;

use WP_Widget;
use BSCustom\Multilang\TranslationService;

class CompanyInfoWidget extends WP_Widget {

    const MAX_FIELD_LENGTH = 200;

    public function __construct() {
        parent::__construct(
            'ct_company_info',
            __( 'BS Custom: Company Info', 'ct-custom' ),
            array(
                'description' => __( 'Displays company logo, PIB, MB, and description.', 'ct-custom' ),
            )
        );
    }

    /**
     * Resolve the logo attachment ID.
     *
     * Returns the widget-level logo if set, otherwise
     * falls back to the customizer custom_logo.
     */
    private function resolve_logo_id( $instance_logo_id ) {
        assert( is_int( $instance_logo_id ) || is_numeric( $instance_logo_id ), 'Logo ID must be numeric' );
        assert( $instance_logo_id >= 0, 'Logo ID must not be negative' );

        if ( $instance_logo_id > 0 ) {
            return $instance_logo_id;
        }

        return absint( get_theme_mod( 'custom_logo', 0 ) );
    }

    public function widget( $args, $instance ) {
        assert( is_array( $args ), 'Widget args must be an array' );
        assert( is_array( $instance ), 'Widget instance must be an array' );

        $widget_logo_id = isset( $instance['logo_id'] ) ? absint( $instance['logo_id'] ) : 0;
        $logo_id        = $this->resolve_logo_id( $widget_logo_id );
        $pib            = isset( $instance['pib'] ) ? $instance['pib'] : '';
        $mb             = isset( $instance['mb'] ) ? $instance['mb'] : '';
        $description    = isset( $instance['description'] ) ? $instance['description'] : '';

        echo $args['before_widget'];

        if ( ! empty( $instance['title'] ) ) {
            echo $args['before_title'] . esc_html( TranslationService::resolve_raw( $instance['title'] ) ) . $args['after_title'];
        }

        echo '<div class="widget-company-info">';

        if ( $logo_id > 0 ) {
            $logo_image = wp_get_attachment_image( $logo_id, 'medium', false, array( 'loading' => 'lazy' ) );
            if ( $logo_image ) {
                $home_link = function_exists( 'ct_get_language_home_url' ) ? ct_get_language_home_url() : home_url( '/' );
                echo '<div class="widget-company-info__logo">';
                echo '<a href="' . esc_url( $home_link ) . '" rel="home">';
                echo $logo_image;
                echo '</a>';
                echo '</div>';
            }
        }

        if ( $description ) {
            echo '<p class="widget-company-info__description">' . esc_html( TranslationService::resolve_raw( $description ) ) . '</p>';
        }

        if ( $pib || $mb ) {
            echo '<dl class="widget-company-info__details">';

            if ( $pib ) {
                echo '<dt class="widget-company-info__label">' . esc_html__( 'PIB:', 'ct-custom' ) . '</dt>';
                echo '<dd class="widget-company-info__value">' . esc_html( $pib ) . '</dd>';
            }

            if ( $mb ) {
                echo '<dt class="widget-company-info__label">' . esc_html__( 'MB:', 'ct-custom' ) . '</dt>';
                echo '<dd class="widget-company-info__value">' . esc_html( $mb ) . '</dd>';
            }

            echo '</dl>';
        }

        echo '</div>';

        echo $args['after_widget'];
    }

    public function form( $instance ) {
        assert( is_array( $instance ) || empty( $instance ), 'Instance must be an array or empty' );
        assert( is_object( $this ), 'Widget must be initialized' );

        $title           = isset( $instance['title'] ) ? $instance['title'] : '';
        $widget_logo_id  = isset( $instance['logo_id'] ) ? absint( $instance['logo_id'] ) : 0;
        $pib             = isset( $instance['pib'] ) ? $instance['pib'] : '';
        $mb              = isset( $instance['mb'] ) ? $instance['mb'] : '';
        $description     = isset( $instance['description'] ) ? $instance['description'] : '';

        $customizer_logo_id = absint( get_theme_mod( 'custom_logo', 0 ) );
        $display_logo_id    = $widget_logo_id > 0 ? $widget_logo_id : $customizer_logo_id;
        $is_using_default   = ( 0 === $widget_logo_id && $customizer_logo_id > 0 );

        $logo_url = '';
        if ( $display_logo_id > 0 ) {
            $logo_url = wp_get_attachment_image_url( $display_logo_id, 'thumbnail' );
        }
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

        <p>
            <label><?php esc_html_e( 'Logo:', 'ct-custom' ); ?></label><br>
            <?php if ( $logo_url ) : ?>
                <img
                    id="<?php echo esc_attr( $this->get_field_id( 'logo_preview' ) ); ?>"
                    src="<?php echo esc_url( $logo_url ); ?>"
                    style="max-width:100%;height:auto;margin-bottom:8px;display:block;"
                >
            <?php else : ?>
                <img
                    id="<?php echo esc_attr( $this->get_field_id( 'logo_preview' ) ); ?>"
                    src=""
                    style="max-width:100%;height:auto;margin-bottom:8px;display:none;"
                >
            <?php endif; ?>
            <?php if ( $is_using_default ) : ?>
                <em style="display:block;margin-bottom:6px;color:#666;font-size:12px;">
                    <?php esc_html_e( 'Using logo from Customizer. Click below to override.', 'ct-custom' ); ?>
                </em>
            <?php endif; ?>
            <input
                type="hidden"
                id="<?php echo esc_attr( $this->get_field_id( 'logo_id' ) ); ?>"
                name="<?php echo esc_attr( $this->get_field_name( 'logo_id' ) ); ?>"
                value="<?php echo esc_attr( $widget_logo_id ); ?>"
            >
            <button
                type="button"
                class="button ct-upload-logo"
                data-target="#<?php echo esc_attr( $this->get_field_id( 'logo_id' ) ); ?>"
                data-preview="#<?php echo esc_attr( $this->get_field_id( 'logo_preview' ) ); ?>"
                data-customizer-logo="<?php echo $customizer_logo_id > 0 ? esc_url( wp_get_attachment_image_url( $customizer_logo_id, 'thumbnail' ) ) : ''; ?>"
                data-frame-title="<?php echo esc_attr__( 'Select Logo', 'ct-custom' ); ?>"
                data-reset-label="<?php echo esc_attr__( 'Reset to Default', 'ct-custom' ); ?>"
            >
                <?php esc_html_e( 'Select Logo', 'ct-custom' ); ?>
            </button>
            <?php if ( $widget_logo_id > 0 ) : ?>
                <button
                    type="button"
                    class="button ct-remove-logo"
                    data-target="#<?php echo esc_attr( $this->get_field_id( 'logo_id' ) ); ?>"
                    data-preview="#<?php echo esc_attr( $this->get_field_id( 'logo_preview' ) ); ?>"
                    data-customizer-logo="<?php echo $customizer_logo_id > 0 ? esc_url( wp_get_attachment_image_url( $customizer_logo_id, 'thumbnail' ) ) : ''; ?>"
                >
                    <?php esc_html_e( 'Reset to Default', 'ct-custom' ); ?>
                </button>
            <?php endif; ?>
        </p>

        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'pib' ) ); ?>">
                <?php esc_html_e( 'PIB:', 'ct-custom' ); ?>
            </label>
            <input
                class="widefat"
                id="<?php echo esc_attr( $this->get_field_id( 'pib' ) ); ?>"
                name="<?php echo esc_attr( $this->get_field_name( 'pib' ) ); ?>"
                type="text"
                value="<?php echo esc_attr( $pib ); ?>"
            >
        </p>

        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'mb' ) ); ?>">
                <?php esc_html_e( 'MB:', 'ct-custom' ); ?>
            </label>
            <input
                class="widefat"
                id="<?php echo esc_attr( $this->get_field_id( 'mb' ) ); ?>"
                name="<?php echo esc_attr( $this->get_field_name( 'mb' ) ); ?>"
                type="text"
                value="<?php echo esc_attr( $mb ); ?>"
            >
        </p>

        <div class="ct-wtp-field">
            <label for="<?php echo esc_attr( $this->get_field_id( 'description' ) ); ?>">
                <?php esc_html_e( 'Description:', 'ct-custom' ); ?>
            </label>
            <div class="ct-wtp">
                <textarea
                    class="widefat ct-wtp__target"
                    id="<?php echo esc_attr( $this->get_field_id( 'description' ) ); ?>"
                    name="<?php echo esc_attr( $this->get_field_name( 'description' ) ); ?>"
                    rows="4"
                ><?php echo esc_textarea( $description ); ?></textarea>
                <button type="button" class="button ct-wtp__pick-btn"><?php esc_html_e( 'Pick Key', 'ct-custom' ); ?></button>
                <div class="ct-wtp__dropdown" style="display:none;">
                    <input type="text" class="ct-wtp__search widefat" placeholder="<?php esc_attr_e( 'Search keys...', 'ct-custom' ); ?>">
                    <ul class="ct-wtp__key-list"></ul>
                </div>
            </div>
        </div>

        <?php
    }

    public function update( $new_instance, $old_instance ) {
        assert( is_array( $new_instance ), 'New instance must be an array' );
        assert( is_array( $old_instance ), 'Old instance must be an array' );

        $instance                = array();
        $instance['title']       = sanitize_text_field( $new_instance['title'] );
        $instance['logo_id']     = absint( $new_instance['logo_id'] );
        $instance['pib']         = sanitize_text_field( $new_instance['pib'] );
        $instance['mb']          = sanitize_text_field( $new_instance['mb'] );
        $instance['description'] = sanitize_textarea_field( $new_instance['description'] );

        return $instance;
    }
}
