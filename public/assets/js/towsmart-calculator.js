(function () {
    'use strict';
    var form = document.querySelector('[data-towsmart-calculator]');
    if (!form) { return; }
    var base = form.dataset.catalogueBase;

    var setValue = function (id, value) {
        var field = document.getElementById(id);
        if (field && value !== null && typeof value !== 'undefined' && value !== '') {
            field.value = value;
            field.dispatchEvent(new Event('change', { bubbles: true }));
        }
    };

    var maps = {
        vehicles: {
            name: 'vehicle_name', kerb_weight: 'vehicle_kerb_mass', gvm: 'vehicle_gvm', gcm: 'vehicle_gcm',
            towing_capacity: 'vehicle_max_braked_towing', towball_download_max: 'vehicle_max_towball',
            front_axle_limit: 'vehicle_front_axle_limit', rear_axle_limit: 'vehicle_rear_axle_limit'
        },
        trailers: {
            name: 'trailer_name', type: 'trailer_type', axle_config: 'trailer_axle_config', tare_weight: 'trailer_tare_mass',
            atm: 'trailer_atm', gtm: 'trailer_gtm', ball_weight: 'trailer_tare_ball_mass'
        }
    };

    var applyItem = function (type, item) {
        Object.keys(maps[type]).forEach(function (key) { setValue(maps[type][key], item[key]); });
        var idField = document.getElementById((type === 'vehicles' ? 'vehicle' : 'trailer') + '_catalogue_id');
        if (idField) { idField.value = item.id; }
        var search = form.querySelector('[data-catalogue-search="' + type + '"]');
        if (search) {
            search.value = [type === 'trailers' ? item.brand : '', item.name, item.years].filter(Boolean).join(' ');
        }
        var summary = form.querySelector('[data-selected-summary="' + (type === 'vehicles' ? 'vehicle' : 'trailer') + '"]');
        if (summary) {
            var stats = type === 'vehicles'
                ? ['GVM ' + item.gvm + ' kg', 'GCM ' + item.gcm + ' kg', 'Tow ' + item.towing_capacity + ' kg']
                : ['Tare ' + item.tare_weight + ' kg', 'ATM ' + item.atm + ' kg', (item.type || 'Trailer')];
            summary.innerHTML = '<strong>Selected: ' + search.value + '</strong><span>' + stats.join(' · ') + '</span><small>Advertised reference specification—confirm against your exact plate and handbook.</small>';
            summary.hidden = false;
        }
    };

    form.querySelectorAll('[data-catalogue-search]').forEach(function (input) {
        var type = input.dataset.catalogueSearch;
        var results = document.getElementById(type === 'vehicles' ? 'vehicle_catalogue_results' : 'trailer_catalogue_results');
        var timer;
        var close = function () { results.hidden = true; results.innerHTML = ''; };
        input.addEventListener('input', function () {
            window.clearTimeout(timer);
            var q = input.value.trim();
            if (q.length < 2) { close(); return; }
            timer = window.setTimeout(function () {
                fetch(base + '/' + type + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        results.innerHTML = '';
                        (data.items || []).forEach(function (item) {
                            var button = document.createElement('button');
                            button.type = 'button';
                            button.setAttribute('role', 'option');
                            button.innerHTML = '<strong>' + item.label + '</strong><span>' + (item.type || (item.gvm ? 'GVM ' + item.gvm + ' kg · Tow ' + item.towing_capacity + ' kg' : '')) + '</span>';
                            button.addEventListener('click', function () {
                                fetch(base + '/' + type + '/' + item.id, { headers: { Accept: 'application/json' } })
                                    .then(function (r) { return r.json(); })
                                    .then(function (detail) { if (detail.item) { applyItem(type, detail.item); close(); } });
                            });
                            results.appendChild(button);
                        });
                        results.hidden = !results.children.length;
                    }).catch(close);
            }, 180);
        });
        input.addEventListener('keydown', function (event) { if (event.key === 'Escape') { close(); } });
        document.addEventListener('click', function (event) { if (event.target !== input && !results.contains(event.target)) { close(); } });
    });

    form.querySelectorAll('[data-custom-entry]').forEach(function (button) {
        button.addEventListener('click', function () {
            var kind = button.dataset.customEntry;
            var search = document.getElementById(kind + '_catalogue_search');
            var id = document.getElementById(kind + '_catalogue_id');
            if (id) { id.value = ''; }
            if (search) { search.value = ''; search.focus(); }
            var name = document.getElementById(kind + '_name');
            if (name) { name.focus(); }
        });
    });

    var accessoryIndex = 0;
    var recalculateAccessories = function () {
        var vehicleTotal = 0, over = 0, front = 0, rear = 0;
        form.querySelectorAll('[data-accessory-row]').forEach(function (row) {
            var weight = parseFloat(row.querySelector('[data-accessory-weight]').value) || 0;
            var quantity = parseInt(row.querySelector('[data-accessory-quantity]').value, 10) || 1;
            var total = Math.max(0, weight) * Math.max(1, quantity);
            if (row.dataset.accessoryKind === 'vehicle') { vehicleTotal += total; return; }
            var position = row.querySelector('[data-accessory-position]').value;
            if (position === 'front') { front += total; } else if (position === 'behind') { rear += total; } else { over += total; }
        });
        setValue('vehicle_accessories_mass', vehicleTotal);
        setValue('trailer_accessories_mass', over);
        setValue('trailer_front_accessories_mass', front);
        setValue('trailer_rear_accessories_mass', rear);
    };
    var addAccessory = function (kind) {
        accessoryIndex += 1;
        var row = document.createElement('div'); row.className = 'accessory-row'; row.dataset.accessoryRow = accessoryIndex; row.dataset.accessoryKind = kind;
        row.innerHTML = '<input aria-label="Accessory name" list="' + kind + '_accessories" placeholder="Accessory name"><input aria-label="Weight in kilograms" data-accessory-weight type="number" min="0" step="0.1" placeholder="kg"><input aria-label="Quantity" data-accessory-quantity type="number" min="1" step="1" value="1">' + (kind === 'trailer' ? '<select aria-label="Position" data-accessory-position><option value="front">Forward</option><option value="over" selected>Over axle</option><option value="behind">Behind axle</option></select>' : '') + '<button type="button" aria-label="Remove accessory">×</button>';
        row.querySelectorAll('input,select').forEach(function (field) { field.addEventListener('input', recalculateAccessories); field.addEventListener('change', recalculateAccessories); });
        row.querySelector('button').addEventListener('click', function () { row.remove(); recalculateAccessories(); });
        form.querySelector('[data-accessory-list="' + kind + '"]').appendChild(row);
    };
    form.querySelectorAll('[data-add-accessory]').forEach(function (button) { button.addEventListener('click', function () { addAccessory(button.dataset.addAccessory); }); });
}());
