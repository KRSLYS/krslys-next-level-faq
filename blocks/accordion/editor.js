( function () {
	const { registerBlockType } = wp.blocks;
	const { InspectorControls, useBlockProps } = wp.blockEditor || wp.editor;
	const { PanelBody, SelectControl, Disabled, Placeholder, Spinner, Button } = wp.components;
	const ServerSideRender = wp.serverSideRender;
	const { createElement: el, Fragment } = wp.element;
	const { __ } = wp.i18n;

	const blockName = 'krslys-next-level/accordion';

	registerBlockType( blockName, {
		edit: function ( props ) {
			const { attributes, setAttributes } = props;
			const { groupId } = attributes;
			const blockProps = useBlockProps( { className: 'nlf-faq-block-wrapper' } );

			const blockData    = window.nlfAccordionBlockData || {};
			const groups       = blockData.groups || [];
			const editGroupsUrl = ( blockData.groupsListUrl || '' );

			const groupOptions = [
				{ label: __( '— Select an accordion group —', 'krslys-next-level-faq-accordion' ), value: 0 },
			].concat(
				groups.map( function ( g ) {
					return {
						label: g.title || __( '(no title)', 'krslys-next-level-faq-accordion' ),
						value: g.id,
					};
				} )
			);

			const editUrl = groupId
				? ( blockData.editGroupUrl || '' ) + groupId
				: null;

			const inspector = el(
				InspectorControls,
				null,
				el(
					PanelBody,
					{ title: __( 'Accordion Settings', 'krslys-next-level-faq-accordion' ), initialOpen: true },
					el( SelectControl, {
						label:    __( 'Accordion Group', 'krslys-next-level-faq-accordion' ),
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
							__( 'Edit Accordion Group', 'krslys-next-level-faq-accordion' )
						)
					)
				)
			);

			if ( groups.length === 0 ) {
				return el(
					Fragment,
					null,
					inspector,
					el(
						'div',
						blockProps,
						el(
							Placeholder,
							{
								icon:         'list-view',
								label:        __( 'Next Level Accordion', 'krslys-next-level-faq-accordion' ),
								instructions: __( 'No accordion groups found. Create an accordion group first, then come back to select it here.', 'krslys-next-level-faq-accordion' ),
							},
							editGroupsUrl && el(
								Button,
								{
									variant: 'primary',
									href:    editGroupsUrl,
									target:  '_blank',
									rel:     'noreferrer noopener',
								},
								__( 'Create Accordion Group', 'krslys-next-level-faq-accordion' )
							)
						)
					)
				);
			}

			if ( ! groupId ) {
				return el(
					Fragment,
					null,
					inspector,
					el(
						'div',
						blockProps,
						el(
							Placeholder,
							{
								icon:         'list-view',
								label:        __( 'Next Level Accordion', 'krslys-next-level-faq-accordion' ),
								instructions: __( 'Select an accordion group from the block settings on the right.', 'krslys-next-level-faq-accordion' ),
							},
							el( SelectControl, {
								label:    __( 'Accordion Group', 'krslys-next-level-faq-accordion' ),
								value:    groupId || 0,
								options:  groupOptions,
								onChange: function ( value ) {
									setAttributes( { groupId: parseInt( value || 0, 10 ) || 0 } );
								},
							} )
						)
					)
				);
			}

			return el(
				Fragment,
				null,
				inspector,
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
										label: __( 'Next Level Accordion', 'krslys-next-level-faq-accordion' ),
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
