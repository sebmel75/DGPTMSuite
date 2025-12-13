/**
 * DGPTM Herzzentren Map Handler
 * Version: 4.0.0
 * 
 * Handles both Multi-Map and Single-Map functionality
 */

(function($) {
    'use strict';

    // Globaler Namespace für Maps
    window.dgptmMaps = window.dgptmMaps || {
        instances: {},
        config: window.dgptmMapConfig || {}
    };

    /**
     * Multi-Map Initialisierung
     */
    function initMultiMap($container) {
        const $map = $container.find('.dgptm-map-canvas');
        
        if (!$map.length || !window.L) {
            console.error('DGPTM Maps: Leaflet nicht geladen oder Container nicht gefunden');
            return;
        }

        const mapId = $map.attr('id');
        const markers = $map.data('markers');
        const zoom = parseInt($map.data('zoom')) || 6;
        const showPopup = $map.data('show-popup') === 'true';
        const iconUrl = $map.data('icon-url');

        // Prüfen ob bereits initialisiert
        if (window.dgptmMaps.instances[mapId]) {
            console.warn('DGPTM Maps: Karte bereits initialisiert - ' + mapId);
            return;
        }

        // Validierung
        if (!Array.isArray(markers) || markers.length === 0) {
            console.warn('DGPTM Maps: Keine Marker-Daten vorhanden');
            $map.html('<div style="padding: 40px; text-align: center; color: #666;">Keine Herzzentren verfügbar</div>');
            return;
        }

        try {
            // Karte initialisieren
            const map = L.map(mapId, {
                scrollWheelZoom: false,
                zoomControl: true,
                attributionControl: true
            }).setView([51.165691, 10.451526], zoom);

            // OpenStreetMap Tiles
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
            }).addTo(map);

            // Custom Icon
            const customIcon = L.icon({
                iconUrl: iconUrl,
                iconSize: [42, 43],
                iconAnchor: [21, 41],
                popupAnchor: [-2, -41]
            });

            // Marker hinzufügen
            const markerInstances = [];
            const bounds = [];

            markers.forEach(function(markerData) {
                if (!markerData.lat || !markerData.lng) return;

                const lat = parseFloat(markerData.lat);
                const lng = parseFloat(markerData.lng);

                if (isNaN(lat) || isNaN(lng)) return;

                bounds.push([lat, lng]);

                // Popup HTML erstellen (mit XSS-Schutz durch escapeHtml)
                const popupContent = createPopupContent(
                    markerData.title,
                    markerData.address,
                    markerData.url
                );

                const marker = L.marker([lat, lng], { icon: customIcon })
                    .addTo(map)
                    .bindPopup(popupContent, {
                        maxWidth: 300,
                        className: 'dgptm-custom-popup'
                    });

                markerInstances.push(marker);
            });

            // Map auf alle Marker anpassen
            if (bounds.length > 0) {
                if (bounds.length === 1) {
                    map.setView(bounds[0], 13);
                } else {
                    map.fitBounds(bounds, {
                        padding: [50, 50],
                        maxZoom: 12
                    });
                }
            }

            // Ersten Popup optional öffnen
            if (showPopup && markerInstances.length > 0) {
                markerInstances[0].openPopup();
            }

            // Scroll-Zoom bei Klick aktivieren
            map.on('click', function() {
                if (map.scrollWheelZoom.enabled()) {
                    map.scrollWheelZoom.disable();
                } else {
                    map.scrollWheelZoom.enable();
                }
            });

            // Map-Instanz speichern
            window.dgptmMaps.instances[mapId] = {
                map: map,
                markers: markerInstances,
                type: 'multi'
            };

        } catch (error) {
            console.error('DGPTM Maps: Fehler beim Initialisieren der Multi-Map', error);
            $map.html('<div style="padding: 40px; text-align: center; color: #d63638;">Fehler beim Laden der Karte</div>');
        }
    }

    /**
     * Single-Map Initialisierung
     */
    function initSingleMap($container) {
        const $map = $container.find('.dgptm-map-canvas-single');
        
        if (!$map.length || !window.L) {
            console.error('DGPTM Maps: Leaflet nicht geladen oder Container nicht gefunden');
            return;
        }

        const mapId = $map.attr('id');
        const lat = parseFloat($map.data('lat'));
        const lng = parseFloat($map.data('lng'));
        const zoom = parseInt($map.data('zoom')) || 13;
        const disableScroll = $map.data('disable-scroll') === 'true';
        const iconUrl = $map.data('icon-url');
        const markerData = $map.data('marker');

        // Prüfen ob bereits initialisiert
        if (window.dgptmMaps.instances[mapId]) {
            console.warn('DGPTM Maps: Karte bereits initialisiert - ' + mapId);
            return;
        }

        // Validierung
        if (isNaN(lat) || isNaN(lng)) {
            console.error('DGPTM Maps: Ungültige Koordinaten');
            $map.html('<div style="padding: 40px; text-align: center; color: #d63638;">Ungültige Koordinaten</div>');
            return;
        }

        try {
            // Karte initialisieren
            const map = L.map(mapId, {
                scrollWheelZoom: !disableScroll,
                zoomControl: true,
                attributionControl: true
            }).setView([lat, lng], zoom);

            // OpenStreetMap Tiles
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
            }).addTo(map);

            let marker = null;

            // Marker hinzufügen wenn aktiviert
            if (markerData) {
                const customIcon = L.icon({
                    iconUrl: iconUrl,
                    iconSize: [42, 43],
                    iconAnchor: [21, 41],
                    popupAnchor: [-2, -41]
                });

                marker = L.marker([lat, lng], { icon: customIcon }).addTo(map);

                // Popup hinzufügen wenn Titel oder Beschreibung vorhanden
                if (markerData.title || markerData.description) {
                    const popupContent = createSinglePopupContent(
                        markerData.title,
                        markerData.description
                    );
                    marker.bindPopup(popupContent, {
                        maxWidth: 300,
                        className: 'dgptm-custom-popup'
                    });
                }
            }

            // Scroll-Zoom bei Klick aktivieren (wenn initial deaktiviert)
            if (disableScroll) {
                map.on('click', function() {
                    if (map.scrollWheelZoom.enabled()) {
                        map.scrollWheelZoom.disable();
                    } else {
                        map.scrollWheelZoom.enable();
                    }
                });
            }

            // Map-Instanz speichern
            window.dgptmMaps.instances[mapId] = {
                map: map,
                marker: marker,
                type: 'single'
            };

        } catch (error) {
            console.error('DGPTM Maps: Fehler beim Initialisieren der Single-Map', error);
            $map.html('<div style="padding: 40px; text-align: center; color: #d63638;">Fehler beim Laden der Karte</div>');
        }
    }

    /**
     * Popup-Content für Multi-Map erstellen
     */
    function createPopupContent(title, address, url) {
        const safeTitle = escapeHtml(title || '');
        // HTML in Plain Text konvertieren, Formatierung beibehalten
        const plainAddress = address ? htmlToPlainText(address) : '';
        const safeUrl = url || '#';

        let html = '<div class="dgptm-map-popup">';

        if (safeTitle) {
            html += '<div class="dgptm-map-popup__title">' + safeTitle + '</div>';
        }

        if (plainAddress) {
            // Zeilenumbrüche in <br> umwandeln für HTML-Darstellung
            const formattedAddress = escapeHtml(plainAddress).replace(/\n/g, '<br>');
            html += '<div class="dgptm-map-popup__address">' + formattedAddress + '</div>';
        }

        if (url && url !== '#') {
            html += '<div class="dgptm-map-popup__link">';
            html += '<a href="' + escapeHtml(safeUrl) + '">Mehr erfahren</a>';
            html += '</div>';
        }

        html += '</div>';

        return html;
    }

    /**
     * Popup-Content für Single-Map erstellen
     */
    function createSinglePopupContent(title, description) {
        const safeTitle = title ? escapeHtml(title) : '';
        // HTML in Plain Text konvertieren, Formatierung beibehalten
        const plainDescription = description ? htmlToPlainText(description) : '';

        let html = '<div class="dgptm-map-popup">';

        if (safeTitle) {
            html += '<div class="dgptm-map-popup__title">' + safeTitle + '</div>';
        }

        if (plainDescription) {
            // Zeilenumbrüche in <br> umwandeln für HTML-Darstellung
            const formattedDescription = escapeHtml(plainDescription).replace(/\n/g, '<br>');
            html += '<div class="dgptm-map-popup__address">' + formattedDescription + '</div>';
        }

        html += '</div>';

        return html;
    }

    /**
     * XSS-Schutz: HTML escapen
     */
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    /**
     * HTML zu Plain Text konvertieren (für Popup-Vorschau)
     * Behält Formatierung bei durch Umwandlung von HTML-Tags in entsprechende Textdarstellung
     */
    function htmlToPlainText(html) {
        if (!html) return '';

        let text = String(html);

        // Zeilenumbrüche: <br>, <br/>, <br /> → Newline
        text = text.replace(/<br\s*\/?>/gi, '\n');

        // Absätze: <p>...</p> → Inhalt mit Newlines
        text = text.replace(/<\/p>/gi, '\n\n');
        text = text.replace(/<p[^>]*>/gi, '');

        // Listen: <li> → Bullet Point
        text = text.replace(/<li[^>]*>/gi, '• ');
        text = text.replace(/<\/li>/gi, '\n');
        text = text.replace(/<\/?[uo]l[^>]*>/gi, '');

        // Überschriften: <h1-h6> → Großbuchstaben
        text = text.replace(/<h[1-6][^>]*>(.*?)<\/h[1-6]>/gi, function(match, content) {
            return content.toUpperCase() + '\n';
        });

        // Formatierung entfernen: <strong>, <b>, <em>, <i>, <u>
        text = text.replace(/<\/?(?:strong|b|em|i|u|span|div)[^>]*>/gi, '');

        // Links: <a href="...">Text</a> → Text
        text = text.replace(/<a[^>]*>(.*?)<\/a>/gi, '$1');

        // Alle übrigen HTML-Tags entfernen
        text = text.replace(/<[^>]+>/g, '');

        // HTML-Entities dekodieren
        const textarea = document.createElement('textarea');
        textarea.innerHTML = text;
        text = textarea.value;

        // Mehrfache Leerzeilen auf maximal 2 reduzieren
        text = text.replace(/\n{3,}/g, '\n\n');

        // Führende/nachfolgende Leerzeichen entfernen
        text = text.trim();

        return text;
    }

    /**
     * Alle Maps auf der Seite initialisieren
     */
    function initAllMaps() {
        // Multi-Maps
        $('.dgptm-herzzentren-map-wrapper').each(function() {
            initMultiMap($(this));
        });

        // Single-Maps
        $('.dgptm-herzzentrum-single-map-wrapper').each(function() {
            initSingleMap($(this));
        });
    }

    /**
     * Maps neu initialisieren (z.B. nach AJAX-Load)
     */
    window.dgptmMaps.reinit = function() {
        initAllMaps();
    };

    /**
     * Spezifische Map zerstören
     */
    window.dgptmMaps.destroy = function(mapId) {
        if (window.dgptmMaps.instances[mapId]) {
            const instance = window.dgptmMaps.instances[mapId];
            if (instance.map) {
                instance.map.remove();
            }
            delete window.dgptmMaps.instances[mapId];
        }
    };

    // Initialisierung bei DOM-Ready
    $(document).ready(function() {
        // Warten bis Leaflet geladen ist
        if (typeof L !== 'undefined') {
            initAllMaps();
        } else {
            console.error('DGPTM Maps: Leaflet (L) ist nicht verfügbar');
        }
    });

    // Elementor Frontend Support
    $(window).on('elementor/frontend/init', function() {
        // Bei Elementor Preview neu initialisieren
        if (window.elementorFrontend && window.elementorFrontend.isEditMode()) {
            setTimeout(function() {
                initAllMaps();
            }, 500);
        }
    });

})(jQuery);
