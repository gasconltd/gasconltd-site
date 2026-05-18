<?php
/**
 * Revert changes made by fix-rankmath-content.php.
 *
 * Run on Azure:
 *   cd /home/site/wwwroot
 *   wp eval-file revert-rankmath-content.php all --allow-root
 */

if ( ! defined( 'ABSPATH' ) ) {
	echo "Run via WP-CLI only.\n";
	exit( 1 );
}

global $args;

$marker_class = 'gascon-rm-seo';
$logo_alt     = 'plumbers bolton GASCON Ltd Gas Safe heating engineers';
$heading_prefix = 'Plumbers Bolton — ';

$seo_html = <<<HTML
<p>Plumbers bolton — GASCON Ltd provides Gas Safe plumbing, boiler repairs and central heating across Bolton and Greater Manchester.</p>
<h2>Plumbers Bolton plumbing and heating services</h2>
<h3>Trusted plumbers bolton for emergencies and installations</h3>
<p>Our <strong>plumbers bolton</strong> team handles leaks, boiler breakdowns, radiators and new installations. Call <a href="tel:07828623767">07828 623 767</a> for a free quote.</p>
<p><img src="https://gasconltd.com/wp-content/uploads/2025/07/Gascon-Logo.png" alt="plumbers bolton GASCON Ltd Gas Safe heating engineers" width="400" height="106" loading="lazy" class="gascon-rm-seo-img" /></p>
HTML;

$default_ids = array( 25420, 27246, 27069 );
$arg0        = isset( $args[0] ) ? (string) $args[0] : '';

if ( 'all' === $arg0 || '' === $arg0 ) {
	$post_ids = 'all' === $arg0 ? $default_ids : array( (int) ( getenv( 'PAGE_ID' ) ?: $default_ids[0] ) );
} else {
	$post_ids = array( (int) $arg0 );
}

function gascon_rm_section_has_marker( array $section, $marker_class ) {
	$classes = $section['settings']['css_classes'] ?? '';
	return is_string( $classes ) && false !== strpos( $classes, $marker_class );
}

function gascon_rm_revert_elementor( array &$elements, $heading_prefix, $logo_alt, &$stats ) {
	$filtered = array();
	foreach ( $elements as $element ) {
		if ( 'section' === ( $element['elType'] ?? '' ) && gascon_rm_section_has_marker( $element, 'gascon-rm-seo' ) ) {
			++$stats['sections_removed'];
			continue;
		}

		if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
			$element['elements'] = gascon_rm_revert_elementor( $element['elements'], $heading_prefix, $logo_alt, $stats );
		}

		$type = $element['widgetType'] ?? '';
		if ( 'heading' === $type ) {
			$title = (string) ( $element['settings']['title'] ?? '' );
			if ( 0 === stripos( $title, $heading_prefix ) ) {
				$element['settings']['title'] = substr( $title, strlen( $heading_prefix ) );
				++$stats['headings_reverted'];
			}
		}

		if ( 'image' === $type ) {
			$alt = (string) ( $element['settings']['image_alt'] ?? '' );
			if ( $alt === $logo_alt ) {
				$element['settings']['image_alt'] = '';
				++$stats['image_alts_cleared'];
			}
			if ( isset( $element['settings']['image']['alt'] ) && $element['settings']['image']['alt'] === $logo_alt ) {
				$element['settings']['image']['alt'] = '';
			}
			if ( ! empty( $element['settings']['image']['id'] ) ) {
				$aid = (int) $element['settings']['image']['id'];
				if ( get_post_meta( $aid, '_wp_attachment_image_alt', true ) === $logo_alt ) {
					delete_post_meta( $aid, '_wp_attachment_image_alt' );
					++$stats['attachment_alts_cleared'];
				}
			}
		}

		if ( 'text-editor' === $type && isset( $element['settings']['editor'] ) ) {
			$editor = (string) $element['settings']['editor'];
			$trim   = ltrim( $editor );
			if ( 0 === stripos( $trim, '<p>Plumbers bolton' ) && false !== stripos( $editor, 'gascon-rm-seo-img' ) ) {
				// Entire widget was our SEO block — drop widget by skipping (parent must handle).
				++$stats['text_widgets_removed'];
				continue;
			}
		}

		$filtered[] = $element;
	}
	return $filtered;
}

function gascon_rm_strip_post_content( $content, $seo_html ) {
	$out = $content;
	while ( 0 === strpos( ltrim( $out ), ltrim( $seo_html ) ) ) {
		$out = ltrim( substr( ltrim( $out ), strlen( ltrim( $seo_html ) ) ) );
		$out = ltrim( $out, "\n\r" );
	}
	return $out;
}

foreach ( $post_ids as $post_id ) {
	echo "=== Post $post_id ===\n";
	$stats = array(
		'sections_removed'       => 0,
		'headings_reverted'      => 0,
		'image_alts_cleared'     => 0,
		'attachment_alts_cleared' => 0,
		'text_widgets_removed'   => 0,
	);

	$post = get_post( $post_id );
	if ( ! $post ) {
		echo "  SKIP: post not found\n";
		continue;
	}

	$new_content = gascon_rm_strip_post_content( (string) $post->post_content, $seo_html );
	if ( $new_content !== $post->post_content ) {
		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => $new_content,
			)
		);
		echo "  OK: removed SEO block from post_content\n";
	} else {
		echo "  post_content: no SEO block found\n";
	}

	$raw = get_post_meta( $post_id, '_elementor_data', true );
	if ( empty( $raw ) ) {
		echo "  WARN: no Elementor data\n";
		continue;
	}

	$data = json_decode( $raw, true );
	if ( ! is_array( $data ) ) {
		echo "  ERROR: invalid Elementor JSON\n";
		continue;
	}

	// Remove top-level injected sections.
	$before = count( $data );
	$data   = array_values(
		array_filter(
			$data,
			function ( $section ) use ( $marker_class ) {
				if ( ! is_array( $section ) || 'section' !== ( $section['elType'] ?? '' ) ) {
					return true;
				}
				return ! gascon_rm_section_has_marker( $section, $marker_class );
			}
		)
	);
	$stats['sections_removed'] += $before - count( $data );

	$data = gascon_rm_revert_elementor( $data, $heading_prefix, $logo_alt, $stats );

	update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
	delete_post_meta( $post_id, '_elementor_css' );

	if ( class_exists( '\Elementor\Plugin' ) ) {
		$elementor = \Elementor\Plugin::$instance;
		if ( $elementor && isset( $elementor->files_manager ) && is_object( $elementor->files_manager ) ) {
			$elementor->files_manager->clear_cache();
		}
	}

	echo "  Sections removed: {$stats['sections_removed']}\n";
	echo "  Headings reverted: {$stats['headings_reverted']}\n";
	echo "  Image alts cleared: {$stats['image_alts_cleared']}\n";
	echo "  Done.\n";
}

echo "\nClear site cache, then Elementor → Tools → Regenerate CSS & Data.\n";
