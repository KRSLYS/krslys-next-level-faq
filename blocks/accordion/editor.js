( function () {
	const { registerBlockType } = wp.blocks;
	const { InspectorControls, useBlockProps } = wp.blockEditor || wp.editor;
	const { PanelBody, SelectControl, TextControl, Disabled, Placeholder, Spinner, Button } = wp.components;
	const ServerSideRender = wp.serverSideRender;
	const { createElement: el, Fragment } = wp.element;
	const { __ } = wp.i18n;

	const blockName = 'next-level-faq/accordion';

	registerBlockType( blockName, {
		edit: function ( props ) {
			const { attributes, setAttributes } = props;
			const { title, groupId } = attributes;
			const blockProps = useBlockProps( { className: 'nlf-faq-block-wrapper' } );

			const blockData    = window.nlfAccordionBlockData || {};
			const groups       = blockData.groups || [];
			const groupOptions = groups.map( function ( g ) {
				return {
					label: g.title || __( '(no title)', 'krslys-next-level-faq' ),
					value: g.id,
				};
			} );

			var editUrl = groupId
				? ( blockData.editGroupUrl || '' ) + groupId
				: null;

			return el(
				Fragment,
				null,

				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Accordion Settings', 'krslys-next-level-faq' ), initialOpen: true },
						el( TextControl, {
							label:    __( 'Title', 'krslys-next-level-faq' ),
							value:    title || '',
							onChange: function ( value ) { setAttributes( { title: value } ); },
						} ),
						el( SelectControl, {
							label:    __( 'Accordion Group', 'krslys-next-level-faq' ),
							value:    groupId || 0,
							options:  groupOptions,
							onChange: function ( value ) {
								setAttributes( { groupId: parseInt( value || 0, 10 ) || 0 } );
							},
						} ),

						editUrl && el(
							'div',
							{ style: { marginTop: '8px' } },
							el(
								Button,
								{
									variant: 'link',
									href:    editUrl,
									target:  '_blank',
									rel:     'noreferrer noopener',
									icon:    'edit',
								},
								__( 'Edit Accordion Group', 'krslys-next-level-faq' )
							)
						)
					)
				),

				el(
					'div',
					blockProps,
					el(
						Disabled,
						null,
						el( ServerSideRender, {
							block:      blockName,
							attributes: attributes,
							httpMethod: 'POST',

							LoadingResponsePlaceholder: function () {
								return el(
									Placeholder,
									{
										icon:  'list-view',
										label: __( 'Next Level Accordion', 'krslys-next-level-faq' ),
									},
									el( Spinner )
								);
							},
						} )
					)
				)
			);
		},

		save: function () {
			return null;
		},
	} );
} )();
