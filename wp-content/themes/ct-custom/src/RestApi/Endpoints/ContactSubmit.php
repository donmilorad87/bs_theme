<?php
/**
 * REST Contact Submit Endpoint
 *
 * Handles public contact form submissions via POST /wp-json/ct-auth/v1/contact/submit.
 * Rate-limited to 5 per IP per hour. Creates a bs_contact_message CPT post,
 * supports dynamic fields, optional captcha, and file uploads.
 *
 * @package BSCustom\RestApi\Endpoints
 */

namespace BSCustom\RestApi\Endpoints;

use BSCustom\RestApi\RateLimiter;
use BSCustom\RestApi\RestLogger;
use BSCustom\Cpt\ContactMessageCpt;
use BSCustom\Cpt\ContactFormCpt;
use BSCustom\Services\MailService;
use BSCustom\Services\EmailTemplate;

class ContactSubmit {

    use RateLimiter;
    use RestLogger;

    const NAMESPACE       = 'ct-auth/v1';
    const ROUTE           = '/contact/submit';
    const MAX_FILE_SIZE   = 5242880; /* 5 MB */
    const MAX_ATTACHMENTS = 10;

    const ALLOWED_TYPES = array(
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    );

    /**
     * Register the route.
     */
    public function register() {
        assert( function_exists( 'register_rest_route' ), 'register_rest_route must exist' );

        register_rest_route( self::NAMESPACE, self::ROUTE, array(
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'handle' ),
            'permission_callback' => '__return_true',
            'args'                => $this->get_args(),
        ) );
    }

    /**
     * Define endpoint arguments.
     *
     * @return array Argument definitions.
     */
    private function get_args() {
        return array(
            'form_id' => array(
                'required' => true,
                'type'     => 'integer',
            ),
            'captcha_token' => array(
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => '',
            ),
            'captcha_value' => array(
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => '',
            ),
        );
    }

    /**
     * Handle the contact form submission.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response
     */
    public function handle( \WP_REST_Request $request ) {
        assert( $request instanceof \WP_REST_Request, 'Request must be WP_REST_Request' );

        $ip = $this->get_client_ip();

        $form_id = absint( $request->get_param( 'form_id' ) );

        if ( $form_id <= 0 ) {
            $this->log( 'Validation failed: missing form_id' );
            return new \WP_REST_Response( array(
                'success' => false,
                'message' => __( 'Invalid form.', 'ct-custom' ),
            ), 400 );
        }

        $form = ContactFormCpt::get_form( $form_id );
        if ( empty( $form ) ) {
            $this->log( 'Validation failed: form not found, form_id=' . $form_id );
            return new \WP_REST_Response( array(
                'success' => false,
                'message' => __( 'Form not found.', 'ct-custom' ),
            ), 404 );
        }

        $settings = isset( $form['settings'] ) && is_array( $form['settings'] ) ? $form['settings'] : array();
        $fields_config = isset( $form['fields'] ) && is_array( $form['fields'] ) ? $form['fields'] : array();
        $has_name_field = false;
        foreach ( $fields_config as $field ) {
            if ( ! is_array( $field ) ) {
                continue;
            }
            $field_name = isset( $field['name'] ) ? (string) $field['name'] : '';
            if ( in_array( $field_name, array( 'name', 'full_name' ), true ) ) {
                $has_name_field = true;
                break;
            }
        }

        if ( empty( $fields_config ) ) {
            return new \WP_REST_Response( array(
                'success' => false,
                'message' => __( 'Form is missing fields.', 'ct-custom' ),
            ), 400 );
        }

        if ( ! empty( $settings['logged_in_only'] ) && ! is_user_logged_in() ) {
            return new \WP_REST_Response( array(
                'success' => false,
                'message' => __( 'You must be logged in to submit this form.', 'ct-custom' ),
            ), 403 );
        }

        if ( ! empty( $settings['captcha_enabled'] ) ) {
            $captcha_token = sanitize_text_field( (string) $request->get_param( 'captcha_token' ) );
            $captcha_value = sanitize_text_field( (string) $request->get_param( 'captcha_value' ) );
            if ( ! $this->verify_captcha( $captcha_token, $captcha_value ) ) {
                return new \WP_REST_Response( array(
                    'success' => false,
                    'message' => __( 'Invalid captcha. Please try again.', 'ct-custom' ),
                ), 400 );
            }
        }

        $params = $request->get_params();
        $files  = $request->get_file_params();

        $result = $this->collect_field_data( $fields_config, $params, $files, $settings, $form_id );

        if ( is_wp_error( $result ) ) {
            $this->log( 'Validation failed: ' . $result->get_error_message() );
            return new \WP_REST_Response( array(
                'success' => false,
                'message' => $result->get_error_message(),
            ), 400 );
        }

        $fields_data  = $result['fields'];
        $attachments  = $result['attachments'];
        $sender_name  = $result['sender_name'];
        $sender_email = $result['sender_email'];
        $sender_phone = $result['sender_phone'];
        $body         = $result['body'];

        $post_title = $sender_name . ' — ' . ( $form['title'] ? $form['title'] : __( 'Contact Form', 'ct-custom' ) );

        $user_id = get_current_user_id();
        $confirm_email = $sender_email;

        if ( empty( $confirm_email ) && $user_id > 0 ) {
            $user = wp_get_current_user();
            if ( $user && $user->ID > 0 && ! empty( $user->user_email ) ) {
                $confirm_email = $user->user_email;
                if ( empty( $sender_email ) ) {
                    $sender_email = $confirm_email;
                }
            }
        }

        if ( $user_id > 0 && ! $has_name_field ) {
            $user = wp_get_current_user();
            if ( $user && $user->ID > 0 ) {
                $username = isset( $user->user_login ) ? (string) $user->user_login : '';
                $full_name = trim( (string) $user->first_name . ' ' . (string) $user->last_name );
                if ( '' !== $username ) {
                    $sender_name = $username;
                    if ( '' !== $full_name ) {
                        $sender_name .= ' (' . $full_name . ')';
                    }
                } elseif ( '' !== $full_name ) {
                    $sender_name = $full_name;
                } elseif ( ! empty( $user->display_name ) ) {
                    $sender_name = (string) $user->display_name;
                }
            }
        }

        $post_id = wp_insert_post( array(
            'post_type'   => ContactMessageCpt::POST_TYPE,
            'post_title'  => sanitize_text_field( $post_title ),
            'post_status' => 'publish',
        ) );

        if ( is_wp_error( $post_id ) || 0 === $post_id ) {
            $this->log( 'Server error: wp_insert_post failed for contact message' );
            return new \WP_REST_Response( array(
                'success' => false,
                'message' => __( 'Failed to save message.', 'ct-custom' ),
            ), 500 );
        }

        update_post_meta( $post_id, '_ct_msg_sender_name', $sender_name );
        update_post_meta( $post_id, '_ct_msg_sender_email', $sender_email );
        update_post_meta( $post_id, '_ct_msg_sender_phone', $sender_phone );
        update_post_meta( $post_id, '_ct_msg_body', $body );
        update_post_meta( $post_id, '_ct_msg_form_id', $form_id );
        update_post_meta( $post_id, '_ct_msg_form_label', $form['title'] );
        update_post_meta( $post_id, '_ct_msg_fields', wp_json_encode( $fields_data ) );
        update_post_meta( $post_id, '_ct_msg_attachments', wp_json_encode( $attachments ) );
        update_post_meta( $post_id, '_ct_msg_user_id', $user_id );
        update_post_meta( $post_id, '_ct_msg_is_read', '0' );
        update_post_meta( $post_id, '_ct_msg_replies', '[]' );
        update_post_meta( $post_id, '_ct_msg_ip', $ip );

        $email_enabled = true;
        if ( function_exists( 'bs_email_enabled' ) ) {
            $email_enabled = bs_email_enabled();
        }

        if ( $email_enabled ) {
            $this->send_notification( $settings, $sender_name, $sender_email, $sender_phone, $body, $form['title'], $attachments );
            $this->send_confirmation( $confirm_email, $sender_name, $form['title'] );
        }

        return new \WP_REST_Response( array(
            'success' => true,
            'message' => __( 'Your message has been sent successfully.', 'ct-custom' ),
        ), 200 );
    }

    /**
     * Collect and validate form field data.
     *
     * @param array $fields_config Configured fields.
     * @param array $params        Request params.
     * @param array $files         Uploaded files.
     * @param array $settings      Form settings.
     * @param int   $form_id       Form ID.
     * @return array|\WP_Error
     */
    private function collect_field_data( array $fields_config, array $params, array $files, array $settings, int $form_id ) {
        $fields      = array();
        $attachments = array();
        $file_count  = 0;

        foreach ( $fields_config as $field ) {
            if ( ! is_array( $field ) ) {
                continue;
            }

            $type     = isset( $field['type'] ) ? $field['type'] : 'text';
            $name     = isset( $field['name'] ) ? $field['name'] : '';
            $label    = isset( $field['label'] ) ? $field['label'] : $name;
            $required = ! empty( $field['required'] );

            if ( '' === $name ) {
                continue;
            }

            $value = '';

            if ( 'file' === $type ) {
                $uploads_enabled = isset( $settings['file_uploads']['enabled'] ) && $settings['file_uploads']['enabled'];
                $accept          = isset( $field['accept'] ) ? (string) $field['accept'] : '';

                if ( ! $uploads_enabled ) {
                    if ( $required ) {
                        return new \WP_Error( 'required', __( 'Please upload the required file.', 'ct-custom' ) );
                    }
                    continue;
                }

                if ( $file_count >= self::MAX_ATTACHMENTS ) {
                    continue;
                }

                $file = isset( $files[ $name ] ) ? $files[ $name ] : null;
                if ( empty( $file ) || ! is_array( $file ) ) {
                    if ( $required ) {
                        return new \WP_Error( 'required', __( 'Please upload the required file.', 'ct-custom' ) );
                    }
                    continue;
                }

                if ( isset( $file['error'] ) && UPLOAD_ERR_NO_FILE === (int) $file['error'] ) {
                    if ( $required ) {
                        return new \WP_Error( 'required', __( 'Please upload the required file.', 'ct-custom' ) );
                    }
                    continue;
                }

                $upload = $this->upload_file( $file, $settings, $form_id, $accept );
                if ( is_wp_error( $upload ) ) {
                    return $upload;
                }

                $file_count++;
                $attachments[] = $upload;
                $value = $upload['url'];
            } else {
                $raw_value = isset( $params[ $name ] ) ? $params[ $name ] : '';
                if ( is_array( $raw_value ) ) {
                    $clean = array();
                    foreach ( $raw_value as $val ) {
                        $val = sanitize_text_field( $val );
                        if ( '' !== $val ) {
                            $clean[] = $val;
                        }
                    }
                    $value = $clean;
                } else {
                    $value = $this->sanitize_field_value( $raw_value, $type );
                }
            }

            if ( $required ) {
                $is_empty = false;
                if ( 'checkbox' === $type ) {
                    $is_empty = empty( $value );
                } elseif ( 'checkbox_group' === $type ) {
                    $is_empty = empty( $value ) || ! is_array( $value );
                } elseif ( 'file' === $type ) {
                    $is_empty = empty( $value );
                } else {
                    $is_empty = '' === $value || ( is_array( $value ) && empty( $value ) );
                }

                if ( $is_empty ) {
                    return new \WP_Error( 'required', __( 'Please fill in all required fields.', 'ct-custom' ) );
                }
            }

            if ( 'email' === $type && '' !== $value && ! is_email( $value ) ) {
                return new \WP_Error( 'email', __( 'Please enter a valid email address.', 'ct-custom' ) );
            }

            $fields[] = array(
                'name'  => $name,
                'label' => $label,
                'type'  => $type,
                'value' => $value,
            );
        }

        $sender_name  = '';
        $sender_email = '';
        $sender_phone = '';
        $body         = '';

        foreach ( $fields as $field ) {
            $value = $field['value'];
            $value_string = is_array( $value ) ? implode( ', ', $value ) : (string) $value;

            if ( '' === $sender_email && 'email' === $field['type'] && is_email( $value_string ) ) {
                $sender_email = $value_string;
            }

            if ( '' === $sender_name && ( 'name' === $field['name'] || 'full_name' === $field['name'] ) && '' !== $value_string ) {
                $sender_name = $value_string;
            }

            if ( '' === $sender_phone && ( 'phone' === $field['name'] || 'tel' === $field['type'] ) && '' !== $value_string ) {
                $sender_phone = $value_string;
            }

            if ( '' === $body && ( 'message' === $field['name'] || 'textarea' === $field['type'] ) && '' !== $value_string ) {
                $body = $value_string;
            }
        }

        if ( '' === $sender_name ) {
            $sender_name = __( 'Guest', 'ct-custom' );
        }

        if ( '' === $body ) {
            $body = $this->build_fields_summary( $fields, $attachments );
        } else {
            $extra = $this->build_fields_summary( $fields, $attachments, array( 'name', 'email', 'phone', 'message', 'full_name' ) );
            if ( '' !== $extra ) {
                $body .= "\n\n" . $extra;
            }
        }

        return array(
            'fields'       => $fields,
            'attachments'  => $attachments,
            'sender_name'  => $sender_name,
            'sender_email' => $sender_email,
            'sender_phone' => $sender_phone,
            'body'         => $body,
        );
    }

    /**
     * Build a summary string of field values.
     *
     * @param array $fields        Fields array.
     * @param array $attachments   Attachment list.
     * @param array $exclude_names Field names to exclude.
     * @return string
     */
    private function build_fields_summary( array $fields, array $attachments, array $exclude_names = array() ): string {
        $lines = array();

        foreach ( $fields as $field ) {
            if ( in_array( $field['name'], $exclude_names, true ) ) {
                continue;
            }
            $value = $field['value'];
            if ( is_array( $value ) ) {
                $value = implode( ', ', $value );
            }
            if ( '' === $value ) {
                continue;
            }
            $lines[] = $field['label'] . ': ' . $value;
        }

        foreach ( $attachments as $attachment ) {
            if ( isset( $attachment['url'] ) ) {
                $lines[] = __( 'Attachment', 'ct-custom' ) . ': ' . $attachment['url'];
            }
        }

        return implode( "\n", $lines );
    }

    /**
     * Sanitize a field value by type.
     *
     * @param mixed  $value Field value.
     * @param string $type  Field type.
     * @return string
     */
    private function sanitize_field_value( $value, string $type ): string {
        $value = is_string( $value ) ? $value : '';

        if ( 'email' === $type ) {
            return sanitize_email( $value );
        }

        if ( 'textarea' === $type ) {
            return sanitize_textarea_field( $value );
        }

        return sanitize_text_field( $value );
    }

    /**
     * Upload a file using WordPress or S3 storage.
     *
     * @param array $file     File array.
     * @param array $settings Form settings.
     * @param int   $form_id  Form ID.
     * @return array|\WP_Error
     */
    private function upload_file( array $file, array $settings, int $form_id, string $accept = '' ) {
        $validation = $this->validate_file( $file, $accept );
        if ( is_wp_error( $validation ) ) {
            return $validation;
        }

        $storage = isset( $settings['file_uploads']['storage'] ) ? $settings['file_uploads']['storage'] : 'wordpress';

        if ( 's3' === $storage ) {
            $s3 = isset( $settings['file_uploads']['s3'] ) && is_array( $settings['file_uploads']['s3'] ) ? $settings['file_uploads']['s3'] : array();
            return $this->upload_to_s3( $file, $s3, $form_id );
        }

        return $this->upload_to_wordpress( $file );
    }

    /**
     * Validate uploaded file.
     *
     * @param array $file File array.
     * @return true|\WP_Error
     */
    private function validate_file( array $file, string $accept = '' ) {
        if ( isset( $file['error'] ) && UPLOAD_ERR_OK !== (int) $file['error'] ) {
            $code = (int) $file['error'];
            if ( UPLOAD_ERR_INI_SIZE === $code || UPLOAD_ERR_FORM_SIZE === $code ) {
                return new \WP_Error( 'file_too_large', __( 'File must be less than 5MB.', 'ct-custom' ) );
            }
            if ( UPLOAD_ERR_PARTIAL === $code ) {
                return new \WP_Error( 'upload_partial', __( 'File upload was interrupted. Please try again.', 'ct-custom' ) );
            }
            if ( UPLOAD_ERR_NO_FILE === $code ) {
                return new \WP_Error( 'no_file', __( 'No file uploaded.', 'ct-custom' ) );
            }
            return new \WP_Error( 'upload_error', __( 'File upload error.', 'ct-custom' ) );
        }

        if ( $file['size'] > self::MAX_FILE_SIZE ) {
            return new \WP_Error( 'file_too_large', __( 'File must be less than 5MB.', 'ct-custom' ) );
        }

        $mime = isset( $file['type'] ) ? strtolower( (string) $file['type'] ) : '';
        if ( '' === $mime && ! empty( $file['name'] ) ) {
            $filetype = wp_check_filetype( $file['name'] );
            $mime = isset( $filetype['type'] ) ? strtolower( (string) $filetype['type'] ) : '';
        }
        $ext = '';
        if ( ! empty( $file['name'] ) ) {
            $ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
        }

        $accept_list = $this->parse_accept_list( $accept );
        if ( empty( $accept_list ) ) {
            if ( ! in_array( $mime, self::ALLOWED_TYPES, true ) ) {
                return new \WP_Error( 'invalid_type', __( 'Only image files (JPEG, PNG, GIF, WebP) are allowed.', 'ct-custom' ) );
            }
            return true;
        }

        if ( ! $this->matches_accept( $mime, $ext, $accept_list ) ) {
            $allowed_text = $this->format_accept_tokens( $accept_list );
            return new \WP_Error(
                'invalid_type',
                sprintf(
                    /* translators: %s: allowed types */
                    __( 'File type not allowed. Allowed: %s.', 'ct-custom' ),
                    $allowed_text
                )
            );
        }

        return true;
    }

    /**
     * Parse the accept string into tokens.
     *
     * @param string $accept Accept string.
     * @return array
     */
    private function parse_accept_list( string $accept ): array {
        $accept = trim( $accept );
        if ( '' === $accept ) {
            return array();
        }

        $parts = array_map( 'trim', explode( ',', $accept ) );
        $tokens = array();

        foreach ( $parts as $part ) {
            if ( '' === $part ) {
                continue;
            }
            $tokens[] = strtolower( $part );
        }

        return $tokens;
    }

    /**
     * Check whether a file matches the accept list.
     *
     * @param string $mime   File mime type.
     * @param string $ext    File extension.
     * @param array  $accept List of accept tokens.
     * @return bool
     */
    private function matches_accept( string $mime, string $ext, array $accept ): bool {
        foreach ( $accept as $token ) {
            if ( '' === $token ) {
                continue;
            }

            if ( '*' === $token || '*/*' === $token ) {
                return true;
            }

            if ( substr( $token, -2 ) === '/*' ) {
                $prefix = substr( $token, 0, -1 );
                if ( '' !== $mime && strpos( $mime, $prefix ) === 0 ) {
                    return true;
                }
                continue;
            }

            if ( strpos( $token, '/' ) !== false ) {
                if ( '' !== $mime && $mime === $token ) {
                    return true;
                }
                continue;
            }

            if ( $token[0] === '.' ) {
                $token = substr( $token, 1 );
            }

            if ( '' !== $ext && $token === $ext ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Format accept tokens for error messaging.
     *
     * @param array $accept Accept tokens.
     * @return string
     */
    private function format_accept_tokens( array $accept ): string {
        $tokens = array();
        foreach ( $accept as $token ) {
            $token = trim( (string) $token );
            if ( '' === $token ) {
                continue;
            }
            $tokens[] = $token;
        }
        if ( empty( $tokens ) ) {
            return __( 'file types configured', 'ct-custom' );
        }
        return implode( ', ', $tokens );
    }

    /**
     * Upload file to WordPress media library.
     *
     * @param array $file File array.
     * @return array|\WP_Error
     */
    private function upload_to_wordpress( array $file ) {
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $mime = isset( $file['type'] ) ? (string) $file['type'] : '';
        if ( '' === $mime && isset( $file['name'] ) ) {
            $filetype = wp_check_filetype( $file['name'] );
            $mime = isset( $filetype['type'] ) ? (string) $filetype['type'] : '';
        }
        $is_video = ( 0 === strpos( $mime, 'video/' ) );
        $is_audio = ( 0 === strpos( $mime, 'audio/' ) );

        if ( $is_video || $is_audio ) {
            return $this->upload_to_wordpress_basic( $file );
        }

        $attachment_id = media_handle_sideload( array(
            'name'     => sanitize_file_name( $file['name'] ),
            'type'     => $file['type'],
            'tmp_name' => $file['tmp_name'],
            'error'    => $file['error'],
            'size'     => $file['size'],
        ), 0 );

        if ( is_wp_error( $attachment_id ) ) {
            return new \WP_Error( 'upload_failed', __( 'Failed to upload file.', 'ct-custom' ) );
        }

        $url = wp_get_attachment_url( $attachment_id );

        return array(
            'id'   => (int) $attachment_id,
            'url'  => $url ? $url : '',
            'name' => sanitize_file_name( $file['name'] ),
            'type' => $file['type'],
        );
    }

    /**
     * Upload file to WordPress without generating metadata (avoids ID3 parsing).
     *
     * @param array $file File array.
     * @return array|\WP_Error
     */
    private function upload_to_wordpress_basic( array $file ) {
        $file_array = array(
            'name'     => sanitize_file_name( $file['name'] ),
            'type'     => $file['type'],
            'tmp_name' => $file['tmp_name'],
            'error'    => $file['error'],
            'size'     => $file['size'],
        );
        $upload = wp_handle_sideload( $file_array, array( 'test_form' => false ) );

        if ( ! is_array( $upload ) || isset( $upload['error'] ) ) {
            return new \WP_Error( 'upload_failed', __( 'Failed to upload file.', 'ct-custom' ) );
        }

        $attachment = array(
            'post_mime_type' => $upload['type'],
            'post_title'     => sanitize_file_name( $file['name'] ),
            'post_status'    => 'inherit',
            'guid'           => $upload['url'],
        );

        $attachment_id = wp_insert_attachment( $attachment, $upload['file'] );

        if ( is_wp_error( $attachment_id ) || 0 === $attachment_id ) {
            return new \WP_Error( 'upload_failed', __( 'Failed to upload file.', 'ct-custom' ) );
        }

        return array(
            'id'   => (int) $attachment_id,
            'url'  => $upload['url'],
            'name' => sanitize_file_name( $file['name'] ),
            'type' => $upload['type'],
        );
    }

    /**
     * Upload file to S3 using a simple signed PUT request.
     *
     * @param array $file File array.
     * @param array $s3   S3 config.
     * @param int   $form_id Form ID.
     * @return array|\WP_Error
     */
    private function upload_to_s3( array $file, array $s3, int $form_id ) {
        $bucket = isset( $s3['bucket'] ) ? trim( $s3['bucket'] ) : '';
        $access = isset( $s3['access_key'] ) ? trim( $s3['access_key'] ) : '';
        $secret = isset( $s3['secret_key'] ) ? trim( $s3['secret_key'] ) : '';

        if ( '' === $bucket || '' === $access || '' === $secret ) {
            return new \WP_Error( 's3_config', __( 'S3 credentials are incomplete.', 'ct-custom' ) );
        }

        $bucket = preg_replace( '#^https?://#', '', $bucket );
        $bucket = trim( $bucket );
        $bucket = trim( $bucket, '/' );
        $parts  = explode( '/', $bucket, 2 );
        $host   = $parts[0];
        $prefix = isset( $parts[1] ) ? $parts[1] : '';

        $filename = sanitize_file_name( $file['name'] );
        $key      = ( $prefix ? $prefix . '/' : '' ) . 'contact-forms/' . $form_id . '/' . gmdate( 'Y/m' ) . '/' . uniqid( '', true ) . '-' . $filename;
        $url      = 'https://' . $host . '/' . $key;

        $payload = file_get_contents( $file['tmp_name'] );
        if ( false === $payload ) {
            return new \WP_Error( 's3_read', __( 'Unable to read upload.', 'ct-custom' ) );
        }

        $region = 'us-east-1';
        $service = 's3';
        $amz_date = gmdate( 'Ymd\THis\Z' );
        $date_stamp = gmdate( 'Ymd' );
        $payload_hash = hash( 'sha256', $payload );

        $canonical_uri = '/' . $key;
        $canonical_headers = 'host:' . $host . "\n" .
            'x-amz-content-sha256:' . $payload_hash . "\n" .
            'x-amz-date:' . $amz_date . "\n";
        $signed_headers = 'host;x-amz-content-sha256;x-amz-date';
        $canonical_request = "PUT\n" . $canonical_uri . "\n\n" . $canonical_headers . "\n" . $signed_headers . "\n" . $payload_hash;
        $credential_scope = $date_stamp . '/' . $region . '/' . $service . '/aws4_request';
        $string_to_sign = "AWS4-HMAC-SHA256\n" . $amz_date . "\n" . $credential_scope . "\n" . hash( 'sha256', $canonical_request );

        $signing_key = $this->get_s3_signing_key( $secret, $date_stamp, $region, $service );
        $signature = hash_hmac( 'sha256', $string_to_sign, $signing_key );

        $authorization = 'AWS4-HMAC-SHA256 Credential=' . $access . '/' . $credential_scope . ', SignedHeaders=' . $signed_headers . ', Signature=' . $signature;

        $headers = array(
            'Authorization'       => $authorization,
            'x-amz-content-sha256' => $payload_hash,
            'x-amz-date'          => $amz_date,
            'Content-Type'        => $file['type'],
            'Expect'              => '',
        );

        $response = wp_remote_request( $url, array(
            'method'  => 'PUT',
            'headers' => $headers,
            'body'    => $payload,
            'timeout' => 20,
        ) );

        if ( is_wp_error( $response ) ) {
            return new \WP_Error( 's3_upload', __( 'Failed to upload to S3.', 'ct-custom' ) );
        }

        $status = wp_remote_retrieve_response_code( $response );
        if ( $status < 200 || $status >= 300 ) {
            return new \WP_Error( 's3_upload', __( 'Failed to upload to S3.', 'ct-custom' ) );
        }

        $attachment_id = $this->create_attachment_from_url( $url, $file );

        return array(
            'id'   => $attachment_id,
            'url'  => $url,
            'name' => $filename,
            'type' => $file['type'],
        );
    }

    /**
     * Create a media attachment post for a remote URL.
     *
     * @param string $url  Remote URL.
     * @param array  $file File array.
     * @return int
     */
    private function create_attachment_from_url( string $url, array $file ): int {
        $attachment = array(
            'post_mime_type' => $file['type'],
            'post_title'     => sanitize_file_name( $file['name'] ),
            'post_status'    => 'inherit',
            'guid'           => $url,
        );

        $attachment_id = wp_insert_attachment( $attachment, $url );
        if ( is_wp_error( $attachment_id ) ) {
            return 0;
        }

        update_post_meta( $attachment_id, '_wp_attached_file', $url );

        return (int) $attachment_id;
    }

    /**
     * Build the signing key for S3 Signature v4.
     *
     * @param string $secret Secret key.
     * @param string $date   Date stamp.
     * @param string $region AWS region.
     * @param string $service Service name.
     * @return string
     */
    private function get_s3_signing_key( string $secret, string $date, string $region, string $service ): string {
        $k_date    = hash_hmac( 'sha256', $date, 'AWS4' . $secret, true );
        $k_region  = hash_hmac( 'sha256', $region, $k_date, true );
        $k_service = hash_hmac( 'sha256', $service, $k_region, true );
        return hash_hmac( 'sha256', 'aws4_request', $k_service, true );
    }

    /**
     * Verify captcha token/value.
     *
     * @param string $token Captcha token.
     * @param string $value Captcha value.
     * @return bool
     */
    private function verify_captcha( string $token, string $value ): bool {
        if ( '' === $token || '' === $value ) {
            return false;
        }

        $hash = get_transient( 'bs_contact_captcha_' . $token );
        if ( ! $hash ) {
            return false;
        }

        $valid = wp_check_password( strtolower( $value ), $hash );
        delete_transient( 'bs_contact_captcha_' . $token );

        return $valid;
    }

    /**
     * Send confirmation email to the sender.
     *
     * @param string $sender_email Sender email address.
     * @param string $sender_name  Sender name.
     * @param string $form_title   Form title.
     */
    private function send_confirmation( $sender_email, $sender_name, $form_title ) {
        if ( empty( $sender_email ) || ! is_email( $sender_email ) ) {
            return;
        }

        $email_tpl    = new EmailTemplate();
        $mail_service = new MailService();
        $html         = $email_tpl->contact_confirmation( $sender_name, $form_title );
        $subject      = __( 'Message Received', 'ct-custom' ) . ' — ' . esc_html( get_bloginfo( 'name' ) );

        $mail_service->send( $sender_email, $subject, $html );
    }

    /**
     * Send email notification to form-linked addresses.
     *
     * @param array  $settings     Form settings.
     * @param string $name         Sender name.
     * @param string $email        Sender email.
     * @param string $phone        Sender phone.
     * @param string $message      Message body.
     * @param string $form_title   Form title.
     * @param array  $attachments  Attachment list.
     */
    private function send_notification( $settings, $name, $email, $phone, $message, $form_title, $attachments ) {
        $emails = isset( $settings['emails'] ) && is_array( $settings['emails'] ) ? $settings['emails'] : array();

        if ( empty( $emails ) ) {
            return;
        }

        $email_attachments = $this->build_email_attachments( $attachments );
        $temp_files = array();
        foreach ( $email_attachments as $attachment ) {
            if ( isset( $attachment['temp'] ) && $attachment['temp'] && isset( $attachment['path'] ) ) {
                $temp_files[] = $attachment['path'];
            }
        }

        if ( ! empty( $attachments ) ) {
            $message .= "\n\n" . __( 'Attachments', 'ct-custom' ) . ":\n";
            foreach ( $attachments as $attachment ) {
                if ( isset( $attachment['url'] ) ) {
                    $message .= $attachment['url'] . "\n";
                }
            }
        }

        $email_tpl     = new EmailTemplate();
        $mail_service  = new MailService();
        $html          = $email_tpl->contact_notification( $name, $email, $phone, $message, $form_title );
        $subject       = __( 'New Contact Message', 'ct-custom' ) . ' — ' . $form_title;

        $max_recipients = ContactFormCpt::MAX_EMAILS;
        $count          = 0;

        foreach ( $emails as $recipient ) {
            if ( $count >= $max_recipients ) {
                break;
            }
            $count++;

            $mail_service->send( $recipient, $subject, $html, $email_attachments );
        }

        if ( ! empty( $temp_files ) ) {
            foreach ( $temp_files as $tmp ) {
                if ( is_string( $tmp ) && file_exists( $tmp ) ) {
                    @unlink( $tmp );
                }
            }
        }
    }

    /**
     * Build email attachment paths from uploaded files.
     *
     * @param array $attachments Attachment metadata.
     * @return array
     */
    private function build_email_attachments( array $attachments ): array {
        if ( empty( $attachments ) ) {
            return array();
        }

        $files = array();
        $limit = 5;
        $count = 0;

        foreach ( $attachments as $attachment ) {
            if ( $count >= $limit ) {
                break;
            }
            $count++;

            $name = isset( $attachment['name'] ) ? sanitize_file_name( $attachment['name'] ) : '';
            $id   = isset( $attachment['id'] ) ? absint( $attachment['id'] ) : 0;
            $path = '';

            if ( $id > 0 ) {
                $path = get_attached_file( $id );
                if ( $path && file_exists( $path ) ) {
                    $files[] = array(
                        'path' => $path,
                        'name' => $name,
                    );
                    continue;
                }
            }

            $url = isset( $attachment['url'] ) ? (string) $attachment['url'] : '';
            if ( '' === $url || 0 !== strpos( $url, 'http' ) ) {
                continue;
            }

            if ( ! function_exists( 'download_url' ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }

            $tmp = download_url( $url );
            if ( is_wp_error( $tmp ) ) {
                continue;
            }

            $files[] = array(
                'path' => $tmp,
                'name' => $name ? $name : basename( $url ),
                'temp' => true,
            );
        }

        return $files;
    }
}
