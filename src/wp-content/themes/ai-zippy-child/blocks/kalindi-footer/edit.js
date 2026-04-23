import { __ } from "@wordpress/i18n";
import {
  useBlockProps,
  InspectorControls,
  RichText,
  MediaUpload,
  MediaUploadCheck,
  InnerBlocks,
  PanelColorSettings,
} from "@wordpress/block-editor";
import {
  PanelBody,
  Button,
  TextControl,
  ResponsiveControl,
} from "@wordpress/components";

const TEMPLATE = [
  ["ai-zippy-child/kalindi-footer-column", { title: "INFORMATION" }],
  ["ai-zippy-child/kalindi-footer-column", { title: "USEFUL LINKS" }],
  ["ai-zippy-child/kalindi-footer-column", { title: "NEWS LETTER" }],
  ["ai-zippy-child/kalindi-footer-column", { title: "SINGAPORE" }],
];

export default function Edit({ attributes, setAttributes }) {
  const {
    topImage,
    logoImage,
    bgColor,
    tagline,
    facebookUrl,
    instagramUrl,
    copyright,
  } = attributes;
  const blockProps = useBlockProps({
    className: "kalindi-footer-edit",
    style: { backgroundColor: bgColor },
  });

  return (
    <>
      <InspectorControls>
        <PanelColorSettings
          title={__("Background Settings", "ai-zippy")}
          colorSettings={[
            {
              value: bgColor,
              onChange: (color) => setAttributes({ bgColor: color }),
              label: __("Footer Background Color", "ai-zippy"),
            },
          ]}
        />
        <PanelBody title={__("Images", "ai-zippy")}>
          <MediaUploadCheck>
            <div className="media-upload-group">
              <label>{__("Top Decoration Image", "ai-zippy")}</label>
              <MediaUpload
                onSelect={(media) => setAttributes({ topImage: media.url })}
                allowedTypes={["image"]}
                value={topImage}
                render={({ open }) => (
                  <Button onClick={open} variant="secondary">
                    {!topImage
                      ? __("Select Image", "ai-zippy")
                      : __("Change Image", "ai-zippy")}
                  </Button>
                )}
              />
            </div>
            <div className="media-upload-group" style={{ marginTop: "1rem" }}>
              <label>{__("Logo Image", "ai-zippy")}</label>
              <MediaUpload
                onSelect={(media) => setAttributes({ logoImage: media.url })}
                allowedTypes={["image"]}
                value={logoImage}
                render={({ open }) => (
                  <Button onClick={open} variant="secondary">
                    {!logoImage
                      ? __("Select Logo", "ai-zippy")
                      : __("Change Logo", "ai-zippy")}
                  </Button>
                )}
              />
            </div>
          </MediaUploadCheck>
        </PanelBody>
        <PanelBody title={__("Social Links", "ai-zippy")}>
          <TextControl
            label={__("Facebook URL", "ai-zippy")}
            value={facebookUrl}
            onChange={(val) => setAttributes({ facebookUrl: val })}
          />
          <TextControl
            label={__("Instagram URL", "ai-zippy")}
            value={instagramUrl}
            onChange={(val) => setAttributes({ instagramUrl: val })}
          />
        </PanelBody>
        <PanelBody title={__("Bottom Bar", "ai-zippy")}>
          <TextControl
            label={__("Copyright Text", "ai-zippy")}
            value={copyright}
            onChange={(val) => setAttributes({ copyright: val })}
          />
        </PanelBody>
      </InspectorControls>

      <footer {...blockProps}>
        {topImage && (
          <div
            className="kalindi-footer__top-image-preview"
            style={{ textAlign: "center", marginTop: "-50px" }}
          >
            <img
              src={topImage}
              style={{ width: "200px", borderRadius: "50%" }}
              alt=""
            />
          </div>
        )}

        <div
          className="kalindi-footer__admin-container"
          style={{ padding: "2rem", display: "flex", gap: "2rem" }}
        >
          <div className="kalindi-footer__col--brand" style={{ flex: "1" }}>
            {logoImage && (
              <img src={logoImage} style={{ width: "80px" }} alt="" />
            )}
            <RichText
              tagName="p"
              value={tagline}
              onChange={(val) => setAttributes({ tagline: val })}
              placeholder={__("Enter tagline...", "ai-zippy")}
            />
          </div>

          <div className="kalindi-footer__dynamic-cols" style={{ flex: "3" }}>
            <InnerBlocks
              allowedBlocks={["ai-zippy-child/kalindi-footer-column"]}
              template={TEMPLATE}
            />
          </div>
        </div>

        <div
          className="kalindi-footer__bottom-preview"
          style={{
            borderTop: "1px solid rgba(255,255,255,0.2)",
            padding: "1rem",
            textAlign: "center",
          }}
        >
          <p>{copyright}</p>
        </div>
      </footer>
    </>
  );
}
