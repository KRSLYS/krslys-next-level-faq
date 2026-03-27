( function () {
	const { registerBlockType } = wp.blocks;
	const { InspectorControls, useBlockProps } = wp.blockEditor || wp.editor;
	const { PanelBody, SelectControl, TextControl, Spinner } = wp.components;
	const { useSelect } = wp.data;
	const { createElement: el, Fragment } = wp.element;
	const { __ } = wp.i18n;

	const blockName = 'next-level-faq/faq';

	// Register or update the block with edit and save functions
	// When block.json is used, WordPress auto-registers the block server-side
	// We provide the client-side edit function here
	registerBlockType( blockName, {
		edit: function ( props ) {
			const { attributes, setAttributes } = props;
			const { title, groupId, preset } = attributes;
			const blockProps = useBlockProps( {
				className: 'nlf-faq-block-wrapper',
			} );

			const presetRegistry = ( window.nlfFaqBlockData && window.nlfFaqBlockData.presets ) || {};
			const presetOptions = [
				{ label: __( 'Use global preset', 'next-level-faq' ), value: '' },
				...Object.keys( presetRegistry ).map( function ( slug ) {
					return {
						label: presetRegistry[ slug ].name || slug,
						value: slug,
					};
				} ),
			];

			const activePreset = preset || ( window.nlfFaqBlockData && window.nlfFaqBlockData.activePreset ) || '';

			const { groups, isLoading } = useSelect(
				function ( select ) {
					const coreData = select( 'core' );
					if ( ! coreData || ! coreData.getEntityRecords ) {
						return { groups: [], isLoading: false };
					}

					const query = {
						per_page: -1,
						_fields: [ 'id', 'title' ],
					};

					const selectorArgs = [ 'postType', 'nlf_faq_group', query ];
					const records = coreData.getEntityRecords( ...selectorArgs );
					const isResolving = coreData.isResolving( 'getEntityRecords', selectorArgs );

					return {
						groups: records || [],
						isLoading: isResolving,
					};
				},
				[]
			);

			const groupOptions = [
				{ label: __( 'All FAQs (default)', 'next-level-faq' ), value: 0 },
				...( groups || [] ).map( function ( g ) {
					return {
						label: g.title && g.title.rendered ? g.title.rendered : __( '(no title)', 'next-level-faq' ),
						value: g.id,
					};
				} ),
			];

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'FAQ Settings', 'next-level-faq' ), initialOpen: true },
						el( TextControl, {
							label: __( 'Title', 'next-level-faq' ),
							value: title || '',
							onChange: function ( value ) {
								setAttributes( { title: value } );
							},
						} ),
							el( SelectControl, {
								label: __( 'Preset', 'next-level-faq' ),
								value: activePreset,
								options: presetOptions,
								onChange: function ( value ) {
									setAttributes( { preset: value || '' } );
								},
								help: __( 'Choose which preset to apply to this block. Leave blank to use the global preset.', 'next-level-faq' ),
							} ),
						isLoading
							? el( 'div', { style: { padding: '10px 0' } }, el( Spinner ) )
							: el( SelectControl, {
								label: __( 'FAQ Group', 'next-level-faq' ),
								value: groupId || 0,
								options: groupOptions,
								onChange: function ( value ) {
									setAttributes( { groupId: parseInt( value || 0, 10 ) || 0 } );
								},
							} )
					)
				),
				el(
					'div',
					blockProps,
					el(
						'div',
						{ className: 'nlf-faq nlf-faq--editor-placeholder' },
						el(
							'h2',
							{ className: 'nlf-faq__title' },
							title || __( 'Frequently Asked Questions', 'next-level-faq' )
						),
						el(
							'p',
							null,
							groupId
								? __( 'This block will display the selected FAQ group on the front-end.', 'next-level-faq' )
								: __( 'No specific group selected. Default FAQs will be shown.', 'next-level-faq' )
						),
						el(
							'p',
							{ className: 'description' },
							activePreset
								? __( 'Preset: ', 'next-level-faq' ) + ( presetRegistry[ activePreset ] ? presetRegistry[ activePreset ].name : activePreset )
								: __( 'Using global preset', 'next-level-faq' )
						)
					)
				)
			);
		},
		save: function () {
			// Dynamic block - rendered server-side via render_callback
			return null;
		},
	} );
} )();


