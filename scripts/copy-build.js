const fs = require('fs');
const path = require('path');

function copyBuild(buildDir, targetDir) {
  // A previous bundle can remain in build/static. The manifest is authoritative.
  const manifest = JSON.parse(fs.readFileSync(path.join(buildDir, 'asset-manifest.json'), 'utf8'));
  fs.mkdirSync(targetDir, { recursive: true });
  for (const [entry, destination] of [['main.js', 'app.js'], ['main.css', 'app.css']]) {
    const entryPath = manifest.files && manifest.files[entry];
    if (typeof entryPath !== 'string') throw new Error(`Missing build manifest entry: ${entry}`);
    const assetPath = new URL(entryPath, 'https://build.invalid/').pathname;
    const staticIndex = assetPath.indexOf('/static/');
    if (staticIndex < 0) throw new Error(`Invalid build asset path: ${entryPath}`);
    const source = path.resolve(buildDir, assetPath.slice(staticIndex + 1));
    if (!source.startsWith(path.resolve(buildDir) + path.sep)) throw new Error('Build asset is outside build directory');
    const target = path.join(targetDir, destination);
    fs.copyFileSync(source, target);
    console.log(`Copied ${source} -> ${target}`);
  }
}

if (require.main === module) {
  try {
    copyBuild(path.join(__dirname, '..', 'build'), path.join(__dirname, '..', 'react-db-plugin', 'assets'));
  } catch (error) {
    console.error(error.message);
    process.exitCode = 1;
  }
}
module.exports = { copyBuild };
