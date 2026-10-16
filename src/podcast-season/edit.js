import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { Disabled, PanelBody, Placeholder, RangeControl, SelectControl, TextControl, ToggleControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';

/** True for an http(s) address. Mirrors PodcastSeasonBlock::feedUrl(). */
export function isFeedUrl( value ) {
	try {
		const url = new URL( String( value || '' ).trim() );
		return /^https?:$/.test( url.protocol ) && url.hostname !== '';
	} catch ( error ) {
		return false;
	}
}

export default function Edit( { attributes, setAttributes } ) {
	const { feedUrl, season, title, order, visible, removePrefix, showNumbers, showCover, showDescription, descriptionWords, autoNext } = attributes;
	const feedControl = (
		<TextControl
			label={ __( 'Podcast RSS feed', 'alps-gutenberg-blocks' ) }
			help={ __( 'The show’s RSS address, e.g. https://anchor.fm/s/…/podcast/rss for Spotify for Creators.', 'alps-gutenberg-blocks' ) }
			type="url"
			value={ feedUrl }
			onChange={ ( value ) => setAttributes( { feedUrl: value } ) }
		/>
	);
	const seasonControl = (
		<TextControl
			label={ __( 'Season', 'alps-gutenberg-blocks' ) }
			type="number"
			min={ 1 }
			max={ 999 }
			value={ season > 0 ? season : '' }
			// Not clamped while typing: emptying the field and typing 3 gives 3, not 13.
			// 0 (empty) shows the server's "enter a season" notice.
			onChange={ ( value ) => setAttributes( { season: Math.max( 0, Math.min( 999, parseInt( value, 10 ) || 0 ) ) } ) }
		/>
	);
	const ready = isFeedUrl( feedUrl );

	return (
		<div { ...useBlockProps() }>
			<InspectorControls>
				<PanelBody title={ __( 'Podcast', 'alps-gutenberg-blocks' ) }>
					{ feedControl }
					{ seasonControl }
					<TextControl
						label={ __( 'Title', 'alps-gutenberg-blocks' ) }
						help={ __( 'Empty: the podcast’s name.', 'alps-gutenberg-blocks' ) }
						value={ title }
						onChange={ ( value ) => setAttributes( { title: value } ) }
					/>
				</PanelBody>
				<PanelBody title={ __( 'Episode list', 'alps-gutenberg-blocks' ) }>
					<SelectControl
						label={ __( 'Order', 'alps-gutenberg-blocks' ) }
						value={ order }
						options={ [
							{ label: __( 'First episode first', 'alps-gutenberg-blocks' ), value: 'asc' },
							{ label: __( 'Newest episode first', 'alps-gutenberg-blocks' ), value: 'desc' },
						] }
						onChange={ ( value ) => setAttributes( { order: value } ) }
					/>
					<RangeControl
						label={ __( 'Episodes shown before “Show all”', 'alps-gutenberg-blocks' ) }
						min={ 1 }
						max={ 100 }
						value={ visible }
						onChange={ ( value ) => setAttributes( { visible: value ?? 10 } ) }
					/>
					<TextControl
						label={ __( 'Remove from the start of titles', 'alps-gutenberg-blocks' ) }
						help={ __( 'For example the series name, e.g. “Marijos Sūnaus gyvenimas.”', 'alps-gutenberg-blocks' ) }
						value={ removePrefix }
						onChange={ ( value ) => setAttributes( { removePrefix: value } ) }
					/>
					<ToggleControl
						label={ __( 'Show episode numbers', 'alps-gutenberg-blocks' ) }
						checked={ showNumbers }
						onChange={ ( value ) => setAttributes( { showNumbers: value } ) }
					/>
					<ToggleControl
						label={ __( 'Show descriptions', 'alps-gutenberg-blocks' ) }
						checked={ showDescription }
						onChange={ ( value ) => setAttributes( { showDescription: value } ) }
					/>
					{ showDescription && (
						<RangeControl
							label={ __( 'Description length (words)', 'alps-gutenberg-blocks' ) }
							min={ 5 }
							max={ 100 }
							value={ descriptionWords }
							onChange={ ( value ) => setAttributes( { descriptionWords: value ?? 30 } ) }
						/>
					) }
				</PanelBody>
				<PanelBody title={ __( 'Player', 'alps-gutenberg-blocks' ) }>
					<ToggleControl
						label={ __( 'Show cover', 'alps-gutenberg-blocks' ) }
						help={ __( 'A small copy is saved on this site in the background; it appears after a minute.', 'alps-gutenberg-blocks' ) }
						checked={ showCover }
						onChange={ ( value ) => setAttributes( { showCover: value } ) }
					/>
					<ToggleControl
						label={ __( 'Play the next episode automatically', 'alps-gutenberg-blocks' ) }
						checked={ autoNext }
						onChange={ ( value ) => setAttributes( { autoNext: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			{ ready ? (
				<Disabled>
					<ServerSideRender block={ metadata.name } attributes={ attributes } httpMethod="POST" />
				</Disabled>
			) : (
				<Placeholder
					icon="microphone"
					label={ __( 'Podcast Season', 'alps-gutenberg-blocks' ) }
					instructions={ __( 'Enter the podcast’s RSS feed and the season to show. New episodes of that season appear by themselves.', 'alps-gutenberg-blocks' ) }
				>
					{ feedControl }
					{ seasonControl }
				</Placeholder>
			) }
		</div>
	);
}
