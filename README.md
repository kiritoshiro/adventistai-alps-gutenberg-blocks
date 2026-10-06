## ALPS Gutenberg Blocks (adventistai.lt fork)

A stripped-down fork of [adventistchurch/alps-gutenberg-blocks](https://github.com/adventistchurch/alps-gutenberg-blocks)
for the ALPS theme on adventistai.lt. It provides one block, **ALPS Latest Posts**
(`alps-gutenberg-blocks/latest-posts`). That is the only block of the original 18 that appears in the site's published content.

The block is rendered in PHP (`src/latest-posts/class-latest-posts-block.php`) with the ALPS theme's markup and
classes, so the plugin ships no front-end CSS or JavaScript. The editor script and stylesheet are built into `dist/`.

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
```

To try it locally, run `npm run package` and copy or link `build/alps-gutenberg-blocks` into `wp-content/plugins`.

The only npm packages are the build tools esbuild and Sass. WordPress provides the editor packages at runtime.

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
