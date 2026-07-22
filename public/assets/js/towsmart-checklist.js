(function () {
    'use strict';
    var root = document.querySelector('[data-tow-checklist]');
    if (!root) { return; }
    var key = 'towsmart.checklist.v1';
    var customKey = 'towsmart.checklist.custom.v1';
    var state = {};
    try { state = JSON.parse(localStorage.getItem(key) || '{}'); } catch (e) { state = {}; }
    var update = function () {
        var boxes = Array.prototype.slice.call(root.querySelectorAll('[data-check-id]'));
        var complete = boxes.filter(function (box) { return box.checked; }).length;
        root.querySelector('[data-check-progress]').textContent = complete + ' of ' + boxes.length + ' complete';
        boxes.forEach(function (box) { state[box.dataset.checkId] = box.checked; });
        localStorage.setItem(key, JSON.stringify(state));
    };
    var bind = function (box) { box.checked = !!state[box.dataset.checkId]; box.addEventListener('change', update); };
    root.querySelectorAll('[data-check-id]').forEach(bind);
    var custom = [];
    try { custom = JSON.parse(localStorage.getItem(customKey) || '[]'); } catch (e) { custom = []; }
    var customRoot = root.querySelector('[data-custom-checks]');
    var renderCustom = function () {
        customRoot.innerHTML = '';
        custom.forEach(function (text, index) {
            var label = document.createElement('label'); label.className = 'checklist-item';
            label.innerHTML = '<input type="checkbox" data-check-id="custom-' + index + '"><span></span><button type="button" aria-label="Remove item">Remove</button>';
            label.querySelector('span').textContent = text; label.querySelector('button').addEventListener('click', function () { custom.splice(index, 1); localStorage.setItem(customKey, JSON.stringify(custom)); renderCustom(); });
            customRoot.appendChild(label); bind(label.querySelector('input'));
        }); update();
    };
    root.querySelector('[data-add-check]').addEventListener('submit', function (event) { event.preventDefault(); var input = root.querySelector('#custom_check'); if (input.value.trim()) { custom.push(input.value.trim()); localStorage.setItem(customKey, JSON.stringify(custom)); input.value = ''; renderCustom(); } });
    root.querySelector('[data-reset-checks]').addEventListener('click', function () { state = {}; localStorage.removeItem(key); root.querySelectorAll('[data-check-id]').forEach(function (box) { box.checked = false; }); update(); });
    renderCustom();
}());
