<?php
/**
 * Diagnose Azure Communication Services email (App Service Email plugin).
 *
 *   wp eval-file acs-email-diagnose.php --allow-root
 */

if ( ! defined( 'ABSPATH' ) ) {
	echo "Run via WP-CLI only.\n";
	exit( 1 );
}

$raw = getenv( 'WP_EMAIL_CONNECTION_STRING' );
if ( ! $raw ) {
	echo "ERROR: WP_EMAIL_CONNECTION_STRING is not set.\n";
	exit( 1 );
}

$parts = array();
foreach ( explode( ';', $raw ) as $pair ) {
	if ( false === strpos( $pair, '=' ) ) {
		continue;
	}
	list( $k, $v ) = explode( '=', $pair, 2 );
	$parts[ trim( $k ) ] = trim( $v );
}

echo "=== Parsed connection string ===\n";
echo 'endpoint:      ' . ( $parts['endpoint'] ?? '(missing)' ) . "\n";
echo 'senderaddress: ' . ( $parts['senderaddress'] ?? '(missing)' ) . "\n";
echo 'accesskey:     ' . ( empty( $parts['accesskey'] ) ? '(missing)' : strlen( $parts['accesskey'] ) . ' chars' ) . "\n";

$missing = array_diff( array( 'endpoint', 'senderaddress', 'accesskey' ), array_keys( $parts ) );
if ( $missing ) {
	echo "ERROR: missing keys: " . implode( ', ', $missing ) . "\n";
	echo "Value must be: endpoint=...;senderaddress=...;accesskey=...\n";
	exit( 1 );
}

if ( '/' === substr( $parts['endpoint'], -1 ) ) {
	echo "WARN: endpoint has trailing slash — remove it.\n";
}

$errors = array();
add_action(
	'wp_mail_failed',
	function ( $e ) use ( &$errors ) {
		$errors[] = $e->get_error_message();
	}
);

echo "\n=== Test A: current senderaddress ===\n";
$ok_a = wp_mail( 'gasconltd@outlook.com', 'ACS test A', 'Current sender test.' );
echo $ok_a ? "RESULT: OK\n" : "RESULT: FAIL\n";
if ( $errors ) {
	echo 'Error: ' . $errors[0] . "\n";
	$errors = array();
}

// Old Azure-managed sender (replace GUID if yours differs).
$legacy_sender = 'DoNotReply@9b6001e6-e6fb-45cc-af4e-00c18355c617.azurecomm.net';

echo "\n=== Test B: legacy Azure-managed sender (isolates custom domain) ===\n";
echo "Temporarily filtering wp_mail_from to: $legacy_sender\n";

add_filter(
	'wp_mail_from',
	function () use ( $legacy_sender ) {
		return $legacy_sender;
	},
	999
);

// Plugin uses connection string senderaddress, not wp_mail_from — patch env for this request only.
$backup = $raw;
putenv( 'WP_EMAIL_CONNECTION_STRING=' . str_replace(
	'senderaddress=' . $parts['senderaddress'],
	'senderaddress=' . $legacy_sender,
	$backup
) );

$ok_b = wp_mail( 'gasconltd@outlook.com', 'ACS test B', 'Legacy managed-domain sender test.' );
putenv( 'WP_EMAIL_CONNECTION_STRING=' . $backup );

echo $ok_b ? "RESULT: OK — custom domain / MailFrom is the problem\n" : "RESULT: FAIL — key, endpoint, or plugin issue\n";
if ( $errors ) {
	echo 'Error: ' . $errors[0] . "\n";
}

echo "\n=== Checklist (Azure Portal) ===\n";
echo "1. Email Communication Services → Provision domains → gasconltd.com\n";
echo "   Status must be Verified (ownership + SPF + DKIM).\n";
echo "2. gasconltd.com → MailFrom addresses → add donotreply (or DoNotReply).\n";
echo "   senderaddress must match EXACTLY what is listed there.\n";
echo "3. Communication Services (gascon-comm-gasconltd) → Email → Domains\n";
echo "   gasconltd.com must show Connected.\n";
echo "4. If Test B OK: fix MailFrom on custom domain, then set senderaddress to that address.\n";
echo "5. If both FAIL: regenerate Keys → update accesskey in connection string → Restart app.\n";
echo "6. Update plugin: App Service Email 1.2.1 from GitHub wordpress-linux-appservice.\n";

echo "\n=== Plugin error hint (line 280) ===\n";
$plugin_file = WP_CONTENT_DIR . '/plugins/app_service_email/admin/mailer/class-azure_app_service_email-controller.php';
if ( is_readable( $plugin_file ) ) {
	$lines = file( $plugin_file );
	$start = max( 0, 268 );
	$end   = min( count( $lines ), 295 );
	for ( $i = $start; $i < $end; $i++ ) {
		echo ( $i + 1 ) . '|' . $lines[ $i ];
	}
} else {
	echo "(plugin file not found at expected path)\n";
}
