import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Notice,
	RadioControl,
	SelectControl,
	Spinner,
	TextareaControl,
	TextControl,
} from '@wordpress/components';
import { createRoot, useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	box,
	cautionFilled,
	check,
	file,
	Icon,
	lock,
	people,
	scheduled,
} from '@wordpress/icons';

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
	batch_status: string;
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
	batch_status: string;
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

function StatusBand( { createdAt }: { createdAt: string } ) {
	const facts = [
		{
			icon: file,
			label: __( 'Status', 'tagcore' ),
			value: __( 'Draft', 'tagcore' ),
		},
		{
			icon: box,
			label: __( 'Generated', 'tagcore' ),
			value: '0',
		},
		{
			icon: lock,
			label: __( 'Activation', 'tagcore' ),
			value: __( 'Disabled', 'tagcore' ),
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
			<main
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
				<StatusBand createdAt={ created.created_at } />
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
					<Button variant="primary" href={ config.listUrl }>
						{ __( 'View batches', 'tagcore' ) }
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
			</main>
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
		<main aria-labelledby="returntag-title">
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

			<StatusBand createdAt={ config.currentTime } />

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
		</main>
	);
}

function BatchListScreen() {
	const [ response, setResponse ] = useState< BatchListResponse | null >(
		null
	);
	const [ error, setError ] = useState< string | null >( null );
	const [ loadingMore, setLoadingMore ] = useState( false );

	useEffect( () => {
		apiFetch< BatchListResponse >( {
			path: `${ config.restPath }/batches?per_page=50`,
		} )
			.then( setResponse )
			.catch( ( reason: ApiError ) => {
				setError(
					reason.message ??
						__( 'TagCore could not load Batches.', 'tagcore' )
				);
			} );
	}, [] );

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
		<main aria-labelledby="returntag-list-title">
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
											{ batch.generated_quantity.toLocaleString() }
										</td>
										<td>
											<span className="returntag-status-label">
												{ batch.batch_status }
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
		</main>
	);
}

function BatchDetailScreen( { batchId }: { batchId: string } ) {
	const [ batch, setBatch ] = useState< BatchRecord | null >( null );
	const [ error, setError ] = useState< string | null >( null );

	useEffect( () => {
		apiFetch< BatchRecord >( {
			path: `${ config.restPath }/batches/${ batchId }`,
		} )
			.then( setBatch )
			.catch( ( reason: ApiError ) => {
				setError(
					reason.message ??
						__( 'TagCore could not load this Batch.', 'tagcore' )
				);
			} );
	}, [ batchId ] );

	if ( error ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ error }
			</Notice>
		);
	}

	if ( ! batch ) {
		return (
			<div className="returntag-loading">
				<Spinner />
				<span>{ __( 'Loading Batch…', 'tagcore' ) }</span>
			</div>
		);
	}

	return (
		<main aria-labelledby="returntag-detail-title">
			<header className="returntag-page-header returntag-list-header">
				<div>
					<h1 id="returntag-detail-title">{ batch.batch_code }</h1>
					<p>
						{ __(
							'Read-only Batch details. RT-201 does not generate IDs or change lifecycle state.',
							'tagcore'
						) }
					</p>
				</div>
				<Button variant="secondary" href={ config.listUrl }>
					{ __( 'Back to batches', 'tagcore' ) }
				</Button>
			</header>
			<StatusBand createdAt={ batch.created_at } />
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
		</main>
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
