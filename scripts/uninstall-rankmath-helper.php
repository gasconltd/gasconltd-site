<?php
/**
 * Remove GASCON Rank Math Elementor helper mu-plugin.
 *
 *   wp eval-file uninstall-rankmath-helper.php --allow-root
 */

if ( ! defined( 'ABSPATH' ) ) {
	echo "Run via WP-CLI only.\n";
	exit( 1 );
}

$dest = WP_CONTENT_DIR . '/mu-plugins/gascon-rankmath-elementor-helper.php';
if ( is_file( $dest ) ) {
	unlink( $dest );
	echo "OK: removed {$dest}\n";
} else {
	echo "Already removed.\n";
}
