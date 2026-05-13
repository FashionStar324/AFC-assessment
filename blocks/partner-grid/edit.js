import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, RangeControl, TextControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

export default function Edit( { attributes, setAttributes } ) {
	const { category, perPage, columns } = attributes;
	const blockProps = useBlockProps();

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Display Settings', 'partner-directory' ) }>
					<RangeControl
						label={ __( 'Columns', 'partner-directory' ) }
						value={ columns }
						onChange={ ( value ) => setAttributes( { columns: value } ) }
						min={ 1 }
						max={ 6 }
					/>
					<RangeControl
						label={ __( 'Partners per page', 'partner-directory' ) }
						value={ perPage }
						onChange={ ( value ) => setAttributes( { perPage: value } ) }
						min={ 1 }
						max={ 100 }
					/>
					<TextControl
						label={ __( 'Category slug', 'partner-directory' ) }
						value={ category }
						onChange={ ( value ) => setAttributes( { category: value } ) }
						help={ __( 'Leave blank to show all partners.', 'partner-directory' ) }
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<ServerSideRender
					block="partner-directory/partner-grid"
					attributes={ attributes }
				/>
			</div>
		</>
	);
}
