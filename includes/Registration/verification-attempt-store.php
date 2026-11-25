<?php
/**
 * Persistent, privacy-conscious registration attempt records.
 *
 * @package TrustGateRegistration
 */

declare(strict_types=1);

namespace HDYHaus\TrustGateRegistration\Registration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- This dedicated custom-table repository uses direct CRUD for its narrowly scoped attempt records.

/**
 * Stores provider verification attempts until they are linked to WordPress users.
 */
class VerificationAttemptStore {
	/**
	 * Schema version.
	 */
	private const SCHEMA_VERSION = '1';

	/**
	 * Pending attempt lifetime.
	 */
	public const ATTEMPT_TTL = 7 * DAY_IN_SECONDS;

	/**
	 * Get table name.
	 */
	public function table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'hdyhaus_verification_attempts';
	}

	/**
	 * Install or upgrade the attempt table.
	 */
	public function install(): void {
		global $wpdb;

		if ( self::SCHEMA_VERSION === get_option( 'trustgate_attempt_schema_version', '' ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset_collate = $wpdb->get_charset_collate();
		$table           = $this->table_name();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			attempt_hash char(64) NOT NULL,
			provider varchar(32) NOT NULL,
			environment varchar(16) NOT NULL,
			provider_reference varchar(191) NOT NULL DEFAULT '',
			reference_hash char(64) NOT NULL DEFAULT '',
			email varchar(191) NOT NULL DEFAULT '',
			email_hash char(64) NOT NULL DEFAULT '',
			username varchar(60) NOT NULL DEFAULT '',
			first_name varchar(100) NOT NULL DEFAULT '',
			last_name varchar(100) NOT NULL DEFAULT '',
			first_name_hash char(64) NOT NULL DEFAULT '',
			last_name_hash char(64) NOT NULL DEFAULT '',
			identity_hash char(64) NOT NULL DEFAULT '',
			status varchar(32) NOT NULL,
			failure_code varchar(64) NOT NULL DEFAULT '',
			consent_at datetime NULL,
			consent_text text NULL,
			consent_version char(64) NOT NULL DEFAULT '',
			resume_token_hash char(64) NOT NULL DEFAULT '',
			resume_expires_at datetime NULL,
			verified_at datetime NULL,
			user_id bigint(20) unsigned NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			expires_at datetime NOT NULL,
			recovered_by bigint(20) unsigned NULL,
			recovered_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY attempt_hash (attempt_hash),
			KEY reference_hash (reference_hash),
			KEY status_expires (status,expires_at),
			KEY email_hash (email_hash),
			KEY identity_hash (identity_hash),
			KEY user_id (user_id)
		) {$charset_collate};";

		dbDelta( $sql );
		update_option( 'trustgate_attempt_schema_version', self::SCHEMA_VERSION, false );
	}

	/**
	 * Record the start of a provider verification flow before opening its widget.
	 *
	 * @param array<string, mixed> $attempt Attempt fields.
	 */
	public function record_started( array $attempt ): bool {
		global $wpdb;

		$now        = gmdate( 'Y-m-d H:i:s' );
		$token      = (string) ( $attempt['token'] ?? '' );
		$email      = sanitize_email( (string) ( $attempt['email'] ?? '' ) );
		$first      = sanitize_text_field( (string) ( $attempt['first_name'] ?? '' ) );
		$last       = sanitize_text_field( (string) ( $attempt['last_name'] ?? '' ) );
		$token_hash = '' !== $token ? hash( 'sha256', $token ) : '';

		if ( '' === $token_hash || ! is_email( $email ) || '' === $first || '' === $last ) {
			return false;
		}

		$existing = $this->get_by_token_hash( $token_hash );
		if ( $existing && in_array( $existing['status'], array( 'verified_pending_registration', 'registration_failed', 'identity_conflict', 'reference_conflict', 'account_created' ), true ) ) {
			return false;
		}

		$data = array(
			'attempt_hash'    => $token_hash,
			'provider'        => sanitize_key( (string) ( $attempt['provider'] ?? 'prembly' ) ),
			'environment'     => 'sandbox' === ( $attempt['environment'] ?? '' ) ? 'sandbox' : 'live',
			'email'           => $email,
			'email_hash'      => hash( 'sha256', strtolower( $email ) ),
			'username'        => sanitize_user( (string) ( $attempt['username'] ?? '' ), true ),
			'first_name'      => $first,
			'last_name'       => $last,
			'first_name_hash' => hash( 'sha256', strtolower( trim( $first ) ) ),
			'last_name_hash'  => hash( 'sha256', strtolower( trim( $last ) ) ),
			'status'          => 'verification_started',
			'failure_code'    => '',
			'consent_at'      => $now,
			'consent_text'    => sanitize_textarea_field( (string) ( $attempt['consent_text'] ?? '' ) ),
			'consent_version' => sanitize_text_field( (string) ( $attempt['consent_version'] ?? '' ) ),
			'updated_at'      => $now,
			'expires_at'      => gmdate( 'Y-m-d H:i:s', time() + self::ATTEMPT_TTL ),
		);

		if ( $existing ) {
			unset( $data['attempt_hash'] );
			return false !== $wpdb->update( $this->table_name(), $data, array( 'attempt_hash' => $token_hash ) );
		}

		$data['created_at'] = $now;
		return false !== $wpdb->insert( $this->table_name(), $data );
	}

	/**
	 * Record a verified attempt, including identity conflicts that need review.
	 *
	 * @param array<string, mixed> $attempt Attempt fields.
	 */
	public function record( array $attempt ): bool {
		global $wpdb;

		$now           = gmdate( 'Y-m-d H:i:s' );
		$token         = (string) ( $attempt['token'] ?? '' );
		$reference     = sanitize_text_field( (string) ( $attempt['reference'] ?? '' ) );
		$email         = sanitize_email( (string) ( $attempt['email'] ?? '' ) );
		$identity_hash = sanitize_text_field( (string) ( $attempt['identity_hash'] ?? '' ) );

		if ( '' === $token || '' === $reference || ! is_email( $email ) || 1 !== preg_match( '/^[a-f0-9]{64}$/', $identity_hash ) ) {
			return false;
		}

		$token_hash     = hash( 'sha256', $token );
		$reference_hash = hash( 'sha256', $reference );
		$existing       = $this->get_by_token_hash( $token_hash );
		$first_name     = sanitize_text_field( (string) ( $attempt['first_name'] ?? '' ) );
		$last_name      = sanitize_text_field( (string) ( $attempt['last_name'] ?? '' ) );
		$data           = array(
			'attempt_hash'       => $token_hash,
			'provider'           => sanitize_key( (string) ( $attempt['provider'] ?? 'prembly' ) ),
			'environment'        => 'sandbox' === ( $attempt['environment'] ?? '' ) ? 'sandbox' : 'live',
			'provider_reference' => $reference,
			'reference_hash'     => $reference_hash,
			'email'              => $email,
			'email_hash'         => hash( 'sha256', strtolower( $email ) ),
			'username'           => sanitize_user( (string) ( $attempt['username'] ?? '' ), true ),
			'first_name'         => $first_name,
			'last_name'          => $last_name,
			'first_name_hash'    => hash( 'sha256', strtolower( trim( $first_name ) ) ),
			'last_name_hash'     => hash( 'sha256', strtolower( trim( $last_name ) ) ),
			'identity_hash'      => $identity_hash,
			'status'             => sanitize_key( (string) ( $attempt['status'] ?? 'verified_pending_registration' ) ),
			'failure_code'       => sanitize_key( (string) ( $attempt['failure_code'] ?? '' ) ),
			'consent_at'         => sanitize_text_field( (string) ( $attempt['consent_at'] ?? $now ) ),
			'consent_text'       => sanitize_textarea_field( (string) ( $attempt['consent_text'] ?? '' ) ),
			'consent_version'    => sanitize_text_field( (string) ( $attempt['consent_version'] ?? '' ) ),
			'resume_token_hash'  => sanitize_text_field( (string) ( $attempt['resume_token_hash'] ?? '' ) ),
			'resume_expires_at'  => sanitize_text_field( (string) ( $attempt['resume_expires_at'] ?? '' ) ),
			'verified_at'        => sanitize_text_field( (string) ( $attempt['verified_at'] ?? $now ) ),
			'updated_at'         => $now,
			'expires_at'         => gmdate( 'Y-m-d H:i:s', time() + self::ATTEMPT_TTL ),
		);

		if ( $existing ) {
			unset( $data['attempt_hash'], $data['created_at'] );
			return false !== $wpdb->update( $this->table_name(), $data, array( 'attempt_hash' => $token_hash ) );
		}

		$data['created_at'] = $now;

		return false !== $wpdb->insert( $this->table_name(), $data );
	}

	/**
	 * Find a current attempt using its secret browser token.
	 *
	 * @param string $token Secret attempt token.
	 * @return array<string, mixed>
	 */
	public function get_by_token( string $token ): array {
		if ( '' === $token ) {
			return array();
		}

		$attempt = $this->get_by_token_hash( hash( 'sha256', $token ) );

		if ( ! $attempt || strtotime( (string) $attempt['expires_at'] . ' UTC' ) < time() || ! empty( $attempt['user_id'] ) ) {
			return array();
		}

		$attempt['token'] = $token;
		return $attempt;
	}

	/**
	 * Find one attempt by its opaque table ID.
	 *
	 * @param int $id Attempt ID.
	 * @return array<string, mixed>
	 */
	public function get_by_id( int $id ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is generated from the site prefix; variable values use placeholders.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $this->table_name() . ' WHERE id = %d', $id ), ARRAY_A );
		return is_array( $row ) ? $row : array();
	}

	/**
	 * Update lifecycle fields for a token.
	 *
	 * @param string               $token Secret attempt token.
	 * @param array<string, mixed> $fields Fields to update.
	 */
	public function update( string $token, array $fields ): bool {
		global $wpdb;

		$allowed            = array( 'status', 'failure_code', 'user_id', 'username', 'recovered_by', 'recovered_at', 'updated_at', 'expires_at', 'resume_token_hash', 'resume_expires_at' );
		$data               = array_intersect_key( $fields, array_flip( $allowed ) );
		$data['updated_at'] = gmdate( 'Y-m-d H:i:s' );

		if ( '' === $token || empty( $data ) ) {
			return false;
		}

		$attempt_hash = hash( 'sha256', $token );
		if ( empty( $this->get_by_token_hash( $attempt_hash ) ) ) {
			$resumed_attempt = $this->get_by_resume_token( $token );
			$attempt_hash    = (string) ( $resumed_attempt['attempt_hash'] ?? '' );
		}

		return '' !== $attempt_hash && false !== $wpdb->update( $this->table_name(), $data, array( 'attempt_hash' => $attempt_hash ) );
	}

	/**
	 * Persist and return a secure resume token for a verified attempt.
	 *
	 * @param string $attempt_token Original secret attempt token.
	 */
	public function issue_resume_token( string $attempt_token ): string {
		$attempt = $this->get_by_token( $attempt_token );
		if ( empty( $attempt ) || ! in_array( $attempt['status'], array( 'verified_pending_registration', 'registration_failed' ), true ) ) {
			return '';
		}

		$token = wp_generate_password( 48, false, false );
		$this->update(
			$attempt_token,
			array(
				'resume_token_hash' => hash( 'sha256', $token ),
				'resume_expires_at' => $attempt['expires_at'],
			)
		);

		return $token;
	}

	/**
	 * Find an attempt by its emailed resume token.
	 *
	 * @param string $token Resume token.
	 * @return array<string, mixed>
	 */
	public function get_by_resume_token( string $token ): array {
		global $wpdb;

		if ( '' === $token ) {
			return array();
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . $this->table_name() . ' WHERE resume_token_hash = %s AND resume_expires_at >= %s', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is generated from the site prefix; variable values use placeholders.
				hash( 'sha256', $token ),
				gmdate( 'Y-m-d H:i:s' )
			),
			ARRAY_A
		);

		if ( ! is_array( $row ) || ! empty( $row['user_id'] ) || '' === (string) $row['email'] || ! in_array( $row['status'], array( 'verified_pending_registration', 'registration_failed' ), true ) ) {
			return array();
		}

		$row['token'] = $token;
		return $row;
	}

	/**
	 * Update a row after administrator recovery.
	 *
	 * @param int                  $id Attempt ID.
	 * @param array<string, mixed> $fields Fields to update.
	 */
	public function update_by_id( int $id, array $fields ): bool {
		global $wpdb;

		$allowed            = array( 'status', 'failure_code', 'user_id', 'username', 'recovered_by', 'recovered_at', 'updated_at', 'expires_at', 'resume_token_hash', 'resume_expires_at' );
		$data               = array_intersect_key( $fields, array_flip( $allowed ) );
		$data['updated_at'] = gmdate( 'Y-m-d H:i:s' );

		return $id > 0 && ! empty( $data ) && false !== $wpdb->update( $this->table_name(), $data, array( 'id' => $id ) );
	}

	/**
	 * List unresolved attempts for authorized administrators.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_unresolved(): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, email, username, first_name, last_name, provider_reference, environment, status, failure_code, verified_at, created_at, expires_at FROM ' . $this->table_name() . ' WHERE user_id IS NULL AND status IN (%s, %s, %s, %s, %s, %s, %s, %s) AND expires_at >= %s ORDER BY created_at DESC LIMIT 100', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is generated from the site prefix; variable values use placeholders.
				'verified_pending_registration',
				'registration_failed',
				'identity_conflict',
				'reference_conflict',
				'verification_started',
				'verification_failed',
				'verification_cancelled',
				'verification_error',
				gmdate( 'Y-m-d H:i:s' )
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Find attempts associated with an email address for privacy export.
	 *
	 * @param string $email Email address.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_by_email( string $email ): array {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return array();
		}

		$email_hash = hash( 'sha256', strtolower( sanitize_email( $email ) ) );
		$rows       = $wpdb->get_results(
			$wpdb->prepare( 'SELECT status, provider, environment, provider_reference, verified_at, failure_code, user_id, created_at FROM ' . $this->table_name() . ' WHERE email_hash = %s ORDER BY created_at DESC', $email_hash ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is generated from the site prefix; variable values use placeholders.
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Delete expired unlinked attempts.
	 */
	public function prune_expired(): void {
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM ' . $this->table_name() . ' WHERE expires_at < %s', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is generated from the site prefix; variable values use placeholders.
				gmdate( 'Y-m-d H:i:s' )
			)
		);
	}

	/**
	 * Erase attempt personal data while retaining the one-way identity fingerprint.
	 *
	 * @param string $email Email address to erase.
	 */
	public function erase_email( string $email ): bool {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return false;
		}

		$email_hash = hash( 'sha256', strtolower( sanitize_email( $email ) ) );
		$result     = $wpdb->update(
			$this->table_name(),
			array(
				'provider_reference' => '',
				'reference_hash'     => '',
				'email'              => '',
				'email_hash'         => '',
				'username'           => '',
				'first_name'         => '',
				'last_name'          => '',
				'status'             => 'erased',
				'failure_code'       => '',
				'consent_at'         => null,
				'consent_text'       => null,
				'consent_version'    => '',
				'resume_token_hash'  => '',
				'resume_expires_at'  => null,
				'user_id'            => null,
				'updated_at'         => gmdate( 'Y-m-d H:i:s' ),
			),
			array( 'email_hash' => $email_hash )
		);

		return false !== $result;
	}

	/**
	 * Find an existing account that owns an identity fingerprint.
	 *
	 * @param string $identity_hash One-way identity hash.
	 * @return int User ID or zero.
	 */
	public function find_user_for_identity( string $identity_hash ): int {
		return $this->find_user_by_meta( 'trustgate_identity_hash', $identity_hash );
	}

	/**
	 * Find an existing account that owns a verification reference.
	 *
	 * @param string $reference_hash One-way verification-reference hash.
	 * @return int User ID or zero.
	 */
	public function find_user_for_reference( string $reference_hash ): int {
		return $this->find_user_by_meta( 'trustgate_reference_hash', $reference_hash );
	}

	/**
	 * Find the user associated with one of this plugin's verification hashes.
	 *
	 * @param string $meta_key User-meta key.
	 * @param string $hash One-way hash value.
	 * @return int User ID or zero.
	 */
	private function find_user_by_meta( string $meta_key, string $hash ): int {
		$user_ids = get_users(
			array(
				'fields'     => 'ids',
				'number'     => 1,
				'meta_key'   => $meta_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => $hash, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		return empty( $user_ids ) ? 0 : (int) $user_ids[0];
	}

	/**
	 * Fetch a row by its hashed browser token.
	 *
	 * @param string $token_hash SHA-256 attempt-token hash.
	 * @return array<string, mixed>
	 */
	private function get_by_token_hash( string $token_hash ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is generated from the site prefix; variable values use placeholders.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $this->table_name() . ' WHERE attempt_hash = %s', $token_hash ), ARRAY_A );
		return is_array( $row ) ? $row : array();
	}
}
