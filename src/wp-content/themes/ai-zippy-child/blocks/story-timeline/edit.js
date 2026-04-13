import { useBlockProps, RichText, InspectorControls } from "@wordpress/block-editor";
import { PanelBody, Button, TextControl } from "@wordpress/components";
import { v4 as uuidv4 } from "uuid";

export default function Edit({ attributes, setAttributes }) {
	const { items } = attributes;

	const blockProps = useBlockProps({ className: "st" });

	const addItem = () => {
		const newItem = {
			id: uuidv4(),
			icon: "⭐",
			title: "New Item",
			description: "Add your description here.",
		};
		setAttributes({ items: [...items, newItem] });
	};

	const removeItem = (id) => {
		setAttributes({ items: items.filter((item) => item.id !== id) });
	};

	const updateItem = (id, field, value) => {
		setAttributes({
			items: items.map((item) =>
				item.id === id ? { ...item, [field]: value } : item
			),
		});
	};

	return (
		<>
			<InspectorControls>
				<PanelBody title="Timeline Items" initialOpen={true}>
					<Button variant="primary" onClick={addItem} style={{ width: "100%" }}>
						+ Add Item
					</Button>
					<p style={{ fontSize: "0.85rem", color: "#666", marginTop: "1rem" }}>
						Total items: {items.length}
					</p>
				</PanelBody>
			</InspectorControls>

			<div {...blockProps}>
				<div className="st__container">
					{items.map((item, index) => (
						<div key={item.id} className="st__item">
							<div className="st__item-header">
								<div className="st__index">{index + 1}</div>
								<Button
									size="small"
									variant="link"
									isDestructive
									onClick={() => removeItem(item.id)}
									style={{ marginLeft: "auto" }}
								>
									Remove
								</Button>
							</div>

							<div className="st__item-content">
								<div className="st__icon-input">
									<label>Icon (emoji or text)</label>
									<TextControl
										value={item.icon}
										onChange={(value) =>
											updateItem(item.id, "icon", value)
										}
										placeholder="📅"
										maxLength="5"
									/>
								</div>

								<div className="st__title-input">
									<label>Title</label>
									<RichText
										tagName="h3"
										value={item.title}
										onChange={(value) =>
											updateItem(item.id, "title", value)
										}
										placeholder="Enter title..."
										allowedFormats={[]}
									/>
								</div>

								<div className="st__description-input">
									<label>Description</label>
									<RichText
										tagName="p"
										value={item.description}
										onChange={(value) =>
											updateItem(item.id, "description", value)
										}
										placeholder="Enter description..."
										allowedFormats={["bold", "italic"]}
									/>
								</div>
							</div>
						</div>
					))}
				</div>

				<div style={{ marginTop: "2rem" }}>
					<Button variant="secondary" onClick={addItem}>
						+ Add Timeline Item
					</Button>
				</div>
			</div>
		</>
	);
}
