const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { spawnSync } = require('node:child_process');

test('shared-edge guard rejects lost hosts, changed routes and wrong product responses', () => {
  const temp = fs.mkdtempSync(path.join(os.tmpdir(), 'edge-guard-test-'));
  const root = path.join(temp, 'edge');
  const bin = path.join(temp, 'bin');
  const state = path.join(temp, 'snapshot');
  for (const directory of [root, bin, state, path.join(root, 'sites')]) fs.mkdirSync(directory, { recursive: true });
  const manifest = path.join(root, 'required-sites.txt');
  fs.writeFileSync(manifest, 'https://one.example.test/|Correct product\nhttps://two.example.test/|Correct product\n');
  const route = path.join(root, 'sites/product.caddy');
  fs.writeFileSync(route, 'original route');
  const candidate = path.join(temp, 'Caddyfile');
  fs.writeFileSync(candidate, 'candidate');
  const json = path.join(temp, 'caddy.json');
  fs.writeFileSync(json, JSON.stringify({ apps: { http: { servers: { test: { routes: [{ match: [{ host: ['one.example.test', 'two.example.test'] }] }] } } } } }));
  fs.writeFileSync(path.join(bin, 'docker'), '#!/usr/bin/env bash\ncat >/dev/null\ncat "$EDGE_TEST_JSON"\n', { mode: 0o755 });
  fs.writeFileSync(path.join(bin, 'curl'), '#!/usr/bin/env bash\nwhile (( $# )); do\n if [[ "$1" == --output ]]; then shift; out="$1"; fi\n shift\ndone\nprintf "%s" "${EDGE_TEST_BODY:-Correct product}" > "$out"\nprintf 200\n', { mode: 0o755 });
  const script = path.join(__dirname, '../infrastructure/binarylane/ops/check-shared-public-edge.sh');
  const run = (args, extra = {}) => spawnSync('bash', [script, ...args], {
    env: { ...process.env, PATH: bin + path.delimiter + process.env.PATH, SHARED_EDGE_ROOT: root, EDGE_TEST_JSON: json, ...extra },
    encoding: 'utf8', timeout: 15000,
  });
  const good = args => { const result = run(args); assert.equal(result.status, 0, result.stderr + result.stdout); };
  try {
    good(['snapshot', state]);
    good(['candidate', candidate]);
    good(['verify', state]);
    fs.writeFileSync(json, '{"apps":{"http":{}}}');
    assert.notEqual(run(['candidate', candidate]).status, 0);
    fs.writeFileSync(route, 'changed route');
    assert.notEqual(run(['verify', state]).status, 0);
    fs.writeFileSync(route, 'original route');
    assert.notEqual(run(['check'], { EDGE_TEST_BODY: 'Wrong product' }).status, 0);
    fs.appendFileSync(manifest, 'https://three.example.test/|Correct product\n');
    assert.notEqual(run(['verify', state]).status, 0);
    fs.writeFileSync(manifest, '');
    assert.notEqual(run(['check']).status, 0);
  } finally {
    fs.rmSync(temp, { recursive: true, force: true });
  }
});
