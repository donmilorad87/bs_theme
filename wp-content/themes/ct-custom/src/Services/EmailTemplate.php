<?php

namespace BSCustom\Services;

class EmailTemplate {

    const MAX_CODE_LENGTH = 10;

    /** @var array|null Cached template config from options. */
    private $template_config_cache = null;

    /**
     * Load and cache email template config from theme_mods.
     *
     * @return array Config array with defaults applied.
     */
    private function get_template_config() {
        if ( null !== $this->template_config_cache ) {
            return $this->template_config_cache;
        }

        $this->template_config_cache = array(
            'title_font_size'   => (int) get_theme_mod( 'bs_email_title_font_size', 24 ),
            'title_color'       => get_theme_mod( 'bs_email_title_color', '#333333' ),
            'title_color_dark'  => get_theme_mod( 'bs_email_title_color_dark', '#E0E0E0' ),
            'title_bold'        => (bool) get_theme_mod( 'bs_email_title_bold', true ),
            'title_transform'   => get_theme_mod( 'bs_email_title_transform', 'none' ),
            'text_font_size'    => (int) get_theme_mod( 'bs_email_text_font_size', 15 ),
            'text_color'        => get_theme_mod( 'bs_email_text_color', '#555555' ),
            'text_color_dark'   => get_theme_mod( 'bs_email_text_color_dark', '#B0B0B0' ),
            'text_line_height'  => (float) get_theme_mod( 'bs_email_text_line_height', 1.6 ),
            'border_color'      => get_theme_mod( 'bs_email_border_color', '#E5E5E5' ),
            'border_color_dark' => get_theme_mod( 'bs_email_border_color_dark', '#333333' ),
            'bg_color'          => get_theme_mod( 'bs_email_bg_color', '#FFFFFF' ),
            'bg_color_dark'     => get_theme_mod( 'bs_email_bg_color_dark', '#1A1A2E' ),
            'accent_color'      => get_theme_mod( 'bs_email_accent_color', '#FF6B35' ),
            'accent_color_dark' => get_theme_mod( 'bs_email_accent_color_dark', '#FF8C5A' ),
        );

        assert( is_array( $this->template_config_cache ), 'Template config must be an array' );

        return $this->template_config_cache;
    }

    /**
     * Wrap content in the base email template with logo, header, and footer.
     *
     * @param string $title   Email heading text.
     * @param string $content Inner HTML content.
     * @return string Complete HTML email.
     */
    public function wrap_in_base_template( $title, $content ) {
        assert( is_string( $title ), 'Title must be a string' );
        assert( is_string( $content ), 'Content must be a string' );

        $tc = $this->get_template_config();

        $bg_color     = esc_attr( $tc['bg_color'] );
        $border_color = esc_attr( $tc['border_color'] );
        $accent_color = esc_attr( $tc['accent_color'] );
        $title_size   = (int) $tc['title_font_size'];
        $title_color  = esc_attr( $tc['title_color'] );
        $title_bold   = $tc['title_bold'] ? 'bold' : 'normal';
        $title_transform = esc_attr( $tc['title_transform'] );
        $text_size    = (int) $tc['text_font_size'];
        $text_color   = esc_attr( $tc['text_color'] );
        $text_lh      = (float) $tc['text_line_height'];

        $logo_html    = $this->get_logo_html();
        $footer_html  = $this->get_footer_html( $text_color, $accent_color );
        $safe_title   = esc_html( $title );

        $html = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">';
        $html .= '<meta name="viewport" content="width=device-width,initial-scale=1">';
        $html .= '</head><body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,Helvetica,sans-serif;">';
        $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:40px 20px;">';
        $html .= '<tr><td align="center">';
        $html .= '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:' . $bg_color . ';border:1px solid ' . $border_color . ';border-radius:8px;overflow:hidden;">';

        /* Header with accent bar */
        $html .= '<tr><td style="background:' . $accent_color . ';height:4px;"></td></tr>';

        /* Logo */
        if ( ! empty( $logo_html ) ) {
            $html .= '<tr><td style="padding:24px 32px 0;text-align:center;">' . $logo_html . '</td></tr>';
        }

        /* Title */
        $html .= '<tr><td style="padding:24px 32px 0;text-align:center;">';
        $html .= '<h1 style="margin:0;font-size:' . $title_size . 'px;color:' . $title_color . ';font-weight:' . $title_bold . ';text-transform:' . $title_transform . ';">' . $safe_title . '</h1>';
        $html .= '</td></tr>';

        /* Content */
        $html .= '<tr><td style="padding:24px 32px;font-size:' . $text_size . 'px;color:' . $text_color . ';line-height:' . $text_lh . ';">';
        $html .= $content;
        $html .= '</td></tr>';

        /* Footer */
        $html .= '<tr><td style="padding:20px 32px;border-top:1px solid ' . $border_color . ';font-size:12px;color:#999;text-align:center;">';
        $html .= $footer_html;
        $html .= '</td></tr>';

        $html .= '</table></td></tr></table></body></html>';

        return $html;
    }

    /**
     * Generate forgot-password code email content.
     *
     * @param string $code The 6-digit verification code.
     * @return string Wrapped HTML email.
     */
    public function forgot_password_code( $code ) {
        assert( is_string( $code ) && strlen( $code ) <= self::MAX_CODE_LENGTH, 'Code must be valid' );

        $accent = esc_attr( $this->get_template_config()['accent_color'] );
        $safe   = esc_html( $code );

        $content  = '<p style="margin:0 0 16px;">You requested a password reset. Use the code below to verify your identity:</p>';
        $content .= '<div style="text-align:center;margin:24px 0;">';
        $content .= '<span style="display:inline-block;font-size:32px;font-weight:bold;letter-spacing:8px;color:' . $accent . ';background:#f8f8f8;padding:16px 32px;border-radius:8px;font-family:monospace;">' . $safe . '</span>';
        $content .= '</div>';
        $content .= '<p style="margin:0 0 8px;">This code expires in <strong>15 minutes</strong>.</p>';
        $content .= '<p style="margin:0;color:#999;font-size:13px;">If you did not request this, please ignore this email.</p>';

        return $this->wrap_in_base_template( 'Password Reset Code', $content );
    }

    /**
     * Generate password reset success email.
     *
     * @return string Wrapped HTML email.
     */
    public function password_reset_success() {
        $content  = '<p style="margin:0 0 16px;">Your password has been successfully reset.</p>';
        $content .= '<p style="margin:0 0 8px;">You can now log in with your new password.</p>';
        $content .= '<p style="margin:0;color:#999;font-size:13px;">If you did not make this change, please contact support immediately.</p>';

        return $this->wrap_in_base_template( 'Password Reset Successful', $content );
    }

    /**
     * Generate activation code email content.
     *
     * @param string $code The 6-digit activation code.
     * @return string Wrapped HTML email.
     */
    public function activation_code( $code ) {
        assert( is_string( $code ) && strlen( $code ) <= self::MAX_CODE_LENGTH, 'Code must be valid' );

        $accent = esc_attr( $this->get_template_config()['accent_color'] );
        $safe   = esc_html( $code );

        $content  = '<p style="margin:0 0 16px;">Welcome! Please activate your account using the code below:</p>';
        $content .= '<div style="text-align:center;margin:24px 0;">';
        $content .= '<span style="display:inline-block;font-size:32px;font-weight:bold;letter-spacing:8px;color:' . $accent . ';background:#f8f8f8;padding:16px 32px;border-radius:8px;font-family:monospace;">' . $safe . '</span>';
        $content .= '</div>';
        $content .= '<p style="margin:0 0 8px;">This code expires in <strong>30 minutes</strong>.</p>';
        $content .= '<p style="margin:0;color:#999;font-size:13px;">If you did not create an account, please ignore this email.</p>';

        return $this->wrap_in_base_template( 'Activate Your Account', $content );
    }

    /**
     * Generate activation success email.
     *
     * @return string Wrapped HTML email.
     */
    public function activation_success() {
        $content  = '<p style="margin:0 0 16px;">Your account has been successfully activated!</p>';
        $content .= '<p style="margin:0;">You can now log in and start using all features.</p>';

        return $this->wrap_in_base_template( 'Account Activated', $content );
    }

    /**
     * Generate password changed from profile email.
     *
     * @return string Wrapped HTML email.
     */
    public function password_changed_from_profile() {
        $content  = '<p style="margin:0 0 16px;">Your password has been changed from your profile.</p>';
        $content .= '<p style="margin:0;color:#999;font-size:13px;">If you did not make this change, please reset your password immediately or contact support.</p>';

        return $this->wrap_in_base_template( 'Password Changed', $content );
    }

    /**
     * Generate contact notification email content.
     *
     * @param string $sender_name  Sender's name.
     * @param string $sender_email Sender's email.
     * @param string $sender_phone Sender's phone.
     * @param string $message_body Message body text.
     * @param string $pointer_label Pointer label (e.g. "Contact Us").
     * @return string Wrapped HTML email.
     */
    public function contact_notification( $sender_name, $sender_email, $sender_phone, $message_body, $pointer_label ) {
        assert( is_string( $sender_name ), 'Sender name must be a string' );
        assert( is_string( $sender_email ), 'Sender email must be a string' );

        $accent = esc_attr( $this->get_template_config()['accent_color'] );

        $content  = '<p style="margin:0 0 8px;font-size:13px;color:#999;">Via: <strong>' . esc_html( $pointer_label ) . '</strong></p>';
        $content .= '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;margin-bottom:16px;">';
        $content .= '<tr><td style="padding:4px 0;"><strong>Name:</strong> ' . esc_html( $sender_name ) . '</td></tr>';
        $content .= '<tr><td style="padding:4px 0;"><strong>Email:</strong> <a href="mailto:' . esc_attr( $sender_email ) . '" style="color:' . $accent . ';">' . esc_html( $sender_email ) . '</a></td></tr>';

        if ( ! empty( $sender_phone ) ) {
            $content .= '<tr><td style="padding:4px 0;"><strong>Phone:</strong> ' . esc_html( $sender_phone ) . '</td></tr>';
        }

        $content .= '</table>';
        $content .= '<div style="background:#f8f8f8;border-left:4px solid ' . $accent . ';padding:16px;border-radius:4px;margin:0 0 16px;">';
        $content .= '<p style="margin:0;white-space:pre-wrap;">' . esc_html( $message_body ) . '</p>';
        $content .= '</div>';

        return $this->wrap_in_base_template( 'New Contact Message', $content );
    }

    /**
     * Generate contact submission confirmation email for the sender.
     *
     * @param string $sender_name  Sender's name.
     * @param string $pointer_label Pointer label (e.g. "Contact Us").
     * @return string Wrapped HTML email.
     */
    public function contact_confirmation( $sender_name, $pointer_label ) {
        assert( is_string( $sender_name ), 'Sender name must be a string' );
        assert( is_string( $pointer_label ), 'Pointer label must be a string' );

        $site_name = esc_html( get_bloginfo( 'name' ) );

        $content  = '<p style="margin:0 0 16px;">Hi ' . esc_html( $sender_name ) . ',</p>';
        $content .= '<p style="margin:0 0 16px;">Thank you for contacting us. Your message has been received and our team will review it shortly.</p>';
        $content .= '<p style="margin:0 0 8px;font-size:13px;color:#999;">Department: <strong>' . esc_html( $pointer_label ) . '</strong></p>';
        $content .= '<p style="margin:0;color:#999;font-size:13px;">You will receive a reply at this email address. Please do not reply to this automated message.</p>';

        return $this->wrap_in_base_template( 'Message Received', $content );
    }

    /**
     * Generate contact reply email content.
     *
     * @param string $original_subject Original message subject.
     * @param string $reply_body       Reply text from admin.
     * @param string $admin_name       Admin's display name.
     * @return string Wrapped HTML email.
     */
    public function contact_reply( $original_subject, $reply_body, $admin_name ) {
        assert( is_string( $original_subject ), 'Subject must be a string' );
        assert( is_string( $reply_body ), 'Reply body must be a string' );

        $accent = esc_attr( $this->get_template_config()['accent_color'] );

        $content  = '<p style="margin:0 0 16px;">You have received a reply to your message:</p>';
        $content .= '<p style="margin:0 0 8px;font-size:13px;color:#999;">Re: ' . esc_html( $original_subject ) . '</p>';
        $content .= '<div style="background:#f8f8f8;border-left:4px solid ' . $accent . ';padding:16px;border-radius:4px;margin:0 0 16px;">';
        $content .= '<p style="margin:0;white-space:pre-wrap;">' . esc_html( $reply_body ) . '</p>';
        $content .= '</div>';
        $content .= '<p style="margin:0;font-size:13px;color:#999;">Replied by: ' . esc_html( $admin_name ) . '</p>';

        return $this->wrap_in_base_template( 'Reply to Your Message', $content );
    }

    /**
     * Get the site logo HTML for emails.
     *
     * Returns the custom_logo image when available,
     * otherwise falls back to the site name as styled text.
     *
     * @return string Logo HTML (never empty).
     */
    private function get_logo_html() {
        $logo_id   = get_theme_mod( 'custom_logo', 0 );
        $site_name = esc_attr( get_bloginfo( 'name' ) );

        if ( $logo_id ) {
            $logo_url = wp_get_attachment_image_url( $logo_id, 'medium' );

            if ( $logo_url ) {
                return '<img src="' . esc_url( $logo_url ) . '" alt="' . $site_name . '" style="max-width:180px;height:auto;" />';
            }
        }

        $accent_color = esc_attr( $this->get_template_config()['accent_color'] );

        return '<span style="font-size:22px;font-weight:700;color:' . $accent_color . ';">' . esc_html( get_bloginfo( 'name' ) ) . '</span>';
    }

    /**
     * Build footer HTML with contact points and social links.
     *
     * @param string $text_color Footer text color.
     * @param string $accent_color Accent color for links.
     * @return string Footer HTML.
     */
    private function get_footer_html( $text_color, $accent_color ) {
        assert( is_string( $text_color ), 'Text color must be a string' );
        assert( is_string( $accent_color ), 'Accent color must be a string' );

        $parts = array();

        /* Contact point */
        $contact_json = get_option( 'bs_custom_contact_point', '' );
        if ( ! empty( $contact_json ) ) {
            $contact = json_decode( $contact_json, true );
            if ( is_array( $contact ) ) {
                $contact_parts = array();
                if ( ! empty( $contact['email'] ) ) {
                    $contact_parts[] = esc_html( $contact['email'] );
                }
                if ( ! empty( $contact['telephone'] ) ) {
                    $contact_parts[] = esc_html( $contact['telephone'] );
                }
                if ( ! empty( $contact_parts ) ) {
                    $parts[] = implode( ' | ', $contact_parts );
                }
            }
        }

        /* Social links */
        $social_json = get_option( 'bs_custom_social_networks', '[]' );
        $socials     = json_decode( $social_json, true );
        if ( is_array( $socials ) && ! empty( $socials ) ) {
            $links     = array();
            $max_links = 10;
            $count     = 0;

            foreach ( $socials as $social ) {
                if ( $count >= $max_links ) {
                    break;
                }
                $count++;

                if ( ! empty( $social['name'] ) && ! empty( $social['url'] ) ) {
                    $links[] = '<a href="' . esc_url( $social['url'] ) . '" style="color:' . $accent_color . ';text-decoration:none;">' . esc_html( $social['name'] ) . '</a>';
                }
            }

            if ( ! empty( $links ) ) {
                $parts[] = implode( ' &middot; ', $links );
            }
        }

        $site_name = esc_html( get_bloginfo( 'name' ) );
        $parts[]   = '&copy; ' . gmdate( 'Y' ) . ' ' . $site_name;

        return implode( '<br>', $parts );
    }
}
