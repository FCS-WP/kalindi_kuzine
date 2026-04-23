import { __ } from "@wordpress/i18n";
import { useBlockProps, RichText, InnerBlocks } from "@wordpress/block-editor";

const ALLOWED_BLOCKS = ["core/paragraph", "core/list", "core/navigation-link"];

export default function Edit({ attributes, setAttributes }) {
  const { title } = attributes;
  const blockProps = useBlockProps({
    className: "kalindi-footer-column-edit",
  });

  return (
    <div {...blockProps}>
      <RichText
        tagName="h3"
        value={title}
        onChange={(val) => setAttributes({ title: val })}
        placeholder={__("Column Title", "ai-zippy")}
        style={{
          color: "#fff",
          fontSize: "0.9rem",
          fontWeight: "bold",
          textTransform: "uppercase",
          marginBottom: "1rem",
        }}
      />
      <div className="kalindi-footer__col-content-edit">
        <InnerBlocks allowedBlocks={ALLOWED_BLOCKS} />
      </div>
    </div>
  );
}
