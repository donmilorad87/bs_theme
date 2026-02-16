<?php

namespace BSCustom\Theme;

use BSCustom\Cpt\ContactMessageCpt;
use BSCustom\Multilang\LanguagePageManager;
use BSCustom\Multilang\Translator;

class ThemeSettings {

    public function admin_save_contact_pointers() {
        $this->verify_ajax_request( 'admin_save_contact_pointers_nonce' );

        $input = isset( $_POST['input'] ) ? wp_unslash( $_POST['input'] ) : '';

        assert( ! empty( $input ), 'Contact pointers input must not be empty' );

        $decoded = json_decode( $input, true );
        if ( ! is_array( $decoded ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid contact pointers data.', 'ct-custom' ) ) );
        }

        $sanitized   = array();
        $max_entries = ContactMessageCpt::MAX_POINTERS;
        $count       = 0;

        foreach ( $decoded as $entry ) {
            if ( $count >= $max_entries ) {
                break;
            }

            $slug  = isset( $entry['slug'] ) ? sanitize_title( $entry['slug'] ) : '';
            $label = isset( $entry['label'] ) ? sanitize_text_field( $entry['label'] ) : '';

            if ( empty( $slug ) || empty( $label ) ) {
                continue;
            }

            $emails     = array();
            $raw_emails = isset( $entry['emails'] ) && is_array( $entry['emails'] ) ? $entry['emails'] : array();
            $email_max  = ContactMessageCpt::MAX_EMAILS_PER;
            $email_count = 0;

            foreach ( $raw_emails as $email ) {
                if ( $email_count >= $email_max ) {
                    break;
                }
                $email_count++;

                $clean = sanitize_email( $email );
                if ( is_email( $clean ) ) {
                    $emails[] = $clean;
                }
            }

            $sanitized[] = array(
                'slug'   => $slug,
                'label'  => $label,
                'emails' => $emails,
            );

            $count++;
        }

        update_option( 'bs_custom_contact_pointers', wp_json_encode( $sanitized ) );

        wp_send_json_success( array( 'message' => __( 'Contact pointers saved.', 'ct-custom' ) ) );
    }

    public function admin_get_contact_messages_count() {
        $this->verify_ajax_request( 'admin_get_contact_messages_count_nonce' );

        $query = new \WP_Query( array(
            'post_type'      => ContactMessageCpt::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => array(
                array(
                    'key'   => '_ct_msg_is_read',
                    'value' => '0',
                ),
            ),
        ) );

        assert( $query instanceof \WP_Query, 'Query must be WP_Query instance' );

        wp_send_json_success( array( 'count' => (int) $query->found_posts ) );
    }

    public function admin_export_settings() {
        $this->verify_ajax_request( 'admin_export_settings_nonce' );

        $theme_mods    = $this->collect_theme_mods_with_defaults();
        $options       = $this->collect_export_options();
        $widgets       = $this->collect_export_widgets();
        $nav_menus     = $this->collect_export_nav_menus();
        $block_content = $this->collect_export_block_content();

        assert( is_array( $theme_mods ), 'Theme mods must be an array' );
        assert( is_array( $options ), 'Options must be an array' );

        $export = array(
            'version'       => '2.0.0',
            'theme'         => 'ct-custom',
            'timestamp'     => current_time( 'mysql' ),
            'theme_mods'    => $theme_mods,
            'options'       => $options,
            'widgets'       => $widgets,
            'nav_menus'     => $nav_menus,
            'block_content' => $block_content,
        );

        wp_send_json_success( $export );
    }

    public function admin_import_settings() {
        $this->verify_ajax_request( 'admin_import_settings_nonce' );

        $input = isset( $_POST['input'] ) ? wp_unslash( $_POST['input'] ) : '';

        assert( ! empty( $input ), 'Import input must not be empty' );

        if ( empty( $input ) ) {
            wp_send_json_error( array( 'message' => __( 'No import data provided.', 'ct-custom' ) ) );
        }

        $data = json_decode( $input, true );
        if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $data ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid JSON format.', 'ct-custom' ) ) );
        }

        if ( ! isset( $data['theme'] ) || 'ct-custom' !== $data['theme'] ) {
            wp_send_json_error( array( 'message' => __( 'This export file is not from the BS Custom theme.', 'ct-custom' ) ) );
        }

        $this->import_theme_mods( $data );
        $this->import_options( $data );
        $this->import_widgets( $data );
        $this->import_nav_menus( $data );
        $this->fixup_menu_widget_term_ids();
        $this->import_block_content( $data );

        wp_send_json_success( array( 'message' => __( 'Settings imported successfully.', 'ct-custom' ) ) );
    }

    /* ─── Export helpers ─── */

    private function collect_theme_mods_with_defaults() {
        $defaults   = $this->get_customizer_defaults();
        $theme_mods = array();
        $max_keys   = 300;
        $count      = 0;

        assert( is_array( $defaults ), 'Defaults must be an array' );
        assert( count( $defaults ) > 0, 'Defaults must not be empty' );

        foreach ( $defaults as $key => $default ) {
            if ( $count >= $max_keys ) {
                break;
            }
            $count++;

            $theme_mods[ $key ] = get_theme_mod( $key, $default );
        }

        /* WordPress core theme_mods */
        $theme_mods['custom_logo']       = get_theme_mod( 'custom_logo', 0 );
        $theme_mods['nav_menu_locations'] = get_theme_mod( 'nav_menu_locations', array() );

        return $theme_mods;
    }

    private function collect_export_options() {
        $option_keys = array(
            'bs_custom_contact_point',
            'bs_custom_social_networks',
            'bs_custom_contact_pointers',
            'bs_custom_email_config',
            'bs_custom_jwt_auth',
            'blogname',
            'blogdescription',
            'site_icon',
        );

        $options  = array();
        $max_keys = 20;
        $count    = 0;

        assert( is_array( $option_keys ), 'Option keys must be an array' );
        assert( count( $option_keys ) > 0, 'Option keys must not be empty' );

        foreach ( $option_keys as $key ) {
            if ( $count >= $max_keys ) {
                break;
            }
            $count++;

            $options[ $key ] = get_option( $key, '' );
        }

        return $options;
    }

    private function collect_export_widgets() {
        $sidebars_raw = get_option( 'sidebars_widgets', array() );

        assert( is_array( $sidebars_raw ), 'Sidebars widgets must be an array' );

        $theme_sidebars = array(
            'footer-column-1',
            'footer-column-2',
            'footer-column-3',
            'footer-column-4',
            'footer-column-5',
        );

        /* Add language-specific footer sidebars */
        if ( function_exists( 'ct_get_language_manager' ) ) {
            $lang_mgr  = ct_get_language_manager();
            $languages = $lang_mgr->get_enabled();
            $max_l     = 50;
            $l_count   = 0;
            foreach ( $languages as $lang ) {
                if ( $l_count >= $max_l ) { break; }
                $l_count++;
                for ( $col = 1; $col <= 5; $col++ ) {
                    $theme_sidebars[] = 'footer-column-' . $col . '-' . $lang['iso2'];
                }
            }
        }

        $sidebars = array();
        $max_sidebars = 260;
        $count = 0;

        foreach ( $theme_sidebars as $sidebar_id ) {
            if ( $count >= $max_sidebars ) {
                break;
            }
            $count++;

            if ( isset( $sidebars_raw[ $sidebar_id ] ) && is_array( $sidebars_raw[ $sidebar_id ] ) ) {
                $sidebars[ $sidebar_id ] = $sidebars_raw[ $sidebar_id ];
            }
        }

        $widget_types = $this->collect_widget_types_from_sidebars( $sidebars );
        $instances    = array();
        $max_types    = 20;
        $type_count   = 0;

        foreach ( $widget_types as $type ) {
            if ( $type_count >= $max_types ) {
                break;
            }
            $type_count++;

            $option_name = 'widget_' . $type;
            $data        = get_option( $option_name, array() );

            if ( ! is_array( $data ) ) {
                continue;
            }

            if ( 'ct_menu' === $type ) {
                $data = $this->enrich_menu_widgets_with_slugs( $data );
            }

            $instances[ $type ] = $data;
        }

        assert( is_array( $sidebars ), 'Sidebars result must be an array' );

        return array(
            'sidebars'  => $sidebars,
            'instances' => $instances,
        );
    }

    private function enrich_menu_widgets_with_slugs( $data ) {
        assert( is_array( $data ), 'Menu widget data must be an array' );

        $max_items = 50;
        $count     = 0;

        foreach ( $data as $key => $instance ) {
            if ( $count >= $max_items ) {
                break;
            }
            $count++;

            if ( ! is_array( $instance ) || ! isset( $instance['nav_menu'] ) ) {
                continue;
            }

            $term_id = absint( $instance['nav_menu'] );
            if ( $term_id < 1 ) {
                continue;
            }

            $menu_obj = wp_get_nav_menu_object( $term_id );
            if ( $menu_obj && ! is_wp_error( $menu_obj ) ) {
                $data[ $key ]['_ct_menu_slug'] = $menu_obj->slug;
            }
        }

        assert( is_array( $data ), 'Enriched data must be an array' );

        return $data;
    }

    private function collect_widget_types_from_sidebars( $sidebars ) {
        assert( is_array( $sidebars ), 'Sidebars must be an array' );

        $types      = array();
        $max_items  = 100;
        $item_count = 0;

        foreach ( $sidebars as $widgets ) {
            if ( ! is_array( $widgets ) ) {
                continue;
            }

            foreach ( $widgets as $widget_id ) {
                if ( $item_count >= $max_items ) {
                    break 2;
                }
                $item_count++;

                $type = $this->parse_widget_type( $widget_id );
                if ( '' !== $type && ! in_array( $type, $types, true ) ) {
                    $types[] = $type;
                }
            }
        }

        assert( is_array( $types ), 'Types must be an array' );

        return $types;
    }

    private function parse_widget_type( $widget_id ) {
        assert( is_string( $widget_id ), 'Widget ID must be a string' );
        assert( strlen( $widget_id ) < 200, 'Widget ID must be reasonably short' );

        $last_dash = strrpos( $widget_id, '-' );

        if ( false === $last_dash ) {
            return '';
        }

        return substr( $widget_id, 0, $last_dash );
    }

    private function collect_export_nav_menus() {
        $menus_raw = wp_get_nav_menus();

        assert( is_array( $menus_raw ), 'Nav menus must be an array' );

        $menus     = array();
        $max_menus = 20;
        $count     = 0;

        foreach ( $menus_raw as $menu ) {
            if ( $count >= $max_menus ) {
                break;
            }
            $count++;

            $items = $this->collect_nav_menu_items( $menu->term_id );

            $menus[] = array(
                'name'  => $menu->name,
                'slug'  => $menu->slug,
                'items' => $items,
            );
        }

        $locations = $this->build_menu_location_map();

        assert( is_array( $locations ), 'Locations must be an array' );

        return array(
            'menus'     => $menus,
            'locations' => $locations,
        );
    }

    private function build_menu_location_map() {
        $nav_locations = get_theme_mod( 'nav_menu_locations', array() );

        assert( is_array( $nav_locations ) || ! $nav_locations, 'Nav locations must be an array or falsy' );
        assert( ! is_array( $nav_locations ) || count( $nav_locations ) <= 100, 'Nav locations must be bounded' );

        if ( ! is_array( $nav_locations ) ) {
            return array();
        }

        $slug_map      = array();
        $max_locations = 20;
        $count         = 0;

        foreach ( $nav_locations as $location => $term_id ) {
            if ( $count >= $max_locations ) {
                break;
            }
            $count++;

            $term_id = absint( $term_id );
            if ( $term_id < 1 ) {
                continue;
            }

            $menu_obj = wp_get_nav_menu_object( $term_id );
            if ( ! $menu_obj || is_wp_error( $menu_obj ) ) {
                continue;
            }

            $slug = $menu_obj->slug;
            if ( ! isset( $slug_map[ $slug ] ) ) {
                $slug_map[ $slug ] = array();
            }
            $slug_map[ $slug ][] = sanitize_text_field( $location );
        }

        return $slug_map;
    }

    private function collect_nav_menu_items( $menu_term_id ) {
        assert( is_int( $menu_term_id ) && $menu_term_id > 0, 'Menu term ID must be a positive int' );

        $items_raw = wp_get_nav_menu_items( $menu_term_id, array( 'update_post_term_cache' => false ) );

        assert( is_array( $items_raw ) || false === $items_raw, 'Items must be array or false' );

        if ( ! is_array( $items_raw ) ) {
            return array();
        }

        $items        = array();
        $id_to_index  = array();
        $max_items    = 200;
        $count        = 0;

        foreach ( $items_raw as $idx => $item ) {
            if ( $count >= $max_items ) {
                break;
            }

            $id_to_index[ $item->ID ] = $count;
            $count++;
        }

        $count = 0;
        foreach ( $items_raw as $item ) {
            if ( $count >= $max_items ) {
                break;
            }
            $count++;

            $parent_id    = absint( $item->menu_item_parent );
            $parent_index = isset( $id_to_index[ $parent_id ] ) ? $id_to_index[ $parent_id ] : -1;

            $items[] = array(
                'title'        => $item->title,
                'url'          => $item->url,
                'type'         => $item->type,
                'object'       => $item->object,
                'parent_index' => $parent_index,
                'menu_order'   => $item->menu_order,
                'classes'      => is_array( $item->classes ) ? $item->classes : array(),
                'target'       => $item->target,
                'attr_title'   => $item->attr_title,
                'description'  => $item->description,
                'xfn'          => $item->xfn,
            );
        }

        return $items;
    }

    private function collect_export_block_content() {
        $posts = get_posts( array(
            'post_type'      => array( 'page', 'post' ),
            'post_status'    => 'publish',
            'posts_per_page' => 100,
        ) );

        assert( is_array( $posts ), 'Posts query must return an array' );

        $results   = array();
        $max_posts = 100;
        $count     = 0;

        foreach ( $posts as $post ) {
            if ( $count >= $max_posts ) {
                break;
            }
            $count++;

            if ( false === strpos( $post->post_content, 'wp:ct-custom/' ) ) {
                continue;
            }

            $results[] = array(
                'post_title'   => $post->post_title,
                'post_name'    => $post->post_name,
                'post_type'    => $post->post_type,
                'post_content' => $post->post_content,
            );
        }

        assert( is_array( $results ), 'Results must be an array' );

        return $results;
    }

    /* ─── Import helpers ─── */

    private function import_theme_mods( $data ) {
        if ( ! isset( $data['theme_mods'] ) || ! is_array( $data['theme_mods'] ) ) {
            return;
        }

        assert( is_array( $data['theme_mods'] ), 'Theme mods data must be an array' );

        $allowed_prefixes = array( 'BS_', 'custom_logo', 'nav_menu_locations' );
        $max_mods = 300;
        $count    = 0;

        foreach ( $data['theme_mods'] as $key => $value ) {
            if ( $count >= $max_mods ) {
                break;
            }
            $count++;

            if ( ! $this->is_allowed_theme_mod( $key, $allowed_prefixes ) ) {
                continue;
            }

            set_theme_mod( sanitize_text_field( $key ), $value );
        }
    }

    private function is_allowed_theme_mod( $key, $allowed_prefixes ) {
        assert( is_string( $key ), 'Key must be a string' );
        assert( is_array( $allowed_prefixes ), 'Prefixes must be an array' );

        $max_prefixes = 10;
        $count        = 0;

        foreach ( $allowed_prefixes as $prefix ) {
            if ( $count >= $max_prefixes ) {
                break;
            }
            $count++;

            if ( 0 === strpos( $key, $prefix ) ) {
                return true;
            }
        }

        return false;
    }

    private function import_options( $data ) {
        if ( ! isset( $data['options'] ) || ! is_array( $data['options'] ) ) {
            return;
        }

        assert( is_array( $data['options'] ), 'Options data must be an array' );

        $allowed_options = array(
            'bs_custom_contact_point',
            'bs_custom_social_networks',
            'bs_custom_contact_pointers',
            'bs_custom_email_config',
            'bs_custom_jwt_auth',
            'blogname',
            'blogdescription',
            'site_icon',
        );

        $max_opts = 20;
        $count    = 0;

        foreach ( $data['options'] as $key => $value ) {
            if ( $count >= $max_opts ) {
                break;
            }
            $count++;

            if ( in_array( $key, $allowed_options, true ) ) {
                update_option( sanitize_text_field( $key ), $value );
            }
        }
    }

    /* ─── Widget import ─── */

    private function import_widgets( $data ) {
        if ( ! isset( $data['widgets'] ) || ! is_array( $data['widgets'] ) ) {
            return;
        }

        assert( is_array( $data['widgets'] ), 'Widgets data must be an array' );

        $widgets = $data['widgets'];

        if ( isset( $widgets['instances'] ) && is_array( $widgets['instances'] ) ) {
            $this->import_widget_instances( $widgets['instances'] );
        }

        if ( isset( $widgets['sidebars'] ) && is_array( $widgets['sidebars'] ) ) {
            $this->import_widget_sidebars( $widgets['sidebars'] );
        }

        assert( true, 'Widget import completed' );
    }

    private function import_widget_instances( $instances ) {
        assert( is_array( $instances ), 'Instances must be an array' );

        $allowed_types = array(
            'ct_company_info',
            'ct_contact_point',
            'ct_social_icons',
            'ct_menu',
            'block',
        );

        $max_types = 20;
        $count     = 0;

        foreach ( $instances as $type => $data ) {
            if ( $count >= $max_types ) {
                break;
            }
            $count++;

            if ( ! in_array( $type, $allowed_types, true ) ) {
                continue;
            }

            if ( ! is_array( $data ) ) {
                continue;
            }

            $option_name = 'widget_' . sanitize_text_field( $type );
            update_option( $option_name, $data );
        }

        assert( $count <= $max_types, 'Instance import respected max bound' );
    }

    private function import_widget_sidebars( $sidebars ) {
        assert( is_array( $sidebars ), 'Sidebars must be an array' );

        $allowed_sidebars = array(
            'footer-column-1',
            'footer-column-2',
            'footer-column-3',
            'footer-column-4',
            'footer-column-5',
            'wp_inactive_widgets',
        );

        /* Add language-specific footer sidebars */
        if ( function_exists( 'ct_get_language_manager' ) ) {
            $lang_mgr  = ct_get_language_manager();
            $languages = $lang_mgr->get_enabled();
            $max_l     = 50;
            $l_count   = 0;
            foreach ( $languages as $lang ) {
                if ( $l_count >= $max_l ) { break; }
                $l_count++;
                for ( $col = 1; $col <= 5; $col++ ) {
                    $allowed_sidebars[] = 'footer-column-' . $col . '-' . $lang['iso2'];
                }
            }
        }

        $current   = get_option( 'sidebars_widgets', array() );
        $max_items = 260;
        $count     = 0;

        if ( ! is_array( $current ) ) {
            $current = array();
        }

        foreach ( $sidebars as $sidebar_id => $widget_ids ) {
            if ( $count >= $max_items ) {
                break;
            }
            $count++;

            if ( ! in_array( $sidebar_id, $allowed_sidebars, true ) ) {
                continue;
            }

            if ( ! is_array( $widget_ids ) ) {
                continue;
            }

            $current[ $sidebar_id ] = $this->sanitize_widget_id_list( $widget_ids );
        }

        assert( is_array( $current ), 'Current sidebars must be an array' );

        update_option( 'sidebars_widgets', $current );
    }

    private function sanitize_widget_id_list( $ids ) {
        assert( is_array( $ids ), 'IDs must be an array' );

        $clean     = array();
        $max_items = 50;
        $count     = 0;

        foreach ( $ids as $id ) {
            if ( $count >= $max_items ) {
                break;
            }
            $count++;

            if ( ! is_string( $id ) ) {
                continue;
            }

            if ( preg_match( '/^[a-z_]+-\d+$/i', $id ) ) {
                $clean[] = $id;
            }
        }

        assert( is_array( $clean ), 'Clean list must be an array' );

        return $clean;
    }

    /* ─── Nav menu import ─── */

    private function import_nav_menus( $data ) {
        if ( ! isset( $data['nav_menus'] ) || ! is_array( $data['nav_menus'] ) ) {
            return;
        }

        assert( is_array( $data['nav_menus'] ), 'Nav menus data must be an array' );

        $nav_menus = $data['nav_menus'];
        $slug_to_term_id = array();

        if ( isset( $nav_menus['menus'] ) && is_array( $nav_menus['menus'] ) ) {
            $slug_to_term_id = $this->import_nav_menu_terms( $nav_menus['menus'] );
        }

        if ( isset( $nav_menus['locations'] ) && is_array( $nav_menus['locations'] ) ) {
            $this->import_nav_menu_locations( $nav_menus['locations'], $slug_to_term_id );
        }

        assert( is_array( $slug_to_term_id ), 'Slug map must be an array' );
    }

    private function import_nav_menu_terms( $menus ) {
        assert( is_array( $menus ), 'Menus must be an array' );

        $slug_to_term_id = array();
        $max_menus       = 20;
        $count           = 0;

        foreach ( $menus as $menu_data ) {
            if ( $count >= $max_menus ) {
                break;
            }
            $count++;

            if ( ! is_array( $menu_data ) ) {
                continue;
            }

            $name = isset( $menu_data['name'] ) ? sanitize_text_field( $menu_data['name'] ) : '';
            $slug = isset( $menu_data['slug'] ) ? sanitize_title( $menu_data['slug'] ) : '';

            if ( '' === $name || '' === $slug ) {
                continue;
            }

            $existing = wp_get_nav_menu_object( $slug );

            if ( $existing && ! is_wp_error( $existing ) ) {
                $term_id = $existing->term_id;
                $this->delete_nav_menu_items( $term_id );
            } else {
                $result = wp_create_nav_menu( $name );
                if ( is_wp_error( $result ) ) {
                    continue;
                }
                $term_id = $result;
            }

            $slug_to_term_id[ $slug ] = absint( $term_id );

            $items = isset( $menu_data['items'] ) && is_array( $menu_data['items'] ) ? $menu_data['items'] : array();
            $this->import_nav_menu_items_for_menu( $term_id, $items );
        }

        assert( is_array( $slug_to_term_id ), 'Slug to term ID map must be an array' );

        return $slug_to_term_id;
    }

    private function delete_nav_menu_items( $menu_term_id ) {
        assert( is_int( $menu_term_id ) || $menu_term_id > 0, 'Term ID must be positive' );

        $existing_items = wp_get_nav_menu_items( $menu_term_id );

        assert( is_array( $existing_items ) || false === $existing_items, 'Items must be array or false' );

        if ( ! is_array( $existing_items ) ) {
            return;
        }

        $max_items = 200;
        $count     = 0;

        foreach ( $existing_items as $item ) {
            if ( $count >= $max_items ) {
                break;
            }
            $count++;

            wp_delete_post( $item->ID, true );
        }
    }

    private function import_nav_menu_items_for_menu( $menu_term_id, $items ) {
        assert( $menu_term_id > 0, 'Menu term ID must be positive' );
        assert( is_array( $items ), 'Items must be an array' );

        $index_to_post_id = array();
        $max_items        = 200;
        $count            = 0;

        foreach ( $items as $idx => $item_data ) {
            if ( $count >= $max_items ) {
                break;
            }
            $count++;

            if ( ! is_array( $item_data ) ) {
                continue;
            }

            $parent_index = isset( $item_data['parent_index'] ) ? intval( $item_data['parent_index'] ) : -1;
            $parent_id    = 0;

            if ( $parent_index >= 0 && isset( $index_to_post_id[ $parent_index ] ) ) {
                $parent_id = $index_to_post_id[ $parent_index ];
            }

            $post_id = $this->create_nav_menu_item( $menu_term_id, $item_data, $parent_id );

            if ( $post_id > 0 ) {
                $index_to_post_id[ $idx ] = $post_id;
            }
        }
    }

    private function create_nav_menu_item( $menu_term_id, $item_data, $parent_id ) {
        assert( $menu_term_id > 0, 'Menu term ID must be positive' );
        assert( is_array( $item_data ), 'Item data must be an array' );

        $title      = isset( $item_data['title'] ) ? sanitize_text_field( $item_data['title'] ) : '';
        $url        = isset( $item_data['url'] ) ? esc_url_raw( $item_data['url'] ) : '';
        $menu_order = isset( $item_data['menu_order'] ) ? absint( $item_data['menu_order'] ) : 0;
        $target     = isset( $item_data['target'] ) ? sanitize_text_field( $item_data['target'] ) : '';
        $attr_title = isset( $item_data['attr_title'] ) ? sanitize_text_field( $item_data['attr_title'] ) : '';
        $desc       = isset( $item_data['description'] ) ? sanitize_text_field( $item_data['description'] ) : '';
        $xfn        = isset( $item_data['xfn'] ) ? sanitize_text_field( $item_data['xfn'] ) : '';
        $classes    = isset( $item_data['classes'] ) && is_array( $item_data['classes'] ) ? $item_data['classes'] : array();

        $max_classes  = 20;
        $clean_classes = array();
        $cls_count    = 0;

        foreach ( $classes as $cls ) {
            if ( $cls_count >= $max_classes ) {
                break;
            }
            $cls_count++;
            $clean_classes[] = sanitize_html_class( $cls );
        }

        $result = wp_update_nav_menu_item( $menu_term_id, 0, array(
            'menu-item-title'       => $title,
            'menu-item-url'         => $url,
            'menu-item-type'        => 'custom',
            'menu-item-status'      => 'publish',
            'menu-item-parent-id'   => absint( $parent_id ),
            'menu-item-position'    => $menu_order,
            'menu-item-target'      => $target,
            'menu-item-attr-title'  => $attr_title,
            'menu-item-description' => $desc,
            'menu-item-xfn'         => $xfn,
            'menu-item-classes'     => implode( ' ', $clean_classes ),
        ) );

        if ( is_wp_error( $result ) ) {
            return 0;
        }

        return absint( $result );
    }

    private function import_nav_menu_locations( $locations, $slug_to_term_id ) {
        assert( is_array( $locations ), 'Locations must be an array' );
        assert( is_array( $slug_to_term_id ), 'Slug map must be an array' );

        $new_locations = array();
        $max_locations = 20;
        $count         = 0;

        foreach ( $locations as $menu_slug => $location_list ) {
            if ( $count >= $max_locations ) {
                break;
            }
            $count++;

            if ( ! is_array( $location_list ) ) {
                continue;
            }

            $term_id = isset( $slug_to_term_id[ $menu_slug ] ) ? $slug_to_term_id[ $menu_slug ] : 0;
            if ( $term_id < 1 ) {
                $menu_obj = wp_get_nav_menu_object( $menu_slug );
                if ( $menu_obj && ! is_wp_error( $menu_obj ) ) {
                    $term_id = $menu_obj->term_id;
                }
            }

            if ( $term_id < 1 ) {
                continue;
            }

            $max_locs = 10;
            $loc_count = 0;

            foreach ( $location_list as $location ) {
                if ( $loc_count >= $max_locs ) {
                    break;
                }
                $loc_count++;

                $new_locations[ sanitize_text_field( $location ) ] = absint( $term_id );
            }
        }

        set_theme_mod( 'nav_menu_locations', $new_locations );
    }

    private function fixup_menu_widget_term_ids() {
        $widget_data = get_option( 'widget_ct_menu', array() );

        assert( is_array( $widget_data ) || empty( $widget_data ), 'Widget data must be array or empty' );

        if ( ! is_array( $widget_data ) ) {
            return;
        }

        $changed   = false;
        $max_items = 50;
        $count     = 0;

        foreach ( $widget_data as $key => $instance ) {
            if ( $count >= $max_items ) {
                break;
            }
            $count++;

            if ( ! is_array( $instance ) ) {
                continue;
            }

            if ( ! isset( $instance['_ct_menu_slug'] ) ) {
                continue;
            }

            $slug     = sanitize_title( $instance['_ct_menu_slug'] );
            $menu_obj = wp_get_nav_menu_object( $slug );

            if ( $menu_obj && ! is_wp_error( $menu_obj ) ) {
                $widget_data[ $key ]['nav_menu'] = $menu_obj->term_id;
                $changed = true;
            }

            unset( $widget_data[ $key ]['_ct_menu_slug'] );
        }

        assert( is_array( $widget_data ), 'Widget data must remain an array' );

        if ( $changed ) {
            update_option( 'widget_ct_menu', $widget_data );
        }
    }

    /* ─── Block content import ─── */

    private function import_block_content( $data ) {
        if ( ! isset( $data['block_content'] ) || ! is_array( $data['block_content'] ) ) {
            return;
        }

        assert( is_array( $data['block_content'] ), 'Block content must be an array' );

        $max_pages = 100;
        $count     = 0;

        foreach ( $data['block_content'] as $page_data ) {
            if ( $count >= $max_pages ) {
                break;
            }
            $count++;

            if ( ! is_array( $page_data ) ) {
                continue;
            }

            $this->import_single_block_page( $page_data );
        }

        assert( $count >= 0, 'Block content import iterated' );
    }

    private function import_single_block_page( $page_data ) {
        assert( is_array( $page_data ), 'Page data must be an array' );

        $title   = isset( $page_data['post_title'] ) ? sanitize_text_field( $page_data['post_title'] ) : '';
        $slug    = isset( $page_data['post_name'] ) ? sanitize_title( $page_data['post_name'] ) : '';
        $type    = isset( $page_data['post_type'] ) ? sanitize_text_field( $page_data['post_type'] ) : 'page';
        $content = isset( $page_data['post_content'] ) ? $page_data['post_content'] : '';

        assert( '' !== $slug, 'Slug must not be empty' );

        if ( '' === $slug || '' === $title ) {
            return;
        }

        if ( false === strpos( $content, 'wp:ct-custom/' ) ) {
            return;
        }

        $allowed_types = array( 'page', 'post' );
        if ( ! in_array( $type, $allowed_types, true ) ) {
            $type = 'page';
        }

        $existing = get_page_by_path( $slug, OBJECT, $type );

        if ( $existing ) {
            $result = wp_update_post( array(
                'ID'           => $existing->ID,
                'post_title'   => $title,
                'post_content' => $content,
            ) );
            assert( 0 !== $result && ! is_wp_error( $result ), 'Post update must succeed' );
        } else {
            $result = wp_insert_post( array(
                'post_title'   => $title,
                'post_name'    => $slug,
                'post_type'    => $type,
                'post_content' => $content,
                'post_status'  => 'publish',
            ) );
            assert( 0 !== $result && ! is_wp_error( $result ), 'Post insert must succeed' );
        }
    }

    /* ═══ Email & JWT AJAX Handlers ═══ */

    public function admin_save_email_config() {
        $this->verify_ajax_request( 'admin_save_email_config_nonce' );

        $input = isset( $_POST['input'] ) ? wp_unslash( $_POST['input'] ) : '';

        assert( ! empty( $input ), 'Email config input must not be empty' );

        if ( empty( $input ) ) {
            wp_send_json_error( array( 'message' => __( 'Email config data is required.', 'ct-custom' ) ) );
        }

        $decoded = json_decode( $input, true );
        if ( ! is_array( $decoded ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid email config JSON.', 'ct-custom' ) ) );
        }

        $allowed_encryptions = array( 'tls', 'ssl', 'none' );
        $encryption = isset( $decoded['encryption'] ) ? sanitize_text_field( $decoded['encryption'] ) : 'tls';
        if ( ! in_array( $encryption, $allowed_encryptions, true ) ) {
            $encryption = 'tls';
        }

        $port = isset( $decoded['port'] ) ? (int) $decoded['port'] : 587;
        if ( $port < 1 || $port > 65535 ) {
            $port = 587;
        }

        $existing = json_decode( get_option( 'bs_custom_email_config', '{}' ), true );
        $new_pass = isset( $decoded['password'] ) ? sanitize_text_field( $decoded['password'] ) : '';
        $password = ( '' !== $new_pass )
            ? $new_pass
            : ( isset( $existing['password'] ) ? $existing['password'] : '' );

        $sanitized = array(
            'host'       => isset( $decoded['host'] ) ? sanitize_text_field( $decoded['host'] ) : '',
            'port'       => $port,
            'username'   => isset( $decoded['username'] ) ? sanitize_text_field( $decoded['username'] ) : '',
            'password'   => $password,
            'encryption' => $encryption,
            'from_email' => isset( $decoded['from_email'] ) ? sanitize_email( $decoded['from_email'] ) : '',
            'from_name'  => isset( $decoded['from_name'] ) ? sanitize_text_field( $decoded['from_name'] ) : '',
        );

        assert( is_array( $sanitized ), 'Sanitized email config must be an array' );
        assert( count( $sanitized ) === 7, 'Email config must have 7 fields' );

        update_option( 'bs_custom_email_config', wp_json_encode( $sanitized ) );

        wp_send_json_success( array( 'message' => __( 'Email configuration saved.', 'ct-custom' ) ) );
    }

    public function admin_save_jwt_auth() {
        $this->verify_ajax_request( 'admin_save_jwt_auth_nonce' );

        $input = isset( $_POST['input'] ) ? wp_unslash( $_POST['input'] ) : '';

        assert( ! empty( $input ), 'JWT auth input must not be empty' );

        if ( empty( $input ) ) {
            wp_send_json_error( array( 'message' => __( 'JWT auth data is required.', 'ct-custom' ) ) );
        }

        $decoded = json_decode( $input, true );
        if ( ! is_array( $decoded ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid JWT auth JSON.', 'ct-custom' ) ) );
        }

        $expiration_hours = isset( $decoded['expiration_hours'] ) ? (int) $decoded['expiration_hours'] : 24;
        $expiration_hours = max( 1, min( 8760, $expiration_hours ) );

        $sanitized = array(
            'secret'           => isset( $decoded['secret'] ) ? sanitize_text_field( $decoded['secret'] ) : '',
            'expiration_hours' => $expiration_hours,
        );

        assert( is_array( $sanitized ), 'Sanitized JWT auth must be an array' );
        assert( count( $sanitized ) === 2, 'JWT auth must have 2 fields' );

        update_option( 'bs_custom_jwt_auth', wp_json_encode( $sanitized ) );

        wp_send_json_success( array( 'message' => __( 'JWT auth settings saved.', 'ct-custom' ) ) );
    }

    /**
     * One-time migration: copy email/JWT theme_mods to options.
     */
    public function maybe_migrate_email_jwt_to_options() {
        if ( get_option( 'ct_email_jwt_migrated' ) ) {
            return;
        }

        $email_config_defaults  = $this->get_email_config_defaults();
        $email_template_defaults = $this->get_email_template_defaults();
        $jwt_auth_defaults      = $this->get_jwt_auth_defaults();

        /* Build email config option from theme_mods */
        $email_config = array(
            'host'       => get_theme_mod( 'ct_smtp_host', $email_config_defaults['ct_smtp_host'] ),
            'port'       => (int) get_theme_mod( 'ct_smtp_port', $email_config_defaults['ct_smtp_port'] ),
            'username'   => get_theme_mod( 'ct_smtp_username', $email_config_defaults['ct_smtp_username'] ),
            'password'   => get_theme_mod( 'ct_smtp_password', $email_config_defaults['ct_smtp_password'] ),
            'encryption' => get_theme_mod( 'ct_smtp_encryption', $email_config_defaults['ct_smtp_encryption'] ),
            'from_email' => get_theme_mod( 'ct_smtp_from_email', $email_config_defaults['ct_smtp_from_email'] ),
            'from_name'  => get_theme_mod( 'ct_smtp_from_name', $email_config_defaults['ct_smtp_from_name'] ),
        );

        /* Build email template option from theme_mods */
        $email_template = array(
            'title_font_size'   => (int) get_theme_mod( 'ct_email_title_font_size', $email_template_defaults['ct_email_title_font_size'] ),
            'title_color'       => get_theme_mod( 'ct_email_title_color', $email_template_defaults['ct_email_title_color'] ),
            'title_color_dark'  => get_theme_mod( 'ct_email_title_color_dark', $email_template_defaults['ct_email_title_color_dark'] ),
            'title_bold'        => (bool) get_theme_mod( 'ct_email_title_bold', $email_template_defaults['ct_email_title_bold'] ),
            'title_transform'   => get_theme_mod( 'ct_email_title_transform', $email_template_defaults['ct_email_title_transform'] ),
            'text_font_size'    => (int) get_theme_mod( 'ct_email_text_font_size', $email_template_defaults['ct_email_text_font_size'] ),
            'text_color'        => get_theme_mod( 'ct_email_text_color', $email_template_defaults['ct_email_text_color'] ),
            'text_color_dark'   => get_theme_mod( 'ct_email_text_color_dark', $email_template_defaults['ct_email_text_color_dark'] ),
            'text_line_height'  => (float) get_theme_mod( 'ct_email_text_line_height', $email_template_defaults['ct_email_text_line_height'] ),
            'border_color'      => get_theme_mod( 'ct_email_border_color', $email_template_defaults['ct_email_border_color'] ),
            'border_color_dark' => get_theme_mod( 'ct_email_border_color_dark', $email_template_defaults['ct_email_border_color_dark'] ),
            'bg_color'          => get_theme_mod( 'ct_email_bg_color', $email_template_defaults['ct_email_bg_color'] ),
            'bg_color_dark'     => get_theme_mod( 'ct_email_bg_color_dark', $email_template_defaults['ct_email_bg_color_dark'] ),
            'accent_color'      => get_theme_mod( 'ct_email_accent_color', $email_template_defaults['ct_email_accent_color'] ),
            'accent_color_dark' => get_theme_mod( 'ct_email_accent_color_dark', $email_template_defaults['ct_email_accent_color_dark'] ),
        );

        /* Build JWT auth option from theme_mods */
        $jwt_auth = array(
            'secret'           => get_theme_mod( 'ct_jwt_secret', $jwt_auth_defaults['ct_jwt_secret'] ),
            'expiration_hours' => (int) get_theme_mod( 'ct_jwt_expiration_hours', $jwt_auth_defaults['ct_jwt_expiration_hours'] ),
        );

        update_option( 'bs_custom_email_config', wp_json_encode( $email_config ) );
        update_option( 'bs_custom_email_template', wp_json_encode( $email_template ) );
        update_option( 'bs_custom_jwt_auth', wp_json_encode( $jwt_auth ) );
        update_option( 'ct_email_jwt_migrated', '1' );
    }

    /**
     * One-time migration: copy email template option values to theme_mods.
     */
    public function maybe_migrate_email_template_to_theme_mods() {
        if ( get_option( 'ct_email_template_to_mods_migrated' ) ) {
            return;
        }

        $raw    = get_option( 'bs_custom_email_template', '{}' );
        $config = json_decode( $raw, true );

        if ( is_array( $config ) ) {
            $key_map = array(
                'title_font_size'   => 'ct_email_title_font_size',
                'title_color'       => 'ct_email_title_color',
                'title_color_dark'  => 'ct_email_title_color_dark',
                'title_bold'        => 'ct_email_title_bold',
                'title_transform'   => 'ct_email_title_transform',
                'text_font_size'    => 'ct_email_text_font_size',
                'text_color'        => 'ct_email_text_color',
                'text_color_dark'   => 'ct_email_text_color_dark',
                'text_line_height'  => 'ct_email_text_line_height',
                'border_color'      => 'ct_email_border_color',
                'border_color_dark' => 'ct_email_border_color_dark',
                'bg_color'          => 'ct_email_bg_color',
                'bg_color_dark'     => 'ct_email_bg_color_dark',
                'accent_color'      => 'ct_email_accent_color',
                'accent_color_dark' => 'ct_email_accent_color_dark',
            );

            $max_keys = 15;
            $count    = 0;

            foreach ( $key_map as $option_key => $mod_key ) {
                if ( $count >= $max_keys ) {
                    break;
                }
                $count++;

                if ( isset( $config[ $option_key ] ) ) {
                    set_theme_mod( $mod_key, $config[ $option_key ] );
                }
            }
        }

        update_option( 'ct_email_template_to_mods_migrated', '1' );
    }

    /* ─── Customizer defaults map ─── */

    private function get_customizer_defaults() {
        return array_merge(
            $this->get_topbar_defaults(),
            $this->get_header_defaults(),
            $this->get_menu_defaults(),
            $this->get_mobile_menu_defaults(),
            $this->get_breadcrumb_defaults(),
            $this->get_body_defaults(),
            $this->get_footer_defaults(),
            $this->get_form_defaults(),
            $this->get_social_defaults(),
            $this->get_back_to_top_defaults(),
            $this->get_pages_defaults(),
            $this->get_typography_defaults(),
            $this->get_site_identity_defaults(),
            $this->get_email_template_defaults()
        );
    }

    private function get_topbar_defaults() {
        return array(
            'ct_topbar_bg_color'                => '#FF6B35',
            'ct_topbar_bg_color_dark'           => '#D45A2B',
            'ct_topbar_text1_content'           => 'CALL US NOW!',
            'ct_topbar_text1_size'              => 14,
            'ct_topbar_text1_color'             => '#FFFFFF',
            'ct_topbar_text1_color_dark'        => '#FFFFFF',
            'ct_topbar_text1_bold'              => true,
            'ct_topbar_text1_italic'            => false,
            'ct_topbar_text1_uppercase'         => false,
            'ct_topbar_text1_margin_left'       => 0,
            'ct_topbar_text1_margin_right'      => 10,
            'ct_topbar_text1_margin_top'        => 0,
            'ct_topbar_text2_content'           => '',
            'ct_topbar_text2_size'              => 14,
            'ct_topbar_text2_color'             => '#FFFFFF',
            'ct_topbar_text2_color_dark'        => '#FFFFFF',
            'ct_topbar_text2_bold'              => false,
            'ct_topbar_text2_italic'            => false,
            'ct_topbar_text2_uppercase'         => false,
            'ct_topbar_text2_margin_left'       => 0,
            'ct_topbar_text2_margin_right'      => 0,
            'ct_topbar_text2_margin_top'        => 0,
            'ct_topbar_links_color'             => '#FFFFFF',
            'ct_topbar_links_color_dark'        => '#FFFFFF',
            'ct_topbar_links_size'              => 14,
            'ct_topbar_links_hover_color'       => '#CCCCCC',
            'ct_topbar_links_hover_color_dark'  => '#FFB088',
            'ct_topbar_links_bold'              => true,
            'ct_topbar_links_italic'            => false,
            'ct_topbar_links_uppercase'         => true,
            'ct_topbar_links_margin_left'       => 0,
            'ct_topbar_links_margin_right'      => 0,
            'ct_topbar_links_margin_top'        => 0,
        );
    }

    private function get_header_defaults() {
        return array(
            'ct_header_bg_color'            => '#FFFFFF',
            'ct_header_bg_color_dark'       => '#1A1A2E',
            'ct_header_border_color'        => '#EEEEEE',
            'ct_header_border_color_dark'   => '#2A2A3E',
            'ct_site_title_color'           => '#333333',
            'ct_site_title_color_dark'      => '#E0E0E0',
            'ct_header_logo_width'          => 200,
            'ct_header_logo_margin_left'    => 0,
            'ct_header_logo_margin_right'   => 0,
            'ct_header_logo_margin_top'     => 10,
            'ct_header_logo_margin_bottom'  => 10,
        );
    }

    private function get_menu_defaults() {
        return array(
            /* Top level */
            'ct_menu_top_font_size'                  => 14,
            'ct_menu_top_color'                      => '#333333',
            'ct_menu_top_color_dark'                 => '#E0E0E0',
            'ct_menu_top_bold'                       => false,
            'ct_menu_top_italic'                     => false,
            'ct_menu_top_uppercase'                  => true,
            'ct_menu_top_margin_left'                => 10,
            'ct_menu_top_margin_right'               => 10,
            'ct_menu_top_margin_top'                 => 0,
            'ct_menu_active_underline_color'         => '#FF6B35',
            'ct_menu_active_underline_color_dark'    => '#FF8C5A',
            /* Submenus */
            'ct_menu_sub_font_size'                  => 13,
            'ct_menu_sub_color'                      => '#333333',
            'ct_menu_sub_color_dark'                 => '#E0E0E0',
            'ct_menu_sub_bold'                       => false,
            'ct_menu_sub_italic'                     => false,
            'ct_menu_sub_uppercase'                  => true,
            'ct_menu_sub_margin_left'                => 0,
            'ct_menu_sub_margin_right'               => 0,
            'ct_menu_sub_margin_top'                 => 0,
            'ct_menu_sub_border_color'               => '#CCCCCC',
            'ct_menu_sub_border_color_dark'          => '#3A3A4E',
            'ct_menu_sub_border_width'               => 1,
            'ct_menu_sub_border_style'               => 'solid',
            'ct_menu_sub_bg_color'                   => '#FFFFFF',
            'ct_menu_sub_bg_color_dark'              => '#242438',
            'ct_menu_sub_hover_bg_color'             => '#F7F7F7',
            'ct_menu_sub_hover_bg_color_dark'        => '#2E2E44',
        );
    }

    private function get_mobile_menu_defaults() {
        return array(
            'ct_mobile_menu_bg_color'           => '#FFFFFF',
            'ct_mobile_menu_bg_color_dark'      => '#1A1A2E',
            'ct_mobile_menu_border_color'       => '#EEEEEE',
            'ct_mobile_menu_border_color_dark'  => '#2A2A3E',
            'ct_mobile_menu_border_width'       => 1,
        );
    }

    private function get_breadcrumb_defaults() {
        return array(
            'ct_breadcrumb_font_size'            => 14,
            'ct_breadcrumb_transform'            => 'none',
            'ct_breadcrumb_color'                => '#999999',
            'ct_breadcrumb_color_dark'           => '#888888',
            'ct_breadcrumb_active_color'         => '#333333',
            'ct_breadcrumb_active_color_dark'    => '#E0E0E0',
            'ct_breadcrumb_active_bold'          => true,
            'ct_breadcrumb_active_underline'     => false,
        );
    }

    private function get_body_defaults() {
        return array(
            'ct_body_bg_color'       => '#FFFFFF',
            'ct_body_bg_color_dark'  => '#16162A',
        );
    }

    private function get_footer_defaults() {
        return array(
            'ct_footer_bg_color'              => '#333333',
            'ct_footer_bg_color_dark'         => '#0D0D1A',
            'ct_footer_text_color'            => '#999999',
            'ct_footer_text_color_dark'       => '#888888',
            'ct_footer_link_color'            => '#CCCCCC',
            'ct_footer_link_color_dark'       => '#BBBBBB',
            'ct_footer_link_hover_color'      => '#FFFFFF',
            'ct_footer_link_hover_color_dark' => '#FFFFFF',
            'ct_footer_columns'               => 3,
        );
    }

    private function get_form_defaults() {
        return array(
            'ct_form_input_bg_color'           => '#FFFFFF',
            'ct_form_input_bg_color_dark'      => '#1E1E32',
            'ct_form_input_border_color'       => '#DDDDDD',
            'ct_form_input_border_color_dark'  => '#3A3A4E',
            'ct_form_submit_hover_color'       => '#E55A28',
            'ct_form_submit_hover_color_dark'  => '#C44A1E',
        );
    }

    private function get_social_defaults() {
        return array(
            'ct_social_bg_color'       => '#888888',
            'ct_social_bg_color_dark'  => '#555566',
            'ct_social_icon_width'     => 36,
            'ct_social_icon_height'    => 36,
            'ct_social_share_enabled'  => true,
        );
    }

    private function get_back_to_top_defaults() {
        return array(
            'ct_back_to_top_enabled'           => true,
            'ct_back_to_top_label'             => '',
            'ct_back_to_top_icon'              => 0,
            'ct_back_to_top_bg_color'          => '#FF6B35',
            'ct_back_to_top_bg_color_dark'     => '#D45A2B',
            'ct_back_to_top_border_color'      => '#E5E5E5',
            'ct_back_to_top_border_color_dark' => '#333333',
            'ct_back_to_top_border_width'      => 1,
            'ct_back_to_top_border_radius'     => 8,
            'ct_back_to_top_position'          => 'right',
        );
    }

    private function get_pages_defaults() {
        $defaults = array(
            /* Homepage */
            'ct_hero_title'          => '',
            'ct_hero_description'    => '',
            'ct_section2_title'      => '',
            'ct_section2_description' => '',
            /* Contact */
            'ct_contact_heading'     => 'Contact',
            'ct_contact_content'     => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Aliquam posuere ipsum nec velit mattis elementum. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Maecenas eu placerat metus, eget placerat libero.',
        );

        /* Homepage hero images (4 images with alt + title each) */
        $max_images = 4;
        for ( $i = 1; $i <= $max_images; $i++ ) {
            $defaults[ 'ct_hero_image_' . $i ]            = 0;
            $defaults[ 'ct_hero_image_' . $i . '_alt' ]   = '';
            $defaults[ 'ct_hero_image_' . $i . '_title' ] = '';
            $defaults[ 'ct_hero_image_' . $i . '_url' ]   = '';
        }

        return $defaults;
    }

    private function get_typography_defaults() {
        $defaults = array();

        /* H1-H5 headings: [size, light_color, dark_color, bold, italic, transform] */
        $headings = array(
            'ct_h1' => array( 36, '#FF6B35', '#FF8C5A', true, false, 'uppercase' ),
            'ct_h2' => array( 30, '#FF6B35', '#FF8C5A', true, false, 'uppercase' ),
            'ct_h3' => array( 24, '#FF6B35', '#FF8C5A', true, false, 'none' ),
            'ct_h4' => array( 20, '#333333', '#D0D0D0', true, false, 'none' ),
            'ct_h5' => array( 18, '#333333', '#D0D0D0', true, false, 'none' ),
        );

        $max_headings = 5;
        $count        = 0;

        foreach ( $headings as $prefix => $values ) {
            if ( $count >= $max_headings ) {
                break;
            }
            $count++;

            $defaults[ $prefix . '_font_size' ]  = $values[0];
            $defaults[ $prefix . '_color' ]      = $values[1];
            $defaults[ $prefix . '_color_dark' ] = $values[2];
            $defaults[ $prefix . '_bold' ]       = $values[3];
            $defaults[ $prefix . '_italic' ]     = $values[4];
            $defaults[ $prefix . '_transform' ]  = $values[5];
        }

        /* Paragraphs */
        $defaults['ct_paragraph_font_size']     = 16;
        $defaults['ct_paragraph_color']         = '#666666';
        $defaults['ct_paragraph_color_dark']    = '#B0B0B0';
        $defaults['ct_paragraph_bold']          = false;
        $defaults['ct_paragraph_italic']        = false;
        $defaults['ct_paragraph_transform']     = 'none';
        $defaults['ct_paragraph_line_height']   = 1.6;
        $defaults['ct_paragraph_margin_top']    = 0;
        $defaults['ct_paragraph_margin_right']  = 0;
        $defaults['ct_paragraph_margin_bottom'] = 16;
        $defaults['ct_paragraph_margin_left']   = 0;

        /* Special text */
        $defaults['ct_special_font_size']       = 16;
        $defaults['ct_special_color']           = '#333333';
        $defaults['ct_special_color_dark']      = '#D0D0D0';
        $defaults['ct_special_bold']            = true;
        $defaults['ct_special_italic']          = false;
        $defaults['ct_special_transform']       = 'none';

        return $defaults;
    }

    private function get_email_config_defaults() {
        return array(
            'ct_smtp_host'       => '',
            'ct_smtp_port'       => 587,
            'ct_smtp_username'   => '',
            'ct_smtp_password'   => '',
            'ct_smtp_encryption' => 'tls',
            'ct_smtp_from_email' => '',
            'ct_smtp_from_name'  => '',
        );
    }

    private function get_email_template_defaults() {
        return array(
            'ct_email_title_font_size'      => 24,
            'ct_email_title_color'          => '#333333',
            'ct_email_title_color_dark'     => '#E0E0E0',
            'ct_email_title_bold'           => true,
            'ct_email_title_transform'      => 'none',
            'ct_email_text_font_size'       => 15,
            'ct_email_text_color'           => '#555555',
            'ct_email_text_color_dark'      => '#B0B0B0',
            'ct_email_text_line_height'     => 1.6,
            'ct_email_border_color'         => '#E5E5E5',
            'ct_email_border_color_dark'    => '#333333',
            'ct_email_bg_color'             => '#FFFFFF',
            'ct_email_bg_color_dark'        => '#1A1A2E',
            'ct_email_accent_color'         => '#FF6B35',
            'ct_email_accent_color_dark'    => '#FF8C5A',
        );
    }

    private function get_jwt_auth_defaults() {
        return array(
            'ct_jwt_secret'           => '',
            'ct_jwt_expiration_hours' => 24,
        );
    }

    private function get_site_identity_defaults() {
        return array(
            'ct_site_description'  => '',
            'ct_footer_copyright'  => '© {year} Coalition Test — Theme by Blazing Sun',
        );
    }

    /* ═══ Language AJAX Handlers ═══ */

    public function admin_save_languages() {
        $this->verify_ajax_request( 'ct_lang_nonce' );

        $input = isset( $_POST['input'] ) ? wp_unslash( $_POST['input'] ) : '';
        $decoded = json_decode( $input, true );

        if ( ! is_array( $decoded ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid languages data.', 'ct-custom' ), 'type' => 'error' ) );
        }

        $mgr  = ct_get_language_manager();
        $path = $mgr->get_file_path();
        $dir  = dirname( $path );

        if ( ! is_dir( $dir ) ) {
            wp_mkdir_p( $dir );
        }

        $json = wp_json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
        file_put_contents( $path, $json, LOCK_EX );

        wp_send_json_success( array( 'message' => __( 'Languages saved.', 'ct-custom' ), 'type' => 'success' ) );
    }

    public function admin_add_language() {
        $this->verify_ajax_request( 'ct_lang_nonce' );

        $input   = isset( $_POST['input'] ) ? wp_unslash( $_POST['input'] ) : '';
        $decoded = json_decode( $input, true );

        if ( ! is_array( $decoded ) || empty( $decoded['iso2'] ) || empty( $decoded['native_name'] ) ) {
            wp_send_json_error( array( 'message' => __( 'Missing required fields (iso2, native_name).', 'ct-custom' ), 'type' => 'error' ) );
        }

        $mgr = ct_get_language_manager();

        $lang_data = array(
            'iso2'        => sanitize_text_field( $decoded['iso2'] ),
            'iso3'        => isset( $decoded['iso3'] ) ? sanitize_text_field( $decoded['iso3'] ) : '',
            'native_name' => sanitize_text_field( $decoded['native_name'] ),
            'flag'        => isset( $decoded['flag'] ) ? esc_url_raw( $decoded['flag'] ) : '',
            'locales'     => array(),
        );

        if ( ! empty( $decoded['locale'] ) ) {
            $lang_data['locales'] = array( sanitize_text_field( $decoded['locale'] ) );
        }

        $result = $mgr->add( $lang_data );

        if ( ! $result ) {
            wp_send_json_error( array( 'message' => __( 'Failed to add language. It may already exist or the limit is reached.', 'ct-custom' ), 'type' => 'error' ) );
        }

        /* Create translation JSON file seeded from the default language */
        $trans_dir  = get_template_directory() . '/translations';
        $trans_file = $trans_dir . '/' . $lang_data['iso2'] . '.json';

        if ( ! file_exists( $trans_file ) ) {
            $default_lang = $mgr->get_default();
            $seed_content = new \stdClass();

            if ( null !== $default_lang ) {
                $default_file = $trans_dir . '/' . $default_lang['iso2'] . '.json';

                if ( file_exists( $default_file ) ) {
                    $default_data = json_decode( file_get_contents( $default_file ), true );

                    if ( is_array( $default_data ) ) {
                        $seed_content = $default_data;
                    }
                }
            }

            file_put_contents( $trans_file, wp_json_encode( $seed_content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ), LOCK_EX );
        }

        /* Duplicate pages for new language */
        $page_mgr = new LanguagePageManager();
        $id_map   = $page_mgr->duplicate_pages_for_language( $lang_data['iso2'] );
        $dup_count = count( $id_map );

        /* Duplicate menus for new language, remapping items to duplicated pages */
        $menu_count = $page_mgr->duplicate_menus_for_language( $lang_data['iso2'], $id_map );

        /* Clone widget instances to new language sidebars */
        $widget_count = $page_mgr->duplicate_widget_areas_for_language( $lang_data['iso2'] );
        update_option( 'bs_custom_widgets_cloned_' . $lang_data['iso2'], true );

        $new_lang = $mgr->get_by_iso2( $lang_data['iso2'] );

        wp_send_json_success( array(
            'message'    => sprintf( __( 'Language added. %d pages duplicated, %d menus created, %d widget areas configured.', 'ct-custom' ), $dup_count, $menu_count, $widget_count ),
            'languages'  => $mgr->get_all(),
            'language'   => $new_lang,
            'duplicated' => $dup_count,
            'type'       => 'success',
        ) );
    }

    public function admin_remove_language() {
        $this->verify_ajax_request( 'ct_lang_nonce' );

        $iso2 = isset( $_POST['iso2'] ) ? sanitize_text_field( wp_unslash( $_POST['iso2'] ) ) : '';

        if ( '' === $iso2 ) {
            wp_send_json_error( array(
                'message' => __( 'Language code is required.', 'ct-custom' ),
                'type'    => 'error',
            ) );
        }

        $mgr  = ct_get_language_manager();
        $lang = $mgr->get_by_iso2( $iso2 );

        if ( null === $lang ) {
            wp_send_json_error( array(
                'message' => sprintf( __( 'Language not found: %s', 'ct-custom' ), $iso2 ),
                'type'    => 'error',
            ) );
        }

        if ( ! empty( $lang['is_default'] ) ) {
            wp_send_json_error( array(
                'message' => __( 'Cannot remove the default language.', 'ct-custom' ),
                'type'    => 'warning',
            ) );
        }

        $force_delete   = isset( $_POST['force_delete'] ) && 'true' === $_POST['force_delete'];
        $remove_menus   = ! isset( $_POST['remove_menus'] ) || 'true' === $_POST['remove_menus'];
        $remove_widgets = ! isset( $_POST['remove_widgets'] ) || 'true' === $_POST['remove_widgets'];

        /*
         * Transaction: remove language → remove pages → remove menus → clean widgets → delete JSON.
         * If page removal fails, rollback by re-adding the language.
         */

        /* Step 1: Remove language from languages.json */
        $lang_snapshot = $lang;
        $remove_result = $mgr->remove( $iso2 );

        if ( ! $remove_result ) {
            wp_send_json_error( array(
                'message' => __( 'Failed to write languages file. Check file permissions on: translations/languages.json', 'ct-custom' ),
                'type'    => 'error',
            ) );
        }

        /* Step 2: Remove pages */
        $page_mgr    = new LanguagePageManager();
        $page_count  = $this->count_language_pages( $iso2 );
        $removed     = $page_mgr->remove_language_pages( $iso2, $force_delete );

        if ( $page_count > 0 && 0 === $removed ) {
            /* Page removal failed entirely — rollback language */
            $mgr->add( $lang_snapshot );
            wp_send_json_error( array(
                'message' => __( 'Failed to remove pages. Language removal rolled back.', 'ct-custom' ),
                'type'    => 'error',
            ) );
        }

        /* Step 3: Remove menus if requested */
        $menus_removed = 0;

        if ( $remove_menus ) {
            $menus_removed = $page_mgr->remove_language_menus( $iso2 );
        }

        /* Step 4: Clean widget instances and areas if requested */
        $widgets_cleared = 0;

        if ( $remove_widgets ) {
            $page_mgr->remove_language_widget_instances( $iso2 );
            $widgets_cleared = $page_mgr->remove_language_widget_areas( $iso2 );
            delete_option( 'bs_custom_widgets_cloned_' . $iso2 );
        }

        /* Step 5: Delete the translation JSON file */
        $trans_file = get_template_directory() . '/translations/' . $iso2 . '.json';

        if ( file_exists( $trans_file ) && is_writable( $trans_file ) ) {
            unlink( $trans_file );
        }

        /* Build response message */
        $parts = array();

        if ( $force_delete && $removed > 0 ) {
            $parts[] = sprintf( __( '%d pages permanently deleted', 'ct-custom' ), $removed );
        } elseif ( $removed > 0 ) {
            $parts[] = sprintf( __( '%d pages moved to trash', 'ct-custom' ), $removed );
        }

        if ( $menus_removed > 0 ) {
            $parts[] = sprintf( __( '%d menus removed', 'ct-custom' ), $menus_removed );
        }

        if ( $widgets_cleared > 0 ) {
            $parts[] = sprintf( __( '%d widget areas cleared', 'ct-custom' ), $widgets_cleared );
        }

        $msg = __( 'Language removed.', 'ct-custom' );

        if ( ! empty( $parts ) ) {
            $msg .= ' ' . implode( ', ', $parts ) . '.';
        }

        wp_send_json_success( array(
            'message'   => $msg,
            'languages' => $mgr->get_all(),
            'type'      => 'success',
        ) );
    }

    public function admin_set_default_language() {
        $this->verify_ajax_request( 'ct_lang_nonce' );

        $iso2 = isset( $_POST['iso2'] ) ? sanitize_text_field( wp_unslash( $_POST['iso2'] ) ) : '';

        if ( '' === $iso2 ) {
            wp_send_json_error( array( 'message' => __( 'Language code is required.', 'ct-custom' ), 'type' => 'error' ) );
        }

        $mgr    = ct_get_language_manager();
        $result = $mgr->set_default( $iso2 );

        if ( ! $result ) {
            wp_send_json_error( array( 'message' => __( 'Failed to set default language.', 'ct-custom' ), 'type' => 'error' ) );
        }

        /* Switch page_on_front to the new default language's homepage */
        $front_page_id = (int) get_option( 'page_on_front' );

        if ( $front_page_id > 0 ) {
            $page_mgr    = new LanguagePageManager();
            $new_front   = $page_mgr->get_page_for_language( $front_page_id, $iso2 );

            if ( null !== $new_front && $new_front !== $front_page_id ) {
                update_option( 'page_on_front', $new_front );
            }
        }

        wp_send_json_success( array(
            'message'   => __( 'Default language updated.', 'ct-custom' ),
            'languages' => $mgr->get_all(),
            'type'      => 'success',
        ) );
    }

    public function admin_update_language() {
        $this->verify_ajax_request( 'ct_lang_nonce' );

        $input   = isset( $_POST['input'] ) ? wp_unslash( $_POST['input'] ) : '';
        $decoded = json_decode( $input, true );

        assert( is_array( $decoded ), 'Decoded input must be an array' );
        assert( ! empty( $decoded['iso2'] ), 'iso2 is required for update' );

        if ( ! is_array( $decoded ) || empty( $decoded['iso2'] ) ) {
            wp_send_json_error( array( 'message' => __( 'Missing required field: iso2.', 'ct-custom' ), 'type' => 'error' ) );
        }

        $iso2 = sanitize_text_field( $decoded['iso2'] );

        if ( empty( $decoded['native_name'] ) ) {
            wp_send_json_error( array( 'message' => __( 'Native name cannot be empty.', 'ct-custom' ), 'type' => 'error' ) );
        }

        $update_data = array(
            'native_name' => sanitize_text_field( $decoded['native_name'] ),
        );

        if ( isset( $decoded['iso3'] ) ) {
            $update_data['iso3'] = sanitize_text_field( $decoded['iso3'] );
        }

        if ( isset( $decoded['flag'] ) ) {
            $update_data['flag'] = esc_url_raw( $decoded['flag'] );
        }

        if ( ! empty( $decoded['locale'] ) ) {
            $update_data['locales'] = array( sanitize_text_field( $decoded['locale'] ) );
        } elseif ( array_key_exists( 'locale', $decoded ) ) {
            $update_data['locales'] = array();
        }

        $mgr    = ct_get_language_manager();
        $result = $mgr->update( $iso2, $update_data );

        if ( ! $result ) {
            wp_send_json_error( array( 'message' => __( 'Failed to update language.', 'ct-custom' ), 'type' => 'error' ) );
        }

        wp_send_json_success( array(
            'message'   => __( 'Language updated.', 'ct-custom' ),
            'languages' => $mgr->get_all(),
            'type'      => 'success',
        ) );
    }

    public function admin_get_translation_keys() {
        $this->verify_ajax_request( 'ct_lang_nonce' );

        $trans_dir = get_template_directory() . '/translations';
        $mgr       = ct_get_language_manager();
        $languages = $mgr->get_all();
        $all_keys  = array();
        $max_langs = 50;
        $lang_count = 0;

        foreach ( $languages as $lang ) {
            if ( $lang_count >= $max_langs ) {
                break;
            }
            $lang_count++;

            $file = $trans_dir . '/' . $lang['iso2'] . '.json';

            if ( ! file_exists( $file ) ) {
                continue;
            }

            $data = json_decode( file_get_contents( $file ), true );

            if ( ! is_array( $data ) ) {
                continue;
            }

            $key_count = 0;
            $max_keys  = 500;
            foreach ( array_keys( $data ) as $key ) {
                if ( $key_count >= $max_keys ) {
                    break;
                }
                $key_count++;
                $all_keys[ $key ] = true;
            }
        }

        ksort( $all_keys );

        wp_send_json_success( array(
            'keys' => array_keys( $all_keys ),
        ) );
    }

    public function admin_save_translation() {
        $this->verify_ajax_request( 'ct_lang_nonce' );

        $input   = isset( $_POST['input'] ) ? wp_unslash( $_POST['input'] ) : '';
        $decoded = json_decode( $input, true );

        if ( ! is_array( $decoded ) || empty( $decoded['key'] ) || empty( $decoded['iso2'] ) ) {
            wp_send_json_error( array( 'message' => __( 'Missing required fields.', 'ct-custom' ) ) );
        }

        $key   = sanitize_text_field( $decoded['key'] );
        $iso2  = sanitize_text_field( $decoded['iso2'] );
        $value = isset( $decoded['value'] ) ? $decoded['value'] : '';

        $trans_dir = get_template_directory() . '/translations';
        $file      = $trans_dir . '/' . $iso2 . '.json';

        $data = array();
        if ( file_exists( $file ) ) {
            $data = json_decode( file_get_contents( $file ), true );
            if ( ! is_array( $data ) ) {
                $data = array();
            }
        }

        /* Value can be a string or a combined singular+plural object */
        if ( is_array( $value ) ) {
            $sanitized  = array();
            $max_fields = 8;
            $field_count = 0;
            foreach ( $value as $form => $text ) {
                if ( $field_count >= $max_fields ) {
                    break;
                }
                $field_count++;
                $sanitized[ sanitize_text_field( $form ) ] = wp_kses_post( $text );
            }
            $data[ $key ] = $sanitized;
        } else {
            $data[ $key ] = wp_kses_post( (string) $value );
        }

        $written = file_put_contents( $file, wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ), LOCK_EX );

        if ( false === $written ) {
            wp_send_json_error( array( 'message' => __( 'Failed to write translation file.', 'ct-custom' ) ) );
        }

        Translator::clear_cache();

        wp_send_json_success( array( 'message' => __( 'Translation saved.', 'ct-custom' ) ) );
    }

    public function admin_add_translation_key() {
        $this->verify_ajax_request( 'ct_lang_nonce' );

        $key = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';

        if ( '' === $key ) {
            wp_send_json_error( array( 'message' => __( 'Key is required.', 'ct-custom' ) ) );
        }

        $trans_dir = get_template_directory() . '/translations';
        $mgr       = ct_get_language_manager();
        $languages = $mgr->get_all();
        $max_langs = 50;
        $count     = 0;

        foreach ( $languages as $lang ) {
            if ( $count >= $max_langs ) {
                break;
            }
            $count++;

            $file = $trans_dir . '/' . $lang['iso2'] . '.json';
            $data = array();

            if ( file_exists( $file ) ) {
                $data = json_decode( file_get_contents( $file ), true );
                if ( ! is_array( $data ) ) {
                    $data = array();
                }
            }

            if ( ! isset( $data[ $key ] ) ) {
                $data[ $key ] = '';
                file_put_contents( $file, wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ), LOCK_EX );
            }
        }

        Translator::clear_cache();

        wp_send_json_success( array( 'message' => __( 'Key added to all languages.', 'ct-custom' ) ) );
    }

    public function admin_delete_translation_key() {
        $this->verify_ajax_request( 'ct_lang_nonce' );

        $key = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';

        if ( '' === $key ) {
            wp_send_json_error( array( 'message' => __( 'Key is required.', 'ct-custom' ) ) );
        }

        $trans_dir = get_template_directory() . '/translations';
        $mgr       = ct_get_language_manager();
        $languages = $mgr->get_all();
        $max_langs = 50;
        $count     = 0;

        foreach ( $languages as $lang ) {
            if ( $count >= $max_langs ) {
                break;
            }
            $count++;

            $file = $trans_dir . '/' . $lang['iso2'] . '.json';

            if ( ! file_exists( $file ) ) {
                continue;
            }

            $data = json_decode( file_get_contents( $file ), true );
            if ( ! is_array( $data ) ) {
                continue;
            }

            if ( isset( $data[ $key ] ) ) {
                unset( $data[ $key ] );
                file_put_contents( $file, wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ), LOCK_EX );
            }
        }

        Translator::clear_cache();

        wp_send_json_success( array( 'message' => __( 'Key deleted from all languages.', 'ct-custom' ) ) );
    }

    public function admin_export_translations() {
        $this->verify_ajax_request( 'ct_lang_nonce' );

        $trans_dir = get_template_directory() . '/translations';
        $mgr       = ct_get_language_manager();
        $languages = $mgr->get_all();
        $export    = array( 'languages' => $languages, 'translations' => array() );
        $max_langs = 50;
        $count     = 0;

        foreach ( $languages as $lang ) {
            if ( $count >= $max_langs ) {
                break;
            }
            $count++;

            $file = $trans_dir . '/' . $lang['iso2'] . '.json';

            if ( ! file_exists( $file ) ) {
                $export['translations'][ $lang['iso2'] ] = new \stdClass();
                continue;
            }

            $data = json_decode( file_get_contents( $file ), true );
            $export['translations'][ $lang['iso2'] ] = is_array( $data ) ? $data : new \stdClass();
        }

        wp_send_json_success( $export );
    }

    public function admin_import_translations() {
        $this->verify_ajax_request( 'ct_lang_nonce' );

        $input   = isset( $_POST['input'] ) ? wp_unslash( $_POST['input'] ) : '';
        $decoded = json_decode( $input, true );

        if ( ! is_array( $decoded ) || ! isset( $decoded['translations'] ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid import data.', 'ct-custom' ) ) );
        }

        $trans_dir = get_template_directory() . '/translations';
        $max_langs = 50;
        $count     = 0;

        foreach ( $decoded['translations'] as $iso2 => $trans_data ) {
            if ( $count >= $max_langs ) {
                break;
            }
            $count++;

            $safe_iso = preg_replace( '/[^a-z_]/', '', strtolower( $iso2 ) );
            $file     = $trans_dir . '/' . $safe_iso . '.json';

            if ( is_array( $trans_data ) ) {
                file_put_contents( $file, wp_json_encode( $trans_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ), LOCK_EX );
            }
        }

        if ( isset( $decoded['languages'] ) && is_array( $decoded['languages'] ) ) {
            $mgr  = ct_get_language_manager();
            $path = $mgr->get_file_path();
            file_put_contents( $path, wp_json_encode( $decoded['languages'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ), LOCK_EX );
        }

        Translator::clear_cache();

        wp_send_json_success( array( 'message' => __( 'Translations imported.', 'ct-custom' ) ) );
    }

    public function admin_get_pages_by_language() {
        $this->verify_ajax_request( 'ct_lang_nonce' );

        $iso2 = isset( $_POST['iso2'] ) ? sanitize_text_field( wp_unslash( $_POST['iso2'] ) ) : '';

        if ( '' === $iso2 ) {
            wp_send_json_error( array( 'message' => __( 'Language code is required.', 'ct-custom' ) ) );
        }

        $pages = get_posts( array(
            'post_type'      => 'page',
            'post_status'    => array( 'publish', 'draft', 'private' ),
            'posts_per_page' => 200,
            'meta_key'       => 'ct_language',
            'meta_value'     => $iso2,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ) );

        $result    = array();
        $max_pages = 200;
        $count     = 0;

        foreach ( $pages as $page ) {
            if ( $count >= $max_pages ) {
                break;
            }
            $count++;

            $group = get_post_meta( $page->ID, 'ct_translation_group', true );

            $result[] = array(
                'id'        => $page->ID,
                'title'     => $page->post_title,
                'slug'      => $page->post_name,
                'status'    => $page->post_status,
                'parent_id' => $page->post_parent,
                'group'     => is_string( $group ) ? $group : '',
                'edit_url'  => get_edit_post_link( $page->ID, 'raw' ),
            );
        }

        wp_send_json_success( array( 'pages' => $result ) );
    }

    public function admin_save_page_translation() {
        $this->verify_ajax_request( 'ct_lang_nonce' );

        $input   = isset( $_POST['input'] ) ? wp_unslash( $_POST['input'] ) : '';
        $decoded = json_decode( $input, true );

        if ( ! is_array( $decoded ) || empty( $decoded['post_id'] ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid page data.', 'ct-custom' ) ) );
        }

        $post_id = absint( $decoded['post_id'] );
        $post    = get_post( $post_id );

        if ( ! $post || 'page' !== $post->post_type ) {
            wp_send_json_error( array( 'message' => __( 'Page not found.', 'ct-custom' ) ) );
        }

        $update = array( 'ID' => $post_id );

        if ( isset( $decoded['title'] ) ) {
            $update['post_title'] = sanitize_text_field( $decoded['title'] );
        }

        if ( isset( $decoded['content'] ) ) {
            $update['post_content'] = wp_kses_post( $decoded['content'] );
        }

        if ( isset( $decoded['slug'] ) ) {
            $update['post_name'] = sanitize_title( $decoded['slug'] );
        }

        if ( isset( $decoded['parent_id'] ) ) {
            $parent_id = absint( $decoded['parent_id'] );
            if ( $parent_id !== $post_id ) {
                $update['post_parent'] = $parent_id;
            }
        }

        $result = wp_update_post( $update, true );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        $updated_post = get_post( $post_id );
        $response = array( 'message' => __( 'Page updated.', 'ct-custom' ) );

        if ( null !== $updated_post ) {
            $response['slug']      = $updated_post->post_name;
            $response['title']     = $updated_post->post_title;
            $response['parent_id'] = $updated_post->post_parent;
        }

        wp_send_json_success( $response );
    }

    public function admin_duplicate_page() {
        $this->verify_ajax_request( 'ct_lang_nonce' );

        $post_id     = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $target_iso2 = isset( $_POST['target_iso2'] ) ? sanitize_text_field( wp_unslash( $_POST['target_iso2'] ) ) : '';

        if ( $post_id <= 0 || '' === $target_iso2 ) {
            wp_send_json_error( array( 'message' => __( 'Post ID and target language are required.', 'ct-custom' ) ) );
        }

        $source_post = get_post( $post_id );

        if ( ! $source_post || 'page' !== $source_post->post_type ) {
            wp_send_json_error( array( 'message' => __( 'Source page not found.', 'ct-custom' ) ) );
        }

        $page_mgr = new LanguagePageManager();
        $new_id   = $page_mgr->duplicate_single_page( $source_post, $target_iso2 );

        if ( $new_id <= 0 ) {
            wp_send_json_error( array( 'message' => __( 'Failed to duplicate page.', 'ct-custom' ) ) );
        }

        wp_send_json_success( array(
            'message' => __( 'Page duplicated.', 'ct-custom' ),
            'new_id'  => $new_id,
        ) );
    }

    /* ─── Shared helpers ─── */

    /**
     * Count published pages for a language (pre-removal check).
     *
     * @param string $iso2 Language code.
     * @return int Page count.
     */
    private function count_language_pages( string $iso2 ): int {
        assert( ! empty( $iso2 ), 'iso2 must not be empty' );

        $pages = get_posts( array(
            'post_type'      => 'page',
            'post_status'    => array( 'publish', 'draft', 'private' ),
            'posts_per_page' => 1,
            'meta_key'       => 'ct_language',
            'meta_value'     => $iso2,
            'fields'         => 'ids',
        ) );

        return is_array( $pages ) ? count( $pages ) : 0;
    }

    private function verify_ajax_request( $nonce_name ) {
        assert( ! empty( $nonce_name ), 'Nonce name must not be empty' );
        assert( is_string( $nonce_name ), 'Nonce name must be a string' );

        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), $nonce_name ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid nonce.', 'ct-custom' ) ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'ct-custom' ) ) );
        }
    }
}
