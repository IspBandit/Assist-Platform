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

    // VanAssist home-screen installation. Android receives the native prompt;
    // iOS receives the exact Safari steps because Apple exposes no prompt API.
    if (document.body.getAttribute('data-brand') === 'vanassist') {
        var installPrompt = null;
        var installButtons = document.querySelectorAll('[data-install-app]');
        var installDialog = document.querySelector('[data-install-dialog]');
        var standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
        var appleMobile = /iPhone|iPad|iPod/i.test(navigator.userAgent)
            || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
        var androidMobile = /Android/i.test(navigator.userAgent);
        var hideInstallButtons = function () { installButtons.forEach(function (button) { button.hidden = true; }); };
        var showInstallInstructions = function () {
            if (!installDialog) { return; }
            var ios = installDialog.querySelector('[data-install-ios]');
            var android = installDialog.querySelector('[data-install-android]');
            var desktop = installDialog.querySelector('[data-install-desktop]');
            if (ios) { ios.hidden = !appleMobile; }
            if (android) { android.hidden = !androidMobile; }
            if (desktop) { desktop.hidden = appleMobile || androidMobile; }
            if (typeof installDialog.showModal === 'function') { installDialog.showModal(); }
            else { installDialog.setAttribute('open', ''); }
        };
        if (standalone) { hideInstallButtons(); }
        window.addEventListener('beforeinstallprompt', function (event) { event.preventDefault(); installPrompt = event; });
        window.addEventListener('appinstalled', function () {
            installPrompt = null;
            hideInstallButtons();
            if (installDialog && installDialog.open) { installDialog.close(); }
        });
        installButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                if (installPrompt) {
                    installPrompt.prompt();
                    installPrompt.userChoice.finally(function () { installPrompt = null; });
                    return;
                }
                showInstallInstructions();
            });
        });
        installDialog?.querySelector('[data-install-close]')?.addEventListener('click', function () { installDialog.close(); });
        installDialog?.addEventListener('click', function (event) { if (event.target === installDialog) { installDialog.close(); } });
        if ('serviceWorker' in navigator && window.isSecureContext) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('/service-worker.js').catch(function () { /* Optional enhancement. */ });
            });
        }
    }

    // Mobile navigation toggle (admin sidebar).
    var adminToggle = document.querySelector('.admin-nav-toggle');
    var sidebar = document.querySelector('.admin-sidebar');
    var adminScrim = document.querySelector('.admin-nav-scrim');
    if (adminToggle && sidebar) {
        var closeAdminNav = function () {
            sidebar.classList.remove('open');
            document.body.classList.remove('admin-nav-open');
            document.querySelector('.admin-main')?.removeAttribute('inert');
            adminToggle.setAttribute('aria-expanded', 'false');
        };
        adminToggle.addEventListener('click', function () {
            var open = sidebar.classList.toggle('open');
            document.body.classList.toggle('admin-nav-open', open);
            var main = document.querySelector('.admin-main');
            if (open) {
                main?.setAttribute('inert', '');
                window.setTimeout(function () { sidebar.querySelector('nav a')?.focus(); }, 0);
            } else {
                main?.removeAttribute('inert');
            }
            adminToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        sidebar.querySelectorAll('nav a').forEach(function (link) {
            link.addEventListener('click', closeAdminNav);
        });
        adminScrim?.addEventListener('click', function () {
            closeAdminNav();
            adminToggle.focus();
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && sidebar.classList.contains('open')) {
                closeAdminNav();
                adminToggle.focus();
            }
            if (event.key === 'Tab' && sidebar.classList.contains('open')) {
                var focusable = Array.prototype.slice.call(sidebar.querySelectorAll('button:not([disabled]), a[href]'));
                if (!focusable.length) return;
                var first = focusable[0];
                var last = focusable[focusable.length - 1];
                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            }
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
        try {
            sessionStorage.setItem('va-current-location', JSON.stringify({
                lat: lat,
                lng: lng,
                label: town.label || town.name || '',
                savedAt: Date.now()
            }));
        } catch (e) {
            // Location still works when browser storage is unavailable.
        }

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
                                if (form.getAttribute('data-location-manual') === '1') {
                                    buttons.forEach(function (b) {
                                        b.disabled = false;
                                        b.removeAttribute('aria-busy');
                                        b.innerHTML = b.getAttribute('data-label-html') || original;
                                    });
                                    return;
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
                    setLocationStatus(form, '', false);
                    sessionStorage.setItem('va-loc-hint', '1');
                }
            });
        }
    }

    document.querySelectorAll('form[data-nearest-url]').forEach(function (form) {
        syncDistanceFilter(form);
        var loc = form.querySelector('input[name="location"]');
        if (loc) {
            loc.addEventListener('input', function () {
                // A manually entered town or postcode must replace a previous
                // GPS lookup. Otherwise the hidden coordinates win on the
                // server and the typed location appears to be ignored.
                setFormField(form, 'lat', '');
                setFormField(form, 'lng', '');
                form.setAttribute('data-location-manual', '1');
                setLocationStatus(form, '', false);
                syncDistanceFilter(form);
            });
        }
        var town = form.querySelector('select[name="town"]');
        if (town) {
            town.addEventListener('change', function () { syncDistanceFilter(form); });
        }
    });

    // VanAssist starts location-first on its main search form. Resolving the
    // nearest town fills the form but never submits it automatically, leaving
    // the traveller free to choose a service or type a different place.
    if ('geolocation' in navigator) {
        document.querySelectorAll('form[data-auto-location]').forEach(function (form) {
            var loc = form.querySelector('input[name="location"]');
            var lat = form.querySelector('input[name="lat"]');
            var empty = (!loc || loc.value.trim() === '') && (!lat || lat.value === '');
            var trigger = form.querySelector('[data-use-location]');
            if (!empty || !trigger) { return; }
            window.setTimeout(function () {
                if (form.getAttribute('data-location-manual') !== '1' && (!loc || loc.value.trim() === '')) {
                    trigger.click();
                }
            }, 250);
        });
    }

    // Nearby discovery links (Fuel, EV charging, services and stays) inherit
    // the traveller's location. A typed place always wins over GPS.
    var locationForLink = function () {
        var manual = document.querySelector('form[data-location-manual="1"] input[name="location"]');
        if (manual && manual.value.trim() !== '') {
            return { location: manual.value.trim() };
        }
        try {
            var cached = JSON.parse(sessionStorage.getItem('va-current-location') || 'null');
            if (cached && cached.lat && cached.lng && Date.now() - Number(cached.savedAt || 0) < 900000) {
                return { lat: cached.lat, lng: cached.lng };
            }
        } catch (e) {
            return null;
        }
        return null;
    };

    var navigateWithLocation = function (link, location) {
        var target = new URL(link.href, window.location.href);
        if (target.searchParams.has('location') || (target.searchParams.has('lat') && target.searchParams.has('lng'))) {
            window.location.assign(target.toString());
            return;
        }
        if (location.location) {
            target.searchParams.delete('lat');
            target.searchParams.delete('lng');
            target.searchParams.set('location', location.location);
        } else {
            target.searchParams.set('lat', location.lat);
            target.searchParams.set('lng', location.lng);
        }
        window.location.assign(target.toString());
    };

    document.querySelectorAll('a[data-location-link]').forEach(function (link) {
        link.addEventListener('click', function (event) {
            if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) { return; }
            var target = new URL(link.href, window.location.href);
            if (target.searchParams.has('location') || (target.searchParams.has('lat') && target.searchParams.has('lng'))) { return; }
            event.preventDefault();
            var known = locationForLink();
            if (known) {
                navigateWithLocation(link, known);
                return;
            }
            if (!('geolocation' in navigator)) {
                window.location.assign(link.href);
                return;
            }
            link.setAttribute('aria-busy', 'true');
            navigator.geolocation.getCurrentPosition(function (pos) {
                navigateWithLocation(link, {
                    lat: pos.coords.latitude.toFixed(6),
                    lng: pos.coords.longitude.toFixed(6)
                });
            }, function () {
                window.location.assign(link.href);
            }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 60000 });
        });
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
        var resolvedTown = form ? form.querySelector('#town_id, input[type="hidden"][name="town"]') : null;
        if (!box) { return; }
        var timer = null;
        var items = [];
        var active = -1;

        var positionBox = function () {
            var anchor = input.closest('.location-field');
            if (!anchor) { return; }
            box.style.left = (input.offsetLeft || 0) + 'px';
            box.style.top = ((input.offsetTop || 0) + input.offsetHeight + 5) + 'px';
            box.style.width = input.offsetWidth + 'px';
        };

        var hide = function () { box.hidden = true; box.innerHTML = ''; active = -1; };

        var choose = function (t) {
            var label = t.name + (t.state_abbr ? ' / ' + t.state_abbr : '');
            if (resolvedTown) { resolvedTown.value = t.id; }
            if (input.name === 'location' || input.id === 'location') {
                input.value = label;
                hide();
                return;
            }
            input.value = label;
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
                var sub = [t.postcode, t.region_name].filter(Boolean).join(' · ');
                var primary = t.name + (t.state_abbr ? ' / ' + t.state_abbr : '');
                var strong = document.createElement('strong');
                strong.textContent = primary;
                btn.appendChild(strong);
                if (sub) {
                    var secondary = document.createElement('span');
                    secondary.className = 'muted';
                    secondary.textContent = sub;
                    btn.appendChild(secondary);
                }
                btn.addEventListener('click', function () { choose(t); input.focus(); });
                box.appendChild(btn);
            });
            positionBox();
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
            // A typed correction supersedes every previously resolved
            // location value, including a GPS fix and linked region.
            var resolvedLat = form ? form.querySelector('input[name="lat"]') : null;
            var resolvedLng = form ? form.querySelector('input[name="lng"]') : null;
            if (regionField) { regionField.value = ''; }
            if (regionId) { regionId.value = ''; }
            if (resolvedTown) { resolvedTown.value = ''; }
            if (resolvedLat) { resolvedLat.value = ''; }
            if (resolvedLng) { resolvedLng.value = ''; }
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
        window.addEventListener('resize', function () { if (!box.hidden) { positionBox(); } });
    });

    // Auto-dismiss flash alerts after a while (kept accessible: not removed instantly).
    document.querySelectorAll('[data-auto-dismiss]').forEach(function (el) {
        window.setTimeout(function () { el.style.display = 'none'; }, 8000);
    });

    // Native lazy-loading hint for images that opt in.
    document.querySelectorAll('img[data-lazy]').forEach(function (img) {
        img.loading = 'lazy';
    });

    // Homepage "Providers near you" — GPS or saved town, with discovered listings labelled.
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
            if (p.is_unclaimed) { badges += '<span class="badge badge-neutral">Unclaimed</span> '; }
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
                subtitle.innerHTML = 'Showing relevant listings serving <strong>' + escapeHtml(town.label) + '</strong>.';
            }

            if (findLink && data && data.find_url) {
                findLink.setAttribute('href', data.find_url);
            }

            if (town && town.id) {
                try { sessionStorage.setItem(storageKey, String(town.id)); } catch (e) { /* ignore */ }
            }

            if (!providers.length) {
                grid.innerHTML = '<div class="nearby-empty card" data-nearby-empty>'
                    + '<p style="margin:0"><strong>No matching providers in this area yet.</strong> '
                    + '<a href="/providers">Browse the national directory</a> or <a href="/for-providers">list your business</a>.</p></div>';
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

    // Provider service-area form. Keep this behaviour in the trusted external
    // bundle so production's strict Content Security Policy remains effective.
    var areaType = document.getElementById('area_type');
    if (areaType) {
        var syncAreaFields = function () {
            var selected = areaType.value;
            document.querySelectorAll('[data-area-field]').forEach(function (field) {
                field.hidden = field.getAttribute('data-area-field') !== selected;
            });
        };
        areaType.addEventListener('change', syncAreaFields);
        syncAreaFields();
    }

    // Hand directions to the phone's natural mapping experience. Desktop links
    // retain their server-generated Google Maps fallback.
    document.querySelectorAll('[data-map-directions]').forEach(function (link) {
        link.addEventListener('click', function (event) {
            var destination = link.getAttribute('data-map-destination') || '';
            if (!destination) { return; }

            var isAppleMobile = /iPhone|iPad|iPod/i.test(navigator.userAgent)
                || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
            var isAndroid = /Android/i.test(navigator.userAgent);
            if (!isAppleMobile && !isAndroid) { return; }

            event.preventDefault();
            var target = isAppleMobile
                ? 'https://maps.apple.com/?daddr=' + encodeURIComponent(destination) + '&dirflg=d'
                : 'geo:0,0?q=' + encodeURIComponent(destination);
            window.location.href = target;
        });
    });

    // Search-result map: a deliberately small, dependency-free OpenStreetMap
    // tile viewer. The server-rendered provider list remains the canonical and
    // fully accessible experience when script or tiles are unavailable.
    var resultsMap = document.querySelector('[data-results-map]');
    var resultsMapData = document.querySelector('[data-results-map-data]');
    if (resultsMap && resultsMapData) {
        var mapCanvas = resultsMap.querySelector('[data-results-map-canvas]');
        var mapStatus = document.querySelector('[data-results-map-status]');
        var mapSummary = resultsMap.querySelector('[data-results-map-summary]');
        var summaryName = resultsMap.querySelector('[data-results-map-summary-name]');
        var summaryLocation = resultsMap.querySelector('[data-results-map-summary-location]');
        var summaryPosition = resultsMap.querySelector('[data-results-map-summary-position]');
        var summaryProfile = resultsMap.querySelector('[data-results-map-summary-profile]');
        var summaryDirections = resultsMap.querySelector('[data-results-map-summary-directions]');
        var summaryList = resultsMap.querySelector('[data-results-map-summary-list]');
        var summaryClose = resultsMap.querySelector('[data-results-map-summary-close]');
        var summaryToggle = resultsMap.querySelector('[data-results-map-summary-toggle]');
        var summaryDrag = resultsMap.querySelector('[data-results-map-summary-drag]');
        var zoomIn = resultsMap.querySelector('[data-results-map-zoom-in]');
        var zoomOut = resultsMap.querySelector('[data-results-map-zoom-out]');
        var fitResults = resultsMap.querySelector('[data-results-map-fit]');
        var resultsViewShell = resultsMap.closest('[data-results-view-shell]');
        var resultsList = document.querySelector('[data-results-list]');
        var mapPayload = null;
        try { mapPayload = JSON.parse(resultsMapData.textContent || '{}'); } catch (e) { mapPayload = null; }

        var providers = mapPayload && Array.isArray(mapPayload.providers) ? mapPayload.providers : [];
        var validCoordinate = function (lat, lng) {
            return Number.isFinite(Number(lat)) && Number.isFinite(Number(lng))
                && Number(lat) >= -90 && Number(lat) <= 90 && Number(lng) >= -180 && Number(lng) <= 180;
        };
        providers = providers.filter(function (provider) { return validCoordinate(provider.lat, provider.lng); }).slice(0, 80);

        if (mapCanvas && providers.length) {
            resultsMap.hidden = false;
            var tileSize = 256;
            var activeProviderId = null;
            var setResultsView = function (view) {
                if (!resultsViewShell || (view !== 'list' && view !== 'map')) { return; }
                resultsViewShell.setAttribute('data-active-view', view);
                document.querySelectorAll('[data-results-view]').forEach(function (button) {
                    button.setAttribute('aria-pressed', button.getAttribute('data-results-view') === view ? 'true' : 'false');
                });
                if (resultsList) { resultsList.hidden = view === 'map' && window.matchMedia('(max-width: 719px)').matches; }
                if (view === 'map') { window.setTimeout(renderResultsMap, 0); }
            };
            document.querySelectorAll('[data-results-view]').forEach(function (button) {
                button.addEventListener('click', function () { setResultsView(button.getAttribute('data-results-view')); });
            });
            var openSummary = function (provider, index, focusSummary) {
                activeProviderId = String(provider.id);
                resultsMap.querySelectorAll('.results-map-pin').forEach(function (candidate) {
                    candidate.classList.toggle('is-active', candidate.getAttribute('data-provider-id') === activeProviderId);
                });
                if (!mapSummary) { return; }
                summaryPosition.textContent = 'Result ' + (index + 1);
                summaryName.textContent = provider.name;
                summaryLocation.textContent = provider.location || 'Location supplied on provider profile';
                summaryProfile.href = provider.profile;
                summaryDirections.hidden = !provider.directions;
                if (provider.directions) {
                    summaryDirections.href = provider.directions;
                    summaryDirections.setAttribute('data-map-destination', provider.destination || '');
                }
                mapSummary.classList.remove('is-collapsed');
                if (summaryToggle) {
                    summaryToggle.setAttribute('aria-expanded', 'true');
                    summaryToggle.textContent = 'Collapse';
                }
                mapSummary.hidden = false;
                if (focusSummary) { summaryProfile.focus(); }
            };
            if (summaryClose) {
                summaryClose.addEventListener('click', function () {
                    mapSummary.hidden = true;
                    activeProviderId = null;
                    resultsMap.querySelectorAll('.results-map-pin').forEach(function (candidate) { candidate.classList.remove('is-active'); });
                });
            }
            if (summaryToggle) {
                summaryToggle.addEventListener('click', function () {
                    var collapsed = mapSummary.classList.toggle('is-collapsed');
                    summaryToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                    summaryToggle.textContent = collapsed ? 'Expand' : 'Collapse';
                });
            }
            if (summaryDrag) {
                var summaryDragStart = null;
                var moveSummary = function (left, top) {
                    var maxLeft = Math.max(0, resultsMap.clientWidth - mapSummary.offsetWidth);
                    var maxTop = Math.max(0, resultsMap.clientHeight - mapSummary.offsetHeight - 24);
                    mapSummary.style.left = Math.max(0, Math.min(maxLeft, left)) + 'px';
                    mapSummary.style.top = Math.max(0, Math.min(maxTop, top)) + 'px';
                    mapSummary.style.bottom = 'auto';
                };
                summaryDrag.addEventListener('pointerdown', function (event) {
                    summaryDragStart = {
                        x: event.clientX,
                        y: event.clientY,
                        left: mapSummary.offsetLeft,
                        top: mapSummary.offsetTop
                    };
                    summaryDrag.setPointerCapture(event.pointerId);
                    event.preventDefault();
                });
                summaryDrag.addEventListener('pointermove', function (event) {
                    if (!summaryDragStart) { return; }
                    moveSummary(summaryDragStart.left + event.clientX - summaryDragStart.x, summaryDragStart.top + event.clientY - summaryDragStart.y);
                });
                summaryDrag.addEventListener('pointerup', function () { summaryDragStart = null; });
                summaryDrag.addEventListener('pointercancel', function () { summaryDragStart = null; });
                summaryDrag.addEventListener('keydown', function (event) {
                    var step = event.shiftKey ? 40 : 12;
                    var left = mapSummary.offsetLeft;
                    var top = mapSummary.offsetTop;
                    if (event.key === 'ArrowLeft') { left -= step; }
                    else if (event.key === 'ArrowRight') { left += step; }
                    else if (event.key === 'ArrowUp') { top -= step; }
                    else if (event.key === 'ArrowDown') { top += step; }
                    else { return; }
                    event.preventDefault();
                    moveSummary(left, top);
                });
            }
            if (summaryDirections) {
                summaryDirections.addEventListener('click', function (event) {
                    var destination = summaryDirections.getAttribute('data-map-destination') || '';
                    if (!destination) { return; }
                    var appleMobile = /iPhone|iPad|iPod/i.test(navigator.userAgent)
                        || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
                    var androidMobile = /Android/i.test(navigator.userAgent);
                    if (!appleMobile && !androidMobile) { return; }
                    event.preventDefault();
                    window.location.href = appleMobile
                        ? 'https://maps.apple.com/?daddr=' + encodeURIComponent(destination) + '&dirflg=d'
                        : 'geo:0,0?q=' + encodeURIComponent(destination);
                });
            }
            if (summaryList) {
                summaryList.addEventListener('click', function () {
                    var card = activeProviderId ? document.getElementById('provider-result-' + activeProviderId) : null;
                    setResultsView('list');
                    if (!card) { return; }
                    window.setTimeout(function () {
                        card.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'center' });
                        card.focus({ preventScroll: true });
                    }, 0);
                });
            }
            var project = function (lat, lng, zoom) {
                var scale = Math.pow(2, zoom);
                var safeLat = Math.max(-85.0511, Math.min(85.0511, Number(lat)));
                var sin = Math.sin(safeLat * Math.PI / 180);
                return {
                    x: (Number(lng) + 180) / 360 * scale * tileSize,
                    y: (0.5 - Math.log((1 + sin) / (1 - sin)) / (4 * Math.PI)) * scale * tileSize
                };
            };
            var unproject = function (x, y, zoom) {
                var world = Math.pow(2, zoom) * tileSize;
                var lng = x / world * 360 - 180;
                var mercator = Math.PI - (2 * Math.PI * y / world);
                var lat = 180 / Math.PI * Math.atan(Math.sinh(mercator));
                return { lat: Math.max(-85.0511, Math.min(85.0511, lat)), lng: lng };
            };
            var resultPoints = providers.map(function (provider) {
                return { lat: Number(provider.lat), lng: Number(provider.lng) };
            });
            var origin = mapPayload.origin;
            if (origin && validCoordinate(origin.lat, origin.lng)) {
                resultPoints.push({ lat: Number(origin.lat), lng: Number(origin.lng) });
            }
            var mapState = { zoom: null, centerLat: null, centerLng: null };
            var calculateFit = function () {
                var width = Math.max(280, mapCanvas.clientWidth);
                var height = Math.max(310, mapCanvas.clientHeight);
                var zoom = 15;
                var projected = [];
                for (; zoom >= 3; zoom -= 1) {
                    projected = resultPoints.map(function (point) { return project(point.lat, point.lng, zoom); });
                    var xs = projected.map(function (point) { return point.x; });
                    var ys = projected.map(function (point) { return point.y; });
                    if (Math.max.apply(null, xs) - Math.min.apply(null, xs) <= width - 96
                        && Math.max.apply(null, ys) - Math.min.apply(null, ys) <= height - 96) { break; }
                }
                var minX = Math.min.apply(null, projected.map(function (point) { return point.x; }));
                var maxX = Math.max.apply(null, projected.map(function (point) { return point.x; }));
                var minY = Math.min.apply(null, projected.map(function (point) { return point.y; }));
                var maxY = Math.max.apply(null, projected.map(function (point) { return point.y; }));
                var center = unproject((minX + maxX) / 2, (minY + maxY) / 2, zoom);
                return { zoom: zoom, centerLat: center.lat, centerLng: center.lng };
            };
            var renderResultsMap = function () {
                var width = Math.max(280, mapCanvas.clientWidth);
                var height = Math.max(310, mapCanvas.clientHeight);
                if (mapState.zoom === null) { mapState = calculateFit(); }
                var zoom = mapState.zoom;
                var center = project(mapState.centerLat, mapState.centerLng, zoom);
                var left = center.x - width / 2;
                var top = center.y - height / 2;
                var fragment = document.createDocumentFragment();

                for (var tileX = Math.floor(left / tileSize); tileX <= Math.floor((left + width) / tileSize); tileX += 1) {
                    for (var tileY = Math.floor(top / tileSize); tileY <= Math.floor((top + height) / tileSize); tileY += 1) {
                        var maxTile = Math.pow(2, zoom);
                        if (tileY < 0 || tileY >= maxTile) { continue; }
                        var wrappedX = ((tileX % maxTile) + maxTile) % maxTile;
                        var tile = document.createElement('img');
                        tile.className = 'results-map-tile';
                        tile.alt = '';
                        tile.decoding = 'async';
                        tile.referrerPolicy = 'strict-origin-when-cross-origin';
                        tile.src = 'https://tile.openstreetmap.org/' + zoom + '/' + wrappedX + '/' + tileY + '.png';
                        tile.style.left = (tileX * tileSize - left) + 'px';
                        tile.style.top = (tileY * tileSize - top) + 'px';
                        fragment.appendChild(tile);
                    }
                }

                providers.forEach(function (provider, index) {
                    var point = project(provider.lat, provider.lng, zoom);
                    var pin = document.createElement('button');
                    pin.type = 'button';
                    pin.className = 'results-map-pin' + (provider.featured ? ' is-featured' : (provider.possible ? ' is-possible' : ''));
                    pin.setAttribute('data-provider-id', String(provider.id));
                    pin.setAttribute('data-number', String(index + 1));
                    pin.style.left = (point.x - left) + 'px';
                    pin.style.top = (point.y - top) + 'px';
                    pin.setAttribute('aria-label', provider.name + (provider.location ? ', ' + provider.location : '') + '. Open provider summary.');
                    pin.title = provider.name;
                    if (String(provider.id) === activeProviderId) { pin.classList.add('is-active'); }
                    pin.addEventListener('click', function (event) {
                        var card = document.getElementById('provider-result-' + provider.id);
                        openSummary(provider, index, true);
                        if (card) {
                            card.classList.add('provider-card--map-focus');
                            window.setTimeout(function () { card.classList.remove('provider-card--map-focus'); }, 1800);
                        }
                    });
                    fragment.appendChild(pin);
                });

                if (origin && validCoordinate(origin.lat, origin.lng)) {
                    var originPoint = project(origin.lat, origin.lng, zoom);
                    var originPin = document.createElement('span');
                    originPin.className = 'results-map-origin';
                    originPin.style.left = (originPoint.x - left) + 'px';
                    originPin.style.top = (originPoint.y - top) + 'px';
                    originPin.setAttribute('role', 'img');
                    originPin.setAttribute('aria-label', 'Your searched location');
                    fragment.appendChild(originPin);
                }

                mapCanvas.replaceChildren(fragment);
                if (mapStatus) { mapStatus.textContent = providers.length + ' returned ' + (providers.length === 1 ? 'provider is' : 'providers are') + ' shown on the map and in the list below.'; }
            };

            var setMapZoom = function (zoom) {
                mapState.zoom = Math.max(3, Math.min(18, Math.round(zoom)));
                renderResultsMap();
            };
            var renderFrame = null;
            var scheduleMapRender = function () {
                if (renderFrame !== null) { return; }
                renderFrame = window.requestAnimationFrame(function () {
                    renderFrame = null;
                    renderResultsMap();
                });
            };
            var panMap = function (deltaX, deltaY) {
                var center = project(mapState.centerLat, mapState.centerLng, mapState.zoom);
                var next = unproject(center.x + deltaX, center.y + deltaY, mapState.zoom);
                mapState.centerLat = next.lat;
                mapState.centerLng = next.lng;
                renderResultsMap();
            };
            var fitMap = function () {
                mapState = calculateFit();
                renderResultsMap();
                mapCanvas.focus({ preventScroll: true });
            };
            if (zoomIn) { zoomIn.addEventListener('click', function () { setMapZoom(mapState.zoom + 1); }); }
            if (zoomOut) { zoomOut.addEventListener('click', function () { setMapZoom(mapState.zoom - 1); }); }
            if (fitResults) { fitResults.addEventListener('click', fitMap); }

            var activePointers = new Map();
            var dragStart = null;
            var pinchStart = null;
            mapCanvas.addEventListener('pointerdown', function (event) {
                if (event.target.closest && event.target.closest('.results-map-pin')) { return; }
                activePointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
                mapCanvas.setPointerCapture(event.pointerId);
                if (activePointers.size === 1) {
                    var center = project(mapState.centerLat, mapState.centerLng, mapState.zoom);
                    dragStart = { x: event.clientX, y: event.clientY, centerX: center.x, centerY: center.y };
                } else if (activePointers.size === 2) {
                    var pair = Array.from(activePointers.values());
                    pinchStart = { distance: Math.hypot(pair[0].x - pair[1].x, pair[0].y - pair[1].y), zoom: mapState.zoom };
                    dragStart = null;
                }
                event.preventDefault();
            });
            mapCanvas.addEventListener('pointermove', function (event) {
                if (!activePointers.has(event.pointerId)) { return; }
                activePointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
                if (activePointers.size === 1 && dragStart) {
                    var nextCenter = unproject(dragStart.centerX - (event.clientX - dragStart.x), dragStart.centerY - (event.clientY - dragStart.y), mapState.zoom);
                    mapState.centerLat = nextCenter.lat;
                    mapState.centerLng = nextCenter.lng;
                    scheduleMapRender();
                } else if (activePointers.size === 2 && pinchStart) {
                    var pair = Array.from(activePointers.values());
                    var distance = Math.max(1, Math.hypot(pair[0].x - pair[1].x, pair[0].y - pair[1].y));
                    var nextZoom = Math.max(3, Math.min(18, Math.round(pinchStart.zoom + Math.log2(distance / Math.max(1, pinchStart.distance)))));
                    if (nextZoom !== mapState.zoom) { mapState.zoom = nextZoom; scheduleMapRender(); }
                }
                event.preventDefault();
            });
            var endPointer = function (event) {
                activePointers.delete(event.pointerId);
                if (activePointers.size === 0) { dragStart = null; pinchStart = null; }
                else if (activePointers.size === 1) {
                    var remaining = Array.from(activePointers.values())[0];
                    var center = project(mapState.centerLat, mapState.centerLng, mapState.zoom);
                    dragStart = { x: remaining.x, y: remaining.y, centerX: center.x, centerY: center.y };
                    pinchStart = null;
                }
            };
            mapCanvas.addEventListener('pointerup', endPointer);
            mapCanvas.addEventListener('pointercancel', endPointer);
            mapCanvas.addEventListener('wheel', function (event) {
                event.preventDefault();
                setMapZoom(mapState.zoom + (event.deltaY < 0 ? 1 : -1));
            }, { passive: false });
            mapCanvas.addEventListener('keydown', function (event) {
                if (event.key === '+' || event.key === '=') { event.preventDefault(); setMapZoom(mapState.zoom + 1); }
                else if (event.key === '-') { event.preventDefault(); setMapZoom(mapState.zoom - 1); }
                else if (event.key === '0' || event.key.toLowerCase() === 'f') { event.preventDefault(); fitMap(); }
                else if (event.key === 'ArrowLeft') { event.preventDefault(); panMap(-80, 0); }
                else if (event.key === 'ArrowRight') { event.preventDefault(); panMap(80, 0); }
                else if (event.key === 'ArrowUp') { event.preventDefault(); panMap(0, -80); }
                else if (event.key === 'ArrowDown') { event.preventDefault(); panMap(0, 80); }
            });

            renderResultsMap();
            setResultsView(window.matchMedia('(max-width: 719px)').matches ? 'list' : 'map');
            document.querySelectorAll('[id^="provider-result-"]').forEach(function (card) {
                var providerId = card.id.replace('provider-result-', '');
                var providerIndex = providers.findIndex(function (provider) { return String(provider.id) === providerId; });
                if (providerIndex < 0) { return; }
                card.addEventListener('focusin', function () { openSummary(providers[providerIndex], providerIndex, false); });
                card.addEventListener('click', function () { openSummary(providers[providerIndex], providerIndex, false); });
            });
            var mapResizeTimer = null;
            window.addEventListener('resize', function () {
                window.clearTimeout(mapResizeTimer);
                mapResizeTimer = window.setTimeout(renderResultsMap, 160);
            }, { passive: true });
        }
    }
})();
