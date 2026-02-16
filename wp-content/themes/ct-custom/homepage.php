<?php
/**
 * Template Name: Homepage
 *
 * @package CT_Custom
 */

extract( ct_custom_get_homepage_hero_data() );

get_header();
?>

<div id="primary" class="content-area">
    <main id="main" class="site-main" role="main">

        <!-- hentry (mf1) + h-entry (mf2) microformat markup for parsers/SEO -->
        <article class="homepage-article hentry h-entry">

            <!-- Hidden microformat metadata: permalink, publish date, modified date -->
            <a class="u-url" href="<?php echo esc_url( get_permalink() ); ?>" hidden></a>
            <time class="dt-published" datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>" hidden><?php echo esc_html( get_the_date() ); ?></time>
            <time class="dt-updated updated" datetime="<?php echo esc_attr( get_the_modified_date( 'c' ) ); ?>" hidden><?php echo esc_html( get_the_modified_date() ); ?></time>

            <section class="hero-section">
                <div class="ct-container">
                    <h1 class="hero-section__title p-name"><?php echo esc_html( $hero_title ); ?></h1>
                    <p class="hero-section__description p-summary"><?php echo esc_html( $hero_description ); ?></p>
                </div>
            </section>

            <div class="e-content">
                <section class="image-grid">
                    <div class="ct-container">
                        <div class="image-grid__columns df fww" id="ct-homepage-image-grid">
                            <?php ct_custom_render_image_grid_items(); ?>
                        </div>
                    </div>
                </section>

                <section class="content-section">
                    <div class="ct-container">
                        <h2 class="content-section__title"><?php echo esc_html( $section2_title ); ?></h2>
                        <p class="content-section__description"><?php echo esc_html( $section2_desc ); ?></p>
                    </div>
                </section>
            </div>

        </article>

    </main>
</div>

<?php
get_footer();
