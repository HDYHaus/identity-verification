<?php
/**
 * Sumsub signed-request and registration-binding regression tests.
 *
 * @package TrustGateRegistration
 */
declare(strict_types=1);

// Reuse the registration harness, including its replay and metadata assertions.
require __DIR__ . '/test-registration-controller.php';
require_once dirname( __DIR__ ) . '/includes/Providers/sumsub-provider.php';

use HDYHaus\TrustGateRegistration\Providers\SumsubProvider;

$trustgate_sumsub_response = array();
$trustgate_sumsub_request = array();
$trustgate_sumsub_code = 200;
$trustgate_sumsub_review = null;
function wp_json_encode( mixed $value ): string|false { return json_encode( $value ); }
function wp_safe_remote_request( string $url, array $args ): array {
	global $trustgate_sumsub_response, $trustgate_sumsub_request, $trustgate_sumsub_code, $trustgate_sumsub_review;
	$trustgate_sumsub_request = array( 'url' => $url, 'args' => $args );
		$data = str_ends_with( $url, '/status' ) ? ( $trustgate_sumsub_review ?? $trustgate_sumsub_response['review'] ?? array() ) : $trustgate_sumsub_response;
	return array( 'body' => json_encode( $data ), 'response' => array( 'code' => $trustgate_sumsub_code ) );
}
function is_wp_error( mixed $value ): bool { return false; }
function wp_remote_retrieve_response_code( array $response ): int { return $response['response']['code']; }
function wp_remote_retrieve_body( array $response ): string { return $response['body']; }
function nocache_headers(): void {}

$trustgate_test_settings = array(
	'provider' => 'sumsub',
	'sumsub_mode' => 'test',
	'sumsub_test_app_token' => 'sbx:test-token',
	'sumsub_test_secret_key' => 'test-secret',
	'sumsub_test_level' => 'id-and-liveness',
	'consent_text' => 'Prembly consent must not leak into Sumsub.',
);
$provider = new SumsubProvider( 'trustgate_settings' );
$context = array( 'token' => 'sumsub-attempt', 'email' => 'person@example.com', 'first_name' => 'Maria', 'last_name' => 'Job' );
$id_method = new ReflectionMethod( $provider, 'external_user_id' );
$external_id = $id_method->invoke( $provider, $context );
$trustgate_sumsub_response = array( 'token' => '_act-example', 'userId' => $external_id );
trustgate_registration_assert_same( '_act-example', $provider->start_verification( $context )['token'] ?? '', 'SDK token is issued for the expected applicant only.' );
$req = $trustgate_sumsub_request;
$headers = $req['args']['headers'];
trustgate_registration_assert_same( 'https://api.sumsub.com/resources/accessTokens/sdk', $req['url'], 'The destination is fixed.' );
trustgate_registration_assert_same( hash_hmac( 'sha256', $headers['X-App-Access-Ts'] . 'POST/resources/accessTokens/sdk' . $req['args']['body'], 'test-secret' ), $headers['X-App-Access-Sig'], 'The exact method, path, timestamp and body are signed.' );
trustgate_registration_assert_same( 0, $req['args']['redirection'], 'Credentials cannot be forwarded on redirects.' );
trustgate_registration_assert_same( array( 'slug' => 'sumsub', 'isTest' => true ), $provider->get_public_config(), 'App credentials are never localized to the frontend.' );
$trustgate_sumsub_response['userId'] = 'wrong-applicant';
trustgate_registration_assert_same( false, isset( $provider->start_verification( $context )['token'] ), 'Mismatched SDK token responses fail closed.' );

$reference = str_repeat( 'a', 24 );
$fixture = array(
	'id' => $reference, 'externalUserId' => $external_id, 'type' => 'individual', 'email' => 'person@example.com',
	'review' => array( 'levelName' => 'id-and-liveness', 'reviewStatus' => 'completed', 'reviewResult' => array( 'reviewAnswer' => 'GREEN' ) ),
	'info' => array( 'idDocs' => array( array( 'country' => 'ALB', 'idDocType' => 'PASSPORT', 'number' => 'A-123 456' ) ) ),
);
$trustgate_test_options['trustgate_identity_secret'] = str_repeat( 'b', 64 );
$trustgate_sumsub_response = $fixture;
$result = $provider->confirm_verification( $reference, $context );
trustgate_registration_assert_same( true, $result['verified'], 'Completed approval with all bindings passes.' );
trustgate_registration_assert_same( hash_hmac( 'sha256', 'sumsub|test|ALB|PASSPORT|A123456', str_repeat( 'b', 64 ) ), $result['identity_hash'], 'Only a one-way document fingerprint is returned.' );
trustgate_registration_assert_same( false, isset( $result['raw'] ), 'Raw applicant data is never returned to registration storage.' );
$trustgate_sumsub_review = $fixture['review'];
$trustgate_sumsub_response['review']['reviewStatus'] = 'pending';
trustgate_registration_assert_same( true, $provider->confirm_verification( $reference, $context )['verified'], 'The current dedicated review endpoint overrides stale applicant metadata.' );
$trustgate_sumsub_response = $fixture;
$trustgate_sumsub_review['reprocessing'] = true;
trustgate_registration_assert_same( true, $provider->confirm_verification( $reference, $context )['verified'], 'A completed GREEN decision is authoritative even when the historical reprocessing flag remains set.' );
$trustgate_sumsub_review = null;

foreach ( array(
	array( 'externalUserId', 'unrelated' ), array( 'id', str_repeat( 'b', 24 ) ), array( 'email', 'other@example.com' ),
	array( 'type', 'company' ), array( 'deleted', true ), array( 'deactivated', true ),
) as [ $key, $value ] ) {
	$trustgate_sumsub_response = $fixture;
	$trustgate_sumsub_response[ $key ] = $value;
	trustgate_registration_assert_same( false, $provider->confirm_verification( $reference, $context )['verified'], 'Invalid applicant field is rejected: ' . $key );
}
foreach ( array(
	array( 'levelName', 'other-level' ), array( 'reviewStatus', 'pending' ), array( 'reviewStatus', 'onHold' ),
	array( 'reviewStatus', 'unknown' ), array( 'reviewResult', array( 'reviewAnswer' => 'RED' ) ),
	array( 'reviewResult', array( 'reviewAnswer' => 'YELLOW' ) ), array( 'reviewResult', array() ),
) as [ $key, $value ] ) {
	$trustgate_sumsub_response = $fixture;
	$trustgate_sumsub_response['review'][ $key ] = $value;
	trustgate_registration_assert_same( false, $provider->confirm_verification( $reference, $context )['verified'], 'Unapproved or mismatched review is rejected: ' . $key );
}
$trustgate_sumsub_response = $fixture;
unset( $trustgate_sumsub_response['info']['idDocs'][0]['number'] );
trustgate_registration_assert_same( false, $provider->confirm_verification( $reference, $context )['verified'], 'Approval without stable document attributes fails closed.' );
$trustgate_sumsub_response = $fixture;
foreach ( array( 'token', 'email', 'first_name', 'last_name' ) as $key ) {
	$other = $context;
	$other[ $key ] .= '-changed';
	trustgate_registration_assert_same( false, $provider->confirm_verification( $reference, $other )['verified'], 'Changed registration context is rejected: ' . $key );
}
$trustgate_sumsub_code = 403;
trustgate_registration_assert_same( false, $provider->confirm_verification( $reference, $context )['verified'], 'An HTTP error fails closed.' );
$trustgate_sumsub_code = 200;
$trustgate_test_settings['sumsub_mode'] = 'live';
$trustgate_sumsub_request = array();
trustgate_registration_assert_same( false, isset( $provider->start_verification( $context )['token'] ), 'Production never falls back to Sandbox credentials.' );
trustgate_registration_assert_same( array(), $trustgate_sumsub_request, 'Missing credentials make no API request.' );
$trustgate_test_settings['sumsub_live_app_token'] = 'sbx:wrong-mode';
$trustgate_test_settings['sumsub_live_secret_key'] = 'wrong-mode-secret';
$trustgate_test_settings['sumsub_live_level'] = 'id-and-liveness';
$provider->start_verification( $context );
trustgate_registration_assert_same( array(), $trustgate_sumsub_request, 'A Sandbox key pasted in Live settings is rejected.' );
$trustgate_test_settings['sumsub_mode'] = 'test';

// Exercise AJAX binding, lifetime, consent, and rate limiting through the real controller.
$controller = new HDYHaus\TrustGateRegistration\Registration\RegistrationController( 'trustgate_settings', $provider );
$attempt_key = 'trustgate_attempt_' . hash( 'sha256', $context['token'] );
$trustgate_test_transients[ $attempt_key ] = array( 'status' => 'issued', 'expires_at' => time() + 600 );
$_POST = array( 'token' => $context['token'], 'email' => $context['email'], 'firstName' => 'Maria', 'lastName' => 'Job', 'consent' => '1' );
$trustgate_sumsub_response = array( 'token' => '_act-example', 'userId' => $external_id );
try { $controller->start_verification(); } catch ( TrustGateJsonResponse $response ) {
	trustgate_registration_assert_same( 'ready', $response->payload['status'], 'Valid attempts can start the SDK.' );
}
try { $controller->start_verification(); } catch ( TrustGateJsonResponse $response ) {
	trustgate_registration_assert_same( 'rate_limited', $response->payload['status'], 'Repeated token requests are limited.' );
}
$trustgate_test_transients[ $attempt_key ]['expires_at'] = time() - 1;
try { $controller->start_verification(); } catch ( TrustGateJsonResponse $response ) {
	trustgate_registration_assert_same( 'invalid_attempt', $response->payload['status'], 'Expired attempts cannot issue tokens.' );
}
$trustgate_test_transients[ $attempt_key ] = array( 'status' => 'issued', 'expires_at' => time() + 600 );
$_POST['consent'] = '0';
try { $controller->start_verification(); } catch ( TrustGateJsonResponse $response ) {
	trustgate_registration_assert_same( 'invalid_attempt', $response->payload['status'], 'Consent is required before API transmission.' );
}
$_POST['consent'] = '1';
$_POST['reference'] = $reference;
$trustgate_sumsub_response = $fixture;
try { $controller->confirm_verification(); } catch ( TrustGateJsonResponse $response ) {
	trustgate_registration_assert_same( 'verified', $response->payload['status'], 'Bound Sumsub results use existing registration gating.' );
}
$attempt = $trustgate_test_transients[ $attempt_key ];
trustgate_registration_assert_same( 'sumsub', $attempt['provider'], 'Attempts retain their provider binding.' );
trustgate_registration_assert_same( HDYHaus\TrustGateRegistration\Registration\RegistrationController::get_sumsub_disclosure_defaults()['consent_text'], $attempt['consent_text'], 'Sumsub never inherits Prembly consent.' );
$valid = new ReflectionMethod( $controller, 'is_valid_attempt' );
trustgate_registration_assert_same( true, $valid->invoke( $controller, $context['token'], $reference, $context['email'], 'Maria', 'Job' ), 'Current Sumsub approval can register.' );
$other_controller = new HDYHaus\TrustGateRegistration\Registration\RegistrationController( 'trustgate_settings', new TrustGateTestProvider() );
trustgate_registration_assert_same( false, $valid->invoke( $other_controller, $context['token'], $reference, $context['email'], 'Maria', 'Job' ), 'Switching away from Sumsub rejects a prior Sumsub approval.' );
$trustgate_test_settings['sumsub_test_level'] = 'changed';
trustgate_registration_assert_same( false, $valid->invoke( $controller, $context['token'], $reference, $context['email'], 'Maria', 'Job' ), 'Changing provider configuration invalidates prior approval.' );
// Exercise failed decisions and expired approvals through registration, not only normalization.
$trustgate_test_settings['sumsub_test_level'] = 'id-and-liveness';
$ajax_post = array( 'token' => $context['token'], 'reference' => $reference, 'email' => $context['email'], 'firstName' => 'Maria', 'lastName' => 'Job', 'consent' => '1' );
$form_post = array( 'trustgate_attempt_token' => $context['token'], 'trustgate_reference' => $reference, 'trustgate_first_name' => 'Maria', 'trustgate_last_name' => 'Job', 'trustgate_consent' => '1', 'trustgate_registration_form_nonce' => 'valid-form-nonce' );
foreach ( array( 'FINAL', 'RETRY' ) as $reject_type ) {
	$trustgate_test_transients[ $attempt_key ] = array( 'status' => 'issued', 'expires_at' => time() + 600 );
	$trustgate_sumsub_response = $fixture;
	$trustgate_sumsub_response['review']['reviewResult'] = array( 'reviewAnswer' => 'RED', 'reviewRejectType' => $reject_type );
	$_POST = $ajax_post;
	try { $controller->confirm_verification(); } catch ( TrustGateJsonResponse $response ) {
		trustgate_registration_assert_same( 'not_verified', $response->payload['status'], 'Rejected and retry decisions fail AJAX approval.' );
	}
	$_POST = $form_post;
	trustgate_registration_assert_same( true, isset( $controller->validate_registration( new WP_Error(), 'person', $context['email'] )->errors['trustgate_verification_required'] ), 'Rejected applicants cannot register.' );
}
$trustgate_test_transients[ $attempt_key ] = $attempt + array( 'expires_at' => time() - 1 );
$_POST = $form_post;
trustgate_registration_assert_same( true, isset( $controller->validate_registration( new WP_Error(), 'person', $context['email'] )->errors['trustgate_verification_required'] ), 'Even a previously approved attempt is rejected after expiry.' );
$_POST = $ajax_post;
$trustgate_sumsub_request = array();
try { $controller->confirm_verification(); } catch ( TrustGateJsonResponse $response ) {
	trustgate_registration_assert_same( 'invalid_attempt', $response->payload['status'], 'Expired attempts cannot reconfirm approval.' );
}
trustgate_registration_assert_same( array(), $trustgate_sumsub_request, 'Expired attempts never call the provider.' );
$trustgate_test_transients[ $attempt_key ] = $attempt;
$_POST = $form_post;
$controller->store_user_meta( 42 );
trustgate_registration_assert_same( false, isset( $trustgate_test_transients[ $attempt_key ] ), 'Successful Sumsub registration consumes the attempt.' );
trustgate_registration_assert_same( true, isset( $controller->validate_registration( new WP_Error(), 'person', $context['email'] )->errors['trustgate_verification_required'] ), 'A consumed Sumsub attempt cannot register twice.' );
$reuse_context = $context;
$reuse_context['token'] = 'another-sumsub-attempt';
$reuse_reference = str_repeat( 'c', 24 );
$reuse_key = 'trustgate_attempt_' . hash( 'sha256', $reuse_context['token'] );
$trustgate_test_transients[ $reuse_key ] = array( 'status' => 'issued', 'expires_at' => time() + 600 );
$trustgate_sumsub_response = $fixture;
$trustgate_sumsub_response['id'] = $reuse_reference;
$trustgate_sumsub_response['externalUserId'] = $id_method->invoke( $provider, $reuse_context );
$_POST = $ajax_post;
$_POST['token'] = $reuse_context['token'];
$_POST['reference'] = $reuse_reference;
try { $controller->confirm_verification(); } catch ( TrustGateJsonResponse $response ) {
	trustgate_registration_assert_same( 'identity_unavailable', $response->payload['status'], 'A second approved applicant using the same document cannot create a second account.' );
}
fwrite( STDOUT, "Sumsub provider and registration tests passed.\n" );
