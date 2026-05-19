<?php
/**
 * Set all CF7 forms Mail From to donotreply@gasconltd.com and sensible To.
 *
 *   wp eval-file fix-cf7-mail.php --allow-root
 */

if ( ! defined( 'ABSPATH' ) ) {
	echo "Run via WP-CLI only.\n";
	exit( 1 );
}

$from    = 'GASCON Ltd <donotreply@gasconltd.com>';
$to      = 'gasconltd@outlook.com';
$reply   = '[your-name] <[your-email]>';
$subject = '[your-name] — enquiry from gasconltd.com';

$forms = get_posts(
	array(
		'post_type'      => 'wpcf7_contact_form',
		'posts_per_page' => -1,
		'post_status'    => 'any',
	)
);

foreach ( $forms as $post ) {
	$id   = (int) $post->ID;
	$form = wpcf7_contact_form( $id );
	if ( ! $form ) {
		continue;
	}

	$mail = $form->prop( 'mail' );
	if ( ! is_array( $mail ) ) {
		$mail = array();
	}

	$mail['sender']    = $from;
	$mail['recipient'] = $to;
	$mail['subject']   = $subject;
	if ( empty( $mail['body'] ) ) {
		$mail['body'] = "Name: [your-name]\nEmail: [your-email]\nPhone: [phone]\n";
	}
	if ( empty( $mail['additional_headers'] ) || false === stripos( $mail['additional_headers'], 'Reply-To' ) ) {
		$mail['additional_headers'] = "Reply-To: $reply\n";
	} else {
		$mail['additional_headers'] = preg_replace(
			'/^Reply-To:.*$/mi',
			"Reply-To: $reply",
			$mail['additional_headers']
		);
	}

	if ( method_exists( $form, 'set_properties' ) ) {
		$form->set_properties( array( 'mail' => $mail ) );
	} else {
		update_post_meta( $id, '_mail', $mail );
	}
	echo "Updated form $id: {$post->post_title}\n";
	echo "  From: $from\n";
	echo "  To:   $to\n";
}

echo "\nDone. Clear cache and test a form in the browser.\n";
