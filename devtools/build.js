// Builds the editor assets, and with --package the plugin folder for a release.
//
//   node devtools/build.js                      dist/ only
//   node devtools/build.js --package [--tag vX.Y.Z]
//
// --package checks that every version string agrees (and matches --tag), then
// copies only the files WordPress needs into build/alps-gutenberg-blocks/.
const esbuild = require('esbuild');
const sass = require('sass');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const slug = 'alps-gutenberg-blocks';
const globals = {
  'block-editor': 'blockEditor', blocks: 'blocks', components: 'components',
  data: 'data', date: 'date', element: 'element', 'html-entities': 'htmlEntities',
  i18n: 'i18n', 'server-side-render': 'serverSideRender',
};
// Everything else in the repository (tooling, tests, .github) stays out of the package.
const packageFiles = [
  'src/newspaper-posts/class-newspaper-posts-block.php',
  'src/newspaper-posts/block.json',
  'dist/newspaper-posts.css',
  'plugin.php',
  'updater.php',
  'index.php',
  'src/init.php',
  'src/latest-posts/class-latest-posts-block.php',
  'src/latest-posts/block.json',
  'src/youtube-channel/class-youtube-channel-block.php',
  'src/youtube-channel/block.json',
  'languages/alps-gutenberg-blocks-lt_LT.l10n.php',
  'dist/blocks.build.js',
  'dist/blocks.editor.build.css',
  'dist/youtube-channel.js',
  'dist/youtube-channel.css',
];
const editorStyles = ['src/latest-posts/editor.scss', 'src/youtube-channel/editor.scss'];

async function buildAssets() {
  fs.mkdirSync(path.join(root, 'dist'), { recursive: true });
  await esbuild.build({
    absWorkingDir: root, entryPoints: ['src/index.js'], bundle: true,
    outfile: 'dist/blocks.build.js', format: 'iife', minify: true,
    target: ['es2020'], loader: { '.js': 'jsx' },
    jsxFactory: 'wp.element.createElement', jsxFragment: 'wp.element.Fragment',
    plugins: [{ name: 'wordpress-and-styles', setup(build) {
      build.onResolve({ filter: /^@wordpress\// }, args => {
        const name = args.path.slice('@wordpress/'.length);
        if (!globals[name]) throw new Error(`Unmapped WordPress dependency: ${args.path}`);
        return { path: name, namespace: 'wordpress' };
      });
      build.onLoad({ filter: /.*/, namespace: 'wordpress' }, args => ({
        contents: `module.exports = window.wp.${globals[args.path]};`, loader: 'js',
      }));
      // Styles are compiled separately below.
      build.onLoad({ filter: /\.scss$/ }, () => ({ contents: '', loader: 'js' }));
    }}],
  });
  const css = editorStyles.map(file => sass.compile(path.join(root, file), { style: 'compressed' }).css);
  fs.writeFileSync(path.join(root, 'dist/blocks.editor.build.css'), css.join('\n'));

  fs.writeFileSync(path.join(root, 'dist/newspaper-posts.css'), sass.compile(path.join(root, 'src/newspaper-posts/style.scss'), { style: 'compressed' }).css);

  // Front end of the YouTube Channel Videos block, loaded only where it renders.
  await esbuild.build({
    absWorkingDir: root, entryPoints: ['src/youtube-channel/view.js'], bundle: true,
    outfile: 'dist/youtube-channel.js', format: 'iife', minify: true, target: ['es2020'],
  });
  fs.writeFileSync(
    path.join(root, 'dist/youtube-channel.css'),
    sass.compile(path.join(root, 'src/youtube-channel/style.scss'), { style: 'compressed' }).css
  );
}

function read(file) {
  return fs.readFileSync(path.join(root, file), 'utf8');
}

function checkVersions(tag) {
  const plugin = read('plugin.php');
  const found = {
    'plugin.php header': (plugin.match(/^\s*\*\s*Version:\s*(\S+)/m) || [])[1],
    'ALPS_GUTENBERG_VERSION': (plugin.match(/'ALPS_GUTENBERG_VERSION',\s*'([^']+)'/) || [])[1],
    'package.json': JSON.parse(read('package.json')).version,
    'package-lock.json': JSON.parse(read('package-lock.json')).version,
    'CHANGELOG.md': (read('CHANGELOG.md').match(/^## \[(\d+\.\d+\.\d+)\]/m) || [])[1],
  };
  if (tag !== undefined) {
    if (!/^v\d+\.\d+\.\d+$/.test(tag)) throw new Error(`Release tag must look like vX.Y.Z, got "${tag}"`);
    found['release tag'] = tag.slice(1);
  }
  const versions = new Set(Object.values(found));
  if (versions.size !== 1 || versions.has(undefined)) {
    throw new Error(`Versions disagree: ${JSON.stringify(found)}`);
  }
  return [...versions][0];
}

function packagePlugin() {
  const target = path.join(root, 'build', slug);
  fs.rmSync(path.join(root, 'build'), { recursive: true, force: true });
  for (const file of packageFiles) {
    fs.mkdirSync(path.dirname(path.join(target, file)), { recursive: true });
    fs.copyFileSync(path.join(root, file), path.join(target, file));
  }
}

async function main() {
  const args = process.argv.slice(2);
  const tagIndex = args.indexOf('--tag');
  const tag = tagIndex === -1 ? undefined : args[tagIndex + 1];
  await buildAssets();
  if (args.includes('--package')) {
    const version = checkVersions(tag);
    packagePlugin();
    console.log(`Packaged ${slug} ${version} in build/${slug}/`);
  }
}

main().catch(error => { console.error(error.message || error); process.exitCode = 1; });
