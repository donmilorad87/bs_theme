<?php

namespace BSCustom\Cpt;

class ContactFormCpt {

    const POST_TYPE   = 'bs_contact_form';
    const MAX_FORMS   = 50;
    const MAX_FIELDS  = 200;
    const MAX_EMAILS  = 10;

    /**
     * Register the custom post type and seed a default form.
     */
    public function register() {
        assert( function_exists( 'register_post_type' ), 'register_post_type must exist' );
        assert( ! post_type_exists( self::POST_TYPE ), 'CPT must not already be registered' );

        register_post_type( self::POST_TYPE, array(
            'labels' => array(
                'name'          => __( 'Contact Forms', 'ct-custom' ),
                'singular_name' => __( 'Contact Form', 'ct-custom' ),
            ),
            'public'              => false,
            'show_ui'             => false,
            'show_in_menu'        => false,
            'show_in_rest'        => false,
            'supports'            => array( 'title' ),
            'capability_type'     => 'post',
            'has_archive'         => false,
            'exclude_from_search' => true,
        ) );

        $this->maybe_seed_default_form();
    }

    private function maybe_seed_default_form() {
        $existing = get_posts( array(
            'post_type'      => self::POST_TYPE,
            'post_status'    => array( 'publish', 'draft', 'private' ),
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ) );

        if ( ! empty( $existing ) ) {
            return;
        }

        $form_id = wp_insert_post( array(
            'post_type'   => self::POST_TYPE,
            'post_status' => 'publish',
            'post_title'  => __( 'Contact Us', 'ct-custom' ),
        ) );

        if ( is_wp_error( $form_id ) || ! $form_id ) {
            return;
        }

        $config = self::get_default_config();

        update_post_meta( $form_id, '_bs_contact_form_config', wp_json_encode( $config ) );
    }

    /**
     * Default form config structure.
     *
     * @return array
     */
    public static function get_default_config(): array {
        $admin_email = get_option( 'admin_email', '' );

        return array(
            'settings' => array(
                'emails'          => $admin_email ? array( $admin_email ) : array(),
                'logged_in_only'  => false,
                'captcha_enabled' => true,
                'file_uploads'    => array(
                    'enabled' => false,
                    'storage' => 'wordpress',
                    's3'      => array(
                        'bucket'     => '',
                        'access_key' => '',
                        'secret_key' => '',
                    ),
                ),
            ),
            'fields' => array(
                array(
                    'id'          => 'name',
                    'type'        => 'text',
                    'label'       => 'Name',
                    'name'        => 'name',
                    'placeholder' => '',
                    'required'    => true,
                    'options'     => array(),
                    'conditions'  => array(),
                    'min'         => '',
                    'max'         => '',
                    'step'        => '',
                    'default'     => '',
                    'accept'      => '',
                ),
                array(
                    'id'          => 'email',
                    'type'        => 'email',
                    'label'       => 'Email',
                    'name'        => 'email',
                    'placeholder' => '',
                    'required'    => true,
                    'options'     => array(),
                    'conditions'  => array(),
                    'min'         => '',
                    'max'         => '',
                    'step'        => '',
                    'default'     => '',
                    'accept'      => '',
                ),
                array(
                    'id'          => 'phone',
                    'type'        => 'tel',
                    'label'       => 'Phone',
                    'name'        => 'phone',
                    'placeholder' => '',
                    'required'    => false,
                    'options'     => array(),
                    'conditions'  => array(),
                    'min'         => '',
                    'max'         => '',
                    'step'        => '',
                    'default'     => '',
                    'accept'      => '',
                ),
                array(
                    'id'          => 'message',
                    'type'        => 'textarea',
                    'label'       => 'Message',
                    'name'        => 'message',
                    'placeholder' => '',
                    'required'    => true,
                    'options'     => array(),
                    'conditions'  => array(),
                    'min'         => '',
                    'max'         => '',
                    'step'        => '',
                    'default'     => '',
                    'accept'      => '',
                ),
            ),
        );
    }

    /**
     * Normalize config array shape for consistent output.
     *
     * @param array $config Raw config.
     * @return array
     */
    public static function normalize_config( array $config ): array {
        $defaults = self::get_default_config();

        $settings = isset( $config['settings'] ) && is_array( $config['settings'] ) ? $config['settings'] : array();
        $settings = array_merge( $defaults['settings'], $settings );
        $settings['file_uploads'] = array_merge(
            $defaults['settings']['file_uploads'],
            isset( $settings['file_uploads'] ) && is_array( $settings['file_uploads'] ) ? $settings['file_uploads'] : array()
        );
        $settings['file_uploads']['s3'] = array_merge(
            $defaults['settings']['file_uploads']['s3'],
            isset( $settings['file_uploads']['s3'] ) && is_array( $settings['file_uploads']['s3'] ) ? $settings['file_uploads']['s3'] : array()
        );

        $fields = isset( $config['fields'] ) && is_array( $config['fields'] ) ? $config['fields'] : $defaults['fields'];
        if ( ! is_array( $fields ) ) {
            $fields = $defaults['fields'];
        }

        return array(
            'settings' => $settings,
            'fields'   => $fields,
        );
    }

    /**
     * Return form config array for a given form ID.
     *
     * @param int $form_id Form ID.
     * @return array
     */
    public static function get_form_config( int $form_id ): array {
        $raw = get_post_meta( $form_id, '_bs_contact_form_config', true );

        if ( ! is_string( $raw ) || '' === $raw ) {
            return array();
        }

        $config = json_decode( $raw, true );

        if ( ! is_array( $config ) ) {
            return array();
        }

        return self::normalize_config( $config );
    }

    /**
     * Get a full form payload (id, title, settings, fields).
     *
     * @param int $form_id Form ID.
     * @return array
     */
    public static function get_form( int $form_id ): array {
        $post = get_post( $form_id );
        if ( ! $post || self::POST_TYPE !== $post->post_type ) {
            return array();
        }

        $config = self::get_form_config( $form_id );

        return array(
            'id'       => (int) $post->ID,
            'title'    => $post->post_title,
            'settings' => isset( $config['settings'] ) ? $config['settings'] : array(),
            'fields'   => isset( $config['fields'] ) ? $config['fields'] : array(),
        );
    }

    /**
     * Get the most recent form id.
     *
     * @return int
     */
    public static function get_first_form_id(): int {
        $posts = get_posts( array(
            'post_type'      => self::POST_TYPE,
            'post_status'    => array( 'publish', 'draft', 'private' ),
            'posts_per_page' => 1,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'fields'         => 'ids',
        ) );

        if ( empty( $posts ) ) {
            return 0;
        }

        return (int) $posts[0];
    }

    /**
     * Return list of forms (id + title).
     *
     * @param int $limit Max forms.
     * @return array
     */
    public static function get_forms( int $limit = 50 ): array {
        $limit = max( 1, min( self::MAX_FORMS, $limit ) );

        $posts = get_posts( array(
            'post_type'      => self::POST_TYPE,
            'post_status'    => array( 'publish', 'draft', 'private' ),
            'posts_per_page' => $limit,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );

        $forms = array();
        foreach ( $posts as $post ) {
            $forms[] = array(
                'id'    => (int) $post->ID,
                'title' => $post->post_title,
            );
        }

        return $forms;
    }
}
