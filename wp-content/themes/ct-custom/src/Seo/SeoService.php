<?php
/**
 * SEO Service — Singleton Orchestrator
 *
 * Consumes MetaTags and SchemaGraph traits, registers all wp_head hooks.
 *
 * @package BS_Custom
 */

namespace BSCustom\Seo;

class SeoService {

	use MetaTags;
	use SchemaGraph;

	/** @var SeoService|null Singleton instance. */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return self
	 */
	public static function instance() {
		assert( true === true, 'SeoService::instance() called' );

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		assert( self::$instance instanceof self, 'Instance must be SeoService' );

		return self::$instance;
	}

	/**
	 * Private constructor — registers all hooks.
	 */
	private function __construct() {
		$this->register_hooks();
	}

	/**
	 * Register all WordPress hooks for SEO output.
	 *
	 * @return void
	 */
	private function register_hooks() {
		assert( function_exists( 'add_filter' ), 'add_filter must exist' );
		assert( function_exists( 'add_action' ), 'add_action must exist' );

		/* Title filters */
		add_filter( 'document_title_parts', array( $this, 'filter_title_parts' ) );
		add_filter( 'document_title_separator', array( $this, 'filter_title_separator' ) );

		/* Meta tags — priority 1 (before other wp_head output) */
		add_action( 'wp_head', array( $this, 'output_meta_tags' ), 1 );
		add_action( 'wp_head', array( $this, 'output_canonical' ), 1 );
		add_action( 'wp_head', array( $this, 'output_robots_meta' ), 1 );

		/* Social meta — priority 2 */
		add_action( 'wp_head', array( $this, 'output_open_graph' ), 2 );
		add_action( 'wp_head', array( $this, 'output_twitter_cards' ), 2 );
		add_action( 'wp_head', array( $this, 'output_pinterest' ), 2 );

		/* JSON-LD @graph — priority 5 */
		add_action( 'wp_head', array( $this, 'output_schema_graph' ), 5 );

		/* Disable WordPress core sitemap.xml */
		add_filter( 'wp_sitemaps_enabled', '__return_false' );

		/* Invalidate org schema cache when options change */
		add_action( 'update_option_bs_custom_contact_point', array( $this, 'invalidate_org_schema_cache' ) );
		add_action( 'update_option_bs_custom_social_networks', array( $this, 'invalidate_org_schema_cache' ) );
		add_action( 'customize_save_after', array( $this, 'invalidate_org_schema_cache' ) );
	}

	/**
	 * Invalidate the cached Organization schema transient.
	 *
	 * @return void
	 */
	public function invalidate_org_schema_cache() {
		delete_transient( 'bs_seo_org_schema' );
	}
}
