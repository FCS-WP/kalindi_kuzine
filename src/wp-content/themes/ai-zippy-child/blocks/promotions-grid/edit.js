import { __ } from "@wordpress/i18n";
import {
  useBlockProps,
  RichText,
  MediaUpload,
  MediaUploadCheck,
  InspectorControls,
} from "@wordpress/block-editor";
import { Button, PanelBody, ToggleControl } from "@wordpress/components";

export default function Edit({ attributes, setAttributes }) {
  const { sectionTitle, promotions, isHidden } = attributes;

  const blockProps = useBlockProps({
    className: isHidden ? "is-hidden-block" : "",
  });

  const updatePromotion = (index, field, value) => {
    const updatedPromotions = [...promotions];
    updatedPromotions[index] = {
      ...updatedPromotions[index],
      [field]: value,
    };
    setAttributes({ promotions: updatedPromotions });
  };

  const addPromotion = () => {
    const newId = Math.max(...promotions.map((p) => p.id), 0) + 1;
    const newPromotion = {
      id: newId,
      imageId: 0,
      imageUrl: "",
      title: `Promotion ${newId}`,
      subtitle: "Add subtitle",
    };
    setAttributes({ promotions: [...promotions, newPromotion] });
  };

  const removePromotion = (index) => {
    setAttributes({
      promotions: promotions.filter((_, i) => i !== index),
    });
  };

  return (
    <>
      <InspectorControls>
        <PanelBody title={__("Slider Settings", "ai-zippy")}>
          <ToggleControl
            label={__("Enable Slider Mode", "ai-zippy")}
            checked={attributes.isSlider}
            onChange={(val) => setAttributes({ isSlider: val })}
          />
          {attributes.isSlider && (
            <ToggleControl
              label={__("Enable Autoplay", "ai-zippy")}
              checked={attributes.autoplay}
              onChange={(val) => setAttributes({ autoplay: val })}
            />
          )}
        </PanelBody>
        <PanelBody title={__("Visibility Settings", "ai-zippy")}>
          <ToggleControl
            label={__("Hide Block on Frontend", "ai-zippy")}
            checked={isHidden}
            onChange={(val) => setAttributes({ isHidden: val })}
            help={
              isHidden
                ? __("Block is hidden on frontend.", "ai-zippy")
                : __("Block is visible on frontend.", "ai-zippy")
            }
          />
        </PanelBody>
      </InspectorControls>

      <div {...blockProps}>
        {isHidden && (
          <div className="block-hidden-notice">
            {__(
              "HIDDEN: This block will not be visible on the homepage.",
              "ai-zippy",
            )}
          </div>
        )}
        <h2 className="promotions-grid__title">
          <RichText
            tagName="span"
            value={sectionTitle}
            onChange={(val) => setAttributes({ sectionTitle: val })}
            placeholder={__("Promotions Today", "ai-zippy")}
          />
        </h2>

        <div className="promotions-db-notice">
          <div className="promotions-db-notice__icon">⭐</div>
          <div className="promotions-db-notice__text">
            <h4>{__("Fetching from Database", "ai-zippy")}</h4>
            <p>
              {__(
                "This block is now configured to pull content automatically from the 'Promotions' section in your WordPress sidebar.",
                "ai-zippy",
              )}
            </p>
            <a href="/wp-admin/edit.php?post_type=promotion" target="_blank" rel="noreferrer">
              {__("Manage Promotions in Admin →", "ai-zippy")}
            </a>
          </div>
        </div>

        {attributes.isSlider ? (
          <div className="promotions-grid-preview slider-preview">
             <p><em>{__("Slider Mode Enabled (Preview not available in editor)", "ai-zippy")}</em></p>
          </div>
        ) : (
          <div className="promotions-grid-preview grid-preview">
             <p><em>{__("Grid Mode Enabled (Preview not available in editor)", "ai-zippy")}</em></p>
          </div>
        )}
      </div>
    </>
  );
}
