import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import './style.css';

registerBlockType( metadata, {
	edit: Edit,
	// Dynamic block — PHP renders the front end; save returns null.
	save: () => null,
} );
