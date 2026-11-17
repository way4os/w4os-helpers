/**
 * js/guide.js
 *
 * @package    magicoli/opensim-helpers
 * @subpackage    magicoli/opensim-helpers/guide
 *
 * @author     Gudule Lapointe <gudule@speculoos.world>
 * @link       https://github.com/magicoli/opensim-helpers
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
