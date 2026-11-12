( function ( $, window ) {
	'use strict';

	const config = window.trustgateRegistration || {};
	let sumsubSdk;
	let sumsubApplicant = '';
	let formVersion = 0;
	let pollTimer;
	let pollCount = 0;
	let confirming = false;

	function attemptData( action ) {
		return {
			action: action,
			nonce: config.nonce,
			token: getFieldValue( '#trustgate_attempt_token' ),
			email: getFieldValue( '#user_email' ),
			firstName: getFieldValue( '#trustgate_first_name' ),
			lastName: getFieldValue( '#trustgate_last_name' ),
			consent: $( '#trustgate_consent' ).is( ':checked' ) ? '1' : '0'
		};
	}

	function startSumsub() {
		if ( ! window.snsWebSdk ) {
			setStatus( config.i18n.unready, 'failed' );
			return;
		}
		if ( sumsubApplicant ) {
			pollCount = 0;
			confirmReference( sumsubApplicant );
			return;
		}
		if ( sumsubSdk ) return;
		const version = formVersion;
		$( '#trustgate_verify_button' ).prop( 'disabled', true );
		setStatus( config.i18n.working, 'working' );
		const accessToken = function () {
			return $.post( config.ajaxUrl, attemptData( 'trustgate_start_verification' ) ).then( function ( response ) {
				if ( version !== formVersion || ! response.success || ! response.data.token ) throw new Error( 'Token unavailable' );
				return response.data.token;
			} );
		};
		accessToken().then( function ( token ) {
			if ( version !== formVersion ) return;
			sumsubSdk = window.snsWebSdk.init( token, accessToken )
				.withConf( { lang: 'en' } )
				.withOptions( { adaptIframeHeight: true, addViewportTag: false } )
				.onMessage( function ( type, payload ) {
					if ( version !== formVersion ) return;
					if ( payload && payload.applicantId ) sumsubApplicant = payload.applicantId;
					if ( sumsubApplicant && ( type === 'idCheck.onApplicantSubmitted' || type === 'idCheck.onApplicantStatusChanged' || type === 'idCheck.applicantStatus' || type === 'idCheck.onApplicantResubmitted' ) ) {
						confirmReference( sumsubApplicant );
					}
				} )
				.on( 'idCheck.onError', function () { if ( version === formVersion ) setStatus( config.i18n.failed, 'failed' ); } )
				.build();
			sumsubSdk.launch( '#trustgate-sumsub-container' );
		}, function () {
			if ( version === formVersion ) setStatus( config.i18n.failed, 'failed' );
		} ).always( function () { $( '#trustgate_verify_button' ).prop( 'disabled', false ); } );
	}


	function setStatus( message, state ) {
		if ( config.provider && config.provider.slug === 'sumsub' ) {
			$( '#wp-submit' ).prop( 'disabled', state !== 'verified' );
		}
		$( '#trustgate_verification_status' )
			.text( message )
			.attr( 'data-state', state || '' );
	}

	function getFieldValue( selector ) {
		return String( $( selector ).val() || '' ).trim();
	}

	function confirmReference( reference ) {
		if ( getFieldValue( '#trustgate_reference' ) === reference ) return;
		if ( confirming ) return;
		setStatus( config.i18n.working, 'working' );
		confirming = true;
		window.clearTimeout( pollTimer );
		const version = formVersion;
		const request = attemptData( 'trustgate_confirm_verification' );
		request.reference = reference;
		$.post( config.ajaxUrl, request )
			.done( function ( response ) {
				if ( version !== formVersion ) return;
				if ( ! response || ! response.success ) { setStatus( config.i18n.failed, 'failed' ); return; }
				const data = response && response.data ? response.data : {};

				$( '#trustgate_reference' ).val( data.reference || reference );
				setStatus( config.i18n.verified, 'verified' );
			} )
			.fail( function ( xhr ) {
				if ( version !== formVersion ) return;
				const data = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : {};
				const detail = config.provider && config.provider.isTest && data.status ? ' (' + data.status + ')' : '';

				$( '#trustgate_reference' ).val( '' );

				if ( config.provider.slug === 'sumsub' && data.status === 'pending' ) {
					setStatus( config.i18n.pending, 'working' );
					if ( pollCount++ < 60 ) pollTimer = window.setTimeout( function () { confirmReference( reference ); }, 5000 );
					return;
				}

				if ( 'identity_unavailable' === data.status ) {
					setStatus( config.i18n.duplicateIdentity, 'failed' );
					return;
				}

				setStatus( config.i18n.failed + detail, 'failed' );
			} ).always( function () { confirming = false; } );
	}

	$( function () {
		if ( config.provider && config.provider.slug === 'sumsub' ) {
			$( '#wp-submit' ).prop( 'disabled', true );
		}

		$( '#user_email, #trustgate_first_name, #trustgate_last_name, #trustgate_consent' ).on( 'input change', function () {
			formVersion++;
			window.clearTimeout( pollTimer );
			if ( sumsubSdk ) sumsubSdk.destroy();
			sumsubSdk = undefined;
			sumsubApplicant = '';
			pollCount = 0;
			$( '#trustgate_reference' ).val( '' );
			setStatus( '', '' );
		} );

		$( '#trustgate_verify_button' ).on( 'click', function () {
			const email = getFieldValue( '#user_email' );
			const firstName = getFieldValue( '#trustgate_first_name' );
			const lastName = getFieldValue( '#trustgate_last_name' );
			const provider = config.provider || {};
			const consent = $( '#trustgate_consent' ).is( ':checked' );

			if ( ! email || ! firstName || ! lastName ) {
				setStatus( config.i18n.invalid, 'failed' );
				return;
			}

			if ( ! consent ) {
				setStatus( config.i18n.consent, 'failed' );
				return;
			}

			if ( provider.slug === 'sumsub' ) {
				startSumsub();
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
					const reference = callbackData.session_id || data.session_id || callbackData.widgetId || data.widgetId || verification.reference || data.reference || response.reference || response.user_ref || response.id;

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
