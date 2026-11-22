/**
 * js/guide.js
 *
 * @package    w4os/w4os-helpers
 * @subpackage    w4os/w4os-helpers/guide
 *
 * @author     Gudule Lapointe <gudule@speculoos.world>
 * @link       https://github.com/way4os/w4os-helpers
 * @license    AGPLv3
 */

// The mouse wheel scrolls the list of the guide sideways while it is one row wider than the panel (in a viewer); there is no list
// when the guide is empty
const guideList = document.querySelector("#guide .list");
if (guideList) {
	guideList.addEventListener("wheel", function (event) {
		// Shift keeps the vertical scrolling, and so does a list that fits
		if (event.shiftKey || this.scrollWidth <= this.clientWidth) {
			return;
		}

		// Prevent the default vertical scrolling behavior
		event.preventDefault();

		// Calculate the scroll amount
		const scrollAmount = event.deltaY * 1.5;

		// Set the horizontal scroll position
		this.scrollBy({
			left: scrollAmount,
			behavior: "smooth", // Add a smooth scrolling effect
		});
	});
}

// The name of a place without a picture fills it: the largest size at which it fits, its words whole, again when the card is resized
function guideFitTitle(title) {
	const box = title.parentElement;
	const style = getComputedStyle(title);
	const room = title.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight);
	// Its lines as they are drawn: the widest is the width of the text
	const text = document.createRange();
	text.selectNodeContents(title);
	let low = 6;
	let high = Math.max(low, box.clientHeight);
	while (high - low > 0.5) {
		const size = (low + high) / 2;
		title.style.fontSize = size + "px";
		const fits = text.getBoundingClientRect().width <= room && title.scrollHeight <= box.clientHeight;
		fits ? (low = size) : (high = size);
	}
	title.style.fontSize = low + "px";
}
const guideTitles = document.querySelectorAll("#guide .picture-title");
if (guideTitles.length) {
	const fitAll = () => guideTitles.forEach(guideFitTitle);
	fitAll();
	// Again once the font of the page is there
	if (document.fonts) {
		document.fonts.ready.then(fitAll);
		document.fonts.addEventListener("loadingdone", fitAll);
	}
	if (window.ResizeObserver) {
		const resized = new ResizeObserver((entries) => entries.forEach((entry) => guideFitTitle(entry.target.querySelector(".picture-title"))));
		guideTitles.forEach((title) => resized.observe(title.parentElement));
	}
}
