jQuery( function( $ ) {

	$(document).ready(function(){
		$( 'input#_subscription_telr' ).is( ':checked' ) ? $( '#recurring_options ' ).show() : $( '#recurring_options ' ).hide();		
	});

	$( 'input#_subscription_telr' ).click( function() {
		if ( $( this ).is( ':checked' ) ) {
			$( "#recurring_options" ).show();
		}else{
			$( "#recurring_options" ).hide();
		}
	});

	$( 'select#_continued_of' ).val() > 0 ? $( '._final_payment_of_field ' ).show() : $( '._final_payment_of_field ' ).hide();
	$( 'select#_continued_of' ).change( function() {
		if( $( this ).val() > 0 ){
			$( '._final_payment_of_field ' ).show();
		}else{
			$( '._final_payment_of_field ' ).hide();			
		}
	});

	$( 'select#_for_number_of' ).val() == 'months' ? $( '._payment_day_field ' ).show() : $( '._payment_day_field ' ).hide();
	$( 'select#_for_number_of' ).change( function() {
		if( $( this ).val() == 'months' ){
			$( '._payment_day_field ' ).show();
		}else{
			$( '._payment_day_field ' ).hide();			
		}
	});

});
