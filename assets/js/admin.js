/* global wp */
( function ( $ ) {
	'use strict';

	var frame;

	$( '#partner-logo-upload' ).on( 'click', function ( e ) {
		e.preventDefault();

		if ( frame ) {
			frame.open();
			return;
		}

		frame = wp.media( {
			title: 'Select Partner Logo',
			button: { text: 'Use this image' },
			multiple: false,
			library: { type: 'image' },
		} );

		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();
			$( '#partner_logo_id' ).val( attachment.id );
			$( '#partner-logo-preview' ).html(
				'<img src="' + attachment.url + '" style="max-width:200px;max-height:200px;display:block;" alt="">'
			);
			$( '#partner-logo-remove' ).show();
		} );

		frame.open();
	} );

	$( document ).on( 'click', '#partner-logo-remove', function ( e ) {
		e.preventDefault();
		$( '#partner_logo_id' ).val( '' );
		$( '#partner-logo-preview' ).html( '' );
		$( this ).hide();
	} );
} )( jQuery );
