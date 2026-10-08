import { __ } from '@wordpress/i18n';
import { Disabled, PanelBody, RangeControl, SelectControl, TextControl, ToggleControl, ColorPalette } from '@wordpress/components';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import ServerSideRender from '@wordpress/server-side-render';

const domain = 'alps-gutenberg-blocks';
const rangeProps = { __nextHasNoMarginBottom: true, __next40pxDefaultSize: true };

export default function BookShowcaseEdit( { attributes, setAttributes } ) {
  const set = ( key ) => ( value ) => setAttributes( { [ key ]: value } );
  return (
    <div { ...useBlockProps() }>
      <InspectorControls>
        <PanelBody title={ __( 'Books', domain ) }>
          <TextControl { ...rangeProps } label={ __( 'Category slug', domain ) }
            help={ __( 'Find the slug under Posts → Categories. The default is pdf-knygos. An unknown or empty category shows the empty message.', domain ) }
            value={ attributes.category } onChange={ set( 'category' ) } />
          <ToggleControl __nextHasNoMarginBottom label={ __( 'Show all books', domain ) } checked={ attributes.showAll } onChange={ set( 'showAll' ) } />
          { ! attributes.showAll && <RangeControl { ...rangeProps } label={ __( 'Number of books', domain ) } min={ 1 } max={ 100 }
            value={ attributes.count } onChange={ ( value ) => setAttributes( { count: value || 12 } ) } /> }
          <SelectControl { ...rangeProps } label={ __( 'Order by', domain ) } value={ attributes.orderBy } onChange={ set( 'orderBy' ) }
            options={ [ { label: __( 'Post title', domain ), value: 'title' }, { label: __( 'Publish date', domain ), value: 'date' }, { label: __( 'Last modified', domain ), value: 'modified' } ] } />
          <SelectControl { ...rangeProps } label={ __( 'Order', domain ) } value={ attributes.order } onChange={ set( 'order' ) }
            options={ [ { label: __( 'Ascending', domain ), value: 'ASC' }, { label: __( 'Descending', domain ), value: 'DESC' } ] } />
        </PanelBody>
        <PanelBody title={ __( 'Layout', domain ) } initialOpen={ false }>
          { [ [ 'desktopColumns', __( 'Desktop columns (1025px and up)', domain ), 6 ], [ 'tabletColumns', __( 'Tablet columns (769–1024px)', domain ), 4 ], [ 'smallColumns', __( 'Small tablet columns (481–768px)', domain ), 3 ] ].map( ( [ key, label, max ] ) => (
            <RangeControl key={ key } { ...rangeProps } label={ label } min={ 1 } max={ max } value={ attributes[ key ] } onChange={ ( value ) => setAttributes( { [ key ]: value || 1 } ) } />
          ) ) }
          <p>{ __( 'Phones show one centered cover at 80% width, like the original grid.', domain ) }</p>
          <RangeControl { ...rangeProps } label={ __( 'Gap (em)', domain ) } min={ 0.5 } max={ 3 } step={ 0.25 } value={ attributes.gap } onChange={ set( 'gap' ) } />
          <RangeControl { ...rangeProps } label={ __( 'Maximum width (px)', domain ) } min={ 600 } max={ 1800 } step={ 50 } value={ attributes.maxWidth } onChange={ set( 'maxWidth' ) } />
          <p>{ __( 'Accent color', domain ) }</p>
          <ColorPalette aria-label={ __( 'Accent color', domain ) } value={ attributes.accentColor }
            colors={ [ { name: __( 'Gold', domain ), color: '#C2A25B' }, { name: __( 'Denim', domain ), color: '#2f557f' }, { name: __( 'Forest', domain ), color: '#355e3b' } ] }
            clearable={ false } onChange={ ( value ) => setAttributes( { accentColor: value || '#C2A25B' } ) } />
        </PanelBody>
        <PanelBody title={ __( 'Titles and effects', domain ) } initialOpen={ false }>
          <ToggleControl __nextHasNoMarginBottom label={ __( 'Show titles', domain ) } checked={ attributes.showTitles } onChange={ set( 'showTitles' ) } />
          <SelectControl { ...rangeProps } label={ __( 'Book title source', domain ) } value={ attributes.titleSource } onChange={ set( 'titleSource' ) }
            help={ __( 'Image captions fall back to the post title. Sorting always uses the post title.', domain ) }
            options={ [ { label: __( 'Featured image caption', domain ), value: 'caption' }, { label: __( 'Post title', domain ), value: 'post' } ] } />
          <ToggleControl __nextHasNoMarginBottom label={ __( 'Animate covers', domain ) } checked={ attributes.animate } onChange={ set( 'animate' ) }
            help={ __( 'Includes fade-in and hover effects. Reduced-motion preferences are always respected.', domain ) } />
          <TextControl { ...rangeProps } label={ __( 'Empty message', domain ) } value={ attributes.emptyMessage } onChange={ set( 'emptyMessage' ) }
            help={ __( 'Leave empty for the translated “No books found.” message.', domain ) } />
        </PanelBody>
      </InspectorControls>
      <Disabled><ServerSideRender block="alps-gutenberg-blocks/book-showcase" attributes={ attributes } httpMethod="POST" /></Disabled>
    </div>
  );
}
