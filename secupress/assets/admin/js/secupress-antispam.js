// Get the submit from the WP comment form via the core hidden field.
var commentPostId = document.querySelector( '[name="comment_post_ID"]' );
var commentForm   = commentPostId ? commentPostId.form : null;
var dcts_submit   = commentForm ? commentForm.querySelector( '[type="submit"]' ) : null;

// If there is not, bail.
if ( dcts_submit ) {
	// Get the button label (input.value or button text)
	var dctsLabelProp     = 'BUTTON' === dcts_submit.tagName ? 'textContent' : 'value';
	var dcts_submit_value = dcts_submit[ dctsLabelProp ];
	// Set our timer in JS from our filter
	var dcts_timer = secupressDctsTimer.dctsTimer;
	// Disable the button and make it alpha 50%
	dcts_submit.setAttribute( 'disabled', '' );
	dcts_submit.style.opacity = 0.5;
	// Change the label to include the timer at max value
	dcts_submit[ dctsLabelProp ] = dcts_submit_value + ' (' + dcts_timer + ')';
	// Every second, reduce the timer by 1 and print it in the button
	dcts_submit_interval = setInterval(
		function() {
			dcts_timer--;
			dcts_submit[ dctsLabelProp ] = dcts_submit_value + ' (' + dcts_timer + ')';
		},
	1000 );
	// When the timer is done, reset the label, alpha, disabled status of the button
		setTimeout(
			function() { 
				clearInterval( dcts_submit_interval );
				var witness = document.getElementById( 'secupress_dcts_timer_witness' );
				if ( witness ) {
					witness.parentNode.removeChild( witness );
				}

				dcts_submit[ dctsLabelProp ] = dcts_submit_value;
				dcts_submit.style.opacity    = 1;
				dcts_submit.removeAttribute( 'disabled' );
			},
		dcts_timer * 1000 );

	var gmtOffset       = secupressDctsTimer.gmtOffset;
	var serverTime      = new Date( new Date().getTime() + ( gmtOffset * 3600 * 1000 ) );
	var serverTimestamp = Math.floor( serverTime.getTime() / 1000 );

	document.getElementById( 'secupress_dcts_timer' ).value = serverTimestamp;
}
