<?php
/**
 * Install GASCON Rank Math Elementor helper mu-plugin.
 *
 *   wp eval-file install-rankmath-helper.php --allow-root
 */

if ( ! defined( 'ABSPATH' ) ) {
	echo "Run via WP-CLI only.\n";
	exit( 1 );
}

$src  = dirname( __FILE__ ) . '/gascon-rankmath-elementor-helper.php';
$dest = WP_CONTENT_DIR . '/mu-plugins/gascon-rankmath-elementor-helper.php';

if ( ! file_exists( $src ) ) {
	// When only helper was copied to wwwroot, use same directory.
	$src = __DIR__ . '/gascon-rankmath-elementor-helper.php';
}

if ( ! file_exists( $src ) ) {
	echo "ERROR: gascon-rankmath-elementor-helper.php not found next to this script.\n";
	echo "Download both files to /home/site/wwwroot first.\n";
	exit( 1 );
}

if ( ! is_dir( WP_CONTENT_DIR . '/mu-plugins' ) ) {
	wp_mkdir_p( WP_CONTENT_DIR . '/mu-plugins' );
}

copy( $src, $dest );
echo "OK: installed {$dest}\n";
echo "Open the page in Elementor → SEO tab → click refresh (↻) on the score.\n";
echo "Uninstall: wp eval-file uninstall-rankmath-helper.php --allow-root\n";
