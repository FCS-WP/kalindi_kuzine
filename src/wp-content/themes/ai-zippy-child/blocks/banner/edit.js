import { __ } from "@wordpress/i18n";
import {
	useBlockProps,
	RichText,
	MediaUpload,
	MediaUploadCheck,
	InspectorControls,
	ColorPalette,
} from "@wordpress/block-editor";
import {
	PanelBody,
	Button,
	TextareaControl,
	TextControl,
} from "@wordpress/components";

export default function Edit({ attributes, setAttributes }) {
	const {
		headline,
		headlineDescription,
		brandName,
		brandDescription,
		buttonText,
		buttonUrl,
		backgroundImageUrl,
		backgroundImageId,
		accentColor,
		headlineColor,
		brandColor,
	} = attributes;

	const blockProps = useBlockProps();

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={__("Brand Info", "ai-zippy")}
					initialOpen={true}
				>
					<TextControl
						label={__("Brand Name", "ai-zippy")}
						value={brandName}
						onChange={(val) => setAttributes({ brandName: val })}
					/>
					<TextareaControl
						label={__("Brand Story", "ai-zippy")}
						value={brandDescription}
						onChange={(val) => setAttributes({ brandDescription: val })}
						rows={3}
					/>
					<p style={{ marginBottom: "8px", marginTop: "16px" }}>
						<strong>{__("Brand Name Color", "ai-zippy")}</strong>
					</p>
					<ColorPalette
						value={brandColor}
						onChange={(color) => setAttributes({ brandColor: color })}
					/>
				</PanelBody>

				<PanelBody
					title={__("Headline", "ai-zippy")}
					initialOpen={true}
				>
					<TextareaControl
						label={__("Headline (use Shift+Enter for line breaks)", "ai-zippy")}
						value={headline}
						onChange={(val) => setAttributes({ headline: val })}
						rows={3}
					/>
					<TextareaControl
						label={__("Headline Description", "ai-zippy")}
						value={headlineDescription}
						onChange={(val) => setAttributes({ headlineDescription: val })}
						rows={3}
					/>
					<p style={{ marginBottom: "8px", marginTop: "16px" }}>
						<strong>{__("Headline Color", "ai-zippy")}</strong>
					</p>
					<ColorPalette
						value={headlineColor}
						onChange={(color) => setAttributes({ headlineColor: color })}
					/>
				</PanelBody>

				<PanelBody
					title={__("CTA Button", "ai-zippy")}
					initialOpen={true}
				>
					<TextControl
						label={__("Button Text", "ai-zippy")}
						value={buttonText}
						onChange={(val) => setAttributes({ buttonText: val })}
					/>
					<TextControl
						label={__("Button URL", "ai-zippy")}
						value={buttonUrl}
						onChange={(val) => setAttributes({ buttonUrl: val })}
					/>
					<p style={{ marginBottom: "8px", marginTop: "16px" }}>
						<strong>{__("Button Color", "ai-zippy")}</strong>
					</p>
					<ColorPalette
						value={accentColor}
						onChange={(color) => setAttributes({ accentColor: color })}
					/>
				</PanelBody>

				<PanelBody
					title={__("Background Image", "ai-zippy")}
					initialOpen={true}
				>
					<MediaUploadCheck>
						<MediaUpload
							onSelect={(media) =>
								setAttributes({
									backgroundImageId: media.id,
									backgroundImageUrl: media.url,
								})
							}
							allowedTypes={["image"]}
							value={backgroundImageId}
							render={({ open }) => (
								<div style={{ marginTop: "8px" }}>
									<p style={{ marginBottom: "4px" }}>
										<strong>{__("Top Image (Food Photography)", "ai-zippy")}</strong>
									</p>
									{backgroundImageUrl && (
										<img
											src={backgroundImageUrl}
											alt=""
											style={{
												maxWidth: "100%",
												marginBottom: "8px",
												borderRadius: "8px",
											}}
										/>
									)}
									<Button onClick={open} variant="secondary">
										{backgroundImageUrl
											? __("Replace", "ai-zippy")
											: __("Select Image", "ai-zippy")}
									</Button>
									{backgroundImageUrl && (
										<Button
											onClick={() =>
												setAttributes({
													backgroundImageId: 0,
													backgroundImageUrl: "",
												})
											}
											variant="link"
											isDestructive
											style={{ marginLeft: "8px" }}
										>
											{__("Remove", "ai-zippy")}
										</Button>
									)}
								</div>
							)}
						/>
					</MediaUploadCheck>
				</PanelBody>
			</InspectorControls>

			<div {...blockProps}>
				{/* Top Image Layer */}
				<div
					className="banner__top-image"
					style={backgroundImageUrl ? { backgroundImage: `url(${backgroundImageUrl})` } : {}}
				/>

				{/* Semi-circle Overlay */}
				<div className="banner__semicircle">
					<div className="banner__semicircle-content">
						<RichText
							tagName="h2"
							className="banner__headline"
							value={headline}
							onChange={(val) => setAttributes({ headline: val })}
							placeholder={__("Pure Vegetarian.\nAuthentic.\nMade with Care.", "ai-zippy")}
							allowedFormats={[]}
							style={{ color: headlineColor }}
						/>
						<RichText
							tagName="p"
							className="banner__headline-desc"
							value={headlineDescription}
							onChange={(val) => setAttributes({ headlineDescription: val })}
							placeholder={__("Flavorful, Plant Based...", "ai-zippy")}
						/>
					</div>
				</div>

				{/* Bottom Content Area */}
				<div className="banner__content">
					<RichText
						tagName="h3"
						className="banner__brand-name"
						value={brandName}
						onChange={(val) => setAttributes({ brandName: val })}
						placeholder={__("KALUNDI'S KUZINES", "ai-zippy")}
						allowedFormats={[]}
						style={{ color: brandColor }}
					/>
					<RichText
						tagName="p"
						className="banner__brand-desc"
						value={brandDescription}
						onChange={(val) => setAttributes({ brandDescription: val })}
						placeholder={__("Capturing kalindi inspiration...", "ai-zippy")}
					/>
					<div className="banner__cta">
						<span className="banner__btn" style={{ backgroundColor: accentColor }}>
							<RichText
								tagName="span"
								value={buttonText}
								onChange={(val) => setAttributes({ buttonText: val })}
								placeholder={__("Order Now", "ai-zippy")}
								allowedFormats={[]}
							/>
						</span>
					</div>
				</div>
			</div>
		</>
	);
}