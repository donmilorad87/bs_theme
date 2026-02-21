<?php

namespace BSCustom\Widgets;

use WP_Widget;
use BSCustom\Multilang\TranslationService;

class MenuWidget extends WP_Widget {

    const MAX_MENUS     = 100;
    const MAX_DEPTH     = 10;
    const DEFAULT_DEPTH = 1;

    public function __construct() {
        parent::__construct(
            'bs_menu',
            __( 'BS Custom: Menu', 'ct-custom' ),
            array(
                'description' => __( 'Displays a selected navigation menu.', 'ct-custom' ),
            )
        );
    }

    public function widget( $args, $instance ) {
        assert( is_array( $args ), 'Widget args must be an array' );
        assert( is_array( $instance ), 'Widget instance must be an array' );

        $nav_menu_id = isset( $instance['nav_menu'] ) ? absint( $instance['nav_menu'] ) : 0;
        $depth       = isset( $instance['depth'] ) ? absint( $instance['depth'] ) : self::DEFAULT_DEPTH;
        $depth       = max( 1, min( self::MAX_DEPTH, $depth ) );

        assert( $depth >= 1 && $depth <= self::MAX_DEPTH, 'depth must be between 1 and MAX_DEPTH' );

        if ( 0 === $nav_menu_id ) {
            return;
        }

        $nav_menu = wp_get_nav_menu_object( $nav_menu_id );

        if ( ! $nav_menu ) {
            return;
        }

        echo $args['before_widget'];

        if ( ! empty( $instance['title'] ) ) {
            echo $args['before_title'] . esc_html( TranslationService::resolve_raw( $instance['title'] ) ) . $args['after_title'];
        }

        wp_nav_menu( array(
            'menu'            => $nav_menu_id,
            'container'       => 'nav',
            'container_class' => 'widget-menu',
            'container_attr'  => 'aria-label="' . esc_attr( $nav_menu->name ) . '"',
            'depth'           => $depth,
            'fallback_cb'     => false,
        ) );

        echo $args['after_widget'];
    }

    public function form( $instance ) {
        assert( is_array( $instance ) || empty( $instance ), 'Instance must be an array or empty' );
        assert( is_object( $this ), 'Widget must be initialized' );

        $title       = isset( $instance['title'] ) ? $instance['title'] : '';
        $nav_menu_id = isset( $instance['nav_menu'] ) ? absint( $instance['nav_menu'] ) : 0;
        $depth       = isset( $instance['depth'] ) ? absint( $instance['depth'] ) : self::DEFAULT_DEPTH;
        $nav_menus   = wp_get_nav_menus();
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
            <label for="<?php echo esc_attr( $this->get_field_id( 'nav_menu' ) ); ?>">
                <?php esc_html_e( 'Select Menu:', 'ct-custom' ); ?>
            </label>
            <select
                class="widefat"
                id="<?php echo esc_attr( $this->get_field_id( 'nav_menu' ) ); ?>"
                name="<?php echo esc_attr( $this->get_field_name( 'nav_menu' ) ); ?>"
            >
                <option value="0"><?php esc_html_e( '&mdash; Select &mdash;', 'ct-custom' ); ?></option>
                <?php
                $menu_count = 0;
                foreach ( $nav_menus as $menu ) {
                    if ( $menu_count >= self::MAX_MENUS ) {
                        break;
                    }
                    $menu_count++;
                    ?>
                    <option
                        value="<?php echo esc_attr( $menu->term_id ); ?>"
                        <?php selected( $nav_menu_id, $menu->term_id ); ?>
                    >
                        <?php echo esc_html( $menu->name ); ?>
                    </option>
                    <?php
                }
                ?>
            </select>
        </p>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'depth' ) ); ?>">
                <?php esc_html_e( 'Menu Depth:', 'ct-custom' ); ?>
            </label>
            <select
                class="widefat"
                id="<?php echo esc_attr( $this->get_field_id( 'depth' ) ); ?>"
                name="<?php echo esc_attr( $this->get_field_name( 'depth' ) ); ?>"
            >
                <?php for ( $i = 1; $i <= self::MAX_DEPTH; $i++ ) : ?>
                    <option value="<?php echo esc_attr( $i ); ?>" <?php selected( $depth, $i ); ?>>
                        <?php echo esc_html( $i ); ?>
                    </option>
                <?php endfor; ?>
            </select>
        </p>
        <?php
    }

    public function update( $new_instance, $old_instance ) {
        assert( is_array( $new_instance ), 'New instance must be an array' );
        assert( is_array( $old_instance ), 'Old instance must be an array' );

        $instance             = array();
        $instance['title']    = sanitize_text_field( $new_instance['title'] );
        $instance['nav_menu'] = absint( $new_instance['nav_menu'] );
        $instance['depth']    = max( 1, min( self::MAX_DEPTH, absint( $new_instance['depth'] ) ) );

        assert( $instance['depth'] >= 1 && $instance['depth'] <= self::MAX_DEPTH, 'Saved depth must be valid' );

        return $instance;
    }
}
