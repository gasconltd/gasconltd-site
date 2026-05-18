<?php
/**
 * Fix Rank Math content checks for Elementor pages (keyword, H2/H3, image alt).
 *
 * Rank Math analyzes post_content and early builder text — not Revolution Slider alone.
 * Run on Azure:
 *   cd /home/site/wwwroot
 *   wp eval-file fix-rankmath-content.php --allow-root
 *   wp eval-file fix-rankmath-content.php 27246 --allow-root
 *
 * All three service pages:
 *   wp eval-file fix-rankmath-content.php all --allow-root
 */

if ( ! defined( 'ABSPATH' ) ) {
	echo "Run via WP-CLI only.\n";
	exit( 1 );
}

global $args;

$keyword      = 'plumbers bolton';
$marker_class = 'gascon-rm-seo';
$logo_url     = 'https://gasconltd.com/wp-content/uploads/2025/07/Gascon-Logo.png';
$logo_id      = 0;

$logo_posts = get_posts(
	array(
		'post_type'      => 'attachment',
		'posts_per_page' => 1,
		'meta_query'     => array(
			array(
				'key'     => '_wp_attached_file',
				'value'   => 'Gascon-Logo.png',
				'compare' => 'LIKE',
			),
		),
		'fields'         => 'ids',
	)
);
if ( ! empty( $logo_posts[0] ) ) {
	$logo_id = (int) $logo_posts[0];
}

$seo_html = <<<HTML
<p>Plumbers bolton — GASCON Ltd provides Gas Safe plumbing, boiler repairs and central heating across Bolton and Greater Manchester.</p>
<h2>Plumbers Bolton plumbing and heating services</h2>
<h3>Trusted plumbers bolton for emergencies and installations</h3>
<p>Our <strong>plumbers bolton</strong> team handles leaks, boiler breakdowns, radiators and new installations. Call <a href="tel:07828623767">07828 623 767</a> for a free quote.</p>
<p><img src="{$logo_url}" alt="plumbers bolton GASCON Ltd Gas Safe heating engineers" width="400" height="106" loading="lazy" class="{$marker_class}-img" /></p>
HTML;

$default_ids = array( 25420, 27246, 27069 );
$arg0        = isset( $args[0] ) ? (string) $args[0] : '';

if ( 'all' === $arg0 || '' === $arg0 ) {
	$post_ids = 'all' === $arg0 ? $default_ids : array( (int) ( getenv( 'PAGE_ID' ) ?: $default_ids[0] ) );
} else {
	$post_ids = array( (int) $arg0 );
}

function gascon_rm_random_id() {
	return substr( bin2hex( random_bytes( 4 ) ), 0, 7 );
}

function gascon_rm_has_marker( array $elements, $marker_class ) {
	foreach ( $elements as $element ) {
		$classes = $element['settings']['css_classes'] ?? '';
		if ( is_string( $classes ) && false !== strpos( $classes, $marker_class ) ) {
			return true;
		}
		if ( ! empty( $element['elements'] ) && gascon_rm_has_marker( $element['elements'], $marker_class ) ) {
			return true;
		}
	}
	return false;
}

function gascon_rm_build_seo_section( $seo_html, $marker_class, $logo_url, $logo_id ) {
	$text_id     = gascon_rm_random_id();
	$heading_id  = gascon_rm_random_id();
	$image_id    = gascon_rm_random_id();
	$column_id   = gascon_rm_random_id();
	$section_id  = gascon_rm_random_id();

	$image_settings = array(
		'image'            => array(
			'url'    => $logo_url,
			'id'     => $logo_id,
			'alt'    => 'plumbers bolton GASCON Ltd Gas Safe heating engineers',
			'source' => $logo_id ? 'library' : 'url',
		),
		'image_size'       => 'medium',
		'align'            => 'left',
		'image_alt'        => 'plumbers bolton GASCON Ltd Gas Safe heating engineers',
		'css_classes'      => $marker_class . '-img',
	);

	return array(
		'id'       => $section_id,
		'elType'   => 'section',
		'isInner'  => false,
		'settings' => array(
			'css_classes' => $marker_class,
			'padding'     => array(
				'unit'       => 'px',
				'top'        => '20',
				'right'      => '0',
				'bottom'     => '20',
				'left'       => '0',
				'isLinked'   => false,
			),
		),
		'elements' => array(
			array(
				'id'       => $column_id,
				'elType'   => 'column',
				'settings' => array( '_column_size' => 100 ),
				'elements' => array(
					array(
						'id'         => $text_id,
						'elType'     => 'widget',
						'widgetType' => 'text-editor',
						'settings'   => array( 'editor' => $seo_html ),
						'elements'   => array(),
					),
					array(
						'id'         => $heading_id,
						'elType'     => 'widget',
						'widgetType' => 'heading',
						'settings'   => array(
							'title'       => 'Plumbers Bolton — Gas Safe plumbing & heating',
							'header_size' => 'h2',
						),
						'elements'   => array(),
					),
					array(
						'id'         => $image_id,
						'elType'     => 'widget',
						'widgetType' => 'image',
						'settings'   => $image_settings,
						'elements'   => array(),
					),
				),
			),
		),
	);
}

function gascon_rm_patch_elementor( array &$elements, $keyword, $logo_alt ) {
	$stats = array(
		'images'   => 0,
		'headings' => 0,
	);

	foreach ( $elements as &$element ) {
		if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
			$child = gascon_rm_patch_elementor( $element['elements'], $keyword, $logo_alt );
			$stats['images']   += $child['images'];
			$stats['headings'] += $child['headings'];
		}

		$type = $element['widgetType'] ?? '';
		if ( 'image' === $type ) {
			$alt = $element['settings']['image_alt'] ?? '';
			if ( '' === trim( (string) $alt ) ) {
				$element['settings']['image_alt'] = $logo_alt;
				++$stats['images'];
			}
			if ( isset( $element['settings']['image'] ) && is_array( $element['settings']['image'] ) ) {
				if ( empty( $element['settings']['image']['alt'] ) ) {
					$element['settings']['image']['alt'] = $logo_alt;
				}
				if ( ! empty( $element['settings']['image']['id'] ) ) {
					update_post_meta(
						(int) $element['settings']['image']['id'],
						'_wp_attachment_image_alt',
						$logo_alt
					);
				}
			}
		}

		if ( 'heading' === $type ) {
			$title = (string) ( $element['settings']['title'] ?? '' );
			if ( false === stripos( $title, $keyword ) ) {
				$size = $element['settings']['header_size'] ?? 'h2';
				if ( in_array( $size, array( 'h2', 'h3', 'h4' ), true ) ) {
					$element['settings']['title'] = 'Plumbers Bolton — ' . $title;
					++$stats['headings'];
				}
			}
		}
	}

	return $stats;
}

foreach ( $post_ids as $post_id ) {
	echo "=== Post $post_id ===\n";

	$post = get_post( $post_id );
	if ( ! $post ) {
		echo "  SKIP: post not found\n";
		continue;
	}

	// Rank Math reads post_content in the editor analysis.
	$current = (string) $post->post_content;
	if ( false === stripos( $current, $keyword ) ) {
		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => $seo_html . "\n" . $current,
			)
		);
		echo "  OK: prepended SEO block to post_content\n";
	} else {
		echo "  post_content already contains focus keyword\n";
	}

	$raw = get_post_meta( $post_id, '_elementor_data', true );
	if ( empty( $raw ) ) {
		echo "  WARN: no Elementor data — post_content update only\n";
		continue;
	}

	$data = json_decode( $raw, true );
	if ( ! is_array( $data ) ) {
		echo "  ERROR: invalid Elementor JSON\n";
		continue;
	}

	if ( ! gascon_rm_has_marker( $data, $marker_class ) ) {
		array_unshift( $data, gascon_rm_build_seo_section( $seo_html, $marker_class, $logo_url, $logo_id ) );
		echo "  OK: inserted Elementor SEO section at top of page\n";
	} else {
		echo "  Elementor SEO section already present\n";
	}

	$stats = gascon_rm_patch_elementor( $data, $keyword, 'plumbers bolton GASCON Ltd Gas Safe heating engineers' );
	echo "  Patched image alts: {$stats['images']}, headings: {$stats['headings']}\n";

	update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );

	if ( class_exists( '\Elementor\Plugin' ) ) {
		$elementor = \Elementor\Plugin::$instance;
		if ( $elementor && isset( $elementor->files_manager ) && is_object( $elementor->files_manager ) ) {
			$elementor->files_manager->clear_cache();
		}
	}

	delete_post_meta( $post_id, '_elementor_css' );
	echo "  Done.\n";
}

echo "\nNext: clear site cache → open page in Elementor → Update → click Rank Math refresh (↻) in SEO panel.\n";
