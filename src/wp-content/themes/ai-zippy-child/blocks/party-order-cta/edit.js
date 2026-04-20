import { __ } from "@wordpress/i18n";
import {
  useBlockProps,
  RichText,
  InspectorControls,
} from "@wordpress/block-editor";
import { PanelBody, TextControl } from "@wordpress/components";

export default function Edit({ attributes, setAttributes }) {
  const { text, menuLabel, menuUrl, whatsappLabel, whatsappUrl } = attributes;
  const blockProps = useBlockProps({ className: "party-order-cta" });

  return (
    <>
      <InspectorControls>
        <PanelBody title={__("Button Links", "ai-zippy")}>
          <TextControl
            label={__("Menu URL", "ai-zippy")}
            value={menuUrl}
            onChange={(val) => setAttributes({ menuUrl: val })}
          />
          <TextControl
            label={__("WhatsApp URL", "ai-zippy")}
            value={whatsappUrl}
            onChange={(val) => setAttributes({ whatsappUrl: val })}
          />
        </PanelBody>
      </InspectorControls>

      <div {...blockProps}>
        <div className="party-order-cta__container">
          <RichText
            tagName="p"
            className="party-order-cta__text"
            value={text}
            onChange={(val) => setAttributes({ text: val })}
            placeholder={__("Enter CTA text...", "ai-zippy")}
          />

          <div className="party-order-cta__buttons">
            <RichText
              tagName="div"
              className="az-btn az-btn--outline"
              value={menuLabel}
              onChange={(val) => setAttributes({ menuLabel: val })}
              placeholder={__("Menu Button Label", "ai-zippy")}
            />
            <RichText
              tagName="div"
              className="az-btn az-btn--solid"
              value={whatsappLabel}
              onChange={(val) => setAttributes({ whatsappLabel: val })}
              placeholder={__("WhatsApp Button Label", "ai-zippy")}
            />
          </div>
        </div>
      </div>
    </>
  );
}
