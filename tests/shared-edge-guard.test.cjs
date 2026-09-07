const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const test = require('node:test');

const root = path.resolve(__dirname, '..');
const guard = path.join(
  root,
  'infrastructure',
  'binarylane',
  'ops',
  'check-shared-public-edge.sh',
);
const installer = path.join(root, 'scripts', 'install-shared-edge-guard.sh');

test('shared-edge guard is generic and has valid shell syntax', () => {
  const source = fs.readFileSync(guard, 'utf8');
  assert.match(source, /required-sites\.txt/);
  assert.match(source, /Candidate drops registered host routes/);
  assert.doesNotMatch(source, /signconsole|vanassist|towsmart|trailerwise|cqdiggings/i);

  for (const script of [guard, installer]) {
    const result = spawnSync('bash', ['-n', script], { encoding: 'utf8' });
    assert.equal(result.status, 0, result.stderr);
  }
});

test(
  'shared-edge guard checks, snapshots and verifies every registered site',
  { skip: process.platform === 'win32' },
  () => {
  const temp = fs.mkdtempSync(path.join(os.tmpdir(), 'shared-edge-guard-'));
  const edge = path.join(temp, 'edge');
  const bin = path.join(temp, 'bin');
  const state = path.join(temp, 'state');
  fs.mkdirSync(path.join(edge, 'sites'), { recursive: true });
  fs.mkdirSync(bin);
  fs.mkdirSync(state);
  fs.writeFileSync(
    path.join(edge, 'required-sites.txt'),
    'https://alpha.example.test/|Alpha Product\nhttps://beta.example.test/ready|Beta Product\n',
  );
  fs.writeFileSync(path.join(edge, 'sites', 'alpha.caddy'), 'alpha.example.test {}\n');
  fs.writeFileSync(
    path.join(bin, 'curl'),
    `#!/usr/bin/env bash
set -eu
output=
url=
while (($#)); do
  case "$1" in
    --output) output="$2"; shift 2 ;;
    http*) url="$1"; shift ;;
    *) shift ;;
  esac
done
case "$url" in
  *alpha*) printf 'Alpha Product' > "$output" ;;
  *beta*) printf 'Beta Product' > "$output" ;;
  *) exit 22 ;;
esac
printf '200'
`,
    { mode: 0o755 },
  );

  const env = {
    ...process.env,
    SHARED_EDGE_ROOT: edge,
    PATH: `${bin}${path.delimiter}${process.env.PATH}`,
  };
  const snapshot = spawnSync('bash', [guard, 'snapshot', state], { env, encoding: 'utf8' });
  assert.equal(snapshot.status, 0, snapshot.stderr);
  assert.match(snapshot.stdout, /alpha\.example\.test/);
  assert.match(snapshot.stdout, /beta\.example\.test/);

  const verify = spawnSync('bash', [guard, 'verify', state], { env, encoding: 'utf8' });
  assert.equal(verify.status, 0, verify.stderr);

  fs.writeFileSync(path.join(edge, 'sites', 'alpha.caddy'), 'changed\n');
  const changed = spawnSync('bash', [guard, 'verify', state], { env, encoding: 'utf8' });
  assert.notEqual(changed.status, 0);
    assert.match(changed.stderr, /routes changed/);
  },
);
