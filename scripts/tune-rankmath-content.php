<?php
/**
 * Lower keyword density and fix image-alt check for Rank Math.
 *
 *   wp eval-file tune-rankmath-content.php all --allow-root
 */

if ( ! defined( 'ABSPATH' ) ) {
	echo "Run via WP-CLI only.\n";
	exit( 1 );
}

global $args;

const GASCON_RM_PATCH_META = '_gascon_rm_patch_v2';
const GASCON_RM_KEYWORD    = 'plumbers bolton';
const GASCON_RM_IMAGE_ALT  = 'plumbers bolton';

/** Old prepends / alts we may need to strip or replace. */
const GASCON_RM_OLD_PREPENDS = array(
	'<p>Plumbers bolton — GASCON Ltd provides Gas Safe plumbing, boiler repairs and central heating across Bolton.</p><h2>Plumbers Bolton — plumbing and heating</h2><h3>Trusted plumbers bolton for emergencies and installations</h3>',
	'<p>Plumbers bolton — GASCON Ltd provides Gas Safe plumbing, boiler repairs and central heating across Bolton and Greater Manchester.</p><h2>Plumbers Bolton plumbing and heating services</h2><h3>Trusted plumbers bolton for emergencies and installations</h3><p>Our <strong>plumbers bolton</strong> team handles leaks, boiler breakdowns, radiators and new installations. Call <a href="tel:07828623767">07828 623 767</a> for a free quote.</p>',
);

const GASCON_RM_LIGHT_PREPEND = '<p>Plumbers bolton — GASCON Ltd provides Gas Safe plumbing and heating across Bolton.</p>';

const GASCON_RM_OLD_IMAGE_ALTS = array(
	'plumbers bolton — GASCON Ltd Gas Safe heating engineers',
	'plumbers bolton GASCON Ltd Gas Safe heating engineers Bolton',
	'plumbers bolton GASCON Ltd Gas Safe heating engineers',
);

$default_ids = array( 25420, 27246, 27069 );
$arg0        = isset( $args[0] ) ? (string) $args[0] : '';
$post_ids    = ( 'all' === $arg0 || '' === $arg0 ) ? $default_ids : array( (int) $arg0 );

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
	return is_string( $url ) && false !== stripos( $url, 'logo' );
}

function gascon_rm_first_image_needing_alt( array $elements ) {
	$found = null;
	$walk  = function ( array $els ) use ( &$walk, &$found ) {
		foreach ( $els as &$el ) {
			if ( null !== $found ) {
				return;
			}
			if ( 'image' === ( $el['widgetType'] ?? '' ) && ! gascon_rm_is_logo_image( $el ) ) {
				$alt    = (string) ( $el['settings']['image_alt'] ?? '' );
				$nested = (string) ( $el['settings']['image']['alt'] ?? '' );
				if ( false === stripos( $alt . ' ' . $nested, GASCON_RM_KEYWORD ) ) {
					$found = &$el;
					return;
				}
			}
			if ( ! empty( $el['elements'] ) ) {
				$walk( $el['elements'] );
			}
		}
	};
	$walk( $elements );
	return $found;
}

foreach ( $post_ids as $post_id ) {
	echo "=== Post $post_id ===\n";

	$patch_json = get_post_meta( $post_id, GASCON_RM_PATCH_META, true );
	$patch      = $patch_json ? json_decode( $patch_json, true ) : array();
	if ( ! is_array( $patch ) ) {
		$patch = array();
	}

	$raw = get_post_meta( $post_id, '_elementor_data', true );
	if ( empty( $raw ) ) {
		echo "  SKIP: no Elementor data\n";
		continue;
	}

	$data = json_decode( $raw, true );
	if ( ! is_array( $data ) ) {
		echo "  ERROR: invalid JSON\n";
		continue;
	}

	// 1) Shorten text-editor prepend (one keyword mention).
	if ( ! empty( $patch['text']['id'] ) ) {
		$widget = null;
		if ( gascon_rm_find_by_id( $data, $patch['text']['id'], $widget ) && $widget ) {
			$editor = (string) ( $widget['settings']['editor'] ?? '' );
			foreach ( GASCON_RM_OLD_PREPENDS as $old ) {
				if ( 0 === strpos( $editor, $old ) ) {
					$editor = substr( $editor, strlen( $old ) );
					break;
				}
			}
			if ( ! empty( $patch['text']['prepended'] ) && 0 === strpos( $editor, $patch['text']['prepended'] ) ) {
				$editor = substr( $editor, strlen( $patch['text']['prepended'] ) );
			}
			$widget['settings']['editor'] = GASCON_RM_LIGHT_PREPEND . ltrim( $editor );
			$patch['text']['prepended']   = GASCON_RM_LIGHT_PREPEND;
			echo "  OK: shortened text prepend (less keyword density)\n";
		}
	}

	// 2) Remove "Plumbers Bolton —" from heading widgets we prefixed.
	foreach ( array( 'heading_h2', 'heading_h3' ) as $hkey ) {
		if ( empty( $patch[ $hkey ]['id'] ) || ! isset( $patch[ $hkey ]['title'] ) ) {
			continue;
		}
		$widget = null;
		if ( gascon_rm_find_by_id( $data, $patch[ $hkey ]['id'], $widget ) && $widget ) {
			$widget['settings']['title'] = $patch[ $hkey ]['title'];
			echo "  OK: restored {$hkey} title (removed extra keyword)\n";
		}
	}

	// 3) Image alt — exact short focus keyword for Rank Math.
	$image_widget = null;
	if ( ! empty( $patch['image']['id'] ) ) {
		gascon_rm_find_by_id( $data, $patch['image']['id'], $image_widget );
	}
	if ( ! $image_widget ) {
		$image_widget = gascon_rm_first_image_needing_alt( $data );
	}

	if ( $image_widget ) {
		$wid = $image_widget['id'] ?? '';
		if ( empty( $patch['image']['id'] ) ) {
			$patch['image'] = array(
				'id'         => $wid,
				'image_alt'  => (string) ( $image_widget['settings']['image_alt'] ?? '' ),
				'nested_alt' => (string) ( $image_widget['settings']['image']['alt'] ?? '' ),
			);
		}
		$image_widget['settings']['image_alt'] = GASCON_RM_IMAGE_ALT;
		if ( isset( $image_widget['settings']['image'] ) && is_array( $image_widget['settings']['image'] ) ) {
			$image_widget['settings']['image']['alt'] = GASCON_RM_IMAGE_ALT;
			$aid = (int) ( $image_widget['settings']['image']['id'] ?? 0 );
			if ( $aid > 0 ) {
				if ( empty( $patch['attachment'] ) ) {
					$patch['attachment'] = array(
						'id'  => $aid,
						'alt' => (string) get_post_meta( $aid, '_wp_attachment_image_alt', true ),
					);
				}
				update_post_meta( $aid, '_wp_attachment_image_alt', GASCON_RM_IMAGE_ALT );
			}
		}
		echo "  OK: image alt set to \"plumbers bolton\" on widget {$wid}\n";
	} else {
		echo "  WARN: no suitable image widget found\n";
	}

	update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
	update_post_meta( $post_id, GASCON_RM_PATCH_META, wp_slash( wp_json_encode( $patch ) ) );
	delete_post_meta( $post_id, '_elementor_css' );

	if ( class_exists( '\Elementor\Plugin' ) ) {
		$el = \Elementor\Plugin::$instance;
		if ( $el && isset( $el->files_manager ) && is_object( $el->files_manager ) ) {
			$el->files_manager->clear_cache();
		}
	}
	echo "  Done.\n";
}

echo "\nRe-install analyzer helper (slimmer snippet + img alt), then refresh Rank Math in Elementor:\n";
echo "  wp eval-file install-rankmath-helper.php --allow-root\n";
