<?php
/**
 * RT-315 Finder evidence review tests.
 *
 * @package ReturnTag\TagCore\Tests
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Tests\Unit\Application;

use PHPUnit\Framework\TestCase;
use ReturnTag\TagCore\Application\FinderReport\FinderEvidenceDerivative;
use ReturnTag\TagCore\Application\FinderReport\FinderEvidenceRejectedException;
use ReturnTag\TagCore\Application\FinderReport\FinderEvidenceSafetyReviewer;
use ReturnTag\TagCore\Application\FinderReport\FinderEvidenceSafetyUnavailableException;
use ReturnTag\TagCore\Application\FinderReport\ProcessedFinderEvidence;
use ReturnTag\TagCore\Application\FinderReport\ReviewFinderEvidence;
use ReturnTag\TagCore\Application\Persistence\Value\MediaDigest;
use ReturnTag\TagCore\Domain\FinderReport\FinderEvidenceMime;
use ReturnTag\TagCore\Domain\FinderReport\FinderEvidenceSafetyDecision;
use ReturnTag\TagCore\Infrastructure\Media\UnavailableFinderEvidenceSafetyReviewer;

/**
 * Verifies explicit approval and default-deny behavior.
 */
final class FinderEvidenceReviewTest extends TestCase {
	/** An explicit provider approval creates the only approved marker. */
	public function test_explicit_approval_creates_approved_marker(): void {
		$reviewer = new class() implements FinderEvidenceSafetyReviewer {
			/**
			 * Approve one synthetic derivative.
			 *
			 * @param FinderEvidenceDerivative $review_derivative Synthetic review bytes.
			 */
			public function review( FinderEvidenceDerivative $review_derivative ): FinderEvidenceSafetyDecision {
				TestCase::assertSame( 'review-bytes', $review_derivative->bytes );

				return FinderEvidenceSafetyDecision::APPROVED;
			}
		};
		$evidence = $this->evidence();

		self::assertSame( $evidence, ( new ReviewFinderEvidence( $reviewer ) )->review( $evidence )->evidence );
	}

	/** A provider rejection cannot be converted into ready evidence. */
	public function test_rejection_fails_closed(): void {
		$reviewer = new class() implements FinderEvidenceSafetyReviewer {
			/**
			 * Reject one synthetic derivative.
			 *
			 * @param FinderEvidenceDerivative $review_derivative Synthetic review bytes.
			 */
			public function review( FinderEvidenceDerivative $review_derivative ): FinderEvidenceSafetyDecision {
				unset( $review_derivative );

				return FinderEvidenceSafetyDecision::REJECTED;
			}
		};

		$this->expectException( FinderEvidenceRejectedException::class );
		( new ReviewFinderEvidence( $reviewer ) )->review( $this->evidence() );
	}

	/** Missing provider configuration must never approve evidence. */
	public function test_unavailable_provider_fails_closed(): void {
		$this->expectException( FinderEvidenceSafetyUnavailableException::class );
		( new ReviewFinderEvidence( new UnavailableFinderEvidenceSafetyReviewer() ) )->review( $this->evidence() );
	}

	/** Build one synthetic processed image without identity data. */
	private function evidence(): ProcessedFinderEvidence {
		return new ProcessedFinderEvidence(
			FinderEvidenceMime::JPEG,
			12,
			100,
			100,
			MediaDigest::from_digest( str_repeat( 'a', 64 ) ),
			FinderEvidenceDerivative::review( 'review-bytes', 100, 100 ),
			FinderEvidenceDerivative::email( 'email-bytes', 100, 100 )
		);
	}
}
