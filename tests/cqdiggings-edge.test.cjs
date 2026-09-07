const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { execFileSync } = require('node:child_process');

test('production Caddy snippet redirects only the two legacy navigation aliases', { timeout: 180000 }, async () => {
  const source = fs.readFileSync(path.join(__dirname, '../infrastructure/binarylane/Caddyfile'), 'utf8');
  const snippet = (start, end) => {
    const from = source.indexOf(start);
    const to = source.indexOf(end, from + start.length);
    assert.ok(from >= 0 && to > from, `Missing Caddy snippet ${start}`);
    return source.slice(from, to);
  };
  const config = '{\n admin off\n auto_https off\n}\n'
    + snippet('(security_headers) {', '(assist_site) {')
    + snippet('(cqdiggings_site) {', 'www.vanassist.com.au {')
    + '\n:8080 {\n import cqdiggings_site\n}\n';
  const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'cq-edge-test-'));
  const docker = (...args) => execFileSync('docker', args, { encoding: 'utf8', timeout: 90000 }).trim();
  let container;
  try {
    fs.writeFileSync(path.join(directory, 'Caddyfile'), config);
    for (const page of ['site-index', 'glossary']) fs.writeFileSync(path.join(directory, `${page}.html`), page);
    container = docker('run', '--detach', '--rm', '--publish', '127.0.0.1::8080',
      '--volume', `${directory}:/var/www/cqdiggings:ro`,
      '--volume', `${path.join(directory, 'Caddyfile')}:/etc/caddy/Caddyfile:ro`, 'caddy:2-alpine');
    assert.match(container, /^[a-f0-9]{64}$/);
    const port = docker('port', container, '8080/tcp');
    assert.match(port, /^127\.0\.0\.1:\d+$/);
    const origin = `http://${port}`;
    let ready = false;
    for (let attempt = 0; attempt < 30; attempt++) {
      try {
        const response = await fetch(`${origin}/site-index.html`, { signal: AbortSignal.timeout(1000) });
        if (response.status === 200) { ready = true; break; }
      } catch {}
      await new Promise(resolve => setTimeout(resolve, 250));
    }
    assert.ok(ready, 'Isolated Caddy did not become ready');
    const rootAlias = await fetch(`${origin}/index.html`, { redirect: 'manual', signal: AbortSignal.timeout(5000) });
    assert.equal(rootAlias.status, 301);
    assert.equal(rootAlias.headers.get('location'), '/');
    for (const page of ['site-index', 'glossary']) {
      for (const query of ['', '?utm_source=regression']) {
        const response = await fetch(`${origin}/occurrences/${page}.html${query}`, { redirect: 'manual', signal: AbortSignal.timeout(5000) });
        assert.equal(response.status, 301);
        assert.equal(response.headers.get('location'), `/${page}.html`);
        const target = await fetch(new URL(response.headers.get('location'), origin), { signal: AbortSignal.timeout(5000) });
        assert.equal(target.status, 200);
        assert.equal(target.headers.get('cache-control'), 'no-cache, must-revalidate');
        assert.equal(await target.text(), page);
      }
    }
    for (const missing of ['/occurrences/missing.html', '/occurrences/site-index.html-extra', '/.htaccess']) {
      const response = await fetch(origin + missing, { redirect: 'manual', signal: AbortSignal.timeout(5000) });
      assert.equal(response.status, 404, missing);
    }
  } finally {
    if (container && /^[a-f0-9]{64}$/.test(container)) docker('rm', '--force', container);
    fs.rmSync(directory, { recursive: true, force: true });
  }
});
