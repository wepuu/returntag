import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Modal,
	Notice,
	RadioControl,
	SelectControl,
	Spinner,
	TextareaControl,
	TextControl,
} from '@wordpress/components';
import {
	createRoot,
	useCallback,
	useEffect,
	useRef,
	useState,
} from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	box,
	cautionFilled,
	check,
	download,
	file,
	Icon,
	lock,
	people,
	scheduled,
} from '@wordpress/icons';

import {
	appendExportRecords,
	type BatchExportDownloadMetadata,
	type BatchExportHistoryResponse,
	canExportBatch,
	isReExport,
	readDownloadMetadata,
} from './admin/batch-export';
import {
	availableLifecycleActions,
	type BatchLifecycleAction,
	type BatchLifecycleResponse,
	canConfirmVoid,
} from './admin/batch-lifecycle';
import {
	type BatchGenerationProgress,
	type BatchStatus,
	calculateProgressPercent,
	generationPollDelay,
	shouldPollGeneration,
} from './admin/batch-generation';
import {
	appendInventoryItems,
	type BatchTagInventoryResponse,
	type TagStatus,
	shouldShowBatchInventory,
} from './admin/batch-inventory';
import {
	type BatchFormValues,
	type SalesChannel,
	type SmartNetwork,
	type TagType,
	validateBatchForm,
} from './admin/batch-form';
import { ADMIN_ROOT_CLASS } from './shared/css-scope';
import './styles/admin.css';

interface AdminConfig {
	nonce: string;
	restPath: string;
	currentUser: string;
	currentTime: string;
	listUrl: string;
	createUrl: string;
}

interface BatchRecord {
	batch_id: number;
	batch_code: string;
	tag_type: TagType;
	model_code: string | null;
	smart_network: SmartNetwork;
	manufacturer: string | null;
	sales_channel: string | null;
	requested_quantity: number;
	generated_quantity: number;
	batch_status: BatchStatus;
	activation_enabled: boolean;
	notes: string | null;
	created_by: number;
	created_at: string;
	updated_at: string;
}

interface BatchSummary {
	batch_id: number;
	batch_code: string;
	tag_type: TagType;
	model_code: string | null;
	requested_quantity: number;
	generated_quantity: number;
	batch_status: BatchStatus;
	activation_enabled: boolean;
	created_at: string;
}

interface BatchListResponse {
	items: BatchSummary[];
	next_cursor: number | null;
}

interface ApiError {
	message?: string;
	data?: {
		fields?: Record< string, string >;
	};
}

declare global {
	interface Window {
		returntagTagCoreAdmin: AdminConfig;
	}
}

const config = window.returntagTagCoreAdmin;
apiFetch.use( apiFetch.createNonceMiddleware( config.nonce ) );

const initialValues: BatchFormValues = {
	batch_code: '',
	tag_type: 'sticker',
	model_code: '',
	smart_network: 'none',
	requested_quantity: '',
	manufacturer: '',
	sales_channel: 'direct',
	notes: '',
};

const tagTypeLabels: Record< TagType, string > = {
	sticker: __( 'Sticker', 'tagcore' ),
	classic_tag: __( 'Classic tag', 'tagcore' ),
	smart_tag: __( 'Smart tag', 'tagcore' ),
};

const smartNetworkLabels: Record< SmartNetwork, string > = {
	none: __( 'None', 'tagcore' ),
	apple_find_my: __( 'Apple Find My', 'tagcore' ),
	google_find_hub: __( 'Google Find Hub', 'tagcore' ),
	other: __( 'Other', 'tagcore' ),
};

const batchStatusLabels: Record< BatchStatus, string > = {
	draft: __( 'Draft', 'tagcore' ),
	generating: __( 'Generating', 'tagcore' ),
	generated: __( 'Generated', 'tagcore' ),
	exported: __( 'Exported', 'tagcore' ),
	released: __( 'Released', 'tagcore' ),
	suspended: __( 'Suspended', 'tagcore' ),
	voided: __( 'Voided', 'tagcore' ),
};

const tagStatusLabels: Record< TagStatus, string > = {
	unregistered: __( 'Unregistered', 'tagcore' ),
	active: __( 'Active', 'tagcore' ),
	suspended: __( 'Suspended', 'tagcore' ),
	retired: __( 'Retired', 'tagcore' ),
};

const fieldLabels: Record< string, string > = {
	batch_code: __( 'Batch Code', 'tagcore' ),
	tag_type: __( 'Tag type', 'tagcore' ),
	model_code: __( 'Model code', 'tagcore' ),
	smart_network: __( 'Smart network', 'tagcore' ),
	requested_quantity: __( 'Requested quantity', 'tagcore' ),
	manufacturer: __( 'Manufacturer', 'tagcore' ),
	sales_channel: __( 'Sales channel', 'tagcore' ),
	notes: __( 'Notes', 'tagcore' ),
};

function formatDate( value: string ): string {
	const date = new Date( value );

	if ( Number.isNaN( date.getTime() ) ) {
		return value;
	}

	return new Intl.DateTimeFormat( undefined, {
		dateStyle: 'medium',
		timeStyle: 'short',
		timeZone: 'UTC',
	} ).format( date );
}

function ErrorHelp( {
	message,
	fallback,
}: {
	message?: string;
	fallback: string;
} ) {
	if ( ! message ) {
		return fallback;
	}

	return (
		<span className="returntag-field-error" role="alert">
			{ message }
		</span>
	);
}

function StatusBand( {
	status,
	generatedQuantity,
	activationEnabled,
	createdAt,
}: {
	status: BatchStatus;
	generatedQuantity: number;
	activationEnabled: boolean;
	createdAt: string;
} ) {
	const facts = [
		{
			icon: file,
			label: __( 'Status', 'tagcore' ),
			value: batchStatusLabels[ status ],
		},
		{
			icon: box,
			label: __( 'Generated', 'tagcore' ),
			value: generatedQuantity.toLocaleString(),
		},
		{
			icon: lock,
			label: __( 'Activation', 'tagcore' ),
			value: activationEnabled
				? __( 'Enabled', 'tagcore' )
				: __( 'Disabled', 'tagcore' ),
		},
		{
			icon: people,
			label: __( 'Created by', 'tagcore' ),
			value: config.currentUser,
		},
		{
			icon: scheduled,
			label: __( 'Created (UTC)', 'tagcore' ),
			value: formatDate( createdAt ),
		},
	];

	return (
		<div
			className="returntag-status-band"
			role="list"
			aria-label={ __( 'Server-controlled Batch values', 'tagcore' ) }
		>
			{ facts.map( ( fact ) => (
				<div
					className="returntag-status-fact"
					key={ fact.label }
					role="listitem"
				>
					<Icon icon={ fact.icon } size={ 24 } />
					<div>
						<span className="returntag-status-term">
							{ fact.label }
						</span>
						<span className="returntag-status-value">
							{ fact.value }
						</span>
					</div>
				</div>
			) ) }
		</div>
	);
}

function ProgressSpine( { active }: { active: number } ) {
	const stages = [
		{
			label: __( 'Identity', 'tagcore' ),
			description: __( 'Batch identity information', 'tagcore' ),
		},
		{
			label: __( 'Product', 'tagcore' ),
			description: __( 'Product and tag details', 'tagcore' ),
		},
		{
			label: __( 'Production', 'tagcore' ),
			description: __( 'Manufacturing details', 'tagcore' ),
		},
	];

	return (
		<nav
			className="returntag-progress"
			aria-label={ __( 'Batch form progress', 'tagcore' ) }
		>
			<ol>
				{ stages.map( ( stage, index ) => {
					const position = index + 1;
					const complete = position < active;
					const current = position === active;

					return (
						<li
							className={ [
								complete ? 'is-complete' : '',
								current ? 'is-current' : '',
							]
								.filter( Boolean )
								.join( ' ' ) }
							key={ stage.label }
						>
							<span className="returntag-progress-marker">
								{ complete ? (
									<Icon icon={ check } size={ 18 } />
								) : (
									position
								) }
							</span>
							<span>
								<strong>{ stage.label }</strong>
								<small>{ stage.description }</small>
							</span>
						</li>
					);
				} ) }
			</ol>
		</nav>
	);
}

function CreateBatchScreen() {
	const [ values, setValues ] = useState< BatchFormValues >( initialValues );
	const [ errors, setErrors ] = useState< Record< string, string > >( {} );
	const [ globalError, setGlobalError ] = useState< string | null >( null );
	const [ submitting, setSubmitting ] = useState( false );
	const [ created, setCreated ] = useState< BatchRecord | null >( null );
	const errorSummary = useRef< HTMLDivElement | null >( null );

	const activeStage = values.batch_code ? 2 : 1;

	useEffect( () => {
		if ( globalError || Object.keys( errors ).length > 0 ) {
			errorSummary.current?.focus();
		}
	}, [ errors, globalError ] );

	if ( created ) {
		return (
			<section
				className="returntag-created"
				aria-labelledby="returntag-title"
			>
				<h1 id="returntag-title">
					{ __( 'Batch created', 'tagcore' ) }
				</h1>
				<Notice status="success" isDismissible={ false }>
					{ sprintf(
						/* translators: %s: Batch Code. */
						__(
							'Draft Batch %s was created successfully.',
							'tagcore'
						),
						created.batch_code
					) }
				</Notice>
				<StatusBand
					status={ created.batch_status }
					generatedQuantity={ created.generated_quantity }
					activationEnabled={ created.activation_enabled }
					createdAt={ created.created_at }
				/>
				<dl className="returntag-created-details">
					<div>
						<dt>{ __( 'Batch Code', 'tagcore' ) }</dt>
						<dd>{ created.batch_code }</dd>
					</div>
					<div>
						<dt>{ __( 'Tag type', 'tagcore' ) }</dt>
						<dd>{ tagTypeLabels[ created.tag_type ] }</dd>
					</div>
					<div>
						<dt>{ __( 'Requested quantity', 'tagcore' ) }</dt>
						<dd>{ created.requested_quantity.toLocaleString() }</dd>
					</div>
				</dl>
				<div className="returntag-actions">
					<Button
						variant="primary"
						href={ `${ config.listUrl }&view=detail&batch_id=${ created.batch_id }` }
					>
						{ __( 'Review and generate IDs', 'tagcore' ) }
					</Button>
					<Button
						variant="secondary"
						onClick={ () => {
							setCreated( null );
							setValues( initialValues );
							setErrors( {} );
						} }
					>
						{ __( 'Create another', 'tagcore' ) }
					</Button>
				</div>
			</section>
		);
	}

	const update = < K extends keyof BatchFormValues >(
		key: K,
		value: BatchFormValues[ K ]
	) => {
		setValues( ( current ) => ( { ...current, [ key ]: value } ) );
		setErrors( ( current ) => {
			const next = { ...current };
			delete next[ key ];
			return next;
		} );
	};

	const submit = async ( event: React.FormEvent ) => {
		event.preventDefault();
		const normalizedValues = {
			...values,
			batch_code: values.batch_code.trim(),
			model_code: values.model_code.trim(),
			manufacturer: values.manufacturer.trim(),
			notes: values.notes.trim(),
		};
		const clientErrors = validateBatchForm( normalizedValues );

		setValues( normalizedValues );

		if ( Object.keys( clientErrors ).length > 0 ) {
			setErrors( clientErrors );
			setGlobalError(
				__( 'Please correct the following to continue.', 'tagcore' )
			);
			return;
		}

		setSubmitting( true );
		setErrors( {} );
		setGlobalError( null );

		try {
			const batch = await apiFetch< BatchRecord >( {
				path: `${ config.restPath }/batches`,
				method: 'POST',
				data: {
					...normalizedValues,
					requested_quantity: Number(
						normalizedValues.requested_quantity
					),
				},
			} );
			setCreated( batch );
		} catch ( error ) {
			const apiError = error as ApiError;
			const fieldErrors = apiError.data?.fields ?? {};

			setErrors( fieldErrors );
			setGlobalError(
				Object.keys( fieldErrors ).length > 0
					? __(
							'Please correct the following to continue.',
							'tagcore'
					  )
					: apiError.message ??
							__(
								'TagCore could not create this Batch.',
								'tagcore'
							)
			);
		} finally {
			setSubmitting( false );
		}
	};

	return (
		<section aria-labelledby="returntag-title">
			<header className="returntag-page-header">
				<div>
					<h1 id="returntag-title">
						{ __( 'Create batch', 'tagcore' ) }
					</h1>
					<p>
						{ __(
							'Create a new manufacturing Batch. It starts as Draft and activation remains disabled until a future release step.',
							'tagcore'
						) }
					</p>
				</div>
			</header>

			{ ( globalError || Object.keys( errors ).length > 0 ) && (
				<div
					ref={ errorSummary }
					tabIndex={ -1 }
					aria-label={ __( 'Batch validation errors', 'tagcore' ) }
				>
					<Notice status="error" isDismissible={ false }>
						<div className="returntag-error-heading">
							<Icon icon={ cautionFilled } size={ 24 } />
							<strong>
								{ globalError ??
									__(
										'Please correct the following to continue.',
										'tagcore'
									) }
							</strong>
						</div>
						{ Object.entries( errors ).length > 0 && (
							<ul className="returntag-error-list">
								{ Object.entries( errors ).map(
									( [ field, message ] ) => (
										<li key={ field }>
											<strong>
												{ fieldLabels[ field ] ??
													__( 'Request', 'tagcore' ) }
												:
											</strong>{ ' ' }
											{ message }
										</li>
									)
								) }
							</ul>
						) }
					</Notice>
				</div>
			) }

			<StatusBand
				status="draft"
				generatedQuantity={ 0 }
				activationEnabled={ false }
				createdAt={ config.currentTime }
			/>

			<div className="returntag-form-layout">
				<ProgressSpine active={ activeStage } />
				<form
					className="returntag-batch-form"
					onSubmit={ submit }
					noValidate
				>
					<div className="returntag-section-heading">
						<h2>{ __( 'Product and tag details', 'tagcore' ) }</h2>
						<p>
							{ __(
								'Provide the product and manufacturing configuration for this Batch.',
								'tagcore'
							) }
						</p>
					</div>

					<div className="returntag-field-grid">
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							className={
								errors.batch_code ? 'has-error' : undefined
							}
							label={ __( 'Batch Code *', 'tagcore' ) }
							maxLength={ 191 }
							required
							value={ values.batch_code }
							onChange={ ( value ) =>
								update( 'batch_code', value )
							}
							help={
								<ErrorHelp
									message={ errors.batch_code }
									fallback={ __(
										'Unique code using letters, numbers, and hyphens.',
										'tagcore'
									) }
								/>
							}
						/>

						<RadioControl
							className={
								errors.tag_type ? 'has-error' : undefined
							}
							label={ __( 'Tag type *', 'tagcore' ) }
							selected={ values.tag_type }
							options={ [
								{
									label: __( 'Sticker', 'tagcore' ),
									value: 'sticker',
								},
								{
									label: __( 'Classic tag', 'tagcore' ),
									value: 'classic_tag',
								},
								{
									label: __( 'Smart tag', 'tagcore' ),
									value: 'smart_tag',
								},
							] }
							onChange={ ( value ) => {
								const tagType = value as TagType;
								update( 'tag_type', tagType );

								if ( tagType !== 'smart_tag' ) {
									update( 'smart_network', 'none' );
								}
							} }
							help={ __(
								'Select the physical product type for this Batch.',
								'tagcore'
							) }
						/>

						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							className={
								errors.model_code ? 'has-error' : undefined
							}
							label={ __( 'Model code', 'tagcore' ) }
							maxLength={ 191 }
							value={ values.model_code }
							onChange={ ( value ) =>
								update( 'model_code', value )
							}
							help={
								<ErrorHelp
									message={ errors.model_code }
									fallback={ __(
										'Optional product or hardware model.',
										'tagcore'
									) }
								/>
							}
						/>

						<SelectControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							className={
								errors.smart_network ? 'has-error' : undefined
							}
							label={ __( 'Smart network', 'tagcore' ) }
							value={ values.smart_network }
							disabled={ values.tag_type !== 'smart_tag' }
							options={ [
								{
									label: __( 'None', 'tagcore' ),
									value: 'none',
								},
								{
									label: __( 'Apple Find My', 'tagcore' ),
									value: 'apple_find_my',
								},
								{
									label: __( 'Google Find Hub', 'tagcore' ),
									value: 'google_find_hub',
								},
								{
									label: __( 'Other', 'tagcore' ),
									value: 'other',
								},
							] }
							onChange={ ( value ) =>
								update( 'smart_network', value as SmartNetwork )
							}
							help={
								<ErrorHelp
									message={ errors.smart_network }
									fallback={
										values.tag_type === 'smart_tag'
											? __(
													'Descriptor metadata only. TagCore does not connect to Apple or Google.',
													'tagcore'
											  )
											: __(
													'Available only when Tag type is Smart tag.',
													'tagcore'
											  )
									}
								/>
							}
						/>

						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							className={
								errors.requested_quantity
									? 'has-error'
									: undefined
							}
							label={ __( 'Requested quantity *', 'tagcore' ) }
							max={ 4294967295 }
							min={ 1 }
							required
							step={ 1 }
							type="number"
							value={ values.requested_quantity }
							onChange={ ( value ) =>
								update( 'requested_quantity', String( value ) )
							}
							help={
								<ErrorHelp
									message={ errors.requested_quantity }
									fallback={ __(
										'Total number of Tag IDs requested for this Batch.',
										'tagcore'
									) }
								/>
							}
						/>

						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							className={
								errors.manufacturer ? 'has-error' : undefined
							}
							label={ __( 'Manufacturer', 'tagcore' ) }
							maxLength={ 191 }
							value={ values.manufacturer }
							onChange={ ( value ) =>
								update( 'manufacturer', value )
							}
							help={
								<ErrorHelp
									message={ errors.manufacturer }
									fallback={ __(
										'Optional manufacturing partner.',
										'tagcore'
									) }
								/>
							}
						/>

						<SelectControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							className={
								errors.sales_channel ? 'has-error' : undefined
							}
							label={ __( 'Sales channel', 'tagcore' ) }
							value={ values.sales_channel }
							options={ [
								{
									label: __( 'Direct', 'tagcore' ),
									value: 'direct',
								},
								{
									label: __( 'Amazon', 'tagcore' ),
									value: 'amazon',
								},
								{
									label: __( 'Mixed', 'tagcore' ),
									value: 'mixed',
								},
								{
									label: __( 'Other', 'tagcore' ),
									value: 'other',
								},
							] }
							onChange={ ( value ) =>
								update( 'sales_channel', value as SalesChannel )
							}
							help={ __(
								'Channel metadata only; it never maps orders to Tag IDs.',
								'tagcore'
							) }
						/>

						<TextareaControl
							__nextHasNoMarginBottom
							className={ errors.notes ? 'has-error' : undefined }
							label={ __( 'Notes', 'tagcore' ) }
							rows={ 2 }
							value={ values.notes }
							onChange={ ( value ) => update( 'notes', value ) }
							help={
								<ErrorHelp
									message={ errors.notes }
									fallback={ __(
										'Optional internal manufacturing notes.',
										'tagcore'
									) }
								/>
							}
						/>
					</div>

					<div className="returntag-actions">
						<Button
							variant="primary"
							type="submit"
							disabled={ submitting }
							isBusy={ submitting }
						>
							{ submitting
								? __( 'Creating…', 'tagcore' )
								: __( 'Create draft batch', 'tagcore' ) }
						</Button>
						<Button
							variant="secondary"
							href={ config.listUrl }
							disabled={ submitting }
						>
							{ __( 'Cancel', 'tagcore' ) }
						</Button>
					</div>
					<p className="returntag-required-note">
						{ __( 'Fields marked * are required.', 'tagcore' ) }
					</p>
				</form>
			</div>
		</section>
	);
}

function BatchListScreen() {
	const [ response, setResponse ] = useState< BatchListResponse | null >(
		null
	);
	const [ error, setError ] = useState< string | null >( null );
	const [ loadingMore, setLoadingMore ] = useState( false );

	const loadBatches = useCallback( async () => {
		try {
			const next = await apiFetch< BatchListResponse >( {
				path: `${ config.restPath }/batches?per_page=50`,
			} );
			setResponse( next );
			setError( null );
		} catch ( reason ) {
			const apiError = reason as ApiError;
			setError(
				apiError.message ??
					__( 'TagCore could not load Batches.', 'tagcore' )
			);
		}
	}, [] );

	useEffect( () => {
		void loadBatches();
	}, [ loadBatches ] );

	useEffect( () => {
		if (
			! response?.items.some(
				( batch ) => batch.batch_status === 'generating'
			)
		) {
			return;
		}

		let timer: ReturnType< typeof setTimeout > | undefined;
		let cancelled = false;

		const schedule = () => {
			if ( cancelled ) {
				return;
			}

			timer = setTimeout( async () => {
				if ( document.visibilityState === 'visible' ) {
					await loadBatches();
				}
				schedule();
			}, 10_000 );
		};

		const onVisibilityChange = () => {
			if ( document.visibilityState === 'visible' ) {
				void loadBatches();
			}
		};

		document.addEventListener( 'visibilitychange', onVisibilityChange );
		schedule();

		return () => {
			cancelled = true;
			if ( timer ) {
				clearTimeout( timer );
			}
			document.removeEventListener(
				'visibilitychange',
				onVisibilityChange
			);
		};
	}, [ loadBatches, response ] );

	const loadMore = async () => {
		if ( ! response?.next_cursor ) {
			return;
		}

		setLoadingMore( true );
		setError( null );

		try {
			const next = await apiFetch< BatchListResponse >( {
				path: `${ config.restPath }/batches?per_page=50&cursor=${ response.next_cursor }`,
			} );
			setResponse( {
				items: [ ...response.items, ...next.items ],
				next_cursor: next.next_cursor,
			} );
		} catch ( reason ) {
			const apiError = reason as ApiError;
			setError(
				apiError.message ??
					__( 'TagCore could not load more Batches.', 'tagcore' )
			);
		} finally {
			setLoadingMore( false );
		}
	};

	return (
		<section aria-labelledby="returntag-list-title">
			<header className="returntag-page-header returntag-list-header">
				<div>
					<h1 id="returntag-list-title">
						{ __( 'Batches', 'tagcore' ) }
					</h1>
					<p>
						{ __(
							'Manufacturing batches remain separate from orders and shipments.',
							'tagcore'
						) }
					</p>
				</div>
				<Button variant="primary" href={ config.createUrl }>
					{ __( 'Create batch', 'tagcore' ) }
				</Button>
			</header>

			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }

			{ ! response && ! error && (
				<div className="returntag-loading">
					<Spinner />
					<span>{ __( 'Loading Batches…', 'tagcore' ) }</span>
				</div>
			) }

			{ response && response.items.length === 0 && (
				<div className="returntag-empty">
					<h2>{ __( 'No Batches yet', 'tagcore' ) }</h2>
					<p>
						{ __(
							'Create a disabled draft Batch to begin a future production workflow.',
							'tagcore'
						) }
					</p>
				</div>
			) }

			{ response && response.items.length > 0 && (
				<>
					<div className="returntag-table-wrap">
						<table className="widefat striped returntag-batch-table">
							<thead>
								<tr>
									<th scope="col">
										{ __( 'Batch Code', 'tagcore' ) }
									</th>
									<th scope="col">
										{ __( 'Tag type', 'tagcore' ) }
									</th>
									<th scope="col">
										{ __( 'Requested', 'tagcore' ) }
									</th>
									<th scope="col">
										{ __( 'Generated', 'tagcore' ) }
									</th>
									<th scope="col">
										{ __( 'Status', 'tagcore' ) }
									</th>
									<th scope="col">
										{ __( 'Created (UTC)', 'tagcore' ) }
									</th>
								</tr>
							</thead>
							<tbody>
								{ response.items.map( ( batch ) => (
									<tr key={ batch.batch_id }>
										<td>
											<a
												href={ `${ config.listUrl }&view=detail&batch_id=${ batch.batch_id }` }
											>
												<strong>
													{ batch.batch_code }
												</strong>
											</a>
										</td>
										<td>
											{ tagTypeLabels[ batch.tag_type ] }
										</td>
										<td>
											{ batch.requested_quantity.toLocaleString() }
										</td>
										<td>
											<div className="returntag-list-progress">
												<span>
													{ sprintf(
														/* translators: 1: Generated quantity. 2: Requested quantity. */
														__(
															'%1$s of %2$s',
															'tagcore'
														),
														batch.generated_quantity.toLocaleString(),
														batch.requested_quantity.toLocaleString()
													) }
												</span>
												<progress
													max={ 100 }
													value={ calculateProgressPercent(
														batch.generated_quantity,
														batch.requested_quantity
													) }
													aria-label={ sprintf(
														/* translators: %s: Batch Code. */
														__(
															'Generation progress for %s',
															'tagcore'
														),
														batch.batch_code
													) }
												/>
											</div>
										</td>
										<td>
											<span className="returntag-status-label">
												{
													batchStatusLabels[
														batch.batch_status
													]
												}
											</span>
										</td>
										<td>
											{ formatDate( batch.created_at ) }
										</td>
									</tr>
								) ) }
							</tbody>
						</table>
					</div>
					{ response.next_cursor && (
						<Button
							className="returntag-load-more"
							variant="secondary"
							onClick={ loadMore }
							disabled={ loadingMore }
							isBusy={ loadingMore }
						>
							{ loadingMore
								? __( 'Loading…', 'tagcore' )
								: __( 'Load more batches', 'tagcore' ) }
						</Button>
					) }
				</>
			) }
		</section>
	);
}

function queueStateLabel( progress: BatchGenerationProgress ): string {
	const labels = {
		idle: __( 'Ready', 'tagcore' ),
		scheduled: __( 'Scheduled', 'tagcore' ),
		running: __( 'Running', 'tagcore' ),
		needs_attention: __( 'Needs attention', 'tagcore' ),
		complete: __( 'Complete', 'tagcore' ),
		unavailable: __( 'Queue status unavailable', 'tagcore' ),
	};

	return labels[ progress.queue_state ];
}

function GenerationProgressPanel( {
	progress,
}: {
	progress: BatchGenerationProgress;
} ) {
	const facts = [
		{
			label: __( 'Target quantity', 'tagcore' ),
			value: progress.requested_quantity.toLocaleString(),
		},
		{
			label: __( 'Generated quantity', 'tagcore' ),
			value: progress.generated_quantity.toLocaleString(),
		},
		{
			label: __( 'Remaining quantity', 'tagcore' ),
			value: progress.remaining_quantity.toLocaleString(),
		},
		{
			label: __( 'Failed IDs', 'tagcore' ),
			value: progress.failed_quantity.toLocaleString(),
		},
		{
			label: __( 'Started (UTC)', 'tagcore' ),
			value: progress.started_at
				? formatDate( progress.started_at )
				: __( 'Not started', 'tagcore' ),
		},
		{
			label: __( 'Completed (UTC)', 'tagcore' ),
			value: progress.completed_at
				? formatDate( progress.completed_at )
				: __( 'Not completed', 'tagcore' ),
		},
		{
			label: __( 'Last progress (UTC)', 'tagcore' ),
			value: formatDate( progress.last_progress_at ),
		},
		{
			label: __( 'Queue state', 'tagcore' ),
			value: queueStateLabel( progress ),
		},
	];

	return (
		<section
			className="returntag-generation-panel"
			aria-labelledby="returntag-generation-title"
		>
			<div className="returntag-generation-heading">
				<div>
					<h2 id="returntag-generation-title">
						{ __( 'Tag ID generation', 'tagcore' ) }
					</h2>
					<p>
						{ __(
							'Progress counts only Tag IDs committed safely to this Batch.',
							'tagcore'
						) }
					</p>
				</div>
				<strong className="returntag-generation-percentage">
					{ sprintf(
						/* translators: %d: Generation percentage. */
						__( '%d%% complete', 'tagcore' ),
						progress.progress_percent
					) }
				</strong>
			</div>
			<progress
				className="returntag-generation-progress"
				max={ 100 }
				value={ progress.progress_percent }
				aria-label={ __(
					'Batch Tag ID generation progress',
					'tagcore'
				) }
			/>
			<div className="returntag-generation-count">
				{ sprintf(
					/* translators: 1: Generated quantity. 2: Requested quantity. */
					__( '%1$s of %2$s Tag IDs generated', 'tagcore' ),
					progress.generated_quantity.toLocaleString(),
					progress.requested_quantity.toLocaleString()
				) }
			</div>
			<dl className="returntag-generation-facts">
				{ facts.map( ( fact ) => (
					<div key={ fact.label }>
						<dt>{ fact.label }</dt>
						<dd>{ fact.value }</dd>
					</div>
				) ) }
			</dl>
			<p className="returntag-generation-note">
				{ __(
					'Remaining IDs are pending work, not failed IDs. Queue interruptions can be resumed without regenerating committed IDs.',
					'tagcore'
				) }
			</p>
		</section>
	);
}

function BatchExportPanel( {
	batch,
	onStatusChange,
}: {
	batch: BatchRecord;
	onStatusChange: ( status: BatchStatus ) => void;
} ) {
	const [ history, setHistory ] =
		useState< BatchExportHistoryResponse | null >( null );
	const [ historyError, setHistoryError ] = useState< string | null >( null );
	const [ loadingMore, setLoadingMore ] = useState( false );
	const [ exporting, setExporting ] = useState( false );
	const [ confirmOpen, setConfirmOpen ] = useState( false );
	const [ completed, setCompleted ] =
		useState< BatchExportDownloadMetadata | null >( null );

	const loadHistory = useCallback( async () => {
		try {
			const response = await apiFetch< BatchExportHistoryResponse >( {
				path: `${ config.restPath }/batches/${ batch.batch_id }/exports?per_page=20`,
			} );
			setHistory( response );
			setHistoryError( null );
			return response;
		} catch ( reason ) {
			const apiError = reason as ApiError;
			setHistoryError(
				apiError.message ??
					__( 'TagCore could not load export history.', 'tagcore' )
			);
			return null;
		}
	}, [ batch.batch_id ] );

	useEffect( () => {
		void loadHistory();
	}, [ loadHistory ] );

	const loadMore = async () => {
		if ( ! history?.next_cursor ) {
			return;
		}

		setLoadingMore( true );
		setHistoryError( null );

		try {
			const next = await apiFetch< BatchExportHistoryResponse >( {
				path: `${ config.restPath }/batches/${
					batch.batch_id
				}/exports?per_page=20&cursor=${ encodeURIComponent(
					history.next_cursor
				) }`,
			} );
			setHistory( {
				items: appendExportRecords( history.items, next.items ),
				next_cursor: next.next_cursor,
			} );
		} catch ( reason ) {
			const apiError = reason as ApiError;
			setHistoryError(
				apiError.message ??
					__(
						'TagCore could not load more export history.',
						'tagcore'
					)
			);
		} finally {
			setLoadingMore( false );
		}
	};

	const exportCsv = async () => {
		setExporting( true );
		setCompleted( null );
		setHistoryError( null );

		try {
			const response = await apiFetch< never, false >( {
				path: `${ config.restPath }/batches/${ batch.batch_id }/exports`,
				method: 'POST',
				parse: false,
			} );
			const metadata = readDownloadMetadata( response );
			const blob = await response.blob();

			if ( blob.size === 0 ) {
				throw new Error( 'Batch export response is empty.' );
			}

			const objectUrl = URL.createObjectURL( blob );
			const anchor = document.createElement( 'a' );
			anchor.href = objectUrl;
			anchor.download = metadata.filename;
			anchor.hidden = true;
			document.body.appendChild( anchor );
			anchor.click();
			anchor.remove();
			window.setTimeout( () => URL.revokeObjectURL( objectUrl ), 0 );

			setCompleted( metadata );
			setConfirmOpen( false );
			onStatusChange( metadata.batchStatus );
			await loadHistory();
		} catch ( reason ) {
			const apiError = reason as ApiError;
			setHistoryError(
				apiError.message ??
					__(
						'TagCore could not complete the CSV export.',
						'tagcore'
					)
			);
		} finally {
			setExporting( false );
		}
	};

	const allowed = canExportBatch(
		batch.batch_status,
		batch.generated_quantity,
		batch.requested_quantity
	);
	const reExport = isReExport( batch.batch_status );
	let confirmationLabel: string = reExport
		? __( 'Re-export CSV', 'tagcore' )
		: __( 'Export CSV', 'tagcore' );

	if ( exporting ) {
		confirmationLabel = __( 'Preparing CSV…', 'tagcore' );
	}

	return (
		<section
			className="returntag-export-panel"
			aria-labelledby="returntag-export-title"
		>
			<div className="returntag-export-heading">
				<div className="returntag-export-title">
					<Icon icon={ download } size={ 24 } />
					<div>
						<h2 id="returntag-export-title">
							{ __( 'Production export', 'tagcore' ) }
						</h2>
						<p>
							{ __(
								'Create a deterministic, checksummed CSV for manufacturing.',
								'tagcore'
							) }
						</p>
					</div>
				</div>
				{ allowed && (
					<Button
						variant={ reExport ? 'secondary' : 'primary' }
						onClick={ () => setConfirmOpen( true ) }
						disabled={ exporting }
						isBusy={ exporting }
					>
						{ reExport
							? __( 'Re-export CSV', 'tagcore' )
							: __( 'Export CSV', 'tagcore' ) }
					</Button>
				) }
			</div>

			{ ! allowed &&
				[ 'suspended', 'voided' ].includes( batch.batch_status ) && (
					<Notice status="warning" isDismissible={ false }>
						{ __(
							'Manufacturing export is unavailable while this Batch is suspended or voided. Existing audit history remains visible.',
							'tagcore'
						) }
					</Notice>
				) }

			{ completed && (
				<Notice
					status="success"
					onRemove={ () => setCompleted( null ) }
				>
					<p>
						{ sprintf(
							/* translators: 1: Export version. 2: Export row count. */
							__(
								'CSV version %1$s was prepared with %2$s Tag IDs and the download has started.',
								'tagcore'
							),
							completed.version.toLocaleString(),
							completed.rowCount.toLocaleString()
						) }
					</p>
					<p>
						<strong>{ __( 'SHA-256:', 'tagcore' ) }</strong>{ ' ' }
						<code className="returntag-checksum">
							{ completed.checksum }
						</code>
					</p>
				</Notice>
			) }

			{ historyError && (
				<Notice status="error" isDismissible={ false }>
					{ historyError }
				</Notice>
			) }

			<h3>{ __( 'Export history', 'tagcore' ) }</h3>

			{ ! history && ! historyError && (
				<div className="returntag-export-loading">
					<Spinner />
					<span>{ __( 'Loading export history…', 'tagcore' ) }</span>
				</div>
			) }

			{ history && history.items.length === 0 && (
				<p className="returntag-export-empty">
					{ __(
						'No manufacturing CSV has been exported for this Batch.',
						'tagcore'
					) }
				</p>
			) }

			{ history && history.items.length > 0 && (
				<>
					<div
						id="returntag-export-history-table"
						className="returntag-table-wrap"
					>
						<table className="widefat striped returntag-export-table">
							<thead>
								<tr>
									<th scope="col">
										{ __( 'Version', 'tagcore' ) }
									</th>
									<th scope="col">
										{ __( 'Rows', 'tagcore' ) }
									</th>
									<th scope="col">
										{ __( 'Format', 'tagcore' ) }
									</th>
									<th scope="col">
										{ __( 'Operator', 'tagcore' ) }
									</th>
									<th scope="col">
										{ __( 'Exported at (UTC)', 'tagcore' ) }
									</th>
									<th scope="col">
										{ __( 'SHA-256', 'tagcore' ) }
									</th>
								</tr>
							</thead>
							<tbody>
								{ history.items.map( ( item ) => (
									<tr key={ item.export_version }>
										<td
											data-label={ __(
												'Version',
												'tagcore'
											) }
										>
											{ item.export_version }
										</td>
										<td
											data-label={ __(
												'Rows',
												'tagcore'
											) }
										>
											{ item.row_count.toLocaleString() }
										</td>
										<td
											data-label={ __(
												'Format',
												'tagcore'
											) }
										>
											{ item.file_format.toUpperCase() }
										</td>
										<td
											data-label={ __(
												'Operator',
												'tagcore'
											) }
										>
											{ item.created_by_name }
										</td>
										<td
											data-label={ __(
												'Exported at (UTC)',
												'tagcore'
											) }
										>
											{ formatDate( item.created_at ) }
										</td>
										<td
											data-label={ __(
												'SHA-256',
												'tagcore'
											) }
										>
											<code className="returntag-checksum">
												{ item.file_checksum }
											</code>
										</td>
									</tr>
								) ) }
							</tbody>
						</table>
					</div>

					{ history.next_cursor && (
						<Button
							className="returntag-load-more"
							variant="secondary"
							onClick={ loadMore }
							disabled={ loadingMore }
							isBusy={ loadingMore }
							aria-controls="returntag-export-history-table"
						>
							{ loadingMore
								? __( 'Loading…', 'tagcore' )
								: __( 'Load more exports', 'tagcore' ) }
						</Button>
					) }
				</>
			) }

			{ confirmOpen && (
				<Modal
					title={
						reExport
							? __( 'Confirm CSV re-export', 'tagcore' )
							: __( 'Confirm CSV export', 'tagcore' )
					}
					onRequestClose={ () => setConfirmOpen( false ) }
				>
					<div className="returntag-generation-confirm">
						<div className="returntag-generation-warning">
							<Icon icon={ cautionFilled } size={ 24 } />
							<p>
								<strong>
									{ reExport
										? __(
												'This creates a new immutable export audit version.',
												'tagcore'
										  )
										: __(
												'This marks the Batch as exported.',
												'tagcore'
										  ) }
								</strong>
								<span>
									{ __(
										'The same permanent Tag IDs are used. No IDs are generated, replaced, or made reusable.',
										'tagcore'
									) }
								</span>
							</p>
						</div>
						<dl>
							<div>
								<dt>{ __( 'Batch Code', 'tagcore' ) }</dt>
								<dd>{ batch.batch_code }</dd>
							</div>
							<div>
								<dt>{ __( 'Rows', 'tagcore' ) }</dt>
								<dd>
									{ batch.generated_quantity.toLocaleString() }
								</dd>
							</div>
							<div>
								<dt>{ __( 'Ordering', 'tagcore' ) }</dt>
								<dd>{ __( 'Tag ID ascending', 'tagcore' ) }</dd>
							</div>
							<div>
								<dt>{ __( 'Activation', 'tagcore' ) }</dt>
								<dd>
									{ batch.activation_enabled
										? __( 'Unchanged (enabled)', 'tagcore' )
										: __(
												'Unchanged (disabled)',
												'tagcore'
										  ) }
								</dd>
							</div>
						</dl>
						<div className="returntag-modal-actions">
							<Button
								variant="tertiary"
								onClick={ () => setConfirmOpen( false ) }
								disabled={ exporting }
							>
								{ __( 'Cancel', 'tagcore' ) }
							</Button>
							<Button
								variant="primary"
								onClick={ exportCsv }
								disabled={ exporting }
								isBusy={ exporting }
							>
								{ confirmationLabel }
							</Button>
						</div>
					</div>
				</Modal>
			) }
		</section>
	);
}

function BatchLifecyclePanel( {
	batch,
	onChange,
}: {
	batch: BatchRecord;
	onChange: ( lifecycle: BatchLifecycleResponse ) => void;
} ) {
	const [ lifecycle, setLifecycle ] =
		useState< BatchLifecycleResponse | null >( null );
	const [ error, setError ] = useState< string | null >( null );
	const [ action, setAction ] = useState< BatchLifecycleAction | null >(
		null
	);
	const [ confirmation, setConfirmation ] = useState( '' );
	const [ changing, setChanging ] = useState( false );

	useEffect( () => {
		let cancelled = false;

		apiFetch< BatchLifecycleResponse >( {
			path: `${ config.restPath }/batches/${ batch.batch_id }/lifecycle`,
		} )
			.then( ( response ) => {
				if ( ! cancelled ) {
					setLifecycle( response );
					setError( null );
				}
			} )
			.catch( ( reason: ApiError ) => {
				if ( ! cancelled ) {
					setError(
						reason.message ??
							__(
								'TagCore could not load Batch lifecycle controls.',
								'tagcore'
							)
					);
				}
			} );

		return () => {
			cancelled = true;
		};
	}, [ batch.batch_id, batch.batch_status ] );

	const closeModal = () => {
		if ( changing ) {
			return;
		}

		setAction( null );
		setConfirmation( '' );
	};

	const changeLifecycle = async () => {
		if ( ! action || ! lifecycle ) {
			return;
		}

		setChanging( true );
		setError( null );

		try {
			const response = await apiFetch< BatchLifecycleResponse >( {
				path: `${ config.restPath }/batches/${ batch.batch_id }/${ action }`,
				method: 'POST',
				data: {
					expected_status: lifecycle.batch_status,
					...( action === 'void'
						? { batch_code_confirmation: confirmation }
						: {} ),
				},
			} );

			setLifecycle( response );
			setAction( null );
			setConfirmation( '' );
			onChange( response );
		} catch ( reason ) {
			const apiError = reason as ApiError;
			setError(
				apiError.message ??
					__(
						'TagCore could not complete the Batch lifecycle action.',
						'tagcore'
					)
			);
		} finally {
			setChanging( false );
		}
	};

	const actions = lifecycle
		? availableLifecycleActions(
				lifecycle.batch_status,
				lifecycle.release_ready
		  )
		: [];
	const modalTitles: Record< BatchLifecycleAction, string > = {
		release: __( 'Release Batch', 'tagcore' ),
		suspend: __( 'Suspend Batch', 'tagcore' ),
		void: __( 'Void Batch permanently', 'tagcore' ),
	};
	const confirmLabels: Record< BatchLifecycleAction, string > = {
		release: __( 'Release Batch', 'tagcore' ),
		suspend: __( 'Suspend Batch', 'tagcore' ),
		void: __( 'Void Batch', 'tagcore' ),
	};
	const warningTitles: Record< BatchLifecycleAction, string > = {
		release: __(
			'New activation will be enabled for this Batch.',
			'tagcore'
		),
		suspend: __( 'New activation will stop immediately.', 'tagcore' ),
		void: __( 'This action cannot be reversed.', 'tagcore' ),
	};
	const warningMessages: Record< BatchLifecycleAction, string > = {
		release: __(
			'Release requires complete inventory and an audited CSV export. The global activation control remains authoritative.',
			'tagcore'
		),
		suspend: __(
			'Already active owners keep access. Generated Tag IDs and export history remain retained.',
			'tagcore'
		),
		void: __(
			'Unregistered IDs become permanently unavailable. No Tag ID or audit record will be deleted or reused.',
			'tagcore'
		),
	};

	return (
		<section
			className="returntag-lifecycle-panel"
			aria-labelledby="returntag-lifecycle-title"
		>
			<div className="returntag-lifecycle-heading">
				<div>
					<h2 id="returntag-lifecycle-title">
						{ __( 'Release and incident controls', 'tagcore' ) }
					</h2>
					<p>
						{ __(
							'Control whether unregistered Tags in this Batch may enter activation. Existing active owners are not changed.',
							'tagcore'
						) }
					</p>
				</div>
				{ lifecycle && (
					<span
						className={ `returntag-activation-state ${
							lifecycle.effective_activation_enabled
								? 'is-enabled'
								: 'is-disabled'
						}` }
					>
						{ lifecycle.effective_activation_enabled
							? __( 'Activation available', 'tagcore' )
							: __( 'Activation unavailable', 'tagcore' ) }
					</span>
				) }
			</div>

			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }

			{ ! lifecycle && ! error && (
				<div className="returntag-loading">
					<Spinner />
					<span>
						{ __( 'Loading lifecycle controls…', 'tagcore' ) }
					</span>
				</div>
			) }

			{ lifecycle && (
				<>
					{ lifecycle.activation_enabled &&
						! lifecycle.global_activation_enabled && (
							<Notice status="warning" isDismissible={ false }>
								{ __(
									'This Batch is released, but the global activation control is disabled. New activation remains unavailable until an authorized operator restores the global control.',
									'tagcore'
								) }
							</Notice>
						) }

					<dl className="returntag-lifecycle-facts">
						<div>
							<dt>{ __( 'Unregistered', 'tagcore' ) }</dt>
							<dd>
								{ lifecycle.tag_counts.unregistered.toLocaleString() }
							</dd>
						</div>
						<div>
							<dt>{ __( 'Active', 'tagcore' ) }</dt>
							<dd>
								{ lifecycle.tag_counts.active.toLocaleString() }
							</dd>
						</div>
						<div>
							<dt>{ __( 'Suspended Tags', 'tagcore' ) }</dt>
							<dd>
								{ lifecycle.tag_counts.suspended.toLocaleString() }
							</dd>
						</div>
						<div>
							<dt>{ __( 'Retired', 'tagcore' ) }</dt>
							<dd>
								{ lifecycle.tag_counts.retired.toLocaleString() }
							</dd>
						</div>
					</dl>

					{ actions.length > 0 ? (
						<div className="returntag-lifecycle-actions">
							{ actions.includes( 'release' ) && (
								<Button
									variant="primary"
									onClick={ () => setAction( 'release' ) }
								>
									{ __( 'Release Batch', 'tagcore' ) }
								</Button>
							) }
							{ actions.includes( 'suspend' ) && (
								<Button
									variant="secondary"
									onClick={ () => setAction( 'suspend' ) }
								>
									{ __( 'Suspend Batch', 'tagcore' ) }
								</Button>
							) }
							{ actions.includes( 'void' ) && (
								<Button
									variant="tertiary"
									isDestructive
									onClick={ () => setAction( 'void' ) }
								>
									{ __( 'Void Batch', 'tagcore' ) }
								</Button>
							) }
						</div>
					) : (
						<p className="returntag-lifecycle-terminal">
							{ lifecycle.batch_status === 'voided'
								? __(
										'This Batch is permanently voided. Its Tag IDs remain retained and can never be reused.',
										'tagcore'
								  )
								: __(
										'No lifecycle action is available for the current Batch status.',
										'tagcore'
								  ) }
						</p>
					) }
				</>
			) }

			{ action && lifecycle && (
				<Modal
					title={ modalTitles[ action ] }
					onRequestClose={ closeModal }
					className="returntag-lifecycle-confirm"
					shouldCloseOnClickOutside={ ! changing }
				>
					<div className="returntag-lifecycle-warning">
						<Icon icon={ cautionFilled } size={ 24 } />
						<div>
							<strong>{ warningTitles[ action ] }</strong>
							<span>{ warningMessages[ action ] }</span>
						</div>
					</div>

					{ action === 'void' && (
						<TextControl
							label={ sprintf(
								/* translators: %s: Batch Code. */
								__(
									'Enter %s to confirm permanent void',
									'tagcore'
								),
								lifecycle.batch_code
							) }
							value={ confirmation }
							onChange={ setConfirmation }
							autoComplete="off"
							disabled={ changing }
						/>
					) }

					{ action === 'release' &&
						! lifecycle.global_activation_enabled && (
							<Notice status="warning" isDismissible={ false }>
								{ __(
									'Global activation is currently disabled. This Batch can be released, but customer activation will remain unavailable.',
									'tagcore'
								) }
							</Notice>
						) }

					<div className="returntag-modal-actions">
						<Button
							variant="tertiary"
							onClick={ closeModal }
							disabled={ changing }
						>
							{ __( 'Cancel', 'tagcore' ) }
						</Button>
						<Button
							variant="primary"
							isDestructive={ action === 'void' }
							onClick={ changeLifecycle }
							disabled={
								changing ||
								( action === 'void' &&
									! canConfirmVoid(
										confirmation,
										lifecycle.batch_code
									) )
							}
							isBusy={ changing }
						>
							{ confirmLabels[ action ] }
						</Button>
					</div>
				</Modal>
			) }
		</section>
	);
}

function BatchTagInventoryPanel( {
	batchId,
	total,
}: {
	batchId: string;
	total: number;
} ) {
	const [ response, setResponse ] =
		useState< BatchTagInventoryResponse | null >( null );
	const [ error, setError ] = useState< string | null >( null );
	const [ loadingMore, setLoadingMore ] = useState( false );
	const [ focusTagId, setFocusTagId ] = useState< string | null >( null );

	useEffect( () => {
		let cancelled = false;

		apiFetch< BatchTagInventoryResponse >( {
			path: `${ config.restPath }/batches/${ batchId }/tags?per_page=50`,
		} )
			.then( ( next ) => {
				if ( ! cancelled ) {
					setResponse( next );
					setError( null );
				}
			} )
			.catch( ( reason: ApiError ) => {
				if ( ! cancelled ) {
					setError(
						reason.message ??
							__(
								'TagCore could not load generated Tag IDs.',
								'tagcore'
							)
					);
				}
			} );

		return () => {
			cancelled = true;
		};
	}, [ batchId ] );

	useEffect( () => {
		if ( ! focusTagId ) {
			return;
		}

		document.getElementById( `returntag-tag-row-${ focusTagId }` )?.focus();
		setFocusTagId( null );
	}, [ focusTagId, response ] );

	const loadMore = async () => {
		if ( ! response?.next_cursor ) {
			return;
		}

		setLoadingMore( true );
		setError( null );

		try {
			const next = await apiFetch< BatchTagInventoryResponse >( {
				path: `${
					config.restPath
				}/batches/${ batchId }/tags?per_page=50&cursor=${ encodeURIComponent(
					response.next_cursor
				) }`,
			} );
			const firstNewTagId = next.items[ 0 ]?.tag_id ?? null;

			setResponse( {
				items: appendInventoryItems( response.items, next.items ),
				next_cursor: next.next_cursor,
			} );
			setFocusTagId( firstNewTagId );
		} catch ( reason ) {
			const apiError = reason as ApiError;
			setError(
				apiError.message ??
					__(
						'TagCore could not load more generated Tag IDs.',
						'tagcore'
					)
			);
		} finally {
			setLoadingMore( false );
		}
	};

	return (
		<section
			className="returntag-inventory-panel"
			aria-labelledby="returntag-inventory-title"
		>
			<div className="returntag-inventory-heading">
				<div>
					<h2 id="returntag-inventory-title">
						{ __( 'Generated Tag IDs', 'tagcore' ) }
					</h2>
					<p>
						{ __(
							'Complete manufacturing inventory in deterministic Tag ID order.',
							'tagcore'
						) }
					</p>
				</div>
				{ response && (
					<strong>
						{ sprintf(
							/* translators: 1: Loaded Tag IDs. 2: Total generated Tag IDs. */
							__( '%1$s of %2$s loaded', 'tagcore' ),
							response.items.length.toLocaleString(),
							total.toLocaleString()
						) }
					</strong>
				) }
			</div>

			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }

			{ ! response && ! error && (
				<div className="returntag-loading">
					<Spinner />
					<span>
						{ __( 'Loading generated Tag IDs…', 'tagcore' ) }
					</span>
				</div>
			) }

			{ response && response.items.length === 0 && (
				<div className="returntag-empty">
					<h3>{ __( 'No generated Tag IDs found', 'tagcore' ) }</h3>
					<p>
						{ __(
							'The Batch inventory is empty. Generation data should be reviewed before export.',
							'tagcore'
						) }
					</p>
				</div>
			) }

			{ response && response.items.length > 0 && (
				<>
					<div
						id="returntag-inventory-table"
						className="returntag-table-wrap"
					>
						<table className="widefat striped returntag-inventory-table">
							<thead>
								<tr>
									<th scope="col">
										{ __( 'Tag ID', 'tagcore' ) }
									</th>
									<th scope="col">
										{ __( 'Tag Status', 'tagcore' ) }
									</th>
									<th scope="col">
										{ __(
											'Generated at (UTC)',
											'tagcore'
										) }
									</th>
								</tr>
							</thead>
							<tbody>
								{ response.items.map( ( item ) => (
									<tr
										id={ `returntag-tag-row-${ item.tag_id }` }
										key={ item.tag_id }
										tabIndex={ -1 }
									>
										<td
											data-label={ __(
												'Tag ID',
												'tagcore'
											) }
										>
											<code>{ item.tag_id }</code>
										</td>
										<td
											data-label={ __(
												'Tag Status',
												'tagcore'
											) }
										>
											<span className="returntag-status-label">
												{
													tagStatusLabels[
														item.tag_status
													]
												}
											</span>
										</td>
										<td
											data-label={ __(
												'Generated at (UTC)',
												'tagcore'
											) }
										>
											{ formatDate( item.created_at ) }
										</td>
									</tr>
								) ) }
							</tbody>
						</table>
					</div>

					{ response.next_cursor && (
						<Button
							className="returntag-load-more"
							variant="secondary"
							onClick={ loadMore }
							disabled={ loadingMore }
							isBusy={ loadingMore }
							aria-controls="returntag-inventory-table"
						>
							{ loadingMore
								? __( 'Loading…', 'tagcore' )
								: __( 'Load more Tag IDs', 'tagcore' ) }
						</Button>
					) }

					<p className="screen-reader-text" aria-live="polite">
						{ sprintf(
							/* translators: 1: Loaded Tag IDs. 2: Total generated Tag IDs. */
							__( '%1$s of %2$s Tag IDs loaded.', 'tagcore' ),
							response.items.length.toLocaleString(),
							total.toLocaleString()
						) }
					</p>
				</>
			) }
		</section>
	);
}

function BatchDetailScreen( { batchId }: { batchId: string } ) {
	const [ batch, setBatch ] = useState< BatchRecord | null >( null );
	const [ progress, setProgress ] =
		useState< BatchGenerationProgress | null >( null );
	const [ error, setError ] = useState< string | null >( null );
	const [ progressError, setProgressError ] = useState< string | null >(
		null
	);
	const [ notice, setNotice ] = useState< string | null >( null );
	const [ confirmOpen, setConfirmOpen ] = useState( false );
	const [ starting, setStarting ] = useState( false );

	const loadProgress = useCallback( async () => {
		try {
			const next = await apiFetch< BatchGenerationProgress >( {
				path: `${ config.restPath }/batches/${ batchId }/generation`,
			} );
			setProgress( next );
			setProgressError( null );
			setBatch( ( current ) =>
				current
					? {
							...current,
							batch_status: next.batch_status,
							generated_quantity: next.generated_quantity,
							updated_at: next.last_progress_at,
					  }
					: current
			);
			return next;
		} catch ( reason ) {
			const apiError = reason as ApiError;
			setProgressError(
				apiError.message ??
					__(
						'TagCore could not load generation progress.',
						'tagcore'
					)
			);
			return null;
		}
	}, [ batchId ] );

	useEffect( () => {
		Promise.all( [
			apiFetch< BatchRecord >( {
				path: `${ config.restPath }/batches/${ batchId }`,
			} ),
			apiFetch< BatchGenerationProgress >( {
				path: `${ config.restPath }/batches/${ batchId }/generation`,
			} ),
		] )
			.then( ( [ loadedBatch, loadedProgress ] ) => {
				setBatch( loadedBatch );
				setProgress( loadedProgress );
			} )
			.catch( ( reason: ApiError ) => {
				setError(
					reason.message ??
						__( 'TagCore could not load this Batch.', 'tagcore' )
				);
			} );
	}, [ batchId ] );

	useEffect( () => {
		if (
			! progress ||
			! shouldPollGeneration(
				progress,
				document.visibilityState === 'visible'
			)
		) {
			return;
		}

		let cancelled = false;
		let timer: ReturnType< typeof setTimeout > | undefined;

		const schedule = ( current: BatchGenerationProgress ) => {
			timer = setTimeout( async () => {
				if ( cancelled || document.visibilityState !== 'visible' ) {
					return;
				}

				const next = await loadProgress();

				if ( next && shouldPollGeneration( next, true ) ) {
					schedule( next );
				}
			}, generationPollDelay( current.poll_after_ms ) );
		};

		const onVisibilityChange = () => {
			if ( document.visibilityState === 'visible' ) {
				void loadProgress();
			}
		};

		document.addEventListener( 'visibilitychange', onVisibilityChange );
		schedule( progress );

		return () => {
			cancelled = true;
			if ( timer ) {
				clearTimeout( timer );
			}
			document.removeEventListener(
				'visibilitychange',
				onVisibilityChange
			);
		};
	}, [ loadProgress, progress ] );

	const startGeneration = async () => {
		setStarting( true );
		setProgressError( null );
		setNotice( null );

		try {
			await apiFetch( {
				path: `${ config.restPath }/batches/${ batchId }/generation`,
				method: 'POST',
			} );
			setConfirmOpen( false );
			setNotice(
				progress?.can_retry
					? __(
							'Generation was safely rescheduled from its last committed checkpoint.',
							'tagcore'
					  )
					: __(
							'Tag ID generation was scheduled successfully.',
							'tagcore'
					  )
			);
			await loadProgress();
		} catch ( reason ) {
			const apiError = reason as ApiError;
			setProgressError(
				apiError.message ??
					__(
						'TagCore could not schedule Tag ID generation.',
						'tagcore'
					)
			);
		} finally {
			setStarting( false );
		}
	};

	if ( error ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ error }
			</Notice>
		);
	}

	if ( ! batch || ! progress ) {
		return (
			<div className="returntag-loading">
				<Spinner />
				<span>{ __( 'Loading Batch…', 'tagcore' ) }</span>
			</div>
		);
	}

	return (
		<section aria-labelledby="returntag-detail-title">
			<header className="returntag-page-header returntag-list-header">
				<div>
					<h1 id="returntag-detail-title">{ batch.batch_code }</h1>
					<p>
						{ __(
							'Review manufacturing details and monitor committed Tag ID generation.',
							'tagcore'
						) }
					</p>
				</div>
				<Button variant="secondary" href={ config.listUrl }>
					{ __( 'Back to batches', 'tagcore' ) }
				</Button>
			</header>

			{ notice && (
				<Notice status="success" onRemove={ () => setNotice( null ) }>
					{ notice }
				</Notice>
			) }

			{ progressError && (
				<Notice status="error" isDismissible={ false }>
					{ progressError }
				</Notice>
			) }

			{ progress.queue_state === 'needs_attention' && (
				<Notice status="warning" isDismissible={ false }>
					<p>
						{ __(
							'Generation is paused because no pending worker is available. Committed Tag IDs remain safe.',
							'tagcore'
						) }
					</p>
					<Button
						variant="secondary"
						onClick={ startGeneration }
						disabled={ starting }
						isBusy={ starting }
					>
						{ starting
							? __( 'Rescheduling…', 'tagcore' )
							: __( 'Retry generation', 'tagcore' ) }
					</Button>
				</Notice>
			) }

			{ progress.queue_state === 'unavailable' && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'Queue status is temporarily unavailable. No generation action is offered until TagCore can verify the worker state.',
						'tagcore'
					) }
				</Notice>
			) }

			{ progress.queue_state === 'complete' && (
				<Notice status="success" isDismissible={ false }>
					{ __(
						'All requested Tag IDs were generated and committed successfully.',
						'tagcore'
					) }
				</Notice>
			) }

			<StatusBand
				status={ batch.batch_status }
				generatedQuantity={ batch.generated_quantity }
				activationEnabled={ batch.activation_enabled }
				createdAt={ batch.created_at }
			/>

			{ progress.can_start && (
				<div className="returntag-generation-start">
					<div>
						<h2>
							{ __( 'Ready to generate Tag IDs', 'tagcore' ) }
						</h2>
						<p>
							{ __(
								'Generation runs safely in the background. Activation remains disabled.',
								'tagcore'
							) }
						</p>
					</div>
					<Button
						variant="primary"
						onClick={ () => setConfirmOpen( true ) }
					>
						{ __( 'Generate Tag IDs', 'tagcore' ) }
					</Button>
				</div>
			) }

			<GenerationProgressPanel progress={ progress } />

			{ shouldShowBatchInventory(
				batch.batch_status,
				progress.generated_quantity,
				progress.requested_quantity
			) && (
				<>
					<BatchExportPanel
						batch={ batch }
						onStatusChange={ ( status ) =>
							setBatch( ( current ) =>
								current
									? {
											...current,
											batch_status: status,
									  }
									: current
							)
						}
					/>
					<BatchLifecyclePanel
						batch={ batch }
						onChange={ ( lifecycle ) => {
							setBatch( ( current ) =>
								current
									? {
											...current,
											batch_status:
												lifecycle.batch_status,
											activation_enabled:
												lifecycle.activation_enabled,
											updated_at: lifecycle.updated_at,
									  }
									: current
							);
							setProgress( ( current ) =>
								current
									? {
											...current,
											batch_status:
												lifecycle.batch_status,
											last_progress_at:
												lifecycle.updated_at,
									  }
									: current
							);
						} }
					/>
					<BatchTagInventoryPanel
						batchId={ batchId }
						total={ progress.generated_quantity }
					/>
				</>
			) }

			<dl className="returntag-created-details">
				<div>
					<dt>{ __( 'Tag type', 'tagcore' ) }</dt>
					<dd>{ tagTypeLabels[ batch.tag_type ] }</dd>
				</div>
				<div>
					<dt>{ __( 'Model code', 'tagcore' ) }</dt>
					<dd>{ batch.model_code || '—' }</dd>
				</div>
				<div>
					<dt>{ __( 'Smart network', 'tagcore' ) }</dt>
					<dd>{ smartNetworkLabels[ batch.smart_network ] }</dd>
				</div>
				<div>
					<dt>{ __( 'Requested quantity', 'tagcore' ) }</dt>
					<dd>{ batch.requested_quantity.toLocaleString() }</dd>
				</div>
				<div>
					<dt>{ __( 'Manufacturer', 'tagcore' ) }</dt>
					<dd>{ batch.manufacturer || '—' }</dd>
				</div>
				<div>
					<dt>{ __( 'Sales channel', 'tagcore' ) }</dt>
					<dd>{ batch.sales_channel || '—' }</dd>
				</div>
				<div className="returntag-detail-notes">
					<dt>{ __( 'Notes', 'tagcore' ) }</dt>
					<dd>{ batch.notes || '—' }</dd>
				</div>
			</dl>

			<div className="screen-reader-text" aria-live="polite">
				{ sprintf(
					/* translators: 1: Generated quantity. 2: Requested quantity. */
					__( '%1$s of %2$s Tag IDs generated.', 'tagcore' ),
					progress.generated_quantity.toLocaleString(),
					progress.requested_quantity.toLocaleString()
				) }
			</div>

			{ confirmOpen && (
				<Modal
					title={ __( 'Confirm Tag ID generation', 'tagcore' ) }
					onRequestClose={ () => setConfirmOpen( false ) }
				>
					<div className="returntag-generation-confirm">
						<div className="returntag-generation-warning">
							<Icon icon={ cautionFilled } size={ 24 } />
							<p>
								<strong>
									{ __(
										'This creates permanent public Tag IDs.',
										'tagcore'
									) }
								</strong>
								<span>
									{ __(
										'Generated IDs are also activation IDs and can never be reused.',
										'tagcore'
									) }
								</span>
							</p>
						</div>
						<dl>
							<div>
								<dt>{ __( 'Batch Code', 'tagcore' ) }</dt>
								<dd>{ batch.batch_code }</dd>
							</div>
							<div>
								<dt>{ __( 'Tag type', 'tagcore' ) }</dt>
								<dd>{ tagTypeLabels[ batch.tag_type ] }</dd>
							</div>
							<div>
								<dt>
									{ __( 'Requested quantity', 'tagcore' ) }
								</dt>
								<dd>
									{ batch.requested_quantity.toLocaleString() }
								</dd>
							</div>
							<div>
								<dt>{ __( 'Activation', 'tagcore' ) }</dt>
								<dd>{ __( 'Remains disabled', 'tagcore' ) }</dd>
							</div>
						</dl>
						<div className="returntag-modal-actions">
							<Button
								variant="tertiary"
								onClick={ () => setConfirmOpen( false ) }
								disabled={ starting }
							>
								{ __( 'Cancel', 'tagcore' ) }
							</Button>
							<Button
								variant="primary"
								onClick={ startGeneration }
								disabled={ starting }
								isBusy={ starting }
							>
								{ starting
									? __( 'Scheduling…', 'tagcore' )
									: sprintf(
											/* translators: %s: Requested quantity. */
											__(
												'Generate %s Tag IDs',
												'tagcore'
											),
											batch.requested_quantity.toLocaleString()
									  ) }
							</Button>
						</div>
					</div>
				</Modal>
			) }
		</section>
	);
}

function AdminApp() {
	const search = new URLSearchParams( window.location.search );
	const view = search.get( 'view' );

	if ( view === 'create' ) {
		return <CreateBatchScreen />;
	}

	if ( view === 'detail' ) {
		const batchId = search.get( 'batch_id' );

		if ( batchId && /^[1-9][0-9]*$/.test( batchId ) ) {
			return <BatchDetailScreen batchId={ batchId } />;
		}
	}

	return <BatchListScreen />;
}

const rootElement = document.getElementById( 'returntag-admin-root' );

if ( rootElement ) {
	rootElement.classList.add( ADMIN_ROOT_CLASS );
	createRoot( rootElement ).render( <AdminApp /> );
}

export { ADMIN_ROOT_CLASS };
