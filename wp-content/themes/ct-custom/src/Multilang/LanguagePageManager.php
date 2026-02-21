<?php
/**
 * Language Page Manager.
 *
 * Handles page duplication for new languages, translation group linking,
 * and language-to-page mapping.
 *
 * @package BS_Custom
 */

namespace BSCustom\Multilang;

class LanguagePageManager {

    const MAX_PAGES_TO_DUPLICATE = 500;
    const MAX_META_KEYS          = 50;

    /** @var array Meta keys to exclude when duplicating a page. */
    private static $excluded_meta_keys = array( 'bs_language', 'bs_locale', 'bs_translation_group' );

    /**
     * Duplicate all pages from the default language for a new language.
     *
     * Creates a homepage for the new language with slug = iso2 code.
     * Preserves parent-child hierarchy by remapping parent IDs to the
     * newly duplicated pages in the target language.
     *
     * @param string $target_iso2  New language code.
     * @param string $default_iso2 Default language code to copy from.
     * @return array<int, int> Mapping of source page ID => new page ID.
     */
    public function duplicate_pages_for_language( string $target_iso2, string $default_iso2 = '' ): array {
        assert( ! empty( $target_iso2 ), 'target_iso2 must not be empty' );

        if ( '' === $default_iso2 ) {
            $mgr     = bs_get_language_manager();
            $default = $mgr->get_default();
            $default_iso2 = ( null !== $default ) ? $default['iso2'] : 'en';
        }

        /* Fetch all pages for the default language, ordered by parent then ID
         * so that root pages come first, then first-level children, etc. */
        $pages = get_posts( array(
            'post_type'      => 'page',
            'post_status'    => array( 'publish', 'draft', 'private' ),
            'posts_per_page' => self::MAX_PAGES_TO_DUPLICATE,
            'meta_key'       => 'bs_language',
            'meta_value'     => $default_iso2,
            'orderby'        => 'parent ID',
            'order'          => 'ASC',
        ) );

        if ( ! is_array( $pages ) || 0 === count( $pages ) ) {
            return array();
        }

        /* Sort pages so parents are always processed before their children.
         * Uses iterative topological ordering with bounded iterations. */
        $pages = $this->sort_pages_parent_first( $pages );

        /* Identify the front page so we can give it slug = iso2 */
        $front_page_id = (int) get_option( 'page_on_front' );

        /* Mapping: source page ID => newly created page ID */
        $id_map      = array();
        $homepage_id = 0;
        $max         = self::MAX_PAGES_TO_DUPLICATE;

        foreach ( $pages as $page ) {
            if ( count( $id_map ) >= $max ) {
                break;
            }

            $is_homepage = ( $page->ID === $front_page_id );

            /* Determine the correct parent for the new page */
            $new_parent = 0;

            if ( 0 === (int) $page->post_parent ) {
                /* Root page: becomes child of the new language homepage
                 * (unless this IS the homepage itself). */
                $new_parent = $is_homepage ? 0 : $homepage_id;
            } else {
                /* Child page: remap parent to the duplicated version.
                 * If parent wasn't duplicated (shouldn't happen), fall
                 * back to the homepage. */
                $source_parent = (int) $page->post_parent;
                $new_parent = isset( $id_map[ $source_parent ] ) ? $id_map[ $source_parent ] : $homepage_id;
            }

            /* Homepage gets slug = iso2; other pages keep their original slug */
            $slug_override = $is_homepage ? $target_iso2 : '';

            $new_id = $this->duplicate_single_page( $page, $target_iso2, $new_parent, $slug_override );

            if ( $new_id > 0 ) {
                $id_map[ $page->ID ] = $new_id;

                if ( $is_homepage ) {
                    $homepage_id = $new_id;
                }
            }
        }

        return $id_map;
    }

    /**
     * Sort pages so that parents always appear before their children.
     *
     * @param array $pages Array of WP_Post objects.
     * @return array Sorted array with parents first.
     */
    private function sort_pages_parent_first( array $pages ): array {
        $by_id  = array();
        $max    = self::MAX_PAGES_TO_DUPLICATE;
        $count  = 0;

        foreach ( $pages as $page ) {
            if ( $count >= $max ) { break; }
            $count++;
            $by_id[ $page->ID ] = $page;
        }

        $sorted  = array();
        $placed  = array();
        $passes  = 0;
        $max_passes = 20;

        while ( count( $sorted ) < count( $by_id ) && $passes < $max_passes ) {
            $passes++;

            foreach ( $by_id as $id => $page ) {
                if ( isset( $placed[ $id ] ) ) {
                    continue;
                }

                $parent = (int) $page->post_parent;

                /* Place if: root page, or parent is outside our set, or parent already placed */
                if ( 0 === $parent || ! isset( $by_id[ $parent ] ) || isset( $placed[ $parent ] ) ) {
                    $sorted[]      = $page;
                    $placed[ $id ] = true;
                }
            }
        }

        return $sorted;
    }

    /**
     * Duplicate a single page for a target language.
     *
     * @param \WP_Post $source_page  Source page object.
     * @param string   $target_iso2  Target language code.
     * @param int      $new_parent   Explicit parent ID for the new page.
     * @param string   $slug         Explicit slug override (empty = auto).
     * @return int New post ID or 0 on failure.
     */
    public function duplicate_single_page( $source_page, string $target_iso2, int $new_parent = 0, string $slug = '' ): int {
        assert( is_object( $source_page ), 'source_page must be a WP_Post object' );
        assert( ! empty( $target_iso2 ), 'target_iso2 must not be empty' );

        $new_post = array(
            'post_title'   => $source_page->post_title,
            'post_content' => $source_page->post_content,
            'post_excerpt' => $source_page->post_excerpt,
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_parent'  => $new_parent,
            'menu_order'   => $source_page->menu_order,
            'post_author'  => $source_page->post_author,
        );

        if ( '' !== $slug ) {
            $new_post['post_name'] = sanitize_title( $slug );
        }

        $new_id = wp_insert_post( $new_post, true );

        if ( is_wp_error( $new_id ) || $new_id <= 0 ) {
            return 0;
        }

        /* Copy meta except excluded keys */
        $this->copy_post_meta( $source_page->ID, $new_id );

        /* Set language */
        update_post_meta( $new_id, 'bs_language', $target_iso2 );

        /* Link translation group */
        $group = $this->get_translation_group( $source_page->ID );
        update_post_meta( $new_id, 'bs_translation_group', $group );

        return $new_id;
    }

    /**
     * Get the translation group UUID for a post.
     * Creates one if it doesn't exist.
     *
     * @param int $post_id Post ID.
     * @return string UUID or empty string if invalid.
     */
    public function get_translation_group( int $post_id ): string {
        assert( is_int( $post_id ), 'post_id must be an int' );

        if ( $post_id <= 0 ) {
            return '';
        }

        $group = get_post_meta( $post_id, 'bs_translation_group', true );

        if ( is_string( $group ) && '' !== $group ) {
            return $group;
        }

        $group = wp_generate_uuid4();
        update_post_meta( $post_id, 'bs_translation_group', $group );

        return $group;
    }

    /**
     * Get all translations for a post (keyed by iso2).
     *
     * @param int $post_id Post ID.
     * @return array<string, int> [iso2 => post_id]
     */
    public function get_translations( int $post_id ): array {
        assert( is_int( $post_id ), 'post_id must be an int' );

        $group = get_post_meta( $post_id, 'bs_translation_group', true );

        if ( ! is_string( $group ) || '' === $group ) {
            return array();
        }

        if ( ! function_exists( 'get_posts' ) ) {
            return array();
        }

        $posts = get_posts( array(
            'post_type'      => 'page',
            'post_status'    => array( 'publish', 'draft', 'private' ),
            'posts_per_page' => self::MAX_PAGES_TO_DUPLICATE,
            'meta_key'       => 'bs_translation_group',
            'meta_value'     => $group,
            'orderby'        => 'ID',
            'order'          => 'ASC',
        ) );

        $translations = array();
        $max   = self::MAX_PAGES_TO_DUPLICATE;
        $count = 0;

        foreach ( $posts as $post ) {
            if ( $count >= $max ) {
                break;
            }
            $count++;

            $lang = get_post_meta( $post->ID, 'bs_language', true );

            if ( is_string( $lang ) && '' !== $lang ) {
                $translations[ $lang ] = $post->ID;
            }
        }

        return $translations;
    }

    /**
     * Get a specific page translation for a language.
     *
     * @param int    $post_id     Source post ID.
     * @param string $target_iso2 Target language code.
     * @return int|null Post ID or null.
     */
    public function get_page_for_language( int $post_id, string $target_iso2 ): ?int {
        $translations = $this->get_translations( $post_id );

        return isset( $translations[ $target_iso2 ] ) ? $translations[ $target_iso2 ] : null;
    }

    /**
     * Remove all pages for a given language.
     *
     * @param string $iso2         Language code.
     * @param bool   $force_delete True to permanently delete, false to trash.
     * @return int Number of pages removed.
     */
    public function remove_language_pages( string $iso2, bool $force_delete = false ): int {
        assert( ! empty( $iso2 ), 'iso2 must not be empty' );

        $pages = get_posts( array(
            'post_type'      => 'page',
            'post_status'    => array( 'publish', 'draft', 'private' ),
            'posts_per_page' => self::MAX_PAGES_TO_DUPLICATE,
            'meta_key'       => 'bs_language',
            'meta_value'     => $iso2,
            'orderby'        => 'ID',
            'order'          => 'ASC',
        ) );

        if ( ! is_array( $pages ) ) {
            return 0;
        }

        $removed = 0;
        $max     = self::MAX_PAGES_TO_DUPLICATE;

        foreach ( $pages as $page ) {
            if ( $removed >= $max ) {
                break;
            }

            if ( $force_delete ) {
                $result = wp_delete_post( $page->ID, true );
            } else {
                $result = wp_trash_post( $page->ID );
            }

            if ( false !== $result ) {
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * Sync page template meta from source to target.
     *
     * @param int $source_id Source post ID.
     * @param int $target_id Target post ID.
     * @return void
     */
    public function sync_template( int $source_id, int $target_id ): void {
        assert( $source_id > 0, 'source_id must be positive' );
        assert( $target_id > 0, 'target_id must be positive' );

        $template = get_post_meta( $source_id, '_wp_page_template', true );

        if ( is_string( $template ) && '' !== $template ) {
            update_post_meta( $target_id, '_wp_page_template', $template );
        }
    }

    /**
     * Migrate existing pages without language meta to the default language.
     *
     * Runs once, guarded by an option flag.
     *
     * @param string $default_iso2 Default language code.
     * @return int Number of pages migrated.
     */
    public static function migrate_existing_pages( string $default_iso2 ): int {
        assert( ! empty( $default_iso2 ), 'default_iso2 must not be empty' );

        if ( get_option( 'bs_custom_language_migration_done' ) ) {
            return 0;
        }

        $pages = get_posts( array(
            'post_type'      => 'page',
            'post_status'    => array( 'publish', 'draft', 'private' ),
            'posts_per_page' => self::MAX_PAGES_TO_DUPLICATE,
            'meta_query'     => array(
                array(
                    'key'     => 'bs_language',
                    'compare' => 'NOT EXISTS',
                ),
            ),
            'orderby'        => 'ID',
            'order'          => 'ASC',
        ) );

        if ( ! is_array( $pages ) ) {
            update_option( 'bs_custom_language_migration_done', true );
            return 0;
        }

        $mgr       = new self();
        $migrated  = 0;
        $max       = self::MAX_PAGES_TO_DUPLICATE;

        foreach ( $pages as $page ) {
            if ( $migrated >= $max ) {
                break;
            }

            update_post_meta( $page->ID, 'bs_language', $default_iso2 );
            $mgr->get_translation_group( $page->ID );
            $migrated++;
        }

        update_option( 'bs_custom_language_migration_done', true );

        return $migrated;
    }

    /**
     * Rename homepage slugs to their language iso2 code.
     *
     * Finds the front page and all its translations, and renames each
     * to its language code (e.g. "homepage" → "en", "homepage-2" → "sr").
     *
     * Runs once, guarded by an option flag.
     *
     * @return int Number of pages renamed.
     */
    public static function migrate_homepage_slugs(): int {
        if ( get_option( 'bs_custom_homepage_slug_migration_done' ) ) {
            return 0;
        }

        $front_page_id = (int) get_option( 'page_on_front' );

        if ( $front_page_id <= 0 ) {
            update_option( 'bs_custom_homepage_slug_migration_done', true );
            return 0;
        }

        $mgr      = new self();
        $group    = $mgr->get_translation_group( $front_page_id );
        $renamed  = 0;

        if ( '' === $group ) {
            update_option( 'bs_custom_homepage_slug_migration_done', true );
            return 0;
        }

        /* Find all pages in this translation group */
        $posts = get_posts( array(
            'post_type'      => 'page',
            'post_status'    => array( 'publish', 'draft', 'private' ),
            'posts_per_page' => self::MAX_PAGES_TO_DUPLICATE,
            'meta_key'       => 'bs_translation_group',
            'meta_value'     => $group,
            'orderby'        => 'ID',
            'order'          => 'ASC',
        ) );

        if ( ! is_array( $posts ) ) {
            update_option( 'bs_custom_homepage_slug_migration_done', true );
            return 0;
        }

        $max   = self::MAX_PAGES_TO_DUPLICATE;
        $count = 0;

        foreach ( $posts as $post ) {
            if ( $count >= $max ) { break; }
            $count++;

            $iso2 = get_post_meta( $post->ID, 'bs_language', true );

            if ( ! is_string( $iso2 ) || '' === $iso2 ) {
                continue;
            }

            /* Only rename if slug doesn't already match iso2 */
            if ( $post->post_name === $iso2 ) {
                continue;
            }

            wp_update_post( array(
                'ID'        => $post->ID,
                'post_name' => sanitize_title( $iso2 ),
            ) );

            $renamed++;
        }

        update_option( 'bs_custom_homepage_slug_migration_done', true );

        return $renamed;
    }

    /** @var string[] Base menu location slugs. */
    private static $base_menus = array( 'main-menu', 'top-bar-menu', 'footer-copyright-menu' );

    /** @var int Maximum menu items per menu during duplication. */
    const MAX_MENU_ITEMS = 200;

    /**
     * Duplicate menus from the default language for a new language.
     *
     * For each base menu location, finds the menu assigned to the default
     * language location, creates a copy with the ISO 639-2 suffix, remaps
     * menu items to point to duplicated pages, and assigns the new menu
     * to the target language's location.
     *
     * @param string         $target_iso2  New language code (2-letter).
     * @param array<int,int> $page_id_map  Source page ID => new page ID mapping.
     * @param string         $default_iso2 Default language code (auto-detected if empty).
     * @return int Number of menus created.
     */
    public function duplicate_menus_for_language( string $target_iso2, array $page_id_map, string $default_iso2 = '' ): int {
        assert( ! empty( $target_iso2 ), 'target_iso2 must not be empty' );
        assert( is_array( $page_id_map ), 'page_id_map must be an array' );

        if ( '' === $default_iso2 ) {
            $mgr     = bs_get_language_manager();
            $default = $mgr->get_default();
            $default_iso2 = ( null !== $default ) ? $default['iso2'] : 'en';
        }

        /* Resolve the ISO 639-2 (3-letter) code for the menu name suffix */
        $mgr          = bs_get_language_manager();
        $target_lang  = $mgr->get_by_iso2( $target_iso2 );
        $iso3_suffix  = '';

        if ( null !== $target_lang && ! empty( $target_lang['iso3'] ) ) {
            $iso3_suffix = ucfirst( strtolower( $target_lang['iso3'] ) );
        } else {
            $iso3_suffix = strtoupper( $target_iso2 );
        }

        $locations     = get_theme_mod( 'nav_menu_locations', array() );
        $created_count = 0;
        $base_count    = 0;

        foreach ( self::$base_menus as $base_slug ) {
            if ( $base_count >= 3 ) { break; }
            $base_count++;

            $default_location = $base_slug . '-' . $default_iso2;
            $target_location  = $base_slug . '-' . $target_iso2;

            /* Find the menu assigned to the default language location */
            $source_menu_id = isset( $locations[ $default_location ] ) ? (int) $locations[ $default_location ] : 0;

            if ( $source_menu_id <= 0 ) {
                continue;
            }

            $source_menu = wp_get_nav_menu_object( $source_menu_id );

            if ( ! $source_menu ) {
                continue;
            }

            /* Build new menu name: replace "Eng" suffix with target iso3 */
            $source_name = $source_menu->name;
            $new_name    = preg_replace( '/\s+\w{2,3}$/i', '', $source_name ) . ' ' . $iso3_suffix;

            /* Create the new menu */
            $new_menu_id = wp_create_nav_menu( $new_name );

            if ( is_wp_error( $new_menu_id ) ) {
                continue;
            }

            /* Duplicate menu items */
            $this->duplicate_menu_items( $source_menu_id, $new_menu_id, $page_id_map );

            /* Assign to location */
            $locations[ $target_location ] = $new_menu_id;
            $created_count++;
        }

        /* Save updated locations */
        if ( $created_count > 0 ) {
            set_theme_mod( 'nav_menu_locations', $locations );
        }

        return $created_count;
    }

    /**
     * Duplicate all items from one menu to another, remapping page references.
     *
     * @param int            $source_menu_id Source nav menu term ID.
     * @param int            $target_menu_id Target nav menu term ID.
     * @param array<int,int> $page_id_map    Source page ID => new page ID mapping.
     * @return void
     */
    private function duplicate_menu_items( int $source_menu_id, int $target_menu_id, array $page_id_map ): void {
        $items = wp_get_nav_menu_items( $source_menu_id, array( 'nopaging' => true ) );

        if ( ! is_array( $items ) || 0 === count( $items ) ) {
            return;
        }

        /* Map old menu item IDs to new ones for parent remapping */
        $item_id_map = array();
        $count       = 0;
        $max         = self::MAX_MENU_ITEMS;

        foreach ( $items as $item ) {
            if ( $count >= $max ) { break; }
            $count++;

            $new_object_id = $item->object_id;
            $new_url       = $item->url;

            /* Remap page references */
            if ( 'post_type' === $item->type && 'page' === $item->object ) {
                $source_page_id = (int) $item->object_id;

                if ( isset( $page_id_map[ $source_page_id ] ) ) {
                    $new_object_id = $page_id_map[ $source_page_id ];
                    $new_url       = get_permalink( $new_object_id );
                }
            }

            /* Remap parent to newly created item */
            $new_parent = 0;
            $old_parent = (int) $item->menu_item_parent;

            if ( $old_parent > 0 && isset( $item_id_map[ $old_parent ] ) ) {
                $new_parent = $item_id_map[ $old_parent ];
            }

            $new_item_id = wp_update_nav_menu_item( $target_menu_id, 0, array(
                'menu-item-title'     => $item->title,
                'menu-item-url'       => $new_url,
                'menu-item-object'    => $item->object,
                'menu-item-object-id' => $new_object_id,
                'menu-item-type'      => $item->type,
                'menu-item-status'    => 'publish',
                'menu-item-parent-id' => $new_parent,
                'menu-item-position'  => $item->menu_order,
                'menu-item-target'    => $item->target,
                'menu-item-classes'   => is_array( $item->classes ) ? implode( ' ', $item->classes ) : '',
                'menu-item-attr-title' => $item->attr_title,
                'menu-item-description' => $item->description,
            ) );

            if ( ! is_wp_error( $new_item_id ) && $new_item_id > 0 ) {
                $item_id_map[ $item->ID ] = $new_item_id;
            }
        }
    }

    /**
     * Remove all menus assigned to a language's locations.
     *
     * Deletes the nav menu terms and all their items, then unsets
     * the language's locations from the theme mod.
     *
     * @param string $iso2 Language code to remove menus for.
     * @return int Number of menus removed.
     */
    public function remove_language_menus( string $iso2 ): int {
        assert( ! empty( $iso2 ), 'iso2 must not be empty' );

        $locations     = get_theme_mod( 'nav_menu_locations', array() );
        $removed_count = 0;
        $base_count    = 0;

        foreach ( self::$base_menus as $base_slug ) {
            if ( $base_count >= 3 ) { break; }
            $base_count++;

            $location = $base_slug . '-' . $iso2;
            $menu_id  = isset( $locations[ $location ] ) ? (int) $locations[ $location ] : 0;

            if ( $menu_id <= 0 ) {
                continue;
            }

            $result = wp_delete_nav_menu( $menu_id );

            if ( false !== $result && ! is_wp_error( $result ) ) {
                unset( $locations[ $location ] );
                $removed_count++;
            }
        }

        if ( $removed_count > 0 ) {
            set_theme_mod( 'nav_menu_locations', $locations );
        }

        return $removed_count;
    }

    /** @var string[] Sidebar base slugs (without language suffix). */
    private static $sidebar_bases = array(
        'sidebar-left',
        'sidebar-right',
        'footer-column-1',
        'footer-column-2',
        'footer-column-3',
        'footer-column-4',
        'footer-column-5',
    );

    /** @var int Maximum widget types to process during cloning. */
    const MAX_WIDGET_TYPES = 100;

    /**
     * Clone widget instances from default language sidebars to a new language.
     *
     * Each widget in the source sidebar is cloned to a new instance number
     * so the target language can be edited independently. bs_menu widgets
     * get their nav_menu field remapped to the target language's menu.
     *
     * @param string $target_iso2  New language code.
     * @param string $default_iso2 Default language code (auto-detected if empty).
     * @return int Number of sidebars populated.
     */
    public function duplicate_widget_areas_for_language( string $target_iso2, string $default_iso2 = '' ): int {
        assert( ! empty( $target_iso2 ), 'target_iso2 must not be empty' );

        if ( '' === $default_iso2 ) {
            $mgr     = bs_get_language_manager();
            $default = $mgr->get_default();
            $default_iso2 = ( null !== $default ) ? $default['iso2'] : 'en';
        }

        assert( $target_iso2 !== $default_iso2, 'target and default must differ' );

        $menu_map = $this->build_menu_map( $default_iso2, $target_iso2 );
        $sidebars = get_option( 'sidebars_widgets', array() );

        /* Cache: source widget ID => cloned widget ID (reuse across sidebars) */
        $clone_cache     = array();
        $dirty_types     = array();
        $populated       = 0;
        $max             = 7;
        $count           = 0;

        foreach ( self::$sidebar_bases as $base ) {
            if ( $count >= $max ) { break; }
            $count++;

            $source_key = $base . '-' . $default_iso2;
            $target_key = $base . '-' . $target_iso2;

            if ( ! isset( $sidebars[ $source_key ] ) || ! is_array( $sidebars[ $source_key ] ) ) {
                continue;
            }

            if ( 0 === count( $sidebars[ $source_key ] ) ) {
                continue;
            }

            $new_widget_ids = array();
            $widget_max     = self::MAX_WIDGET_TYPES;
            $widget_count   = 0;

            foreach ( $sidebars[ $source_key ] as $widget_id ) {
                if ( $widget_count >= $widget_max ) { break; }
                $widget_count++;

                $cloned_id = $this->clone_widget_instance( $widget_id, $menu_map, $clone_cache, $dirty_types );
                $new_widget_ids[] = $cloned_id;
            }

            $sidebars[ $target_key ] = $new_widget_ids;
            $populated++;
        }

        if ( $populated > 0 ) {
            update_option( 'sidebars_widgets', $sidebars );
        }

        return $populated;
    }

    /**
     * Build a mapping of source menu IDs to target menu IDs.
     *
     * Reads nav_menu_locations and maps each base menu location
     * from the source language to the target language.
     *
     * @param string $source_iso2 Source language code.
     * @param string $target_iso2 Target language code.
     * @return array<int, int> {source_menu_id => target_menu_id}
     */
    private function build_menu_map( string $source_iso2, string $target_iso2 ): array {
        assert( ! empty( $source_iso2 ), 'source_iso2 must not be empty' );
        assert( ! empty( $target_iso2 ), 'target_iso2 must not be empty' );

        $locations = get_theme_mod( 'nav_menu_locations', array() );
        $map       = array();
        $count     = 0;

        foreach ( self::$base_menus as $base_slug ) {
            if ( $count >= 3 ) { break; }
            $count++;

            $source_loc = $base_slug . '-' . $source_iso2;
            $target_loc = $base_slug . '-' . $target_iso2;

            $source_id = isset( $locations[ $source_loc ] ) ? (int) $locations[ $source_loc ] : 0;
            $target_id = isset( $locations[ $target_loc ] ) ? (int) $locations[ $target_loc ] : 0;

            if ( $source_id > 0 && $target_id > 0 ) {
                $map[ $source_id ] = $target_id;
            }
        }

        return $map;
    }

    /**
     * Clone a single widget instance, returning the new widget ID.
     *
     * If the same source widget ID was already cloned (tracked in $cache),
     * returns the previously cloned ID. For bs_menu widgets, remaps the
     * nav_menu field using the menu mapping.
     *
     * @param string            $widget_id   Source widget ID (e.g. "bs_company_info-1").
     * @param array<int, int>   $menu_map    Source menu ID => target menu ID.
     * @param array<string, string> &$cache  Clone cache: source ID => cloned ID.
     * @param array<string, bool>   &$dirty  Tracks modified widget types for batch write.
     * @return string New widget ID or original if unparseable/missing.
     */
    private function clone_widget_instance( string $widget_id, array $menu_map, array &$cache, array &$dirty ): string {
        assert( is_string( $widget_id ), 'widget_id must be a string' );
        assert( is_array( $menu_map ), 'menu_map must be an array' );

        /* Already cloned this source widget — reuse */
        if ( isset( $cache[ $widget_id ] ) ) {
            return $cache[ $widget_id ];
        }

        $parsed = self::parse_widget_id( $widget_id );

        if ( null === $parsed ) {
            /* Unparseable widget ID — return unchanged (graceful degradation) */
            return $widget_id;
        }

        $type            = $parsed[0];
        $instance_number = $parsed[1];
        $option_key      = 'widget_' . $type;
        $instances       = get_option( $option_key, array() );

        if ( ! is_array( $instances ) || ! isset( $instances[ $instance_number ] ) ) {
            /* Missing widget option — return unchanged */
            return $widget_id;
        }

        $source_data = $instances[ $instance_number ];
        $new_number  = self::next_widget_instance_number( $instances );
        $new_data    = is_array( $source_data ) ? $source_data : array();

        /* bs_menu widgets: remap nav_menu to the target language's menu */
        if ( 'bs_menu' === $type && isset( $new_data['nav_menu'] ) ) {
            $source_menu = (int) $new_data['nav_menu'];

            if ( isset( $menu_map[ $source_menu ] ) ) {
                $new_data['nav_menu'] = $menu_map[ $source_menu ];
            }
        }

        /* Write new instance into the option array */
        $instances[ $new_number ] = $new_data;
        update_option( $option_key, $instances );
        $dirty[ $type ] = true;

        $new_id = $type . '-' . $new_number;
        $cache[ $widget_id ] = $new_id;

        return $new_id;
    }

    /**
     * Parse a widget ID into its type and instance number.
     *
     * @param string $widget_id Widget ID (e.g. "bs_company_info-1").
     * @return array{0: string, 1: int}|null [type, number] or null if unparseable.
     */
    private static function parse_widget_id( string $widget_id ): ?array {
        assert( is_string( $widget_id ), 'widget_id must be a string' );

        $last_dash = strrpos( $widget_id, '-' );

        if ( false === $last_dash || $last_dash < 1 ) {
            return null;
        }

        $type   = substr( $widget_id, 0, $last_dash );
        $number = substr( $widget_id, $last_dash + 1 );

        if ( '' === $type || strlen( $type ) > 50 || ! ctype_digit( $number ) ) {
            return null;
        }

        return array( $type, (int) $number );
    }

    /**
     * Find the next available instance number in a widget option array.
     *
     * Scans integer keys, skipping '_multiwidget', and returns max + 1.
     *
     * @param array $instances Widget option array.
     * @return int Next available instance number.
     */
    private static function next_widget_instance_number( array $instances ): int {
        assert( is_array( $instances ), 'instances must be an array' );

        $max   = 0;
        $count = 0;
        $limit = self::MAX_WIDGET_TYPES;

        foreach ( $instances as $key => $value ) {
            if ( $count >= $limit ) { break; }
            $count++;

            if ( '_multiwidget' === $key ) {
                continue;
            }

            if ( is_int( $key ) && $key > $max ) {
                $max = $key;
            }
        }

        return $max + 1;
    }

    /**
     * Remove widget assignments for a language's sidebars.
     *
     * Clears the widget ID arrays from the sidebars_widgets option
     * for all sidebar slots belonging to the given language.
     *
     * @param string $iso2 Language code.
     * @return int Number of sidebars cleared.
     */
    public function remove_language_widget_areas( string $iso2 ): int {
        assert( ! empty( $iso2 ), 'iso2 must not be empty' );

        $sidebars = get_option( 'sidebars_widgets', array() );
        $cleared  = 0;
        $max      = 7;
        $count    = 0;

        foreach ( self::$sidebar_bases as $base ) {
            if ( $count >= $max ) { break; }
            $count++;

            $key = $base . '-' . $iso2;

            if ( ! isset( $sidebars[ $key ] ) ) {
                continue;
            }

            $sidebars[ $key ] = array();
            $cleared++;
        }

        if ( $cleared > 0 ) {
            update_option( 'sidebars_widgets', $sidebars );
        }

        return $cleared;
    }

    /**
     * Migrate shared widget instances for an existing language.
     *
     * Compares target language sidebars against default language sidebars.
     * Any widget ID that appears in both (shared reference) is cloned to
     * a new instance so the target language can be edited independently.
     * Widget IDs unique to the target are left untouched.
     *
     * @param string $target_iso2  Target language code.
     * @param string $default_iso2 Default language code.
     * @return int Number of widgets cloned.
     */
    public static function migrate_shared_widgets( string $target_iso2, string $default_iso2 ): int {
        assert( ! empty( $target_iso2 ), 'target_iso2 must not be empty' );
        assert( ! empty( $default_iso2 ), 'default_iso2 must not be empty' );

        if ( $target_iso2 === $default_iso2 ) {
            return 0;
        }

        $instance    = new self();
        $menu_map    = $instance->build_menu_map( $default_iso2, $target_iso2 );
        $sidebars    = get_option( 'sidebars_widgets', array() );
        $clone_cache = array();
        $dirty_types = array();
        $cloned      = 0;
        $changed     = false;
        $max         = 7;
        $count       = 0;

        /* Collect all widget IDs in default language sidebars */
        $default_widgets = array();
        $dw_count        = 0;

        foreach ( self::$sidebar_bases as $base ) {
            if ( $dw_count >= $max ) { break; }
            $dw_count++;

            $source_key = $base . '-' . $default_iso2;

            if ( ! isset( $sidebars[ $source_key ] ) || ! is_array( $sidebars[ $source_key ] ) ) {
                continue;
            }

            $wid_count = 0;

            foreach ( $sidebars[ $source_key ] as $wid ) {
                if ( $wid_count >= self::MAX_WIDGET_TYPES ) { break; }
                $wid_count++;
                $default_widgets[ $wid ] = true;
            }
        }

        /* Walk target sidebars: clone any widget that also exists in default */
        foreach ( self::$sidebar_bases as $base ) {
            if ( $count >= $max ) { break; }
            $count++;

            $target_key = $base . '-' . $target_iso2;

            if ( ! isset( $sidebars[ $target_key ] ) || ! is_array( $sidebars[ $target_key ] ) ) {
                continue;
            }

            $new_ids   = array();
            $wid_count = 0;
            $sidebar_changed = false;

            foreach ( $sidebars[ $target_key ] as $widget_id ) {
                if ( $wid_count >= self::MAX_WIDGET_TYPES ) { break; }
                $wid_count++;

                if ( isset( $default_widgets[ $widget_id ] ) ) {
                    /* Shared widget — clone it */
                    $cloned_id = $instance->clone_widget_instance( $widget_id, $menu_map, $clone_cache, $dirty_types );

                    if ( $cloned_id !== $widget_id ) {
                        $new_ids[] = $cloned_id;
                        $cloned++;
                        $sidebar_changed = true;
                    } else {
                        $new_ids[] = $widget_id;
                    }
                } else {
                    /* Unique to target — keep as-is */
                    $new_ids[] = $widget_id;
                }
            }

            if ( $sidebar_changed ) {
                $sidebars[ $target_key ] = $new_ids;
                $changed = true;
            }
        }

        if ( $changed ) {
            update_option( 'sidebars_widgets', $sidebars );
        }

        /* Set migration flag */
        update_option( 'bs_custom_widgets_cloned_' . $target_iso2, true );

        return $cloned;
    }

    /**
     * Remove widget instances that only belong to a language's sidebars.
     *
     * For each widget ID in the target language's sidebars, checks whether
     * that same ID appears in any other language's sidebars. If not, the
     * widget instance data is deleted from the widget option.
     *
     * @param string $iso2 Language code to remove instances for.
     * @return int Number of widget instances removed.
     */
    public function remove_language_widget_instances( string $iso2 ): int {
        assert( ! empty( $iso2 ), 'iso2 must not be empty' );

        $sidebars = get_option( 'sidebars_widgets', array() );

        /* Collect widget IDs assigned to the target language */
        $target_widgets = array();
        $tw_count       = 0;
        $max            = 7;

        foreach ( self::$sidebar_bases as $base ) {
            if ( $tw_count >= $max ) { break; }
            $tw_count++;

            $key = $base . '-' . $iso2;

            if ( ! isset( $sidebars[ $key ] ) || ! is_array( $sidebars[ $key ] ) ) {
                continue;
            }

            $wid_count = 0;

            foreach ( $sidebars[ $key ] as $wid ) {
                if ( $wid_count >= self::MAX_WIDGET_TYPES ) { break; }
                $wid_count++;
                $target_widgets[ $wid ] = true;
            }
        }

        if ( 0 === count( $target_widgets ) ) {
            return 0;
        }

        /* Collect widget IDs used by all OTHER sidebars */
        $other_widgets = array();
        $ow_count      = 0;
        $ow_max        = 200;

        foreach ( $sidebars as $sidebar_id => $widgets ) {
            if ( $ow_count >= $ow_max ) { break; }
            $ow_count++;

            if ( ! is_array( $widgets ) ) {
                continue;
            }

            /* Skip the target language's own sidebars */
            $is_target = false;
            $base_check = 0;

            foreach ( self::$sidebar_bases as $base ) {
                if ( $base_check >= $max ) { break; }
                $base_check++;

                if ( $sidebar_id === $base . '-' . $iso2 ) {
                    $is_target = true;
                    break;
                }
            }

            if ( $is_target ) {
                continue;
            }

            $wid_count = 0;

            foreach ( $widgets as $wid ) {
                if ( $wid_count >= self::MAX_WIDGET_TYPES ) { break; }
                $wid_count++;
                $other_widgets[ $wid ] = true;
            }
        }

        /* Delete orphaned instances (only in target, not in any other sidebar) */
        $removed     = 0;
        $dirty_types = array();

        foreach ( $target_widgets as $widget_id => $unused ) {
            /* If another sidebar still uses this widget, keep it */
            if ( isset( $other_widgets[ $widget_id ] ) ) {
                continue;
            }

            $parsed = self::parse_widget_id( $widget_id );

            if ( null === $parsed ) {
                continue;
            }

            $type            = $parsed[0];
            $instance_number = $parsed[1];
            $option_key      = 'widget_' . $type;

            if ( ! isset( $dirty_types[ $type ] ) ) {
                $dirty_types[ $type ] = get_option( $option_key, array() );
            }

            if ( isset( $dirty_types[ $type ][ $instance_number ] ) ) {
                unset( $dirty_types[ $type ][ $instance_number ] );
                $removed++;
            }
        }

        /* Write back modified widget type options */
        $write_count = 0;

        foreach ( $dirty_types as $type => $instances ) {
            if ( $write_count >= self::MAX_WIDGET_TYPES ) { break; }
            $write_count++;

            update_option( 'widget_' . $type, $instances );
        }

        return $removed;
    }

    /**
     * Copy all post meta from source to target, excluding language-specific keys.
     *
     * @param int $source_id Source post ID.
     * @param int $target_id Target post ID.
     * @return void
     */
    private function copy_post_meta( int $source_id, int $target_id ): void {
        $meta  = get_post_meta( $source_id );
        $count = 0;
        $max   = self::MAX_META_KEYS;

        if ( ! is_array( $meta ) ) {
            return;
        }

        foreach ( $meta as $key => $values ) {
            if ( $count >= $max ) {
                break;
            }

            if ( in_array( $key, self::$excluded_meta_keys, true ) ) {
                continue;
            }

            $count++;

            if ( is_array( $values ) ) {
                $value = isset( $values[0] ) ? $values[0] : '';
            } else {
                $value = $values;
            }

            update_post_meta( $target_id, $key, maybe_unserialize( $value ) );
        }
    }
}
