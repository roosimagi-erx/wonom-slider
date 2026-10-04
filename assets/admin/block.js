/* Wonom Slider – Gutenberg block (server-side rendered). */
( function ( blocks, element, i18n, blockEditor, serverSideRender ) {
	var el = element.createElement;
	var __ = i18n.__;
	var useBlockProps = blockEditor.useBlockProps;
	var ServerSideRender = serverSideRender;

	blocks.registerBlockType( 'wonom/slider', {
		edit: function ( props ) {
			return el(
				'div',
				useBlockProps( { className: 'wonom-slider-block-editor' } ),
				el( ServerSideRender, { block: 'wonom/slider', attributes: props.attributes } ),
				el( 'p', { style: { fontSize: '12px', color: '#666', margin: '6px 0 0' } },
					__( 'Slides are managed under Wonom Slider in the admin menu.', 'wonom-slider' ) )
			);
		},
		save: function () {
			return null;
		}
	} );
}( window.wp.blocks, window.wp.element, window.wp.i18n, window.wp.blockEditor, window.wp.serverSideRender ) );
