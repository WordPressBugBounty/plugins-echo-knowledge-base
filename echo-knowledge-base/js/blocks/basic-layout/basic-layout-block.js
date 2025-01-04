/**
 * Registers a new block provided a unique name and an object defining its behavior.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import BasicLayoutBlockEdit from './basic-layout-block-edit';
import BasicLayoutBlockSave from "./basic-layout-block-save";
import { unregister_block_for_non_page } from '../utils';

/**
 * Every block starts by registering a new block type definition.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/
 */
registerBlockType(
	'echo-knowledge-base/basic-layout',
	{
		icon: {
			src: (
				<svg id="Basic_Layout" data-name="Basic Layout" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 119.51 79.31" fill="none">
					<path d="M1.96 42.61h53.39v7.43H1.96Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M1.69 71.08h53.39v7.43H1.69Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M64.16 42.87h53.39v7.43H64.16Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M63.89 71.33h53.39v7.43H63.89Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M0.5 0.5h56.75v21.33H0.5Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M62.21 0.83h56.75v21.33H62.21Z" stroke="#000" strokeMiterlimit="10" />
				</svg>
			),
		},
		edit: BasicLayoutBlockEdit,
		save: BasicLayoutBlockSave,
	}
);

// Unregister block if not 'page' post type
(function(wp) {
	unregister_block_for_non_page(wp, 'basic-layout');
})(window.wp);
