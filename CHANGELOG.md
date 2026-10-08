# Changelog
A record of the changes made to `ALPS Gutenberg Blocks`.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.2.2]
### Changed
- YouTube Channel Videos: thumbnails are served from the site. YouTube serves them with a 2-hour cache lifetime (PageSpeed "Use efficient cache lifetimes": 139 KiB on adventistai.lt) from another host. After a list is fetched, WP-Cron copies its thumbnails into `uploads/alps-ytc/` (up to 640 px, and the first video's 1280 px for wide blocks), 40 per run, keeping only JPEGs from `i.ytimg.com` under names built from the validated video ID. Until a copy exists the page uses YouTube's address and asks for the copy; lists cached by earlier versions are copied on their next view. Copies unused for 90 days are removed.

## [3.2.1]
### Fixed
- YouTube Channel Videos: pages with the block no longer lose their Largest Contentful Paint (PageSpeed desktop reported `NO_LCP` and no performance score for adventistai.lt). The video row snaps to its first card while the page lays out (it moves 4 px), and with `scroll-behavior: smooth` that snap was an animated scroll, which Chrome treats as a scroll that ends LCP measurement. The row no longer sets smooth scrolling in CSS; the arrows and the jump to the player still scroll smoothly from the script, and instantly for visitors who prefer reduced motion. Checked on a copy of the live homepage: no LCP before, LCP reported at 1350 and 375 px after.

## [3.2.0]
### Added
- Dynamic Newspaper Posts block with category slug, bounded post count, date/excerpt controls, server preview, medium thumbnails and scoped styles. Excerpts preserve the original shortcode cleanup; protected excerpts remain hidden.
- Add the dynamic Book Showcase block, matching the PDF books grid with category, count, sorting, responsive columns, gap, width, accent, caption/title and animation settings.
- Keep book links keyboard accessible, respect reduced motion, use responsive WordPress cover images and a local missing-cover fallback.

## [3.1.5]
### Fixed
- Updates: "Check again" on Dashboard → Updates now finds a new release straight away. WordPress' button only forces its own core check, so this plugin kept answering from its one-hour release cache; on `update-core.php?force-check=1` the cache is now dropped for users who can update plugins.

## [3.1.4]
### Changed
- YouTube Channel Videos: the poster offers YouTube's 1280 px image only when the block is set to wide or full width. In a content column 640 px is enough (the poster shows at ~536 px on desktop and ~300 CSS px on phones), so phones with 3x screens and PageSpeed no longer download the ~250-300 KB image.

## [3.1.3]
### Fixed
- YouTube Channel Videos: text contrast meets WCAG AA. The "Watch on YouTube" link is darker gold (#8a6508, 5.3:1 on white) and dates and the list label are darker grey (#556274, 5.8:1 on the card colour); PageSpeed flagged 3.25:1 and 4.44:1.
- YouTube Channel Videos: no forced reflow on page load. The row's arrows and counter are measured in an animation frame (first through a ResizeObserver, after layout), with every layout read before any change; PageSpeed listed the start-up measurement as forced reflow.

## [3.1.2]
### Fixed
- YouTube Channel Videos inside ALPS page content: the social icons no longer overlap (the theme's -10px list indent squeezed them), the line under the player keeps its space below (the theme's `p:last-of-type` rule removed it), and the video row keeps its padding, so the active card's ring and the scrollbar are not cramped.

### Changed
- Slightly more space between the parts of the block: header, player, title line, list header, cards and card text.

## [3.1.1]
### Fixed
- YouTube Channel Videos: a key with a website (HTTP referrer) restriction for this site now works. API requests send the site's address as the referrer.
- A failed fetch is no longer remembered for 10 minutes after the API key changes; a new key is tried at once.

### Added
- The editor note for a failed fetch names the key that was used (its last four characters and where it is set) and, for a referrer error, how to fix the key's website restriction. Settings → Media shows which key is in use.

## [3.1.0]
New block: **YouTube Channel Videos** (`alps-gutenberg-blocks/youtube-channel`), replacing the front page's "Trijų Angelų Studija" code snippet.

### Added
- A player with the channel's newest video and a scrollable row of the next ones, with the channel's social links. Settings: channel (link, @handle or ID), title (defaults to the channel name), number of videos (up to 25) and "Leave out Shorts" (videos of 3 minutes or less, as before).
- Nothing loads from YouTube until a visitor presses play. Then a youtube-nocookie.com player plays that video and continues with the following ones. A "Watch on YouTube" link stays under the player.
- The video list is fetched on the server with the YouTube Data API and cached for an hour; stale lists are refreshed by WP-Cron in the background, and a failed refresh keeps the old list. Visitors make no API requests, and the API key never appears in the page.
- The API key is set under Settings → Media, or with the `ALPS_YOUTUBE_API_KEY` constant. If neither is set, the WP YouTube plugin's key is used.
- Thumbnails come in responsive sizes (up to 480 px wide in the row) instead of 1280 px images. The block's CSS (9 KB) and script (4 KB) load only on pages that use it, and it loads no web fonts.
- Lithuanian translations of the front-end and settings texts (`languages/alps-gutenberg-blocks-lt_LT.l10n.php`, WordPress 6.5+). Video dates are shown relative ("prieš 3 dienas") in the page language.
- Editors see a short note when the channel or API key is missing or the videos cannot be loaded; visitors see nothing.

## [3.0.0]
This fork keeps only the ALPS Latest Posts block, the one block adventistai.lt uses.

### Removed
- The other 17 blocks (accordion, blockquote, content block/expand/read-more/show-more/step, CTA, gallery, highlight blocks, highlighted paragraph, image 2-up/breakout, media block/testimonies/testimony, split content). Content made with them still shows its saved HTML on the front end, but they can no longer be edited as blocks.
- The front-end script (`src/front.js`, which loaded jQuery on every page) and the front-end stylesheet. The ALPS theme already provides those styles.
- The de/es/ko/ru translations and i18n tooling, Composer, the CDN/SFTP release path, `plugin.json` and the custom tags REST route (`/alps-gutenberg-blocks/latest-posts/tags`, which was public).
- lodash, classnames and nine build/release npm packages.

### Changed
- The block is defined in `block.json` (block API version 3), and the editor is a function component using current WordPress APIs.
- Updates come from this repository's GitHub releases, and the `Update URI` header stops WordPress.org offering a same-named plugin.
- Releases are built by `devtools/build.js`, which checks that all version strings and the tag agree.
- New "Button label" setting. The editor no longer resets the label to "Read More" each time it opens.

### Fixed
- Block attributes are validated: at most 100 posts (`-1` used to mean all posts), `asc`/`desc` and `date`/`title` only, numeric category and tag IDs only, sanitized custom classes.
- Password-protected posts no longer show an excerpt.
- Excerpts no longer show the current page's content, and no longer print double-escaped entities such as `&amp;hellip;`.
- Image URLs and alt text are escaped for attributes, and missing image sizes fall back to `large` instead of raising PHP warnings.
- The Yoast primary category is read for each listed post, not for the current page.
- An empty "see all" link is no longer printed.
- The editor no longer loads a placeholder image from a third-party site.

## [2.1.12]
### Fix
- Alps latest-post block issue [#799](https://github.com/adventistchurch/alps-wordpress/issues/799)

## [2.1.11]
### Fixed
- German language support for Gutenberg Blocks [#549](https://github.com/adventistchurch/alps-gutenberg-blocks/pull/108);

## [2.1.10]
### Fixed
- Fix plugin build process.

## [2.1.9]
### Update
- German language support for Gutenberg Blocks [#549](https://github.com/adventistchurch/alps-gutenberg-blocks/pull/108);
- Add permission_callback for register_rest_route[#109](https://github.com/adventistchurch/alps-gutenberg-blocks/pull/109).

## [2.1.8]
### Update
- New Blocks used on Adventist.org Homepage (Content-read-more, Content-step, Highlights-blocks, aplit-content).
- Update Media-block.
- NOTE! - Changes From NAD repository.

## [2.1.7]
### Fixed
- Fixed ALPS Blockquote empty citation field (remove previous logic).
- [#87](https://app.zenhub.com/workspaces/alps---core-and-wp-583365a5f9e6361b5cc5f5f6/issues/gh/adventistchurch/alps-gutenberg-blocks/87)

## [2.1.6]
### Fixed
- Fixed ALPS Blockquote empty citation field (add new rule).
- [#87](https://app.zenhub.com/workspaces/alps---core-and-wp-583365a5f9e6361b5cc5f5f6/issues/gh/adventistchurch/alps-gutenberg-blocks/87)

## [2.1.5]
### Fixed
- Fixed ALPS Blockquote color for blockquote.
- Fixed ALPS Blockquote empty citation field.
- [#87](https://app.zenhub.com/workspaces/alps---core-and-wp-583365a5f9e6361b5cc5f5f6/issues/gh/adventistchurch/alps-gutenberg-blocks/87)

## [2.1.4]
### Fixed
- Fixed ALPS Content Show More block Upload Issue. Check media.
- Fixed ALPS Image Breakout block Upload Issue. Check media. 
- [#77](https://github.com/adventistchurch/alps-gutenberg-blocks/issues/77)

## [2.1.3]
### Fixed
- Fixed ALPS CTA block Upload Issue. Check media. [#77](https://github.com/adventistchurch/alps-gutenberg-blocks/issues/77)

## [2.1.2]
### Fixed
- Fixed CTA component for resizing image. [#77](https://github.com/adventistchurch/alps-gutenberg-blocks/issues/77)

## [2.1.1]
### Fixed
- Fixed CTA component in the uploading image part. [#77](https://github.com/adventistchurch/alps-gutenberg-blocks/issues/77)

## [2.1.0]
### Added
- Added support for Korean internationalization.


## [2.0.1]
### Fixed
- Fixed the display of html in descriptions. [#69](https://github.com/adventistchurch/alps-gutenberg-blocks/issues/69)


## [2.0.0]
### Added
- Added: Major rewrite of the plugin to provide a consistency in the code of how the blocks are written and give a consistent user-interface for the edit side. [#64](https://github.com/adventistchurch/alps-gutenberg-blocks/pull/64)


## [1.9.4]
### Fixed
- Fixed: Incorrectly spelled class name on the Testimonies Media block.
- Fixed: Refactored code to make the blocks more consistent. [#52](https://github.com/adventistchurch/alps-gutenberg-blocks/pull/52)


## [1.9.1]
### Fixed
- HOTFIX: Added missing front.js file.


## [1.9.0]
### Added
- Added the Inline Sidebar block that creates a collapsible block inside the page. [#511](https://github.com/adventistchurch/alps-wordpress/issues/511)


## [1.8.1]
### Fixed
- Version increment.


## [1.8.0]
### Added 
- ALPS Media Block adds a new block component that supports an image along with text beside or below. [#523](https://github.com/adventistchurch/alps-wordpress/issues/523)
- Added Spanish localization to the plugin. (es_US)


## [1.7.0]
### Added 
- ALPS Latest Posts limited dropdown of categories and tags in ALPS Gutenberg Blocks [#502](https://github.com/adventistchurch/alps-wordpress/issues/502)


## [1.6.2]
### Added 
- Added Russian localization.
- Fixed bugs with localization process.

### Fixed
- Fixed the `alps.pot` generation files to remove the ` msgctxt "alps" ` lines. [#516](https://github.com/adventistchurch/alps-wordpress/issues/516)


## [1.6.1]
### Fixed
- Fix plugin author name.


## [1.6.0]
### Add
- Rebuild the deployment system. 
- Build the new the i18n system. [#36](https://github.com/adventistchurch/alps-gutenberg-blocks/pull/36)


## [1.5.2]
### Fixed
- Gallery: fix image size select. [#503](https://github.com/adventistchurch/alps-wordpress/issues/503)


## [1.5.1]
### Fixed
- ALPS Latest Posts settings do nothing. [#499](https://github.com/adventistchurch/alps-wordpress/issues/499)
- Changed the size of the title font to make it more consistent with the theme.


## [1.5.0]
### Added
- Add the "Strong" property to the ALPS Blockquote block. [#340](https://github.com/adventistchurch/alps-wordpress/issues/340)
- Add the "See All" link to the "Latest Posts" block. [#324](https://github.com/adventistchurch/alps-wordpress/issues/324)


## [1.4.4]
### Fixed
- Fixed the Image 2up block on singe post and page.


## [1.4.3]
### Fixed
- Fixed version numbering.


## [1.4.2]
### Fixed
- Fixed a bug with the `image-2up` block not spacing right on the Sabbath columns. [#29](https://github.com/adventistchurch/alps-gutenberg-blocks/issues/29)


## [1.4.1]
### Fixed
- Cleanup of the blocks to improve their usage: Interface, copy/paste, and adding limited text styling. [#28](https://github.com/adventistchurch/alps-gutenberg-blocks/pull/28)


## [1.4.0]
### Added
- Added the Media Testimonies Gutenberg Block. [#23](https://github.com/adventistchurch/alps-gutenberg-blocks/issues/23)


## [1.3.8]
### Fixed
- Fixed version numbering.


## [1.3.7]
### Added
- Added a "Open in New Window" option to the CTA block. [#404](https://github.com/adventistchurch/alps-wordpress/issues/404)


## [1.3.6]
### Added
- Added: More html formatting options to the highlight block.


## [1.3.5]
### Fixed
- Fixing the filter by tag options.


## [1.3.4]
### Fixed
- Version number increment.


## [1.3.3]
### Fixed
- Image Sizing on image blocks [#334](https://github.com/adventistchurch/alps-wordpress/issues/334)


## [1.3.2]
### Fixed
- Added the ALPS prefix to all the blocks provided here. [#344](https://github.com/adventistchurch/alps-wordpress/issues/344)


## [1.3.1]
### Fixed
- Version number increment.


## [1.3.0]
### Added
- Added a `Call to Action` block. [#342](https://github.com/adventistchurch/alps-wordpress/issues/342)


## [1.2.0]
### Added
- Added a feature to the `Latest Posts` block that allows you filter posts by `tags`. [#336](https://github.com/adventistchurch/alps-wordpress/issues/336)


## [1.1.3]
### Fixed
- Fixes the 2up Images not going full width on pages without the Sabbath column. [#332](https://github.com/adventistchurch/alps-wordpress/issues/332)


## [1.1.2]
### Fixed
- Fixes the Latest Posts not displaying more the one block on a page. [#323](https://github.com/adventistchurch/alps-wordpress/issues/323)


## [1.1.1]
### Fixed
- Fixes the breakout block on the wrong grid alignment. [#307](https://github.com/adventistchurch/alps-wordpress/issues/307)


##[1.1.0]
### Added
- Adds a Latest Posts block [#285](https://github.com/adventistchurch/alps-wordpress/issues/285)


## [1.0.2]
### Fixed
- Removes the custom paragraph block. [#282](https://github.com/adventistchurch/alps-wordpress/issues/282)
