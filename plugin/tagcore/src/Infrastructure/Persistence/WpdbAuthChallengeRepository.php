<?php
/**
 * WordPress database Authentication Challenge Repository.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Persistence;

use InvalidArgumentException;
use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceMappingException;
use ReturnTag\TagCore\Application\Persistence\Record\AuthChallengeRecord;
use ReturnTag\TagCore\Application\Persistence\Record\NewAuthChallengeRecord;
use ReturnTag\TagCore\Application\Persistence\RecordValidator;
use ReturnTag\TagCore\Application\Persistence\Repository\AuthChallengeRepository;
use ReturnTag\TagCore\Application\Persistence\Value\EmailCiphertext;
use ReturnTag\TagCore\Application\Persistence\Value\LookupDigest;
use ReturnTag\TagCore\Application\Persistence\Value\OtpHash;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;

/**
 * Persists opaque one-time authentication challenge state.
 */
final class WpdbAuthChallengeRepository implements AuthChallengeRepository {
	/**
	 * Create the Repository.
	 *
	 * @param WpdbGateway           $gateway Database gateway.
	 * @param TableNames            $tables Trusted table names.
	 * @param DatabaseDateTimeCodec $dates UTC datetime codec.
	 */
	public function __construct(
		private readonly WpdbGateway $gateway,
		private readonly TableNames $tables,
		private readonly DatabaseDateTimeCodec $dates
	) {
	}

	/**
	 * Insert one opaque authentication challenge.
	 *
	 * @param NewAuthChallengeRecord $record New challenge data.
	 */
	public function insert( NewAuthChallengeRecord $record ): AuthChallengeRecord {
		$challenge_id = $this->gateway->insert(
			$this->tables->auth_challenges(),
			array(
				'purpose'          => $record->purpose,
				'subject_type'     => $record->subject_type,
				'subject_id'       => $record->subject_id,
				'email_ciphertext' => $record->email_ciphertext->value,
				'email_lookup'     => $record->email_lookup->value,
				'code_hash'        => $record->code_hash->value,
				'attempt_count'    => $record->attempt_count,
				'send_count'       => $record->send_count,
				'ip_hash'          => $record->ip_hash?->value,
				'expires_at'       => $this->dates->format( $record->expires_at ),
				'verified_at'      => $this->dates->format_nullable( $record->verified_at ),
				'consumed_at'      => $this->dates->format_nullable( $record->consumed_at ),
				'created_at'       => $this->dates->format( $record->created_at ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		return new AuthChallengeRecord( $challenge_id, $record );
	}

	/**
	 * Find one challenge by identifier.
	 *
	 * @param int $challenge_id Challenge identifier.
	 */
	public function find_by_id( int $challenge_id ): ?AuthChallengeRecord {
		RecordValidator::positive_id( $challenge_id, 'challenge_id' );
		$row = $this->gateway->row(
			'SELECT * FROM %i WHERE challenge_id = %d LIMIT 1',
			array( $this->tables->auth_challenges(), $challenge_id )
		);

		return null === $row ? null : $this->hydrate( $row );
	}

	/**
	 * Find the most recently created structural match.
	 *
	 * Application code remains responsible for expiry, attempts, verification,
	 * and consumption decisions.
	 *
	 * @param string       $purpose Challenge purpose.
	 * @param LookupDigest $email_lookup Keyed lookup digest.
	 */
	public function find_latest_for_purpose_and_lookup( string $purpose, LookupDigest $email_lookup ): ?AuthChallengeRecord {
		RecordValidator::ascii( $purpose, 32, 'purpose' );
		$row = $this->gateway->row(
			'SELECT * FROM %i WHERE purpose = %s AND email_lookup = %s ORDER BY created_at DESC, challenge_id DESC LIMIT 1',
			array( $this->tables->auth_challenges(), $purpose, $email_lookup->value )
		);

		return null === $row ? null : $this->hydrate( $row );
	}

	/**
	 * Map one strict stored challenge row.
	 *
	 * @param array<string, mixed> $row Stored row.
	 * @throws PersistenceMappingException When stored data violates the contract.
	 */
	private function hydrate( array $row ): AuthChallengeRecord {
		try {
			$ip_hash = StoredRow::nullable_string( $row, 'ip_hash' );

			return new AuthChallengeRecord(
				StoredRow::positive_int( $row, 'challenge_id' ),
				new NewAuthChallengeRecord(
					StoredRow::string( $row, 'purpose' ),
					StoredRow::string( $row, 'subject_type' ),
					StoredRow::string( $row, 'subject_id' ),
					EmailCiphertext::from_storage( StoredRow::string( $row, 'email_ciphertext' ) ),
					LookupDigest::from_storage( StoredRow::string( $row, 'email_lookup' ) ),
					OtpHash::from_storage( StoredRow::string( $row, 'code_hash' ) ),
					StoredRow::unsigned_int( $row, 'attempt_count' ),
					StoredRow::unsigned_int( $row, 'send_count' ),
					null === $ip_hash ? null : LookupDigest::from_storage( $ip_hash ),
					$this->dates->parse( StoredRow::string( $row, 'expires_at' ) ),
					$this->dates->parse_nullable( StoredRow::nullable_string( $row, 'verified_at' ) ),
					$this->dates->parse_nullable( StoredRow::nullable_string( $row, 'consumed_at' ) ),
					$this->dates->parse( StoredRow::string( $row, 'created_at' ) )
				)
			);
		} catch ( InvalidArgumentException ) {
			throw new PersistenceMappingException( 'Stored Authentication Challenge record is invalid.' );
		}
	}
}
