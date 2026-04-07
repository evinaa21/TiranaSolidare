/**
 * map-component.js
 * ---------------------------------------------------
 * Reusable Leaflet map components for Tirana Solidare.
 *
 * Provides:
 *  - TSMap.picker(containerId, options)  — click-to-place map for forms
 *  - TSMap.display(containerId, options) — read-only map for detail views
 *  - TSMap.overview(containerId, markers) — multi-marker overview map
 * ---------------------------------------------------
 */

function tsMapDetectBasePath() {
  if (typeof window !== 'undefined' && typeof window.TS_BASE_PATH === 'string') {
    return window.TS_BASE_PATH;
  }

  const candidates = [document.currentScript?.src || ''];
  document.querySelectorAll('script[src]').forEach((script) => candidates.push(script.src));

  for (const candidate of candidates) {
    if (!candidate) continue;
    try {
      const pathname = new URL(candidate, window.location.href).pathname;
      const marker = '/assets/js/map-component.js';
      const index = pathname.indexOf(marker);
      if (index >= 0) {
        return pathname.slice(0, index);
      }
    } catch (err) {
      // Ignore malformed URLs.
    }
  }

  return '';
}

const TS_MAP_BASE_PATH = tsMapDetectBasePath();
const tsMapPath = (typeof window !== 'undefined' && typeof window.tsAppPath === 'function')
  ? window.tsAppPath
  : ((path = '') => {
      const trimmed = String(path || '').replace(/^\/+/, '');
      if (!trimmed) {
        return TS_MAP_BASE_PATH || '/';
      }
      return `${TS_MAP_BASE_PATH}/${trimmed}`.replace(/\/+/g, '/');
    });
const TS_MAP_API_BASE = (typeof window !== 'undefined' && typeof window.TS_API_BASE === 'string')
  ? window.TS_API_BASE
  : tsMapPath('api');

const TSMap = (() => {
  // Default center: Tirana, Albania
  const TIRANA = [41.3275, 19.8187];
  const DEFAULT_ZOOM = 13;
  const DETAIL_ZOOM = 15;

  // Custom marker icon using Tirana Solidare brand color
  function createIcon(color = '#00715D') {
    return L.divIcon({
      className: 'ts-map-marker',
      html: `<svg xmlns="http://www.w3.org/2000/svg" width="32" height="42" viewBox="0 0 32 42" fill="none">
        <path d="M16 0C7.163 0 0 7.163 0 16c0 12 16 26 16 26s16-14 16-26C32 7.163 24.837 0 16 0z" fill="${color}"/>
        <circle cx="16" cy="16" r="6" fill="white"/>
      </svg>`,
      iconSize: [32, 42],
      iconAnchor: [16, 42],
      popupAnchor: [0, -42],
    });
  }

  // Event icon (green)
  const eventIcon = createIcon('#00715D');
  // Help request icon (orange-red)
  const requestIcon = createIcon('#E17254');
  // Offer icon (blue)
  const offerIcon = createIcon('#3B82F6');

  // Shared tile layer config
  function addTiles(map) {
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
      maxZoom: 19,
    }).addTo(map);
    return map;
  }

  // ══════════════════════════════════════════════════
  //  PICKER — Interactive location selector for forms
  // ══════════════════════════════════════════════════

  /**
   * Creates an interactive map picker inside a container.
   * @param {string} containerId - DOM element ID for the map
   * @param {object} options
   *   - latInput:  ID of hidden input for latitude
   *   - lngInput:  ID of hidden input for longitude
   *   - addressInput: ID of text input for address (optional, for reverse geocoding)
   *   - initialLat: starting latitude (default: Tirana center)
   *   - initialLng: starting longitude (default: Tirana center)
   *   - onSelect: callback(lat, lng, address) when location is picked
   * @returns {object} { map, marker, setPosition(lat, lng) }
   */
  function picker(containerId, options = {}) {
    const container = document.getElementById(containerId);
    if (!container) return null;

    const lat = options.initialLat || null;
    const lng = options.initialLng || null;
    const center = (lat && lng) ? [lat, lng] : TIRANA;
    const zoom = (lat && lng) ? DETAIL_ZOOM : DEFAULT_ZOOM;

    const map = L.map(containerId, {
      center: center,
      zoom: zoom,
      scrollWheelZoom: true,
    });
    addTiles(map);

    let marker = null;

    // Place marker if initial position exists
    if (lat && lng) {
      marker = L.marker(center, { icon: eventIcon, draggable: true }).addTo(map);
      bindMarkerDrag(marker, options);
    }

    // Instruction overlay
    const instructionDiv = L.DomUtil.create('div', 'ts-map-instruction');
    instructionDiv.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/></svg> Kliko në hartë për të zgjedhur vendndodhjen';
    const InstructionControl = L.Control.extend({
      options: { position: 'topright' },
      onAdd: function() { return instructionDiv; },
    });
    map.addControl(new InstructionControl());

    let searchSelected = false;
    if (options.showSearch !== false) {
      // Search box for address
      const searchDiv = L.DomUtil.create('div', 'ts-map-search');
      searchDiv.innerHTML = `
        <div class="ts-map-search__inner">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
          <input type="text" class="ts-map-search__input" placeholder="Kërko adresë në Tiranë..." />
        </div>
        <div class="ts-map-search__results"></div>
      `;
      const SearchControl = L.Control.extend({
        options: { position: 'topleft' },
        onAdd: function() { return searchDiv; },
      });
      map.addControl(new SearchControl());

      // Prevent map clicks when interacting with search
      L.DomEvent.disableClickPropagation(searchDiv);
      L.DomEvent.disableScrollPropagation(searchDiv);

      const searchInput = searchDiv.querySelector('.ts-map-search__input');
      const searchResults = searchDiv.querySelector('.ts-map-search__results');
      let searchTimeout = null;

      searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const q = this.value.trim();
        if (q.length < 3) { searchResults.innerHTML = ''; searchResults.style.display = 'none'; return; }
        searchTimeout = setTimeout(() => geocodeSearch(q, searchResults, map, marker, options, (m) => { marker = m; }, () => { searchSelected = true; }), 400);
      });

      searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') e.preventDefault();
      });
    }

    // Click on map to place marker
    map.on('click', function(e) {
  if (searchSelected) {
    searchSelected = false;
    return;
  }
      const { lat: clickLat, lng: clickLng } = e.latlng;

      if (marker) {
        marker._skipReverseGeocode = true; 
        marker.setLatLng(e.latlng);
      } else {
        marker = L.marker(e.latlng, { icon: eventIcon, draggable: true }).addTo(map);
        bindMarkerDrag(marker, options);
      }

      updateInputs(clickLat, clickLng, options);
      reverseGeocode(clickLat, clickLng, options);

      // Hide instruction after first click
      instructionDiv.style.display = 'none';
    });

    return {
      map,
      get marker() { return marker; },
      setPosition(lat, lng) {
        const latlng = L.latLng(lat, lng);
        if (marker) {
          marker.setLatLng(latlng);
        } else {
          marker = L.marker(latlng, { icon: eventIcon, draggable: true }).addTo(map);
          bindMarkerDrag(marker, options);
        }
        map.setView(latlng, DETAIL_ZOOM);
        updateInputs(lat, lng, options);
      },
    };
  }

  function bindMarkerDrag(marker, options) {
  marker.on('dragend', function(e) {
    if (e.target._skipReverseGeocode) {
      e.target._skipReverseGeocode = false;
      return;
    }
    const { lat, lng } = e.target.getLatLng();
    updateInputs(lat, lng, options);
    reverseGeocode(lat, lng, options);
  });
}

  function updateInputs(lat, lng, options) {
    const latInput = options.latInput ? document.getElementById(options.latInput) : null;
    const lngInput = options.lngInput ? document.getElementById(options.lngInput) : null;
    if (latInput) latInput.value = lat.toFixed(7);
    if (lngInput) lngInput.value = lng.toFixed(7);
    if (options.onSelect) options.onSelect(lat, lng);
  }

  // Reverse geocode using Nominatim (free, no API key)
  async function reverseGeocode(lat, lng, options) {
    const addressInput = options.addressInput ? document.getElementById(options.addressInput) : null;
    if (!addressInput) return;

    try {
      const res = await fetch(`${TS_MAP_API_BASE}/geocode.php?action=reverse&lat=${encodeURIComponent(lat)}&lon=${encodeURIComponent(lng)}`);
      const json = await res.json();
      const data = json.success ? (json.data.result || null) : null;
      if (data && (data.short_name || data.display_name)) {
        addressInput.value = data.short_name || data.display_name;
      }
    } catch (e) {
      // Silently fail — user can type manually
    }
  }

  // Forward geocode search
  async function geocodeSearch(query, resultsDiv, map, marker, options, setMarker, onResultSelect) {
    try {
      const res = await fetch(`${TS_MAP_API_BASE}/geocode.php?action=search&q=${encodeURIComponent(query)}`);
      const json = await res.json();
      const results = json.success ? (json.data.results || []) : [];

      if (results.length === 0) {
        resultsDiv.innerHTML = '<div class="ts-map-search__item ts-map-search__empty">Asnjë rezultat</div>';
        resultsDiv.style.display = 'block';
        return;
      }

      resultsDiv.innerHTML = results.map(r => {
        const name = r.short_name || r.display_name || '';
        return `<div class="ts-map-search__item" data-lat="${r.lat}" data-lng="${r.lon}" data-name="${name}">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/></svg>
          <span>${name}</span>
        </div>`;
      }).join('');
      resultsDiv.style.display = 'block';

      // Click handler for results
      resultsDiv.querySelectorAll('.ts-map-search__item[data-lat]').forEach(item => {
        item.addEventListener('click', function() {
          if (onResultSelect) onResultSelect(); 
          searchSelected = true;
          const lat = parseFloat(this.dataset.lat);
          const lng = parseFloat(this.dataset.lng);
          const name = this.dataset.name;

          const latlng = L.latLng(lat, lng);
          map.setView(latlng, DETAIL_ZOOM);

          if (marker) {
            marker.setLatLng(latlng);
          } else {
            marker = L.marker(latlng, { icon: eventIcon, draggable: true }).addTo(map);
            bindMarkerDrag(marker, options);
            setMarker(marker);
          }

          updateInputs(lat, lng, options);
          const addressInput = options.addressInput ? document.getElementById(options.addressInput) : null;
          if (addressInput) addressInput.value = name;

          resultsDiv.innerHTML = '';
          resultsDiv.style.display = 'none';

          // Update search input
          const searchInput = resultsDiv.parentElement.querySelector('.ts-map-search__input');
          if (searchInput) searchInput.value = name;
        });
      });
    } catch (e) {
      resultsDiv.innerHTML = '';
      resultsDiv.style.display = 'none';
    }
  }


  // ══════════════════════════════════════════════════
  //  DISPLAY — Read-only map for detail views
  // ══════════════════════════════════════════════════

  /**
   * Shows a read-only map with a single marker.
   * @param {string} containerId
   * @param {object} options
   *   - lat, lng: coordinates
   *   - label: popup text (e.g. event title)
   *   - type: 'event' | 'request' | 'offer' (controls icon color)
   */
  function display(containerId, options = {}) {
    const container = document.getElementById(containerId);
    if (!container || !options.lat || !options.lng) return null;

    const center = [parseFloat(options.lat), parseFloat(options.lng)];
    const icon = options.type === 'request' ? requestIcon :
                 options.type === 'offer' ? offerIcon : eventIcon;

    const map = L.map(containerId, {
      center: center,
      zoom: DETAIL_ZOOM,
      scrollWheelZoom: false,
      dragging: true,
      zoomControl: true,
    });
    addTiles(map);

    const marker = L.marker(center, { icon: icon }).addTo(map);
    if (options.label) {
      marker.bindPopup(`<strong>${options.label}</strong>`).openPopup();
    }

    // Invalidate size after render (needed if map is in a hidden tab)
    setTimeout(() => map.invalidateSize(), 100);

    return { map, marker };
  }


  // ══════════════════════════════════════════════════
  //  OVERVIEW — Multi-marker map for browse page
  // ══════════════════════════════════════════════════

  /**
   * Shows multiple markers on a map.
   * @param {string} containerId
   * @param {Array} markers - array of { lat, lng, title, type, url }
   */
  function overview(containerId, markers = []) {
    const container = document.getElementById(containerId);
    if (!container) return null;

    const validMarkers = markers.filter(m => m.lat && m.lng);
    const center = validMarkers.length > 0
      ? [validMarkers[0].lat, validMarkers[0].lng]
      : TIRANA;

    const map = L.map(containerId, {
      center: center,
      zoom: DEFAULT_ZOOM,
      scrollWheelZoom: true,
    });
    addTiles(map);

    const group = L.featureGroup();

    validMarkers.forEach(m => {
      const icon = m.type === 'request' ? requestIcon :
                   m.type === 'offer' ? offerIcon : eventIcon;

      const marker = L.marker([parseFloat(m.lat), parseFloat(m.lng)], { icon: icon });
      let popupHtml = `<strong>${escapeHtmlMap(m.title)}</strong>`;
      if (m.url) {
        popupHtml += `<br><a href="${m.url}" style="color:#00715D;font-weight:500;">Shiko detaje &rarr;</a>`;
      }
      if (m.address) {
        popupHtml = `<small style="color:#666;">${escapeHtmlMap(m.address)}</small><br>` + popupHtml;
      }
      marker.bindPopup(popupHtml);
      group.addLayer(marker);
    });

    group.addTo(map);

    // Fit bounds to show all markers
    if (validMarkers.length > 1) {
      map.fitBounds(group.getBounds().pad(0.1));
    } else if (validMarkers.length === 1) {
      map.setView([validMarkers[0].lat, validMarkers[0].lng], DETAIL_ZOOM);
    }

    setTimeout(() => map.invalidateSize(), 100);

    return { map, group };
  }

  function escapeHtmlMap(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  // Public API
  return { picker, display, overview, TIRANA, eventIcon, requestIcon, offerIcon };
})();
