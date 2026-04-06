/**
 * Sticky header — adds shadow on scroll.
 */
export function initHeader() {
	const header = document.querySelector("header.wp-block-group");
	if (!header) return;

	window.addEventListener(
		"scroll",
		() => {
			header.classList.toggle("is-scrolled", window.scrollY > 10);
		},
		{ passive: true },
	);
}
