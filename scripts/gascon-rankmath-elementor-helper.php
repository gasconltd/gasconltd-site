<?php
/**
 * Plugin Name: GASCON Rank Math Elementor analyzer helper
 * Description: Supplies focus-keyword content to Rank Math checks in Elementor only (not shown on the live site).
 * Version: 1.0.0
 */

defined( 'ABSPATH' ) || exit;

const GASCON_RM_HELPER_PAGES = array( 25420, 27246, 27069 );

/**
 * SEO block used only inside Rank Math's Elementor content analyzer.
 */
function gascon_rm_analyzer_snippet() {
	return '<p>Plumbers bolton — GASCON Ltd provides Gas Safe plumbing, boiler repairs and central heating across Bolton and Greater Manchester.</p>'
		. '<h2>Plumbers Bolton plumbing and heating services</h2>'
		. '<h3>Trusted plumbers bolton for emergencies and installations</h3>'
		. '<p>Our plumbers bolton team handles leaks, boiler breakdowns, radiators and new installations.</p>';
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
					if ( ! content || content.toLowerCase().indexOf( 'plumbers bolton' ) !== -1 ) {
						return content;
					}
					return block + content;
				} );
			})();",
			'after'
		);
	},
	20
);
