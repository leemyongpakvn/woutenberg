<?php
/**
 * Load global styles assets in the front-end.
 *
 * @package gutenberg
 */

/**
 * This code lives in script-loader.php
 * where we just load styles using wp_get_global_stylesheet.
 */
function gutenberg_enqueue_global_styles_assets() {
	$separate_assets  = wp_should_load_separate_core_block_assets();
	$is_block_theme   = wp_is_block_theme();
	$is_classic_theme = ! $is_block_theme;

	/*
	 * Global styles should be printed in the head when loading all styles combined.
	 * The footer should only be used to print global styles for classic themes with separate core assets enabled.
	 *
	 * See https://core.trac.wordpress.org/ticket/53494.
	 */
	if (
		( $is_block_theme && doing_action( 'wp_footer' ) ) ||
		( $is_classic_theme && doing_action( 'wp_footer' ) && ! $separate_assets ) ||
		( $is_classic_theme && doing_action( 'wp_enqueue_scripts' ) && $separate_assets )
	) {
		return;
	}

	$stylesheet = gutenberg_get_global_stylesheet();

	if ( empty( $stylesheet ) ) {
		return;
	}

	if ( isset( wp_styles()->registered['global-styles'] ) ) {
		// There's a GS stylesheet (theme has theme.json), so we overwrite it.
		wp_styles()->registered['global-styles']->extra['after'][0] = $stylesheet;
	} else {
		// No GS stylesheet (theme has no theme.json), so we enqueue a new one.
		wp_register_style( 'global-styles', false, array(), true, true );
		wp_add_inline_style( 'global-styles', $stylesheet );
		wp_enqueue_style( 'global-styles' );
	}
}
add_action( 'wp_enqueue_scripts', 'gutenberg_enqueue_global_styles_assets' );
add_action( 'wp_footer', 'gutenberg_enqueue_global_styles_assets' );

/**
 * Load the user preset styles separately with lower priority to they will be more
 * likely to load after any theme css that they need to override.
 */
function gutenberg_enqueue_user_preset_styles() {
	$presets_stylesheet = gutenberg_get_global_stylesheet( array( 'presets' ) );

	wp_register_style( 'use-preset-styles', false, array(), true, true );
	wp_add_inline_style( 'use-preset-styles', '.has-background { background-color: var(--wp--user--preset--background-color);} .has-text-color {color: var(--wp--user--preset--color);}' . $presets_stylesheet );
	wp_enqueue_style( 'use-preset-styles' );
}
add_action( 'wp_enqueue_scripts', 'gutenberg_enqueue_user_preset_styles', 100 );

/**
 * This function takes care of adding inline styles
 * in the proper place, depending on the theme in use.
 *
 * For block themes, it's loaded in the head.
 * For classic ones, it's loaded in the body
 * because the wp_head action  happens before
 * the render_block.
 *
 * @link https://core.trac.wordpress.org/ticket/53494.
 *
 * @param string $style String containing the CSS styles to be added.
 */
function gutenberg_enqueue_block_support_styles( $style ) {
	$action_hook_name = 'wp_footer';
	if ( wp_is_block_theme() ) {
		$action_hook_name = 'wp_head';
	}
	add_action(
		$action_hook_name,
		static function () use ( $style ) {
			echo "<style>$style</style>\n";
		}
	);
}
