/**
 * BLOCK: ALPS Latest Posts. Rendered in PHP (class-latest-posts-block.php).
 */
import { registerBlockType } from '@wordpress/blocks';

import './editor.scss';
import metadata from './block.json';
import edit from './edit';

registerBlockType( metadata.name, {
	edit,
	save: () => null,
} );
