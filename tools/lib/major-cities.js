"use strict";

const fs = require("fs");
const path = require("path");

const MAJOR_CITIES_FILE = path.join(__dirname, "..", "..", "database", "seeds", "major_cities.json");

/** @returns {{metros:object[],cities:object[]}} */
function loadMajorCities() {
  if (!fs.existsSync(MAJOR_CITIES_FILE)) {
    throw new Error(`Missing ${MAJOR_CITIES_FILE}`);
  }
  const data = JSON.parse(fs.readFileSync(MAJOR_CITIES_FILE, "utf8"));
  return {
    metros: data.metros || [],
    cities: data.cities || [],
  };
}

/** @returns {Map<string,object>} keyed by STATE|name */
function majorCityIndex() {
  const { metros, cities } = loadMajorCities();
  const map = new Map();
  for (const row of [...metros, ...cities]) {
    map.set(`${row.state}|${row.name}`.toUpperCase(), row);
  }
  return map;
}

function distKm(a, b, c, d) {
  const R = 6371;
  const toRad = (x) => (x * Math.PI) / 180;
  const dLat = toRad(c - a);
  const dLng = toRad(d - b);
  const s =
    Math.sin(dLat / 2) ** 2 +
    Math.cos(toRad(a)) * Math.cos(toRad(c)) * Math.sin(dLng / 2) ** 2;
  return 2 * R * Math.asin(Math.sqrt(s));
}

/** @returns {object|null} nearest major city row within its scan radius */
function nearestMajorCity(lat, lng, state = null) {
  const { metros, cities } = loadMajorCities();
  let best = null;
  let bestD = Infinity;
  for (const row of [...metros, ...cities]) {
    if (state && row.state !== state) continue;
    const radius = row.scan_radius_km || (row.bbox ? 80 : 50);
    const d = distKm(lat, lng, row.lat, row.lng);
    if (d <= radius && d < bestD) {
      bestD = d;
      best = row;
    }
  }
  return best;
}

module.exports = {
  MAJOR_CITIES_FILE,
  loadMajorCities,
  majorCityIndex,
  nearestMajorCity,
  distKm,
};
