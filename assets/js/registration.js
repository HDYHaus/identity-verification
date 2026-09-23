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
			reference: reference
		} )
			.done( function ( response ) {
				const data = response && response.data ? response.data : {};

				$( '#trustgate_reference' ).val( data.reference || reference );
				$( '#trustgate_verified' ).val( '1' );
				setStatus( config.i18n.verified, 'verified' );
			} )
			.fail( function () {
				$( '#trustgate_reference' ).val( '' );
				$( '#trustgate_verified' ).val( '0' );
				setStatus( config.i18n.failed, 'failed' );
			} );
	}

	$( function () {
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
				merchant_key: provider.publicKey,
				first_name: firstName,
				last_name: lastName,
				email: email,
				user_ref: 'trustgate_' + Date.now(),
				is_test: !! provider.isTest,
				config_id: provider.configurationId,
				callback: function ( response ) {
					const reference = response && ( response.reference || response.user_ref || response.id );

					if ( reference ) {
						confirmReference( reference );
						return;
					}

					setStatus( config.i18n.failed, 'failed' );
				}
			} );
		} );
	} );
}( jQuery, window ) );
