import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, RangeControl, TextControl, ToggleControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';

export default function Edit( { attributes, setAttributes } ) {
  const { category, postsPerPage, showDate, showExcerpt } = attributes;
  return <div { ...useBlockProps() }>
    <InspectorControls>
      <PanelBody title={ __( 'Post list settings', 'alps-gutenberg-blocks' ) }>
        <TextControl label={ __( 'Category slug', 'alps-gutenberg-blocks' ) }
          help={ __( 'Leave empty for all categories. Use the slug from Posts → Categories.', 'alps-gutenberg-blocks' ) }
          value={ category } onChange={ value => setAttributes( { category: value } ) } />
        <RangeControl label={ __( 'Number of posts', 'alps-gutenberg-blocks' ) } min={ 1 } max={ 50 }
          value={ postsPerPage } onChange={ value => setAttributes( { postsPerPage: value ?? 5 } ) } />
        <ToggleControl label={ __( 'Show date', 'alps-gutenberg-blocks' ) } checked={ showDate }
          onChange={ value => setAttributes( { showDate: value } ) } />
        <ToggleControl label={ __( 'Show excerpt', 'alps-gutenberg-blocks' ) } checked={ showExcerpt }
          onChange={ value => setAttributes( { showExcerpt: value } ) } />
      </PanelBody>
    </InspectorControls>
    <ServerSideRender block={ metadata.name } attributes={ attributes } />
  </div>;
}
