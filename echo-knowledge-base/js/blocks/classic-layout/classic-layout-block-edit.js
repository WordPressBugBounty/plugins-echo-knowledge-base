/**
 * React hook that is used to mark the block wrapper element.
 * It provides all the necessary props like the class name.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useblockprops
 */

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#edit
 *
 * @return {WPElement} Element to render.
 */
import EpkbInspectorControls from "../components";

export default function ClassicLayoutBlockEdit({ attributes, setAttributes, name }) {

	// this should never happen except during development
	// and indicates a critical issue
	if (!epkb_classic_layout_block_ui_config) {
		return (
			<>
				<div>Unable to load all assets.</div>
			</>
		);
	}

	return (
		<EpkbInspectorControls
			block_ui_config={epkb_classic_layout_block_ui_config}
			attributes={attributes}
			setAttributes={setAttributes}
			blockName={name}
		/>
	);
}
