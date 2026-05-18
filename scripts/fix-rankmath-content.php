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
 */

if ( ! defined( 'ABSPATH' ) ) {
	echo "Run via WP-CLI only.\n";
	exit( 1 );
}

global $args;

const GASCON_RM_PATCH_META = '_gascon_rm_patch_v2';
const GASCON_RM_KEYWORD    = 'plumbers bolton';

$prepend_html = '<p>Plumbers bolton — GASCON Ltd provides Gas Safe plumbing, boiler repairs and central heating across Bolton.</p>'
	. '<h2>Plumbers Bolton — plumbing and heating</h2>'
	. '<h3>Trusted plumbers bolton for emergencies and installations</h3>';

$image_alt = 'plumbers bolton — GASCON Ltd Gas Safe heating engineers';

$default_ids = array( 25420, 27246, 27069 );
$arg0        = isset( $args[0] ) ? (string) $args[0] : '';

if ( 'all' === $arg0 || '' === $arg0 ) {
	$post_ids = 'all' === $arg0 ? $default_ids : array( (int) ( getenv( 'PAGE_ID' ) ?: $default_ids[0] ) );
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
function gascon_rm_find_target_ids( array $elements, $skip_first_section = true ) {
	$text_id  = null;
	$image_id = null;
	$skipped  = false;

	$walk = function ( array $els, $inside_hero ) use ( &$walk, &$text_id, &$image_id, &$skipped, $skip_first_section ) {
		foreach ( $els as $el ) {
			if ( null !== $text_id && null !== $image_id ) {
				return;
			}

			$is_top = ( 'section' === ( $el['elType'] ?? '' ) && empty( $el['isInner'] ) );

			if ( $is_top && $skip_first_section && ! $skipped ) {
				$skipped = true;
				if ( ! empty( $el['elements'] ) ) {
					$walk( $el['elements'], true );
				}
				continue;
			}

			$hero = $inside_hero;
			if ( $is_top && $skipped ) {
				$hero = false;
			}

			$type = $el['widgetType'] ?? '';
			$id   = $el['id'] ?? '';

			if ( ! $hero && 'text-editor' === $type && null === $text_id && $id && ! empty( $el['settings']['editor'] ) ) {
				if ( false === stripos( (string) $el['settings']['editor'], GASCON_RM_KEYWORD ) ) {
					$text_id = $id;
				}
			}

			if ( ! $hero && 'image' === $type && null === $image_id && $id && ! gascon_rm_is_logo_image( $el ) ) {
				$alt    = trim( (string) ( $el['settings']['image_alt'] ?? '' ) );
				$nested = trim( (string) ( $el['settings']['image']['alt'] ?? '' ) );
				if ( '' === $alt && '' === $nested ) {
					$image_id = $id;
				}
			}

			if ( ! empty( $el['elements'] ) ) {
				$walk( $el['elements'], $hero );
			}
		}
	};

	$walk( $elements, false );

	return array(
		'text_id'  => $text_id,
		'image_id' => $image_id,
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
	if ( ! empty( $existing ) ) {
		echo "  SKIP: v2 patch already applied (run revert-rankmath-content.php first to re-apply)\n";
		continue;
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
			$widget['settings']['image_alt'] = $image_alt;
			if ( isset( $widget['settings']['image'] ) && is_array( $widget['settings']['image'] ) ) {
				$widget['settings']['image']['alt'] = $image_alt;
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
				update_post_meta( $aid, '_wp_attachment_image_alt', $image_alt );
			}
			echo "  OK: set image alt on widget {$wid}\n";
		}
	} else {
		echo "  WARN: no empty non-logo image widget found\n";
	}

	if ( empty( $patch['text'] ) && empty( $patch['image'] ) ) {
		echo "  Nothing to patch — aborting without saving meta.\n";
		continue;
	}

	update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
	update_post_meta( $post_id, GASCON_RM_PATCH_META, wp_slash( wp_json_encode( $patch ) ) );
	delete_post_meta( $post_id, '_elementor_css' );
	gascon_rm_elementor_clear_cache();
	echo "  Saved patch meta for revert.\n";
}

echo "\nTest the page in the browser. If layout breaks, run:\n";
echo "  wp eval-file revert-rankmath-content.php all --allow-root\n";
echo "Then Elementor → Update and clear cache.\n";
