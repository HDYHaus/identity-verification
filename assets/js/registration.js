( function ( $, window ) {
	'use strict';

	const config = window.trustgateRegistration || {};

	function setStatus( message, state ) {
		$( '#trustgate_verification_status' )
			.text( message )
			.attr( 'data-state', state || '' );
	}

	function getFieldValue( selector ) {
		return String( $( selector ).val() || '' ).trim();
	}

	function confirmReference( reference ) {
		setStatus( config.i18n.working, 'working' );

		$.post( config.ajaxUrl, {
			action: 'trustgate_confirm_verification',
			nonce: config.nonce,
			reference: reference,
			token: getFieldValue( '#trustgate_attempt_token' ),
			email: getFieldValue( '#user_email' )
		} )
			.done( function ( response ) {
				const data = response && response.data ? response.data : {};

				$( '#trustgate_reference' ).val( data.reference || reference );
				setStatus( config.i18n.verified, 'verified' );
			} )
			.fail( function ( xhr ) {
				const data = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : {};
				const detail = config.provider && config.provider.isTest && data.status ? ' (' + data.status + ')' : '';

				$( '#trustgate_reference' ).val( '' );
				setStatus( config.i18n.failed + detail, 'failed' );
			} );
	}

	$( function () {
		$( '#user_email, #trustgate_first_name, #trustgate_last_name' ).on( 'change', function () {
			$( '#trustgate_reference' ).val( '' );
			setStatus( '', '' );
		} );

		$( '#trustgate_verify_button' ).on( 'click', function () {
			const email = getFieldValue( '#user_email' );
			const firstName = getFieldValue( '#trustgate_first_name' );
			const lastName = getFieldValue( '#trustgate_last_name' );
			const provider = config.provider || {};

			if ( ! email || ! firstName || ! lastName ) {
				setStatus( config.i18n.invalid, 'failed' );
				return;
			}

			if ( ! window.IdentityKYC || ! provider.publicKey || ! provider.configurationId ) {
				setStatus( config.i18n.unready, 'failed' );
				return;
			}

			window.IdentityKYC.verify( {
				widget_key: provider.publicKey,
				widget_id: provider.configurationId,
				first_name: firstName,
				last_name: lastName,
				email: email,
				user_ref: 'trustgate_' + Date.now(),
				is_test: !! provider.isTest,
				callback: function ( response, rawData ) {
					const verification = response && response.verification ? response.verification : {};
					const data = response && response.data ? response.data : {};
					const callbackData = rawData || {};
					const reference = callbackData.widgetId || callbackData.session_id || data.widgetId || data.session_id || verification.reference || data.reference || response.reference || response.user_ref || response.id;

					if ( response && 'success' === response.status && '00' === response.code && reference ) {
						confirmReference( reference );
						return;
					}

					const detail = provider.isTest && response && response.message ? ' (' + response.message + ')' : '';

					setStatus( config.i18n.failed + detail, 'failed' );
				}
			} );
		} );
	} );
}( jQuery, window ) );
