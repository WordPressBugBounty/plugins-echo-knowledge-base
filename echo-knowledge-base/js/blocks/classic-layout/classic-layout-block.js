/**
 * Registers a new block provided a unique name and an object defining its behavior.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import ClassicLayoutBlockEdit from './classic-layout-block-edit';
import ClassicLayoutBlockSave from "./classic-layout-block-save";
import { unregister_block_for_non_page } from '../utils';

/**
 * Every block starts by registering a new block type definition.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/
 */
registerBlockType(
	'echo-knowledge-base/classic-layout',
	{
		icon: {
			src: (
				<svg id="Classic_Layout_2" data-name="Classic Layout 2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 119.51 79.33" fill="none">
					<circle cx="29.6" cy="16.8" r="15.04" stroke="#000" strokeMiterlimit="10" fill="none" />
					<path
						d="M1.58 22.15v56.68h56.04V22.15H1.58ZM52.86 75.42H4.65V26.28h48.21v49.14Z"
						stroke="#000"
						strokeMiterlimit="10"
						fill="none"
					/>
					<path d="M8.72 42.96h40.07v4.48H8.72Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M9.56 60.09h40.07v4.48H9.56Z" stroke="#000" strokeMiterlimit="10" />
					<path
						d="M61.38 22.15v56.68h56.04V22.15H61.38ZM112.66 75.42h-48.21V26.28h48.21v49.14Z"
						stroke="#000"
						strokeMiterlimit="10"
						fill="none"
					/>
					<path d="M68.52 42.96h40.07v4.48H68.52Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M69.36 60.09h40.07v4.48H69.36Z" stroke="#000" strokeMiterlimit="10" />
					<circle cx="88.55" cy="16.8" r="15.04" stroke="#000" strokeMiterlimit="10" fill="none" />
				</svg>

			),
		},
		edit: ClassicLayoutBlockEdit,
		save: ClassicLayoutBlockSave,
	}
);

// Unregister block if not 'page' post type
(function(wp) {
	unregister_block_for_non_page(wp, 'classic-layout');
})(window.wp);
