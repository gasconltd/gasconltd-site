<?php
/**
 * Legacy: prepends to first text-editor only. Prefer fix-rankmath-content.php instead.
 * Run on Azure: cd /home/site/wwwroot && wp eval-file add-rankmath-body-content.php --allow-root
 *
 * Optional: PAGE_ID=27246 wp eval-file add-rankmath-body-content.php --allow-root
 */

if ( ! defined( 'ABSPATH' ) ) {
	echo "Run via WP-CLI only.\n";
	exit( 1 );
}

global $args;
$post_id = (int) ( getenv( 'PAGE_ID' ) ?: ( isset( $args[0] ) ? $args[0] : 27246 ) );
$keyword = 'plumbers bolton';

$snippet = <<<HTML
<p><strong>Plumbers Bolton</strong> customers choose GASCON Ltd for Gas Safe plumbing, boiler repairs and central heating across Bolton and Greater Manchester.</p>
<p>Our <strong>plumbers Bolton</strong> team handles emergency leaks, boiler breakdowns, radiator work and planned installations. Call <a href="tel:07828623767">07828 623 767</a> for a free quote.</p>
<h2>Plumbers Bolton — plumbing &amp; heating services</h2>

HTML;

$raw = get_post_meta( $post_id, '_elementor_data', true );
if ( empty( $raw ) ) {
	echo "No _elementor_data for post $post_id\n";
	exit( 1 );
}

$data = json_decode( $raw, true );
if ( ! is_array( $data ) ) {
	echo "Invalid Elementor JSON for post $post_id\n";
	exit( 1 );
}

$updated = false;

$walk = function ( array &$elements ) use ( &$walk, &$updated, $snippet, $keyword ) {
	foreach ( $elements as &$element ) {
		if ( $updated ) {
			return;
		}
		if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
			$walk( $element['elements'] );
		}
		if ( ! $updated
			&& isset( $element['widgetType'] )
			&& 'text-editor' === $element['widgetType']
			&& isset( $element['settings']['editor'] )
		) {
			$editor = $element['settings']['editor'];
			if ( false !== stripos( $editor, $keyword ) ) {
				echo "Post $post_id already contains focus keyword in a text widget — skipped.\n";
				$updated = true;
				return;
			}
			$element['settings']['editor'] = $snippet . $editor;
			$updated = true;
			return;
		}
	}
};

$walk( $data );

if ( ! $updated ) {
	echo "No text-editor widget found on post $post_id\n";
	exit( 1 );
}

$json = wp_slash( wp_json_encode( $data ) );
update_post_meta( $post_id, '_elementor_data', $json );

// Regenerate Elementor CSS/cache for this post.
if ( class_exists( '\Elementor\Plugin' ) ) {
	\Elementor\Plugin::$instance->files_manager->clear_cache();
}

echo "OK: Prepended focus-keyword content to post $post_id (first Text Editor widget).\n";
echo "Clear site cache, then re-check Rank Math in Elementor.\n";
