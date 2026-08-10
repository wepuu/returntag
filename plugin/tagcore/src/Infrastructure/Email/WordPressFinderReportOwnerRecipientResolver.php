<?php
/**
 * WordPress current-Owner recipient resolver.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\Email;

use ReturnTag\TagCore\Application\FinderReport\FinderReportOwnerRecipient;
use ReturnTag\TagCore\Application\FinderReport\FinderReportOwnerRecipientResolver;
use ReturnTag\TagCore\Application\PublicTag\PublicTagStateReader;
use ReturnTag\TagCore\Domain\Auth\EmailAddress;
use ReturnTag\TagCore\Domain\Tag\TagId;
use ReturnTag\TagCore\Domain\Tag\TagStatus;
use Throwable;

/** Resolves only the current active Owner from trusted server state. */
final readonly class WordPressFinderReportOwnerRecipientResolver implements FinderReportOwnerRecipientResolver {
	/**
	 * Create the resolver.
	 *
	 * @param PublicTagStateReader $tags Current Tag state reader.
	 */
	public function __construct( private PublicTagStateReader $tags ) {
	}

	/**
	 * Resolve the current Owner and validated WordPress email.
	 *
	 * @param TagId $tag_id Server-resolved Tag.
	 */
	public function resolve( TagId $tag_id ): ?FinderReportOwnerRecipient {
		$tag = $this->tags->find( $tag_id );

		if ( null === $tag || TagStatus::ACTIVE !== $tag->tag_status || null === $tag->owner_id ) {
			return null;
		}

		$user = get_userdata( $tag->owner_id );

		if ( false === $user ) {
			return null;
		}

		try {
			return new FinderReportOwnerRecipient( $tag->owner_id, new EmailAddress( $user->user_email ) );
		} catch ( Throwable ) {
			return null;
		}
	}
}
