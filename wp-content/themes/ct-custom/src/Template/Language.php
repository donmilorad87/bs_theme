<?php
/**
 * Language singleton.
 *
 * Provides data-preparation for the language switcher component,
 * page translation lookups, and menu location resolution.
 *
 * @package BS_Custom
 */

namespace BSCustom\Template;

use BSCustom\Multilang\LanguagePageManager;

class Language {

	/** @var Language|null Singleton instance. */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		assert( null === self::$instance || self::$instance instanceof self, 'Instance must be null or Language' );

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		assert( self::$instance instanceof self, 'Instance must be Language' );

		return self::$instance;
	}

	/**
	 * Private constructor to enforce singleton pattern.
	 */
	private function __construct() {}

	/**
	 * Get all translations for a given post (keyed by iso2 => post_id).
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, int>
	 */
	public function get_page_translations( int $post_id ): array {
		assert( $post_id > 0, 'post_id must be positive' );
		assert( class_exists( LanguagePageManager::class ), 'LanguagePageManager must be loaded' );

		$page_mgr = new LanguagePageManager();
		return $page_mgr->get_translations( $post_id );
	}

	/**
	 * Get the translated page ID for a specific language.
	 *
	 * @param int    $post_id Source post ID.
	 * @param string $iso2    Target language code.
	 * @return int|null Post ID or null.
	 */
	public function get_page_for_language( int $post_id, string $iso2 ): ?int {
		assert( $post_id > 0, 'post_id must be positive' );
		assert( '' !== $iso2, 'iso2 must be non-empty' );

		$page_mgr = new LanguagePageManager();
		return $page_mgr->get_page_for_language( $post_id, $iso2 );
	}

	/**
	 * Get the homepage URL for a specific language.
	 *
	 * @param string $iso2 Language code.
	 * @return string URL.
	 */
	public function get_homepage_for_language( string $iso2 ): string {
		assert( '' !== $iso2, 'iso2 must be non-empty' );
		assert( function_exists( 'get_option' ), 'get_option must exist' );

		$front_page_id = (int) get_option( 'page_on_front' );

		if ( $front_page_id > 0 ) {
			$translated = $this->get_page_for_language( $front_page_id, $iso2 );

			if ( null !== $translated ) {
				$url = get_permalink( $translated );
				if ( $url ) {
					return $url;
				}
			}
		}

		return home_url( '/' );
	}

	/**
	 * Build the language switcher data array.
	 *
	 * @return array{items: array, current_iso2: string, is_multilingual: bool}
	 */
	public function get_language_switcher_data(): array {
		assert( function_exists( 'bs_get_language_manager' ), 'bs_get_language_manager must exist' );
		assert( function_exists( 'bs_get_current_language' ), 'bs_get_current_language must exist' );

		$mgr       = bs_get_language_manager();
		$languages = $mgr->get_enabled();

		if ( count( $languages ) < 2 ) {
			return array(
				'items'           => array(),
				'current_iso2'    => bs_get_current_language(),
				'is_multilingual' => false,
			);
		}

		$current_iso2 = bs_get_current_language();
		$post_id      = get_the_ID();
		$translations = array();

		if ( $post_id ) {
			$translations = $this->get_page_translations( $post_id );
		}

		$items     = array();
		$max_langs = 50;
		$count     = 0;

		foreach ( $languages as $lang ) {
			if ( $count >= $max_langs ) {
				break;
			}
			$count++;

			$iso2       = $lang['iso2'];
			$is_current = ( $iso2 === $current_iso2 );

			$target_url = home_url( '/' );

			if ( $post_id && isset( $translations[ $iso2 ] ) ) {
				$translated_post = get_post( $translations[ $iso2 ] );
				if ( $translated_post && 'publish' === $translated_post->post_status ) {
					$permalink = get_permalink( $translated_post );
					if ( $permalink ) {
						$target_url = $permalink;
					}
				}
			} elseif ( ! $post_id || is_front_page() ) {
				$target_url = $this->get_homepage_for_language( $iso2 );
			}

			$items[] = array(
				'iso2'       => $iso2,
				'name'       => $lang['native_name'],
				'flag_url'   => ! empty( $lang['flag'] ) ? $lang['flag'] : '',
				'target_url' => $target_url,
				'is_current' => $is_current,
			);
		}

		return array(
			'items'           => $items,
			'current_iso2'    => $current_iso2,
			'is_multilingual' => ( count( $items ) >= 2 ),
		);
	}

	/**
	 * Check if the site has multiple languages enabled.
	 *
	 * @return bool
	 */
	public function is_multilingual(): bool {
		assert( function_exists( 'bs_get_language_manager' ), 'bs_get_language_manager must exist' );

		$mgr     = bs_get_language_manager();
		$enabled = $mgr->get_enabled();

		assert( is_array( $enabled ), 'enabled must be an array' );

		return count( $enabled ) >= 2;
	}

	/**
	 * Get the correct menu location for the current language.
	 *
	 * @param string $base_location Base menu location (e.g. 'main-menu').
	 * @return string Resolved menu location slug.
	 */
	public function get_menu_location( string $base_location ): string {
		assert( is_string( $base_location ) && '' !== $base_location, 'Base location must be a non-empty string' );

		$current       = bs_get_current_language();
		$lang_location = $base_location . '-' . $current;

		if ( has_nav_menu( $lang_location ) ) {
			return $lang_location;
		}

		$mgr     = bs_get_language_manager();
		$default = $mgr->get_default();
		$iso2    = ( null !== $default ) ? $default['iso2'] : 'en';

		assert( is_string( $iso2 ), 'Default language iso2 must be a string' );

		return $base_location . '-' . $iso2;
	}
}
