<?php
/**
 * BS Team Members — Dynamic block render.
 *
 * Receives $attributes, $content, $block from WordPress.
 * Matches Blazing Sun who-we-are design pattern.
 *
 * @package BS_Custom
 */

use BSCustom\Multilang\TranslationService;

/**
 * Convert hex color + opacity to rgba() string.
 *
 * @param string $hex     Hex color (#RRGGBB or #RGB).
 * @param float  $opacity Opacity 0–1.
 * @return string rgba() value.
 */
if ( ! function_exists( 'ct_team_hex_to_rgba' ) ) :
function ct_team_hex_to_rgba( $hex, $opacity ) {
	assert( is_string( $hex ), 'Hex must be a string' );
	assert( is_numeric( $opacity ), 'Opacity must be numeric' );

	$hex = ltrim( $hex, '#' );

	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}

	if ( 6 !== strlen( $hex ) ) {
		return 'rgba(0, 0, 0, ' . floatval( $opacity ) . ')';
	}

	$r = hexdec( substr( $hex, 0, 2 ) );
	$g = hexdec( substr( $hex, 2, 2 ) );
	$b = hexdec( substr( $hex, 4, 2 ) );

	return 'rgba(' . $r . ', ' . $g . ', ' . $b . ', ' . floatval( $opacity ) . ')';
}
endif;

/* ── Extract existing attributes ── */
$members       = isset( $attributes['members'] ) ? $attributes['members'] : array();
$visible_count = isset( $attributes['visibleCount'] ) ? absint( $attributes['visibleCount'] ) : 8;
$button_text   = isset( $attributes['buttonText'] ) ? $attributes['buttonText'] : 'Meet Our Team';
$total         = count( $members );

/* ── Extract configurable attributes ── */
$hide_text         = isset( $attributes['hideText'] ) ? $attributes['hideText'] : 'Hide';

/* Resolve ct_translate() patterns if Translation Service is available */
if ( class_exists( TranslationService::class ) ) {
	$button_text = TranslationService::resolve_raw( $button_text );
	$hide_text   = TranslationService::resolve_raw( $hide_text );
}

/* Light colors */
$grid_bg_color     = isset( $attributes['gridBgColor'] ) ? $attributes['gridBgColor'] : '#4a6eb0';
$overlay_color     = isset( $attributes['imageOverlayColor'] ) ? $attributes['imageOverlayColor'] : '#355fad';
$showmore_bg_color = isset( $attributes['showmoreBgColor'] ) ? $attributes['showmoreBgColor'] : '#021327';
$showmore_opacity  = isset( $attributes['showmoreBgOpacity'] ) ? floatval( $attributes['showmoreBgOpacity'] ) : 0.65;
$button_bg_color   = isset( $attributes['buttonBgColor'] ) ? $attributes['buttonBgColor'] : '#a8c930';
$btn_hover_color   = isset( $attributes['buttonHoverColor'] ) ? $attributes['buttonHoverColor'] : '#7e9724';
$name_color        = isset( $attributes['nameColor'] ) ? $attributes['nameColor'] : '#4eafdc';
$pos_color         = isset( $attributes['positionColor'] ) ? $attributes['positionColor'] : '#4eafdc';

/* Dark colors */
$grid_bg_color_dk     = isset( $attributes['gridBgColorDark'] ) ? $attributes['gridBgColorDark'] : '#4a6eb0';
$overlay_color_dk     = isset( $attributes['imageOverlayColorDark'] ) ? $attributes['imageOverlayColorDark'] : '#355fad';
$showmore_bg_color_dk = isset( $attributes['showmoreBgColorDark'] ) ? $attributes['showmoreBgColorDark'] : '#021327';
$button_bg_color_dk   = isset( $attributes['buttonBgColorDark'] ) ? $attributes['buttonBgColorDark'] : '#a8c930';
$btn_hover_color_dk   = isset( $attributes['buttonHoverColorDark'] ) ? $attributes['buttonHoverColorDark'] : '#7e9724';
$name_color_dk        = isset( $attributes['nameColorDark'] ) ? $attributes['nameColorDark'] : '#4eafdc';
$pos_color_dk         = isset( $attributes['positionColorDark'] ) ? $attributes['positionColorDark'] : '#4eafdc';

/* Typography (shared across themes) */
$name_font_size    = isset( $attributes['nameFontSize'] ) ? absint( $attributes['nameFontSize'] ) : 14;
$name_bold         = isset( $attributes['nameBold'] ) ? $attributes['nameBold'] : true;
$name_italic       = isset( $attributes['nameItalic'] ) ? $attributes['nameItalic'] : false;
$name_transform    = isset( $attributes['nameTransform'] ) ? $attributes['nameTransform'] : 'none';
$pos_font_size     = isset( $attributes['positionFontSize'] ) ? absint( $attributes['positionFontSize'] ) : 11;
$pos_bold          = isset( $attributes['positionBold'] ) ? $attributes['positionBold'] : false;
$pos_italic        = isset( $attributes['positionItalic'] ) ? $attributes['positionItalic'] : false;
$pos_transform     = isset( $attributes['positionTransform'] ) ? $attributes['positionTransform'] : 'none';
$btn_font_size     = isset( $attributes['buttonFontSize'] ) ? absint( $attributes['buttonFontSize'] ) : 15;
$btn_line_height   = isset( $attributes['buttonLineHeight'] ) ? floatval( $attributes['buttonLineHeight'] ) : 1.2;

assert( is_array( $members ), 'Members must be an array' );
assert( $visible_count >= 1, 'Visible count must be at least 1' );

if ( 0 === $total ) {
	return;
}

if ( $visible_count < 1 ) {
	$visible_count = 1;
}

$has_hidden   = $total > $visible_count;
$max_members  = 100;
$count        = 0;
$wrapper_class = 'team-members';

if ( $has_hidden ) {
	$wrapper_class .= ' team-members--collapsed';
}

/* ── Build CSS custom properties ── */
$custom_props = '';

/* Light theme colors */
$custom_props .= '--ct-grid-bg-light:' . esc_attr( $grid_bg_color ) . ';';
$custom_props .= '--ct-overlay-bg-light:' . esc_attr( $overlay_color ) . ';';
$custom_props .= '--ct-showmore-bg-light:' . esc_attr( ct_team_hex_to_rgba( $showmore_bg_color, $showmore_opacity ) ) . ';';
$custom_props .= '--ct-btn-bg-light:' . esc_attr( $button_bg_color ) . ';';
$custom_props .= '--ct-btn-hover-light:' . esc_attr( $btn_hover_color ) . ';';
$custom_props .= '--ct-name-color-light:' . esc_attr( $name_color ) . ';';
$custom_props .= '--ct-pos-color-light:' . esc_attr( $pos_color ) . ';';

/* Dark theme colors */
$custom_props .= '--ct-grid-bg-dark:' . esc_attr( $grid_bg_color_dk ) . ';';
$custom_props .= '--ct-overlay-bg-dark:' . esc_attr( $overlay_color_dk ) . ';';
$custom_props .= '--ct-showmore-bg-dark:' . esc_attr( ct_team_hex_to_rgba( $showmore_bg_color_dk, $showmore_opacity ) ) . ';';
$custom_props .= '--ct-btn-bg-dark:' . esc_attr( $button_bg_color_dk ) . ';';
$custom_props .= '--ct-btn-hover-dark:' . esc_attr( $btn_hover_color_dk ) . ';';
$custom_props .= '--ct-name-color-dark:' . esc_attr( $name_color_dk ) . ';';
$custom_props .= '--ct-pos-color-dark:' . esc_attr( $pos_color_dk ) . ';';

/* Typography (shared across themes) */
$custom_props .= '--ct-name-size:' . esc_attr( $name_font_size ) . 'px;';
$custom_props .= '--ct-name-weight:' . ( $name_bold ? '700' : '400' ) . ';';
$custom_props .= '--ct-name-style:' . ( $name_italic ? 'italic' : 'normal' ) . ';';
$custom_props .= '--ct-name-transform:' . esc_attr( $name_transform ) . ';';
$custom_props .= '--ct-pos-size:' . esc_attr( $pos_font_size ) . 'px;';
$custom_props .= '--ct-pos-weight:' . ( $pos_bold ? '700' : '400' ) . ';';
$custom_props .= '--ct-pos-style:' . ( $pos_italic ? 'italic' : 'normal' ) . ';';
$custom_props .= '--ct-pos-transform:' . esc_attr( $pos_transform ) . ';';
$custom_props .= '--ct-btn-size:' . esc_attr( $btn_font_size ) . 'px;';
$custom_props .= '--ct-btn-lh:' . esc_attr( $btn_line_height ) . ';';
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => $wrapper_class, 'style' => $custom_props ) ); ?>>
	<div class="team-members__grid">
		<?php foreach ( $members as $index => $member ) : ?>
			<?php
			if ( $count >= $max_members ) {
				break;
			}
			$count++;

			$image_id  = isset( $member['imageId'] ) ? absint( $member['imageId'] ) : 0;
			$first     = isset( $member['firstName'] ) ? $member['firstName'] : '';
			$last      = isset( $member['lastName'] ) ? $member['lastName'] : '';
			$position  = isset( $member['position'] ) ? $member['position'] : '';

			$image_url = '';
			$image_alt = '';

			if ( $image_id > 0 ) {
				$image_url = wp_get_attachment_image_url( $image_id, 'medium' );
				$image_alt = get_post_meta( $image_id, '_wp_attachment_image_alt', true );
			}

			if ( ! $image_alt ) {
				$image_alt = trim( $first . ' ' . $last );
			}

			$full_name  = trim( $first . ' ' . $last );
			$card_class = 'team-member';
			?>
			<div class="<?php echo esc_attr( $card_class ); ?>" data-index="<?php echo esc_attr( $index ); ?>">
				<?php if ( $image_url ) : ?>
					<img
						class="team-member__image"
						src="<?php echo esc_url( $image_url ); ?>"
						alt="<?php echo esc_attr( $image_alt ); ?>"
						loading="lazy"
					>
				<?php endif; ?>
				<div class="team-member__popup">
					<div class="team-member__popup-photo">
						<?php if ( $image_id > 0 ) : ?>
							<?php echo wp_get_attachment_image( $image_id, 'medium', false, array( 'loading' => 'lazy' ) ); ?>
						<?php endif; ?>
					</div>
					<div class="team-member__popup-info">
						<?php if ( $full_name ) : ?>
							<p class="team-member__name"><?php echo esc_html( $full_name ); ?></p>
						<?php endif; ?>
						<?php if ( $position ) : ?>
							<p class="team-member__position"><?php echo esc_html( TranslationService::resolve_raw( $position ) ); ?></p>
						<?php endif; ?>
					</div>
				</div>
			</div>
		<?php endforeach; ?>
	</div>

	<?php if ( $has_hidden ) : ?>
		<div class="team-members__showmore">
			<button
				type="button"
				class="team-members__showmore-btn"
				data-show-text="<?php echo esc_attr( $button_text ); ?>"
				data-hide-text="<?php echo esc_attr( $hide_text ); ?>"
			>
				<?php echo esc_html( $button_text ); ?>
			</button>
		</div>
	<?php endif; ?>
</div>
