<?php
/**
 * Finder Report composition factory.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Infrastructure\FinderReport;

use ReturnTag\TagCore\Application\FinderReport\CleanupFinderReportEvidence;
use ReturnTag\TagCore\Application\FinderReport\DispatchFinderEmailOtp;
use ReturnTag\TagCore\Application\FinderReport\FinderEmailVerification;
use ReturnTag\TagCore\Application\FinderReport\ConvergeStaleFinderReportNotifications;
use ReturnTag\TagCore\Application\FinderReport\FinderEvidenceSafetyAvailability;
use ReturnTag\TagCore\Application\FinderReport\FinderEvidenceSafetyReviewer;
use ReturnTag\TagCore\Application\FinderReport\FinderReportEventIdentityPolicy;
use ReturnTag\TagCore\Application\FinderReport\ProcessFinderReportEvidence;
use ReturnTag\TagCore\Application\FinderReport\ReviewFinderEvidence;
use ReturnTag\TagCore\Application\FinderReport\NotifyFinderReportOwner;
use ReturnTag\TagCore\Application\FinderReport\SubmitFinderReport;
use ReturnTag\TagCore\Application\Persistence\DenyAllEventMetadataPolicy;
use ReturnTag\TagCore\Infrastructure\Media\GdFinderEvidenceImageProcessor;
use ReturnTag\TagCore\Infrastructure\Media\PrivateMediaSecrets;
use ReturnTag\TagCore\Infrastructure\Media\SodiumFilesystemPrivateMediaStorage;
use ReturnTag\TagCore\Infrastructure\Media\UnavailableFinderEvidenceSafetyReviewer;
use ReturnTag\TagCore\Infrastructure\Migration\TableNames;
use ReturnTag\TagCore\Infrastructure\Persistence\DatabaseDateTimeCodec;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbEventRepository;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbAuthChallengeRepository;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbConversationRepository;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbFinderEmailVerificationStore;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbFinderReportMediaRepository;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbFinderReportRepository;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbGateway;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbPublicTagStateReader;
use ReturnTag\TagCore\Infrastructure\Persistence\WpdbTransactionManager;
use ReturnTag\TagCore\Infrastructure\Email\WordPressFinderReportOwnerNotificationSender;
use ReturnTag\TagCore\Infrastructure\Email\WordPressFinderReportOwnerRecipientResolver;
use ReturnTag\TagCore\Infrastructure\Queue\ActionSchedulerFinderReportOwnerNotificationScheduler;
use ReturnTag\TagCore\Infrastructure\Queue\ActionSchedulerFinderReportProcessingScheduler;
use ReturnTag\TagCore\Infrastructure\Queue\ActionSchedulerFinderEmailOtpScheduler;
use ReturnTag\TagCore\Infrastructure\Random\PhpActivationOtpCodeGenerator;
use ReturnTag\TagCore\Infrastructure\RateLimit\WordPressOptionFinderReportRateLimiter;
use ReturnTag\TagCore\Infrastructure\RateLimit\WordPressOptionFinderEmailRateLimiter;
use ReturnTag\TagCore\Infrastructure\Security\FinderReportMessageSecrets;
use ReturnTag\TagCore\Infrastructure\Security\FinderEmailVerificationSecrets;
use ReturnTag\TagCore\Infrastructure\Security\SodiumFinderEmailProtector;
use ReturnTag\TagCore\Infrastructure\Security\SodiumFinderReportMessageProtector;
use ReturnTag\TagCore\Infrastructure\Email\WordPressFinderEmailOtpSender;
use ReturnTag\TagCore\Infrastructure\SystemClock;
use ReturnTag\TagCore\Infrastructure\Conversation\ConversationRelayRuntimeFactory;
use ReturnTag\TagCore\Infrastructure\WordPressOptionFeatureFlagReader;
use Throwable;
use wpdb;

/** Returns null unless every private-runtime prerequisite is valid. */
final class FinderReportRuntimeFactory {
	public const ROOT_NAME = 'RETURNTAG_TAGCORE_PRIVATE_MEDIA_ROOT';

	/**
	 * Compose Stage 3 intake and Stage 4 notification services for one site.
	 *
	 * @param wpdb $database Active database connection.
	 */
	public static function create( wpdb $database ): ?FinderReportRuntime {
		try {
			$root = self::read_root();
			$keys = PrivateMediaSecrets::load();
			$msg  = FinderReportMessageSecrets::load();

			$uploads      = wp_upload_dir( null, false );
			$public_roots = array( $uploads['basedir'] );
			$storage      = new SodiumFilesystemPrivateMediaStorage( $root, $keys, $public_roots );
			$processor    = new GdFinderEvidenceImageProcessor();
			$fallback     = new UnavailableFinderEvidenceSafetyReviewer();

			/**
			 * Select an approved Finder evidence reviewer supplied by trusted code.
			 *
			 * The default can never approve evidence. Implementations must also expose
			 * explicit availability so the public intake boundary can fail closed.
			 *
			 * @param mixed $fallback Default-deny reviewer.
			 */
			$reviewer = apply_filters( 'returntag_finder_evidence_safety_reviewer', $fallback );

			if ( ! $reviewer instanceof FinderEvidenceSafetyReviewer || ! $reviewer instanceof FinderEvidenceSafetyAvailability ) {
				return null;
			}

			$gateway       = new WpdbGateway( $database );
			$tables        = new TableNames( $database->prefix );
			$dates         = new DatabaseDateTimeCodec();
			$reports       = new WpdbFinderReportRepository( $gateway, $tables, $dates );
			$media         = new WpdbFinderReportMediaRepository( $gateway, $tables, $dates );
			$events        = new WpdbEventRepository(
				$gateway,
				$tables,
				$dates,
				new DenyAllEventMetadataPolicy(),
				new FinderReportEventIdentityPolicy()
			);
			$transactions  = new WpdbTransactionManager( $database );
			$clock         = new SystemClock();
			$scheduler     = new ActionSchedulerFinderReportProcessingScheduler();
			$notifications = new ActionSchedulerFinderReportOwnerNotificationScheduler();

			$process            = new ProcessFinderReportEvidence(
				$reports,
				$media,
				$storage,
				$processor,
				new ReviewFinderEvidence( $reviewer ),
				$events,
				$transactions,
				$clock
			);
			$cleanup            = new CleanupFinderReportEvidence(
				$media,
				$reports,
				$storage,
				$events,
				$transactions,
				$clock
			);
			$submit             = new SubmitFinderReport(
				new WpdbPublicTagStateReader( $gateway, $tables, $dates ),
				new WordPressOptionFeatureFlagReader(),
				$reviewer,
				new WordPressOptionFinderReportRateLimiter( $database, get_current_blog_id() ),
				$processor,
				$storage,
				new SodiumFinderReportMessageProtector( $msg ),
				$reports,
				$media,
				$events,
				$transactions,
				$scheduler,
				$clock
			);
			$notify             = new NotifyFinderReportOwner(
				new WordPressOptionFeatureFlagReader(),
				$reports,
				$media,
				$storage,
				new SodiumFinderReportMessageProtector( $msg ),
				new WordPressFinderReportOwnerRecipientResolver( new WpdbPublicTagStateReader( $gateway, $tables, $dates ) ),
				new WordPressFinderReportOwnerNotificationSender(),
				$events,
				$transactions,
				$clock
			);
			$converge           = new ConvergeStaleFinderReportNotifications( $reports, $events, $transactions, $clock );
			$email_verification = null;
			$email_dispatch     = null;
			$relay              = ConversationRelayRuntimeFactory::create( $database );
			try {
				$email_protector    = new SodiumFinderEmailProtector( FinderEmailVerificationSecrets::load() );
				$email_store        = new WpdbFinderEmailVerificationStore(
					$gateway,
					$tables,
					$dates,
					new WpdbAuthChallengeRepository( $gateway, $tables, $dates ),
					$transactions
				);
				$email_scheduler    = new ActionSchedulerFinderEmailOtpScheduler();
				$email_verification = new FinderEmailVerification(
					new WordPressOptionFeatureFlagReader(),
					$reports,
					new WpdbConversationRepository( $gateway, $tables, $dates ),
					$events,
					$email_store,
					$email_protector,
					new WordPressOptionFinderEmailRateLimiter( $database, get_current_blog_id() ),
					$email_scheduler,
					$clock,
					$relay?->ensure_access
				);
				$email_dispatch     = new DispatchFinderEmailOtp(
					new WordPressOptionFeatureFlagReader(),
					$email_store,
					$email_protector,
					new PhpActivationOtpCodeGenerator(),
					new WordPressFinderEmailOtpSender(),
					$clock
				);
			} catch ( Throwable ) {
				// Optional private continuation fails closed without disabling anonymous reports.
				$email_verification = null;
				$email_dispatch     = null;
			}

			return new FinderReportRuntime(
				$submit,
				$process,
				$cleanup,
				$media,
				$reports,
				$scheduler,
				$notify,
				$converge,
				$notifications,
				$reviewer,
				$email_verification,
				$email_dispatch,
				$relay?->ensure_access
			);
		} catch ( Throwable ) {
			return null;
		}
	}

	/**
	 * Read the absolute private root from external configuration.
	 *
	 * @throws \RuntimeException When configuration is absent.
	 */
	private static function read_root(): string {
		$value = defined( self::ROOT_NAME ) ? constant( self::ROOT_NAME ) : getenv( self::ROOT_NAME );

		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			throw new \RuntimeException( 'Finder Report private storage is unavailable.' );
		}

		return $value;
	}
}
