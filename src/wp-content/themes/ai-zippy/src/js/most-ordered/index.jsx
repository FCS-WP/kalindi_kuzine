import { createRoot } from "react-dom/client";
import MostOrdered from "./MostOrdered.jsx";

console.log("📦 Most Ordered script loaded");

let mounted = false;

// Wait for DOM to be ready before mounting
function initMostOrdered() {
	if (mounted) return;

	const container = document.getElementById("ai-zippy-most-ordered");
	if (container) {
		mounted = true;
		const props = {
			limit: container.dataset.limit ? parseInt(container.dataset.limit) : 4,
			menuUrl: container.dataset.menuUrl || ""
		};
		createRoot(container).render(<MostOrdered {...props} />);
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
