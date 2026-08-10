<?php
/**
 * Encrypted private-media storage port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\FinderReport;

use ReturnTag\TagCore\Domain\FinderReport\PrivateMediaObjectKind;

/**
 * Stores encrypted bytes without producing a URL or public attachment.
 */
interface PrivateMediaStorage {
	/**
	 * Encrypt and persist one private object.
	 *
	 * @param PrivateMediaObjectKind $kind Cryptographic object purpose.
	 * @param string                 $bytes Plaintext bytes held only in process memory.
	 */
	public function put( PrivateMediaObjectKind $kind, string $bytes ): PrivateMediaObject;

	/**
	 * Authenticate, decrypt, and verify one private object.
	 *
	 * @param PrivateMediaObjectKind $kind Expected object purpose.
	 * @param PrivateMediaObject     $stored_object Stored descriptor.
	 */
	public function read( PrivateMediaObjectKind $kind, PrivateMediaObject $stored_object ): string;

	/**
	 * Idempotently remove one private object.
	 *
	 * @param PrivateMediaObjectKind $kind Expected object purpose.
	 * @param PrivateMediaObject     $stored_object Stored descriptor.
	 */
	public function delete( PrivateMediaObjectKind $kind, PrivateMediaObject $stored_object ): void;
}
