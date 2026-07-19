/* VanAssist lightweight progressive enhancement. No framework. */
(function () {
    'use strict';

    // Mobile navigation toggle (public site).
    var toggle = document.querySelector('.nav-toggle');
    var nav = document.getElementById('main-nav');
    if (toggle && nav) {
        toggle.addEventListener('click', function () {
            var open = nav.classList.toggle('open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    // Mobile navigation toggle (admin sidebar).
    var adminToggle = document.querySelector('.admin-nav-toggle');
    var sidebar = document.querySelector('.admin-sidebar');
    if (adminToggle && sidebar) {
        adminToggle.addEventListener('click', function () {
            var open = sidebar.classList.toggle('open');
            adminToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    // "Use my location" — GPS → nearest town → fill the form (search or request).
    // Buttons stay hidden until geolocation is available (progressive enhancement).
    var setFormField = function (form, name, value) {
        var field = form.querySelector('[name="' + name + '"]');
        if (!field) {
            field = document.createElement('input');
            field.type = 'hidden';
            field.name = name;
            form.appendChild(field);
        }
        field.value = value;
    };

    var setLocationStatus = function (form, message, visible) {
        var el = form.querySelector('.location-status');
        if (!el) { return; }
        if (!visible || !message) {
            el.hidden = true;
            el.textContent = '';
            return;
        }
        el.textContent = message;
        el.hidden = false;
    };

    var nearestEndpoint = function (form) {
        return form.getAttribute('data-nearest-url') || '/locations/nearest';
    };

    var syncDistanceFilter = function (form) {
        var select = form.querySelector('select[name="max_distance"]');
        if (!select) { return; }
        var lat = form.querySelector('input[name="lat"]');
        var lng = form.querySelector('input[name="lng"]');
        var loc = form.querySelector('input[name="location"]');
        var town = form.querySelector('select[name="town"], input[name="town"], #town_id');
        var hasCoords = lat && lat.value !== '' && lng && lng.value !== '';
        var hasLocation = (loc && loc.value.trim() !== '') || (town && town.value !== '' && town.value !== '0');
        var enable = hasCoords || hasLocation;
        select.disabled = !enable;
        var hint = select.closest('.form-group');
        if (!hint) { return; }
        var muted = hint.querySelector('.muted');
        if (!muted) { return; }
        if (enable) {
            muted.textContent = 'Default shows providers in and serving this suburb or town. Widen with km options.';
            if (select.value === '') {
                var townOpt = select.querySelector('option[value="town"]');
                if (townOpt) { select.value = 'town'; }
            }
        } else {
            muted.textContent = 'Enter a town, suburb or postcode to filter by distance.';
        }
    };

    var applyNearestTown = function (form, btn, town, lat, lng) {
        var autoSubmit = btn.getAttribute('data-auto-submit') !== 'false';
        var selectSel = btn.getAttribute('data-select-target');
        var postcodeSel = btn.getAttribute('data-postcode-target');
        var buttons = form.querySelectorAll('[data-use-location]');
        var originals = [];
        buttons.forEach(function (b, i) {
            originals[i] = b.getAttribute('data-label-html') || b.innerHTML;
            if (!b.getAttribute('data-label-html')) {
                b.setAttribute('data-label-html', b.innerHTML);
            }
        });

        setFormField(form, 'lat', lat);
        setFormField(form, 'lng', lng);
        syncDistanceFilter(form);

        if (selectSel) {
            var sel = form.querySelector(selectSel);
            if (sel) {
                var id = String(town.id);
                if (sel.tagName === 'SELECT') {
                    if (!sel.querySelector('option[value="' + id + '"]')) {
                        var opt = document.createElement('option');
                        opt.value = id;
                        opt.textContent = town.label || town.name;
                        sel.appendChild(opt);
                    }
                    sel.value = id;
                } else {
                    sel.value = id;
                }
            }
        }

        var townSearch = form.querySelector('#town_search');
        if (townSearch && town.label) {
            townSearch.value = town.label;
        }

        var loc = form.querySelector('input[name="location"]');
        if (loc && town.label) {
            loc.value = town.label;
        }

        if (postcodeSel && town.postcode) {
            var pc = form.querySelector(postcodeSel);
            if (pc) { pc.value = town.postcode; }
        }

        var dist = town.distance_km != null ? ' (~' + town.distance_km + ' km)' : '';
        setLocationStatus(form, 'Location set: ' + (town.label || town.name) + dist, true);

        if (autoSubmit) {
            form.submit();
            return;
        }

        buttons.forEach(function (b, i) {
            b.disabled = false;
            b.removeAttribute('aria-busy');
            b.innerHTML = originals[i];
        });
    };

    if ('geolocation' in navigator) {
        document.querySelectorAll('[data-use-location]').forEach(function (btn) {
            btn.hidden = false;
            btn.addEventListener('click', function () {
                var form = btn.closest('form');
                if (!form) { return; }

                var original = btn.innerHTML;
                var buttons = form.querySelectorAll('[data-use-location]');
                buttons.forEach(function (b) {
                    b.disabled = true;
                    if (b !== btn) { b.setAttribute('aria-busy', 'true'); }
                });
                btn.setAttribute('aria-busy', 'true');
                btn.innerHTML = '<span>Locating\u2026</span>';
                setLocationStatus(form, 'Getting your location\u2026', true);

                navigator.geolocation.getCurrentPosition(
                    function (pos) {
                        var lat = pos.coords.latitude.toFixed(6);
                        var lng = pos.coords.longitude.toFixed(6);
                        var url = nearestEndpoint(form) + '?lat=' + encodeURIComponent(lat)
                            + '&lng=' + encodeURIComponent(lng);

                        fetch(url, { headers: { 'Accept': 'application/json' } })
                            .then(function (r) { return r.json(); })
                            .then(function (data) {
                                if (!data || !data.town) {
                                    throw new Error((data && data.error) || 'No town found near you.');
                                }
                                applyNearestTown(form, btn, data.town, lat, lng);
                            })
                            .catch(function (e) {
                                buttons.forEach(function (b) {
                                    b.disabled = false;
                                    b.removeAttribute('aria-busy');
                                    b.innerHTML = original;
                                });
                                setLocationStatus(form, '', false);
                                window.alert(e.message || 'We could not find a town near your location. Please type a town or postcode.');
                            });
                    },
                    function (err) {
                        buttons.forEach(function (b) {
                            b.disabled = false;
                            b.removeAttribute('aria-busy');
                            b.innerHTML = original;
                        });
                        setLocationStatus(form, '', false);
                        var msg = err && err.code === 1
                            ? 'Location access was blocked. Allow location in your browser settings, or type a town or postcode.'
                            : 'We could not get your location. Please type a town or postcode.';
                        window.alert(msg);
                    },
                    { enableHighAccuracy: true, timeout: 15000, maximumAge: 60000 }
                );
            });
        });

        // On phones/tablets, hint once that GPS search is available when the form is empty.
        if (window.matchMedia('(max-width: 719px)').matches && sessionStorage.getItem('va-loc-hint') !== '1') {
            document.querySelectorAll('form[data-nearest-url]').forEach(function (form) {
                var loc = form.querySelector('input[name="location"]');
                var lat = form.querySelector('input[name="lat"]');
                var town = form.querySelector('#town_id');
                var empty = (!loc || loc.value.trim() === '') && (!lat || lat.value === '') && (!town || !town.value);
                if (empty && form.querySelector('[data-use-location]')) {
                    setLocationStatus(form, 'Tip: tap “Use my current location” to find services near you.', true);
                    sessionStorage.setItem('va-loc-hint', '1');
                }
            });
        }
    }

    document.querySelectorAll('form[data-nearest-url]').forEach(function (form) {
        syncDistanceFilter(form);
        var loc = form.querySelector('input[name="location"]');
        if (loc) {
            loc.addEventListener('input', function () { syncDistanceFilter(form); });
        }
        var town = form.querySelector('select[name="town"]');
        if (town) {
            town.addEventListener('change', function () { syncDistanceFilter(form); });
        }
    });

    // Town type-ahead: query the JSON endpoint and, when a town is chosen, fill
    // the linked region (and hidden region id) fields. Progressive enhancement —
    // without JS the town field is still a plain text input.
    document.querySelectorAll('[data-town-search]').forEach(function (input) {
        var endpoint = input.getAttribute('data-town-search');
        var form = input.closest('form');
        var box = form ? form.querySelector('#town-suggest') : null;
        var regionField = form ? form.querySelector('#region') : null;
        var regionId = form ? form.querySelector('#region_id') : null;
        if (!box) { return; }
        var timer = null;
        var items = [];
        var active = -1;

        var hide = function () { box.hidden = true; box.innerHTML = ''; active = -1; };

        var choose = function (t) {
            var label = t.name + (t.state_abbr ? ', ' + t.state_abbr : '');
            if (input.name === 'location' || input.id === 'location') {
                input.value = label;
                hide();
                return;
            }
            input.value = label;
            var townId = form ? form.querySelector('#town_id, input[name="town"]') : null;
            if (townId) { townId.value = t.id; }
            if (regionField) { regionField.value = t.region_name || ''; }
            if (regionId) { regionId.value = t.region_id || ''; }
            hide();
        };

        var render = function (towns) {
            items = towns;
            if (!towns.length) { hide(); return; }
            box.innerHTML = '';
            towns.forEach(function (t, i) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.setAttribute('role', 'option');
                btn.dataset.index = i;
                var sub = [t.region_name, t.state_abbr].filter(Boolean).join(', ');
                btn.innerHTML = '<strong>' + t.name + '</strong>' + (sub ? ' <span class="muted">' + sub + '</span>' : '');
                btn.addEventListener('click', function () { choose(t); input.focus(); });
                box.appendChild(btn);
            });
            box.hidden = false;
            active = -1;
        };

        var search = function (q) {
            fetch(endpoint + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (data) { render((data && data.towns) || []); })
                .catch(function () { hide(); });
        };

        input.addEventListener('input', function () {
            // Typing invalidates any previously resolved region.
            if (regionField) { regionField.value = ''; }
            if (regionId) { regionId.value = ''; }
            var q = input.value.trim();
            window.clearTimeout(timer);
            if (q.length < 2) { hide(); return; }
            timer = window.setTimeout(function () { search(q); }, 200);
        });

        input.addEventListener('keydown', function (e) {
            if (box.hidden) { return; }
            var buttons = box.querySelectorAll('button');
            if (e.key === 'ArrowDown') { e.preventDefault(); active = Math.min(active + 1, buttons.length - 1); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); active = Math.max(active - 1, 0); }
            else if (e.key === 'Enter' && active >= 0) { e.preventDefault(); choose(items[active]); return; }
            else if (e.key === 'Escape') { hide(); return; }
            else { return; }
            buttons.forEach(function (b, i) { b.classList.toggle('active', i === active); });
        });

        document.addEventListener('click', function (e) {
            if (e.target !== input && !box.contains(e.target)) { hide(); }
        });
    });

    // Auto-dismiss flash alerts after a while (kept accessible: not removed instantly).
    document.querySelectorAll('[data-auto-dismiss]').forEach(function (el) {
        window.setTimeout(function () { el.style.display = 'none'; }, 8000);
    });

    // Native lazy-loading hint for images that opt in.
    document.querySelectorAll('img[data-lazy]').forEach(function (img) {
        img.loading = 'lazy';
    });

    // Homepage "Providers near you" — GPS or saved town, claimed listings only.
    var nearbySection = document.querySelector('[data-nearby-providers]');
    if (nearbySection) {
        var endpoint = nearbySection.getAttribute('data-endpoint') || '/locations/nearby-providers';
        var nearestUrl = nearbySection.getAttribute('data-nearest-url') || '/locations/nearest';
        var grid = nearbySection.querySelector('[data-nearby-grid]');
        var subtitle = nearbySection.querySelector('[data-nearby-subtitle]');
        var statusEl = nearbySection.querySelector('[data-nearby-status]');
        var findLink = nearbySection.querySelector('[data-nearby-find]');
        var locateBtn = nearbySection.querySelector('[data-nearby-locate]');
        var storageKey = 'va-nearby-town-id';

        var setStatus = function (msg, show) {
            if (!statusEl) { return; }
            if (!show || !msg) {
                statusEl.hidden = true;
                statusEl.textContent = '';
                return;
            }
            statusEl.hidden = false;
            statusEl.textContent = msg;
        };

        var escapeHtml = function (s) {
            return String(s)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        };

        var renderCard = function (p) {
            var isFeatured = p.slot === 'featured' || p.is_featured;
            var badges = '';
            if (isFeatured) { badges += '<span class="badge badge-sponsored">Featured</span> '; }
            if (p.is_verified) { badges += '<span class="badge badge-verified">Verified</span> '; }
            if (p.service_model) {
                badges += '<span class="badge badge-neutral">' + escapeHtml(p.service_model.charAt(0).toUpperCase() + p.service_model.slice(1)) + '</span>';
            }
            var loc = '';
            if (p.town_name) {
                loc = '<p class="muted nearby-card-loc">' + escapeHtml(p.town_name);
                if (p.state_abbr) { loc += ', ' + escapeHtml(p.state_abbr); }
                loc += '</p>';
            }
            var cls = 'nearby-card card' + (isFeatured ? ' nearby-card-featured' : '');
            var href = p.profile_url || ('/providers/' + encodeURIComponent(p.slug));
            return '<a class="' + cls + '" href="' + escapeHtml(href) + '">'
                + '<h3 class="nearby-card-title">' + escapeHtml(p.business_name) + '</h3>'
                + '<div class="nearby-card-badges">' + badges + '</div>'
                + loc
                + '</a>';
        };

        var render = function (data) {
            if (!grid) { return; }
            var town = data && data.town;
            var providers = (data && data.providers) || [];

            if (subtitle && town && town.label) {
                subtitle.innerHTML = 'Serving travellers in <strong>' + escapeHtml(town.label) + '</strong> — claimed listings only.';
            }

            if (findLink && data && data.find_url) {
                findLink.setAttribute('href', data.find_url);
            }

            if (town && town.id) {
                try { sessionStorage.setItem(storageKey, String(town.id)); } catch (e) { /* ignore */ }
            }

            if (!providers.length) {
                grid.innerHTML = '<div class="nearby-empty card" data-nearby-empty>'
                    + '<p style="margin:0"><strong>No claimed providers in this area yet.</strong> '
                    + '<a href="/find">Search by town</a> or <a href="/for-providers">list your business</a> to be first.</p></div>';
                return;
            }

            grid.innerHTML = providers.map(renderCard).join('');
        };

        var loadNearby = function (query) {
            setStatus('Loading local providers\u2026', true);
            return fetch(endpoint + '?' + query, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    setStatus('', false);
                    if (!data || !data.town) {
                        throw new Error((data && data.error) || 'No town found.');
                    }
                    render(data);
                })
                .catch(function (e) {
                    setStatus('', false);
                    window.alert(e.message || 'Could not load providers for your area.');
                });
        };

        var savedTown = null;
        try { savedTown = sessionStorage.getItem(storageKey); } catch (e) { savedTown = null; }

        var initialTownId = nearbySection.getAttribute('data-initial-town-id');
        if (savedTown && savedTown !== initialTownId) {
            loadNearby('town_id=' + encodeURIComponent(savedTown));
        }

        if (locateBtn && 'geolocation' in navigator) {
            locateBtn.hidden = false;
            locateBtn.addEventListener('click', function () {
                var original = locateBtn.innerHTML;
                locateBtn.disabled = true;
                locateBtn.setAttribute('aria-busy', 'true');
                locateBtn.innerHTML = 'Locating\u2026';
                setStatus('Getting your location\u2026', true);

                navigator.geolocation.getCurrentPosition(
                    function (pos) {
                        var lat = pos.coords.latitude.toFixed(6);
                        var lng = pos.coords.longitude.toFixed(6);
                        loadNearby('lat=' + encodeURIComponent(lat) + '&lng=' + encodeURIComponent(lng))
                            .finally(function () {
                                locateBtn.disabled = false;
                                locateBtn.removeAttribute('aria-busy');
                                locateBtn.innerHTML = original;
                            });
                    },
                    function (err) {
                        locateBtn.disabled = false;
                        locateBtn.removeAttribute('aria-busy');
                        locateBtn.innerHTML = original;
                        setStatus('', false);
                        var msg = err && err.code === 1
                            ? 'Location access was blocked. Allow location in your browser settings.'
                            : 'We could not get your location. Try search instead.';
                        window.alert(msg);
                    },
                    { enableHighAccuracy: true, timeout: 15000, maximumAge: 120000 }
                );
            });
        }
    }
})();
