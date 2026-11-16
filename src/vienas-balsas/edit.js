import { __ } from '@wordpress/i18n';
import { Button, Disabled, PanelBody, SelectControl, TextControl, TextareaControl, ToggleControl } from '@wordpress/components';
import { InspectorControls, MediaUpload, MediaUploadCheck, useBlockProps } from '@wordpress/block-editor';
import ServerSideRender from '@wordpress/server-side-render';
import metadata from './block.json';

const domain = 'alps-gutenberg-blocks';
const fieldProps = { __nextHasNoMarginBottom: true, __next40pxDefaultSize: true };
const MAX_CARDS = 3;
const emptyCard = { imageId: 0, eyebrow: '', title: '', text: '', url: '' };
const cardKeys = Object.keys( emptyCard );
/** A saved card: a plain object with at least one card field. Mirrors VienasBalsasBlock::isCard(). */
export const isCard = ( card ) => !! card && typeof card === 'object' && ! Array.isArray( card ) && cardKeys.some( ( key ) => Object.prototype.hasOwnProperty.call( card, key ) );
const urlHelp =() => __( 'Adresas, prasidedantis https://, arba svetainės kelias, pvz. /kaip-prisideti/. Be adreso nuoroda nerodoma.', domain );

export default function VienasBalsasEdit( { attributes, setAttributes } ) {
  const set = ( key ) => ( value ) => setAttributes( { [ key ]: value } );
  // Only plain objects with a card field count as cards (null, strings, arrays and
  // {} are dropped), as VienasBalsasBlock::cards() does. With none left the default
  // cards show, as the server renders them; the next edit saves the cleaned list.
  // Opening the block never changes it.
  const saved = Array.isArray( attributes.cards ) ? attributes.cards.filter( isCard ) : [];
  const cards = saved.length ? saved : metadata.attributes.cards.default;
  const setCard = ( index, key ) => ( value ) => setAttributes( {
    cards: cards.map( ( card, i ) => ( i === index ? { ...card, [ key ]: value } : card ) ),
  } );
  const removeCard = ( index ) => setAttributes( { cards: cards.filter( ( card, i ) => i !== index ) } );
  const addCard = () => setAttributes( { cards: [ ...cards, { ...emptyCard } ] } );

  return (
    <div { ...useBlockProps() }>
      <InspectorControls>
        <PanelBody title={ __( 'Antraštė', domain ) }>
          <TextControl { ...fieldProps } label={ __( 'Antraštė', domain ) } value={ attributes.heading } onChange={ set( 'heading' ) } />
          <SelectControl { ...fieldProps } label={ __( 'Antraštės lygis', domain ) } value={ String( attributes.headingLevel ) }
            help={ __( 'Kortelių pavadinimai būna vienu lygiu žemiau.', domain ) }
            options={ [ 2, 3, 4 ].map( ( level ) => ( { label: 'H' + level, value: String( level ) } ) ) }
            onChange={ ( value ) => setAttributes( { headingLevel: parseInt( value, 10 ) || 2 } ) } />
          <TextControl { ...fieldProps } label={ __( 'Nuorodos tekstas', domain ) } value={ attributes.linkText } onChange={ set( 'linkText' ) } />
          <TextControl { ...fieldProps } type="url" label={ __( 'Nuorodos adresas', domain ) } help={ urlHelp() } value={ attributes.linkUrl } onChange={ set( 'linkUrl' ) } />
        </PanelBody>
        { cards.map( ( card, index ) => (
          <PanelBody key={ index } title={ __( 'Projekto kortelė', domain ) + ' ' + ( index + 1 ) } initialOpen={ false }>
            <MediaUploadCheck>
              <MediaUpload allowedTypes={ [ 'image' ] } value={ card.imageId || undefined }
                onSelect={ ( media ) => setCard( index, 'imageId' )( media && media.id ? media.id : 0 ) }
                render={ ( { open } ) => (
                  <Button variant="secondary" onClick={ open }>
                    { card.imageId ? __( 'Pakeisti paveikslėlį', domain ) : __( 'Pasirinkti paveikslėlį', domain ) }
                  </Button>
                ) } />
            </MediaUploadCheck>
            { !! card.imageId && (
              <Button variant="link" isDestructive onClick={ () => setCard( index, 'imageId' )( 0 ) }>{ __( 'Pašalinti paveikslėlį', domain ) }</Button>
            ) }
            <p>{ __( 'Alternatyvusis tekstas imamas iš paveikslėlio Medijos bibliotekoje.', domain ) }</p>
            <TextControl { ...fieldProps } label={ __( 'Viršutinė eilutė', domain ) } value={ card.eyebrow || '' } onChange={ setCard( index, 'eyebrow' ) } />
            <TextControl { ...fieldProps } label={ __( 'Pavadinimas', domain ) } value={ card.title || '' } onChange={ setCard( index, 'title' ) } />
            <TextareaControl __nextHasNoMarginBottom label={ __( 'Tekstas', domain ) } value={ card.text || '' } onChange={ setCard( index, 'text' ) } />
            <TextControl { ...fieldProps } type="url" label={ __( 'Nuorodos „Daugiau“ adresas', domain ) } help={ urlHelp() } value={ card.url || '' } onChange={ setCard( index, 'url' ) } />
            <Button variant="secondary" isDestructive disabled={ cards.length <= 1 } onClick={ () => removeCard( index ) }>
              { __( 'Pašalinti kortelę', domain ) }
            </Button>
          </PanelBody>
        ) ) }
        <PanelBody title={ __( 'Projekto kortelės', domain ) }>
          <TextControl { ...fieldProps } label={ __( 'Nuorodos „Daugiau“ tekstas', domain ) } value={ attributes.moreText } onChange={ set( 'moreText' ) } />
          <Button variant="primary" disabled={ cards.length >= MAX_CARDS } onClick={ addCard }>{ __( 'Pridėti kortelę', domain ) }</Button>
          <p>{ __( 'Galima turėti nuo 1 iki 3 projekto kortelių.', domain ) }</p>
        </PanelBody>
        <PanelBody title={ __( 'Kvietimo kortelė', domain ) } initialOpen={ false }>
          <ToggleControl __nextHasNoMarginBottom label={ __( 'Rodyti kvietimo kortelę', domain ) } checked={ attributes.showCta } onChange={ set( 'showCta' ) } />
          { attributes.showCta && <>
            <TextControl { ...fieldProps } label={ __( 'Viršutinė eilutė', domain ) } value={ attributes.ctaEyebrow } onChange={ set( 'ctaEyebrow' ) } />
            <TextControl { ...fieldProps } label={ __( 'Pavadinimas', domain ) } value={ attributes.ctaTitle } onChange={ set( 'ctaTitle' ) } />
            <TextareaControl __nextHasNoMarginBottom label={ __( 'Tekstas', domain ) } value={ attributes.ctaText } onChange={ set( 'ctaText' ) } />
            <TextControl { ...fieldProps } label={ __( 'Mygtuko tekstas', domain ) } value={ attributes.ctaButtonText } onChange={ set( 'ctaButtonText' ) } />
            <TextControl { ...fieldProps } type="url" label={ __( 'Mygtuko adresas', domain ) } help={ urlHelp() } value={ attributes.ctaUrl } onChange={ set( 'ctaUrl' ) } />
          </> }
        </PanelBody>
      </InspectorControls>
      <Disabled><ServerSideRender block={ metadata.name } attributes={ attributes } httpMethod="POST" /></Disabled>
    </div>
  );
}
