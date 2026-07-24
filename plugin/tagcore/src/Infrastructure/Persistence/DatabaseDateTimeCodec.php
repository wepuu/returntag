<?php
/**
 * UTC database datetime codec.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use ReturnTag\TagCore\Application\Persistence\Exception\PersistenceMappingException;
use ReturnTag\TagCore\Application\Persistence\RecordValidator;

/**
 * Converts strict UTC DateTime values without database timezone inference.
 */
final class DatabaseDateTimeCodec {
	private const FORMAT = 'Y-m-d H:i:s';

	/**
	 * Format one UTC datetime for a MySQL/MariaDB datetime column.
	 *
	 * @param DateTimeImmutable $value UTC datetime.
	 */
	public function format( DateTimeImmutable $value ): string {
		return RecordValidator::utc( $value, 'datetime' )->format( self::FORMAT );
	}

	/**
	 * Format one nullable UTC datetime.
	 *
	 * @param DateTimeImmutable|null $value UTC datetime.
	 */
	public function format_nullable( ?DateTimeImmutable $value ): ?string {
		return null === $value ? null : $this->format( $value );
	}

	/**
	 * Parse one strict database datetime as UTC.
	 *
	 * @param string $value Stored datetime.
	 * @throws PersistenceMappingException When the stored value is invalid.
	 */
	public function parse( string $value ): DateTimeImmutable {
		$parsed = DateTimeImmutable::createFromFormat( '!' . self::FORMAT, $value, new DateTimeZone( 'UTC' ) );
		$errors = DateTimeImmutable::getLastErrors();

		if (
			false === $parsed
			|| ( false !== $errors && ( 0 !== $errors['warning_count'] || 0 !== $errors['error_count'] ) )
			|| $value !== $parsed->format( self::FORMAT )
		) {
			throw new PersistenceMappingException( 'Stored datetime is invalid.' );
		}

		return $parsed;
	}

	/**
	 * Parse one nullable database datetime as UTC.
	 *
	 * @param string|null $value Stored datetime.
	 * @throws PersistenceMappingException When the stored value is invalid.
	 */
	public function parse_nullable( ?string $value ): ?DateTimeImmutable {
		return null === $value ? null : $this->parse( $value );
	}
}
