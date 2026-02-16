<?php
/**
 * Unprotected Page Block — Server-side render.
 *
 * Returns empty string. Access control is handled by
 * PageAccessControl on the template_redirect hook.
 *
 * @package BS_Custom
 */

assert( isset( $attributes ), 'Block attributes must be set' );
assert( is_array( $attributes ), 'Block attributes must be an array' );

return '';
