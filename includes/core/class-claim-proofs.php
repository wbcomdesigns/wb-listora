<?php
/**
 * Keeps claim proof documents out of the public web.
 *
 * A claim proof is whatever a member uploads to show they own a business: an ID
 * scan, a utility bill, a company letter. Those went into the ordinary uploads
 * tree, which is served directly by the web server to anyone who knows the
 * path. An earlier pass gave them a random 32-character basename, which makes
 * the path unguessable — and unguessable is not private. The URL is still a
 * live public file, and it was being handed out: the admin claims REST response
 * and the Claims screen both emitted `wp_get_attachment_url()`, so the URL
 * reached browser history, Referer headers, and anyone the page was shared
 * with. `post_status => private` on the attachment governs the POST, never the
 * file on disk.
 *
 * Two things close it, and both are needed:
 *
 * 1. Proofs are written to their own directory with server rules denying direct
 *    access, and an index file so a mis-configured server cannot list it.
 * 2. Nothing publishes the raw URL any more. The only route to a proof is
 *    {@see self::download()}, which checks the capability on every request.
 *
 * The directory rules cover Apache and LiteSpeed (`.htaccess`) and IIS
 * (`web.config`). **nginx ignores both** — see {@see self::nginx_rule()} for
 * the location block a site on nginx needs. Point 2 holds regardless of server,
 * which is why it is not optional: on nginx the guarded endpoint is the whole
 * protection until that block is added.
 *
 * @package WBListora\Core
 * @since 1.7.0
 */

namespace WBListora\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Private storage and capability-checked delivery for claim proofs.
 */
class Claim_Proofs {

	/**
	 * Directory name under uploads.
	 */
	const DIR = 'listora-claim-proofs';

	/**
	 * Marks an attachment as a claim proof.
	 */
	const META_IS_PROOF = '_listora_claim_proof';

	/**
	 * Register the download route.
	 */
	public static function init() {
		add_action( 'admin_post_wb_listora_claim_proof', array( __CLASS__, 'download' ) );
	}

	/*
	 * The Site Health check below is registered by {@see Site_Health}, which
	 * collects every Listora check in one list. The logic stays here with the
	 * feature it describes.
	 *
	 * It matters because the `.htaccess` this class writes does nothing on
	 * nginx, a large share of WordPress hosting. Left in a code comment, the
	 * one person who can fix that never learns it applies to them — so the
	 * check asks the server directly and reports what it finds.
	 */

	/**
	 * Fetch a canary from the proofs directory and see whether it is served.
	 *
	 * Tested, not assumed. Whether the directory rules apply depends on the web
	 * server, and the only reliable way to know is to ask it over HTTP the way
	 * a stranger would.
	 *
	 * @return array<string, mixed>
	 */
	public static function run_health_test() {
		$result = array(
			'label'       => __( 'Claim proof documents are private', 'wb-listora' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Security', 'wb-listora' ),
				'color' => 'blue',
			),
			'description' => '<p>' . esc_html__( 'The folder holding claim proof documents refuses direct requests, so an ID scan or utility bill can only be opened by someone with permission to review claims.', 'wb-listora' ) . '</p>',
			'actions'     => '',
			'test'        => 'wb_listora_claim_proofs',
		);

		$path = self::dir();

		if ( '' === $path ) {
			$result['status']      = 'recommended';
			$result['label']       = __( 'The claim proof folder could not be created', 'wb-listora' );
			$result['description'] = '<p>' . esc_html__( 'Listora could not create the folder it keeps claim proof documents in. Uploads may fail until the uploads directory is writable.', 'wb-listora' ) . '</p>';

			return $result;
		}

		$uploads = wp_upload_dir();
		$canary  = trailingslashit( $uploads['baseurl'] ) . self::DIR . '/index.html';

		$response = wp_remote_get(
			$canary,
			array(
				'timeout'   => 10,
				'sslverify' => false,
			)
		);

		if ( is_wp_error( $response ) ) {
			// Cannot reach itself over HTTP — say so rather than pass by default.
			$result['status']      = 'recommended';
			$result['label']       = __( 'Could not check whether claim proofs are private', 'wb-listora' );
			$result['description'] = '<p>' . esc_html__( 'Listora could not make a request to this site to check whether the claim proof folder is readable from outside. Check it by hand if this site stores proof documents.', 'wb-listora' ) . '</p>';

			return $result;
		}

		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return $result;
		}

		$result['status']      = 'critical';
		$result['badge']['color'] = 'red';
		$result['label']       = __( 'Claim proof documents can be downloaded by anyone with the link', 'wb-listora' );
		$result['description'] = '<p>' . esc_html__( 'Members upload ID scans, utility bills and company letters to prove they own a listing. This server is serving that folder directly, so anyone who learns or guesses a file address can download those documents without logging in.', 'wb-listora' ) . '</p>'
			. '<p>' . esc_html__( 'Listora writes rules that stop this on Apache and LiteSpeed. This server ignores them — nginx is the usual reason. Ask whoever manages the server to add this to the site configuration:', 'wb-listora' ) . '</p>'
			. '<p><code>' . esc_html( self::nginx_rule() ) . '</code></p>'
			. '<p>' . esc_html__( 'Until then, proof documents are only protected by having long random file names, and by Listora never publishing their addresses.', 'wb-listora' ) . '</p>';

		return $result;
	}

	/**
	 * Absolute path to the protected directory, creating it if needed.
	 *
	 * @return string Path, or '' when it could not be created.
	 */
	public static function dir(): string {
		$uploads = wp_upload_dir();

		if ( ! empty( $uploads['error'] ) ) {
			return '';
		}

		$path = trailingslashit( $uploads['basedir'] ) . self::DIR;

		if ( ! is_dir( $path ) && ! wp_mkdir_p( $path ) ) {
			return '';
		}

		self::protect( $path );

		return $path;
	}

	/**
	 * Write the server rules that deny direct access.
	 *
	 * @param string $path Directory to protect.
	 */
	private static function protect( string $path ) {
		$files = array(
			'.htaccess'  => "Require all denied\n<IfModule !mod_authz_core.c>\n\tOrder deny,allow\n\tDeny from all\n</IfModule>\n",
			'index.html' => '',
			'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration>\n\t<system.webServer>\n\t\t<authorization>\n\t\t\t<deny users=\"*\" />\n\t\t</authorization>\n\t</system.webServer>\n</configuration>\n",
		);

		foreach ( $files as $name => $contents ) {
			$file = trailingslashit( $path ) . $name;

			if ( file_exists( $file ) ) {
				continue;
			}

			// Direct write: WP_Filesystem would ask for credentials on some
			// hosts, and a proof upload is not a moment to prompt for FTP
			// details. Failure is not fatal — the capability-checked endpoint
			// is the protection that always applies.
			$handle = @fopen( $file, 'wb' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- see above.
			if ( $handle ) {
				fwrite( $handle, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- see above.
				fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- see above.
			}
		}
	}

	/**
	 * Send proof uploads to the protected directory.
	 *
	 * Filters `upload_dir` for the duration of one upload only — see
	 * {@see self::with_private_dir()}.
	 *
	 * @param array<string, mixed> $dirs Upload directory parts.
	 * @return array<string, mixed>
	 */
	public static function filter_upload_dir( $dirs ) {
		$uploads = wp_upload_dir( null, false );

		$dirs['subdir'] = '/' . self::DIR;
		$dirs['path']   = trailingslashit( $uploads['basedir'] ) . self::DIR;
		$dirs['url']    = trailingslashit( $uploads['baseurl'] ) . self::DIR;

		return $dirs;
	}

	/**
	 * Run an upload with proofs routed to the protected directory.
	 *
	 * Scoped with a try/finally so a failure part-way cannot leave every later
	 * upload on the site writing into the proofs directory.
	 *
	 * @param callable $callback Performs the upload.
	 * @return mixed Whatever the callback returns.
	 */
	public static function with_private_dir( callable $callback ) {
		self::dir();

		add_filter( 'upload_dir', array( __CLASS__, 'filter_upload_dir' ) );

		try {
			return $callback();
		} finally {
			remove_filter( 'upload_dir', array( __CLASS__, 'filter_upload_dir' ) );
		}
	}

	/**
	 * The only URL that should ever be published for a proof.
	 *
	 * @param int $attachment_id Proof attachment.
	 * @return string
	 */
	public static function url( int $attachment_id ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'wb_listora_claim_proof',
					'id'     => $attachment_id,
				),
				admin_url( 'admin-post.php' )
			),
			'wb_listora_claim_proof_' . $attachment_id
		);
	}

	/**
	 * Stream a proof to someone entitled to see it.
	 */
	public static function download() {
		$attachment_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

		if ( $attachment_id <= 0 ) {
			wp_die( esc_html__( 'No document specified.', 'wb-listora' ), '', array( 'response' => 400 ) );
		}

		check_admin_referer( 'wb_listora_claim_proof_' . $attachment_id );

		// The capability that governs claims, not a generic upload capability.
		if ( ! current_user_can( 'manage_listora_claims' ) && ! current_user_can( 'manage_listora_settings' ) ) {
			wp_die( esc_html__( 'You are not allowed to view this document.', 'wb-listora' ), '', array( 'response' => 403 ) );
		}

		// Only files this class stored. Without it, the endpoint would read any
		// attachment on the site for anyone holding the claims capability.
		if ( ! get_post_meta( $attachment_id, self::META_IS_PROOF, true ) ) {
			wp_die( esc_html__( 'That document is not a claim proof.', 'wb-listora' ), '', array( 'response' => 403 ) );
		}

		$path = get_attached_file( $attachment_id );

		if ( ! $path || ! file_exists( $path ) ) {
			wp_die( esc_html__( 'That document is no longer available.', 'wb-listora' ), '', array( 'response' => 404 ) );
		}

		$mime = get_post_mime_type( $attachment_id );

		nocache_headers();
		header( 'Content-Type: ' . ( $mime ? $mime : 'application/octet-stream' ) );
		header( 'Content-Length: ' . (string) filesize( $path ) );
		header( 'Content-Disposition: inline; filename="' . basename( $path ) . '"' );
		// A proof must never be framed or sniffed into something executable.
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Security-Policy: default-src \'none\'; img-src \'self\'; object-src \'none\'' );

		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- streaming a file is the entire purpose of this endpoint.
		exit;
	}

	/**
	 * The nginx rule a site needs, since nginx reads neither file written above.
	 *
	 * Surfaced in Site Health rather than left in a comment nobody opens.
	 *
	 * @return string
	 */
	public static function nginx_rule(): string {
		return 'location ~* /uploads/' . self::DIR . '/ { deny all; return 403; }';
	}
}
