## ALPS Gutenberg Blocks (adventistai.lt fork)

A stripped-down fork of [adventistchurch/alps-gutenberg-blocks](https://github.com/adventistchurch/alps-gutenberg-blocks)
for the ALPS theme on adventistai.lt. It provides these blocks:

- **ALPS Latest Posts** (`alps-gutenberg-blocks/latest-posts`), the only block of the original 18 that appears in the
  site's published content. It is rendered in PHP with the ALPS theme's markup and classes, so it has no front-end CSS
  or JavaScript of its own.
- **YouTube Channel Videos** (`alps-gutenberg-blocks/youtube-channel`), a channel's newest videos: a player and a
  scrollable row. See [YouTube Channel Videos](#youtube-channel-videos).

- **Book Showcase** (`alps-gutenberg-blocks/book-showcase`), the PDF book cover grid with editable settings. See [Book Showcase](#book-showcase).
- **Newspaper Posts** (`alps-gutenberg-blocks/newspaper-posts`), newest posts with medium thumbnails, optional dates and cleaned excerpts. See [Newspaper Posts](#newspaper-posts).
- **Podcast Season** (`alps-gutenberg-blocks/podcast-season`), one season of a podcast from its RSS feed: an audio player and the episode list. See [Podcast Season](#podcast-season).

All are rendered in PHP. The editor script and stylesheet, and the front-end files of the blocks that have them, are built into `dist/`.

Version 3.0.0 removed the other 17 blocks. Content made with them still shows its saved HTML on the front end, but
the editor can no longer edit it as those blocks. See `CHANGELOG.md`.

## Layout

| Path | Purpose |
|------|---------|
| `plugin.php` | Plugin header, version, bootstrap |
| `updater.php` | Updates from this repository's GitHub releases |
| `src/init.php` | Registers the editor assets and the block |
| `src/latest-posts/block.json` | Block definition and attributes (block API 3) |
| `src/latest-posts/class-latest-posts-block.php` | Server-side render and attribute validation |
| `src/latest-posts/edit.js`, `editor.scss` | Block editor UI |
| `src/podcast-season/` | Podcast Season: `block.json`, feed parsing, cache and render (`class-podcast-season-block.php`), editor (`edit.js`), front end (`view.js`, `style.scss`) |
| `src/youtube-channel/` | YouTube Channel Videos: `block.json`, server render and API cache (`class-youtube-channel-block.php`), editor (`edit.js`), front end (`view.js`, `style.scss`) |
| `src/index.js` | Editor entry that registers all blocks |
| `languages/` | Lithuanian strings for the server-rendered texts (`.l10n.php`, WordPress 6.5+) |
| `devtools/build.js` | Builds `dist/`, and with `--package` the release folder |
| `tests/` | Bundle test (Node) and render test (PHP) |

## Development

Requires Node.js 22 or newer and PHP 7.4 or newer.

```
npm ci
npm run build     # dist/blocks.build.js and dist/blocks.editor.build.css
npm run dev       # rebuild when src/ changes
npm test          # bundle test
php tests/render-newspaper-posts.php
php tests/render-latest-posts.php
php tests/render-youtube-channel.php
php tests/render-book-showcase.php
```

To try it locally, run `npm run package` and copy or link `build/alps-gutenberg-blocks` into `wp-content/plugins`.

The only npm packages are the build tools esbuild and Sass. WordPress provides the editor packages at runtime.

## YouTube Channel Videos

The block shows a channel's newest videos. Nothing loads from YouTube until a visitor presses play; then a
youtube-nocookie.com player plays that video and continues with the following ones in the list.

- **Settings:** channel (link such as `https://www.youtube.com/@TrijuAngeluStudija`, `@handle` or `UC…` ID), title
  (defaults to the channel name), number of videos (1–25), "Leave out Shorts" (on by default: every video of
  3 minutes or less, because the API does not mark Shorts), the main website (shown under the title as its address,
  e.g. `example.lt`, or as custom link text) and Facebook/Instagram/TikTok/X profile links.
- **API key:** Settings → Media → "YouTube Data API key", or `define( 'ALPS_YOUTUBE_API_KEY', '…' );`. If both are
  empty, the WP YouTube plugin's key (`WPY_YOUTUBE_API_KEY` or its setting) is used. Requests come from the server, so
  the key must not have a website (HTTP referrer) restriction; restrict it to the YouTube Data API v3 instead (and
  optionally to the server's IP address). The key is never sent to browsers.
- **Caching:** the list is fetched on the first render (one `channels`, then `playlistItems` and `videos` per page of 50
  uploads) and stored in a non-autoloaded option. After an hour it is refreshed by WP-Cron while the old list is still
  shown. A failed refresh keeps the old list and retries after 10 minutes; a channel with no list yet waits
  10 minutes between attempts.
- **Thumbnails:** after a list is fetched, WP-Cron copies its thumbnails from `i.ytimg.com` into
  `wp-content/uploads/alps-ytc/` (40 per run), so the page loads them from the site with its long cache lifetime.
  Until a copy exists YouTube's address is used. Copies unused for 90 days are removed.
- **Without a channel, a key or videos** editors see a short note in place of the block; visitors see nothing.

## Book Showcase

Defaults match the live `/pdf-knygos/` snippet: all published posts in `pdf-knygos`, sorted by post title ascending; featured image captions as titles (falling back to post titles); 4/3/2 columns above 1024/768/480px and one centered 80% card on phones; 1.5em gap and 1400px maximum width.

The sidebar controls category slug, show all or 1–100 books, sorting by title/date/modified, direction, three column counts, gap, maximum width, accent color, title visibility/source, animations and empty message. A blank category never lists all site posts. Covers use WordPress responsive images; missing covers use a local placeholder. Reduced motion is respected, keyboard focus stays visible, and hovered title text uses a darker accent for contrast. The stylesheet is scoped to the block and loads only on pages rendering it; there is no front-end JavaScript.

To replace the old snippet after installing this plugin version, edit **PDF Knygos** (post ID 8909) in Gutenberg, remove its snippet/shortcode, insert **Book Showcase**, retain defaults, preview, and update. Do not leave both versions on the page. This code change does not modify production content.

## Releasing

1. Set the new version in `plugin.php` (the header and `ALPS_GUTENBERG_VERSION`) and in `package.json`. Run `npm install`
   so `package-lock.json` matches, and add a `## [X.Y.Z]` section at the top of `CHANGELOG.md`.
2. Merge to `master`, then push a `vX.Y.Z` tag on that commit.
3. `publish.yml` runs the full security gate on the tag, builds the package (refusing if any version string disagrees with
   the tag), and creates the GitHub release with `alps-gutenberg-blocks-vX.Y.Z.zip`.

## Updates

Installed copies update from this repository's GitHub releases (`updater.php`). The `Update URI` header stops WordPress.org
offering a same-named plugin, and upstream's `cdn.adventist.org` is never used. The latest non-draft, non-prerelease
`vX.Y.Z` release with an `alps-gutenberg-blocks-vX.Y.Z.zip` asset is offered. No token is needed. A fine-grained, read-only
token is optional and only raises the GitHub API rate limit:

```php
define( 'ALPS_GUTENBERG_GITHUB_TOKEN', 'github_pat_...' );
```

A site running upstream's plugin (2.x, which updates from the CDN) must install the first release of this fork by hand once.

## Newspaper Posts

`alps-gutenberg-blocks/newspaper-posts` displays five newest published posts by default. Set a category slug (empty means all), a count from 1–50, and date/excerpt visibility in the sidebar. Featured images use WordPress medium thumbnails. Excerpts strip HTML, decode entities, remove trailing `[...]`, `[…]`, `...`, `…` or `Continued`, and append one ellipsis. Protected excerpts are hidden. The original shortcode markup classes are preserved inside a scoped wrapper; its small stylesheet is loaded only with the block. No front-end JavaScript. The existing `[posts_list]` shortcode is not registered or replaced.

## External Posts Aggregator

Add **External Posts Aggregator** in the block inserter. Enter one RSS/Atom URL per line (or separate URLs with commas). Bare WordPress homepages use /feed/ automatically. Each source gets its own heading and newest posts; sources are not merged into one chronological list. Up to eight feeds, 1–20 posts each; list/cards, 1–100 excerpt words, 40–500px thumbnails and a 1–1440 minute cache. Errors are escaped and details are administrator-only. Cold requests allow at most five seconds per feed; cached pages reuse the result. The stylesheet loads only where the block or shortcode renders; no frontend JavaScript.

Example sources: https://sveikas.info/feed/, https://tavoseimai.lt/feed/, https://dievozodis.lt/feed/, https://malonetau.lt/feed/.

Migration: update this plugin, replace the Shortcode block with External Posts Aggregator, and copy the same feed URLs/settings. Existing [external_posts] shortcodes also work after deactivating the standalone External Posts Aggregator plugin. If the old plugin remains active, it owns its shortcode and still loads its global CSS. This block always uses its own renderer and scoped styles. Attribution: Darius, External Posts Aggregator 4.1.3, GPL-2.0-or-later.

## Podcast Season

Add **Podcast Season** in the block inserter, then enter the podcast's RSS feed and a season number. Spotify for Creators feeds look like `https://anchor.fm/s/…/podcast/rss` (the 5 minutės podcast: `https://anchor.fm/s/430eecd0/podcast/rss`, season 3 is "Marijos Sūnaus gyvenimas"). Episodes are the feed items whose `itunes:season` matches, ordered by `itunes:episode` (then date); trailers and items without an absolute http(s) audio enclosure are skipped. A season keeps its newest 200 episodes. New episodes appear by themselves within an hour.

Settings: title (empty: the podcast's name), first or newest episode first, episodes shown before a native "Show all" (`<details>`) list (1–100), text removed from the start of titles (e.g. the series name "Marijos Sūnaus gyvenimas."; with numbers shown, a leading "1 –" is dropped too), episode numbers, descriptions (5–100 words), cover, and playing the next episode automatically.

Loading:
- One `<audio controls preload="none">` per block: no audio bytes until a visitor presses play. Episodes are links to their audio files (`rel="nofollow"`), so they also play without JavaScript; each reads "Play: number title duration" to screen readers ("Pause:" while playing). `view.js` (2 KB, deferred, footer) plays them in the block's player, marks the current one, moves to the next at the end, pauses other podcast blocks, and fills the phone's lock-screen controls (Media Session).
- The season list is cached in a non-autoloaded option (`alps_gb_pod_*`, ~1 KB per episode at most) and refreshed by WP-Cron after an hour while the stale list is served. Only the first render of a feed and season fetches it (`wp_safe_remote_get`, 10 s, 10 MB cap); a failure is remembered for 10 minutes and an existing list is kept.
- The cover (the season's first episode artwork, else the podcast's) is never loaded from the feed host. WP-Cron saves a 240 px square copy in `uploads/alps-podcast/` as AVIF, WebP or JPEG (whatever the server's image editor writes); until then the block shows no cover. A cover that cannot be saved (missing, over 8 MB, no image editor) is tried again after a day, not on every view. Saving a cover removes copies older than 90 days; a page still showing one makes it again.
- The stylesheet (4 KB) and script load only where the block renders. Problems are shown to editors only.
