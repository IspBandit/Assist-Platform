(() => {
    'use strict';
    const form = document.getElementById('tow-form');
    if (!form) return;
    const storageKey = 'towwise:last-scenario:v1';
    const status = document.getElementById('save-status');
    const numberAt = (selector) => Number(document.querySelector(selector)?.value || 0);
    const updatePlan = () => {
        document.getElementById('planned-vehicle').textContent = (
            numberAt('[data-planner="vehicle_tare"]') + numberAt('[data-planner="people"]') +
            numberAt('[data-planner="vehicle_accessories"]') + numberAt('[data-planner="vehicle_cargo"]')
        ).toFixed(1);
        document.getElementById('planned-trailer').textContent = (
            numberAt('[data-planner="trailer_tare"]') + numberAt('[data-planner="water"]') +
            numberAt('[data-planner="trailer_accessories"]') + numberAt('[data-planner="trailer_cargo"]')
        ).toFixed(1);
    };
    document.querySelectorAll('[data-planner]').forEach((input) => input.addEventListener('input', updatePlan));
    document.getElementById('apply-plan')?.addEventListener('click', () => {
        form.elements.loaded_vehicle.value = document.getElementById('planned-vehicle').textContent;
        form.elements.actual_trailer_mass.value = document.getElementById('planned-trailer').textContent;
        status.textContent = 'Planner totals applied.';
    });
    document.getElementById('save-local')?.addEventListener('click', () => {
        const data = {};
        new FormData(form).forEach((value, key) => { if (key !== '_csrf') data[key] = value; });
        document.querySelectorAll('[data-planner]').forEach((input) => { data[`planner:${input.dataset.planner}`] = input.value; });
        localStorage.setItem(storageKey, JSON.stringify(data));
        status.textContent = 'Saved privately in this browser.';
    });
    document.getElementById('clear-local')?.addEventListener('click', () => {
        localStorage.removeItem(storageKey);
        status.textContent = 'Saved figures cleared.';
    });
    document.getElementById('print-result')?.addEventListener('click', () => window.print());
    try {
        const data = JSON.parse(localStorage.getItem(storageKey) || 'null');
        if (data) {
            Object.entries(data).forEach(([key, value]) => {
                const element = key.startsWith('planner:')
                    ? document.querySelector(`[data-planner="${key.slice(8)}"]`)
                    : form.elements[key];
                if (element && !element.value) element.value = value;
            });
            updatePlan();
            status.textContent = 'Your last saved scenario is available.';
        }
    } catch (error) {
        localStorage.removeItem(storageKey);
    }
    document.getElementById('results')?.scrollIntoView({block: 'start'});
})();
