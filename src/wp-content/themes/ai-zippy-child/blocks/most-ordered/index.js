import { registerBlockType } from "@wordpress/blocks";
import Edit from "./edit.js";
import "./style.scss";
import "./editor.scss";
import metadata from "./block.json";

registerBlockType(metadata.name, {
  ...metadata,
  edit: Edit,
  save: () => null, // Server-side rendering
});
