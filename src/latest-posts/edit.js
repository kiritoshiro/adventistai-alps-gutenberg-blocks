/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	Icon,
	PanelBody,
	Placeholder,
	QueryControls,
	Spinner,
	ToggleControl,
	ToolbarGroup,
	TextControl,
	FormTokenField,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { dateI18n, getSettings } from '@wordpress/date';
import { decodeEntities } from '@wordpress/html-entities';
import {
	InspectorControls,
	BlockControls,
	RichText,
	useBlockProps,
} from '@wordpress/block-editor';

/**
 * Internal dependencies
 */
import { ALPSLogo, text as textIcon } from './icons';

// Keep in step with LatestPostsBlock::MAX_POSTS, which also caps saved content.
const MAX_POSTS = 100;
const ALL_TERMS = { per_page: -1, _fields: 'id,name,parent', context: 'view' };
const DEFAULT_READ_MORE = __( 'Read More', 'alps-gutenberg-blocks' );

function classNames( ...names ) {
	return names.filter( Boolean ).join( ' ' );
}

export default function LatestPostsEdit( { attributes, setAttributes } ) {
	const {
		hideExcerpt,
		hidePostDate,
		hideCategoryName,
		alignRight,
		hideButton,
		hideImage,
		postLayout,
		order,
		orderBy,
		categories,
		tags,
		postsToShow,
		readMoreLabel,
	} = attributes;

	const { latestPosts, categoriesList, tagsList } = useSelect(
		( select ) => {
			const { getEntityRecords } = select( 'core' );
			const query = { order, orderby: orderBy, per_page: postsToShow };
			if ( categories ) {
				query.categories = categories;
			}
			if ( tags && tags.length ) {
				query.tags = tags;
			}
			return {
				latestPosts: getEntityRecords( 'postType', 'post', query ),
				categoriesList: getEntityRecords( 'taxonomy', 'category', ALL_TERMS ) || [],
				tagsList: getEntityRecords( 'taxonomy', 'post_tag', ALL_TERMS ) || [],
			};
		},
		[ order, orderBy, postsToShow, categories, tags ]
	);

	const tagName = ( id ) => {
		const tag = tagsList.find( ( item ) => String( item.id ) === String( id ) );
		return tag ? decodeEntities( tag.name ) : null;
	};
	const tagIds = ( names ) =>
		names
			.map( ( name ) => tagsList.find( ( item ) => decodeEntities( item.name ) === name ) )
			.filter( Boolean )
			.map( ( item ) => String( item.id ) );

	const toggle = ( key ) => () => setAttributes( { [ key ]: ! attributes[ key ] } );

	const blockProps = useBlockProps();

	const inspectorControls = (
		<InspectorControls>
			<PanelBody title={ __( 'Latest Posts Settings', 'alps-gutenberg-blocks' ) }>
				<QueryControls
					order={ order }
					orderBy={ orderBy }
					numberOfItems={ postsToShow }
					maxItems={ MAX_POSTS }
					categoriesList={ categoriesList }
					selectedCategoryId={ categories }
					onOrderChange={ ( value ) => setAttributes( { order: value } ) }
					onOrderByChange={ ( value ) => setAttributes( { orderBy: value } ) }
					onCategoryChange={ ( value ) => setAttributes( { categories: '' !== value ? value : undefined } ) }
					onNumberOfItemsChange={ ( value ) => setAttributes( { postsToShow: value } ) }
				/>
				<FormTokenField
					label={ __( 'Tags', 'alps-gutenberg-blocks' ) }
					value={ ( tags || [] ).map( tagName ).filter( Boolean ) }
					suggestions={ tagsList.map( ( item ) => decodeEntities( item.name ) ) }
					onChange={ ( names ) => setAttributes( { tags: tagIds( names ) } ) }
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
				<ToggleControl
					label={ __( 'Hide excerpt', 'alps-gutenberg-blocks' ) }
					checked={ hideExcerpt }
					onChange={ toggle( 'hideExcerpt' ) }
					__nextHasNoMarginBottom
				/>
				<ToggleControl
					label={ __( 'Hide post date', 'alps-gutenberg-blocks' ) }
					checked={ hidePostDate }
					onChange={ toggle( 'hidePostDate' ) }
					__nextHasNoMarginBottom
				/>
				<ToggleControl
					label={ __( 'Hide category name', 'alps-gutenberg-blocks' ) }
					checked={ hideCategoryName }
					onChange={ toggle( 'hideCategoryName' ) }
					__nextHasNoMarginBottom
				/>
				<ToggleControl
					label={ __( 'Hide button', 'alps-gutenberg-blocks' ) }
					checked={ hideButton }
					onChange={ toggle( 'hideButton' ) }
					__nextHasNoMarginBottom
				/>
				{ ! hideButton && (
					<TextControl
						label={ __( 'Button label', 'alps-gutenberg-blocks' ) }
						placeholder={ DEFAULT_READ_MORE }
						value={ readMoreLabel }
						onChange={ ( value ) => setAttributes( { readMoreLabel: value } ) }
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				) }
				<ToggleControl
					label={ __( 'Hide image', 'alps-gutenberg-blocks' ) }
					checked={ hideImage }
					onChange={ toggle( 'hideImage' ) }
					__nextHasNoMarginBottom
				/>
				<ToggleControl
					label={ __( 'Align the image right', 'alps-gutenberg-blocks' ) }
					checked={ alignRight }
					onChange={ toggle( 'alignRight' ) }
					__nextHasNoMarginBottom
				/>
			</PanelBody>
		</InspectorControls>
	);

	const hasPosts = Array.isArray( latestPosts ) && latestPosts.length;
	if ( ! hasPosts ) {
		return (
			<div { ...blockProps }>
				{ inspectorControls }
				<Placeholder icon="admin-post" label={ __( 'Latest Posts', 'alps-gutenberg-blocks' ) }>
					{ ! Array.isArray( latestPosts ) ? <Spinner /> : __( 'No posts found.', 'alps-gutenberg-blocks' ) }
				</Placeholder>
			</div>
		);
	}

	const displayPosts = latestPosts.slice( 0, postsToShow );
	const dateFormat = getSettings().formats.date;
	const layoutControls = [
		{
			icon: 'list-view',
			title: __( 'List View', 'alps-gutenberg-blocks' ),
			onClick: () => setAttributes( { postLayout: 'list' } ),
			isActive: postLayout === 'list',
		},
		{
			icon: 'grid-view',
			title: __( 'Grid View', 'alps-gutenberg-blocks' ),
			onClick: () => setAttributes( { postLayout: 'grid' } ),
			isActive: postLayout === 'grid',
		},
	];

	return (
		<div { ...blockProps }>
			{ inspectorControls }
			<BlockControls>
				<ToolbarGroup controls={ layoutControls } />
			</BlockControls>
			<div className="c-block__heading u-theme--border-color--darker">
				<div className="descCard">
					<div className="descCard__title-box">
						<Icon className="icon" icon={ ALPSLogo } />
						<div className="descCard__title">{ __( 'Latest Posts', 'alps-gutenberg-blocks' ) }</div>
					</div>
					<div className="descCard__info-icon-box">
						<div className="descCard__info-icon">
							<Icon className="icon" icon={ textIcon } />
						</div>
					</div>
				</div>
				<div className="contentCard">
					<fieldset>
						<legend>{ __( 'Title', 'alps-gutenberg-blocks' ) }</legend>
						<RichText
							tagName="h3"
							className="c-block__heading-title u-theme--color--darker contentCard__input"
							placeholder={ __( 'Enter your Title...', 'alps-gutenberg-blocks' ) }
							value={ attributes.title }
							allowedFormats={ [ 'core/bold', 'core/italic', 'core/link' ] }
							onChange={ ( title ) => setAttributes( { title } ) }
						/>
					</fieldset>
					<fieldset>
						<legend>{ __( 'See All Link Label', 'alps-gutenberg-blocks' ) }</legend>
						<RichText
							tagName="span"
							className="c-block__heading-link u-theme--color--base u-theme--link-hover--dark contentCard__input"
							placeholder={ __( 'Enter your link label...', 'alps-gutenberg-blocks' ) }
							value={ attributes.linkLabel }
							allowedFormats={ [ 'core/bold', 'core/italic' ] }
							onChange={ ( linkLabel ) => setAttributes( { linkLabel } ) }
						/>
					</fieldset>
					<fieldset>
						<legend>{ __( 'See All URL', 'alps-gutenberg-blocks' ) }</legend>
						<div className="contentCard__link">
							<TextControl
								type="url"
								placeholder="https://..."
								value={ attributes.linkUrl }
								onChange={ ( linkUrl ) => setAttributes( { linkUrl } ) }
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
						</div>
					</fieldset>
				</div>
			</div>
			<ul
				className={ classNames(
					'alps-latest-posts__list',
					postLayout === 'grid' && 'l-grid l-grid--3-col',
					alignRight && 'u-align--right',
					hideImage && 'u-hide--image'
				) }
			>
				{ displayPosts.map( ( post ) => (
					<li key={ post.id }>
						<div className="alps-latest-posts__content">
							<a
								href={ post.link }
								className="alps-latest-posts__title"
								target="_blank"
								rel="noopener noreferrer"
							>
								{ decodeEntities( post.title.rendered.trim() ) || __( '(Untitled)', 'alps-gutenberg-blocks' ) }
							</a>
							{ ! hideExcerpt && (
								<div className="alps-latest-posts__excerpt">
									[{ __( 'Post excerpt is visible', 'alps-gutenberg-blocks' ) }]
								</div>
							) }
							<div className="alps-latest-posts__meta">
								{ ! hidePostDate && (
									<span className="alps-latest-posts__date">{ dateI18n( dateFormat, post.date_gmt ) }</span>
								) }
								{ ! hideCategoryName && (
									<span className="alps-latest-posts__category">
										[{ __( 'Category name is visible', 'alps-gutenberg-blocks' ) }]
									</span>
								) }
							</div>
							{ ! hideButton && (
								<span className="alps-latest-posts__button">{ readMoreLabel || DEFAULT_READ_MORE }</span>
							) }
						</div>
					</li>
				) ) }
			</ul>
		</div>
	);
}
