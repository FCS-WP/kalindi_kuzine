import { createRoot } from "react-dom/client";
import OrderModeInfo from "./OrderModeInfo.jsx";

console.log("📦 Order Mode Info script loaded");

// Track if already mounted
let mounted = false;

// Wait for DOM to be ready before mounting
function initOrderModeInfo() {
	if (mounted) return;

	const container = document.getElementById("selected-mode-user-info");
	console.log("🔍 Looking for container #selected-mode-user-info:", container);

	if (container) {
		console.log("✅ Found container, mounting React app");
		mounted = true;
		createRoot(container).render(<OrderModeInfo />);
	} else {
		console.warn("⚠️ Container #selected-mode-user-info not found!");
	}
}

// Try multiple ways to ensure DOM is ready
if (document.readyState === "loading") {
	document.addEventListener("DOMContentLoaded", initOrderModeInfo);
} else {
	// DOM already loaded (e.g., if script is deferred)
	initOrderModeInfo();
}

// Also try right away in case it's inline
setTimeout(initOrderModeInfo, 0);