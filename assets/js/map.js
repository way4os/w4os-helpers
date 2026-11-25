/**
 * js/map.js: the map of the grid in a browser (the block map, MapBlock), on Leaflet: its tiles as the grid serves them, the name of
 * each region above it from close enough, a click on a region giving its name and its teleport link (the one of the list of the
 * block, w4os-views' teleport link, its menu included).
 *
 * The method of the Second Life maps (maps.secondlife.com) for the tiles of OpenSimulator, as GwynethLlewelyn/goswi applies it
 * (leaflet-gridmap.js, BSD-3): a simple projection (L.CRS.Simple) whose unit is a region, x east and y north from the origin of the
 * grid; Leaflet's zoom 8 is the tiles' zoom 1 (a region to a tile of 256 pixels), each zoom out a tile of twice as many regions, named
 * by the region of its bottom left corner. Closer than 8, the tiles of zoom 1 enlarged.
 *
 * @package    w4os/w4os-helpers
 * @license    AGPLv3
 */
(() => {
	if (typeof L === "undefined") {
		return;
	}
	// Leaflet's zooms: a region 2^zoom pixels wide; 8 the tiles' own zoom 1 (256 px), 10 enlarged four times
	const ZOOM = { min: 1, native: 8, max: 10 };
	// The view a map opens on: a region about 128 px wide, never smaller than 32 px (a large grid, regions far apart)
	const VIEW = { closest: 7, farthest: 5 };
	// The names of the regions from 64 px a region
	const LABELS = 6;
	const read = (element, name) => {
		try {
			return JSON.parse(element.dataset[name] || "{}") || {};
		} catch (error) {
			return {};
		}
	};

	// The tiles of the grid: the zoom of the tiles from the one of the layer (8 is their 1), the region of a tile from its place
	const GridTiles = L.TileLayer.extend({
		getTileUrl(coords) {
			const zoom = ZOOM.native + 1 - Math.min(this._tileZoom, ZOOM.native);
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
			name: item.dataset.name || item.textContent.trim(),
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
		const grid = L.latLngBounds(min, max);
		// A little water around the grid, to move it about
		const around = grid.pad(0.5);

		// The whole height left in the window, when the page asks it (the map is the page)
		const fill = () => {
			const top = view.getBoundingClientRect().top + window.scrollY;
			view.style.height = Math.max(288, window.innerHeight - top - 16) + "px";
		};
		view.hidden = false;
		block.classList.add("is-drawn");
		block.dataset.mapFill && fill();
		const map = L.map(view, {
			crs: L.CRS.Simple,
			minZoom: ZOOM.min,
			maxZoom: ZOOM.max,
			maxBounds: around,
			zoomControl: false,
			attributionControl: false,
		});
		L.control.zoom({ zoomInTitle: texts.zoom_in || "Zoom in", zoomOutTitle: texts.zoom_out || "Zoom out" }).addTo(map);
		new GridTiles(block.dataset.mapTiles, {
			minZoom: ZOOM.min,
			maxZoom: ZOOM.max,
			maxNativeZoom: ZOOM.native,
			tileSize: 256,
			bounds: around,
		}).addTo(map);
		// Its whole, a region about 128 px wide at most, 32 px at least, on its center (the default region's middle, the engine's)
		const zoom = Math.max(VIEW.farthest, Math.min(VIEW.closest, map.getBoundsZoom(grid)));
		const middle = center.x !== undefined && center.y !== undefined ? [Number(center.y), Number(center.x)] : grid.getCenter();
		map.setView(middle, zoom);
		if (block.dataset.mapFill) {
			window.addEventListener("resize", () => {
				fill();
				map.invalidateSize();
			});
		}

		// The name of each region above it, from close enough
		regions.forEach((region) => {
			L.tooltip({ permanent: true, direction: "top", className: "map-label", offset: [0, 0], interactive: false })
				.setLatLng([region.y + region.height, region.x + region.width / 2])
				.setContent(region.name)
				.addTo(map);
		});
		const labels = () => view.classList.toggle("map-labels-hidden", map.getZoom() < LABELS);
		map.on("zoomend", labels);
		labels();

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
