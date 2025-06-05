const fs = require('fs');
const path = require('path');

const buildDir = path.join(__dirname, '..', 'build');
const targetDir = path.join(__dirname, '..', 'react-db-plugin', 'assets');

if (!fs.existsSync(targetDir)) {
  fs.mkdirSync(targetDir, { recursive: true });
}

function findFile(dir, pattern) {
  const files = fs.readdirSync(dir);
  const match = files.find(f => pattern.test(f));
  return match ? path.join(dir, match) : null;
}

function copyFile(srcPattern, destName) {
  const src = findFile(srcPattern.dir, srcPattern.regex);
  if (!src) {
    throw new Error(`File matching ${srcPattern.regex} not found in ${srcPattern.dir}`);
  }
  const dest = path.join(targetDir, destName);
  fs.copyFileSync(src, dest);
  console.log(`Copied ${src} -> ${dest}`);
}

try {
  copyFile({dir: path.join(buildDir, 'static', 'js'), regex: /^main.*\.js$/}, 'app.js');
  copyFile({dir: path.join(buildDir, 'static', 'css'), regex: /^main.*\.css$/}, 'app.css');
} catch (err) {
  console.error(err.message);
  process.exit(1);
}
