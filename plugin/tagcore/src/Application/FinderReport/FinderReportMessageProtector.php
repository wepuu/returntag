<?php
/**
 * Finder Report message encryption port.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Application\FinderReport;

use ReturnTag\TagCore\Application\Persistence\Value\FinderReportMessageCiphertext;
use ReturnTag\TagCore\Domain\Tag\TagId;

/** Keeps optional report plaintext outside persistence. */
interface FinderReportMessageProtector {
	/**
	 * Encrypt one validated message for a Tag-bound envelope.
	 *
	 * @param string $message Validated plaintext.
	 * @param TagId  $tag_id Associated Tag.
	 */
	public function encrypt( string $message, TagId $tag_id ): FinderReportMessageCiphertext;

	/**
	 * Decrypt one Tag-bound message for a later trusted use case.
	 *
	 * @param FinderReportMessageCiphertext $ciphertext Stored ciphertext.
	 * @param TagId                         $tag_id Associated Tag.
	 */
	public function decrypt( FinderReportMessageCiphertext $ciphertext, TagId $tag_id ): string;
}
