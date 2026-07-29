import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Notice,
	RadioControl,
	SelectControl,
	Spinner,
	TextControl,
} from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import {
	appendTagSearchItems,
	buildTagSearchPath,
	normalizeTagId,
	type BatchStatus,
	type TagActivationAvailability,
	type TagSearchMode,
	type TagSearchResponse,
	type TagSearchValues,
	type TagStatus,
	type TagType,
	validateTagSearch,
} from './tag-search';

interface ApiError {
	message?: string;
}

const initialValues: TagSearchValues = {
	mode: 'tag_id',
	tagId: '',
	batchCode: '',
	tagStatus: '',
};

const tagTypeLabels: Record< TagType, string > = {
	sticker: __( 'Sticker', 'tagcore' ),
	classic_tag: __( 'Classic tag', 'tagcore' ),
	smart_tag: __( 'Smart tag', 'tagcore' ),
};

const tagStatusLabels: Record< TagStatus, string > = {
	unregistered: __( 'Unregistered', 'tagcore' ),
	active: __( 'Active', 'tagcore' ),
	suspended: __( 'Suspended', 'tagcore' ),
	retired: __( 'Retired', 'tagcore' ),
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

const activationAvailabilityLabels: Record<
	TagActivationAvailability,
	string
> = {
	eligible: __( 'Eligible for activation', 'tagcore' ),
	awaiting_release: __( 'Awaiting Batch release', 'tagcore' ),
	paused_globally: __( 'Paused globally', 'tagcore' ),
	blocked_batch_control: __( 'Blocked by Batch control', 'tagcore' ),
	blocked_batch_suspended: __( 'Paused — Batch suspended', 'tagcore' ),
	blocked_batch_voided: __( 'Permanently blocked — Batch voided', 'tagcore' ),
	blocked_tag_suspended: __( 'Blocked — Tag suspended', 'tagcore' ),
	blocked_tag_retired: __(
		'Permanently unavailable — Tag retired',
		'tagcore'
	),
	existing_activation_retained: __(
		'Existing activation retained',
		'tagcore'
	),
	data_inconsistent: __( 'Review data integrity', 'tagcore' ),
};

function formatDate( value: string | null ): string {
	if ( ! value ) {
		return __( 'Never activated', 'tagcore' );
	}

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

export function TagManagementScreen( {
	restPath,
	batchListUrl,
}: {
	restPath: string;
	batchListUrl: string;
} ) {
	const [ values, setValues ] = useState< TagSearchValues >( initialValues );
	const [ response, setResponse ] = useState< TagSearchResponse | null >(
		null
	);
	const [ errors, setErrors ] = useState< Record< string, string > >( {} );
	const [ error, setError ] = useState< string | null >( null );
	const [ loading, setLoading ] = useState( false );
	const [ loadingMore, setLoadingMore ] = useState( false );
	const [ focusIndex, setFocusIndex ] = useState< number | null >( null );
	const resultsRef = useRef< HTMLDivElement | null >( null );

	useEffect( () => {
		if ( focusIndex === null ) {
			return;
		}

		resultsRef.current
			?.querySelector< HTMLTableRowElement >(
				`tr[data-result-index="${ focusIndex }"]`
			)
			?.focus();
		setFocusIndex( null );
	}, [ focusIndex, response ] );

	const updateMode = ( mode: TagSearchMode ) => {
		setValues( ( current ) => ( { ...current, mode } ) );
		setErrors( {} );
		setError( null );
		setResponse( null );
	};

	const submit = async ( event: React.FormEvent ) => {
		event.preventDefault();
		const normalized = {
			...values,
			tagId: normalizeTagId( values.tagId ),
			batchCode: values.batchCode.trim(),
		};
		const nextErrors = validateTagSearch( normalized );

		setValues( normalized );
		setErrors( nextErrors );
		setError( null );

		if ( Object.keys( nextErrors ).length > 0 ) {
			setResponse( null );
			return;
		}

		setLoading( true );
		setResponse( null );

		try {
			setResponse(
				await apiFetch< TagSearchResponse >( {
					path: buildTagSearchPath( restPath, normalized ),
				} )
			);
		} catch ( reason ) {
			const apiError = reason as ApiError;
			setResponse( null );
			setError(
				apiError.message ??
					__(
						'TagCore could not complete the Tag search.',
						'tagcore'
					)
			);
		} finally {
			setLoading( false );
		}
	};

	const loadMore = async () => {
		if ( ! response?.next_cursor ) {
			return;
		}

		setLoadingMore( true );
		setError( null );
		const previousLength = response.items.length;

		try {
			const next = await apiFetch< TagSearchResponse >( {
				path: buildTagSearchPath(
					restPath,
					values,
					response.next_cursor
				),
			} );
			setResponse( {
				items: appendTagSearchItems( response.items, next.items ),
				next_cursor: next.next_cursor,
				context: next.context,
			} );
			setFocusIndex( previousLength );
		} catch ( reason ) {
			const apiError = reason as ApiError;
			setError(
				apiError.message ??
					__( 'TagCore could not load more Tags.', 'tagcore' )
			);
		} finally {
			setLoadingMore( false );
		}
	};

	return (
		<section aria-labelledby="returntag-tag-title">
			<header className="returntag-page-header">
				<div>
					<h1 id="returntag-tag-title">
						{ __( 'Tags', 'tagcore' ) }
					</h1>
					<p>
						{ __(
							'Search the manufacturing registry by an exact Tag ID or Batch Code. This page is read-only.',
							'tagcore'
						) }
					</p>
				</div>
			</header>

			<form
				className="returntag-tag-search"
				onSubmit={ submit }
				noValidate
			>
				<RadioControl
					label={ __( 'Search by', 'tagcore' ) }
					selected={ values.mode }
					options={ [
						{
							label: __( 'Exact Tag ID', 'tagcore' ),
							value: 'tag_id',
						},
						{
							label: __( 'Exact Batch Code', 'tagcore' ),
							value: 'batch',
						},
					] }
					onChange={ ( mode ) => updateMode( mode as TagSearchMode ) }
				/>

				<div className="returntag-tag-search-fields">
					{ values.mode === 'tag_id' ? (
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							className={
								errors.tag_id ? 'has-error' : undefined
							}
							label={ __( 'Tag ID', 'tagcore' ) }
							value={ values.tagId }
							maxLength={ 12 }
							onChange={ ( tagId ) =>
								setValues( ( current ) => ( {
									...current,
									tagId,
								} ) )
							}
							help={
								errors.tag_id ??
								__(
									'Six characters. Spaces and hyphens are removed.',
									'tagcore'
								)
							}
						/>
					) : (
						<>
							<TextControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								className={
									errors.batch_code ? 'has-error' : undefined
								}
								label={ __( 'Batch Code', 'tagcore' ) }
								value={ values.batchCode }
								maxLength={ 191 }
								onChange={ ( batchCode ) =>
									setValues( ( current ) => ( {
										...current,
										batchCode,
									} ) )
								}
								help={
									errors.batch_code ??
									__(
										'Enter the complete, case-sensitive Batch Code.',
										'tagcore'
									)
								}
							/>
							<SelectControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ __( 'Tag status', 'tagcore' ) }
								value={ values.tagStatus }
								options={ [
									{
										label: __( 'All statuses', 'tagcore' ),
										value: '',
									},
									...Object.entries( tagStatusLabels ).map(
										( [ value, label ] ) => ( {
											label,
											value,
										} )
									),
								] }
								onChange={ ( tagStatus ) =>
									setValues( ( current ) => ( {
										...current,
										tagStatus:
											tagStatus as TagSearchValues[ 'tagStatus' ],
									} ) )
								}
							/>
						</>
					) }
				</div>

				<div className="returntag-actions">
					<Button
						variant="primary"
						type="submit"
						isBusy={ loading }
						disabled={ loading }
					>
						{ __( 'Search tags', 'tagcore' ) }
					</Button>
					<Button
						variant="secondary"
						onClick={ () => {
							setValues( initialValues );
							setErrors( {} );
							setError( null );
							setResponse( null );
						} }
					>
						{ __( 'Clear', 'tagcore' ) }
					</Button>
				</div>
			</form>

			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }

			{ loading && (
				<div className="returntag-loading" aria-live="polite">
					<Spinner />
					<span>{ __( 'Searching Tags…', 'tagcore' ) }</span>
				</div>
			) }

			{ response && (
				<div
					className="returntag-tag-results"
					ref={ resultsRef }
					aria-live="polite"
					aria-busy={ loadingMore }
				>
					<h2>{ __( 'Search results', 'tagcore' ) }</h2>
					<p className="returntag-tag-result-summary">
						{ sprintf(
							/* translators: %s: Number of loaded Tag results. */
							__( '%s Tags loaded.', 'tagcore' ),
							response.items.length.toLocaleString()
						) }
					</p>

					<Notice status="info" isDismissible={ false }>
						{ __(
							'Search results include suspended and voided Tag IDs retained for audit. Search visibility does not mean activation is allowed.',
							'tagcore'
						) }
					</Notice>

					{ ! response.context.global_activation_enabled && (
						<Notice status="warning" isDismissible={ false }>
							{ __(
								'Global activation is paused. Released, unregistered Tags cannot be activated until the global control is enabled.',
								'tagcore'
							) }
						</Notice>
					) }

					{ response.items.length === 0 ? (
						<div className="returntag-empty">
							<h3>{ __( 'No matching Tags', 'tagcore' ) }</h3>
							<p>
								{ __(
									'Check the exact identifier and search again.',
									'tagcore'
								) }
							</p>
						</div>
					) : (
						<div className="returntag-table-wrap">
							<table className="widefat striped returntag-tag-table">
								<thead>
									<tr>
										<th scope="col">
											{ __( 'Tag ID', 'tagcore' ) }
										</th>
										<th scope="col">
											{ __( 'Tag status', 'tagcore' ) }
										</th>
										<th scope="col">
											{ __( 'Batch', 'tagcore' ) }
										</th>
										<th scope="col">
											{ __(
												'Activation availability',
												'tagcore'
											) }
										</th>
										<th scope="col">
											{ __( 'Product', 'tagcore' ) }
										</th>
										<th scope="col">
											{ __( 'Lost Mode', 'tagcore' ) }
										</th>
										<th scope="col">
											{ __(
												'Activated (UTC)',
												'tagcore'
											) }
										</th>
										<th scope="col">
											{ __( 'Updated (UTC)', 'tagcore' ) }
										</th>
									</tr>
								</thead>
								<tbody>
									{ response.items.map( ( item, index ) => (
										<tr
											key={ item.tag_id }
											tabIndex={ -1 }
											data-result-index={ index }
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
													'Tag status',
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
													'Batch',
													'tagcore'
												) }
											>
												<div className="returntag-batch-reference">
													<a
														href={ `${ batchListUrl }&view=detail&batch_id=${ item.batch_id }` }
													>
														{ item.batch_code }
													</a>
													<span
														className={ `returntag-status-label returntag-batch-status is-${ item.batch_status }` }
													>
														{
															batchStatusLabels[
																item
																	.batch_status
															]
														}
													</span>
												</div>
											</td>
											<td
												data-label={ __(
													'Activation availability',
													'tagcore'
												) }
											>
												<span
													className={ `returntag-availability-label is-${ item.activation_availability }` }
												>
													{
														activationAvailabilityLabels[
															item
																.activation_availability
														]
													}
												</span>
											</td>
											<td
												data-label={ __(
													'Product',
													'tagcore'
												) }
											>
												{
													tagTypeLabels[
														item.tag_type
													]
												}
												{ item.model_code
													? ` · ${ item.model_code }`
													: '' }
											</td>
											<td
												data-label={ __(
													'Lost Mode',
													'tagcore'
												) }
											>
												{ item.lost_mode
													? __( 'On', 'tagcore' )
													: __( 'Off', 'tagcore' ) }
											</td>
											<td
												data-label={ __(
													'Activated (UTC)',
													'tagcore'
												) }
											>
												{ formatDate(
													item.activated_at
												) }
											</td>
											<td
												data-label={ __(
													'Updated (UTC)',
													'tagcore'
												) }
											>
												{ formatDate(
													item.updated_at
												) }
											</td>
										</tr>
									) ) }
								</tbody>
							</table>
						</div>
					) }

					{ response.next_cursor && (
						<Button
							className="returntag-load-more"
							variant="secondary"
							isBusy={ loadingMore }
							disabled={ loadingMore }
							onClick={ () => void loadMore() }
						>
							{ __( 'Load more', 'tagcore' ) }
						</Button>
					) }
				</div>
			) }
		</section>
	);
}
