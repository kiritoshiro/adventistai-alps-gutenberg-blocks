// Runs the built editor bundle against WordPress API doubles and checks that both
// blocks register from block.json with their editors, and checks the front-end
// files of the YouTube Channel Videos block.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const metadata = JSON.parse(fs.readFileSync(path.join(root, 'src/latest-posts/block.json'), 'utf8'));
const youtube = JSON.parse(fs.readFileSync(path.join(root, 'src/youtube-channel/block.json'), 'utf8'));
const books = JSON.parse(fs.readFileSync(path.join(root, 'src/book-showcase/block.json'), 'utf8'));
const registered = new Map();
const stub = new Proxy(function () { return stub; }, { get(target, key) { return Reflect.has(target, key) ? Reflect.get(target, key) : stub; } });
const wp = {
  element: { createElement: (type, props, ...children) => ({ type, props, children }), Fragment: 'fragment' },
  blocks: { registerBlockType(name, config) { assert(!registered.has(name)); registered.set(name, config); } },
  i18n: { __: text => text },
};
for (const name of ['blockEditor', 'components', 'data', 'date', 'htmlEntities', 'serverSideRender']) wp[name] = stub;
wp.blockEditor = { useBlockProps: () => ({}), InspectorControls: stub, BlockControls: stub };
const bundle = fs.readFileSync(path.join(root, 'dist/blocks.build.js'), 'utf8');
vm.runInNewContext(bundle, { window: { wp }, wp, console }, { timeout: 5000 });

assert.deepEqual([...registered.keys()], [metadata.name, youtube.name, books.name], 'All three blocks register, nothing else');
for (const data of [metadata, youtube, books]) {
  const block = registered.get(data.name);
  assert.equal(typeof block.edit, 'function', `${data.name} has an editor`);
  assert.equal(block.save(), null, `${data.name} is rendered in PHP`);
  assert.equal(data.apiVersion, 3, `${data.name} uses block API version 3`);
}
assert(!/lodash|imgplaceholder|jQuery/.test(bundle), 'The bundle has no lodash, jQuery or third-party image URLs');

const css = fs.readFileSync(path.join(root, 'dist/blocks.editor.build.css'), 'utf8');
assert(css.includes('.wp-block-alps-gutenberg-blocks-latest-posts') && css.includes('.alps-ytc-placeholder__form'), 'Editor CSS of both blocks is compiled');
assert(!css.includes('$') && !/url\(/.test(css), 'Sass variables are compiled and the CSS loads no remote files');
// YouTube Channel Videos front end: small, self-contained, YouTube only after a click.
const view = fs.readFileSync(path.join(root, 'dist/youtube-channel.js'), 'utf8');
const style = fs.readFileSync(path.join(root, 'dist/youtube-channel.css'), 'utf8');
assert(view.length < 6000 && style.length < 12000, `Front-end files stay small (JS ${view.length} B, CSS ${style.length} B)`);
assert(!/iframe_api|googleapis|fetch\(|XMLHttpRequest|jQuery/.test(view), 'The view script loads no YouTube API or data');
assert(view.includes('youtube-nocookie.com/embed/') && view.includes('strict-origin-when-cross-origin'), 'The player is youtube-nocookie with a referrer (avoids error 153)');
assert(!/@import|url\(|fonts\.google/.test(style), 'The stylesheet loads no fonts or remote files');
assert(style.includes('.alps-ytc') && !style.includes('$'), 'Front-end CSS is compiled');

// The front end works: a click on a card swaps the poster for a player.
const listeners = {};
const el = (props = {}) => Object.assign({ dataset: {}, classList: { toggle() {}, contains: c => (props.classes || []).includes(c) }, setAttribute() {}, removeAttribute() {}, addEventListener(type, fn) { listeners[type] = fn; }, querySelector: () => null, querySelectorAll: () => [], getBoundingClientRect: () => ({ top: 0, bottom: 100, width: 300 }) }, props);
const player = el({ replaceChildren(node) { this.child = node; } });
const cards = ['v1aaaaaaaaa', 'v2aaaaaaaaa', 'v3aaaaaaaaa'].map(id => el({ dataset: { video: id, title: id }, classes: ['alps-ytc__card'], parentElement: el() }));
const section = el({ dataset: { iframeTitle: 'Player' }, contains: () => true, querySelector: s => (s === '.alps-ytc__player' ? player : null), querySelectorAll: s => (s === '.alps-ytc__card' ? cards : []) });
cards.forEach(card => { card.closest = () => card; });
const documentStub = { readyState: 'complete', documentElement: { lang: 'lt-LT' }, querySelectorAll: () => [section], createElement: () => ({}), addEventListener() {} };
const windowStub = { matchMedia: () => ({ matches: false }), addEventListener() {}, innerHeight: 800, scrollY: 0 };
vm.runInNewContext(view, { window: windowStub, document: documentStub, URLSearchParams, Intl, Date, Math, getComputedStyle: () => ({}) }, { timeout: 5000 });
listeners.click({ target: cards[1] });
assert.equal(player.child.src, 'https://www.youtube-nocookie.com/embed/v2aaaaaaaaa?autoplay=1&playsinline=1&rel=0&playlist=v3aaaaaaaaa%2Cv1aaaaaaaaa', 'Clicking a card plays it, then the following videos');
assert.equal(player.child.referrerPolicy, 'strict-origin-when-cross-origin');
// Exercise the real editor function: opening does not mutate saved content and
// controls update only their attributes. The server preview receives the settings.
const bookDefaults = Object.fromEntries(Object.entries(books.attributes).map(([key, value]) => [key, value.default]));
const updates = [];
const treeNodes = tree => !tree || typeof tree !== 'object' ? [] : [tree, ...((tree.children || []).flat(Infinity).flatMap(treeNodes))];
let editorNodes = treeNodes(registered.get(books.name).edit({attributes: bookDefaults, setAttributes: value => updates.push(value)}));
assert.equal(updates.length, 0, 'Opening Book Showcase never changes its saved attributes');
const control = label => editorNodes.find(node => node.props && node.props.label === label);
assert(control('Category slug') && control('Show all books'), 'Content settings render');
assert.equal(control('Number of books'), undefined, 'Count is hidden while all books are shown');
control('Category slug').props.onChange('audio-knygos');
assert.equal(updates.at(-1).category, 'audio-knygos');
control('Show all books').props.onChange(false);
assert.equal(updates.at(-1).showAll, false);
editorNodes = treeNodes(registered.get(books.name).edit({attributes: {...bookDefaults, showAll: false}, setAttributes: value => updates.push(value)}));
control('Number of books').props.onChange(6);
assert.equal(updates.at(-1).count, 6);
control('Book title source').props.onChange('post');
assert.equal(updates.at(-1).titleSource, 'post');
assert(editorNodes.some(node => node.props && node.props.block === books.name && node.props.httpMethod === 'POST'), 'Server preview is configured with the real block');
const bookCss = fs.readFileSync(path.join(root, 'dist/book-showcase.css'), 'utf8');
assert(bookCss.includes('.alps-book-showcase.book-showcase-grid') && bookCss.includes('prefers-reduced-motion'), 'Book styles are scoped and respect reduced motion');
assert(!/@import|url\(/.test(bookCss), 'Book grid loads no remote assets');
assert.equal(books.attributes.category.default, 'pdf-knygos');
assert.equal(books.attributes.showAll.default, true);
if (fs.existsSync(path.join(root, 'build/alps-gutenberg-blocks'))) {
assert(fs.existsSync(path.join(root, 'build/alps-gutenberg-blocks/src/book-showcase/block.json')), 'Book metadata is packaged');
assert(fs.existsSync(path.join(root, 'build/alps-gutenberg-blocks/src/book-showcase/class-book-showcase-block.php')), 'Book renderer is packaged');
assert(fs.existsSync(path.join(root, 'build/alps-gutenberg-blocks/dist/book-showcase.css')), 'Book styles are packaged');
}
console.log(`PASS: Book Showcase plus ${metadata.name} and ${youtube.name} register (API v3); bundle ${bundle.length} B, editor CSS ${css.length} B, YouTube front end JS ${view.length} B + CSS ${style.length} B.`);
