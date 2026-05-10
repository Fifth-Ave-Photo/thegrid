/**
 * Grid Index — Editor script
 * Registers all server-rendered Grid Index blocks with ServerSideRender
 * previews and InspectorControls. No build step required.
 */
( function ( wp ) {
	var registerBlockType = wp.blocks.registerBlockType;
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var __ = wp.i18n.__;
	var ServerSideRender = wp.serverSideRender;
	var ic = wp.blockEditor || wp.editor;
	var InspectorControls = ic.InspectorControls;
	var useBlockProps = ic.useBlockProps;
	var c = wp.components;

	var data = window.gipBlocks || { registry: {}, categories: [] };

	var CARD_OPTIONS = [
		{ label: 'Minimal',   value: 'minimal' },
		{ label: 'Editorial', value: 'editorial' },
		{ label: 'Signal',    value: 'signal' },
		{ label: 'Cinematic', value: 'cinematic' }
	];
	var BG_OPTIONS = [
		{ label: 'Transparent', value: 'transparent' },
		{ label: 'Panel',       value: 'panel' },
		{ label: 'Accent',      value: 'accent' },
		{ label: 'Inverse',     value: 'inverse' }
	];
	var DENSITY_OPTIONS = [
		{ label: 'Compact',     value: 'compact' },
		{ label: 'Comfortable', value: 'comfortable' },
		{ label: 'Spacious',    value: 'spacious' }
	];
	var MOBILE_OPTIONS = [
		{ label: 'Show',     value: 'show' },
		{ label: 'Collapse', value: 'collapse' },
		{ label: 'Hide',     value: 'hide' }
	];
	var THEME_OPTIONS = [
		{ label: 'Inherit', value: 'inherit' },
		{ label: 'Dark',    value: 'dark' },
		{ label: 'Light',   value: 'light' }
	];
	var VISIBILITY_OPTIONS = [
		{ label: 'Everyone',         value: 'all' },
		{ label: 'Guests only',      value: 'guests' },
		{ label: 'Logged-in only',   value: 'logged_in' }
	];

	function makeEdit( blockName ) {
		return function ( props ) {
			var a = props.attributes;
			var setA = props.setAttributes;
			var blockProps = useBlockProps ? useBlockProps( { className: 'gi-block-edit' } ) : { className: 'gi-block-edit' };

			var inspector = el( InspectorControls, {},
				el( c.PanelBody, { title: __( 'Content', 'the-grid-index' ), initialOpen: true },
					el( c.SelectControl, {
						label: __( 'Category', 'the-grid-index' ),
						value: String( a.category ),
						options: data.categories.map( function ( o ) { return { label: o.label, value: String( o.value ) }; } ),
						onChange: function ( v ) { setA( { category: parseInt( v, 10 ) || 0 } ); }
					} ),
					el( c.RangeControl, {
						label: __( 'Story count', 'the-grid-index' ),
						value: a.count, min: 0, max: 30,
						onChange: function ( v ) { setA( { count: v } ); }
					} )
				),
				el( c.PanelBody, { title: __( 'Design', 'the-grid-index' ), initialOpen: false },
					el( c.SelectControl, { label: 'Card style',  value: a.card,    options: CARD_OPTIONS,    onChange: function ( v ) { setA( { card: v } ); } } ),
					el( c.SelectControl, { label: 'Background',  value: a.bg,      options: BG_OPTIONS,      onChange: function ( v ) { setA( { bg: v } ); } } ),
					el( c.SelectControl, { label: 'Density',     value: a.density, options: DENSITY_OPTIONS, onChange: function ( v ) { setA( { density: v } ); } } ),
					el( c.SelectControl, { label: 'Theme',       value: a.theme,   options: THEME_OPTIONS,   onChange: function ( v ) { setA( { theme: v } ); } } )
				),
				el( c.PanelBody, { title: __( 'Responsive & Visibility', 'the-grid-index' ), initialOpen: false },
					el( c.SelectControl, { label: 'On mobile', value: a.mobile,     options: MOBILE_OPTIONS,     onChange: function ( v ) { setA( { mobile: v } ); } } ),
					el( c.SelectControl, { label: 'Visibility', value: a.visibility, options: VISIBILITY_OPTIONS, onChange: function ( v ) { setA( { visibility: v } ); } } )
				)
			);

			var preview = ServerSideRender
				? el( ServerSideRender, { block: blockName, attributes: a } )
				: el( 'div', { className: 'gi-block-edit__placeholder' }, blockName );

			return el( Fragment, {},
				inspector,
				el( 'div', blockProps, preview )
			);
		};
	}

	Object.keys( data.registry ).forEach( function ( name ) {
		var meta = data.registry[ name ];
		registerBlockType( name, {
			apiVersion: 2,
			title: meta.title,
			description: meta.desc,
			category: 'grid-index',
			icon: meta.icon || 'screenoptions',
			keywords: [ 'grid index', 'news', 'editorial' ],
			supports: { html: false, align: [ 'wide', 'full' ], anchor: true },
			attributes: {
				category:   { type: 'integer', default: 0 },
				count:      { type: 'integer', default: 6 },
				card:       { type: 'string',  default: 'editorial' },
				bg:         { type: 'string',  default: 'transparent' },
				density:    { type: 'string',  default: 'comfortable' },
				mobile:     { type: 'string',  default: 'show' },
				visibility: { type: 'string',  default: 'all' },
				placement:  { type: 'string',  default: 'main' },
				theme:      { type: 'string',  default: 'inherit' }
			},
			edit: makeEdit( name ),
			save: function () { return null; }
		} );
	} );
} )( window.wp );
