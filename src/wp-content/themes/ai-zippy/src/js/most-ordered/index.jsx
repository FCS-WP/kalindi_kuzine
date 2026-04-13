import { createRoot } from "react-dom/client";
import MostOrdered from "./MostOrdered.jsx";

console.log("📦 Most Ordered script loaded");

// Wait for DOM to be ready before mounting
function initMostOrdered() {
	const container = document.getElementById("ai-zippy-most-ordered");
	console.log("🔍 Looking for container #ai-zippy-most-ordered:", container);

	if (container) {
		console.log("✅ Found container, mounting React app");
		createRoot(container).render(<MostOrdered />);
	} else {
		console.warn("⚠️ Container #ai-zippy-most-ordered not found!");
	}
}

// Try multiple ways to ensure DOM is ready
if (document.readyState === "loading") {
	document.addEventListener("DOMContentLoaded", initMostOrdered);
} else {
	// DOM already loaded (e.g., if script is deferred)
	initMostOrdered();
}

// Also try right away in case it's inline
setTimeout(initMostOrdered, 0);
