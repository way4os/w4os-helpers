/**
 * js/map.js: the map of the grid in a browser (the block map, MapBlock), on Leaflet: its tiles as the grid serves them, a click on a
 * region giving its name and its teleport link (the one of the list of the block, w4os-views' teleport link, its menu included).
 *
 * The method of the Second Life maps (maps.secondlife.com) for the tiles of OpenSimulator, as GwynethLlewelyn/goswi applies it
 * (leaflet-gridmap.js, BSD-3): a simple projection (L.CRS.Simple) whose unit is a region, x east and y north from the origin of the
 * grid; Leaflet's zoom 8 is the tiles' zoom 1 (a region to a tile of 256 pixels), each zoom out a tile of twice as many regions, named
 * by the region of its bottom left corner.
 *
 * @package    w4os/w4os-helpers
 * @license    AGPLv3
 */
(() => {
	if (typeof L === "undefined") {
		return;
	}
	const read = (element, name) => {
		try {
			return JSON.parse(element.dataset[name] || "{}") || {};
		} catch (error) {
			return {};
		}
	};

	// The tiles of the grid: zoom 1 to 8 of the tiles (zoomReverse, zoomOffset), the region of a tile from its place in the layer
	const GridTiles = L.TileLayer.extend({
		getTileUrl(coords) {
			const zoom = this._getZoomForUrl();
			const regions = Math.pow(2, zoom - 1);

			return L.Util.template(this._url, {
				z: zoom,
				x: coords.x * regions,
				y: (Math.abs(coords.y) - 1) * regions,
			});
		},
	});

	document.querySelectorAll(".block-map .map[data-map-tiles]").forEach((block) => {
		const view = block.querySelector(".map-view");
		const regions = [...block.querySelectorAll(".map-region")].map((item) => ({
			item,
			x: Number(item.dataset.x),
			y: Number(item.dataset.y),
			width: Number(item.dataset.width) || 1,
			height: Number(item.dataset.height) || 1,
		}));
		if (!view || regions.length === 0) {
			return;
		}
		const bounds = read(block, "mapBounds");
		const center = read(block, "mapCenter");
		const texts = read(block, "mapTexts");
		// The bounds of the grid in regions, the max excluded (the engine's, else the list's)
		const min = [Number(bounds.minY ?? Math.min(...regions.map((r) => r.y))), Number(bounds.minX ?? Math.min(...regions.map((r) => r.x)))];
		const max = [Number(bounds.maxY ?? Math.max(...regions.map((r) => r.y + r.height))), Number(bounds.maxX ?? Math.max(...regions.map((r) => r.x + r.width)))];
		// A little water around the grid, to move it about
		const around = L.latLngBounds(min, max).pad(0.5);

		view.hidden = false;
		block.classList.add("is-drawn");
		const map = L.map(view, {
			crs: L.CRS.Simple,
			minZoom: 1,
			maxZoom: 8,
			maxBounds: around,
			zoomControl: false,
			attributionControl: false,
		});
		L.control.zoom({ zoomInTitle: texts.zoom_in || "Zoom in", zoomOutTitle: texts.zoom_out || "Zoom out" }).addTo(map);
		new GridTiles(block.dataset.mapTiles, {
			minZoom: 1,
			maxZoom: 8,
			zoomOffset: 1,
			zoomReverse: true,
			tileSize: 256,
			bounds: around,
		}).addTo(map);
		map.fitBounds(L.latLngBounds(min, max));
		// Its center in view (the middle of its default region, the engine's)
		if (center.x !== undefined && center.y !== undefined && regions.length > 1) {
			map.panTo([Number(center.y), Number(center.x)]);
		}

		// A click on a region: its name and its teleport link, the ones of the list
		map.on("click", (event) => {
			const x = Math.floor(event.latlng.lng);
			const y = Math.floor(event.latlng.lat);
			const region = regions.find((r) => x >= r.x && x < r.x + r.width && y >= r.y && y < r.y + r.height);
			if (!region) {
				return;
			}
			const content = document.createElement("div");
			content.className = "map-popup";
			content.append(...[...region.item.childNodes].map((node) => node.cloneNode(true)));
			L.popup({ maxWidth: 280 }).setLatLng(event.latlng).setContent(content).openOn(map);
			window.W4OSTeleportLinks?.setup(content);
		});
	});
})();
