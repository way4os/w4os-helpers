/**
 * js/search.js
 *
 * @package    w4os/w4os-helpers
 *
 * @author     Gudule Lapointe <gudule@speculoos.world>
 * @link       https://github.com/way4os/w4os-helpers
 * @license    AGPLv3
 */

// The result chosen in the list of the web search shows its detail beside it without loading the page again: the page of that
// result (?id=, which works without this script too) is read, its detail put in place of the one shown, the address kept.
(() => {
	const show = async (url, push) => {
		const panel = document.getElementById("search-detail");
		if (!panel) {
			return false;
		}
		let page;
		try {
			const response = await fetch(url, { credentials: "same-origin" });
			if (!response.ok) {
				return false;
			}
			page = new DOMParser().parseFromString(await response.text(), "text/html");
		} catch (error) {
			return false;
		}
		const detail = page.getElementById("search-detail");
		if (!detail) {
			return false;
		}
		panel.innerHTML = detail.innerHTML;
		const chosen = new URL(url, location.href).search;
		document.querySelectorAll(".block-search .search-select").forEach((link) => {
			const current = new URL(link.href, location.href).search === chosen;
			current ? link.setAttribute("aria-current", "true") : link.removeAttribute("aria-current");
			link.closest("tr")?.classList.toggle("current", current);
		});
		window.W4OSTeleportLinks?.setup(panel);
		push && history.pushState({ search: true }, "", url);
		// On a narrow screen the detail is under the list
		panel.getBoundingClientRect().top > window.innerHeight && panel.scrollIntoView({ block: "start" });

		return true;
	};

	document.addEventListener("click", async (event) => {
		const link = event.target.closest(".block-search .search-select");
		// A new tab or window, as the visitor asks it
		if (!link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
			return;
		}
		event.preventDefault();
		(await show(link.href, true)) || (location.href = link.href);
	});

	window.addEventListener("popstate", () => {
		document.getElementById("search-detail") && show(location.href, false);
	});
})();
