/**
 * BLOCK: YouTube Channel Videos. Rendered in PHP (class-youtube-channel-block.php).
 */
import { registerBlockType } from '@wordpress/blocks';

import './editor.scss';
import metadata from './block.json';
import edit from './edit';

registerBlockType( metadata.name, {
	edit,
	save: () => null,
} );
