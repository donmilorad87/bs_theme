<?php
/**
 * The template for displaying all pages
 *
 * @package CT_Custom
 */

get_header();
?>

<div id="primary" class="content-area">
    <main id="main" class="site-main" role="main">
        <?php
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
            if ( comments_open() || get_comments_number() ) :
                comments_template();
            endif;
            ?>

        <?php endwhile; ?>
    </main>
</div>

<?php
get_footer();
