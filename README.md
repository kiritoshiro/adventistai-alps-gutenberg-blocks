## ALPS Gutenberg Blocks (adventistai.lt fork)

A stripped-down fork of [adventistchurch/alps-gutenberg-blocks](https://github.com/adventistchurch/alps-gutenberg-blocks)
for the ALPS theme on adventistai.lt. It provides two blocks:

- **ALPS Latest Posts** (`alps-gutenberg-blocks/latest-posts`), the only block of the original 18 that appears in the
  site's published content. It is rendered in PHP with the ALPS theme's markup and classes, so it has no front-end CSS
  or JavaScript of its own.
- **YouTube Channel Videos** (`alps-gutenberg-blocks/youtube-channel`), a channel's newest videos: a player and a
  scrollable row. See [YouTube Channel Videos](#youtube-channel-videos).

Both are rendered in PHP. The editor script and stylesheet, and the YouTube block's front-end files, are built into `dist/`.

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
| `src/youtube-channel/` | YouTube Channel Videos: `block.json`, server render and API cache (`class-youtube-channel-block.php`), editor (`edit.js`), front end (`view.js`, `style.scss`) |
| `src/index.js` | Editor entry that registers both blocks |
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
php tests/render-latest-posts.php
php tests/render-youtube-channel.php
```

To try it locally, run `npm run package` and copy or link `build/alps-gutenberg-blocks` into `wp-content/plugins`.

The only npm packages are the build tools esbuild and Sass. WordPress provides the editor packages at runtime.

## YouTube Channel Videos

The block shows a channel's newest videos. Nothing loads from YouTube until a visitor presses play; then a
youtube-nocookie.com player plays that video and continues with the following ones in the list.

- **Settings:** channel (link such as `https://www.youtube.com/@TrijuAngeluStudija`, `@handle` or `UC…` ID), title
  (defaults to the channel name), number of videos (1–25), "Leave out Shorts" (on by default: every video of
  3 minutes or less, because the API does not mark Shorts) and Facebook/Instagram/TikTok/X profile links.
- **API key:** Settings → Media → "YouTube Data API key", or `define( 'ALPS_YOUTUBE_API_KEY', '…' );`. If both are
  empty, the WP YouTube plugin's key (`WPY_YOUTUBE_API_KEY` or its setting) is used. Requests come from the server, so
  the key must not have a website (HTTP referrer) restriction; restrict it to the YouTube Data API v3 instead (and
  optionally to the server's IP address). The key is never sent to browsers.
- **Caching:** the list is fetched on the first render (one `channels`, then `playlistItems` and `videos` per page of 50
  uploads) and stored in a non-autoloaded option. After an hour it is refreshed by WP-Cron while the old list is still
  shown. A failed refresh keeps the old list and retries after 10 minutes; a channel with no list yet waits
  10 minutes between attempts.
- **Without a channel, a key or videos** editors see a short note in place of the block; visitors see nothing.

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
