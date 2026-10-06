// Runs the built editor bundle against WordPress API doubles and checks that the
// latest-posts block registers from block.json with its editor and CSS.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const metadata = JSON.parse(fs.readFileSync(path.join(root, 'src/latest-posts/block.json'), 'utf8'));
const registered = new Map();
const stub = new Proxy(function () { return stub; }, { get(target, key) { return Reflect.has(target, key) ? Reflect.get(target, key) : stub; } });
const wp = {
  element: { createElement: (type, props, ...children) => ({ type, props, children }), Fragment: 'fragment' },
  blocks: { registerBlockType(name, config) { assert(!registered.has(name)); registered.set(name, config); } },
  i18n: { __: text => text },
};
for (const name of ['blockEditor', 'components', 'data', 'date', 'htmlEntities']) wp[name] = stub;
const bundle = fs.readFileSync(path.join(root, 'dist/blocks.build.js'), 'utf8');
vm.runInNewContext(bundle, { window: { wp }, wp, console }, { timeout: 5000 });

assert.deepEqual([...registered.keys()], ['alps-gutenberg-blocks/latest-posts'], 'Only the latest-posts block registers');
const block = registered.get(metadata.name);
assert.equal(typeof block.edit, 'function', 'The block has an editor');
assert.equal(block.save(), null, 'The block is rendered in PHP');
assert.equal(metadata.apiVersion, 3, 'block.json uses block API version 3');
assert(!/lodash|imgplaceholder|jQuery/.test(bundle), 'The bundle has no lodash, jQuery or third-party image URLs');

const css = fs.readFileSync(path.join(root, 'dist/blocks.editor.build.css'), 'utf8');
assert(css.includes('.wp-block-alps-gutenberg-blocks-latest-posts'), 'Editor CSS is compiled');
assert(!css.includes('$') && !/url\(/.test(css), 'Sass variables are compiled and the CSS loads no remote files');
console.log(`PASS: ${metadata.name} registers (API v${metadata.apiVersion}); bundle ${bundle.length} B, editor CSS ${css.length} B.`);
