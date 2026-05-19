<?php
/**
 * Capture why CF7 / wp_mail fails (App Service Email + ACS).
 *
 *   wp eval-file debug-cf7-send.php --allow-root
 */

if ( ! defined( 'ABSPATH' ) ) {
	echo "Run via WP-CLI only.\n";
	exit( 1 );
}

$log = array();

add_action(
	'wp_mail_failed',
	function ( $error ) use ( &$log ) {
		$log[] = array(
			'action'  => 'wp_mail_failed',
			'message' => $error->get_error_message(),
			'data'    => $error->get_error_data(),
		);
	},
	10,
	1
);

echo "=== WP_EMAIL_CONNECTION_STRING ===\n";
$conn = getenv( 'WP_EMAIL_CONNECTION_STRING' );
if ( ! $conn ) {
	echo "(not set)\n";
} else {
	// Redact access key for display.
	echo preg_replace( '/accesskey=[^;]+/', 'accesskey=***REDACTED***', $conn ) . "\n";
	if ( false !== stripos( $conn, 'YOUR_KEY_HERE' ) ) {
		echo "ERROR: accesskey is still placeholder YOUR_KEY_HERE\n";
	}
}

echo "\n=== ACS MailFrom must match CF7 From and senderaddress ===\n";
if ( function_exists( 'wpcf7_contact_form' ) ) {
	$form = wpcf7_contact_form( 23863 ) ?: wpcf7_contact_form();
	if ( $form ) {
		$mail = $form->prop( 'mail' );
		echo 'CF7 sender: ' . ( $mail['sender'] ?? '?' ) . "\n";
		echo 'CF7 recipient: ' . ( $mail['recipient'] ?? '?' ) . "\n";
	}
}

echo "\n=== Test wp_mail (same headers CF7 uses) ===\n";

$to      = 'gasconltd@outlook.com';
$subject = 'CF7 debug test';
$body    = "Debug test from SSH\n";
$headers = array(
	'From: GASCON Ltd <donotreply@gasconltd.com>',
	'Reply-To: Test User <test@example.com>',
	'Content-Type: text/plain; charset=UTF-8',
);

$sent = wp_mail( $to, $subject, $body, $headers );
echo $sent ? "wp_mail: OK\n" : "wp_mail: FAILED\n";

if ( ! empty( $log ) ) {
	echo "\n=== wp_mail_failed details ===\n";
	print_r( $log );
} else {
	echo "\nNo wp_mail_failed hook fired (failure may be inside App Service Email plugin).\n";
}

echo "\n=== PHP error_log tail (if debug.log exists) ===\n";
$debug = WP_CONTENT_DIR . '/debug.log';
if ( is_readable( $debug ) ) {
	echo implode( '', array_slice( file( $debug ), -30 ) );
} else {
	echo "(no wp-content/debug.log — enable WP_DEBUG_LOG to capture plugin errors)\n";
}

echo "\n=== Tips ===\n";
echo "- senderaddress in env must EXACTLY match Azure MailFrom (check capitalisation).\n";
echo "- CF7 Mail From must use the same address.\n";
echo "- If wp_mail FAILED here, fix ACS/key/domain before testing the browser form.\n";
