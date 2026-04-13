import { __ } from '@wordpress/i18n';
import { useBlockProps, RichText, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ColorPalette } from '@wordpress/components';

export default function Edit({ attributes, setAttributes }) {
	const { title, subtitle, backgroundColor, textColor } = attributes;
	const blockProps = useBlockProps({
		className: 'page-header-banner',
		style: { backgroundColor, color: textColor }
	});

	return (
		<>
			<InspectorControls>
				<PanelBody title={__('Colors', 'ai-zippy')}>
					<p>{__('Background Color', 'ai-zippy')}</p>
					<ColorPalette
						value={backgroundColor}
						onChange={(val) => setAttributes({ backgroundColor: val })}
					/>
					<p>{__('Text Color', 'ai-zippy')}</p>
					<ColorPalette
						value={textColor}
						onChange={(val) => setAttributes({ textColor: val })}
					/>
				</PanelBody>
			</InspectorControls>
			<div {...blockProps}>
				<div className="page-header-banner__container">
					<div className="page-header-banner__breadcrumbs" style={{ opacity: 0.5 }}>
						{__('Breadcrumbs will appear here on front-end', 'ai-zippy')}
					</div>
					<RichText
						tagName="h1"
						className="page-header-banner__title"
						value={title}
						onChange={(val) => setAttributes({ title: val })}
						placeholder={__('Enter Page Title...', 'ai-zippy')}
					/>
					<RichText
						tagName="p"
						className="page-header-banner__subtitle"
						value={subtitle}
						onChange={(val) => setAttributes({ subtitle: val })}
						placeholder={__('Enter Subtitle (Optional)...', 'ai-zippy')}
					/>
				</div>
			</div>
		</>
	);
}
