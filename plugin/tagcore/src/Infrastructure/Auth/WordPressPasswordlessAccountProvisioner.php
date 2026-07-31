<?php
/**
 * WordPress passwordless account provisioner.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Auth;

use DateTimeImmutable;
use ReturnTag\TagCore\Application\Auth\PasswordlessAccountEventIdentityPolicy;
use ReturnTag\TagCore\Application\Auth\PasswordlessAccountProvisioner;
use ReturnTag\TagCore\Application\Persistence\EventMetadata;
use ReturnTag\TagCore\Application\Persistence\Record\NewEventRecord;
use ReturnTag\TagCore\Application\Persistence\Repository\EventRepository;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;
use ReturnTag\TagCore\Domain\Auth\EmailAddress;
use RuntimeException;
use Throwable;
use WP_Error;
use WP_User;
use wpdb;

/**
 * Serializes ReturnTag account creation and reuses exact existing identities.
 */
final class WordPressPasswordlessAccountProvisioner implements PasswordlessAccountProvisioner {
	public const SOURCE_META = 'returntag_passwordless_account_source_version';

	public const AUDIT_EVENT_META = 'returntag_passwordless_account_event_id';

	private const LOCK_TIMEOUT_SECONDS = 2;

	private const USERNAME_ATTEMPTS = 3;

	/**
	 * Create the WordPress account adapter.
	 *
	 * @param wpdb            $database Active database connection.
	 * @param EventRepository $events Privacy-safe audit Event repository.
	 */
	public function __construct(
		private readonly wpdb $database,
		private readonly EventRepository $events
	) {
	}

	/**
	 * Find or create exactly one account under a keyed database lock.
	 *
	 * @param EmailAddress      $email Canonical verified email.
	 * @param LookupDigest      $email_lookup Keyed email lock scope.
	 * @param DateTimeImmutable $now Current UTC time.
	 * @throws RuntimeException When the identity cannot be resolved safely.
	 */
	public function provision(
		EmailAddress $email,
		LookupDigest $email_lookup,
		DateTimeImmutable $now
	): int {
		$lock_name = $this->lock_name( $email_lookup );
		$this->acquire_lock( $lock_name );

		try {
			$users = $this->find_exact_users( $email );

			if ( count( $users ) > 1 ) {
				throw new RuntimeException( 'Passwordless identity is ambiguous.' );
			}

			if ( 1 === count( $users ) ) {
				$user = $users[0];
				$this->ensure_site_membership( $user->ID );
				$this->repair_account_audit( $user->ID, $now );

				return $user->ID;
			}

			$user_id = $this->create_user( $email );
			$users   = $this->find_exact_users( $email );

			if ( 1 !== count( $users ) || $user_id !== $users[0]->ID ) {
				throw new RuntimeException( 'Passwordless identity postcondition failed.' );
			}

			$this->ensure_site_membership( $user_id );
			$this->repair_account_audit( $user_id, $now );

			return $user_id;
		} finally {
			$this->release_lock( $lock_name );
		}
	}

	/**
	 * Create one least-privilege user, retrying only an opaque username collision.
	 *
	 * @param EmailAddress $email Canonical verified email.
	 * @throws RuntimeException When account creation cannot complete safely.
	 */
	private function create_user( EmailAddress $email ): int {
		if ( strlen( $email->value ) > 100 || false === is_email( $email->value ) ) {
			throw new RuntimeException( 'Passwordless identity cannot be provisioned.' );
		}

		for ( $attempt = 0; $attempt < self::USERNAME_ATTEMPTS; ++$attempt ) {
			$user_id = wp_insert_user(
				array(
					'user_login'   => 'returntag_' . bin2hex( random_bytes( 16 ) ),
					'user_pass'    => wp_generate_password( 48, true, true ),
					'user_email'   => $email->value,
					'display_name' => 'ReturnTag User',
					'role'         => 'subscriber',
					'meta_input'   => array(
						self::SOURCE_META => 1,
					),
				)
			);

			if ( is_int( $user_id ) && $user_id > 0 ) {
				return $user_id;
			}

			if ( ! $user_id instanceof WP_Error ) {
				throw new RuntimeException( 'Passwordless identity cannot be provisioned.' );
			}

			if ( 'existing_user_email' === $user_id->get_error_code() ) {
				$users = $this->find_exact_users( $email );

				if ( 1 === count( $users ) ) {
					return $users[0]->ID;
				}

				throw new RuntimeException( 'Passwordless identity is ambiguous.' );
			}

			if ( 'existing_user_login' !== $user_id->get_error_code() ) {
				throw new RuntimeException( 'Passwordless identity cannot be provisioned.' );
			}
		}

		throw new RuntimeException( 'Passwordless identity cannot be provisioned.' );
	}

	/**
	 * Return exact canonical email matches without limiting to current-site users.
	 *
	 * @param EmailAddress $email Canonical verified email.
	 * @return list<WP_User>
	 */
	private function find_exact_users( EmailAddress $email ): array {
		$candidates = get_users(
			array(
				'blog_id'        => 0,
				'search'         => $email->value,
				'search_columns' => array( 'user_email' ),
				'number'         => 3,
				'fields'         => 'all',
			)
		);
		$matches    = array();

		foreach ( $candidates as $candidate ) {
			if (
				$candidate instanceof WP_User
				&& strtolower( trim( $candidate->user_email ) ) === $email->value
			) {
				$matches[] = $candidate;
			}
		}

		return $matches;
	}

	/**
	 * Add a network user to the current site without changing existing roles.
	 *
	 * @param int $user_id Positive WordPress User ID.
	 * @throws RuntimeException When multisite membership cannot be established.
	 */
	private function ensure_site_membership( int $user_id ): void {
		if ( ! is_multisite() || is_user_member_of_blog( $user_id, get_current_blog_id() ) ) {
			return;
		}

		$result = add_user_to_blog( get_current_blog_id(), $user_id, 'subscriber' );

		if ( $result instanceof WP_Error ) {
			throw new RuntimeException( 'Passwordless identity cannot join this site.' );
		}
	}

	/**
	 * Append an at-least-once account creation audit before session issuance.
	 *
	 * @param int               $user_id Positive WordPress User ID.
	 * @param DateTimeImmutable $now Current UTC time.
	 * @throws RuntimeException When the audit record cannot be persisted.
	 */
	private function repair_account_audit( int $user_id, DateTimeImmutable $now ): void {
		if (
			'1' !== (string) get_user_meta( $user_id, self::SOURCE_META, true )
			|| 0 < (int) get_user_meta( $user_id, self::AUDIT_EVENT_META, true )
		) {
			return;
		}

		$event = $this->events->append(
			new NewEventRecord(
				PasswordlessAccountEventIdentityPolicy::ACCOUNT_CREATED,
				'system',
				null,
				'user',
				(string) $user_id,
				'success',
				null,
				EventMetadata::none(),
				$now
			)
		);

		$updated = update_user_meta( $user_id, self::AUDIT_EVENT_META, $event->event_id );

		if ( false === $updated && (int) get_user_meta( $user_id, self::AUDIT_EVENT_META, true ) !== $event->event_id ) {
			throw new RuntimeException( 'Passwordless account audit could not be recorded.' );
		}
	}

	/**
	 * Build a network-scoped lock name containing no raw email.
	 *
	 * @param LookupDigest $email_lookup Keyed email lock scope.
	 */
	private function lock_name( LookupDigest $email_lookup ): string {
		return sprintf(
			'returntag:account:%d:%s',
			is_multisite() ? get_current_network_id() : 1,
			substr( $email_lookup->value, 0, 32 )
		);
	}

	/**
	 * Acquire the bounded advisory lock on the active WordPress connection.
	 *
	 * @param string $lock_name Privacy-safe MySQL advisory lock name.
	 * @throws RuntimeException When the advisory lock cannot be acquired.
	 */
	private function acquire_lock( string $lock_name ): void {
		$previous = $this->database->suppress_errors( true );

		try {
			// A keyed advisory lock serializes account provisioning.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $this->database->get_var(
				$this->database->prepare( 'SELECT GET_LOCK(%s, %d)', $lock_name, self::LOCK_TIMEOUT_SECONDS )
			);
		} finally {
			$this->database->suppress_errors( $previous );
		}

		if ( '1' !== (string) $result ) {
			throw new RuntimeException( 'Passwordless identity lock is unavailable.' );
		}
	}

	/**
	 * Release the advisory lock without masking the primary operation result.
	 *
	 * @param string $lock_name Exact MySQL advisory lock name to release.
	 */
	private function release_lock( string $lock_name ): void {
		$previous = $this->database->suppress_errors( true );

		try {
			// Release the exact keyed advisory lock acquired above.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$this->database->get_var(
				$this->database->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name )
			);
		} catch ( Throwable $exception ) {
			// The server releases named locks automatically if the connection closes.
			unset( $exception );
		} finally {
			$this->database->suppress_errors( $previous );
		}
	}
}
