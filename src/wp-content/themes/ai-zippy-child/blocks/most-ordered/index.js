import { registerBlockType } from "@wordpress/blocks";
import Edit from "./edit.js";
import metadata from "./block.json";

registerBlockType(metadata.name, {
	...metadata,
	edit: Edit,
	save: () => null, // Server-side rendering
});
