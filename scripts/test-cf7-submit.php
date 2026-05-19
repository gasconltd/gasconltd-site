<?php
/**
 * Submit CF7 form #23863 from CLI (same path as browser AJAX).
 *
 *   wp eval-file test-cf7-submit.php --allow-root
 */

if ( ! defined( 'ABSPATH' ) ) {
	echo "Run via WP-CLI only.\n";
	exit( 1 );
}

if ( ! function_exists( 'wpcf7_contact_form' ) ) {
	echo "CF7 not loaded.\n";
	exit( 1 );
}

$form_id = 23863;
$form    = wpcf7_contact_form( $form_id );
if ( ! $form ) {
	echo "Form $form_id not found.\n";
	exit( 1 );
}

$mail_failed_log = array();
add_action(
	'wp_mail_failed',
	function ( $e ) use ( &$mail_failed_log ) {
		$mail_failed_log[] = $e->get_error_message();
	}
);
add_action(
	'wpcf7_mail_failed',
	function ( $contact_form ) use ( &$mail_failed_log ) {
		$mail_failed_log[] = 'wpcf7_mail_failed fired';
	}
);

// Mimic browser POST (CF7 5.x).
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_ACCEPT']  = 'application/json';

$_POST = array(
	'_wpcf7'                  => (string) $form_id,
	'_wpcf7_version'          => defined( 'WPCF7_VERSION' ) ? WPCF7_VERSION : '6.1.5',
	'_wpcf7_locale'           => 'en_US',
	'_wpcf7_unit_tag'         => 'wpcf7-f' . $form_id . '-p25420-o1',
	'_wpcf7_container_post'   => '25420',
	'_wpcf7_posted_data_hash' => '',
	'your-name'               => 'SSH CF7 Test',
	'your-email'              => 'test@example.com',
	'phone'                   => '07000000000',
);

echo "=== Simple wp_mail first ===\n";
$simple = wp_mail( 'gasconltd@outlook.com', 'Simple test', 'Plain body.' );
echo $simple ? "simple wp_mail: OK\n" : "simple wp_mail: FAILED\n";
if ( $mail_failed_log ) {
	echo "  " . implode( ' | ', $mail_failed_log ) . "\n";
	$mail_failed_log = array();
}

echo "\n=== CF7 submit() form $form_id ===\n";
echo 'Mail From config: ' . $form->prop( 'mail' )['sender'] . "\n";

if ( ! method_exists( $form, 'submit' ) ) {
	echo "This CF7 version has no submit() — update Contact Form 7.\n";
	exit( 1 );
}

$result = $form->submit();
echo "Submit result:\n";
print_r( $result );

if ( ! empty( $mail_failed_log ) ) {
	echo "\nMail errors:\n";
	print_r( $mail_failed_log );
}

$submission = WPCF7_Submission::get_instance();
if ( $submission ) {
	echo "\nSubmission status: " . $submission->get_status() . "\n";
	$response = $submission->get_result();
	if ( is_array( $response ) ) {
		echo "Response message: " . ( $response['message'] ?? '' ) . "\n";
	}
}
