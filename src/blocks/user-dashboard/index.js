import { registerBlockType } from '@wordpress/blocks';
import ServerSideBlock from '../../editor/server-side-block';
import metadata from '../../../blocks/user-dashboard/block.json';

registerBlockType( metadata.name, {
	edit( props ) {
		return <ServerSideBlock name={ metadata.name } { ...props } />;
	},
	save() {
		return null;
	},
} );
