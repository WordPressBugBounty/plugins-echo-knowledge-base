/**
 * Registers a new block provided a unique name and an object defining its behavior.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import TabsLayoutBlockEdit from './tabs-layout-block-edit';
import TabsLayoutBlockSave from "./tabs-layout-block-save";
import { unregister_block_for_non_page } from '../utils';

/**
 * Every block starts by registering a new block type definition.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/
 */
registerBlockType(
	'echo-knowledge-base/tabs-layout',
	{
		icon: {
			src: (
				<svg id="Tabs_Layout" data-name="Tabs Layout" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 119.27 79.15" fill="none">
					<path d="M65 0.85h53.66v16.78H65Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M0.71 0.85h55.61v16.78H0.71Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M23.54 12.85h9.95v9.56h-9.95Z" transform="rotate(45 28.52 18.63)" stroke="#000" strokeMiterlimit="10" />
					<path d="M1.91 34.32h35.8v11.9H1.91Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M2.83 58.24h33.95v4.2H2.83Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M2.66 74.31h33.95v4.2H2.66Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M42.39 58.39h33.95v4.2H42.39Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M42.22 74.46h33.95v4.2H42.22Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M82.34 58.39h33.95v4.2H82.34Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M82.12 74.46h33.95v4.2H82.12Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M41.69 34.46h35.8v11.9H41.69Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M81.47 34.46h35.8v11.9H81.47Z" stroke="#000" strokeMiterlimit="10" />
				</svg>
			),
		},
		edit: TabsLayoutBlockEdit,
		save: TabsLayoutBlockSave,
	}
);

// Unregister block if not 'page' post type
(function(wp) {
	unregister_block_for_non_page(wp, 'tabs-layout');
})(window.wp);
