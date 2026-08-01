const { createElement: el } = wp.element;
const { __ } = wp.i18n;
const { InspectorControls, useBlockProps } = wp.blockEditor;
const { PanelBody, SelectControl } = wp.components;

const labels = {
	activate: __( 'Activate my tag', 'tagcore' ),
	report: __( 'Report a found tag', 'tagcore' ),
};

wp.blocks.registerBlockType( 'tagcore/tag-entry-link', {
	edit: function Edit( { attributes, setAttributes } ) {
		const intent = [ 'activate', 'report' ].includes( attributes.intent )
			? attributes.intent
			: 'activate';
		const blockProps = useBlockProps( {
			className: 'returntag-entry-link',
		} );

		return el(
			wp.element.Fragment,
			null,
			el(
				InspectorControls,
				null,
				el(
					PanelBody,
					{ title: __( 'ForgeTag entry', 'tagcore' ) },
					el( SelectControl, {
						label: __( 'Initial intent', 'tagcore' ),
						value: intent,
						options: [
							{
								label: __( 'Activate', 'tagcore' ),
								value: 'activate',
							},
							{
								label: __( 'Report a found tag', 'tagcore' ),
								value: 'report',
							},
						],
						onChange: ( value ) =>
							setAttributes( { intent: value } ),
					} )
				)
			),
			el(
				'div',
				blockProps,
				el(
					'span',
					{ className: 'returntag-entry-link__trigger' },
					labels[ intent ]
				)
			)
		);
	},
	save: () => null,
} );

wp.blocks.registerBlockVariation( 'tagcore/tag-entry-link', {
	name: 'activate',
	title: __( 'Activate my tag', 'tagcore' ),
	attributes: { intent: 'activate' },
	isDefault: true,
	scope: [ 'inserter', 'transform' ],
} );

wp.blocks.registerBlockVariation( 'tagcore/tag-entry-link', {
	name: 'report',
	title: __( 'Report a found tag', 'tagcore' ),
	attributes: { intent: 'report' },
	scope: [ 'inserter', 'transform' ],
} );
