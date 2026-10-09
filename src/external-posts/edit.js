import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { Notice, PanelBody, RangeControl, SelectControl, TextareaControl, ToggleControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';

export default function Edit( { attributes, setAttributes } ) {
  const { feeds, number, cacheMinutes, layout, excerptLength, imageSize, debug } = attributes;
  const feedControl = <TextareaControl label={ __( 'Feed URLs', 'alps-gutenberg-blocks' ) }
    help={ __( 'One URL per line, or comma-separated. Up to eight feeds. A WordPress homepage URL automatically uses /feed/.', 'alps-gutenberg-blocks' ) }
    value={ feeds } onChange={ value => setAttributes( { feeds: value } ) } />;
  return <div { ...useBlockProps() }>
    <InspectorControls>
      <PanelBody title={ __( 'External feed settings', 'alps-gutenberg-blocks' ) }>
        { feedControl }
        <RangeControl label={ __( 'Posts per feed', 'alps-gutenberg-blocks' ) } min={ 1 } max={ 20 } value={ number }
          onChange={ value => setAttributes( { number: value ?? 5 } ) } />
        <RangeControl label={ __( 'Cache duration (minutes)', 'alps-gutenberg-blocks' ) } min={ 1 } max={ 1440 } value={ cacheMinutes }
          onChange={ value => setAttributes( { cacheMinutes: value ?? 30 } ) } />
        <SelectControl label={ __( 'Layout', 'alps-gutenberg-blocks' ) } value={ layout }
          options={ [ { label: __( 'List', 'alps-gutenberg-blocks' ), value: 'list' }, { label: __( 'Cards', 'alps-gutenberg-blocks' ), value: 'cards' } ] }
          onChange={ value => setAttributes( { layout: value } ) } />
        { layout === 'cards' && <>
          <RangeControl label={ __( 'Excerpt length (words)', 'alps-gutenberg-blocks' ) } min={ 1 } max={ 100 } value={ excerptLength }
            onChange={ value => setAttributes( { excerptLength: value ?? 15 } ) } />
          <RangeControl label={ __( 'Thumbnail size (pixels)', 'alps-gutenberg-blocks' ) } min={ 40 } max={ 500 } value={ imageSize }
            onChange={ value => setAttributes( { imageSize: value ?? 120 } ) } />
        </> }
        <ToggleControl label={ __( 'Administrator diagnostics', 'alps-gutenberg-blocks' ) } checked={ debug }
          help={ __( 'Feed error details are visible only to administrators.', 'alps-gutenberg-blocks' ) }
          onChange={ value => setAttributes( { debug: value } ) } />
      </PanelBody>
    </InspectorControls>
    { feeds.trim() ? <ServerSideRender block={ metadata.name } attributes={ attributes } httpMethod="POST" /> :
      <div className="alps-epa-placeholder"><Notice status="info" isDismissible={ false }>{ __( 'Add feed URLs to display external posts.', 'alps-gutenberg-blocks' ) }</Notice>{ feedControl }</div> }
  </div>;
}
