/**
 * CAPTCHA fields for a Listora form's REST request.
 *
 * Captcha::render_widget() prints a hidden `listora_captcha_token` and
 * `listora_captcha_provider` into a form, and the endpoint verifies them. The
 * token logic is shared here rather than living in the submission wizard, or
 * the review forms would send no token and every review would be refused while
 * CAPTCHA is on. Every form that renders the widget asks here.
 *
 * reCAPTCHA v3 is executed at submit time; Cloudflare Turnstile and the reCAPTCHA v2
 * checkbox fill the token through their page-level callbacks, installed below.
 */

/**
 * Refresh the form's CAPTCHA token and return the fields to send.
 *
 * @param {HTMLElement} formEl Form (or the element holding the widget).
 * @param {string}      action reCAPTCHA v3 action name.
 * @return {Promise<Object>} `{ listora_captcha_token, listora_captcha_provider }`,
 *                           or `{}` when the form has no CAPTCHA widget.
 */
export async function captchaFields( formEl, action ) {
	const provider = formEl?.querySelector( '[name="listora_captcha_provider"]' )?.value || '';
	const tokenInput = formEl?.querySelector( '[name="listora_captcha_token"]' );
	if ( ! provider || ! tokenInput ) {
		return {};
	}

	if ( 'recaptcha_v3' === provider && window.grecaptcha ) {
		// Captcha::enqueue_scripts() loads api.js?render=SITE_KEY; the key is
		// read back from that tag rather than printed a second time.
		const script = document.querySelector( 'script[src*="recaptcha/api.js?render="]' );
		const siteKey = script?.src.match( /render=([^&]+)/ )?.[ 1 ];
		if ( siteKey ) {
			try {
				await new Promise( ( resolve ) => window.grecaptcha.ready( resolve ) );
				tokenInput.value = await window.grecaptcha.execute( siteKey, { action } );
			} catch {
				// No token: the endpoint answers with its own "verification
				// required" message, which the form shows.
			}
		}
	}

	return {
		listora_captcha_token: tokenInput.value,
		listora_captcha_provider: provider,
	};
}

/**
 * Store a solved widget's token on the forms that use that provider.
 *
 * @param {string} provider Provider value of the widget ('cloudflare_turnstile', 'recaptcha_v2').
 * @param {string} token    Response token, '' when the challenge expired.
 */
function setCaptchaToken( provider, token ) {
	document.querySelectorAll( '[name="listora_captcha_token"]' ).forEach( ( input ) => {
		const field = input.parentElement?.querySelector( '[name="listora_captcha_provider"]' );
		if ( field && provider === field.value ) {
			input.value = token;
		}
	} );
}

if ( typeof window.listoraOnRecaptchaV2Success === 'undefined' ) {
	window.listoraOnRecaptchaV2Success = ( token ) => setCaptchaToken( 'recaptcha_v2', token );
	// An expired checkbox must not submit its stale token.
	window.listoraOnRecaptchaV2Expired = () => setCaptchaToken( 'recaptcha_v2', '' );
}

if ( typeof window.listoraOnTurnstileSuccess === 'undefined' ) {
	/**
	 * Cloudflare Turnstile success callback (data-callback on the widget).
	 *
	 * @param {string} token Turnstile response token.
	 */
	window.listoraOnTurnstileSuccess = ( token ) => setCaptchaToken( 'cloudflare_turnstile', token );
}
