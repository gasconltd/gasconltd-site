<?php
/**
 * Revert Rank Math SEO changes (legacy v1 injection + v2 minimal patch).
 *
 * Run on Azure:
 *   wp eval-file revert-rankmath-content.php all --allow-root
 */

if ( ! defined( 'ABSPATH' ) ) {
	echo "Run via WP-CLI only.\n";
	exit( 1 );
}

global $args;

const GASCON_RM_PATCH_META = '_gascon_rm_patch_v2';

$marker_class   = 'gascon-rm-seo';
$legacy_alt     = 'plumbers bolton GASCON Ltd Gas Safe heating engineers';
$heading_prefix = 'Plumbers Bolton — ';

$legacy_seo_html = <<<HTML
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

function gascon_rm_revert_v2_patch( array &$elements, array $patch, &$stats ) {
	if ( ! empty( $patch['text']['id'] ) && ! empty( $patch['text']['prepended'] ) ) {
		$widget = null;
		if ( gascon_rm_find_by_id( $elements, $patch['text']['id'], $widget ) && $widget ) {
			$editor = (string) ( $widget['settings']['editor'] ?? '' );
			if ( 0 === strpos( $editor, $patch['text']['prepended'] ) ) {
				$widget['settings']['editor'] = substr( $editor, strlen( $patch['text']['prepended'] ) );
				++$stats['v2_text'];
			}
		}
	}

	if ( ! empty( $patch['image']['id'] ) ) {
		$widget = null;
		if ( gascon_rm_find_by_id( $elements, $patch['image']['id'], $widget ) && $widget ) {
			$widget['settings']['image_alt'] = $patch['image']['image_alt'] ?? '';
			if ( isset( $widget['settings']['image'] ) && is_array( $widget['settings']['image'] ) ) {
				$widget['settings']['image']['alt'] = $patch['image']['nested_alt'] ?? '';
			}
			++$stats['v2_image'];
		}
	}

	if ( ! empty( $patch['attachment']['id'] ) ) {
		$aid = (int) $patch['attachment']['id'];
		$prev = $patch['attachment']['alt'] ?? '';
		if ( '' === $prev ) {
			delete_post_meta( $aid, '_wp_attachment_image_alt' );
		} else {
			update_post_meta( $aid, '_wp_attachment_image_alt', $prev );
		}
		++$stats['v2_attachment'];
	}
}

function gascon_rm_find_by_id( array $elements, $widget_id, &$found = null ) {
	foreach ( $elements as &$element ) {
		if ( ( $element['id'] ?? '' ) === $widget_id ) {
			$found = &$element;
			return true;
		}
		if ( ! empty( $element['elements'] ) && gascon_rm_find_by_id( $element['elements'], $widget_id, $found ) ) {
			return true;
		}
	}
	return false;
}

function gascon_rm_revert_legacy_elementor( array &$elements, $heading_prefix, $logo_alt, &$stats ) {
	$filtered = array();
	foreach ( $elements as $element ) {
		if ( 'section' === ( $element['elType'] ?? '' ) && gascon_rm_section_has_marker( $element, 'gascon-rm-seo' ) ) {
			++$stats['legacy_sections'];
			continue;
		}

		if ( ! empty( $element['elements'] ) ) {
			$element['elements'] = gascon_rm_revert_legacy_elementor( $element['elements'], $heading_prefix, $logo_alt, $stats );
		}

		$type = $element['widgetType'] ?? '';
		if ( 'heading' === $type ) {
			$title = (string) ( $element['settings']['title'] ?? '' );
			if ( 0 === stripos( $title, $heading_prefix ) ) {
				$element['settings']['title'] = substr( $title, strlen( $heading_prefix ) );
				++$stats['legacy_headings'];
			}
		}

		if ( 'image' === $type ) {
			if ( ( $element['settings']['image_alt'] ?? '' ) === $logo_alt ) {
				$element['settings']['image_alt'] = '';
				++$stats['legacy_alts'];
			}
			if ( isset( $element['settings']['image']['alt'] ) && $element['settings']['image']['alt'] === $logo_alt ) {
				$element['settings']['image']['alt'] = '';
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

function gascon_rm_elementor_clear_cache() {
	if ( ! class_exists( '\Elementor\Plugin' ) ) {
		return;
	}
	$elementor = \Elementor\Plugin::$instance;
	if ( $elementor && isset( $elementor->files_manager ) && is_object( $elementor->files_manager ) ) {
		$elementor->files_manager->clear_cache();
	}
}

foreach ( $post_ids as $post_id ) {
	echo "=== Post $post_id ===\n";
	$stats = array(
		'v2_text'          => 0,
		'v2_image'         => 0,
		'v2_attachment'    => 0,
		'legacy_sections'  => 0,
		'legacy_headings'  => 0,
		'legacy_alts'      => 0,
	);

	$post = get_post( $post_id );
	if ( ! $post ) {
		echo "  SKIP: post not found\n";
		continue;
	}

	$new_content = gascon_rm_strip_post_content( (string) $post->post_content, $legacy_seo_html );
	if ( $new_content !== $post->post_content ) {
		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => $new_content,
			)
		);
		echo "  OK: removed legacy SEO block from post_content\n";
	}

	$raw = get_post_meta( $post_id, '_elementor_data', true );
	if ( empty( $raw ) ) {
		delete_post_meta( $post_id, GASCON_RM_PATCH_META );
		echo "  WARN: no Elementor data\n";
		continue;
	}

	$data = json_decode( $raw, true );
	if ( ! is_array( $data ) ) {
		echo "  ERROR: invalid Elementor JSON\n";
		continue;
	}

	$patch_json = get_post_meta( $post_id, GASCON_RM_PATCH_META, true );
	if ( ! empty( $patch_json ) ) {
		$patch = json_decode( $patch_json, true );
		if ( is_array( $patch ) && (int) ( $patch['version'] ?? 0 ) === 2 ) {
			gascon_rm_revert_v2_patch( $data, $patch, $stats );
			delete_post_meta( $post_id, GASCON_RM_PATCH_META );
			echo "  OK: reverted v2 patch from meta\n";
		}
	}

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
	$stats['legacy_sections'] += $before - count( $data );

	$data = gascon_rm_revert_legacy_elementor( $data, $heading_prefix, $legacy_alt, $stats );

	update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
	delete_post_meta( $post_id, '_elementor_css' );
	gascon_rm_elementor_clear_cache();

	echo "  v2 reverted: text={$stats['v2_text']} image={$stats['v2_image']}\n";
	echo "  legacy: sections={$stats['legacy_sections']} headings={$stats['legacy_headings']}\n";
	echo "  Done.\n";
}

echo "\nClear site cache → Elementor → Tools → Regenerate CSS & Data.\n";
