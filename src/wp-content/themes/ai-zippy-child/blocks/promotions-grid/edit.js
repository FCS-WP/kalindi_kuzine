import { __ } from "@wordpress/i18n";
import {
	useBlockProps,
	RichText,
	MediaUpload,
	MediaUploadCheck,
} from "@wordpress/block-editor";
import { Button } from "@wordpress/components";

export default function Edit({ attributes, setAttributes }) {
	const { sectionTitle, promotions } = attributes;

	const blockProps = useBlockProps();

	const updatePromotion = (index, field, value) => {
		const updatedPromotions = [...promotions];
		updatedPromotions[index] = {
			...updatedPromotions[index],
			[field]: value,
		};
		setAttributes({ promotions: updatedPromotions });
	};

	const addPromotion = () => {
		const newId = Math.max(...promotions.map(p => p.id), 0) + 1;
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
		<div {...blockProps}>
			<h2 className="promotions-grid__title">
				<RichText
					tagName="span"
					value={sectionTitle}
					onChange={(val) => setAttributes({ sectionTitle: val })}
					placeholder={__("Promotions Today", "ai-zippy")}
				/>
			</h2>

			<div className="promotions-grid__grid">
				{promotions.map((promo, index) => (
					<div key={promo.id} className="promotions-grid__card">
						<MediaUploadCheck>
							<MediaUpload
								onSelect={(media) => {
									updatePromotion(index, "imageId", media.id);
									updatePromotion(index, "imageUrl", media.url);
								}}
								allowedTypes={["image"]}
								value={promo.imageId}
								render={({ open }) => (
									<div
										className="promotions-grid__card-image"
										style={promo.imageUrl ? { backgroundImage: `url(${promo.imageUrl})` } : {}}
										onClick={open}
										role="button"
										tabIndex={0}
									/>
								)}
							/>
						</MediaUploadCheck>

						<div className="promotions-grid__card-overlay">
							<div className="promotions-grid__card-content">
								<h3 className="promotions-grid__card-title">
									<RichText
										tagName="span"
										value={promo.title}
										onChange={(val) => updatePromotion(index, "title", val)}
										placeholder={__("FREE DELIVERY", "ai-zippy")}
									/>
								</h3>
								<p className="promotions-grid__card-subtitle">
									<RichText
										tagName="span"
										value={promo.subtitle}
										onChange={(val) => updatePromotion(index, "subtitle", val)}
										placeholder={__("Minimum order of $XX", "ai-zippy")}
									/>
								</p>
							</div>
						</div>

						{promotions.length > 1 && (
							<Button
								onClick={() => removePromotion(index)}
								variant="link"
								isDestructive
								size="small"
							>
								{__("Remove", "ai-zippy")}
							</Button>
						)}
					</div>
				))}
			</div>

			<Button onClick={addPromotion} variant="primary">
				{__("+ Add Promotion", "ai-zippy")}
			</Button>
		</div>
	);
}