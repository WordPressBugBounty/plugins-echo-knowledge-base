/**
 * Registers a new block provided a unique name and an object defining its behavior.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import CategoriesLayoutBlockEdit from './categories-layout-block-edit';
import CategoriesLayoutBlockSave from "./categories-layout-block-save";
import { unregister_block_for_non_page } from '../utils';

/**
 * Every block starts by registering a new block type definition.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/
 */
registerBlockType(
	'echo-knowledge-base/categories-layout',
	{
		icon: {
			src: (
				<svg id="Category_Layout" data-name="Category Layout" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 119.51 79.31" fill="none">
					<path d="M1.96 42.61h53.39v7.43H1.96Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M1.69 71.08h53.39v7.43H1.69Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M64.16 42.87h53.39v7.43H64.16Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M63.89 71.33h53.39v7.43H63.89Z" stroke="#000" strokeMiterlimit="10" />
					<path
						d="M0.5 0.5v21.08h56.3V0.5H0.5ZM46.57 17.36c-3.96 0-7.17-3.21-7.17-7.17s3.21-7.17 7.17-7.17 7.17 3.21 7.17 7.17-3.21 7.17-7.17 7.17Z"
						stroke="#000"
						strokeMiterlimit="10"
					/>
					<path
						d="M62.71 0.76v21.08h56.3V0.76h-56.3ZM108.78 17.62c-3.96 0-7.17-3.21-7.17-7.17s3.21-7.17 7.17-7.17 7.17 3.21 7.17 7.17-3.21 7.17-7.17 7.17Z"
						stroke="#000"
						strokeMiterlimit="10"
					/>
				</svg>
			),
		},
		edit: CategoriesLayoutBlockEdit,
		save: CategoriesLayoutBlockSave,
	}
);

// Unregister block if not 'page' post type
(function(wp) {
	unregister_block_for_non_page(wp, 'categories-layout');
})(window.wp);
