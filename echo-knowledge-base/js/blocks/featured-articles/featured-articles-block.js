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
