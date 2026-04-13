( function () {
	const { registerBlockType } = wp.blocks;
	const { InspectorControls, useBlockProps } = wp.blockEditor || wp.editor;
	const { PanelBody, SelectControl, Disabled, Placeholder, Spinner, Button } = wp.components;
	const ServerSideRender = wp.serverSideRender;
	const { createElement: el, Fragment } = wp.element;
	const { __ } = wp.i18n;

	const blockName = 'krslys-next-level/faq';

	registerBlockType( blockName, {
		edit: function ( props ) {
			const { attributes, setAttributes } = props;
			const { groupId } = attributes;
			const blockProps = useBlockProps( { className: 'nlf-faq-block-wrapper' } );

			const blockData    = window.nlfFaqBlockData || {};
			const groups       = blockData.groups || [];
			const editGroupsUrl = ( blockData.groupsListUrl || '' );

			const groupOptions = [
				{ label: __( '— Select a FAQ group —', 'krslys-next-level-faq-accordion' ), value: 0 },
			].concat(
				groups.map( function ( g ) {
					return {
						label: g.title || __( '(no title)', 'krslys-next-level-faq-accordion' ),
						value: g.id,
					};
				} )
			);

			// Edit link — only available when a group is selected.
			const editUrl = groupId
				? ( blockData.editGroupUrl || '' ) + groupId
				: null;

			// Inspector panel (always shown).
			const inspector = el(
				InspectorControls,
				null,
				el(
					PanelBody,
					{ title: __( 'FAQ Settings', 'krslys-next-level-faq-accordion' ), initialOpen: true },
					el( SelectControl, {
						label:    __( 'FAQ Group', 'krslys-next-level-faq-accordion' ),
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
							__( 'Edit FAQ Group', 'krslys-next-level-faq-accordion' )
						)
					)
				)
			);

			// Placeholder when no groups exist.
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
								icon:         'editor-help',
								label:        __( 'Next Level FAQ', 'krslys-next-level-faq-accordion' ),
								instructions: __( 'No FAQ groups found. Create a FAQ group first, then come back to select it here.', 'krslys-next-level-faq-accordion' ),
							},
							editGroupsUrl && el(
								Button,
								{
									variant: 'primary',
									href:    editGroupsUrl,
									target:  '_blank',
									rel:     'noreferrer noopener',
								},
								__( 'Create FAQ Group', 'krslys-next-level-faq-accordion' )
							)
						)
					)
				);
			}

			// Placeholder when no group is selected yet.
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
								icon:         'editor-help',
								label:        __( 'Next Level FAQ', 'krslys-next-level-faq-accordion' ),
								instructions: __( 'Select a FAQ group from the block settings on the right.', 'krslys-next-level-faq-accordion' ),
							},
							el( SelectControl, {
								label:    __( 'FAQ Group', 'krslys-next-level-faq-accordion' ),
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

			// Group selected: show server-side preview.
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
										icon:  'editor-help',
										label: __( 'Next Level FAQ', 'krslys-next-level-faq-accordion' ),
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
			// Dynamic block — rendered server-side via render_callback.
			return null;
		},
	} );
} )();
