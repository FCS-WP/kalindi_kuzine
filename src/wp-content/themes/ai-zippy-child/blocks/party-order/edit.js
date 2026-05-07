import { __ } from "@wordpress/i18n";
import { useBlockProps, InspectorControls } from "@wordpress/block-editor";
import { PanelBody, RangeControl } from "@wordpress/components";

export default function Edit({ attributes, setAttributes }) {
  const { limit, columns } = attributes;
  const blockProps = useBlockProps();

  return (
    <>
      <InspectorControls>
        <PanelBody title={__("Settings", "ai-zippy")} initialOpen={true}>
          <RangeControl
            label={__("Number of Products", "ai-zippy")}
            value={limit}
            onChange={(val) => setAttributes({ limit: val })}
            min={1}
            max={24}
          />
          <RangeControl
            label={__("Columns", "ai-zippy")}
            value={columns}
            onChange={(val) => setAttributes({ columns: val })}
            min={1}
            max={4}
          />
        </PanelBody>
      </InspectorControls>

      <div {...blockProps}>
        <div
          style={{
            padding: "2rem",
            background: "#fff9f5",
            border: "2px dashed #cc5d33",
            borderRadius: "8px",
            textAlign: "center"
          }}
        >
          <h3 style={{ margin: "0 0 1rem", color: "#cc5d33" }}>
            {__("Party Order Products", "ai-zippy")}
          </h3>
          <p style={{ margin: 0, color: "#666" }}>
            {__(`Displaying ${limit} products from 'party-order' category in ${columns} columns.`, "ai-zippy")}
          </p>
        </div>
      </div>
    </>
  );
}
