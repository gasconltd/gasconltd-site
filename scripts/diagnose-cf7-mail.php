<?php
/**
 * Diagnose Contact Form 7 mail setup vs wp_mail.
 *
 *   wp eval-file diagnose-cf7-mail.php --allow-root
 *   wp eval-file diagnose-cf7-mail.php 23863 --allow-root
 */

if ( ! defined( 'ABSPATH' ) ) {
	echo "Run via WP-CLI only.\n";
	exit( 1 );
}

global $args;

$form_id = isset( $args[0] ) ? (int) $args[0] : 23863;

echo "=== Environment ===\n";
$conn = getenv( 'WP_EMAIL_CONNECTION_STRING' );
echo 'WP_EMAIL_CONNECTION_STRING: ' . ( $conn ? $conn : '(not set)' ) . "\n";
echo 'ENABLE_EMAIL_MANAGED_IDENTITY: ' . ( getenv( 'ENABLE_EMAIL_MANAGED_IDENTITY' ) ?: '(not set)' ) . "\n";
echo 'admin_email: ' . get_option( 'admin_email' ) . "\n";
echo 'siteurl: ' . get_option( 'siteurl' ) . "\n";

echo "\n=== Plugins ===\n";
$active = (array) get_option( 'active_plugins', array() );
foreach ( $active as $p ) {
	if ( false !== stripos( $p, 'mail' ) || false !== stripos( $p, 'cf7' ) || false !== stripos( $p, 'contact' ) || false !== stripos( $p, 'app_service' ) ) {
		echo "  $p\n";
	}
}

if ( ! function_exists( 'wpcf7_contact_form' ) ) {
	echo "\nERROR: Contact Form 7 is not active.\n";
	exit( 1 );
}

$forms = get_posts(
	array(
		'post_type'      => 'wpcf7_contact_form',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
	)
);

echo "\n=== CF7 forms (" . count( $forms ) . ") ===\n";

foreach ( $forms as $post ) {
	$id    = (int) $post->ID;
	$form  = wpcf7_contact_form( $id );
	$mail  = $form ? $form->prop( 'mail' ) : array();
	$mail2 = $form ? $form->prop( 'mail_2' ) : array();

	echo "\n--- Form $id: {$post->post_title} ---\n";
	if ( $mail ) {
		echo "  Mail To:       " . ( $mail['recipient'] ?? '' ) . "\n";
		echo "  Mail From:     " . ( $mail['sender'] ?? '' ) . "\n";
		echo "  Mail subject:  " . ( $mail['subject'] ?? '' ) . "\n";
		echo "  Use HTML:      " . ( $mail['use_html'] ?? '' ) . "\n";
		echo "  Attachments:   " . ( $mail['attachments'] ?? '' ) . "\n";
	}
	if ( ! empty( $mail2['active'] ) ) {
		echo "  Mail 2 active: yes\n";
	}

	$issues = array();
	$sender = (string) ( $mail['sender'] ?? '' );
	if ( '' === trim( $sender ) ) {
		$issues[] = 'Empty Mail From (sender)';
	}
	if ( false !== stripos( $sender, 'Air Supply' ) ) {
		$issues[] = 'From still uses theme name Air Supply';
	}
	if ( false !== stripos( $sender, 'wordpress@' ) && false === stripos( $sender, 'donotreply@gasconltd.com' ) ) {
		$issues[] = 'From uses wordpress@ instead of donotreply@gasconltd.com';
	}
	if ( false !== stripos( $sender, 'enquiries@' ) && false === stripos( $sender, 'donotreply@' ) ) {
		$issues[] = 'From uses enquiries@ but you chose donotreply@';
	}
	if ( preg_match( '/<>\s*$/', $sender ) || '<>' === trim( $sender ) ) {
		$issues[] = 'Invalid empty angle brackets in From';
	}
	if ( ! empty( $issues ) ) {
		echo "  ISSUES:\n";
		foreach ( $issues as $i ) {
			echo "    - $i\n";
		}
	} else {
		echo "  From line looks OK for donotreply@gasconltd.com\n";
	}
}

if ( ! wpcf7_contact_form( $form_id ) ) {
	echo "\nForm ID $form_id not found.\n";
	exit( 1 );
}

echo "\n=== Simulated CF7 mail for form $form_id ===\n";

$form = wpcf7_contact_form( $form_id );
$mail = $form->prop( 'mail' );

$submission_data = array(
	'your-name'  => 'SSH Test',
	'your-email' => 'test@example.com',
	'phone'      => '07000000000',
);

$components = $form->scan_form_tags();
foreach ( $components as $tag ) {
	if ( ! empty( $tag['name'] ) && ! isset( $submission_data[ $tag['name'] ] ) ) {
		if ( 'acceptance' === $tag['type'] ) {
			$submission_data[ $tag['name'] ] = '1';
		} elseif ( in_array( $tag['type'], array( 'email', 'text', 'tel', 'textarea' ), true ) ) {
			$submission_data[ $tag['name'] ] = 'test';
		}
	}
}

// Build WPCF7_Submission-like context via mail tag replacement.
$args_mail = array(
	'contact_form' => $form,
	'status'       => 'mail_sent',
);

add_filter(
	'wp_mail_from',
	function () {
		return 'donotreply@gasconltd.com';
	},
	999
);
add_filter(
	'wp_mail_from_name',
	function () {
		return 'GASCON Ltd';
	},
	999
);

$recipient = $mail['recipient'];
$subject   = $mail['subject'];
$body      = $mail['body'];
$headers   = array();

if ( ! empty( $mail['additional_headers'] ) ) {
	$headers = explode( "\n", $mail['additional_headers'] );
}

// Minimal tag replace for common tags.
$replace = function ( $text ) use ( $submission_data ) {
	foreach ( $submission_data as $k => $v ) {
		$text = str_replace( '[' . $k . ']', $v, $text );
	}
	return $text;
};

$recipient = $replace( $recipient );
$subject   = $replace( $subject );
$body      = $replace( $body );

$sender_line = $replace( $mail['sender'] );
if ( preg_match( '/^(.*?)<([^>]+)>$/', trim( $sender_line ), $m ) ) {
	$from_name  = trim( $m[1] );
	$from_email = trim( $m[2] );
} else {
	$from_email = trim( $sender_line );
	$from_name  = 'GASCON Ltd';
}

$headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
if ( ! empty( $submission_data['your-email'] ) ) {
	$headers[] = 'Reply-To: ' . $submission_data['your-name'] . ' <' . $submission_data['your-email'] . '>';
}

echo "Resolved To: $recipient\n";
echo "Resolved From header: " . end( $headers ) . "\n";
echo "Subject: $subject\n";

$sent = wp_mail( $recipient, $subject, $body, $headers );
echo $sent ? "Simulated CF7 wp_mail: SUCCESS\n" : "Simulated CF7 wp_mail: FAILED\n";

echo "\nIf SUCCESS here but browser form fails → JS, cache, reCAPTCHA, or AJAX.\n";
echo "If FAILED → fix CF7 Mail tab From/To (run fix-cf7-mail.php).\n";
