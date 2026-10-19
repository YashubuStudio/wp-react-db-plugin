const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { copyBuild } = require('./copy-build');

test('packages the manifest bundle even when an older bundle sorts first', () => {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), 'reactdb-build-'));
  const buildDir = path.join(root, 'build');
  const targetDir = path.join(root, 'plugin-assets');
  fs.mkdirSync(path.join(buildDir, 'static/js'), { recursive: true });
  fs.mkdirSync(path.join(buildDir, 'static/css'), { recursive: true });
  fs.writeFileSync(path.join(buildDir, 'static/js/main.000old.js'), 'OLD');
  fs.writeFileSync(path.join(buildDir, 'static/js/main.999new.js'), 'NEW AUTH FIX');
  fs.writeFileSync(path.join(buildDir, 'static/css/main.current.css'), 'CURRENT CSS');
  fs.writeFileSync(path.join(buildDir, 'asset-manifest.json'), JSON.stringify({ files: {
    'main.js': '/static/js/main.999new.js', 'main.css': '/static/css/main.current.css'
  } }));
  copyBuild(buildDir, targetDir);
  assert.equal(fs.readFileSync(path.join(targetDir, 'app.js'), 'utf8'), 'NEW AUTH FIX');
  assert.equal(fs.readFileSync(path.join(targetDir, 'app.css'), 'utf8'), 'CURRENT CSS');
});
test('does not silently package arbitrary files if the manifest is missing', () => {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), 'reactdb-no-manifest-'));
  assert.throws(() => copyBuild(root, path.join(root, 'assets')), /ENOENT/);
});
