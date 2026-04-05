( function () {
	const { registerBlockType } = wp.blocks;
	const { InspectorControls, useBlockProps } = wp.blockEditor || wp.editor;
	const { PanelBody, SelectControl, TextControl, Disabled, Placeholder, Spinner, Button, ExternalLink } = wp.components;
	const ServerSideRender = wp.serverSideRender;
	const { createElement: el, Fragment } = wp.element;
	const { __ } = wp.i18n;

	const blockName = 'next-level-faq/faq';

	registerBlockType( blockName, {
		edit: function ( props ) {
			const { attributes, setAttributes } = props;
			const { title, groupId } = attributes;
			const blockProps = useBlockProps( { className: 'nlf-faq-block-wrapper' } );

			const blockData    = window.nlfFaqBlockData || {};
			const groups       = blockData.groups || [];
			const groupOptions = groups.map( function ( g ) {
				return {
					label: g.title || __( '(no title)', 'krslys-next-level-faq' ),
					value: g.id,
				};
			} );

			// Edit link — only available when a group is selected.
			const editUrl = groupId
				? ( blockData.editGroupUrl || '' ) + groupId
				: null;

			return el(
				Fragment,
				null,

				// ── Inspector panel ──────────────────────────────────────────
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'FAQ Settings', 'krslys-next-level-faq' ), initialOpen: true },
						el( TextControl, {
							label:    __( 'Title', 'krslys-next-level-faq' ),
							value:    title || '',
							onChange: function ( value ) { setAttributes( { title: value } ); },
						} ),
						el( SelectControl, {
							label:    __( 'FAQ Group', 'krslys-next-level-faq' ),
							value:    groupId || 0,
							options:  groupOptions,
							onChange: function ( value ) {
								setAttributes( { groupId: parseInt( value || 0, 10 ) || 0 } );
							},
						} ),

						// Edit link — opens the group editor in a new tab.
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
								__( 'Edit FAQ Group', 'krslys-next-level-faq' )
							)
						)
					)
				),

				// ── Block canvas: live server-side preview ────────────────
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
										label: __( 'Next Level FAQ', 'krslys-next-level-faq' ),
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
