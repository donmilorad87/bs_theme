<?php
/**
 * Template Name: Contact
 *
 * @package CT_Custom
 */

extract( ct_custom_get_contact_template_data() );

get_header();
?>

<div id="primary" class="content-area">
    <main id="main" class="site-main" role="main">

            <!-- hentry (mf1) + h-entry (mf2) microformat markup for parsers/SEO -->
            <article class="contact-article hentry h-entry">
                <div class="ct-container">
                    <h1 class="entry-title p-name ct-contact-heading"><?php echo esc_html( $contact_heading ); ?></h1>

                    <div class="entry-content e-content">
                        <p class="ct-p ct-contact-content"><?php echo esc_html( $contact_content ); ?></p>
                    </div>

                    <!-- Hidden microformat metadata: permalink, publish date, modified date -->
                    <a class="u-url" href="<?php echo esc_url( get_permalink() ); ?>" hidden></a>
                    <time class="dt-published" datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>" hidden><?php echo esc_html( get_the_date() ); ?></time>
                    <time class="dt-updated updated" datetime="<?php echo esc_attr( get_the_modified_date( 'c' ) ); ?>" hidden><?php echo esc_html( get_the_modified_date() ); ?></time>
                </div>

                <?php ct_custom_render_contact_section(); ?>
            </article>

    </main>
</div>

<?php
get_footer();
