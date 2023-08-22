<?php
/*
Silly Functiion that tests if wp_mail sents an email
*/


if( !defined('WP_CLI') || (!WP_CLI) ){
	return;
}

/*
	Todo Remove, Global Variables are bad
*/
$mail_error = null;

// show wp_mail() errors
add_action( 'wp_mail_failed', 'onMailError', 10, 1 );
function onMailError( $wp_error ) {
	global $mail_error;
	$mail_error = $wp_error;
}  


WP_CLI::add_command("mail-test send", function($args = [], $assoc_args = []){
		
		global $mail_error;

		$recipient = $args[0] ?? get_option("admin_email");
		$subject = $args[0] ?? "Test Email Sending";
		$content = $args[0] ?? "This was a test email";

		WP_CLI::line("Sending Test Email");
		WP_CLI::line(sprintf("Recipient : %s", $recipient));
		WP_CLI::line(sprintf("Subject : %s", $subject));
		WP_CLI::line(sprintf("Content : %s", $content));

		$send_result = wp_mail(
			$recipient,
			"Test Email Sending",
			"This was a test email"
		);

		if($send_result === true){
			WP_CLI::success("wp_mail() reports that the email was sent correctly");
		} else {
			WP_CLI::warning("wp_mail() reports that the email failed to send");
			WP_CLI::error_multi_line(print_r($mail_error, true));
		}
} );
