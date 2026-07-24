<?php
/**
 * RT-109 typed persistence contract tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReturnTag\TagCore\Application\Persistence\DenyAllEventIdentityPolicy;
use ReturnTag\TagCore\Application\Persistence\DenyAllEventMetadataPolicy;
use ReturnTag\TagCore\Application\Persistence\EventMetadata;
use ReturnTag\TagCore\Application\Persistence\EventMetadataPolicy;
use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceMappingException;
use ReturnTag\TagCore\Application\Persistence\Pagination\PageSize;
use ReturnTag\TagCore\Application\Persistence\RecordValidator;
use ReturnTag\TagCore\Application\Persistence\Repository\EventRepository;
use ReturnTag\TagCore\Application\Persistence\Value\AccessTokenDigest;
use ReturnTag\TagCore\Application\Persistence\Value\EmailCiphertext;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;
use ReturnTag\TagCore\Application\Persistence\Value\MessageCiphertext;
use ReturnTag\TagCore\Application\Persistence\Value\OtpHash;
use ReturnTag\TagCore\Domain\Batch\BatchStatus;
use ReturnTag\TagCore\Domain\Conversation\ConversationStatus;
use ReturnTag\TagCore\Domain\Conversation\DeliveryStatus;
use ReturnTag\TagCore\Domain\Conversation\MessageSenderRole;
use ReturnTag\TagCore\Domain\Tag\SmartNetwork;
use ReturnTag\TagCore\Domain\Tag\TagStatus;
use ReturnTag\TagCore\Domain\Tag\TagType;
use ReturnTag\TagCore\Infrastructure\Persistence\StoredRow;

/**
 * Guards canonical values, bounded inputs, metadata, and append-only APIs.
 */
final class PersistenceContractsTest extends TestCase {
	/**
	 * Enum values must exactly match the frozen PRD vocabulary.
	 */
	public function test_canonical_enum_values_are_exact(): void {
		self::assertSame(
			array( 'sticker', 'classic_tag', 'smart_tag' ),
			array_column( TagType::cases(), 'value' )
		);
		self::assertSame(
			array( 'draft', 'generating', 'generated', 'exported', 'released', 'suspended', 'voided' ),
			array_column( BatchStatus::cases(), 'value' )
		);
		self::assertSame(
			array( 'unregistered', 'active', 'suspended', 'retired' ),
			array_column( TagStatus::cases(), 'value' )
		);
		self::assertSame(
			array( 'none', 'apple_find_my', 'google_find_hub', 'other' ),
			array_column( SmartNetwork::cases(), 'value' )
		);
		self::assertSame(
			array( 'pending_verification', 'open', 'closed', 'blocked', 'expired' ),
			array_column( ConversationStatus::cases(), 'value' )
		);
		self::assertSame(
			array( 'finder', 'owner', 'system' ),
			array_column( MessageSenderRole::cases(), 'value' )
		);
		self::assertSame(
			array( 'queued', 'sent', 'delivered', 'deferred', 'bounced', 'complained', 'failed' ),
			array_column( DeliveryStatus::cases(), 'value' )
		);
	}

	/**
	 * Tag IDs and digests accept only canonical storage forms.
	 */
	public function test_public_identifiers_and_digests_are_strict(): void {
		self::assertSame( 'N7R2W9', RecordValidator::tag_id( 'N7R2W9' ) );
		self::assertSame( str_repeat( 'a', 64 ), RecordValidator::digest( str_repeat( 'a', 64 ), 'digest' ) );

		$this->expectException( InvalidArgumentException::class );
		RecordValidator::tag_id( 'n7r2w9' );
	}

	/**
	 * Sensitive persistence values must use distinct explicit types.
	 */
	public function test_sensitive_persistence_values_are_not_interchangeable_strings(): void {
		$email  = EmailCiphertext::from_encrypted_bytes( "email-envelope\0bytes" );
		$body   = MessageCiphertext::from_encrypted_bytes( "message-envelope\0bytes" );
		$lookup = LookupDigest::from_digest( str_repeat( 'a', 64 ) );
		$token  = AccessTokenDigest::from_digest( str_repeat( 'b', 64 ) );
		$otp    = OtpHash::from_password_hash( '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.' );

		self::assertSame( "email-envelope\0bytes", $email->value );
		self::assertSame( "message-envelope\0bytes", $body->value );
		self::assertSame( str_repeat( 'a', 64 ), $lookup->value );
		self::assertSame( str_repeat( 'b', 64 ), $token->value );
		self::assertStringStartsWith( '$2y$', $otp->value );

		$this->expectException( InvalidArgumentException::class );
		OtpHash::from_password_hash( '123456' );
	}

	/**
	 * Persistence timestamps must have a zero UTC offset.
	 */
	public function test_persistence_timestamp_requires_utc(): void {
		$utc = new DateTimeImmutable( '2026-07-24 00:00:00', new DateTimeZone( 'UTC' ) );
		self::assertSame( $utc, RecordValidator::utc( $utc, 'created_at' ) );

		$this->expectException( InvalidArgumentException::class );
		RecordValidator::utc(
			new DateTimeImmutable( '2026-07-24 08:00:00', new DateTimeZone( 'Asia/Shanghai' ) ),
			'created_at'
		);
	}

	/**
	 * Page sizes are bounded and default to fifty rows.
	 */
	public function test_page_size_is_bounded(): void {
		self::assertSame( 50, ( new PageSize() )->value );
		self::assertSame( 100, ( new PageSize( 100 ) )->value );

		$this->expectException( InvalidArgumentException::class );
		new PageSize( 101 );
	}

	/**
	 * RT-109 defaults to no approved non-empty Event metadata.
	 */
	public function test_default_event_metadata_policy_fails_closed(): void {
		$this->expectException( InvalidArgumentException::class );
		EventMetadata::from_values(
			'tag_activated',
			array( 'reason_code' => 'owner_verified' ),
			new DenyAllEventMetadataPolicy()
		);
	}

	/**
	 * Event identity persistence defaults to deny and rejects obvious PII.
	 */
	public function test_event_identity_defaults_to_deny_and_has_a_minimum_privacy_guard(): void {
		$policy = new DenyAllEventIdentityPolicy();

		self::assertFalse( $policy->allows( 'tag_activated', 'user', 42, 'tag', 'N7R2W9', 'rt109-test' ) );

		foreach ( array( 'finder@example.test', '192.0.2.4', 'bearer-token-value', str_repeat( 'a', 64 ) ) as $unsafe ) {
			try {
				RecordValidator::privacy_safe_event_identifier( $unsafe, 191 );
				self::fail( 'Expected an unsafe Event identifier to fail.' );
			} catch ( InvalidArgumentException ) {
				self::assertTrue( true );
			}
		}
	}

	/**
	 * Approved metadata is canonical while sensitive and nested values fail.
	 */
	public function test_event_metadata_is_flat_canonical_and_privacy_safe(): void {
		$policy = $this->metadata_policy( array( 'reason_code', 'attempt' ) );
		$value  = EventMetadata::from_values(
			'tag_activation_conflict',
			array(
				'reason_code' => 'already_active',
				'attempt'     => 2,
			),
			$policy
		);

		self::assertSame( '{"attempt":2,"reason_code":"already_active"}', $value->json() );

		try {
			EventMetadata::from_values(
				'tag_activation_conflict',
				array( 'reason_code' => 'finder@example.test' ),
				$policy
			);
			self::fail( 'Expected a full email-shaped value to be rejected.' );
		} catch ( InvalidArgumentException ) {
			self::assertTrue( true );
		}

		$this->expectException( InvalidArgumentException::class );
		EventMetadata::from_values( 'tag_activation_conflict', array( 'reason_code' => array( 'nested' ) ), $policy );
	}

	/**
	 * Invalid or unapproved JSON already in storage fails closed.
	 */
	public function test_invalid_stored_metadata_fails_mapping(): void {
		$this->expectException( PersistenceMappingException::class );
		EventMetadata::from_stored_json( 'tag_activated', '{"unknown":true}', new DenyAllEventMetadataPolicy() );
	}

	/**
	 * Unknown persisted enum values must never receive a fallback mapping.
	 */
	public function test_unknown_stored_enum_fails_mapping(): void {
		$this->expectException( PersistenceMappingException::class );
		StoredRow::enum( array( 'tag_type' => 'classic' ), 'tag_type', TagType::class );
	}

	/**
	 * Event persistence remains append-and-query only.
	 */
	public function test_event_repository_exposes_no_update_or_delete(): void {
		$methods = array_map(
			static fn( \ReflectionMethod $method ): string => $method->getName(),
			( new ReflectionClass( EventRepository::class ) )->getMethods()
		);
		sort( $methods );

		self::assertSame( array( 'append', 'list_by_correlation', 'list_by_target' ), $methods );
	}

	/**
	 * Build one explicit test-only Event metadata policy.
	 *
	 * @param array $allowed_keys Approved test keys.
	 * @phpstan-param list<string> $allowed_keys
	 */
	private function metadata_policy( array $allowed_keys ): EventMetadataPolicy {
		return new class( $allowed_keys ) implements EventMetadataPolicy {
			/**
			 * Create a test policy.
			 *
			 * @param array $allowed_keys Approved test keys.
			 * @phpstan-param list<string> $allowed_keys
			 */
			public function __construct( private readonly array $allowed_keys ) {
			}

			/**
			 * Return approved test keys.
			 *
			 * @param string $event_type Event type.
			 * @return list<string>
			 */
			public function allowed_keys( string $event_type ): array {
				unset( $event_type );

				return $this->allowed_keys;
			}
		};
	}
}
