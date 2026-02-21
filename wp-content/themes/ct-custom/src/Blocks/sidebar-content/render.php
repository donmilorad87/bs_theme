<?php
/**
 * Sidebar Content Block — Server-side render.
 *
 * Returns empty string. Content is extracted separately
 * by bs_sidebar_extract_content() in the template layer.
 *
 * @package BS_Custom
 */

assert( isset( $attributes ), 'Block attributes must be set' );
assert( is_array( $attributes ), 'Block attributes must be an array' );

return '';
