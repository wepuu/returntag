<?php
/**
 * Privacy-safe global Audit search normalization.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\Admin;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/** Normalizes the fixed global Audit Log filters. */
final class AuditEventSearchNormalizer {
	private const TARGETS = array( 'batch', 'tag', 'finder_report', 'conversation', 'user' );

	/**
	 * Normalize one exact bounded search request.
	 *
	 * @param array<string, mixed> $input External filters.
	 * @param DateTimeImmutable    $now Current UTC time.
	 * @return array<string, int|string>
	 * @throws InvalidArgumentException When a filter or time window is invalid.
	 */
	public function normalize( array $input, DateTimeImmutable $now ): array {
		$now  = $now->setTimezone( new DateTimeZone( 'UTC' ) );
		$to   = $this->time( $input['to'] ?? null ) ?? $now;
		$from = $this->time( $input['from'] ?? null ) ?? $to->sub( new DateInterval( 'PT24H' ) );
		if ( $from > $to || $from < $to->sub( new DateInterval( 'P31D' ) ) || $to > $now->add( new DateInterval( 'PT5M' ) ) ) {
			throw new InvalidArgumentException( 'Audit time window is invalid.' );
		}

		$criteria = array(
			'from' => $from->format( 'Y-m-d H:i:s' ),
			'to'   => $to->format( 'Y-m-d H:i:s' ),
		);
		if ( isset( $input['actor_user_id'] ) && '' !== $input['actor_user_id'] ) {
			$criteria['actor_user_id'] = $this->positive_id( $input['actor_user_id'] );
		}
		if ( isset( $input['target_type'] ) && '' !== $input['target_type'] ) {
			if ( ! is_string( $input['target_type'] ) || ! in_array( $input['target_type'], self::TARGETS, true ) ) {
				throw new InvalidArgumentException( 'Audit target is invalid.' );
			}
			$criteria['target_type'] = $input['target_type'];
		}
		if ( isset( $input['target_id'] ) && '' !== $input['target_id'] ) {
			if ( ! isset( $criteria['target_type'] ) || ! is_string( $input['target_id'] ) ) {
				throw new InvalidArgumentException( 'Audit target identifier is invalid.' );
			}
			if ( 'tag' === $criteria['target_type'] ) {
				if ( 1 !== preg_match( '/^[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{6}$/D', $input['target_id'] ) ) {
					throw new InvalidArgumentException( 'Audit target identifier is invalid.' );
				}
				$criteria['target_id'] = $input['target_id'];
			} else {
				$criteria['target_id'] = (string) $this->positive_id( $input['target_id'] );
			}
		}
		foreach ( array( 'event_type', 'result' ) as $key ) {
			if ( ! isset( $input[ $key ] ) || '' === $input[ $key ] ) {
				continue;
			}
			if ( ! is_string( $input[ $key ] ) || 1 !== preg_match( '/^[a-z][a-z0-9_]{0,63}$/D', $input[ $key ] ) ) {
				throw new InvalidArgumentException( 'Audit filter is invalid.' );
			}
			$criteria[ $key ] = $input[ $key ];
		}
		return $criteria;
	}

	/**
	 * Parse one strict UTC second boundary.
	 *
	 * @param mixed $value External timestamp.
	 * @throws InvalidArgumentException When the timestamp is invalid.
	 */
	private function time( mixed $value ): ?DateTimeImmutable {
		if ( null === $value || '' === $value ) {
			return null;
		}
		if ( ! is_string( $value ) || 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D', $value ) ) {
			throw new InvalidArgumentException( 'Audit time is invalid.' );
		}
		$date   = DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i:s\Z', $value, new DateTimeZone( 'UTC' ) );
		$errors = DateTimeImmutable::getLastErrors();
		if ( false === $date || ( is_array( $errors ) && ( $errors['warning_count'] || $errors['error_count'] ) ) ) {
			throw new InvalidArgumentException( 'Audit time is invalid.' );
		}
		return $date;
	}

	/**
	 * Parse one strict positive User ID.
	 *
	 * @param mixed $value External identifier.
	 * @throws InvalidArgumentException When the identifier is invalid.
	 */
	private function positive_id( mixed $value ): int {
		if ( is_int( $value ) && $value > 0 ) {
			return $value;
		}
		if ( ! is_string( $value ) || 1 !== preg_match( '/^[1-9][0-9]*$/D', $value ) ) {
			throw new InvalidArgumentException( 'Actor identifier is invalid.' );
		}
		return (int) $value;
	}
}
