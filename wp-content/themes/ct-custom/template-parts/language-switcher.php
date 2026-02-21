<?php
/**
 * Language Switcher component.
 *
 * Expects $bs_switcher_data to be set before inclusion.
 *
 * @package BS_Custom
 */

if ( ! isset( $bs_switcher_data ) || empty( $bs_switcher_data['is_multilingual'] ) ) {
    return;
}

$bs_switcher_class = isset( $bs_switcher_class ) && is_string( $bs_switcher_class ) && '' !== trim( $bs_switcher_class )
    ? trim( $bs_switcher_class )
    : 'ct-lang-switcher';
$bs_switcher_class = trim( $bs_switcher_class . ' dib' );

$bs_sw_items   = $bs_switcher_data['items'];
$bs_sw_current = null;
$bs_sw_max     = 50;
$bs_sw_count   = 0;

foreach ( $bs_sw_items as $bs_sw_item ) {
    if ( $bs_sw_count >= $bs_sw_max ) {
        break;
    }
    $bs_sw_count++;

    if ( ! empty( $bs_sw_item['is_current'] ) ) {
        $bs_sw_current = $bs_sw_item;
        break;
    }
}

if ( null === $bs_sw_current ) {
    return;
}
?>

<div class="<?php echo esc_attr( $bs_switcher_class ); ?>" role="navigation" aria-label="<?php esc_attr_e( 'Language Switcher', 'ct-custom' ); ?>">
    <button type="button"
            class="ct-lang-switcher__toggle df aic cp"
            aria-expanded="false"
            aria-haspopup="listbox"
            aria-label="<?php echo esc_attr( sprintf( __( 'Current language: %s', 'ct-custom' ), $bs_sw_current['name'] ) ); ?>">
        <?php if ( ! empty( $bs_sw_current['flag_url'] ) ) : ?>
            <img src="<?php echo esc_url( $bs_sw_current['flag_url'] ); ?>"
                 alt=""
                 class="ct-lang-switcher__flag"
                 aria-hidden="true"
                 loading="lazy">
        <?php endif; ?>
        <span class="ct-lang-switcher__name"><?php echo esc_html( $bs_sw_current['name'] ); ?></span>
        <svg class="ct-lang-switcher__arrow" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"></polyline></svg>
    </button>

    <ul class="ct-lang-switcher__dropdown m0" role="listbox" aria-label="<?php esc_attr_e( 'Available languages', 'ct-custom' ); ?>">
        <?php
        $bs_sw_dd_count = 0;
        foreach ( $bs_sw_items as $bs_sw_item ) :
            if ( $bs_sw_dd_count >= $bs_sw_max ) { break; }
            $bs_sw_dd_count++;
        ?>
            <li role="option"
                class="ct-lang-switcher__item m0 p0<?php echo ! empty( $bs_sw_item['is_current'] ) ? ' ct-lang-switcher__item--current' : ''; ?>"
                aria-selected="<?php echo ! empty( $bs_sw_item['is_current'] ) ? 'true' : 'false'; ?>">
                <a href="<?php echo esc_url( $bs_sw_item['target_url'] ); ?>" class="ct-lang-switcher__link df aic">
                    <?php if ( ! empty( $bs_sw_item['flag_url'] ) ) : ?>
                        <img src="<?php echo esc_url( $bs_sw_item['flag_url'] ); ?>"
                             alt=""
                             class="ct-lang-switcher__flag"
                             aria-hidden="true"
                             loading="lazy">
                    <?php endif; ?>
                    <span><?php echo esc_html( $bs_sw_item['name'] ); ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
