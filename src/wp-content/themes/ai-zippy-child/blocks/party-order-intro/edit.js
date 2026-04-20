import { __ } from "@wordpress/i18n";
import {
  useBlockProps,
  RichText,
  MediaPlaceholder,
  InspectorControls,
} from "@wordpress/block-editor";
import { PanelBody, Button } from "@wordpress/components";

export default function Edit({ attributes, setAttributes }) {
  const { title, content, topImageUrl, sideImage1Url, sideImage2Url } =
    attributes;
  const blockProps = useBlockProps({ className: "party-order-intro" });

  const onSelectImage = (attrNameId, attrNameUrl) => (media) => {
    setAttributes({
      [attrNameId]: media.id,
      [attrNameUrl]: media.url,
    });
  };

  const removeImage = (attrNameId, attrNameUrl) => {
    setAttributes({
      [attrNameId]: undefined,
      [attrNameUrl]: undefined,
    });
  };

  return (
    <>
      <InspectorControls>
        <PanelBody title={__("Main Image", "ai-zippy")}>
          {topImageUrl && (
            <Button
              isDestructive
              onClick={() => removeImage("topImageId", "topImageUrl")}
            >
              {__("Remove Main Image", "ai-zippy")}
            </Button>
          )}
        </PanelBody>
        <PanelBody title={__("Side Images", "ai-zippy")}>
          {sideImage1Url && (
            <Button
              isDestructive
              onClick={() => removeImage("sideImage1Id", "sideImage1Url")}
              style={{ marginBottom: "10px" }}
            >
              {__("Remove Side Image 1", "ai-zippy")}
            </Button>
          )}
          {sideImage2Url && (
            <Button
              isDestructive
              onClick={() => removeImage("sideImage2Id", "sideImage2Url")}
            >
              {__("Remove Side Image 2", "ai-zippy")}
            </Button>
          )}
        </PanelBody>
      </InspectorControls>

      <div {...blockProps}>
        {/* Top Hero Image */}
        <div className="party-order-intro__top-image-wrap">
          {topImageUrl ? (
            <img
              src={topImageUrl}
              alt=""
              className="party-order-intro__hero-img"
            />
          ) : (
            <MediaPlaceholder
              onSelect={onSelectImage("topImageId", "topImageUrl")}
              allowedTypes={["image"]}
              multiple={false}
              labels={{ title: __("Main Photo", "ai-zippy") }}
            />
          )}
        </div>

        <div className="party-order-intro__main-content">
          <div className="party-order-intro__columns">
            {/* Left Column: Text */}
            <div className="party-order-intro__text-col">
              <RichText
                tagName="h2"
                className="party-order-intro__title"
                value={title}
                onChange={(val) => setAttributes({ title: val })}
                placeholder={__("Enter Title...", "ai-zippy")}
              />
              <RichText
                tagName="div"
                className="party-order-intro__content"
                value={content}
                onChange={(val) => setAttributes({ content: val })}
                placeholder={__("Enter Description...", "ai-zippy")}
              />
            </div>

            {/* Right Column: Staggered Photos */}
            <div className="party-order-intro__photos-col">
              <div className="party-order-intro__side-img-wrap img-1">
                {sideImage1Url ? (
                  <img src={sideImage1Url} alt="" />
                ) : (
                  <MediaPlaceholder
                    onSelect={onSelectImage("sideImage1Id", "sideImage1Url")}
                    allowedTypes={["image"]}
                    multiple={false}
                    labels={{ title: __("Side Photo 1", "ai-zippy") }}
                  />
                )}
              </div>
              <div className="party-order-intro__side-img-wrap img-2">
                {sideImage2Url ? (
                  <img src={sideImage2Url} alt="" />
                ) : (
                  <MediaPlaceholder
                    onSelect={onSelectImage("sideImage2Id", "sideImage2Url")}
                    allowedTypes={["image"]}
                    multiple={false}
                    labels={{ title: __("Side Photo 2", "ai-zippy") }}
                  />
                )}
              </div>
            </div>
          </div>
        </div>
      </div>
    </>
  );
}
