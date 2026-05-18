<?php
/**
 * Rank Math / Elementor — minimal in-place SEO (v2).
 *
 * - Does NOT add new sections or change post_content (avoids duplicate layout).
 * - Prepends a short block to the first main text widget (skips hero section).
 * - Sets alt on one existing content image (not the logo).
 * - Saves _gascon_rm_patch_v2 for a clean revert.
 *
 * Run on Azure:
 *   cd /home/site/wwwroot
 *   curl -fsSL -o fix-rankmath-content.php "https://raw.githubusercontent.com/gasconltd/gasconltd-site/main/scripts/fix-rankmath-content.php"
 *   wp eval-file fix-rankmath-content.php all --allow-root
 *
 * Revert:
 *   wp eval-file revert-rankmath-content.php all --allow-root
 *
 * Re-apply after a previous run:
 *   wp eval-file fix-rankmath-content.php all force --allow-root
 *
 * Rank Math score in Elementor (no layout change): install-rankmath-helper.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	echo "Run via WP-CLI only.\n";
	exit( 1 );
}

global $args;

const GASCON_RM_PATCH_META = '_gascon_rm_patch_v2';
const GASCON_RM_KEYWORD    = 'plumbers bolton';
const GASCON_RM_IMAGE_ALT  = 'plumbers bolton GASCON Ltd Gas Safe heating engineers Bolton';

$prepend_html = '<p>Plumbers bolton — GASCON Ltd provides Gas Safe plumbing, boiler repairs and central heating across Bolton.</p>'
	. '<h2>Plumbers Bolton — plumbing and heating</h2>'
	. '<h3>Trusted plumbers bolton for emergencies and installations</h3>';

$default_ids = array( 25420, 27246, 27069 );
$arg0        = isset( $args[0] ) ? (string) $args[0] : '';
$force       = false;

if ( 'force' === $arg0 || ( isset( $args[1] ) && 'force' === (string) $args[1] ) ) {
	$force = true;
}

if ( 'all' === $arg0 || '' === $arg0 || 'force' === $arg0 ) {
	$post_ids = ( 'all' === $arg0 || 'force' === $arg0 || '' === $arg0 ) ? $default_ids : array( (int) ( getenv( 'PAGE_ID' ) ?: $default_ids[0] ) );
} else {
	$post_ids = array( (int) $arg0 );
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

function gascon_rm_is_logo_image( array $widget ) {
	$url = $widget['settings']['image']['url'] ?? '';
	if ( is_string( $url ) && false !== stripos( $url, 'logo' ) ) {
		return true;
	}
	$alt = $widget['settings']['image_alt'] ?? '';
	return is_string( $alt ) && false !== stripos( $alt, 'logo' );
}

/**
 * Walk page Elementor tree; skip first top-level section (hero/slider).
 *
 * @return array{text: ?array, image: ?array}
 */
function gascon_rm_find_target_ids( array $elements ) {
	$text_id    = null;
	$image_id   = null;
	$heading_h2 = null;
	$heading_h3 = null;

	$walk = function ( array $els ) use ( &$walk, &$text_id, &$image_id, &$heading_h2, &$heading_h3 ) {
		foreach ( $els as $el ) {
			$type = $el['widgetType'] ?? '';
			$id   = $el['id'] ?? '';

			if ( 'text-editor' === $type && null === $text_id && $id && ! empty( $el['settings']['editor'] ) ) {
				if ( false === stripos( (string) $el['settings']['editor'], GASCON_RM_KEYWORD ) ) {
					$text_id = $id;
				}
			}

			if ( 'heading' === $type && $id ) {
				$size  = $el['settings']['header_size'] ?? 'h2';
				$title = (string) ( $el['settings']['title'] ?? '' );
				if ( 'h2' === $size && null === $heading_h2 && false === stripos( $title, GASCON_RM_KEYWORD ) ) {
					$heading_h2 = $id;
				}
				if ( 'h3' === $size && null === $heading_h3 && false === stripos( $title, GASCON_RM_KEYWORD ) ) {
					$heading_h3 = $id;
				}
			}

			if ( 'image' === $type && null === $image_id && $id && ! gascon_rm_is_logo_image( $el ) ) {
				$alt    = trim( (string) ( $el['settings']['image_alt'] ?? '' ) );
				$nested = trim( (string) ( $el['settings']['image']['alt'] ?? '' ) );
				$combined = $alt . ' ' . $nested;
				if ( false === stripos( $combined, GASCON_RM_KEYWORD ) ) {
					$image_id = $id;
				}
			}

			if ( ! empty( $el['elements'] ) ) {
				$walk( $el['elements'] );
			}
		}
	};

	$walk( $elements );

	return array(
		'text_id'    => $text_id,
		'image_id'   => $image_id,
		'heading_h2' => $heading_h2,
		'heading_h3' => $heading_h3,
	);
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
	echo "=== Post $post_id (v2 minimal) ===\n";

	$existing = get_post_meta( $post_id, GASCON_RM_PATCH_META, true );
	if ( ! empty( $existing ) && ! $force ) {
		echo "  SKIP: patch already applied (use: wp eval-file fix-rankmath-content.php {$post_id} force --allow-root)\n";
		continue;
	}
	if ( ! empty( $existing ) && $force ) {
		echo "  Re-applying (force) — merge with existing patch meta\n";
	}

	$raw = get_post_meta( $post_id, '_elementor_data', true );
	if ( empty( $raw ) ) {
		echo "  ERROR: no Elementor data\n";
		continue;
	}

	$data = json_decode( $raw, true );
	if ( ! is_array( $data ) ) {
		echo "  ERROR: invalid Elementor JSON\n";
		continue;
	}

	$targets = gascon_rm_find_target_ids( $data );
	$patch   = array( 'version' => 2 );

	if ( ! empty( $targets['text_id'] ) ) {
		$widget = null;
		if ( gascon_rm_find_by_id( $data, $targets['text_id'], $widget ) && $widget ) {
			$wid    = $targets['text_id'];
			$before = (string) ( $widget['settings']['editor'] ?? '' );
			$widget['settings']['editor'] = $prepend_html . $before;
			$patch['text'] = array(
				'id'        => $wid,
				'prepended' => $prepend_html,
			);
			echo "  OK: prepended SEO copy to text-editor widget {$wid}\n";
		}
	} else {
		echo "  WARN: no suitable text-editor found (keyword may already be present)\n";
	}

	if ( ! empty( $targets['image_id'] ) ) {
		$widget = null;
		if ( gascon_rm_find_by_id( $data, $targets['image_id'], $widget ) && $widget ) {
			$wid           = $targets['image_id'];
			$before        = (string) ( $widget['settings']['image_alt'] ?? '' );
			$nested_before = (string) ( $widget['settings']['image']['alt'] ?? '' );
			$widget['settings']['image_alt'] = GASCON_RM_IMAGE_ALT;
			if ( isset( $widget['settings']['image'] ) && is_array( $widget['settings']['image'] ) ) {
				$widget['settings']['image']['alt'] = GASCON_RM_IMAGE_ALT;
			}
			$patch['image'] = array(
				'id'         => $wid,
				'image_alt'  => $before,
				'nested_alt' => $nested_before,
			);
			$aid = (int) ( $widget['settings']['image']['id'] ?? 0 );
			if ( $aid > 0 ) {
				$patch['attachment'] = array(
					'id'  => $aid,
					'alt' => (string) get_post_meta( $aid, '_wp_attachment_image_alt', true ),
				);
				update_post_meta( $aid, '_wp_attachment_image_alt', GASCON_RM_IMAGE_ALT );
			}
			echo "  OK: set image alt on widget {$wid}\n";
		}
	} else {
		echo "  WARN: no image widget without focus keyword in alt\n";
	}

	foreach ( array( 'heading_h2' => 'h2', 'heading_h3' => 'h3' ) as $key => $label ) {
		if ( empty( $targets[ $key ] ) ) {
			continue;
		}
		$widget = null;
		if ( gascon_rm_find_by_id( $data, $targets[ $key ], $widget ) && $widget ) {
			$wid    = $targets[ $key ];
			$before = (string) ( $widget['settings']['title'] ?? '' );
			$widget['settings']['title'] = 'Plumbers Bolton — ' . $before;
			$patch[ $key ] = array(
				'id'    => $wid,
				'title' => $before,
			);
			echo "  OK: updated {$label} heading widget {$wid}\n";
		}
	}

	if ( empty( $patch['text'] ) && empty( $patch['image'] ) && empty( $patch['heading_h2'] ) && empty( $patch['heading_h3'] ) ) {
		echo "  Nothing to patch — aborting without saving meta.\n";
		continue;
	}

	update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
	update_post_meta( $post_id, GASCON_RM_PATCH_META, wp_slash( wp_json_encode( $patch ) ) );
	delete_post_meta( $post_id, '_elementor_css' );
	gascon_rm_elementor_clear_cache();
	echo "  Saved patch meta for revert.\n";
}

echo "\nFor Rank Math checks in Elementor (recommended), also run:\n";
echo "  wp eval-file install-rankmath-helper.php --allow-root\n";
echo "Then open Elementor → SEO tab → refresh (↻) the score.\n";
echo "\nIf layout breaks: wp eval-file revert-rankmath-content.php all --allow-root\n";
