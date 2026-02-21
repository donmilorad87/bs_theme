<?php
/**
 * SEO admin partial.
 *
 * Nine CSS-only sub-tabs:
 * A) Global Settings
 * B) Social Defaults
 * C) Social Icons
 * D) Contact Point
 * E) Sitemap
 * F) LLMs.txt
 * G) Redirects
 * H) Breadcrumbs
 * I) Dashboard
 *
 * @package BS_Custom
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="ct-admin-section ct-seo-section"
     data-nonce="<?php echo esc_attr( wp_create_nonce( 'bs_seo_nonce' ) ); ?>"
     data-ajax-url="<?php echo esc_attr( admin_url( 'admin-ajax.php' ) ); ?>">

    <!-- Radio inputs at wrapper level so ~ combinator reaches panels -->
    <input type="radio" name="bs_seo_tab" id="bs_seo_tab_global" class="ct-seo-tabs__radio" checked>
    <input type="radio" name="bs_seo_tab" id="bs_seo_tab_social" class="ct-seo-tabs__radio">
    <input type="radio" name="bs_seo_tab" id="bs_seo_tab_social_icons" class="ct-seo-tabs__radio">
    <input type="radio" name="bs_seo_tab" id="bs_seo_tab_contact_point" class="ct-seo-tabs__radio">
    <input type="radio" name="bs_seo_tab" id="bs_seo_tab_sitemap" class="ct-seo-tabs__radio">
    <input type="radio" name="bs_seo_tab" id="bs_seo_tab_llms" class="ct-seo-tabs__radio">
    <input type="radio" name="bs_seo_tab" id="bs_seo_tab_redirects" class="ct-seo-tabs__radio">
    <input type="radio" name="bs_seo_tab" id="bs_seo_tab_breadcrumbs" class="ct-seo-tabs__radio">
    <input type="radio" name="bs_seo_tab" id="bs_seo_tab_dashboard" class="ct-seo-tabs__radio">

    <!-- Sub-tab navigation labels -->
    <div class="ct-seo-tabs">
        <label for="bs_seo_tab_global" class="ct-seo-tabs__label"><?php esc_html_e( 'Global Settings', 'ct-custom' ); ?></label>
        <label for="bs_seo_tab_social" class="ct-seo-tabs__label"><?php esc_html_e( 'Social Defaults', 'ct-custom' ); ?></label>
        <label for="bs_seo_tab_social_icons" class="ct-seo-tabs__label"><?php esc_html_e( 'Social Icons', 'ct-custom' ); ?></label>
        <label for="bs_seo_tab_contact_point" class="ct-seo-tabs__label"><?php esc_html_e( 'Contact Point', 'ct-custom' ); ?></label>
        <label for="bs_seo_tab_sitemap" class="ct-seo-tabs__label"><?php esc_html_e( 'Sitemap', 'ct-custom' ); ?></label>
        <label for="bs_seo_tab_llms" class="ct-seo-tabs__label"><?php esc_html_e( 'LLMs.txt', 'ct-custom' ); ?></label>
        <label for="bs_seo_tab_redirects" class="ct-seo-tabs__label"><?php esc_html_e( 'Redirects', 'ct-custom' ); ?></label>
        <label for="bs_seo_tab_breadcrumbs" class="ct-seo-tabs__label"><?php esc_html_e( 'Breadcrumbs', 'ct-custom' ); ?></label>
        <label for="bs_seo_tab_dashboard" class="ct-seo-tabs__label"><?php esc_html_e( 'Dashboard', 'ct-custom' ); ?></label>
    </div>

    <!-- ═══ Sub-Tab A: Global Settings ═══ -->
    <div class="ct-seo-panel ct-seo-panel--global">
        <h3><?php esc_html_e( 'Title Template', 'ct-custom' ); ?></h3>

        <form id="bs_seo_global_form" class="ct-seo-form">
            <div class="ct-seo-field">
                <label for="bs_seo_title_template"><?php esc_html_e( 'Title Template', 'ct-custom' ); ?></label>
                <input type="text" id="bs_seo_title_template" class="ct-seo-form__input" value=""
                       placeholder="%%title%% %%sep%% %%sitename%%">
                <div class="ct-seo-field__placeholders">
                    <button type="button" class="ct-seo-placeholder-btn" data-placeholder="%%title%%">%%title%%</button>
                    <button type="button" class="ct-seo-placeholder-btn" data-placeholder="%%sitename%%">%%sitename%%</button>
                    <button type="button" class="ct-seo-placeholder-btn" data-placeholder="%%sep%%">%%sep%%</button>
                </div>
            </div>

            <div class="ct-seo-field">
                <label for="bs_seo_separator"><?php esc_html_e( 'Title Separator', 'ct-custom' ); ?></label>
                <input type="text" id="bs_seo_separator" class="ct-seo-form__input ct-seo-form__input--small" value="" maxlength="5"
                       placeholder="|">
            </div>

            <div class="ct-seo-field">
                <label for="bs_seo_default_description"><?php esc_html_e( 'Default Meta Description', 'ct-custom' ); ?></label>
                <textarea id="bs_seo_default_description" class="ct-seo-form__textarea" rows="3"
                          placeholder="<?php esc_attr_e( 'A brief description of your site...', 'ct-custom' ); ?>"></textarea>
            </div>

            <div class="ct-seo-field">
                <label for="bs_seo_default_keywords"><?php esc_html_e( 'Default Keywords', 'ct-custom' ); ?></label>
                <input type="text" id="bs_seo_default_keywords" class="ct-seo-form__input" value=""
                       placeholder="<?php esc_attr_e( 'keyword1, keyword2, keyword3', 'ct-custom' ); ?>">
            </div>

            <hr class="ct-admin-divider">

            <h3><?php esc_html_e( 'Knowledge Graph', 'ct-custom' ); ?></h3>

            <div class="ct-seo-field">
                <label for="bs_seo_kg_type"><?php esc_html_e( 'Type', 'ct-custom' ); ?></label>
                <select id="bs_seo_kg_type" class="ct-seo-form__select">
                    <option value="Organization"><?php esc_html_e( 'Organization', 'ct-custom' ); ?></option>
                    <option value="Person"><?php esc_html_e( 'Person', 'ct-custom' ); ?></option>
                </select>
            </div>

            <div class="ct-seo-field">
                <label for="bs_seo_kg_name"><?php esc_html_e( 'Organization / Person Name', 'ct-custom' ); ?></label>
                <input type="text" id="bs_seo_kg_name" class="ct-seo-form__input" value=""
                       placeholder="<?php esc_attr_e( 'Your Company Name', 'ct-custom' ); ?>">
            </div>

            <div class="ct-seo-field">
                <label><?php esc_html_e( 'Logo', 'ct-custom' ); ?></label>
                <div class="ct-seo-media-picker">
                    <input type="hidden" id="bs_seo_kg_logo" value="">
                    <img id="bs_seo_kg_logo_preview" src="" alt="" class="ct-seo-media-picker__preview" style="display:none;">
                    <button type="button" id="bs_seo_kg_logo_btn" class="button"><?php esc_html_e( 'Choose Logo', 'ct-custom' ); ?></button>
                    <button type="button" id="bs_seo_kg_logo_remove" class="button ct-seo-media-picker__remove" style="display:none;">&times;</button>
                </div>
            </div>

            <div class="ct-seo-field">
                <label for="bs_seo_kg_url"><?php esc_html_e( 'Organization URL', 'ct-custom' ); ?></label>
                <input type="url" id="bs_seo_kg_url" class="ct-seo-form__input" value=""
                       placeholder="https://example.com">
            </div>

            <div class="ct-seo-form__actions">
                <button type="button" id="bs_seo_global_save" class="button button-primary"><?php esc_html_e( 'Save Global Settings', 'ct-custom' ); ?></button>
                <span class="ct-seo-form__status" id="bs_seo_global_status"></span>
            </div>
        </form>
    </div>

    <!-- ═══ Sub-Tab B: Social Defaults ═══ -->
    <div class="ct-seo-panel ct-seo-panel--social">
        <h3><?php esc_html_e( 'Open Graph Defaults', 'ct-custom' ); ?></h3>

        <form id="bs_seo_social_form" class="ct-seo-form">
            <div class="ct-seo-field">
                <label><?php esc_html_e( 'Default OG Image', 'ct-custom' ); ?></label>
                <div class="ct-seo-media-picker">
                    <input type="hidden" id="bs_seo_og_image" value="">
                    <img id="bs_seo_og_image_preview" src="" alt="" class="ct-seo-media-picker__preview ct-seo-media-picker__preview--og" style="display:none;">
                    <button type="button" id="bs_seo_og_image_btn" class="button"><?php esc_html_e( 'Choose Image', 'ct-custom' ); ?></button>
                    <button type="button" id="bs_seo_og_image_remove" class="button ct-seo-media-picker__remove" style="display:none;">&times;</button>
                </div>
            </div>

            <div class="ct-seo-field">
                <label for="bs_seo_og_sitename"><?php esc_html_e( 'OG Site Name', 'ct-custom' ); ?></label>
                <input type="text" id="bs_seo_og_sitename" class="ct-seo-form__input" value=""
                       placeholder="<?php esc_attr_e( 'My Website', 'ct-custom' ); ?>">
            </div>

            <hr class="ct-admin-divider">

            <h3><?php esc_html_e( 'Twitter / X', 'ct-custom' ); ?></h3>

            <div class="ct-seo-field">
                <label for="bs_seo_twitter_username"><?php esc_html_e( 'Twitter @Username', 'ct-custom' ); ?></label>
                <input type="text" id="bs_seo_twitter_username" class="ct-seo-form__input ct-seo-form__input--small" value=""
                       placeholder="@yourhandle" maxlength="50">
            </div>

            <div class="ct-seo-field">
                <label for="bs_seo_twitter_card_type"><?php esc_html_e( 'Default Card Type', 'ct-custom' ); ?></label>
                <select id="bs_seo_twitter_card_type" class="ct-seo-form__select">
                    <option value="summary"><?php esc_html_e( 'Summary', 'ct-custom' ); ?></option>
                    <option value="summary_large_image"><?php esc_html_e( 'Summary with Large Image', 'ct-custom' ); ?></option>
                </select>
            </div>

            <hr class="ct-admin-divider">

            <h3><?php esc_html_e( 'Other', 'ct-custom' ); ?></h3>

            <div class="ct-seo-field">
                <label for="bs_seo_pinterest_verify"><?php esc_html_e( 'Pinterest Verification Code', 'ct-custom' ); ?></label>
                <input type="text" id="bs_seo_pinterest_verify" class="ct-seo-form__input ct-seo-form__input--small" value=""
                       placeholder="<?php esc_attr_e( 'Verification code', 'ct-custom' ); ?>" maxlength="100">
            </div>

            <div class="ct-seo-field">
                <label for="bs_seo_social_profiles"><?php esc_html_e( 'Social Profile URLs', 'ct-custom' ); ?></label>
                <textarea id="bs_seo_social_profiles" class="ct-seo-form__textarea" rows="4"
                          placeholder="<?php esc_attr_e( "https://facebook.com/yourpage\nhttps://twitter.com/yourhandle\nhttps://linkedin.com/company/yourcompany", 'ct-custom' ); ?>"></textarea>
                <p class="ct-seo-field__hint"><?php esc_html_e( 'One URL per line. Used in Schema.org sameAs property.', 'ct-custom' ); ?></p>
            </div>

            <div class="ct-seo-form__actions">
                <button type="button" id="bs_seo_social_save" class="button button-primary"><?php esc_html_e( 'Save Social Settings', 'ct-custom' ); ?></button>
                <span class="ct-seo-form__status" id="bs_seo_social_status"></span>
            </div>
        </form>
    </div>

    <?php
    $bs_social_networks_raw = get_option( 'bs_custom_social_networks', '[]' );
    $bs_social_networks     = json_decode( stripslashes( $bs_social_networks_raw ), true );
    if ( ! is_array( $bs_social_networks ) ) {
        $bs_social_networks = array();
    }

    $bs_social_icons_enabled = get_option( 'bs_social_icons_enabled', 'on' );
    $bs_social_share_enabled = get_theme_mod( 'bs_social_share_enabled', true );

    $bs_contact_point_raw = get_option( 'bs_custom_contact_point', '' );
    $bs_contact_point     = json_decode( stripslashes( $bs_contact_point_raw ), true );
    if ( ! is_array( $bs_contact_point ) ) {
        $bs_contact_point = array();
    }
    $bs_cp_address = isset( $bs_contact_point['address'] ) && is_array( $bs_contact_point['address'] )
        ? $bs_contact_point['address'] : array();
    $bs_cp_type = isset( $bs_contact_point['contact_type'] ) ? $bs_contact_point['contact_type'] : 'customer service';

    $bs_contact_type_choices = array(
        'customer service'    => __( 'Customer Service', 'ct-custom' ),
        'technical support'   => __( 'Technical Support', 'ct-custom' ),
        'billing support'     => __( 'Billing Support', 'ct-custom' ),
        'sales'               => __( 'Sales', 'ct-custom' ),
        'reservations'        => __( 'Reservations', 'ct-custom' ),
        'credit card support' => __( 'Credit Card Support', 'ct-custom' ),
        'emergency'           => __( 'Emergency', 'ct-custom' ),
        'baggage tracking'    => __( 'Baggage Tracking', 'ct-custom' ),
        'roadside assistance' => __( 'Roadside Assistance', 'ct-custom' ),
        'package tracking'    => __( 'Package Tracking', 'ct-custom' ),
    );
    ?>

    <!-- ═══ Sub-Tab C: Social Icons ═══ -->
    <div class="ct-seo-panel ct-seo-panel--social-icons">
        <h3><?php esc_html_e( 'Social Icons', 'ct-custom' ); ?></h3>

        <form id="bs_seo_social_icons_form" class="ct-seo-form"
              data-networks="<?php echo esc_attr( wp_json_encode( $bs_social_networks ) ); ?>">
            <div class="ct-seo-field ct-seo-field--toggle">
                <label><?php esc_html_e( 'Enable Social Icons', 'ct-custom' ); ?></label>
                <label class="ct-seo-toggle">
                    <input type="checkbox" id="bs_social_icons_enabled" class="ct-seo-toggle__input"
                        <?php checked( 'off' !== $bs_social_icons_enabled ); ?>>
                    <span class="ct-seo-toggle__slider"></span>
                </label>
            </div>

            <div class="ct-seo-field ct-seo-field--toggle">
                <label><?php esc_html_e( 'Share with Friend', 'ct-custom' ); ?></label>
                <label class="ct-seo-toggle">
                    <input type="checkbox" id="bs_social_share_enabled" class="ct-seo-toggle__input"
                        <?php checked( $bs_social_share_enabled ); ?>>
                    <span class="ct-seo-toggle__slider"></span>
                </label>
            </div>

            <div class="ct-seo-field">
                <label><?php esc_html_e( 'Social Networks', 'ct-custom' ); ?></label>
                <p class="ct-seo-field__hint"><?php esc_html_e( 'Add, edit, or remove social icons. Icon background styling lives in the Customizer.', 'ct-custom' ); ?></p>
                <ul class="ct-admin-social-list" id="bs_social_icons_list"></ul>
                <p class="ct-admin-no-items" id="bs_social_icons_empty" style="display:none;"><?php esc_html_e( 'No social networks added yet.', 'ct-custom' ); ?></p>
            </div>

            <div class="ct-admin-form ct-admin-social-form" id="bs_social_icons_editor">
                <div class="ct-admin-field">
                    <label for="bs_social_icon_name"><?php esc_html_e( 'Name', 'ct-custom' ); ?></label>
                    <input type="text" id="bs_social_icon_name" maxlength="100" placeholder="<?php esc_attr_e( 'e.g. Facebook', 'ct-custom' ); ?>">
                </div>
                <div class="ct-admin-field">
                    <label for="bs_social_icon_link"><?php esc_html_e( 'URL', 'ct-custom' ); ?></label>
                    <input type="url" id="bs_social_icon_link" maxlength="500" placeholder="https://">
                </div>
                <div class="ct-admin-field">
                    <label><?php esc_html_e( 'Icon', 'ct-custom' ); ?></label>
                    <div class="ct-admin-image-preview">
                        <div id="social_icon_preview"><span class="ct-admin-no-image"><?php esc_html_e( 'No icon selected.', 'ct-custom' ); ?></span></div>
                        <button type="button" class="button" id="bs_social_icon_select"><?php esc_html_e( 'Select Icon', 'ct-custom' ); ?></button>
                        <button type="button" class="button" id="bs_social_icon_remove" style="display:none;"><?php esc_html_e( 'Remove', 'ct-custom' ); ?></button>
                    </div>
                    <input type="hidden" id="bs_social_icon_media_id" value="0">
                    <input type="hidden" id="bs_social_icon_media_url" value="">
                </div>
                <div class="ct-admin-form-actions">
                    <button type="button" id="bs_social_icon_add" class="button button-primary"><?php esc_html_e( 'Add Network', 'ct-custom' ); ?></button>
                    <button type="button" id="bs_social_icon_cancel" class="button" style="display:none;"><?php esc_html_e( 'Cancel', 'ct-custom' ); ?></button>
                </div>
            </div>

            <div class="ct-seo-form__actions">
                <button type="button" id="bs_social_icons_save" class="button button-primary"><?php esc_html_e( 'Save Social Icons', 'ct-custom' ); ?></button>
                <span class="ct-seo-form__status" id="bs_social_icons_status"></span>
            </div>
        </form>
    </div>

    <!-- ═══ Sub-Tab D: Contact Point ═══ -->
    <div class="ct-seo-panel ct-seo-panel--contact-point">
        <h3><?php esc_html_e( 'Contact Point', 'ct-custom' ); ?></h3>

        <form id="bs_seo_contact_point_form" class="ct-seo-form">
            <div class="ct-admin-form">
                <div class="ct-admin-field">
                    <label for="bs_cp_company"><?php esc_html_e( 'Company', 'ct-custom' ); ?></label>
                    <input type="text" id="bs_cp_company" maxlength="200" value="<?php echo esc_attr( $bs_contact_point['company'] ?? '' ); ?>">
                </div>
                <div class="ct-admin-field">
                    <label for="bs_cp_telephone"><?php esc_html_e( 'Telephone', 'ct-custom' ); ?></label>
                    <input type="text" id="bs_cp_telephone" maxlength="50" value="<?php echo esc_attr( $bs_contact_point['telephone'] ?? '' ); ?>">
                </div>
                <div class="ct-admin-field">
                    <label for="bs_cp_fax"><?php esc_html_e( 'Fax', 'ct-custom' ); ?></label>
                    <input type="text" id="bs_cp_fax" maxlength="50" value="<?php echo esc_attr( $bs_contact_point['fax_number'] ?? '' ); ?>">
                </div>
                <div class="ct-admin-field">
                    <label for="bs_cp_email"><?php esc_html_e( 'Email', 'ct-custom' ); ?></label>
                    <input type="email" id="bs_cp_email" maxlength="254" value="<?php echo esc_attr( $bs_contact_point['email'] ?? '' ); ?>">
                </div>
                <div class="ct-admin-field">
                    <label for="bs_cp_contact_type"><?php esc_html_e( 'Contact Type', 'ct-custom' ); ?></label>
                    <select id="bs_cp_contact_type">
                        <?php foreach ( $bs_contact_type_choices as $bs_type_value => $bs_type_label ) : ?>
                            <option value="<?php echo esc_attr( $bs_type_value ); ?>" <?php selected( $bs_cp_type, $bs_type_value ); ?>>
                                <?php echo esc_html( $bs_type_label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <hr class="ct-admin-divider">

                <div class="ct-admin-field">
                    <label for="bs_cp_street_number"><?php esc_html_e( 'Street Number', 'ct-custom' ); ?></label>
                    <input type="text" id="bs_cp_street_number" maxlength="20" value="<?php echo esc_attr( $bs_cp_address['street_number'] ?? '' ); ?>">
                </div>
                <div class="ct-admin-field">
                    <label for="bs_cp_street_address"><?php esc_html_e( 'Street Address', 'ct-custom' ); ?></label>
                    <input type="text" id="bs_cp_street_address" maxlength="200" value="<?php echo esc_attr( $bs_cp_address['street_address'] ?? '' ); ?>">
                </div>
                <div class="ct-admin-field">
                    <label for="bs_cp_city"><?php esc_html_e( 'City', 'ct-custom' ); ?></label>
                    <input type="text" id="bs_cp_city" maxlength="100" value="<?php echo esc_attr( $bs_cp_address['city'] ?? '' ); ?>">
                </div>
                <div class="ct-admin-field">
                    <label for="bs_cp_state"><?php esc_html_e( 'State', 'ct-custom' ); ?></label>
                    <input type="text" id="bs_cp_state" maxlength="100" value="<?php echo esc_attr( $bs_cp_address['state'] ?? '' ); ?>">
                </div>
                <div class="ct-admin-field">
                    <label for="bs_cp_postal_code"><?php esc_html_e( 'Postal Code', 'ct-custom' ); ?></label>
                    <input type="text" id="bs_cp_postal_code" maxlength="20" value="<?php echo esc_attr( $bs_cp_address['postal_code'] ?? '' ); ?>">
                </div>
                <div class="ct-admin-field">
                    <label for="bs_cp_country"><?php esc_html_e( 'Country', 'ct-custom' ); ?></label>
                    <input type="text" id="bs_cp_country" maxlength="100" value="<?php echo esc_attr( $bs_cp_address['country'] ?? '' ); ?>">
                </div>
            </div>

            <div class="ct-seo-form__actions">
                <button type="button" id="bs_seo_contact_point_save" class="button button-primary"><?php esc_html_e( 'Save Contact Point', 'ct-custom' ); ?></button>
                <span class="ct-seo-form__status" id="bs_seo_contact_point_status"></span>
            </div>
        </form>
    </div>

    <!-- ═══ Sub-Tab E: Sitemap ═══ -->
    <div class="ct-seo-panel ct-seo-panel--sitemap">

        <!-- Inner sub-tab radios (CSS-only, scoped inside sitemap panel) -->
        <input type="radio" name="bs_sitemap_subtab" id="bs_sitemap_subtab_general" class="ct-sitemap-subtab__radio" checked>
        <input type="radio" name="bs_sitemap_subtab" id="bs_sitemap_subtab_index" class="ct-sitemap-subtab__radio">

        <!-- Inner sub-tab nav -->
        <div class="ct-sitemap-subtabs">
            <label for="bs_sitemap_subtab_general" class="ct-sitemap-subtabs__label"><?php esc_html_e( 'General', 'ct-custom' ); ?></label>
            <label for="bs_sitemap_subtab_index" class="ct-sitemap-subtabs__label"><?php esc_html_e( 'Sitemap Index', 'ct-custom' ); ?></label>
        </div>

        <!-- ─── General sub-panel ─── -->
        <div class="ct-sitemap-subpanel ct-sitemap-subpanel--general">
            <h3><?php esc_html_e( 'XML Sitemap', 'ct-custom' ); ?></h3>

            <form id="bs_seo_sitemap_form" class="ct-seo-form">
                <div class="ct-seo-field ct-seo-field--toggle">
                    <label><?php esc_html_e( 'Enable XML Sitemap', 'ct-custom' ); ?></label>
                    <label class="ct-seo-toggle">
                        <input type="checkbox" id="bs_seo_sitemap_enabled" class="ct-seo-toggle__input" checked>
                        <span class="ct-seo-toggle__slider"></span>
                    </label>
                </div>

                <section class="ct-sitemap-types">
                    <h4><?php esc_html_e( 'Include in Sitemap', 'ct-custom' ); ?></h4>
                    <div id="bs_sitemap_type_toggles" class="ct-sitemap-types__grid">
                        <!-- Populated by JS -->
                    </div>
                </section>

                <div class="ct-seo-form__actions">
                    <button type="button" id="bs_seo_sitemap_regenerate" class="button">
                        <span class="ct-sitemap-regen__icon" aria-hidden="true">&#8635;</span>
                        <?php esc_html_e( 'Regenerate Sitemap', 'ct-custom' ); ?>
                    </button>
                    <span class="ct-seo-form__status" id="bs_seo_sitemap_status"></span>
                </div>
            </form>
        </div>

        <!-- ─── Sitemap Index sub-panel ─── -->
        <div class="ct-sitemap-subpanel ct-sitemap-subpanel--index">
            <p class="ct-seo-field__hint"><?php esc_html_e( 'Expand a language to browse post types. Expand a post type to see individual pages. Drag rows to reorder — order is saved automatically.', 'ct-custom' ); ?></p>
            <div id="bs_sitemap_tree_root" aria-live="polite">
                <!-- Populated by JS -->
            </div>
        </div>

    </div>

    <!-- ═══ Sub-Tab F: LLMs.txt ═══ -->
    <div class="ct-seo-panel ct-seo-panel--llms">
        <h3><?php esc_html_e( 'LLMs.txt', 'ct-custom' ); ?></h3>

        <form id="bs_seo_llms_form" class="ct-seo-form">
            <div class="ct-seo-field ct-seo-field--toggle">
                <label><?php esc_html_e( 'Enable LLMs.txt', 'ct-custom' ); ?></label>
                <label class="ct-seo-toggle">
                    <input type="checkbox" id="bs_seo_llms_enabled" class="ct-seo-toggle__input" checked>
                    <span class="ct-seo-toggle__slider"></span>
                </label>
            </div>

            <div class="ct-seo-field">
                <label><?php esc_html_e( 'Preview', 'ct-custom' ); ?></label>
                <pre id="bs_seo_llms_preview" class="ct-seo-form__preview"><?php esc_html_e( 'Loading preview...', 'ct-custom' ); ?></pre>
            </div>

            <div class="ct-seo-field">
                <label for="bs_seo_llms_custom"><?php esc_html_e( 'Custom Additions', 'ct-custom' ); ?></label>
                <textarea id="bs_seo_llms_custom" class="ct-seo-form__textarea" rows="6"
                          placeholder="<?php esc_attr_e( '# Additional context for LLMs', 'ct-custom' ); ?>"></textarea>
                <p class="ct-seo-field__hint"><?php esc_html_e( 'Appended to the auto-generated LLMs.txt content.', 'ct-custom' ); ?></p>
            </div>

            <div class="ct-seo-form__actions">
                <button type="button" id="bs_seo_llms_save" class="button button-primary"><?php esc_html_e( 'Save LLMs.txt Settings', 'ct-custom' ); ?></button>
                <span class="ct-seo-form__status" id="bs_seo_llms_status"></span>
            </div>
        </form>
    </div>

    <!-- ═══ Sub-Tab G: Redirects ═══ -->
    <div class="ct-seo-panel ct-seo-panel--redirects">
        <h3><?php esc_html_e( 'Redirects', 'ct-custom' ); ?></h3>

        <div class="ct-seo-redirects">
            <div class="ct-seo-redirects__add">
                <div class="ct-seo-redirects__add-row">
                    <div class="ct-seo-redirects__add-field">
                        <label for="bs_seo_redirect_from"><?php esc_html_e( 'From URL', 'ct-custom' ); ?></label>
                        <input type="text" id="bs_seo_redirect_from" class="ct-seo-form__input" placeholder="/old-page/">
                    </div>
                    <div class="ct-seo-redirects__add-field">
                        <label for="bs_seo_redirect_to"><?php esc_html_e( 'To URL', 'ct-custom' ); ?></label>
                        <input type="text" id="bs_seo_redirect_to" class="ct-seo-form__input" placeholder="/new-page/">
                    </div>
                    <div class="ct-seo-redirects__add-field ct-seo-redirects__add-field--type">
                        <label for="bs_seo_redirect_type"><?php esc_html_e( 'Type', 'ct-custom' ); ?></label>
                        <select id="bs_seo_redirect_type" class="ct-seo-form__select">
                            <option value="301">301</option>
                            <option value="302">302</option>
                        </select>
                    </div>
                    <div class="ct-seo-redirects__add-field ct-seo-redirects__add-field--btn">
                        <button type="button" id="bs_seo_redirect_add" class="button button-primary"><?php esc_html_e( 'Add Redirect', 'ct-custom' ); ?></button>
                    </div>
                </div>
            </div>

            <table class="ct-seo-redirects__table" id="bs_seo_redirects_table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'From', 'ct-custom' ); ?></th>
                        <th><?php esc_html_e( 'To', 'ct-custom' ); ?></th>
                        <th><?php esc_html_e( 'Type', 'ct-custom' ); ?></th>
                        <th><?php esc_html_e( 'Hits', 'ct-custom' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'ct-custom' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="ct-seo-redirects__loading">
                        <td colspan="5"><?php esc_html_e( 'Loading redirects...', 'ct-custom' ); ?></td>
                    </tr>
                </tbody>
            </table>

            <div class="ct-seo-redirects__bulk">
                <button type="button" id="bs_seo_redirects_export" class="button"><?php esc_html_e( 'Export', 'ct-custom' ); ?></button>
                <button type="button" id="bs_seo_redirects_import" class="button"><?php esc_html_e( 'Import', 'ct-custom' ); ?></button>
                <input type="file" id="bs_seo_redirects_import_file" accept=".json,.csv" style="display:none;">
            </div>
        </div>
    </div>

    <!-- ═══ Sub-Tab H: Breadcrumbs ═══ -->
    <div class="ct-seo-panel ct-seo-panel--breadcrumbs">
        <h3><?php esc_html_e( 'Breadcrumbs', 'ct-custom' ); ?></h3>

        <form id="bs_seo_breadcrumbs_form" class="ct-seo-form">
            <div class="ct-seo-field ct-seo-field--toggle">
                <label><?php esc_html_e( 'Enable Breadcrumbs', 'ct-custom' ); ?></label>
                <label class="ct-seo-toggle">
                    <input type="checkbox" id="bs_seo_breadcrumbs_enabled" class="ct-seo-toggle__input">
                    <span class="ct-seo-toggle__slider"></span>
                </label>
            </div>

            <div class="ct-seo-field">
                <label for="bs_seo_breadcrumbs_separator"><?php esc_html_e( 'Separator', 'ct-custom' ); ?></label>
                <input type="text" id="bs_seo_breadcrumbs_separator" class="ct-seo-form__input ct-seo-form__input--small" value="" maxlength="10"
                       placeholder="/">
            </div>

            <div class="ct-seo-field">
                <label for="bs_seo_breadcrumbs_home_label"><?php esc_html_e( 'Home Label', 'ct-custom' ); ?></label>
                <input type="text" id="bs_seo_breadcrumbs_home_label" class="ct-seo-form__input ct-seo-form__input--small" value=""
                       placeholder="Home" maxlength="50">
            </div>

            <div class="ct-seo-field ct-seo-field--toggle">
                <label><?php esc_html_e( 'Show on Pages', 'ct-custom' ); ?></label>
                <label class="ct-seo-toggle">
                    <input type="checkbox" id="bs_seo_breadcrumbs_pages" class="ct-seo-toggle__input" checked>
                    <span class="ct-seo-toggle__slider"></span>
                </label>
            </div>

            <div class="ct-seo-field ct-seo-field--toggle">
                <label><?php esc_html_e( 'Show on Posts', 'ct-custom' ); ?></label>
                <label class="ct-seo-toggle">
                    <input type="checkbox" id="bs_seo_breadcrumbs_posts" class="ct-seo-toggle__input" checked>
                    <span class="ct-seo-toggle__slider"></span>
                </label>
            </div>

            <div class="ct-seo-form__actions">
                <button type="button" id="bs_seo_breadcrumbs_save" class="button button-primary"><?php esc_html_e( 'Save Breadcrumb Settings', 'ct-custom' ); ?></button>
                <span class="ct-seo-form__status" id="bs_seo_breadcrumbs_status"></span>
            </div>
        </form>
    </div>

    <!-- ═══ Sub-Tab I: Dashboard ═══ -->
    <div class="ct-seo-panel ct-seo-panel--dashboard">
        <h3><?php esc_html_e( 'SEO Dashboard', 'ct-custom' ); ?></h3>

        <div class="ct-seo-dashboard">
            <div class="ct-seo-dashboard__cards" id="bs_seo_dashboard_cards">
                <!-- Cards rendered by JS -->
            </div>

            <div class="ct-seo-dashboard__filters">
                <select id="bs_seo_dashboard_type_filter" class="ct-seo-form__select">
                    <option value="all"><?php esc_html_e( 'All Post Types', 'ct-custom' ); ?></option>
                    <option value="page"><?php esc_html_e( 'Pages', 'ct-custom' ); ?></option>
                    <option value="post"><?php esc_html_e( 'Posts', 'ct-custom' ); ?></option>
                </select>

                <?php
                /* Build language options — default language pre-selected. */
                $_dash_lang_opts    = array();
                $_dash_default_iso2 = '';

                if ( function_exists( 'bs_get_language_manager' ) ) {
                    $_dash_lmgr        = bs_get_language_manager();
                    $_dash_default     = $_dash_lmgr->get_default();
                    $_dash_default_iso2 = ( null !== $_dash_default && isset( $_dash_default['iso2'] ) )
                        ? $_dash_default['iso2'] : '';

                    foreach ( $_dash_lmgr->get_all() as $_dl ) {
                        if ( isset( $_dl['enabled'] ) && ! $_dl['enabled'] ) {
                            continue;
                        }
                        $_dash_lang_opts[] = $_dl;
                    }
                }
                ?>
                <select id="bs_seo_dashboard_lang_filter" class="ct-seo-form__select">
                    <option value=""><?php esc_html_e( 'All Languages', 'ct-custom' ); ?></option>
                    <?php foreach ( $_dash_lang_opts as $_dlo ) : ?>
                        <option value="<?php echo esc_attr( $_dlo['iso2'] ); ?>"
                            <?php selected( $_dlo['iso2'], $_dash_default_iso2 ); ?>>
                            <?php echo esc_html( isset( $_dlo['native_name'] ) ? $_dlo['native_name'] : $_dlo['iso2'] ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <input type="text" id="bs_seo_dashboard_search" class="ct-seo-form__input ct-seo-form__input--search"
                       placeholder="<?php esc_attr_e( 'Search pages...', 'ct-custom' ); ?>">
            </div>

            <div class="ct-seo-dashboard__table" id="bs_seo_dashboard_table">
                <p class="ct-seo-dashboard__loading"><?php esc_html_e( 'Loading SEO data...', 'ct-custom' ); ?></p>
            </div>

            <div class="ct-seo-dashboard__pagination" id="bs_seo_dashboard_pagination"></div>

            <div class="ct-seo-dashboard__actions">
                <button type="button" id="bs_seo_bulk_analyze" class="button button-primary"><?php esc_html_e( 'Bulk Analyze', 'ct-custom' ); ?></button>
                <button type="button" id="bs_seo_ping_engines" class="button"><?php esc_html_e( 'Ping Search Engines', 'ct-custom' ); ?></button>
            </div>
        </div>
    </div>


</div>
