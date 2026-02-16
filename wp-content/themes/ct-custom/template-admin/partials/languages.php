<?php
/**
 * Languages admin partial.
 *
 * Three CSS-only sub-tabs:
 * A) Language Management
 * B) Translation Editor
 * C) Page Translations
 *
 * @package BS_Custom
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$ct_lang_mgr   = ct_get_language_manager();
$ct_languages  = $ct_lang_mgr->get_all();
$ct_default    = $ct_lang_mgr->get_default();
$ct_default_iso = ( null !== $ct_default ) ? $ct_default['iso2'] : 'en';
?>

<div class="ct-admin-section ct-lang-section"
     data-nonce="<?php echo esc_attr( wp_create_nonce( 'ct_lang_nonce' ) ); ?>"
     data-ajax-url="<?php echo esc_attr( admin_url( 'admin-ajax.php' ) ); ?>">

    <!-- Radio inputs at wrapper level so ~ combinator reaches panels -->
    <input type="radio" name="ct_lang_tab" id="ct_lang_tab_manage" class="ct-lang-tabs__radio" checked>
    <input type="radio" name="ct_lang_tab" id="ct_lang_tab_translate" class="ct-lang-tabs__radio">
    <input type="radio" name="ct_lang_tab" id="ct_lang_tab_pages" class="ct-lang-tabs__radio">

    <!-- Sub-tab navigation labels -->
    <div class="ct-lang-tabs">
        <label for="ct_lang_tab_manage" class="ct-lang-tabs__label"><?php esc_html_e( 'Languages', 'ct-custom' ); ?></label>
        <label for="ct_lang_tab_translate" class="ct-lang-tabs__label"><?php esc_html_e( 'Translations', 'ct-custom' ); ?></label>
        <label for="ct_lang_tab_pages" class="ct-lang-tabs__label"><?php esc_html_e( 'Page Translations', 'ct-custom' ); ?></label>
    </div>

    <!-- ═══ Sub-Tab A: Language Management ═══ -->
    <div class="ct-lang-panel ct-lang-panel--manage">
        <h3><?php esc_html_e( 'Add Language', 'ct-custom' ); ?></h3>

        <form id="ct_add_language_form" class="ct-lang-add-form">
            <div class="ct-lang-add-form__row">
                <div class="ct-lang-add-form__field">
                    <label for="ct_lang_iso2"><?php esc_html_e( 'ISO 639-1 Code (2 letters)', 'ct-custom' ); ?></label>
                    <input type="text" id="ct_lang_iso2" maxlength="2" pattern="[a-z]{2}" required
                           placeholder="<?php esc_attr_e( 'e.g. fr', 'ct-custom' ); ?>">
                    <span class="ct-lang-add-form__error" id="ct_lang_iso2_error"></span>
                </div>
                <div class="ct-lang-add-form__field">
                    <label for="ct_lang_iso3"><?php esc_html_e( 'ISO 639-2 Code (3 letters)', 'ct-custom' ); ?></label>
                    <input type="text" id="ct_lang_iso3" maxlength="3" pattern="[a-z]{3}"
                           placeholder="<?php esc_attr_e( 'e.g. fra', 'ct-custom' ); ?>">
                    <span class="ct-lang-add-form__error" id="ct_lang_iso3_error"></span>
                </div>
            </div>
            <div class="ct-lang-add-form__row">
                <div class="ct-lang-add-form__field">
                    <label for="ct_lang_native_name"><?php esc_html_e( 'Native Name', 'ct-custom' ); ?></label>
                    <input type="text" id="ct_lang_native_name" maxlength="100" required
                           placeholder="<?php esc_attr_e( 'e.g. Français', 'ct-custom' ); ?>">
                    <span class="ct-lang-add-form__error" id="ct_lang_native_name_error"></span>
                </div>
                <div class="ct-lang-add-form__field">
                    <label for="ct_lang_locale"><?php esc_html_e( 'Primary Locale', 'ct-custom' ); ?></label>
                    <input type="text" id="ct_lang_locale" maxlength="10" pattern="[a-z]{2}_[A-Z]{2}"
                           placeholder="<?php esc_attr_e( 'e.g. fr_FR', 'ct-custom' ); ?>">
                    <span class="ct-lang-add-form__error" id="ct_lang_locale_error"></span>
                </div>
            </div>
            <div class="ct-lang-add-form__row">
                <div class="ct-lang-add-form__field ct-lang-add-form__field--flag">
                    <label><?php esc_html_e( 'Flag', 'ct-custom' ); ?></label>
                    <div class="ct-lang-flag-picker">
                        <input type="hidden" id="ct_lang_flag" value="">
                        <img id="ct_lang_flag_preview" src="" alt="" class="ct-lang-flag-picker__preview" style="display:none;">
                        <button type="button" id="ct_lang_flag_btn" class="button"><?php esc_html_e( 'Choose Flag', 'ct-custom' ); ?></button>
                        <button type="button" id="ct_lang_flag_remove" class="button ct-lang-flag-picker__remove" style="display:none;">&times;</button>
                    </div>
                </div>
            </div>
            <div class="ct-lang-add-form__actions">
                <button type="submit" class="button button-primary" id="ct_lang_submit_btn" disabled><?php esc_html_e( 'Add Language', 'ct-custom' ); ?></button>
                <span class="ct-lang-add-form__status"></span>
            </div>
        </form>

        <hr class="ct-admin-divider">

        <h3><?php esc_html_e( 'Registered Languages', 'ct-custom' ); ?></h3>

        <table class="ct-lang-table" id="ct_language_table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Flag', 'ct-custom' ); ?></th>
                    <th><?php esc_html_e( 'Code', 'ct-custom' ); ?></th>
                    <th><?php esc_html_e( 'Name', 'ct-custom' ); ?></th>
                    <th><?php esc_html_e( 'Locales', 'ct-custom' ); ?></th>
                    <th><?php esc_html_e( 'Default', 'ct-custom' ); ?></th>
                    <th><?php esc_html_e( 'Enabled', 'ct-custom' ); ?></th>
                    <th><?php esc_html_e( 'Actions', 'ct-custom' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $ct_max_langs  = 50;
                $ct_lang_count = 0;
                foreach ( $ct_languages as $ct_lang ) :
                    if ( $ct_lang_count >= $ct_max_langs ) { break; }
                    $ct_lang_count++;
                    $ct_is_default = ! empty( $ct_lang['is_default'] );
                    $ct_is_enabled = ! empty( $ct_lang['enabled'] );
                ?>
                <tr data-iso2="<?php echo esc_attr( $ct_lang['iso2'] ); ?>">
                    <td>
                        <?php if ( ! empty( $ct_lang['flag'] ) ) : ?>
                            <img src="<?php echo esc_url( $ct_lang['flag'] ); ?>" alt="" class="ct-lang-table__flag">
                        <?php else : ?>
                            <span class="ct-lang-table__flag-placeholder"><?php echo esc_html( strtoupper( $ct_lang['iso2'] ) ); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><code><?php echo esc_html( $ct_lang['iso2'] ); ?></code></td>
                    <td><?php echo esc_html( $ct_lang['native_name'] ); ?></td>
                    <td><?php echo esc_html( implode( ', ', isset( $ct_lang['locales'] ) ? $ct_lang['locales'] : array() ) ); ?></td>
                    <td>
                        <?php if ( $ct_is_default ) : ?>
                            <span class="ct-lang-table__badge ct-lang-table__badge--default"><?php esc_html_e( 'Default', 'ct-custom' ); ?></span>
                        <?php else : ?>
                            <button type="button" class="button button-small ct-lang-set-default"><?php esc_html_e( 'Set Default', 'ct-custom' ); ?></button>
                        <?php endif; ?>
                    </td>
                    <td>
                        <label class="ct-lang-toggle">
                            <input type="checkbox" class="ct-lang-toggle__input ct-lang-enable-toggle"
                                   <?php checked( $ct_is_enabled ); ?>
                                   <?php disabled( $ct_is_default ); ?>>
                            <span class="ct-lang-toggle__slider"></span>
                        </label>
                    </td>
                    <td>
                        <button type="button" class="button button-small ct-lang-edit"
                                data-iso2="<?php echo esc_attr( $ct_lang['iso2'] ); ?>"
                                data-iso3="<?php echo esc_attr( isset( $ct_lang['iso3'] ) ? $ct_lang['iso3'] : '' ); ?>"
                                data-native-name="<?php echo esc_attr( $ct_lang['native_name'] ); ?>"
                                data-locale="<?php echo esc_attr( ! empty( $ct_lang['locales'] ) ? $ct_lang['locales'][0] : '' ); ?>"
                                data-flag="<?php echo esc_attr( isset( $ct_lang['flag'] ) ? $ct_lang['flag'] : '' ); ?>"
                                title="<?php esc_attr_e( 'Edit', 'ct-custom' ); ?>"><?php esc_html_e( 'Edit', 'ct-custom' ); ?></button>
                        <?php if ( ! $ct_is_default ) : ?>
                            <button type="button" class="button button-small ct-lang-remove" title="<?php esc_attr_e( 'Remove', 'ct-custom' ); ?>">&times;</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Edit Language Modal -->
        <div class="ct-lang-edit-modal" id="ct_lang_edit_modal" role="dialog" aria-labelledby="ct_lang_edit_title" aria-modal="true" style="display:none;">
            <div class="ct-lang-edit-modal__backdrop"></div>
            <div class="ct-lang-edit-modal__panel">
                <h3 class="ct-lang-edit-modal__title" id="ct_lang_edit_title">
                    <?php esc_html_e( 'Edit Language', 'ct-custom' ); ?>
                    <span class="ct-lang-edit-modal__badge" id="ct_edit_iso2_badge"></span>
                </h3>
                <form id="ct_edit_language_form" class="ct-lang-add-form">
                    <input type="hidden" id="ct_edit_iso2" value="">
                    <div class="ct-lang-add-form__row">
                        <div class="ct-lang-add-form__field">
                            <label for="ct_edit_native_name"><?php esc_html_e( 'Native Name', 'ct-custom' ); ?></label>
                            <input type="text" id="ct_edit_native_name" maxlength="100" required
                                   placeholder="<?php esc_attr_e( 'e.g. Français', 'ct-custom' ); ?>">
                        </div>
                        <div class="ct-lang-add-form__field">
                            <label for="ct_edit_iso3"><?php esc_html_e( 'ISO 639-2 Code (3 letters)', 'ct-custom' ); ?></label>
                            <input type="text" id="ct_edit_iso3" maxlength="3" pattern="[a-z]{3}"
                                   placeholder="<?php esc_attr_e( 'e.g. fra', 'ct-custom' ); ?>">
                        </div>
                    </div>
                    <div class="ct-lang-add-form__row">
                        <div class="ct-lang-add-form__field">
                            <label for="ct_edit_locale"><?php esc_html_e( 'Primary Locale', 'ct-custom' ); ?></label>
                            <input type="text" id="ct_edit_locale" maxlength="10" pattern="[a-z]{2}_[A-Z]{2}"
                                   placeholder="<?php esc_attr_e( 'e.g. fr_FR', 'ct-custom' ); ?>">
                        </div>
                        <div class="ct-lang-add-form__field ct-lang-add-form__field--flag">
                            <label><?php esc_html_e( 'Flag', 'ct-custom' ); ?></label>
                            <div class="ct-lang-flag-picker">
                                <input type="hidden" id="ct_edit_flag" value="">
                                <img id="ct_edit_flag_preview" src="" alt="" class="ct-lang-flag-picker__preview" style="display:none;">
                                <button type="button" id="ct_edit_flag_btn" class="button"><?php esc_html_e( 'Choose Flag', 'ct-custom' ); ?></button>
                                <button type="button" id="ct_edit_flag_remove" class="button ct-lang-flag-picker__remove" style="display:none;">&times;</button>
                            </div>
                        </div>
                    </div>
                    <div class="ct-lang-edit-modal__actions">
                        <button type="button" id="ct_edit_cancel" class="button"><?php esc_html_e( 'Cancel', 'ct-custom' ); ?></button>
                        <button type="submit" class="button button-primary"><?php esc_html_e( 'Save Changes', 'ct-custom' ); ?></button>
                        <span class="ct-lang-edit-modal__status" id="ct_edit_status"></span>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ═══ Sub-Tab B: Translation Editor ═══ -->
    <div class="ct-lang-panel ct-lang-panel--translate">
        <div class="ct-trans-editor">
            <div class="ct-trans-editor__sidebar">
                <div class="ct-trans-editor__search">
                    <input type="text" id="ct_trans_search" placeholder="<?php esc_attr_e( 'Search keys...', 'ct-custom' ); ?>">
                </div>
                <div class="ct-trans-editor__actions">
                    <button type="button" id="ct_trans_add_key" class="button button-small"><?php esc_html_e( '+ Add Key', 'ct-custom' ); ?></button>
                </div>
                <ul class="ct-trans-editor__key-list" id="ct_trans_key_list">
                    <li class="ct-trans-editor__key-loading"><?php esc_html_e( 'Loading...', 'ct-custom' ); ?></li>
                </ul>
            </div>
            <div class="ct-trans-editor__main">
                <div class="ct-trans-editor__placeholder" id="ct_trans_placeholder">
                    <p><?php esc_html_e( 'Select a translation key from the sidebar to edit its values.', 'ct-custom' ); ?></p>
                </div>
                <div class="ct-trans-editor__fields" id="ct_trans_fields" style="display:none;">
                    <div class="ct-trans-editor__key-header">
                        <h4 id="ct_trans_current_key"></h4>
                        <div class="ct-trans-type-toggle" id="ct_trans_type_toggle">
                            <button type="button" class="ct-trans-type-toggle__btn ct-trans-type-toggle__btn--active"
                                    data-type="singular" id="ct_trans_type_singular"><?php esc_html_e( 'Singular', 'ct-custom' ); ?></button>
                            <button type="button" class="ct-trans-type-toggle__btn"
                                    data-type="plural" id="ct_trans_type_plural"><?php esc_html_e( 'Plural', 'ct-custom' ); ?></button>
                        </div>
                        <button type="button" id="ct_trans_delete_key" class="button button-small ct-trans-editor__delete"><?php esc_html_e( 'Delete Key', 'ct-custom' ); ?></button>
                    </div>
                    <div class="ct-trans-placeholders" id="ct_trans_placeholders" style="display:none;">
                        <span class="ct-trans-placeholders__label"><?php esc_html_e( 'Arguments:', 'ct-custom' ); ?></span>
                        <span class="ct-trans-placeholders__list" id="ct_trans_placeholder_list"></span>
                        <span class="ct-trans-placeholders__hint"><?php esc_html_e( 'Use ##name## syntax in translation text', 'ct-custom' ); ?></span>
                    </div>
                    <div id="ct_trans_lang_fields">
                        <!-- Dynamically populated per-language input fields -->
                    </div>
                    <span class="ct-trans-editor__status" id="ct_trans_status"></span>
                </div>
            </div>
        </div>

        <hr class="ct-admin-divider">

        <div class="ct-trans-bulk">
            <button type="button" id="ct_trans_export" class="button"><?php esc_html_e( 'Export Translations', 'ct-custom' ); ?></button>
            <button type="button" id="ct_trans_import" class="button"><?php esc_html_e( 'Import Translations', 'ct-custom' ); ?></button>
            <input type="file" id="ct_trans_import_file" accept=".json" style="display:none;">
        </div>

        <!-- Add Translation Key Modal -->
        <div class="ct-add-key-modal" id="ct_add_key_modal" role="dialog" aria-labelledby="ct_add_key_title" aria-modal="true" style="display:none;">
            <div class="ct-add-key-modal__backdrop"></div>
            <div class="ct-add-key-modal__panel">
                <h3 class="ct-add-key-modal__title" id="ct_add_key_title"><?php esc_html_e( 'Add Translation Key', 'ct-custom' ); ?></h3>
                <div class="ct-add-key-modal__body">
                    <label for="ct_add_key_input"><?php esc_html_e( 'Translation Key', 'ct-custom' ); ?></label>
                    <input type="text" id="ct_add_key_input" class="ct-admin-input"
                           placeholder="<?php esc_attr_e( 'e.g. BUTTON_LABEL', 'ct-custom' ); ?>"
                           maxlength="100" autocomplete="off">
                    <p class="ct-add-key-modal__hint"><?php esc_html_e( 'Use uppercase letters, numbers, and underscores. Other characters will be converted automatically.', 'ct-custom' ); ?></p>
                </div>
                <div class="ct-add-key-modal__actions">
                    <button type="button" id="ct_add_key_cancel" class="button"><?php esc_html_e( 'Cancel', 'ct-custom' ); ?></button>
                    <button type="button" id="ct_add_key_confirm" class="button button-primary"><?php esc_html_e( 'Add Key', 'ct-custom' ); ?></button>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══ Sub-Tab C: Page Translations ═══ -->
    <div class="ct-lang-panel ct-lang-panel--pages">
        <div class="ct-page-trans__filter">
            <label for="ct_page_lang_filter"><?php esc_html_e( 'Language:', 'ct-custom' ); ?></label>
            <select id="ct_page_lang_filter">
                <?php
                $ct_p_lang_count = 0;
                foreach ( $ct_languages as $ct_p_lang ) :
                    if ( $ct_p_lang_count >= $ct_max_langs ) { break; }
                    $ct_p_lang_count++;
                ?>
                    <option value="<?php echo esc_attr( $ct_p_lang['iso2'] ); ?>"
                            <?php selected( $ct_p_lang['iso2'], $ct_default_iso ); ?>>
                        <?php echo esc_html( $ct_p_lang['native_name'] . ' (' . $ct_p_lang['iso2'] . ')' ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div id="ct_page_list" class="ct-page-trans__list">
            <p class="ct-page-trans__loading"><?php esc_html_e( 'Select a language to load pages.', 'ct-custom' ); ?></p>
        </div>
    </div>

</div>
