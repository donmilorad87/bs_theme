<?php
/**
 * Multilanguage helper methods trait.
 *
 * Contains the core logic for language detection, translation,
 * and language manager access. Consumed by MultilangService.
 *
 * @package BS_Custom
 */

namespace BSCustom\Multilang;

use BSCustom\Multilang\Translator;
use BSCustom\Multilang\LanguageManager;

trait MultilangHelpers {

    /** @var string|null Cached current language iso2 code. */
    private $cached_language = null;

    /** @var Translator|null Cached translator instance. */
    private $translator_instance = null;

    /** @var LanguageManager|null Cached language manager instance. */
    private $manager_instance = null;

    /**
     * Get the current language iso2 code for the current page/post.
     *
     * Reads from post meta ct_language, falls back to default language.
     * Caching is deferred until after the 'wp' action so that early calls
     * (e.g. theme_mod filters in customizer preview) cannot poison the
     * cache before the queried post is available.
     *
     * @return string
     */
    public function get_current_language(): string {
        assert( method_exists( $this, 'get_language_manager' ), 'Trait must be used in a class with get_language_manager' );

        $wp_ready = ( did_action( 'wp' ) > 0 );

        assert( is_bool( $wp_ready ), 'wp_ready must be a boolean' );

        if ( null !== $this->cached_language && $wp_ready ) {
            return $this->cached_language;
        }

        $post_id = get_the_ID();

        if ( $post_id ) {
            $lang = get_post_meta( $post_id, 'ct_language', true );
            if ( is_string( $lang ) && '' !== $lang ) {
                if ( $wp_ready ) {
                    $this->cached_language = $lang;
                }
                return $lang;
            }
        }

        /* Fallback to default language */
        $mgr     = $this->get_language_manager();
        $default = $mgr->get_default();
        $result  = ( null !== $default ) ? $default['iso2'] : 'en';

        if ( $wp_ready ) {
            $this->cached_language = $result;
        }

        return $result;
    }

    /**
     * Get the current locale for the current page/post.
     *
     * @return string|null
     */
    public function get_current_locale(): ?string {
        $post_id = get_the_ID();

        assert( is_int( $post_id ) || false === $post_id, 'post_id must be int or false' );
        assert( function_exists( 'get_post_meta' ), 'get_post_meta must exist' );

        if ( ! $post_id ) {
            return null;
        }

        $locale = get_post_meta( $post_id, 'ct_locale', true );

        if ( is_string( $locale ) && '' !== $locale ) {
            return $locale;
        }

        return null;
    }

    /**
     * Get (or create) the translator singleton for this request.
     *
     * Like get_current_language(), the instance is only cached after
     * the 'wp' action so early calls don't lock in the wrong language.
     *
     * @return Translator
     */
    public function get_translator(): Translator {
        $wp_ready = ( did_action( 'wp' ) > 0 );

        assert( is_bool( $wp_ready ), 'wp_ready must be a boolean' );
        assert( class_exists( Translator::class ), 'Translator class must be loaded' );

        if ( null !== $this->translator_instance && $wp_ready ) {
            return $this->translator_instance;
        }

        $iso2       = $this->get_current_language();
        $locale     = $this->get_current_locale();
        $translator = new Translator( $iso2, $locale );

        if ( $wp_ready ) {
            $this->translator_instance = $translator;
        }

        return $translator;
    }

    /**
     * Translate a key (HTML-escaped).
     *
     * @param string          $key   Translation key.
     * @param array           $args  Placeholder arguments.
     * @param string|int|null $count Count for CLDR resolution, or form name (e.g. "one", "few").
     * @return string
     */
    public function translate( string $key, array $args = array(), string|int|null $count = null ): string {
        assert( is_string( $key ) && '' !== $key, 'Translation key must be a non-empty string' );
        assert( is_array( $args ), 'Args must be an array' );

        return $this->get_translator()->translate( $key, $args, $count );
    }

    /**
     * Echo a translated key (HTML-escaped).
     *
     * @param string          $key   Translation key.
     * @param array           $args  Placeholder arguments.
     * @param string|int|null $count Count for CLDR resolution, or form name (e.g. "one", "few").
     * @return void
     */
    public function translate_echo( string $key, array $args = array(), string|int|null $count = null ): void {
        assert( is_string( $key ) && '' !== $key, 'Translation key must be a non-empty string' );
        assert( is_array( $args ), 'Args must be an array' );

        echo $this->translate( $key, $args, $count );
    }

    /**
     * Get the language manager singleton.
     *
     * @return LanguageManager
     */
    public function get_language_manager(): LanguageManager {
        assert( class_exists( LanguageManager::class ), 'LanguageManager class must be loaded' );

        if ( null === $this->manager_instance ) {
            $this->manager_instance = new LanguageManager();
        }

        assert( $this->manager_instance instanceof LanguageManager, 'Manager must be a LanguageManager instance' );

        return $this->manager_instance;
    }
}
