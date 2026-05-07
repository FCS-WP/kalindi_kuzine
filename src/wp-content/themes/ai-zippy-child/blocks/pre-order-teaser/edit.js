import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
	MediaUpload,
	MediaUploadCheck,
	RichText,
} from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	Button,
} from '@wordpress/components';

export default function Edit({ attributes, setAttributes }) {
	const {
		title,
		description,
		buttonLabel,
		buttonUrl,
		imageUrl,
	} = attributes;

	const onSelectImage = (media) => {
		setAttributes({
			imageUrl: media.url,
			imageId: media.id,
		});
	};

	const blockProps = useBlockProps({
		className: 'pre-order-teaser',
	});

	return (
		<>
			<InspectorControls>
				<PanelBody title={__('Content Settings', 'ai-zippy')}>
					<TextControl
						label={__('Button Label', 'ai-zippy')}
						value={buttonLabel}
						onChange={(val) => setAttributes({ buttonLabel: val })}
					/>
					<TextControl
						label={__('Button URL', 'ai-zippy')}
						value={buttonUrl}
						onChange={(val) => setAttributes({ buttonUrl: val })}
					/>
					<MediaUploadCheck>
						<MediaUpload
							onSelect={onSelectImage}
							allowedTypes={['image']}
							value={attributes.imageId}
							render={({ open }) => (
								<Button
									variant="secondary"
									onClick={open}
									className="is-primary"
								>
									{!imageUrl
										? __('Select Right Image', 'ai-zippy')
										: __('Replace Right Image', 'ai-zippy')}
								</Button>
							)}
						/>
					</MediaUploadCheck>
				</PanelBody>
			</InspectorControls>

			<div {...blockProps}>
				<div className="pre-order-teaser__container">
					<div className="pre-order-teaser__banner">
						<div className="pre-order-teaser__text">
							<RichText
								tagName="h2"
								className="pre-order-teaser__title"
								value={title}
								onChange={(val) => setAttributes({ title: val })}
								placeholder={__('Enter title...', 'ai-zippy')}
							/>
							<RichText
								tagName="p"
								className="pre-order-teaser__description"
								value={description}
								onChange={(val) => setAttributes({ description: val })}
								placeholder={__('Enter description...', 'ai-zippy')}
							/>
							<div className="pre-order-teaser__actions">
								<span className="pre-order-teaser__button">
									{buttonLabel}
								</span>
							</div>
						</div>
						<div className="pre-order-teaser__image">
							{imageUrl ? (
								<img src={imageUrl} alt="" />
							) : (
								<div className="pre-order-teaser__image-placeholder">
									{__('Select an image in the sidebar', 'ai-zippy')}
								</div>
							)}
						</div>
					</div>
				</div>
			</div>
		</>
	);
}
