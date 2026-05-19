<?php
/**
 * Minimal CF7 mail config for App Service Email + ACS (plain text, simple headers).
 *
 *   wp eval-file fix-cf7-mail-minimal.php --allow-root
 */

if ( ! defined( 'ABSPATH' ) ) {
	echo "Run via WP-CLI only.\n";
	exit( 1 );
}

// Use exact MailFrom from Azure portal — adjust if portal shows DoNotReply@...
$from = 'donotreply@gasconltd.com';

$forms = get_posts(
	array(
		'post_type'      => 'wpcf7_contact_form',
		'posts_per_page' => -1,
	)
);

foreach ( $forms as $post ) {
	$form = wpcf7_contact_form( (int) $post->ID );
	if ( ! $form ) {
		continue;
	}
	$mail = $form->prop( 'mail' );
	$mail['active']           = true;
	$mail['recipient']        = 'gasconltd@outlook.com';
	$mail['sender']           = $from;
	$mail['subject']          = 'Enquiry from [your-name]';
	$mail['body']             = "Name: [your-name]\nEmail: [your-email]\nPhone: [phone]\n";
	$mail['additional_headers'] = "Reply-To: [your-email]\n";
	$mail['use_html']         = 0;
	$mail['attachments']      = '';
	$form->set_properties( array( 'mail' => $mail ) );
	$mail2 = $form->prop( 'mail_2' );
	if ( is_array( $mail2 ) ) {
		$mail2['active'] = false;
		$form->set_properties( array( 'mail_2' => $mail2 ) );
	}
	echo "Updated {$post->ID} {$post->post_title}\n";
}

echo "Done. From is bare email only; plain text; Reply-To visitor only.\n";
