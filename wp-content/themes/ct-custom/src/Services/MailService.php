<?php

namespace BSCustom\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

class MailService {

    const MAX_RECIPIENT_LEN = 320;
    const MAX_SUBJECT_LEN   = 998;

    /** @var array|null Cached config from options. */
    private $config_cache = null;

    /**
     * Load and cache email config from the bs_custom_email_config option.
     *
     * @return array Config array with defaults applied.
     */
    private function get_config() {
        if ( null !== $this->config_cache ) {
            return $this->config_cache;
        }

        $raw    = get_option( 'bs_custom_email_config', '{}' );
        $config = json_decode( $raw, true );

        if ( ! is_array( $config ) ) {
            $config = array();
        }

        $defaults = array(
            'host'       => '',
            'port'       => 587,
            'username'   => '',
            'password'   => '',
            'encryption' => 'tls',
            'from_email' => '',
            'from_name'  => '',
        );

        $max_keys = 7;
        $count    = 0;

        foreach ( $defaults as $key => $default ) {
            if ( $count >= $max_keys ) {
                break;
            }
            $count++;

            if ( ! isset( $config[ $key ] ) ) {
                $config[ $key ] = $default;
            }
        }

        assert( is_array( $config ), 'Config must be an array' );

        $this->config_cache = $config;
        return $config;
    }

    /**
     * Send an HTML email.
     *
     * @param string $to      Recipient email address.
     * @param string $subject Email subject.
     * @param string $body    HTML body content.
     * @return bool True on success, false on failure.
     */
    public function send( $to, $subject, $body ) {
        assert( is_string( $to ) && strlen( $to ) > 0, 'Recipient must be a non-empty string' );
        assert( is_string( $subject ) && strlen( $subject ) <= self::MAX_SUBJECT_LEN, 'Subject must be valid' );

        if ( ! class_exists( PHPMailer::class ) ) {
            return false;
        }

        $mail = new PHPMailer( true );

        try {
            $this->configure_smtp( $mail );
            $this->configure_sender( $mail );
            $this->configure_recipient( $mail, $to );
            $this->configure_content( $mail, $subject, $body );

            $mail->send();
            return true;
        } catch ( PHPMailerException $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[CT_Mail_Service] PHPMailer error: ' . $e->getMessage() );
            }
            return false;
        }
    }

    /**
     * Configure SMTP transport from Customizer settings.
     *
     * @param PHPMailer $mail PHPMailer instance.
     */
    private function configure_smtp( PHPMailer $mail ) {
        assert( $mail instanceof PHPMailer, 'Must receive PHPMailer instance' );

        $config = $this->get_config();
        $host   = $config['host'];

        if ( empty( $host ) ) {
            return;
        }

        $mail->isSMTP();
        $mail->Host       = $host;
        $mail->Port       = (int) $config['port'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['username'];
        $mail->Password   = $config['password'];

        $encryption = $config['encryption'];

        if ( 'tls' === $encryption ) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ( 'ssl' === $encryption ) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        }

        assert( is_string( $mail->Host ), 'Host must be set' );
    }

    /**
     * Configure the From address and name.
     *
     * @param PHPMailer $mail PHPMailer instance.
     */
    private function configure_sender( PHPMailer $mail ) {
        assert( $mail instanceof PHPMailer, 'Must receive PHPMailer instance' );

        $config     = $this->get_config();
        $from_email = $config['from_email'];
        $from_name  = $config['from_name'];

        if ( empty( $from_email ) ) {
            $from_email = get_option( 'admin_email', 'noreply@example.com' );
        }

        if ( empty( $from_name ) ) {
            $from_name = get_bloginfo( 'name' );
        }

        $mail->setFrom( $from_email, $from_name );
    }

    /**
     * Configure the recipient.
     *
     * @param PHPMailer $mail PHPMailer instance.
     * @param string    $to   Recipient email.
     */
    private function configure_recipient( PHPMailer $mail, $to ) {
        assert( $mail instanceof PHPMailer, 'Must receive PHPMailer instance' );
        assert( strlen( $to ) <= self::MAX_RECIPIENT_LEN, 'Recipient must be within bounds' );

        $mail->addAddress( $to );
    }

    /**
     * Configure email content.
     *
     * @param PHPMailer $mail    PHPMailer instance.
     * @param string    $subject Subject line.
     * @param string    $body    HTML body.
     */
    private function configure_content( PHPMailer $mail, $subject, $body ) {
        assert( $mail instanceof PHPMailer, 'Must receive PHPMailer instance' );
        assert( is_string( $body ), 'Body must be a string' );

        $mail->isHTML( true );
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = wp_strip_all_tags( $body );
    }
}
