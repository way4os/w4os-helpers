/**
 * js/map.js: the map of the grid in a browser (the block map, MapBlock), on Leaflet: its tiles as the grid serves them, the name of
 * each region above it from close enough, a click on a region giving its name and its teleport link (the one of the list of the
 * block, w4os-views' teleport link, its menu included); a picker of the regions by their names (Tom Select) taking the map to one.
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
	// Leaflet's zooms: a region 2^zoom pixels wide; 8 the tiles' own zoom 1 (256 px), 10 enlarged four times (the closest at least)
	const ZOOM = { min: 1, native: 8, max: 10 };
	// The view a map opens on: its regions fitting it with a margin, never smaller than 128 px a region (then on the default region)
	const VIEW = { farthest: 7, margin: 16 };
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

		// The whole height left in the window, down to its bottom, when the page asks it (the map is the page)
		const fill = () => {
			const top = view.getBoundingClientRect().top + window.scrollY;
			view.style.height = Math.max(288, window.innerHeight - top) + "px";
		};
		// The closest zoom: a region filling the smaller side of the map, its tile enlarged (a finer tile keeps its own pixels)
		const closest = () => Math.max(ZOOM.max, Math.ceil(Math.log2(Math.max(1, Math.min(view.clientWidth, view.clientHeight)))));
		view.hidden = false;
		block.classList.add("is-drawn");
		block.dataset.mapFill && fill();
		const map = L.map(view, {
			crs: L.CRS.Simple,
			minZoom: ZOOM.min,
			maxZoom: closest(),
			zoomSnap: 0.25,
			maxBounds: around,
			zoomControl: false,
			attributionControl: false,
		});
		L.control.zoom({ zoomInTitle: texts.zoom_in || "Zoom in", zoomOutTitle: texts.zoom_out || "Zoom out" }).addTo(map);
		new GridTiles(block.dataset.mapTiles, {
			minZoom: ZOOM.min,
			maxZoom: Infinity,
			maxNativeZoom: ZOOM.native,
			tileSize: 256,
			bounds: around,
		}).addTo(map);
		// Its regions fitting it, up to the closest zoom; a grid too large for 128 px a region on its center (the default region's
		// middle, the engine's)
		const fitted = map.getBoundsZoom(grid, false, L.point(2 * VIEW.margin, 2 * VIEW.margin));
		const middle = center.x !== undefined && center.y !== undefined ? [Number(center.y), Number(center.x)] : grid.getCenter();
		fitted >= VIEW.farthest ? map.setView(grid.getCenter(), fitted) : map.setView(middle, VIEW.farthest);
		window.addEventListener("resize", () => {
			block.dataset.mapFill && fill();
			map.invalidateSize();
			map.setMaxZoom(closest());
		});

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

		// The popup of a region: its name and its teleport link, the ones of the list
		const popup = (region, latlng) => {
			const content = document.createElement("div");
			content.className = "map-popup";
			content.append(...[...region.item.childNodes].map((node) => node.cloneNode(true)));
			L.popup({ maxWidth: 280 }).setLatLng(latlng).setContent(content).openOn(map);
			window.W4OSTeleportLinks?.setup(content);
		};
		map.on("click", (event) => {
			const x = Math.floor(event.latlng.lng);
			const y = Math.floor(event.latlng.lat);
			const region = regions.find((r) => x >= r.x && x < r.x + r.width && y >= r.y && y < r.y + r.height);
			region && popup(region, event.latlng);
		});

		// The region picker over the map, top right (Tom Select, beside Leaflet's controls: they keep the events of the mouse from the
		// page, it needs them): the regions matching what is typed; the one picked fills the map, its popup open
		if (typeof TomSelect !== "undefined") {
			const label = texts.find_region || "Find a region";
			const box = document.createElement("div");
			const select = document.createElement("select");
			box.className = "map-picker";
			select.setAttribute("aria-label", label);
			box.append(select);
			view.after(box);
			new TomSelect(select, {
				options: regions.map((region, index) => ({ value: String(index), text: region.name })),
				sortField: { field: "text" },
				placeholder: label,
				maxOptions: 50,
				render: { no_results: (data, escape) => `<div class="no-results">${escape(texts.no_region || "No region found")}</div>` },
				onChange(value) {
					const region = regions[Number(value)];
					if (!region) {
						return;
					}
					const area = L.latLngBounds([region.y, region.x], [region.y + region.height, region.x + region.width]);
					map.once("moveend", () => popup(region, area.getCenter()));
					map.flyToBounds(area, { padding: [VIEW.margin, VIEW.margin] });
					this.clear(true);
					this.blur();
				},
			});
		}
	});
})();
