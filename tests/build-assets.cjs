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
const newspaper = JSON.parse(fs.readFileSync(path.join(root, 'src/newspaper-posts/block.json'), 'utf8'));
const external = JSON.parse(fs.readFileSync(path.join(root, 'src/external-posts/block.json'), 'utf8'));
const podcast = JSON.parse(fs.readFileSync(path.join(root, 'src/podcast-season/block.json'), 'utf8'));
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
vm.runInNewContext(bundle, { window: { wp }, wp, console, URL }, { timeout: 5000 });

assert.deepEqual([...registered.keys()], [metadata.name, youtube.name, books.name, newspaper.name, external.name, podcast.name], 'All six blocks register, nothing else');
for (const data of [metadata, youtube, books, newspaper, external, podcast]) {
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
// A smooth snap of the video row during layout counts as a scroll and ends Chrome's LCP measurement (PageSpeed NO_LCP).
assert(!/(^|[^-])scroll-behavior\s*:\s*smooth/.test(style), 'YouTube row has no CSS smooth scrolling');
assert(view.includes('prefers-reduced-motion'), 'YouTube arrows respect reduced motion');
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
console.log(`PASS: ${metadata.name}, ${youtube.name}, ${books.name} ${newspaper.name} and ${external.name} register (API v3); bundle ${bundle.length} B, editor CSS ${css.length} B, YouTube front end JS ${view.length} B + CSS ${style.length} B.`);

const externalDefaults = Object.fromEntries(Object.entries(external.attributes).map(([k,v]) => [k,v.default]));
const externalUpdates = [];
const externalNodes = treeNodes(registered.get(external.name).edit({attributes: {...externalDefaults,feeds:'https://news.example/feed/',layout:'cards'},setAttributes:v=>externalUpdates.push(v)}));
assert.equal(externalUpdates.length,0,'Opening external feeds preserves settings');
for(const [label,key,value] of [['Feed URLs','feeds','https://other.example/feed/'],['Posts per feed','number',4],['Cache duration (minutes)','cacheMinutes',10],['Layout','layout','list'],['Excerpt length (words)','excerptLength',18],['Thumbnail size (pixels)','imageSize',100],['Administrator diagnostics','debug',true]]) {
 externalNodes.find(n=>n.props?.label===label).props.onChange(value);
 assert.equal(externalUpdates.at(-1)[key],value,label+' updates the correct setting');
}
assert(externalNodes.some(n=>n.props?.block===external.name && n.props.httpMethod==='POST'),'External feeds server preview');
for(const p of ['src/external-posts/block.json','src/external-posts/class-external-posts-block.php','dist/external-posts.css']) assert(fs.existsSync(path.join(root,'build/alps-gutenberg-blocks',p)),'Packaged '+p);
console.log('PASS: External Posts Aggregator editor controls and packaged assets.');

// Podcast Season: editor controls, and a front end that loads no audio before play.
const podcastDefaults = Object.fromEntries(Object.entries(podcast.attributes).map(([k, v]) => [k, v.default]));
const podcastUpdates = [];
let podcastNodes = treeNodes(registered.get(podcast.name).edit({ attributes: podcastDefaults, setAttributes: v => podcastUpdates.push(v) }));
assert.equal(podcastUpdates.length, 0, 'Opening Podcast Season preserves settings');
assert(!podcastNodes.some(n => n.props?.block === podcast.name), 'No server preview before a feed address is entered');
podcastNodes = treeNodes(registered.get(podcast.name).edit({ attributes: { ...podcastDefaults, feedUrl: 'https://anchor.fm/s/430eecd0/podcast/rss', season: 3, showDescription: true }, setAttributes: v => podcastUpdates.push(v) }));
for (const [label, key, value, expected] of [
  ['Podcast RSS feed', 'feedUrl', 'https://other.example/rss'], ['Season', 'season', '4', 4], ['Season', 'season', '', 0], ['Season', 'season', '5000', 999], ['Season', 'season', '-3', 0],
  ['Title', 'title', 'Marijos Sūnaus gyvenimas'], ['Order', 'order', 'desc'], ['Episodes shown before “Show all”', 'visible', 20],
  ['Remove from the start of titles', 'removePrefix', 'Marijos Sūnaus gyvenimas.'], ['Show episode numbers', 'showNumbers', false],
  ['Show descriptions', 'showDescription', false], ['Description length (words)', 'descriptionWords', 40], ['Show cover', 'showCover', false],
  ['Play the next episode automatically', 'autoNext', false],
]) {
  podcastNodes.find(n => n.props?.label === label).props.onChange(value);
  assert.equal(podcastUpdates.at(-1)[key], expected === undefined ? value : expected, label + ' updates the correct setting');
}
assert(podcastNodes.some(n => n.props?.block === podcast.name && n.props.httpMethod === 'POST'), 'Podcast Season server preview');
assert.equal(podcast.attributes.order.default, 'asc', 'Series start at episode 1');

const podView = fs.readFileSync(path.join(root, 'dist/podcast-season.js'), 'utf8');
const podStyle = fs.readFileSync(path.join(root, 'dist/podcast-season.css'), 'utf8');
assert(podView.length < 3000 && podStyle.length < 6000, `Podcast front-end files stay small (JS ${podView.length} B, CSS ${podStyle.length} B)`);
assert(!/fetch\(|XMLHttpRequest|jQuery|import\(|\.load\(\)/.test(podView), 'The podcast script fetches nothing itself');
assert(!/@import|url\(|fonts\.google/.test(podStyle) && podStyle.includes('.alps-pod') && !podStyle.includes('$'), 'Podcast CSS is compiled and loads no remote files');

// Drive the real view script: a click plays that episode in the one player; the end moves on.
function podcastSection(autoNext) {
  const handlers = {};
  const audio = {
    src: 'https://cdn.example/e1.m4a', paused: true, plays: 0, listeners: {},
    addEventListener(type, fn) { this.listeners[type] = fn; },
    play() { this.paused = false; this.plays++; if (this.listeners.play) this.listeners.play(); return Promise.resolve(); },
    pause() { this.paused = true; if (this.listeners.pause) this.listeners.pause(); },
  };
  const classes = () => { const set = new Set(); return { add: c => set.add(c), remove: c => set.delete(c), contains: c => set.has(c) }; };
  const details = { open: false };
  const links = [1, 2, 3].map(n => {
    const action = { textContent: 'Play:' };
    return {
      href: `https://cdn.example/e${n}.m4a`, dataset: { title: `Episode ${n}` }, classList: classes(), attrs: {}, action,
      setAttribute(k, v) { this.attrs[k] = v; }, removeAttribute(k) { delete this.attrs[k]; },
      closest(selector) { return selector === 'details' ? (n === 3 ? details : null) : this; },
      querySelector: selector => (selector === '.alps-pod__action' ? action : null),
    };
  });
  links[0].classList.add('is-active');
  const now = { textContent: 'Episode 1' };
  const parts = { '.alps-pod__audio': audio, '.alps-pod__now': now, '.alps-pod__title': { textContent: 'Series' }, '.alps-pod__cover': { src: 'https://site.example/cover.avif' } };
  const section = {
    dataset: { play: 'Paleisti:', pause: 'Pristabdyti:', ...(autoNext ? { autoNext: '' } : {}) }, classList: classes(), contains: () => true,
    addEventListener(type, fn) { handlers[type] = fn; },
    querySelector: selector => parts[selector] || null,
    querySelectorAll: selector => (selector === '.alps-pod__episode' ? links : []),
  };
  const click = (link, extra = {}) => {
    let prevented = false;
    handlers.click({ target: link, button: 0, preventDefault() { prevented = true; }, ...extra });
    return prevented;
  };
  return { audio, links, now, details, section, click };
}
function runPodcastView(sections) {
  const session = { metadata: null, handlers: {}, setActionHandler(name, fn) { this.handlers[name] = fn; } };
  const documentStub = { readyState: 'complete', querySelectorAll: () => sections.map(p => p.section), addEventListener() {} };
  vm.runInNewContext(podView, { window: { MediaMetadata: function (data) { Object.assign(this, data); } }, navigator: { mediaSession: session }, document: documentStub }, { timeout: 5000 });
  return session;
}
let page = podcastSection(true);
const session = runPodcastView([page]);
assert(page.click(page.links[1]), 'An episode click is handled in the page');
assert.equal(page.audio.src, 'https://cdn.example/e2.m4a', 'The clicked episode loads into the one player');
assert(page.audio.plays === 1 && page.now.textContent === 'Episode 2' && page.links[1].attrs['aria-current'] === 'true' && !page.links[0].classList.contains('is-active'), 'It plays and is marked as current');
assert(page.section.classList.contains('is-playing'), 'The block shows it is playing');
assert(page.links[1].action.textContent === 'Pristabdyti:' && page.links[0].action.textContent === 'Paleisti:', 'Screen readers hear "Pause:" on the playing episode only');
assert(session.metadata.title === 'Episode 2' && session.metadata.album === 'Series' && session.metadata.artwork[0].src === 'https://site.example/cover.avif', 'Lock-screen controls show the episode');
assert(typeof session.handlers.previoustrack === 'function' && typeof session.handlers.nexttrack === 'function', 'Previous and next on the lock screen');
session.handlers.nexttrack();
assert(page.audio.src === 'https://cdn.example/e3.m4a' && page.details.open, 'Lock-screen "next" plays the next episode');
assert.equal(session.handlers.nexttrack, null, 'No "next" on the last episode');
page.click(page.links[2]);
assert(page.audio.paused && !page.section.classList.contains('is-playing') && page.links[2].action.textContent === 'Paleisti:', 'Clicking the playing episode pauses it');
page.click(page.links[1]);
page.audio.listeners.ended();
assert(page.audio.src === 'https://cdn.example/e3.m4a', 'At the end the next episode plays');
assert(!page.click(page.links[0], { ctrlKey: true }), 'Ctrl+click still opens the file');
page = podcastSection(false);
runPodcastView([page]);
page.click(page.links[0]);
page.audio.listeners.ended();
assert.equal(page.audio.src, 'https://cdn.example/e1.m4a', 'No autoplay of the next episode when it is turned off');
const first = podcastSection(true);
const second = podcastSection(true);
runPodcastView([first, second]);
first.click(first.links[0]);
second.click(second.links[1]);
assert(first.audio.paused && !second.audio.paused, 'Two blocks on a page: starting one pauses the other');
for (const p of ['src/podcast-season/block.json', 'src/podcast-season/class-podcast-season-block.php', 'dist/podcast-season.js', 'dist/podcast-season.css']) {
  assert(fs.existsSync(path.join(root, 'build/alps-gutenberg-blocks', p)), 'Packaged ' + p);
}
console.log(`PASS: Podcast Season editor controls, player script (JS ${podView.length} B, CSS ${podStyle.length} B) and packaged assets.`);
