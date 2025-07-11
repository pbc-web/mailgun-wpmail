<?php

/**
 * Plugin Name: MailGunWPMail
 * Plugin URI: http://poweredbycoffee.co.uk
 * Description: Enables the loading of plugins sitting in mu-plugins (as folders)
 * Version: 3.0
 * Author: poweredbycoffee, stewarty
 * Author URI: http://poweredbycoffee.co.uk
 *
*/

require 'cli.php';

class PBC_WP_Mail_MailGun {

	public $http;
	public $mg;
	private static $errors = array();

	private static $instance = null;

	public function __construct() {

		if ( self::$instance !== null ) {
			return self::$instance;
		}

		if ( ! defined( 'MAILGUN_API_BASE' ) ) {
			define( 'MAILGUN_API_BASE', 'https://api.mailgun.net' );
		}

		$this->mg = \Mailgun\Mailgun::create( MAILGUN_API_KEY, MAILGUN_API_BASE );

		add_action( 'wp_mail_failed', array( __CLASS__, 'capture_email_failure' ) );
	}

	// The object is created from within the class itself
	// only if the class has no instance.
	public static function instance() {

		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public static function capture_email_failure( $error ) {
		self::$errors[] = $error;
	}

	public static function get_errors() {
		return self::$errors;
	}

	public function send( $from, $to, $subject, $message, $headers, $attachments ): void {
		$builder = new \Mailgun\Message\MessageBuilder();

		if ( isset( $from['name'] ) ) {
			$name = array( 'first' => $from['name'] );
		} else {
			$name = array();
		}

		$builder->setFromAddress( $from['address'], $name );

		foreach ( $to as $email => $name ) {
			$builder->addToRecipient( $email, array( $name ) );
		}

		foreach ( $headers['cc'] as $email => $name ) {
			$builder->addCcRecipient( $email, array( $name ) );
		}

		foreach ( $headers['bcc'] as $email => $name ) {
			$builder->addBccRecipient( $email, array( $name ) );
		}

		if ( isset( $headers['reply-to'] ) ) {
			foreach ( $headers['reply-to'] as $email ) {
				$builder->setReplyToAddress( $email );
			}
		}

		if ( is_array( $attachments ) ) {
			foreach ( $attachments as $attachment ) {
				$builder->addAttachment( $attachment );
			}
		}

		$builder->setHtmlBody( $message );
		$builder->setTextBody( $message );
		$builder->setSubject( $subject );

		$this->mg->messages()->send( MAILGUN_DOMAIN, $builder->getMessage() );
	}
}

function log_email_generath_path() {
	$log_dir = wp_upload_dir();
	$log_dir = $log_dir['basedir'] . '/email-logs';
	return $log_dir;
}

function log_email_create_folder( $log_dir ) {

	if ( ! file_exists( $log_dir ) ) {
		wp_mkdir_p( $log_dir );
	}
}

function log_email_generate_filename( $log_dir ) {
	$date = gmdate( 'Y-m-d', time() );
	$salt = substr( wp_hash( $date, 'auth' ), 0, 10 );
	return sprintf( '%s/%s-email.%s.log', $log_dir, $date, $salt );
}

function log_email( $to, $subject, $message, $headers = '', $attachments = '', $disabled = false ) {

	if(!apply_filters("mailgun_email_logging_enabled", true)){
		return;
	}
	
	$to          = is_array( $to ) ? $to : array( $to );
	$headers     = is_array( $headers ) ? $headers : array( $headers );
	$attachments = is_array( $attachments ) ? $attachments : array( $attachments );

	$to          = count( $to ) ? print_r( $to, true ) : '';
	$headers     = count( $headers ) ? print_r( $headers, true ) : '';
	$attachments = count( $attachments ) ? print_r( $attachments, true ) : '';

	$data = sprintf(
		"Time: %s \r\n" .
		"To: %s \r\n" .
		"Subject: %s \r\n" .
		"Disabled: %s \r\n" .
		"Message: --- \r\n" .
		"%s \r\n" .
		"----------------  \r\n" .
		"Headers: --- \r\n" .
		"%s \r\n" .
		"----------------  \r\n" .
		"Attachments: --- \r\n" .
		"%s \r\n" .
		"----------------  \r\n" .
		"\r\n",
		gmdate( 'Y-M-d H:i:s', time() ),
		$to,
		$subject,
		$disabled,
		$message,
		$headers,
		$attachments
	);

	$log_dir = log_email_generath_path();
	log_email_create_folder( $log_dir );
	$filename = log_email_generate_filename( $log_dir );
	file_put_contents( $filename, $data, FILE_APPEND );
}

// Only let this get created once
if ( ! function_exists( 'wp_mail' ) ) {
	function wp_mail( $to, $subject, $message, $headers = '', $attachments = '' ) {

		// Headers
		$cc       = array();
		$bcc      = array();
		$reply_to = array();

		if ( defined( 'DISABLE_EMAIL' ) && ( DISABLE_EMAIL === true ) ) {
			log_email( $to, $subject, $message, $headers, $attachments, DISABLE_EMAIL );
			return true;
		}

		global $mg;

		// look for the MailGun wrapper, if it doesn't exist create it
		if ( ! isset( $mg ) ) {
			$mg = PBC_WP_Mail_MailGun::instance();
		}

		$atts = apply_filters( 'wp_mail', compact( 'to', 'subject', 'message', 'headers', 'attachments' ) );

		if ( isset( $atts['to'] ) ) {
			$to = $atts['to'];
		}

		if ( isset( $atts['subject'] ) ) {
			$subject = $atts['subject'];
		}

		if ( isset( $atts['message'] ) ) {
			$message = $atts['message'];
		}

		if ( isset( $atts['headers'] ) ) {
			$headers = $atts['headers'];
		}

		if ( isset( $atts['attachments'] ) ) {
			$attachments = $atts['attachments'];
		}

		if ( ! is_array( $attachments ) && ! empty( $attachments ) ) {
			$attachments = explode( "\n", str_replace( "\r\n", "\n", $attachments ) );
		}

		if ( empty( $headers ) ) {
			$headers = array();
		} else {
			if ( ! is_array( $headers ) ) {
				// Explode the headers out, so this function can take both
				// string headers and an array of headers.
				$tempheaders = explode( "\n", str_replace( "\r\n", "\n", $headers ) );
			} else {
				$tempheaders = $headers;
			}

			$headers = array();

			// If it's actually got contents
			if ( ! empty( $tempheaders ) ) {
				// Iterate through the raw headers
				foreach ( (array) $tempheaders as $header ) {

					if ( strpos( $header, ':' ) === false ) {
						if ( false !== stripos( $header, 'boundary=' ) ) {
							$parts    = preg_split( '/boundary=/i', trim( $header ) );
							$boundary = trim( str_replace( array( "'", '"' ), '', $parts[1] ) );
						}
						continue;
					}
					// Explode them out
					list( $name, $content ) = explode( ':', trim( $header ), 2 );

					// Cleanup crew
					$name    = trim( $name );
					$content = trim( $content );

					switch ( strtolower( $name ) ) {
						// Mainly for legacy -- process a From: header if it's there
						case 'from':
							$bracket_pos = strpos( $content, '<' );
							if ( $bracket_pos !== false ) {
								// Text before the bracketed email is the "From" name.
								if ( $bracket_pos > 0 ) {
									$from_name = substr( $content, 0, $bracket_pos - 1 );
									$from_name = str_replace( '"', '', $from_name );
									$from_name = trim( $from_name );
								}

								$from_email = substr( $content, $bracket_pos + 1 );
								$from_email = str_replace( '>', '', $from_email );
								$from_email = trim( $from_email );

								// Avoid setting an empty $from_email.
							} elseif ( '' !== trim( $content ) ) {
								$from_email = trim( $content );
							}
							break;
						case 'content-type':
							if ( strpos( $content, ';' ) !== false ) {
								list( $type, $charset_content ) = explode( ';', $content );
								$content_type                   = trim( $type );
								if ( false !== stripos( $charset_content, 'charset=' ) ) {
									$charset = trim( str_replace( array( 'charset=', '"' ), '', $charset_content ) );
								} elseif ( false !== stripos( $charset_content, 'boundary=' ) ) {
									$boundary = trim( str_replace( array( 'BOUNDARY=', 'boundary=', '"' ), '', $charset_content ) );
									$charset  = '';
								}

								// Avoid setting an empty $content_type.
							} elseif ( '' !== trim( $content ) ) {
								$content_type = trim( $content );
							}
							break;
						case 'cc':
							$cc = array_merge( (array) $cc, explode( ',', $content ) );
							break;
						case 'bcc':
							$bcc = array_merge( (array) $bcc, explode( ',', $content ) );
							break;
						case 'reply-to':
							$reply_to = array_merge( (array) $reply_to, explode( ',', $content ) );

							break;
						default:
							// Add it to our grand headers array
							$headers[ trim( $name ) ] = trim( $content );
							break;
					}
				}
			}
		}

		/* If we don't have an email from the input headers default to wordpress@$sitename
		 * Some hosts will block outgoing mail from this address if it doesn't exist but
		 * there's no easy alternative. Defaulting to admin_email might appear to be another
		 * option but some hosts may refuse to relay mail from an unknown domain. See
		 * https://core.trac.wordpress.org/ticket/5007.
		 */

		if ( ! isset( $from_email ) ) {
			// Get the site domain and get rid of www.
			$sitename = strtolower( $_SERVER['SERVER_NAME'] );
			if ( substr( $sitename, 0, 4 ) === 'www.' ) {
				$sitename = substr( $sitename, 4 );
			}
			$from_email = 'wordpress@' . $sitename;
		}

		// Can filter that
		$from_email = apply_filters( 'wp_mail_from', $from_email );

		// From email and name
		// If we don't have a name from the input headers
		if ( ! isset( $from_name ) ) {
			$from_name = 'WordPress';
		}
		$from_name = apply_filters( 'wp_mail_from_name', $from_name );

		// this will be passed into send
		$from = array(
			'address' => $from_email,
			'name'    => $from_name,
		);

		if ( ! is_array( $to ) ) {
			$to = explode( ',', $to );
		}

		$to_addresses = array();

		foreach ( (array) $to as $recipient ) {
			// Break $recipient into name and address parts if in the format "Foo <bar@baz.com>"
			$recipient_name = '';
			if ( preg_match( '/(.*)<(.+)>/', $recipient, $matches ) ) {
				if ( count( $matches ) === 3 ) {
					$recipient_name = $matches[1];
					$recipient      = $matches[2];
				}
			}

			$to_addresses[ $recipient ] = $recipient_name;
		}

		$cc_addresses = array();

		foreach ( (array) $cc as $recipient ) {
			// Break $recipient into name and address parts if in the format "Foo <bar@baz.com>"
			$recipient_name = '';
			if ( preg_match( '/(.*)<(.+)>/', $recipient, $matches ) ) {
				if ( count( $matches ) === 3 ) {
					$recipient_name = $matches[1];
					$recipient      = $matches[2];
				}
			}

			$cc_addresses[ $recipient ] = $recipient_name;
		}

		// Set up headers
		$headers['cc'] = $cc_addresses;

		$bcc_addresses = array();

		foreach ( (array) $bcc as $recipient ) {
			// Break $recipient into name and address parts if in the format "Foo <bar@baz.com>"
			$recipient_name = '';
			if ( preg_match( '/(.*)<(.+)>/', $recipient, $matches ) ) {
				if ( count( $matches ) === 3 ) {
					$recipient_name = $matches[1];
					$recipient      = $matches[2];
				}
			}

			$bcc_addresses[ $recipient ] = $recipient_name;
		}

		// Set up headers
		$headers['bcc'] = $bcc_addresses;

		//
		$headers['reply-to'] = $reply_to;

		try {
			$resp = $mg->send( $from, $to_addresses, $subject, $message, $headers, $attachments );
		} catch ( Exception $e ) {
			$mail_error_data = compact( 'to', 'subject', 'message', 'headers', 'attachments' );

			/**
			 * Fires after a phpmailerException is caught.
			 *
			 * @since 4.4.0
			 *
			 * @param WP_Error $error A WP_Error object with the phpmailerException code, message, and an array
			 *                        containing the mail recipient, subject, message, headers, and attachments.
			 */
			do_action( 'wp_mail_failed', new WP_Error( "wp_mail_failed", $e->getMessage(), $mail_error_data ) );

			return false;
		}

		log_email( $to, $subject, $message, $headers, $attachments );
		return true;
	}
}
