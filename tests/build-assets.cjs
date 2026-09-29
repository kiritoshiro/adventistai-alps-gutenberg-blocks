const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const registered = new Map();
const createElement = (type, props, ...children) => ({ type, props, children });
const wp = {
  element: { Component: class {}, createElement, Fragment: 'fragment' },
  blocks: { registerBlockType(name, config) { assert(!registered.has(name)); registered.set(name, config); } },
  i18n: { __: text => text, _x: text => text, sprintf: text => text },
};
const stub = new Proxy(function () { return stub; }, { get(target, key) { return Reflect.has(target, key) ? Reflect.get(target, key) : stub; } });
for (const name of ['apiFetch','blockEditor','components','data','date','htmlEntities','primitives','url','keycodes','richText','compose','editor','hooks']) wp[name] = stub;
const window = { wp };
// Explicit properties are required for CommonJS named-import interop.
for (const name of ['withNotices','withSelect','withDispatch','compose','withInstanceId']) {
  for (const module of ['components','data','compose']) {
    if (!Object.hasOwn(wp[module], name)) wp[module][name] = () => Component => Component;
  }
}
try { vm.runInNewContext(fs.readFileSync(path.join(root, 'dist/blocks.build.js'), 'utf8'), { window, wp, console, lodash: require('lodash') }, { timeout: 5000 }); } catch (error) { console.error(error.message); process.exit(1); }
const expected = [...fs.readFileSync(path.join(root, 'src/blocks.js'), 'utf8').matchAll(/blocks\/([^/]+)\/block\.js/g)].map(m => m[1]);
assert.equal(registered.size, expected.length, 'All imported blocks register');
for (const [name, config] of registered) {
  assert.match(name, /^alps-gutenberg-blocks\//);
  assert(config.edit, `${name} has an editor`);
  assert(config.save, `${name} has a save implementation`);
}
for (const kind of ['style','editor']) {
  const css = fs.readFileSync(path.join(root, `dist/blocks.${kind}.build.css`), 'utf8');
  assert(css.length > 1000, `${kind} CSS is populated`);
  assert(!css.includes('$space'), 'Sass variables are compiled');
}
console.log(`PASS: ${registered.size} blocks register; editor/frontend styles compiled.`);
