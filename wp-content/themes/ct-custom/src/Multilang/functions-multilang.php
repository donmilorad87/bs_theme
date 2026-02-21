<?php
/**
 * Multilanguage global helper functions.
 *
 * Thin wrappers that delegate to BS_Multilang_Service singleton.
 * Keeps backward compatibility for the 23+ call sites across
 * the codebase that use these functions directly.
 *
 * @package BS_Custom
 */

use BSCustom\Multilang\MultilangService;
use BSCustom\Multilang\Translator;
use BSCustom\Multilang\LanguageManager;

/**
 * Get the current language iso2 code for the current page/post.
 *
 * @return string
 */
function bs_get_current_language(): string {
    return MultilangService::instance()->get_current_language();
}

/**
 * Get the current locale for the current page/post.
 *
 * @return string|null
 */
function bs_get_current_locale(): ?string {
    return MultilangService::instance()->get_current_locale();
}

/**
 * Get (or create) the translator singleton for this request.
 *
 * @return Translator
 */
function bs_get_translator(): Translator {
    return MultilangService::instance()->get_translator();
}

/**
 * Translate a key (HTML-escaped).
 *
 * @param string          $key   Translation key.
 * @param array           $args  Placeholder arguments.
 * @param string|int|null $count Count for CLDR resolution, or form name (e.g. "one", "few").
 * @return string
 */
function bs_translate( string $key, array $args = array(), string|int|null $count = null ): string {
    return MultilangService::instance()->translate( $key, $args, $count );
}

/**
 * Echo a translated key (HTML-escaped).
 *
 * @param string          $key   Translation key.
 * @param array           $args  Placeholder arguments.
 * @param string|int|null $count Count for CLDR resolution, or form name (e.g. "one", "few").
 * @return void
 */
function bs_translate_echo( string $key, array $args = array(), string|int|null $count = null ): void {
    MultilangService::instance()->translate_echo( $key, $args, $count );
}

/**
 * Get the language manager singleton.
 *
 * @return LanguageManager
 */
function bs_get_language_manager(): LanguageManager {
    return MultilangService::instance()->get_language_manager();
}

/**
 * Get the homepage URL for the current language.
 *
 * Default language returns home_url('/'), other languages
 * return home_url('/{iso2}/').
 *
 * @return string
 */
function bs_get_language_home_url(): string {
    $current = bs_get_current_language();
    $mgr     = bs_get_language_manager();
    $default = $mgr->get_default();
    $def_iso = ( null !== $default ) ? $default['iso2'] : 'en';

    if ( $current === $def_iso ) {
        return home_url( '/' );
    }

    return home_url( '/' . $current . '/' );
}
