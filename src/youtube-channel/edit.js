/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import {
	Button,
	Disabled,
	Notice,
	PanelBody,
	Placeholder,
	RangeControl,
	TextControl,
	ToggleControl,
	ToolbarButton,
	ToolbarGroup,
} from '@wordpress/components';
import { BlockControls, InspectorControls, useBlockProps } from '@wordpress/block-editor';
import ServerSideRender from '@wordpress/server-side-render';

const settings = window.alpsGbYouTube || { hasKey: false, maxVideos: 25 };

const NETWORKS = [
	[ 'facebook', 'Facebook' ],
	[ 'instagram', 'Instagram' ],
	[ 'tiktok', 'TikTok' ],
	[ 'x', 'X' ],
];

/** True for a channel ID, an @handle or a youtube.com channel link. Mirrors YouTubeChannelBlock::parseChannel(). */
export function isChannel( value ) {
	const text = String( value || '' ).trim();
	const handle = /^@[\p{L}\p{N}._-]{3,30}$/u;
	if ( /^UC[A-Za-z0-9_-]{22}$/.test( text ) || handle.test( text ) ) {
		return true;
	}
	try {
		const url = new URL( text );
		const path = decodeURIComponent( url.pathname );
		return [ 'youtube.com', 'www.youtube.com', 'm.youtube.com' ].includes( url.hostname.toLowerCase() ) &&
			/^https?:$/.test( url.protocol ) &&
			( /^\/channel\/UC[A-Za-z0-9_-]{22}(\/|$)/.test( path ) || /^\/@[\p{L}\p{N}._-]{3,30}(\/|$)/u.test( path ) );
	} catch ( error ) {
		return false;
	}
}

function ChannelForm( { channel, onSubmit, onCancel } ) {
	const [ draft, setDraft ] = useState( channel || '' );
	const [ touched, setTouched ] = useState( false );
	const valid = isChannel( draft );

	return (
		<Placeholder
			icon="video-alt3"
			label={ __( 'YouTube Channel Videos', 'alps-gutenberg-blocks' ) }
			instructions={ __( 'Paste the channel link (https://www.youtube.com/@name), its @handle or its channel ID.', 'alps-gutenberg-blocks' ) }
			className="alps-ytc-placeholder"
		>
			<form
				className="alps-ytc-placeholder__form"
				onSubmit={ ( event ) => {
					event.preventDefault();
					setTouched( true );
					if ( valid ) {
						onSubmit( draft.trim() );
					}
				} }
			>
				<input
					type="text"
					className="components-placeholder__input"
					aria-label={ __( 'YouTube channel', 'alps-gutenberg-blocks' ) }
					placeholder="https://www.youtube.com/@…"
					value={ draft }
					onChange={ ( event ) => setDraft( event.target.value ) }
					onBlur={ () => setTouched( draft !== '' ) }
				/>
				<Button variant="primary" type="submit" disabled={ draft === '' }>
					{ __( 'Show videos', 'alps-gutenberg-blocks' ) }
				</Button>
				{ onCancel && (
					<Button variant="tertiary" onClick={ onCancel }>
						{ __( 'Cancel', 'alps-gutenberg-blocks' ) }
					</Button>
				) }
			</form>
			{ touched && ! valid && (
				<p className="alps-ytc-placeholder__error" role="alert">
					{ __( 'That is not a channel. Open the channel on YouTube and copy its address, for example https://www.youtube.com/@TrijuAngeluStudija.', 'alps-gutenberg-blocks' ) }
				</p>
			) }
		</Placeholder>
	);
}

export default function YouTubeChannelEdit( { attributes, setAttributes } ) {
	const { channel, title, count, excludeShorts, links } = attributes;
	const [ editing, setEditing ] = useState( false );
	const blockProps = useBlockProps();
	const valid = isChannel( channel );
	const setLink = ( network, value ) => setAttributes( { links: { ...( links || {} ), [ network ]: value } } );

	if ( ! valid || editing ) {
		return (
			<div { ...blockProps }>
				<ChannelForm
					channel={ channel }
					onSubmit={ ( value ) => {
						setAttributes( { channel: value } );
						setEditing( false );
					} }
					onCancel={ valid ? () => setEditing( false ) : null }
				/>
			</div>
		);
	}

	return (
		<div { ...blockProps }>
			<BlockControls>
				<ToolbarGroup>
					<ToolbarButton icon="edit" label={ __( 'Change channel', 'alps-gutenberg-blocks' ) } onClick={ () => setEditing( true ) } />
				</ToolbarGroup>
			</BlockControls>
			<InspectorControls>
				<PanelBody title={ __( 'Channel', 'alps-gutenberg-blocks' ) }>
					{ ! settings.hasKey && (
						<Notice status="warning" isDismissible={ false }>
							{ __( 'Add a YouTube Data API key under Settings → Media. Until then the block shows nothing to visitors.', 'alps-gutenberg-blocks' ) }
						</Notice>
					) }
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Channel link, @handle or ID', 'alps-gutenberg-blocks' ) }
						value={ channel }
						help={ isChannel( channel ) ? '' : __( 'Not a YouTube channel.', 'alps-gutenberg-blocks' ) }
						onChange={ ( value ) => setAttributes( { channel: value } ) }
					/>
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Title', 'alps-gutenberg-blocks' ) }
						help={ __( 'Leave empty to use the channel name.', 'alps-gutenberg-blocks' ) }
						value={ title }
						onChange={ ( value ) => setAttributes( { title: value } ) }
					/>
					<RangeControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Number of videos', 'alps-gutenberg-blocks' ) }
						min={ 1 }
						max={ settings.maxVideos || 25 }
						value={ count }
						onChange={ ( value ) => setAttributes( { count: value || 10 } ) }
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Leave out Shorts', 'alps-gutenberg-blocks' ) }
						help={ __( 'YouTube does not mark Shorts, so every video of 3 minutes or less is left out.', 'alps-gutenberg-blocks' ) }
						checked={ !! excludeShorts }
						onChange={ ( value ) => setAttributes( { excludeShorts: value } ) }
					/>
				</PanelBody>
				<PanelBody title={ __( 'Social media links', 'alps-gutenberg-blocks' ) } initialOpen={ false }>
					<p>{ __( 'The YouTube channel is always linked. Add the other profiles to show their icons.', 'alps-gutenberg-blocks' ) }</p>
					{ NETWORKS.map( ( [ network, name ] ) => (
						<TextControl
							key={ network }
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							type="url"
							label={ name }
							placeholder="https://"
							value={ ( links && links[ network ] ) || '' }
							onChange={ ( value ) => setLink( network, value ) }
						/>
					) ) }
				</PanelBody>
			</InspectorControls>
			{ /* The real server output. Disabled: the editor never loads YouTube. */ }
			<Disabled>
				<ServerSideRender block="alps-gutenberg-blocks/youtube-channel" attributes={ attributes } />
			</Disabled>
		</div>
	);
}
