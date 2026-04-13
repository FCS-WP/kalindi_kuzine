import { __ } from "@wordpress/i18n";
import { useBlockProps, InspectorControls } from "@wordpress/block-editor";
import { PanelBody, RangeControl } from "@wordpress/components";

export default function Edit({ attributes, setAttributes }) {
	const { limit } = attributes;
	const blockProps = useBlockProps();

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={__("Settings", "ai-zippy")}
					initialOpen={true}
				>
					<RangeControl
						label={__("Number of Products", "ai-zippy")}
						value={limit}
						onChange={(val) => setAttributes({ limit: val })}
						min={1}
						max={12}
					/>
				</PanelBody>
			</InspectorControls>

			<div {...blockProps}>
				<div style={{ padding: "2rem", background: "#f5f5f5", borderRadius: "4px" }}>
					<h3 style={{ margin: "0 0 1rem" }}>
						{__("Most Ordered Products", "ai-zippy")}
					</h3>
					<p style={{ margin: 0, color: "#666" }}>
						{__(`Displaying ${limit} most ordered products`, "ai-zippy")}
					</p>
				</div>
			</div>
		</>
	);
}
