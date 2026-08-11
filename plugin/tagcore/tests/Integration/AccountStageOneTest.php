<?php
/**
 * RT-317 Stage 1 Account rendering integration tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use ReturnTag\TagCore\Account\AccountFormResult;
use ReturnTag\TagCore\Account\AccountFormState;
use ReturnTag\TagCore\Account\AccountRoute;
use ReturnTag\TagCore\Account\AccountTemplateRenderer;
use ReturnTag\TagCore\Account\AccountUrlProvider;
use ReturnTag\TagCore\Application\Account\OwnerTagAccessState;
use ReturnTag\TagCore\Application\Account\OwnerTagCollection;
use ReturnTag\TagCore\Application\Account\OwnerTagDetail;
use ReturnTag\TagCore\Application\Persistence\Pagination\TagPage;
use ReturnTag\TagCore\Application\Persistence\Record\NewTagRecord;
use ReturnTag\TagCore\Application\Persistence\Record\TagRecord;
use ReturnTag\TagCore\Domain\Tag\TagStatus;
use ReturnTag\TagCore\Domain\Tag\TagType;
use WP_UnitTestCase;

/**
 * Verifies semantic sign-in, Owner-only fields, and generic unavailable views.
 */
final class AccountStageOneTest extends WP_UnitTestCase {
	/** Sign-in uses labelled passwordless controls and generic request feedback. */
	public function test_sign_in_form_is_semantic_and_non_enumerating(): void {
		$renderer = new AccountTemplateRenderer( RETURNTAG_TAGCORE_DIR, new AccountUrlProvider() );
		$html     = $renderer->render_to_string(
			AccountRoute::SIGN_IN,
			new AccountFormResult( AccountFormState::CODE_SENT, 'owner@example.test' )
		);

		self::assertStringContainsString( '<label for="returntag-account-code">', $html );
		self::assertStringContainsString( 'autocomplete="one-time-code"', $html );
		self::assertStringContainsString( 'If the address can receive a code', $html );
		self::assertStringNotContainsString( 'email exists', strtolower( $html ) );
	}

	/** Overview and detail expose approved Owner fields without identity metadata. */
	public function test_owner_views_render_approved_fields_only(): void {
		$renderer = new AccountTemplateRenderer( RETURNTAG_TAGCORE_DIR, new AccountUrlProvider() );
		$tag      = $this->tag();
		$overview = $renderer->render_to_string(
			AccountRoute::OVERVIEW,
			new AccountFormResult( AccountFormState::READY ),
			new OwnerTagCollection( OwnerTagAccessState::READY, new TagPage( array( $tag ), null ) )
		);
		$detail   = $renderer->render_to_string(
			AccountRoute::TAG,
			new AccountFormResult( AccountFormState::READY ),
			null,
			new OwnerTagDetail( OwnerTagAccessState::READY, $tag )
		);

		self::assertStringContainsString( 'Weekend carry-on', $overview );
		self::assertStringContainsString( 'Private item name', $detail );
		self::assertStringContainsString( 'Public label', $detail );
		self::assertStringContainsString( 'Please contact me through ForgeTag.', $detail );
		self::assertStringNotContainsString( 'owner@example.test', $detail );
		self::assertStringNotContainsString( 'owner_id', $detail );
	}

	/** Unknown and transferred candidates render the same generic unavailable copy. */
	public function test_unavailable_detail_does_not_disclose_tag_existence(): void {
		$html = ( new AccountTemplateRenderer( RETURNTAG_TAGCORE_DIR, new AccountUrlProvider() ) )->render_to_string(
			AccountRoute::TAG,
			new AccountFormResult( AccountFormState::READY ),
			null,
			new OwnerTagDetail( OwnerTagAccessState::UNAVAILABLE )
		);

		self::assertStringContainsString( 'Tag unavailable', $html );
		self::assertStringNotContainsString( 'transferred', strtolower( $html ) );
		self::assertStringNotContainsString( 'unknown', strtolower( $html ) );
	}

	/** Build one synthetic current-Owner Tag without real PII. */
	private function tag(): TagRecord {
		$time = new DateTimeImmutable( '2026-08-10 00:00:00', new DateTimeZone( 'UTC' ) );

		return new TagRecord(
			new NewTagRecord(
				'A7R2W9',
				1,
				42,
				TagType::CLASSIC_TAG,
				null,
				'Weekend carry-on',
				'Black suitcase',
				TagStatus::ACTIVE,
				true,
				'Please contact me through ForgeTag.',
				null,
				$time,
				$time,
				null,
				$time,
				$time
			)
		);
	}
}
