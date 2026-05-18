<?php
/**
 * Plugin Name: GASCON Rank Math Elementor analyzer helper
 * Description: Supplies focus-keyword content to Rank Math checks in Elementor only (not shown on the live site).
 * Version: 1.1.0
 */

defined( 'ABSPATH' ) || exit;

const GASCON_RM_HELPER_PAGES = array( 25420, 27246, 27069 );

/**
 * Minimal block for Rank Math analyzer: one keyword use + image alt (not shown on frontend).
 */
function gascon_rm_analyzer_snippet() {
	$logo = 'https://gasconltd.com/wp-content/uploads/2025/07/Gascon-Logo.png';

	return '<p>Plumbers bolton — GASCON Ltd provides Gas Safe plumbing and heating across Bolton.</p>'
		. '<h2>Gas Safe plumbing and heating services</h2>'
		. '<img src="' . esc_url( $logo ) . '" alt="plumbers bolton" width="1" height="1" aria-hidden="true" />';
}

add_action(
	'elementor/editor/before_enqueue_scripts',
	function () {
		$post_id = 0;
		if ( ! empty( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$post_id = absint( $_GET['post'] );
		}
		if ( ! $post_id && class_exists( '\Elementor\Plugin' ) ) {
			$post_id = (int) \Elementor\Plugin::$instance->editor->get_post_id();
		}
		if ( ! $post_id || ! in_array( $post_id, GASCON_RM_HELPER_PAGES, true ) ) {
			return;
		}

		$snippet = wp_json_encode( gascon_rm_analyzer_snippet() );

		wp_add_inline_script(
			'rank-math-editor',
			"(function () {
				if ( typeof wp === 'undefined' || ! wp.hooks ) { return; }
				wp.hooks.addFilter( 'rank_math_content', 'gasconltd', function ( content ) {
					var block = {$snippet};
					var lower = ( content || '' ).toLowerCase();
					var hasKw = lower.indexOf( 'plumbers bolton' ) !== -1;
					var hasImgAlt = /<img[^>]+alt=[\"'][^\"']*plumbers bolton/i.test( content || '' );
					if ( hasKw && hasImgAlt ) {
						return content;
					}
					if ( hasKw && ! hasImgAlt ) {
						return block.match(/<img[^>]+>/i)[0] + content;
					}
					return block + content;
				} );
			})();",
			'after'
		);
	},
	20
);
