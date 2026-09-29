// Keep the existing dist filenames and use WordPress-provided editor packages.
const esbuild = require('esbuild');
const sass = require('sass');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const styles = new Set();
const globals = {
  'api-fetch': 'apiFetch', 'block-editor': 'blockEditor', blocks: 'blocks',
  components: 'components', data: 'data', date: 'date', element: 'element',
  'html-entities': 'htmlEntities', i18n: 'i18n', primitives: 'primitives', url: 'url',
};
async function build() {
  await esbuild.build({
    absWorkingDir: root, entryPoints: ['src/blocks.js'], bundle: true,
    outfile: 'dist/blocks.build.js', format: 'iife', minify: true,
    target: ['es2018'], loader: { '.js': 'jsx' },
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
      build.onLoad({ filter: /\.scss$/ }, args => {
        styles.add(args.path);
        return { contents: '', loader: 'js' };
      });
    }}],
  });
  for (const kind of ['style', 'editor']) {
    const files = [...styles].filter(p => path.basename(p) === `${kind}.scss`).sort((a, b) => {
      const entry = path.join(root, 'src', 'editor.scss');
      return a === entry ? -1 : b === entry ? 1 : a.localeCompare(b);
    });
    if (!files.length) throw new Error(`No ${kind} styles found`);
    const css = files.map(file => sass.compile(file, {
      loadPaths: [root], style: 'compressed', silenceDeprecations: ['import', 'global-builtin', 'color-functions'],
    }).css).join('\n');
    fs.writeFileSync(path.join(root, `dist/blocks.${kind}.build.css`), css);
  }
}
build().catch(error => { console.error(error); process.exitCode = 1; });
