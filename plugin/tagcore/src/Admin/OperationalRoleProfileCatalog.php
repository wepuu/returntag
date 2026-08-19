<?php
/**
 * Fixed TagCore operational role profiles.
 *
 * @package ReturnTag\TagCore
 */

declare(strict_types=1);

namespace ReturnTag\TagCore\Admin;

/** Provides the immutable, least-privilege role contract. */
final class OperationalRoleProfileCatalog {
	/**
	 * Return the fixed role profiles.
	 *
	 * @return array<string, array{name: string, responsibility: string, capabilities: list<string>}>
	 */
	public function profiles(): array {
		$base = array( 'read', Capability::MANAGE_RETURNTAG );

		return array(
			'returntag_batch_operator'         => $this->profile( 'Batch Operator', 'Create, generate, export, and manage manufacturing batches.', $base, array( Capability::MANAGE_BATCHES ) ),
			'returntag_tag_operator'           => $this->profile( 'Tag Operator', 'Run exact, privacy-safe Tag support queries.', $base, array( Capability::MANAGE_TAGS ) ),
			'returntag_tag_lifecycle_operator' => $this->profile( 'Tag Lifecycle Operator', 'Query Tags and perform approved lifecycle changes.', $base, array( Capability::MANAGE_TAGS, Capability::MANAGE_TAG_LIFECYCLE ) ),
			'returntag_dispute_operator'       => $this->profile( 'Dispute Operator', 'Review Finder Reports and make approved evidence decisions.', $base, array( Capability::MANAGE_DISPUTES, Capability::MANAGE_FINDER_REPORT_DECISIONS ) ),
			'returntag_user_support'           => $this->profile( 'User Support', 'Run exact User and linked Tag support queries.', $base, array( Capability::VIEW_USERS, Capability::MANAGE_TAGS ) ),
			'returntag_audit_viewer'           => $this->profile( 'Audit Viewer', 'Search the metadata-free operational audit log.', $base, array( Capability::VIEW_AUDIT_LOGS ) ),
			'returntag_retention_operator'     => $this->profile( 'Retention Operator', 'Review retention health and request bounded cleanup runs.', $base, array( Capability::MANAGE_RETENTION ) ),
			'returntag_operations_manager'     => $this->profile(
				'Operations Manager',
				'Coordinate operational work without configuring roles or administering WordPress users.',
				$base,
				array(
					Capability::MANAGE_BATCHES,
					Capability::MANAGE_TAGS,
					Capability::MANAGE_TAG_LIFECYCLE,
					Capability::MANAGE_DISPUTES,
					Capability::MANAGE_FINDER_REPORT_DECISIONS,
					Capability::VIEW_USERS,
					Capability::VIEW_AUDIT_LOGS,
					Capability::MANAGE_RETENTION,
				)
			),
		);
	}

	/**
	 * Return every TagCore-owned capability.
	 *
	 * @return list<string>
	 */
	public function owned_capabilities(): array {
		return array(
			Capability::MANAGE_RETURNTAG,
			Capability::MANAGE_BATCHES,
			Capability::MANAGE_TAGS,
			Capability::MANAGE_TAG_LIFECYCLE,
			Capability::MANAGE_DISPUTES,
			Capability::MANAGE_FINDER_REPORT_DECISIONS,
			Capability::VIEW_USERS,
			Capability::VIEW_AUDIT_LOGS,
			Capability::MANAGE_ROLE_PROFILES,
			Capability::MANAGE_RETENTION,
		);
	}

	/**
	 * Build one fixed role profile.
	 *
	 * @param string             $name Role display name.
	 * @param string             $responsibility Fixed responsibility copy.
	 * @param array<int, string> $base Shared capabilities.
	 * @param array<int, string> $specific Responsibility-specific capabilities.
	 * @return array{name: string, responsibility: string, capabilities: list<string>}
	 */
	private function profile( string $name, string $responsibility, array $base, array $specific ): array {
		return array(
			'name'           => $name,
			'responsibility' => $responsibility,
			'capabilities'   => array_values( array_unique( array_merge( $base, $specific ) ) ),
		);
	}
}
