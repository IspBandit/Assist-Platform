const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

test('activation waits for cache cleanup and claim without navigating open forms', async () => {
  const events = {};
  const order = [];
  const self = {
    location: { origin: 'https://vanassist.test' },
    addEventListener(name, handler) { events[name] = handler; },
    clients: {
      async matchAll() { return [{ url: 'https://vanassist.test/calculator', navigate() { assert.fail('Must preserve the current form'); } }]; },
      async claim() { order.push('claim'); },
    },
  };
  const caches = {
    async keys() { return ['assist-platform-old', 'assist-platform-static-v2']; },
    async delete(key) { order.push(key); },
  };
  vm.runInNewContext(fs.readFileSync(require.resolve('../public/service-worker.js'), 'utf8'), { self, caches });
  let completion;
  events.activate({ waitUntil(promise) { completion = promise; } });
  assert.ok(completion);
  await completion;
  assert.deepEqual(order, ['assist-platform-old', 'claim']);
});
