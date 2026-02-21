<?php
/**
 * Multilanguage helper trait for sidebar components.
 *
 * OOP wrapper around BS_Multilang_Service singleton methods.
 * Replaces direct calls to the procedural BS_* functions
 * (bs_get_current_language, bs_translate, etc.) with trait
 * methods that can be consumed by any class in the Sidebar namespace.
 *
 * Each method delegates to BS_Multilang_Service::instance() and
 * contains at least two assertions per NASA "Power of 10" rule 5.
 *
 * @package BS_Custom
 */

namespace BSCustom\Sidebar;

use BSCustom\Multilang\MultilangService;
use BSCustom\Multilang\Translator;
use BSCustom\Multilang\LanguageManager;

trait MultilangHelpers {

	/**
	 * Get the current language iso2 code for the current page/post.
	 *
	 * @return string Two-letter language code (e.g. 'en', 'es').
	 */
	public function get_current_language(): string {
		assert(
			class_exists( MultilangService::class ),
			'MultilangService class must be loaded'
		);

		$result = MultilangService::instance()->get_current_language();

		assert( is_string( $result ) && '' !== $result, 'Language code must be a non-empty string' );

		return $result;
	}

	/**
	 * Get the current locale for the current page/post.
	 *
	 * @return string|null Locale string (e.g. 'en_US') or null.
	 */
	public function get_current_locale(): ?string {
		assert(
			class_exists( MultilangService::class ),
			'MultilangService class must be loaded'
		);

		$result = MultilangService::instance()->get_current_locale();

		assert(
			null === $result || ( is_string( $result ) && '' !== $result ),
			'Locale must be null or a non-empty string'
		);

		return $result;
	}

	/**
	 * Get (or create) the translator singleton for this request.
	 *
	 * @return Translator
	 */
	public function get_translator(): Translator {
		assert(
			class_exists( MultilangService::class ),
			'MultilangService class must be loaded'
		);
		assert(
			class_exists( Translator::class ),
			'Translator class must be loaded'
		);

		$translator = MultilangService::instance()->get_translator();

		assert(
			$translator instanceof Translator,
			'Translator must be a Translator instance'
		);

		return $translator;
	}

	/**
	 * Translate a key (HTML-escaped).
	 *
	 * @param string          $key   Translation key.
	 * @param array           $args  Placeholder arguments.
	 * @param string|int|null $count Count for CLDR resolution, or form name.
	 * @return string Translated and escaped string.
	 */
	public function translate( string $key, array $args = array(), string|int|null $count = null ): string {
		assert( is_string( $key ) && '' !== $key, 'Translation key must be a non-empty string' );
		assert( is_array( $args ), 'Args must be an array' );

		return MultilangService::instance()->translate( $key, $args, $count );
	}

	/**
	 * Echo a translated key (HTML-escaped).
	 *
	 * @param string          $key   Translation key.
	 * @param array           $args  Placeholder arguments.
	 * @param string|int|null $count Count for CLDR resolution, or form name.
	 * @return void
	 */
	public function translate_echo( string $key, array $args = array(), string|int|null $count = null ): void {
		assert( is_string( $key ) && '' !== $key, 'Translation key must be a non-empty string' );
		assert( is_array( $args ), 'Args must be an array' );

		MultilangService::instance()->translate_echo( $key, $args, $count );
	}

	/**
	 * Get the language manager singleton.
	 *
	 * @return LanguageManager
	 */
	public function get_language_manager(): LanguageManager {
		assert(
			class_exists( MultilangService::class ),
			'MultilangService class must be loaded'
		);
		assert(
			class_exists( LanguageManager::class ),
			'LanguageManager class must be loaded'
		);

		$manager = MultilangService::instance()->get_language_manager();

		assert(
			$manager instanceof LanguageManager,
			'Manager must be a LanguageManager instance'
		);

		return $manager;
	}
}
