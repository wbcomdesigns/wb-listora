/**
 * Shared ServerSideRender block editor component.
 *
 * Used by blocks that render server-side via render.php.
 * Shows the actual block output in the editor.
 */

import ServerSideRender from '@wordpress/server-side-render';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function ServerSideBlock( { name, attributes, setAttributes, children } ) {
	const blockProps = useBlockProps();

	return (
		< >
			{ children && (
				< InspectorControls >
					< PanelBody title = { __( 'Settings', 'wb-listora' ) } >
						{ children }
					< / PanelBody >
				< / InspectorControls >
			) }
			< div { ...blockProps } >
				< ServerSideRender
					block      = { name }
					attributes = { attributes }
				/ >
			< / div >
		< / >
	);
}
