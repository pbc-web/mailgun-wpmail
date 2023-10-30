<?php
/*
Silly Functiion that tests if wp_mail sents an email
*/


if ( ! defined( 'WP_CLI' ) || ( ! WP_CLI ) ) {
	return;
}

/*
	Todo Remove, Global Variables are bad
*/
$mail_error = null;

// show wp_mail() errors
add_action( 'wp_mail_failed', 'on_mail_error', 10, 1 );
function on_mail_error( $wp_error ) {
	global $mail_error;
	$mail_error = $wp_error;
}


WP_CLI::add_command(
	'mail-test send',
	function ( $args = array(), $assoc_args = array() ) {

		global $mail_error;

		$recipient = $args[0] ?? get_option( 'admin_email' );
		$subject   = $args[0] ?? 'Test Email Sending';
		$content   = $args[0] ?? 'This was a test email';

		WP_CLI::line( 'Sending Test Email' );
		WP_CLI::line( sprintf( 'Recipient : %s', $recipient ) );
		WP_CLI::line( sprintf( 'Subject : %s', $subject ) );
		WP_CLI::line( sprintf( 'Content : %s', $content ) );

		$send_result = wp_mail(
			$recipient,
			'Test Email Sending',
			'This was a test email'
		);

		if ( defined( 'DISABLE_EMAIL' ) && ( DISABLE_EMAIL === true ) ) {
			WP_CLI::warning( 'Sending email is currently disabled as the constant `DISABLE_EMAIL` is true. To keep WordPress working as expected, wp_mail returns true even though the email is not sent. This can lead to false reporting on tests.' );
		}

		if ( $send_result === true ) {
			WP_CLI::success( 'wp_mail() reports that the email was sent correctly' );
		} else {
			WP_CLI::error( 'wp_mail() reports that the email failed to send', false );
			WP_CLI::error_multi_line( print_r( $mail_error, true ) );
		}
	}
);
