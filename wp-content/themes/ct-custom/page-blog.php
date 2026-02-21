<?php
/**
 * Template for language blog pages (slug: blog).
 *
 * Displays posts filtered by language category/tag.
 *
 * @package BS_Custom
 */

get_header();

$page_id = function_exists( 'get_queried_object_id' ) ? (int) get_queried_object_id() : 0;
$lang    = '';

if ( $page_id > 0 ) {
    $lang = get_post_meta( $page_id, 'bs_language', true );
}

if ( ! is_string( $lang ) || '' === $lang ) {
    $lang = function_exists( 'bs_get_current_language' ) ? bs_get_current_language() : '';
}

$lang = sanitize_key( (string) $lang );

$native_name = '';
if ( '' !== $lang && function_exists( 'bs_get_language_manager' ) ) {
    $mgr      = bs_get_language_manager();
    $lang_obj = $mgr->get_by_iso2( $lang );

    if ( is_array( $lang_obj ) && ! empty( $lang_obj['native_name'] ) ) {
        $native_name = (string) $lang_obj['native_name'];
    }
}

if ( '' === $native_name ) {
    $native_name = $lang;
}

$cat_ids = array();
if ( '' !== $native_name ) {
    $cat_slug = sanitize_title( $native_name );
    $cat_term = ( '' !== $cat_slug ) ? get_term_by( 'slug', $cat_slug, 'category' ) : null;

    if ( $cat_term && ! is_wp_error( $cat_term ) ) {
        $cat_ids[] = (int) $cat_term->term_id;
        $children  = get_term_children( $cat_term->term_id, 'category' );

        if ( is_array( $children ) ) {
            foreach ( $children as $child_id ) {
                $cat_ids[] = (int) $child_id;
            }
        }
    }
}

$cat_ids = array_values( array_unique( array_filter( array_map( 'intval', $cat_ids ) ) ) );

$tag_id = 0;
if ( '' !== $lang ) {
    $tag_slug = sanitize_key( $lang );
    $tag_term = ( '' !== $tag_slug ) ? get_term_by( 'slug', $tag_slug, 'post_tag' ) : null;

    if ( $tag_term && ! is_wp_error( $tag_term ) ) {
        $tag_id = (int) $tag_term->term_id;
    }
}

$tax_query = array();

if ( ! empty( $cat_ids ) ) {
    $tax_query[] = array(
        'taxonomy'         => 'category',
        'field'            => 'term_id',
        'terms'            => $cat_ids,
        'include_children' => false,
    );
}

if ( $tag_id > 0 ) {
    $tax_query[] = array(
        'taxonomy'         => 'post_tag',
        'field'            => 'term_id',
        'terms'            => array( $tag_id ),
        'include_children' => false,
    );
}

if ( count( $tax_query ) > 1 ) {
    $tax_query = array_merge( array( 'relation' => 'OR' ), $tax_query );
}

$paged = 1;
$paged_var = get_query_var( 'paged' );
if ( $paged_var ) {
    $paged = max( 1, (int) $paged_var );
}

$page_var = get_query_var( 'page' );
if ( $page_var ) {
    $paged = max( $paged, (int) $page_var );
}

$query_args = array(
    'post_type'      => 'post',
    'post_status'    => 'publish',
    'posts_per_page' => (int) get_option( 'posts_per_page' ),
    'paged'          => $paged,
);

if ( ! empty( $tax_query ) ) {
    $query_args['tax_query'] = $tax_query;
} else {
    $query_args['post__in'] = array( 0 );
}

$posts_query = new \WP_Query( $query_args );
?>

<div id="primary" class="content-area">
    <main id="main" class="site-main" role="main">
        <?php
        if ( have_posts() ) :
            while ( have_posts() ) :
                the_post();
                ?>
                <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                    <h1 class="entry-title page-title"><?php the_title(); ?></h1>

                    <div class="entry-content">
                        <?php
                        the_content();
                        wp_link_pages( array(
                            'before' => '<div class="page-links">' . esc_html__( 'Pages:', 'ct-custom' ),
                            'after'  => '</div>',
                        ) );
                        ?>
                    </div>
                </article>
                <?php
            endwhile;
        endif;

        wp_reset_postdata();

        if ( $posts_query->have_posts() ) :
            global $wp_query;
            $temp_query = $wp_query;
            $wp_query   = $posts_query;

            while ( $posts_query->have_posts() ) :
                $posts_query->the_post();
                get_template_part( 'template-parts/content', get_post_type() );
            endwhile;

            the_posts_navigation();

            $wp_query = $temp_query;
            wp_reset_postdata();
        else :
            get_template_part( 'template-parts/content', 'none' );
        endif;
        ?>
    </main>
</div>

<?php
get_footer();
