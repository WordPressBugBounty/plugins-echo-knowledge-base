/**
 * Registers a new block provided a unique name and an object defining its behavior.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import DrillDownLayoutBlockEdit from './drill-down-layout-block-edit';
import DrillDownLayoutBlockSave from "./drill-down-layout-block-save";
import { unregister_block_for_non_page } from '../utils';

/**
 * Every block starts by registering a new block type definition.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/
 */
registerBlockType(
	'echo-knowledge-base/drill-down-layout',
	{
		icon: {
			src: (
				<svg id="Drill_Down_Layout" data-name="Drill Down Layout" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 119.57 79.54" fill="none">
					<path d="M7.01 38.39h49.94v6.04H7.01Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M60.78 38.39h49.94v6.04H60.78Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M7.01 47.61h49.94v6.04H7.01Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M60.78 47.61h49.94v6.04H60.78Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M0.56 58.52h37.55v8.22H0.56Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M40.98 58.52h37.55v8.22H40.98Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M81.41 58.52h37.55v8.22H81.41Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M0.51 70.82h37.55v8.22H0.51Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M40.93 70.82h37.55v8.22H40.93Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M81.36 70.82h37.55v8.22H81.36Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M33.24 2.43h52.94v31.96H33.24Z" stroke="#000" strokeMiterlimit="10" />
				</svg>

			),
		},
		edit: DrillDownLayoutBlockEdit,
		save: DrillDownLayoutBlockSave,
	}
);

// Unregister block if not 'page' post type
(function(wp) {
	unregister_block_for_non_page(wp, 'drill-down-layout');
})(window.wp);
