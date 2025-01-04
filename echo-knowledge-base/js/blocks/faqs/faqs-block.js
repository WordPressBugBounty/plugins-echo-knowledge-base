/**
 * Registers a new block provided a unique name and an object defining its behavior.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import FaqsBlockEdit from './faqs-block-edit';
import FaqsBlockSave from "./faqs-block-save";

/**
 * Every block starts by registering a new block type definition.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/
 */
registerBlockType(
	'echo-knowledge-base/faqs',
	{
		icon: {
			src: (
				<svg id="FAQs" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 119.51 79.49" fill="none">
					<path d="M0.6 0.73h17.61v15.75H0.6Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M29.15 0.95h89.69v15.31H29.15Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M0.53 63.24h17.61v15.75H0.53Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M29.07 63.46h89.69v15.31H29.07Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M0.53 21.79h17.61v15.75H0.53Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M29.07 22.01h89.69v15.31H29.07Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M0.53 42.51h17.61v15.75H0.53Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M29.07 42.73h89.69v15.31H29.07Z" stroke="#000" strokeMiterlimit="10" />
				</svg>
			),
		},
		edit: FaqsBlockEdit,
		save: FaqsBlockSave,
	}
);

// Unregister block if not 'page' post type
(function(wp) {

	const { unregisterBlockType, getBlockType } = wp.blocks;
	const { select, subscribe } = wp.data;

	wp.domReady(() => {

		let is_unregistered = false;

		const unsubscribe = subscribe(() => {

			// Do nothing if already unregistered
			if (is_unregistered) {
				return;
			}

			// Try to get current post type
			let post_type = null;
			if (select('core/editor') && typeof select('core/editor').getCurrentPostType === 'function') {
				post_type = select('core/editor').getCurrentPostType();
			}

			// If post type is not available yet, then do not continue
			if (!post_type) {
				return;
			}

			// If post type is 'page', then unsubscribe and do nothing
			if (post_type === 'page') {
				is_unregistered = true;
				unsubscribe();
				return;
			}

			// Unregister the current block for non-page post type
			if (getBlockType('echo-knowledge-base/faqs')) {
				try {
					unregisterBlockType('echo-knowledge-base/faqs');
				} catch (error) {}
			}

			// Unsubscribe to prevent further execution
			is_unregistered = true;
			unsubscribe();
		});
	});
})(window.wp);
