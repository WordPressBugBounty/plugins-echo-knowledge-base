/**
 * Registers a new block provided a unique name and an object defining its behavior.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import FeaturedArticlesBlockEdit from './featured-articles-block-edit';
import FeaturedArticlesBlockSave from "./featured-articles-block-save";

/**
 * Every block starts by registering a new block type definition.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/
 */
registerBlockType(
	'echo-knowledge-base/featured-articles',
	{
		icon: {
			src: (
				<svg id="Featured_Articles" data-name="Featured Articles" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 119.57 79.49" fill="none">
					<path d="M0.55 27.86h6.89v6.89H0.55Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M9.64 27.86h25.38v6.89H9.64Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M20.89 4.01h78.54v12.36H20.89Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M42.72 27.86h6.89v6.89H42.72Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M51.53 27.86h25.38v6.89H51.53Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M84.88 27.86h6.89v6.89H84.88Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M93.59 27.86h25.38v6.89H93.59Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M0.5 42.9h6.89v6.89H0.5Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M9.59 42.9h25.38v6.89H9.59Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M42.67 42.9h6.89v6.89H42.67Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M51.48 42.9h25.38v6.89H51.48Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M84.83 42.9h6.89v6.89H84.83Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M93.54 42.9h25.38v6.89H93.54Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M0.5 72.08h6.89v6.89H0.5Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M9.59 72.08h25.38v6.89H9.59Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M42.67 72.08h6.89v6.89H42.67Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M51.48 72.08h25.38v6.89H51.48Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M84.83 72.08h6.89v6.89H84.83Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M93.54 72.08h25.38v6.89H93.54Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M0.5 57.49h6.89v6.89H0.5Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M9.59 57.49h25.38v6.89H9.59Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M42.67 57.49h6.89v6.89H42.67Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M51.48 57.49h25.38v6.89H51.48Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M84.83 57.49h6.89v6.89H84.83Z" stroke="#000" strokeMiterlimit="10" />
					<path d="M93.54 57.49h25.38v6.89H93.54Z" stroke="#000" strokeMiterlimit="10" />
				</svg>
			),
		},
		edit: FeaturedArticlesBlockEdit,
		save: FeaturedArticlesBlockSave,
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
			if (getBlockType('echo-knowledge-base/featured-articles')) {
				try {
					unregisterBlockType('echo-knowledge-base/featured-articles');
				} catch (error) {}
			}

			// Unsubscribe to prevent further execution
			is_unregistered = true;
			unsubscribe();
		});
	});
})(window.wp);
