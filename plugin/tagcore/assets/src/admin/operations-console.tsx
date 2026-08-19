import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Modal,
	Notice,
	RadioControl,
	SelectControl,
	Spinner,
	TextControl,
} from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

export interface OperationsConfig {
	restPath: string;
	tagsUrl: string;
	finderReportsUrl: string;
	usersUrl: string;
	roleProfilesUrl: string;
	auditLogUrl: string;
	retentionUrl: string;
	wordpressUsersUrl: string | null;
	roleProfiles: RoleProfile[];
	surface:
		| 'batches'
		| 'tags'
		| 'finder_reports'
		| 'users'
		| 'role_profiles'
		| 'audit_log'
		| 'retention';
	canManageTags: boolean;
	canManageTagLifecycle: boolean;
	canManageDisputes: boolean;
	canManageFinderReportDecisions: boolean;
	canViewUsers: boolean;
	canViewAudit: boolean;
	canManageRoleProfiles: boolean;
	canManageRetention: boolean;
}

interface RoleProfile {
	slug: string;
	name: string;
	responsibility: string;
	capabilities: string[];
	userCount: number;
}

interface ApiError {
	message?: string;
}

type RecordValue =
	| string
	| number
	| boolean
	| null
	| string[]
	| Record< string, number >;
type OperationRecord = Record< string, RecordValue > & { audit?: AuditEntry[] };
interface AuditEntry {
	event_id: number;
	event_type: string;
	actor_type: string;
	actor_id: number | null;
	event_result: string;
	created_at: string;
}

function OperationsNav( { config }: { config: OperationsConfig } ) {
	return (
		<nav
			className="returntag-operations-nav"
			aria-label={ __( 'Operations console', 'tagcore' ) }
		>
			{ config.canManageTags && (
				<a
					className={ config.surface === 'tags' ? 'is-current' : '' }
					href={ config.tagsUrl }
				>
					{ __( 'Tags', 'tagcore' ) }
				</a>
			) }
			{ config.canManageDisputes && (
				<a
					className={
						config.surface === 'finder_reports' ? 'is-current' : ''
					}
					href={ config.finderReportsUrl }
				>
					{ __( 'Finder Reports', 'tagcore' ) }
				</a>
			) }
			{ config.canViewUsers && (
				<a
					className={ config.surface === 'users' ? 'is-current' : '' }
					href={ config.usersUrl }
				>
					{ __( 'Users', 'tagcore' ) }
				</a>
			) }
			{ config.canManageRoleProfiles && (
				<a
					className={
						config.surface === 'role_profiles' ? 'is-current' : ''
					}
					href={ config.roleProfilesUrl }
				>
					{ __( 'Role Profiles', 'tagcore' ) }
				</a>
			) }
			{ config.canViewAudit && (
				<a
					className={
						config.surface === 'audit_log' ? 'is-current' : ''
					}
					href={ config.auditLogUrl }
				>
					{ __( 'Audit Log', 'tagcore' ) }
				</a>
			) }
			{ config.canManageRetention && (
				<a
					className={
						config.surface === 'retention' ? 'is-current' : ''
					}
					href={ config.retentionUrl }
				>
					{ __( 'Retention', 'tagcore' ) }
				</a>
			) }
		</nav>
	);
}

function PageHeader( {
	title,
	description,
	config,
	titleId,
}: {
	title: string;
	description: string;
	config: OperationsConfig;
	titleId?: string;
} ) {
	let modeLabel: string = __( 'Read only', 'tagcore' );
	if (
		( config.surface === 'tags' && config.canManageTagLifecycle ) ||
		( config.surface === 'finder_reports' &&
			config.canManageFinderReportDecisions )
	) {
		modeLabel = __( 'Controlled changes', 'tagcore' );
	} else if ( config.surface === 'retention' ) {
		modeLabel = __( 'Guarded runs', 'tagcore' );
	} else if ( config.surface === 'role_profiles' ) {
		modeLabel = __( 'Fixed profiles', 'tagcore' );
	}

	return (
		<>
			<header className="returntag-page-header">
				<div>
					<h1 id={ titleId }>{ title }</h1>
					<p>{ description }</p>
				</div>
				<span className="returntag-read-only">{ modeLabel }</span>
			</header>
			<OperationsNav config={ config } />
		</>
	);
}

function ErrorNotice( { message }: { message: string | null } ) {
	return message ? (
		<Notice status="error" isDismissible={ false }>
			{ message }
		</Notice>
	) : null;
}

function Busy() {
	return (
		<div className="returntag-loading" aria-live="polite">
			<Spinner />
			<span>{ __( 'Loading secure results…', 'tagcore' ) }</span>
		</div>
	);
}

function formatKey( key: string ): string {
	return key
		.replace( /_/g, ' ' )
		.replace( /^./, ( value ) => value.toUpperCase() )
		.replace( /\bid\b/gi, 'ID' );
}

function formatValue( value: RecordValue | AuditEntry[] | undefined ): string {
	if ( value === null || value === undefined || value === '' ) {
		return '—';
	}
	if ( typeof value === 'boolean' ) {
		return value ? __( 'Yes', 'tagcore' ) : __( 'No', 'tagcore' );
	}
	if ( Array.isArray( value ) ) {
		return value.join( ', ' );
	}
	if ( typeof value === 'object' ) {
		return Object.entries( value )
			.map( ( [ key, count ] ) => `${ key }: ${ count }` )
			.join( ', ' );
	}
	return String( value );
}

function AuditTimeline( { entries }: { entries?: AuditEntry[] } ) {
	if ( ! entries ) {
		return null;
	}
	return (
		<section
			className="returntag-audit"
			aria-labelledby="returntag-audit-title"
		>
			<h3 id="returntag-audit-title">
				{ __( 'Audit timeline', 'tagcore' ) }
			</h3>
			{ entries.length === 0 ? (
				<p>{ __( 'No audit events are available.', 'tagcore' ) }</p>
			) : (
				<ol>
					{ entries.map( ( entry ) => (
						<li key={ entry.event_id }>
							<strong>{ formatKey( entry.event_type ) }</strong>
							<span>
								{ entry.created_at } · { entry.event_result }
							</span>
						</li>
					) ) }
				</ol>
			) }
		</section>
	);
}

function SafeDetail( {
	record,
	exclude = [],
}: {
	record: OperationRecord;
	exclude?: string[];
} ) {
	return (
		<div className="returntag-operation-detail">
			<dl>
				{ Object.entries( record )
					.filter(
						( [ key ] ) =>
							key !== 'audit' && ! exclude.includes( key )
					)
					.map( ( [ key, value ] ) => (
						<div key={ key }>
							<dt>{ formatKey( key ) }</dt>
							<dd>{ formatValue( value ) }</dd>
						</div>
					) ) }
			</dl>
			<AuditTimeline entries={ record.audit } />
		</div>
	);
}

function ResultTable( {
	items,
	columns,
	onOpen,
}: {
	items: OperationRecord[];
	columns: string[];
	onOpen: ( item: OperationRecord ) => void;
} ) {
	if ( items.length === 0 ) {
		return (
			<div className="returntag-empty">
				<h3>{ __( 'No matching records', 'tagcore' ) }</h3>
				<p>
					{ __(
						'Check the complete identifier and search again.',
						'tagcore'
					) }
				</p>
			</div>
		);
	}
	return (
		<div className="returntag-table-wrap">
			<table className="widefat striped returntag-operation-table">
				<thead>
					<tr>
						{ columns.map( ( column ) => (
							<th scope="col" key={ column }>
								{ formatKey( column ) }
							</th>
						) ) }
						<th scope="col">{ __( 'Details', 'tagcore' ) }</th>
					</tr>
				</thead>
				<tbody>
					{ items.map( ( item, index ) => (
						<tr
							key={ String(
								item.tag_id ??
									item.finder_report_id ??
									item.user_id ??
									index
							) }
						>
							{ columns.map( ( column ) => (
								<td
									key={ column }
									data-label={ formatKey( column ) }
								>
									{ formatValue( item[ column ] ) }
								</td>
							) ) }
							<td data-label={ __( 'Details', 'tagcore' ) }>
								<Button
									variant="secondary"
									onClick={ () => onOpen( item ) }
								>
									{ __( 'Open', 'tagcore' ) }
								</Button>
							</td>
						</tr>
					) ) }
				</tbody>
			</table>
		</div>
	);
}

type TagLifecycleAction =
	| 'suspend'
	| 'retire'
	| 'remove-owner'
	| 'transfer-owner';

interface LifecycleChoice {
	action: TagLifecycleAction;
	title: string;
	description: string;
	button: string;
}

function TagLifecycleDangerZone( {
	config,
	tag,
	onCommitted,
}: {
	config: OperationsConfig;
	tag: OperationRecord;
	onCommitted: () => Promise< void >;
} ) {
	const tagId = String( tag.tag_id );
	const status = String( tag.tag_status );
	const ownerId = typeof tag.owner_id === 'number' ? tag.owner_id : null;
	const [ choice, setChoice ] = useState< LifecycleChoice | null >( null );
	const [ confirmation, setConfirmation ] = useState( '' );
	const [ targetUserId, setTargetUserId ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState< string | null >( null );
	const [ success, setSuccess ] = useState< string | null >( null );
	const feedbackRef = useRef< HTMLDivElement | null >( null );

	useEffect( () => {
		if ( error || success ) {
			feedbackRef.current?.focus();
		}
	}, [ error, success ] );

	const choices: LifecycleChoice[] = [
		{
			action: 'suspend',
			title: __( 'Suspend Tag', 'tagcore' ),
			description: __(
				'Block activation and new recovery conversations while preserving the current Owner.',
				'tagcore'
			),
			button: __( 'Suspend Tag', 'tagcore' ),
		},
		{
			action: 'retire',
			title: __( 'Retire Tag permanently', 'tagcore' ),
			description: __(
				'Permanently retire this Tag. This action cannot be reversed.',
				'tagcore'
			),
			button: __( 'Retire Tag', 'tagcore' ),
		},
		{
			action: 'remove-owner',
			title: __( 'Remove Owner and suspend', 'tagcore' ),
			description: __(
				'Remove the current Owner and keep the Tag suspended. Public activation will remain unavailable.',
				'tagcore'
			),
			button: __( 'Remove Owner', 'tagcore' ),
		},
		{
			action: 'transfer-owner',
			title: __( 'Transfer Owner', 'tagcore' ),
			description: __(
				'Transfer to an existing exact User ID without changing the current Tag status.',
				'tagcore'
			),
			button: __( 'Transfer Owner', 'tagcore' ),
		},
	];

	const eligible = ( action: TagLifecycleAction ) => {
		if ( action === 'suspend' ) {
			return status === 'unregistered' || status === 'active';
		}
		if ( action === 'retire' ) {
			return status !== 'retired';
		}
		return (
			ownerId !== null &&
			( status === 'active' || status === 'suspended' )
		);
	};

	const close = () => {
		setChoice( null );
		setConfirmation( '' );
		setTargetUserId( '' );
		setError( null );
	};

	const submit = async () => {
		if ( ! choice ) {
			return;
		}
		setBusy( true );
		setError( null );
		setSuccess( null );
		try {
			await apiFetch( {
				path: `${ config.restPath }/admin/tags/${ encodeURIComponent(
					tagId
				) }/${ choice.action }`,
				method: 'POST',
				data: {
					confirmation,
					expected_status: status,
					expected_owner_id: ownerId,
					...( choice.action === 'transfer-owner'
						? { target_user_id: targetUserId }
						: {} ),
				},
			} );
			await onCommitted();
			setSuccess(
				sprintf(
					/* translators: %s: Tag ID. */
					__(
						'Tag %s was updated from committed server state.',
						'tagcore'
					),
					tagId
				)
			);
			close();
		} catch ( reason ) {
			setError(
				( reason as ApiError ).message ??
					__(
						'This Tag could not be changed. Reload and try again.',
						'tagcore'
					)
			);
		} finally {
			setBusy( false );
		}
	};

	return (
		<section
			className="returntag-danger-zone"
			aria-labelledby="returntag-danger-title"
		>
			<h2 id="returntag-danger-title">
				{ __( 'Danger Zone', 'tagcore' ) }
			</h2>
			<p>
				{ __(
					'Every action is atomic, closes active recovery conversations, revokes secure access, cancels pending transfers, and records an audit event.',
					'tagcore'
				) }
			</p>
			{ success && (
				<div ref={ feedbackRef } tabIndex={ -1 } aria-live="polite">
					<Notice status="success" isDismissible={ false }>
						{ success }
					</Notice>
				</div>
			) }
			<div className="returntag-danger-actions">
				{ choices.map( ( item ) => (
					<Button
						key={ item.action }
						variant="secondary"
						isDestructive
						disabled={ ! eligible( item.action ) }
						onClick={ () => {
							setChoice( item );
							setError( null );
							setSuccess( null );
						} }
					>
						{ item.button }
					</Button>
				) ) }
			</div>
			{ choice && (
				<Modal title={ choice.title } onRequestClose={ close }>
					{ error && (
						<div
							ref={ feedbackRef }
							tabIndex={ -1 }
							aria-live="assertive"
						>
							<ErrorNotice message={ error } />
						</div>
					) }
					<p>{ choice.description }</p>
					<dl className="returntag-confirm-facts">
						<div>
							<dt>{ __( 'Current status', 'tagcore' ) }</dt>
							<dd>{ status }</dd>
						</div>
						<div>
							<dt>
								{ __( 'Current Owner User ID', 'tagcore' ) }
							</dt>
							<dd>{ ownerId ?? __( 'None', 'tagcore' ) }</dd>
						</div>
						<div>
							<dt>{ __( 'Access revocation', 'tagcore' ) }</dt>
							<dd>
								{ __(
									'Conversations, secure links, queued delivery, notifications, and pending transfers',
									'tagcore'
								) }
							</dd>
						</div>
					</dl>
					{ choice.action === 'transfer-owner' && (
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Target User ID', 'tagcore' ) }
							value={ targetUserId }
							onChange={ setTargetUserId }
						/>
					) }
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ sprintf(
							/* translators: %s: exact Tag ID. */
							__( 'Type %s to confirm', 'tagcore' ),
							tagId
						) }
						value={ confirmation }
						onChange={ setConfirmation }
					/>
					<div className="returntag-modal-actions">
						<Button
							variant="tertiary"
							onClick={ close }
							disabled={ busy }
						>
							{ __( 'Cancel', 'tagcore' ) }
						</Button>
						<Button
							variant="primary"
							isDestructive
							isBusy={ busy }
							disabled={
								busy ||
								confirmation !== tagId ||
								( choice.action === 'transfer-owner' &&
									! /^[1-9][0-9]*$/.test( targetUserId ) )
							}
							onClick={ () => void submit() }
						>
							{ choice.button }
						</Button>
					</div>
				</Modal>
			) }
		</section>
	);
}

function TagsConsole( { config }: { config: OperationsConfig } ) {
	const [ mode, setMode ] = useState( 'tag_id' );
	const [ value, setValue ] = useState( '' );
	const [ tagType, setTagType ] = useState<
		'' | 'sticker' | 'classic_tag' | 'smart_tag'
	>( '' );
	const [ status, setStatus ] = useState( '' );
	const [ lostMode, setLostMode ] = useState< '' | '0' | '1' >( '' );
	const [ activatedFrom, setActivatedFrom ] = useState( '' );
	const [ activatedTo, setActivatedTo ] = useState( '' );
	const [ items, setItems ] = useState< OperationRecord[] >( [] );
	const [ nextCursor, setNextCursor ] = useState< string | null >( null );
	const [ detail, setDetail ] = useState< OperationRecord | null >( null );
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState< string | null >( null );
	useEffect( () => {
		const ownerId = new URLSearchParams( window.location.search ).get(
			'owner_user_id'
		);
		if ( ownerId && /^[1-9][0-9]*$/.test( ownerId ) ) {
			setMode( 'owner_id' );
			setValue( ownerId );
		}
	}, [] );

	const search = async ( cursor: string | null = null ) => {
		setLoading( true );
		setError( null );
		if ( ! cursor ) {
			setDetail( null );
			setItems( [] );
		}
		const key = mode === 'batch' ? 'batch_code' : mode;
		try {
			const response = await apiFetch< {
				items: OperationRecord[];
				next_cursor: string | null;
			} >( {
				path: `${ config.restPath }/admin/tags/search`,
				method: 'POST',
				data: {
					mode,
					[ key ]: value,
					tag_type: tagType,
					tag_status: status,
					lost_mode: lostMode,
					activated_from: activatedFrom,
					activated_to: activatedTo,
					per_page: 50,
					cursor,
				},
			} );
			setItems( ( current ) =>
				cursor ? [ ...current, ...response.items ] : response.items
			);
			setNextCursor( response.next_cursor );
		} catch ( reason ) {
			if ( ! cursor ) {
				setItems( [] );
			}
			setNextCursor( null );
			setError(
				( reason as ApiError ).message ??
					__( 'Tag search failed.', 'tagcore' )
			);
		} finally {
			setLoading( false );
		}
	};
	const submit = ( event: React.FormEvent ) => {
		event.preventDefault();
		void search();
	};

	const loadDetail = async ( tagId: string ) => {
		setDetail(
			await apiFetch< OperationRecord >( {
				path: `${ config.restPath }/admin/tags/${ encodeURIComponent(
					tagId
				) }`,
			} )
		);
	};

	const open = async ( item: OperationRecord ) => {
		setLoading( true );
		setError( null );
		try {
			await loadDetail( String( item.tag_id ) );
		} catch ( reason ) {
			setError(
				( reason as ApiError ).message ??
					__( 'Tag detail is unavailable.', 'tagcore' )
			);
		} finally {
			setLoading( false );
		}
	};

	return (
		<section>
			<PageHeader
				title={ __( 'Tags', 'tagcore' ) }
				description={ __(
					'Find a Tag by an exact Tag ID, Batch Code, Owner User ID, or complete Owner email.',
					'tagcore'
				) }
				config={ config }
			/>
			<form
				className="returntag-operation-search"
				onSubmit={ submit }
				noValidate
			>
				<RadioControl
					label={ __( 'Exact anchor', 'tagcore' ) }
					selected={ mode }
					options={ [
						{ label: __( 'Tag ID', 'tagcore' ), value: 'tag_id' },
						{
							label: __( 'Batch Code', 'tagcore' ),
							value: 'batch',
						},
						{
							label: __( 'Owner User ID', 'tagcore' ),
							value: 'owner_id',
						},
						{
							label: __( 'Owner email', 'tagcore' ),
							value: 'owner_email',
						},
					] }
					onChange={ ( next ) => {
						setMode( next );
						setValue( '' );
						setItems( [] );
						setNextCursor( null );
						setDetail( null );
					} }
				/>
				<div className="returntag-operation-fields">
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={
							mode === 'owner_email'
								? __( 'Complete email address', 'tagcore' )
								: formatKey(
										mode === 'batch' ? 'batch_code' : mode
								  )
						}
						type={ mode === 'owner_email' ? 'email' : 'text' }
						value={ value }
						onChange={ setValue }
					/>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Tag type', 'tagcore' ) }
						value={ tagType }
						options={ [
							{ value: '', label: __( 'All types', 'tagcore' ) },
							{
								value: 'sticker',
								label: __( 'Sticker', 'tagcore' ),
							},
							{
								value: 'classic_tag',
								label: __( 'Classic Tag', 'tagcore' ),
							},
							{
								value: 'smart_tag',
								label: __( 'Smart Tag', 'tagcore' ),
							},
						] }
						onChange={ setTagType }
					/>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Tag status', 'tagcore' ) }
						value={ status }
						options={ [
							[ '', __( 'All statuses', 'tagcore' ) ],
							[ 'unregistered', __( 'Unregistered', 'tagcore' ) ],
							[ 'active', __( 'Active', 'tagcore' ) ],
							[ 'suspended', __( 'Suspended', 'tagcore' ) ],
							[ 'retired', __( 'Retired', 'tagcore' ) ],
						].map( ( [ v, label ] ) => ( { value: v, label } ) ) }
						onChange={ setStatus }
					/>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Lost Mode', 'tagcore' ) }
						value={ lostMode }
						options={ [
							{ value: '', label: __( 'Any', 'tagcore' ) },
							{ value: '1', label: __( 'On', 'tagcore' ) },
							{ value: '0', label: __( 'Off', 'tagcore' ) },
						] }
						onChange={ ( next ) =>
							setLostMode( next as '' | '0' | '1' )
						}
					/>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Activated from', 'tagcore' ) }
						type="date"
						value={ activatedFrom }
						onChange={ setActivatedFrom }
					/>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Activated to', 'tagcore' ) }
						type="date"
						value={ activatedTo }
						onChange={ setActivatedTo }
					/>
				</div>
				<Button
					type="submit"
					variant="primary"
					isBusy={ loading }
					disabled={ loading || value.trim() === '' }
				>
					{ __( 'Search tags', 'tagcore' ) }
				</Button>
			</form>
			<ErrorNotice message={ error } />
			{ loading && <Busy /> }
			{ detail ? (
				<>
					<Button
						variant="tertiary"
						onClick={ () => setDetail( null ) }
					>
						{ __( 'Back to results', 'tagcore' ) }
					</Button>
					<SafeDetail record={ detail } />
					{ config.canManageTagLifecycle && (
						<TagLifecycleDangerZone
							config={ config }
							tag={ detail }
							onCommitted={ () =>
								loadDetail( String( detail.tag_id ) )
							}
						/>
					) }
					{ config.canViewUsers &&
						typeof detail.owner_id === 'number' && (
							<div className="returntag-cross-links">
								<a
									className="components-button is-secondary"
									href={ `${ config.usersUrl }&user_id=${ detail.owner_id }` }
								>
									{ __(
										'Open Owner support view',
										'tagcore'
									) }
								</a>
							</div>
						) }
				</>
			) : (
				<>
					<ResultTable
						items={ items }
						columns={ [
							'tag_id',
							'tag_status',
							'batch_code',
							'tag_type',
							'lost_mode',
							'finder_report_count',
						] }
						onOpen={ open }
					/>
					{ nextCursor && (
						<Button
							variant="secondary"
							isBusy={ loading }
							disabled={ loading }
							onClick={ () => void search( nextCursor ) }
						>
							{ __( 'Load more Tags', 'tagcore' ) }
						</Button>
					) }
				</>
			) }
		</section>
	);
}

type FinderDecisionAction =
	| 'place-hold'
	| 'release-hold'
	| 'resolve-no-action'
	| 'block';

function FinderReportReviewPanel( {
	config,
	report,
	onCommitted,
}: {
	config: OperationsConfig;
	report: OperationRecord;
	onCommitted: () => Promise< void >;
} ) {
	const reportId = Number( report.finder_report_id );
	const [ action, setAction ] = useState< FinderDecisionAction | null >(
		null
	);
	const [ confirmation, setConfirmation ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState< string | null >( null );
	const [ success, setSuccess ] = useState< string | null >( null );
	const feedbackRef = useRef< HTMLDivElement | null >( null );
	const activeHold = Boolean( report.hold_active );
	const eligible =
		[ 'ready', 'notified' ].includes( String( report.report_status ) ) &&
		report.evidence_status === 'ready';

	useEffect( () => {
		if ( error || success ) {
			feedbackRef.current?.focus();
		}
	}, [ error, success ] );

	const labels: Record< FinderDecisionAction, string > = {
		'place-hold': __( 'Place 90-day hold', 'tagcore' ),
		'release-hold': __( 'Release evidence hold', 'tagcore' ),
		'resolve-no-action': __( 'Resolve with no action', 'tagcore' ),
		block: __( 'Block Finder Report', 'tagcore' ),
	};
	const submit = async () => {
		if ( ! action ) {
			return;
		}
		setBusy( true );
		setError( null );
		setSuccess( null );
		try {
			await apiFetch( {
				path: `${ config.restPath }/admin/finder-reports/${ reportId }/${ action }`,
				method: 'POST',
				data: {
					confirmation,
					expected_report_status: report.report_status,
					expected_evidence_status: report.evidence_status,
					expected_notification_status: report.notification_status,
					expected_has_conversation: report.has_conversation,
					expected_expires_at: report.expires_at,
					expected_retention_until: report.retention_until,
					expected_hold_until: report.hold_until,
					expected_has_review_evidence: report.has_review_evidence,
				},
			} );
			setConfirmation( '' );
			setAction( null );
			await onCommitted();
			setSuccess(
				__(
					'The committed Finder Report state was reloaded.',
					'tagcore'
				)
			);
		} catch ( reason ) {
			setError(
				( reason as ApiError ).message ??
					__(
						'The secure decision could not be completed.',
						'tagcore'
					)
			);
		} finally {
			setBusy( false );
		}
	};

	return (
		<section
			className="returntag-review-panel"
			aria-labelledby="returntag-review-title"
		>
			<h3 id="returntag-review-title">
				{ __( 'Evidence custody and review', 'tagcore' ) }
			</h3>
			<div
				className={ `returntag-custody-strip ${
					activeHold ? 'has-hold' : ''
				}` }
			>
				<strong>
					{ activeHold
						? __( 'Evidence hold active', 'tagcore' )
						: __( 'Standard retention', 'tagcore' ) }
				</strong>
				<span>
					{ activeHold
						? sprintf(
								/* translators: %s: UTC evidence-hold expiry. */
								__( 'Held until %s', 'tagcore' ),
								String( report.hold_until )
						  )
						: sprintf(
								/* translators: %s: UTC ordinary evidence-retention expiry. */
								__( 'Retained until %s', 'tagcore' ),
								String( report.retention_until )
						  ) }
				</span>
			</div>
			<div ref={ feedbackRef } tabIndex={ -1 } aria-live="polite">
				<ErrorNotice message={ error } />
				{ success && (
					<Notice status="success" isDismissible={ false }>
						{ success }
					</Notice>
				) }
			</div>
			<p>
				{ __(
					'Every decision is audited. Hold duration is fixed by policy; no finder identity or original file is exposed.',
					'tagcore'
				) }
			</p>
			<div className="returntag-review-actions">
				<Button
					variant="secondary"
					disabled={ ! eligible || activeHold || busy }
					onClick={ () => setAction( 'place-hold' ) }
				>
					{ labels[ 'place-hold' ] }
				</Button>
				<Button
					variant="secondary"
					disabled={ ! eligible || ! activeHold || busy }
					onClick={ () => setAction( 'release-hold' ) }
				>
					{ labels[ 'release-hold' ] }
				</Button>
				<Button
					variant="secondary"
					disabled={ ! eligible || ! activeHold || busy }
					onClick={ () => setAction( 'resolve-no-action' ) }
				>
					{ labels[ 'resolve-no-action' ] }
				</Button>
				<Button
					variant="secondary"
					isDestructive
					disabled={ ! eligible || busy }
					onClick={ () => setAction( 'block' ) }
				>
					{ labels.block }
				</Button>
			</div>
			{ action && (
				<Modal
					title={ labels[ action ] }
					onRequestClose={ () => {
						setAction( null );
						setConfirmation( '' );
					} }
				>
					<Notice
						status={ action === 'block' ? 'error' : 'warning' }
						isDismissible={ false }
					>
						{ action === 'block'
							? __(
									'Blocking is irreversible. It blocks the linked conversation, revokes access tokens, fails queued messages and applies a 90-day evidence hold.',
									'tagcore'
							  )
							: __(
									'This changes evidence custody state and creates a permanent audit event.',
									'tagcore'
							  ) }
					</Notice>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ sprintf(
							/* translators: %s: numeric Finder Report ID that the operator must retype. */
							__( 'Type Report ID %s to confirm', 'tagcore' ),
							String( reportId )
						) }
						value={ confirmation }
						onChange={ setConfirmation }
					/>
					<div className="returntag-modal-actions">
						<Button
							variant="tertiary"
							onClick={ () => {
								setAction( null );
								setConfirmation( '' );
							} }
						>
							{ __( 'Cancel', 'tagcore' ) }
						</Button>
						<Button
							variant="primary"
							isDestructive={ action === 'block' }
							isBusy={ busy }
							disabled={
								busy || confirmation !== String( reportId )
							}
							onClick={ () => void submit() }
						>
							{ labels[ action ] }
						</Button>
					</div>
				</Modal>
			) }
		</section>
	);
}

function FinderReportsConsole( { config }: { config: OperationsConfig } ) {
	const [ mode, setMode ] = useState( 'report_id' );
	const [ value, setValue ] = useState( '' );
	const [ reportStatus, setReportStatus ] = useState( '' );
	const [ evidenceStatus, setEvidenceStatus ] = useState( '' );
	const [ notificationStatus, setNotificationStatus ] = useState( '' );
	const [ createdFrom, setCreatedFrom ] = useState( '' );
	const [ createdTo, setCreatedTo ] = useState( '' );
	const [ items, setItems ] = useState< OperationRecord[] >( [] );
	const [ nextCursor, setNextCursor ] = useState< string | null >( null );
	const [ detail, setDetail ] = useState< OperationRecord | null >( null );
	const [ message, setMessage ] = useState< string | null >( null );
	const [ evidenceUrl, setEvidenceUrl ] = useState< string | null >( null );
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState< string | null >( null );
	const imageRef = useRef< HTMLImageElement | null >( null );
	useEffect(
		() => () => {
			if ( evidenceUrl ) {
				URL.revokeObjectURL( evidenceUrl );
			}
		},
		[ evidenceUrl ]
	);
	const clearSensitive = () => {
		setMessage( null );
		setEvidenceUrl( ( current ) => {
			if ( current ) {
				URL.revokeObjectURL( current );
			}
			return null;
		} );
	};
	const loadDetail = async ( reportId: number ) => {
		setDetail(
			await apiFetch< OperationRecord >( {
				path: `${ config.restPath }/admin/finder-reports/${ reportId }`,
			} )
		);
	};
	const search = async ( cursor: string | null = null ) => {
		setLoading( true );
		setError( null );
		if ( ! cursor ) {
			setDetail( null );
			clearSensitive();
		}
		const key = mode === 'report_id' ? 'finder_report_id' : mode;
		try {
			const response = await apiFetch< {
				items: OperationRecord[];
				next_cursor: string | null;
			} >( {
				path: `${ config.restPath }/admin/finder-reports/search`,
				method: 'POST',
				data: {
					mode,
					[ key ]: value,
					report_status: reportStatus,
					evidence_status: evidenceStatus,
					owner_notification_status: notificationStatus,
					created_from: createdFrom,
					created_to: createdTo,
					per_page: 50,
					cursor,
				},
			} );
			setItems( ( current ) =>
				cursor ? [ ...current, ...response.items ] : response.items
			);
			setNextCursor( response.next_cursor );
		} catch ( reason ) {
			if ( ! cursor ) {
				setItems( [] );
			}
			setNextCursor( null );
			setError(
				( reason as ApiError ).message ??
					__( 'Finder Report search failed.', 'tagcore' )
			);
		} finally {
			setLoading( false );
		}
	};
	const submit = ( event: React.FormEvent ) => {
		event.preventDefault();
		void search();
	};
	const open = async ( item: OperationRecord ) => {
		setLoading( true );
		setError( null );
		clearSensitive();
		try {
			await loadDetail( Number( item.finder_report_id ) );
		} catch ( reason ) {
			setError(
				( reason as ApiError ).message ??
					__( 'Finder Report detail is unavailable.', 'tagcore' )
			);
		} finally {
			setLoading( false );
		}
	};
	const revealMessage = async () => {
		if ( ! detail ) {
			return;
		}
		setLoading( true );
		setError( null );
		try {
			const response = await apiFetch< { message: string } >( {
				path: `${ config.restPath }/admin/finder-reports/${ detail.finder_report_id }/reveal-message`,
				method: 'POST',
			} );
			setMessage( response.message );
		} catch ( reason ) {
			setError(
				( reason as ApiError ).message ??
					__( 'Sensitive preview is unavailable.', 'tagcore' )
			);
		} finally {
			setLoading( false );
		}
	};
	const revealEvidence = async () => {
		if ( ! detail ) {
			return;
		}
		setLoading( true );
		setError( null );
		try {
			const fetchRaw = apiFetch as unknown as ( options: {
				path: string;
				method: string;
				parse: boolean;
			} ) => Promise< Response >;
			const response = await fetchRaw( {
				path: `${ config.restPath }/admin/finder-reports/${ detail.finder_report_id }/reveal-evidence`,
				method: 'POST',
				parse: false,
			} );
			const blob = await response.blob();
			const url = URL.createObjectURL( blob );
			setEvidenceUrl( ( current ) => {
				if ( current ) {
					URL.revokeObjectURL( current );
				}
				return url;
			} );
			window.setTimeout( () => imageRef.current?.focus(), 0 );
		} catch ( reason ) {
			setError(
				( reason as ApiError ).message ??
					__( 'Processed evidence is unavailable.', 'tagcore' )
			);
		} finally {
			setLoading( false );
		}
	};
	return (
		<section>
			<PageHeader
				title={ __( 'Finder Reports', 'tagcore' ) }
				description={ __(
					'Review operational status by an exact Report ID, Tag ID, or Owner User ID.',
					'tagcore'
				) }
				config={ config }
			/>
			<form
				className="returntag-operation-search"
				onSubmit={ submit }
				noValidate
			>
				<RadioControl
					label={ __( 'Exact anchor', 'tagcore' ) }
					selected={ mode }
					options={ [
						{
							label: __( 'Report ID', 'tagcore' ),
							value: 'report_id',
						},
						{ label: __( 'Tag ID', 'tagcore' ), value: 'tag_id' },
						{
							label: __( 'Owner User ID', 'tagcore' ),
							value: 'owner_id',
						},
					] }
					onChange={ ( next ) => {
						setMode( next );
						setValue( '' );
						setItems( [] );
						setNextCursor( null );
						setDetail( null );
						clearSensitive();
					} }
				/>
				<div className="returntag-operation-fields">
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ formatKey( mode ) }
						value={ value }
						onChange={ setValue }
					/>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Report status', 'tagcore' ) }
						value={ reportStatus }
						options={ [
							'',
							'received',
							'processing',
							'ready',
							'notified',
							'blocked',
							'expired',
						].map( ( v ) => ( {
							value: v,
							label: v
								? formatKey( v )
								: __( 'All statuses', 'tagcore' ),
						} ) ) }
						onChange={ setReportStatus }
					/>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Evidence status', 'tagcore' ) }
						value={ evidenceStatus }
						options={ [
							'',
							'quarantined',
							'processing',
							'ready',
							'rejected',
							'deleted',
						].map( ( v ) => ( {
							value: v,
							label: v
								? formatKey( v )
								: __( 'All statuses', 'tagcore' ),
						} ) ) }
						onChange={ setEvidenceStatus }
					/>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Notification status', 'tagcore' ) }
						value={ notificationStatus }
						options={ [
							'',
							'queued',
							'sent',
							'delivered',
							'deferred',
							'failed',
							'bounced',
							'complained',
						].map( ( v ) => ( {
							value: v,
							label: v
								? formatKey( v )
								: __( 'All statuses', 'tagcore' ),
						} ) ) }
						onChange={ setNotificationStatus }
					/>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Created from', 'tagcore' ) }
						type="date"
						value={ createdFrom }
						onChange={ setCreatedFrom }
					/>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Created to', 'tagcore' ) }
						type="date"
						value={ createdTo }
						onChange={ setCreatedTo }
					/>
				</div>
				<Button
					type="submit"
					variant="primary"
					isBusy={ loading }
					disabled={ loading || value.trim() === '' }
				>
					{ __( 'Search Finder Reports', 'tagcore' ) }
				</Button>
			</form>
			<ErrorNotice message={ error } />
			{ loading && <Busy /> }
			{ detail ? (
				<>
					<Button
						variant="tertiary"
						onClick={ () => {
							setDetail( null );
							clearSensitive();
						} }
					>
						{ __( 'Back to results', 'tagcore' ) }
					</Button>
					<SafeDetail record={ detail } />
					{ config.canManageFinderReportDecisions && (
						<FinderReportReviewPanel
							config={ config }
							report={ detail }
							onCommitted={ () => {
								clearSensitive();
								return loadDetail(
									Number( detail.finder_report_id )
								);
							} }
						/>
					) }
					<div className="returntag-sensitive-actions">
						<Notice status="warning" isDismissible={ false }>
							{ __(
								'Sensitive previews are audited. They are available only while the operational preview control is enabled.',
								'tagcore'
							) }
						</Notice>
						<Button
							variant="secondary"
							disabled={ ! detail.has_message || loading }
							onClick={ revealMessage }
						>
							{ __( 'Reveal finder message', 'tagcore' ) }
						</Button>
						<Button
							variant="secondary"
							disabled={ ! detail.has_review_evidence || loading }
							onClick={ revealEvidence }
						>
							{ __( 'View processed evidence', 'tagcore' ) }
						</Button>
						{ message !== null && (
							<div className="returntag-sensitive-message">
								<h3>{ __( 'Finder message', 'tagcore' ) }</h3>
								<p>
									{ message ||
										__(
											'No message was provided.',
											'tagcore'
										) }
								</p>
							</div>
						) }
						{ evidenceUrl && (
							<figure>
								<img
									ref={ imageRef }
									tabIndex={ -1 }
									src={ evidenceUrl }
									alt={ __(
										'Processed Finder Report evidence for internal review',
										'tagcore'
									) }
								/>
								<figcaption>
									{ __(
										'Processed review derivative. This preview is not a public file.',
										'tagcore'
									) }
								</figcaption>
							</figure>
						) }
					</div>
				</>
			) : (
				<>
					<ResultTable
						items={ items }
						columns={ [
							'finder_report_id',
							'tag_id',
							'report_status',
							'evidence_status',
							'notification_status',
							'expires_at',
						] }
						onOpen={ open }
					/>
					{ nextCursor && (
						<Button
							variant="secondary"
							isBusy={ loading }
							disabled={ loading }
							onClick={ () => void search( nextCursor ) }
						>
							{ __( 'Load more Finder Reports', 'tagcore' ) }
						</Button>
					) }
				</>
			) }
		</section>
	);
}

function UsersConsole( { config }: { config: OperationsConfig } ) {
	const [ mode, setMode ] = useState( 'user_id' );
	const [ value, setValue ] = useState( '' );
	const [ items, setItems ] = useState< OperationRecord[] >( [] );
	const [ detail, setDetail ] = useState< OperationRecord | null >( null );
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState< string | null >( null );
	useEffect( () => {
		const userId = new URLSearchParams( window.location.search ).get(
			'user_id'
		);
		if ( userId && /^[1-9][0-9]*$/.test( userId ) ) {
			setMode( 'user_id' );
			setValue( userId );
		}
	}, [] );
	const submit = async ( event: React.FormEvent ) => {
		event.preventDefault();
		setLoading( true );
		setError( null );
		setDetail( null );
		try {
			const response = await apiFetch< { items: OperationRecord[] } >( {
				path: `${ config.restPath }/admin/users/search`,
				method: 'POST',
				data: { mode, [ mode ]: value },
			} );
			setItems( response.items );
		} catch ( reason ) {
			setItems( [] );
			setError(
				( reason as ApiError ).message ??
					__( 'User search failed.', 'tagcore' )
			);
		} finally {
			setLoading( false );
		}
	};
	const open = async ( item: OperationRecord ) => {
		setLoading( true );
		setError( null );
		try {
			setDetail(
				await apiFetch< OperationRecord >( {
					path: `${ config.restPath }/admin/users/${ item.user_id }`,
				} )
			);
		} catch ( reason ) {
			setError(
				( reason as ApiError ).message ??
					__( 'User support view is unavailable.', 'tagcore' )
			);
		} finally {
			setLoading( false );
		}
	};
	return (
		<section>
			<PageHeader
				title={ __( 'Users', 'tagcore' ) }
				description={ __(
					'Find one WordPress user by exact User ID or complete email address.',
					'tagcore'
				) }
				config={ config }
			/>
			<form
				className="returntag-operation-search"
				onSubmit={ submit }
				noValidate
			>
				<RadioControl
					label={ __( 'Exact anchor', 'tagcore' ) }
					selected={ mode }
					options={ [
						{ label: __( 'User ID', 'tagcore' ), value: 'user_id' },
						{ label: __( 'Email', 'tagcore' ), value: 'email' },
					] }
					onChange={ ( next ) => {
						setMode( next );
						setValue( '' );
						setItems( [] );
						setDetail( null );
					} }
				/>
				<div className="returntag-operation-fields">
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={
							mode === 'email'
								? __( 'Complete email address', 'tagcore' )
								: __( 'User ID', 'tagcore' )
						}
						type={ mode === 'email' ? 'email' : 'text' }
						value={ value }
						onChange={ setValue }
					/>
				</div>
				<Button
					type="submit"
					variant="primary"
					isBusy={ loading }
					disabled={ loading || value.trim() === '' }
				>
					{ __( 'Search Users', 'tagcore' ) }
				</Button>
			</form>
			<ErrorNotice message={ error } />
			{ loading && <Busy /> }
			{ detail ? (
				<>
					<Button
						variant="tertiary"
						onClick={ () => setDetail( null ) }
					>
						{ __( 'Back to results', 'tagcore' ) }
					</Button>
					<SafeDetail
						record={ detail }
						exclude={ [ 'wordpress_user_url' ] }
					/>
					<div className="returntag-cross-links">
						<a
							className="components-button is-secondary"
							href={ `${ config.tagsUrl }&owner_user_id=${ detail.user_id }` }
						>
							{ __( 'Search this user’s Tags', 'tagcore' ) }
						</a>
						{ typeof detail.wordpress_user_url === 'string' &&
							detail.wordpress_user_url && (
								<a
									className="components-button is-secondary"
									href={ detail.wordpress_user_url }
								>
									{ __(
										'Open in WordPress Users',
										'tagcore'
									) }
								</a>
							) }
					</div>
				</>
			) : (
				<ResultTable
					items={ items }
					columns={ [
						'user_id',
						'email',
						'registered_at',
						'roles',
						'tag_count',
						'finder_report_count',
						'conversation_count',
					] }
					onOpen={ open }
				/>
			) }
		</section>
	);
}

function RoleProfilesConsole( { config }: { config: OperationsConfig } ) {
	return (
		<section aria-labelledby="returntag-role-profiles-title">
			<PageHeader
				titleId="returntag-role-profiles-title"
				title={ __( 'Role Profiles', 'tagcore' ) }
				description={ __(
					'Review the fixed, least-privilege responsibilities installed by TagCore.',
					'tagcore'
				) }
				config={ config }
			/>
			<Notice status="info" isDismissible={ false }>
				{ __(
					'Assign users through WordPress Users. TagCore does not create accounts or grant capabilities directly to individuals.',
					'tagcore'
				) }
			</Notice>
			<div className="returntag-role-grid">
				{ config.roleProfiles.map( ( profile ) => (
					<article
						className="returntag-governance-card"
						key={ profile.slug }
					>
						<div className="returntag-governance-card-heading">
							<div>
								<h2>{ profile.name }</h2>
								<p>{ profile.responsibility }</p>
							</div>
							<strong>
								{ config.wordpressUsersUrl ? (
									<a
										href={ `${
											config.wordpressUsersUrl
										}?role=${ encodeURIComponent(
											profile.slug
										) }` }
									>
										{ sprintf(
											/* translators: %d: Number of users assigned to the role. */
											__( '%d users', 'tagcore' ),
											profile.userCount
										) }
									</a>
								) : (
									sprintf(
										/* translators: %d: Number of users assigned to the role. */
										__( '%d users', 'tagcore' ),
										profile.userCount
									)
								) }
							</strong>
						</div>
						<h3>{ __( 'TagCore capabilities', 'tagcore' ) }</h3>
						<ul className="returntag-capability-list">
							{ profile.capabilities.map( ( capability ) => (
								<li key={ capability }>
									<code>{ capability }</code>
								</li>
							) ) }
						</ul>
					</article>
				) ) }
			</div>
			{ config.wordpressUsersUrl && (
				<Button variant="secondary" href={ config.wordpressUsersUrl }>
					{ __( 'Open WordPress Users', 'tagcore' ) }
				</Button>
			) }
		</section>
	);
}

interface GlobalAuditEntry {
	event_id: number;
	event_type: string;
	actor_type: string;
	actor_id: number | null;
	actor_user_url: string | null;
	target_type: string;
	target_id: string;
	result: string;
	created_at: string;
}

interface AuditSearchResponse {
	items: GlobalAuditEntry[];
	next_cursor: string | null;
}

type AuditTargetType =
	| ''
	| 'batch'
	| 'tag'
	| 'finder_report'
	| 'conversation'
	| 'user';

function localDateTime( offsetHours: number ): string {
	const date = new Date( Date.now() + offsetHours * 60 * 60 * 1000 );
	date.setMinutes( date.getMinutes() - date.getTimezoneOffset() );
	return date.toISOString().slice( 0, 16 );
}

function toUtcSeconds( value: string ): string {
	return new Date( value ).toISOString().replace( /\.\d{3}Z$/, 'Z' );
}

function AuditLogConsole( { config }: { config: OperationsConfig } ) {
	const [ from, setFrom ] = useState( localDateTime( -24 ) );
	const [ to, setTo ] = useState( localDateTime( 0 ) );
	const [ actor, setActor ] = useState( '' );
	const [ targetType, setTargetType ] = useState< AuditTargetType >( '' );
	const [ targetId, setTargetId ] = useState( '' );
	const [ eventType, setEventType ] = useState( '' );
	const [ result, setResult ] = useState( '' );
	const [ response, setResponse ] = useState< AuditSearchResponse | null >(
		null
	);
	const [ error, setError ] = useState< string | null >( null );
	const [ busy, setBusy ] = useState( false );
	const [ announcement, setAnnouncement ] = useState( '' );

	const criteria = () => ( {
		from: toUtcSeconds( from ),
		to: toUtcSeconds( to ),
		actor_user_id: actor,
		target_type: targetType,
		target_id: targetId,
		event_type: eventType,
		result,
		per_page: 50,
	} );

	const search = async ( cursor?: string ) => {
		setBusy( true );
		setError( null );
		try {
			const next = await apiFetch< AuditSearchResponse >( {
				path: `${ config.restPath }/admin/audit-events/search`,
				method: 'POST',
				data: { ...criteria(), cursor: cursor ?? '' },
			} );
			setResponse( ( current ) =>
				cursor && current
					? {
							items: [ ...current.items, ...next.items ],
							next_cursor: next.next_cursor,
					  }
					: next
			);
			setAnnouncement(
				sprintf(
					/* translators: %d: Number of audit events loaded. */
					__( '%d audit events loaded.', 'tagcore' ),
					cursor && response
						? response.items.length + next.items.length
						: next.items.length
				)
			);
		} catch ( reason ) {
			const apiError = reason as ApiError;
			setError(
				apiError.message ??
					__( 'TagCore could not search the audit log.', 'tagcore' )
			);
		} finally {
			setBusy( false );
		}
	};

	useEffect( () => {
		void search();
	}, [] ); // eslint-disable-line react-hooks/exhaustive-deps

	return (
		<section aria-labelledby="returntag-audit-log-title">
			<PageHeader
				titleId="returntag-audit-log-title"
				title={ __( 'Audit Log', 'tagcore' ) }
				description={ __(
					'Search privacy-safe operational events without metadata or correlation identifiers.',
					'tagcore'
				) }
				config={ config }
			/>
			<form
				className="returntag-operation-search"
				onSubmit={ ( event ) => {
					event.preventDefault();
					void search();
				} }
			>
				<h2>{ __( 'Exact audit filters', 'tagcore' ) }</h2>
				<p>
					{ __(
						'The default view covers the most recent 24 hours. The maximum search window is 31 days.',
						'tagcore'
					) }
				</p>
				<div className="returntag-operation-fields returntag-audit-fields">
					<TextControl
						type="datetime-local"
						label={ __( 'From (local time)', 'tagcore' ) }
						value={ from }
						onChange={ setFrom }
						required
					/>
					<TextControl
						type="datetime-local"
						label={ __( 'To (local time)', 'tagcore' ) }
						value={ to }
						onChange={ setTo }
						required
					/>
					<TextControl
						label={ __( 'Actor User ID', 'tagcore' ) }
						value={ actor }
						onChange={ setActor }
						inputMode="numeric"
					/>
					<SelectControl
						label={ __( 'Target type', 'tagcore' ) }
						value={ targetType }
						onChange={ setTargetType }
						options={ [
							{ label: __( 'Any target', 'tagcore' ), value: '' },
							{ label: __( 'Batch', 'tagcore' ), value: 'batch' },
							{ label: __( 'Tag', 'tagcore' ), value: 'tag' },
							{
								label: __( 'Finder Report', 'tagcore' ),
								value: 'finder_report',
							},
							{
								label: __( 'Conversation', 'tagcore' ),
								value: 'conversation',
							},
							{ label: __( 'User', 'tagcore' ), value: 'user' },
						] }
					/>
					<TextControl
						label={ __( 'Target ID', 'tagcore' ) }
						value={ targetId }
						onChange={ setTargetId }
						disabled={ ! targetType }
					/>
					<TextControl
						label={ __( 'Event type', 'tagcore' ) }
						value={ eventType }
						onChange={ setEventType }
					/>
					<TextControl
						label={ __( 'Result', 'tagcore' ) }
						value={ result }
						onChange={ setResult }
					/>
				</div>
				<Button
					type="submit"
					variant="primary"
					disabled={ busy }
					isBusy={ busy }
				>
					{ __( 'Search audit log', 'tagcore' ) }
				</Button>
			</form>
			<ErrorNotice message={ error } />
			{ ! response && busy && <Busy /> }
			{ response && response.items.length === 0 && (
				<div className="returntag-empty">
					<h2>{ __( 'No audit events found', 'tagcore' ) }</h2>
					<p>
						{ __(
							'No metadata-free events match this exact window and filter set.',
							'tagcore'
						) }
					</p>
				</div>
			) }
			{ response && response.items.length > 0 && (
				<div className="returntag-audit-stream">
					<ol>
						{ response.items.map( ( entry ) => (
							<li key={ entry.event_id }>
								<time>{ entry.created_at } UTC</time>
								<div>
									<strong>
										{ formatKey( entry.event_type ) }
									</strong>
									<span>
										{ entry.target_type } ·{ ' ' }
										{ entry.target_id } · { entry.result }
									</span>
								</div>
								<span>
									{ entry.actor_type }{ ' ' }
									{ entry.actor_user_url && entry.actor_id ? (
										<a href={ entry.actor_user_url }>
											{ entry.actor_id }
										</a>
									) : (
										entry.actor_id ?? '—'
									) }
								</span>
							</li>
						) ) }
					</ol>
					{ response.next_cursor && (
						<Button
							variant="secondary"
							disabled={ busy }
							isBusy={ busy }
							onClick={ () =>
								void search( response.next_cursor ?? undefined )
							}
						>
							{ __( 'Load more events', 'tagcore' ) }
						</Button>
					) }
				</div>
			) }
			<p className="screen-reader-text" aria-live="polite">
				{ announcement }
			</p>
		</section>
	);
}

interface RetentionTask {
	task_id: string;
	name: string;
	description: string;
	policy: string;
	schedule_health: string;
	last_run_at: string | null;
	last_result: string | null;
	next_run_at: string | null;
	current_status: string;
	pending_count: string;
}

interface RetentionResponse {
	items: RetentionTask[];
	manual_run_enabled: boolean;
}

function RetentionConsole( { config }: { config: OperationsConfig } ) {
	const [ response, setResponse ] = useState< RetentionResponse | null >(
		null
	);
	const [ selected, setSelected ] = useState< RetentionTask | null >( null );
	const [ confirmation, setConfirmation ] = useState( '' );
	const [ error, setError ] = useState< string | null >( null );
	const [ notice, setNotice ] = useState< string | null >( null );
	const [ busy, setBusy ] = useState( false );
	const load = async () => {
		setBusy( true );
		try {
			setResponse(
				await apiFetch< RetentionResponse >( {
					path: `${ config.restPath }/admin/retention/tasks`,
				} )
			);
			setError( null );
		} catch ( reason ) {
			const apiError = reason as ApiError;
			setError(
				apiError.message ??
					__( 'TagCore could not load retention health.', 'tagcore' )
			);
		} finally {
			setBusy( false );
		}
	};
	useEffect( () => {
		void load();
	}, [] ); // eslint-disable-line react-hooks/exhaustive-deps
	const run = async () => {
		if ( ! selected ) {
			return;
		}
		setBusy( true );
		setError( null );
		try {
			await apiFetch( {
				path: `${ config.restPath }/admin/retention/tasks/${ selected.task_id }/run`,
				method: 'POST',
				data: { confirmation },
			} );
			setNotice(
				__( 'One bounded cleanup batch was queued.', 'tagcore' )
			);
			setSelected( null );
			setConfirmation( '' );
			await load();
		} catch ( reason ) {
			const apiError = reason as ApiError;
			setError(
				apiError.message ??
					__(
						'TagCore could not queue this retention task.',
						'tagcore'
					)
			);
		} finally {
			setBusy( false );
		}
	};
	return (
		<section aria-labelledby="returntag-retention-title">
			<PageHeader
				titleId="returntag-retention-title"
				title={ __( 'Retention', 'tagcore' ) }
				description={ __(
					'Monitor fixed cleanup policies and request one bounded batch when explicitly enabled.',
					'tagcore'
				) }
				config={ config }
			/>
			{ response && ! response.manual_run_enabled && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'Manual retention runs are disabled. Automatic scheduled cleanup continues.',
						'tagcore'
					) }
				</Notice>
			) }
			{ notice && (
				<Notice status="success" onRemove={ () => setNotice( null ) }>
					{ notice }
				</Notice>
			) }
			<ErrorNotice message={ error } />
			{ ! response && busy && <Busy /> }
			{ response && (
				<div className="returntag-retention-grid">
					{ response.items.map( ( task ) => (
						<article
							className="returntag-governance-card"
							key={ task.task_id }
						>
							<div className="returntag-governance-card-heading">
								<div>
									<h2>{ task.name }</h2>
									<p>{ task.description }</p>
								</div>
								<strong
									className={ `is-${ task.schedule_health }` }
								>
									{ formatKey( task.schedule_health ) }
								</strong>
							</div>
							<p className="returntag-policy-note">
								{ task.policy }
							</p>
							<dl className="returntag-retention-facts">
								<div>
									<dt>{ __( 'Pending', 'tagcore' ) }</dt>
									<dd>{ task.pending_count }</dd>
								</div>
								<div>
									<dt>{ __( 'Status', 'tagcore' ) }</dt>
									<dd>
										{ formatKey( task.current_status ) }
									</dd>
								</div>
								<div>
									<dt>{ __( 'Last run', 'tagcore' ) }</dt>
									<dd>{ task.last_run_at ?? '—' }</dd>
								</div>
								<div>
									<dt>{ __( 'Next run', 'tagcore' ) }</dt>
									<dd>{ task.next_run_at ?? '—' }</dd>
								</div>
							</dl>
							<Button
								variant="secondary"
								disabled={
									! response.manual_run_enabled ||
									task.current_status !== 'idle'
								}
								onClick={ () => {
									setSelected( task );
									setConfirmation( '' );
								} }
							>
								{ __( 'Run one bounded batch', 'tagcore' ) }
							</Button>
						</article>
					) ) }
				</div>
			) }
			{ selected && (
				<Modal
					title={ __( 'Confirm bounded retention run', 'tagcore' ) }
					onRequestClose={ () => setSelected( null ) }
					shouldCloseOnClickOutside={ ! busy }
				>
					<p>
						{ __(
							'This queues one existing bounded cleanup batch. Fixed retention periods and Active Holds are not changed.',
							'tagcore'
						) }
					</p>
					<TextControl
						label={ sprintf(
							/* translators: %s: Fixed retention Task ID. */
							__( 'Enter %s to confirm', 'tagcore' ),
							selected.task_id
						) }
						value={ confirmation }
						onChange={ setConfirmation }
						autoComplete="off"
						disabled={ busy }
					/>
					<div className="returntag-modal-actions">
						<Button
							variant="tertiary"
							onClick={ () => setSelected( null ) }
							disabled={ busy }
						>
							{ __( 'Cancel', 'tagcore' ) }
						</Button>
						<Button
							variant="primary"
							isDestructive
							onClick={ run }
							disabled={
								busy || confirmation !== selected.task_id
							}
							isBusy={ busy }
						>
							{ __( 'Queue cleanup batch', 'tagcore' ) }
						</Button>
					</div>
				</Modal>
			) }
			<p className="screen-reader-text" aria-live="polite">
				{ notice }
			</p>
		</section>
	);
}

export function OperationsConsole( { config }: { config: OperationsConfig } ) {
	if ( config.surface === 'role_profiles' ) {
		return <RoleProfilesConsole config={ config } />;
	}
	if ( config.surface === 'audit_log' ) {
		return <AuditLogConsole config={ config } />;
	}
	if ( config.surface === 'retention' ) {
		return <RetentionConsole config={ config } />;
	}
	if ( config.surface === 'finder_reports' ) {
		return <FinderReportsConsole config={ config } />;
	}
	if ( config.surface === 'users' ) {
		return <UsersConsole config={ config } />;
	}
	return <TagsConsole config={ config } />;
}
