/* globals jQuery: false, ajaxurl: false, SecuPressPointerTour: false */
(function($) {
	var tour = window.SecuPressPointerTour,
		steps, index, $currentEl, moving = false;

	if ( ! tour || ! tour.steps || ! tour.steps.length ) {
		return;
	}

	steps = tour.steps;
	index = 0;

	function currentStep() {
		return steps[ index ];
	}

	function dismissPointers( ids, callback ) {
		$.post( ajaxurl, {
			action:     'dismiss-sp-pointer-tour',
			pointers:   ids,
			_ajaxnonce: tour.nonce
		} ).always( callback || $.noop );
	}

	function addTourArgs( url, stepId ) {
		var hash  = '',
			parts = String( url || '' ).split( '#' ),
			sep;

		url = parts[0];
		if ( parts[1] ) {
			hash = '#' + parts[1];
		}
		sep = url.indexOf( '?' ) === -1 ? '?' : '&';
		return url + sep + 'secupress_pointer_tour=' + encodeURIComponent( tour.id ) + '&secupress_pointer_step=' + encodeURIComponent( stepId ) + hash;
	}

	function scrollToTarget( $el, callback ) {
		var offset, top;

		if ( ! $el.length ) {
			callback();
			return;
		}

		offset = $el.offset();
		if ( ! offset ) {
			callback();
			return;
		}

		top = Math.max( 0, offset.top - 96 );
		if ( Math.abs( $( window ).scrollTop() - top ) < 80 ) {
			callback();
			return;
		}

		$( 'html, body' ).animate( { scrollTop: top }, 400, callback );
	}

	function closeCurrent() {
		if ( $currentEl && $currentEl.length ) {
			try {
				$currentEl.pointer( 'destroy' );
			} catch ( err ) {}
		}
		$currentEl = null;
	}

	function goToStep( nextIndex ) {
		var step = steps[ nextIndex ];

		if ( ! step ) {
			closeCurrent();
			return;
		}

		if ( $( step.selector ).first().length ) {
			index = nextIndex;
			openStep();
			return;
		}

		if ( step.url ) {
			window.location = addTourArgs( step.url, step.id );
			return;
		}

		goToStep( nextIndex + 1 );
	}

	function goNext() {
		var step = currentStep(),
			nextIndex = index + 1;

		moving = true;
		dismissPointers( [ step.id ], function() {
			if ( nextIndex >= steps.length ) {
				closeCurrent();
				moving = false;
				return;
			}
			closeCurrent();
			moving = false;
			goToStep( nextIndex );
		} );
	}

	function dismissTour() {
		var ids = $.map( steps.slice( index ), function( step ) {
			return step.id;
		} );
		dismissPointers( ids, function() {
			closeCurrent();
		} );
	}

	function openStep() {
		var step = currentStep(),
			$el  = $( step.selector ).first(),
			isLast, options;

		if ( ! $el.length ) {
			goToStep( index + 1 );
			return;
		}

		closeCurrent();
		isLast  = index === steps.length - 1;
		options = $.extend( true, {}, step.options || {}, {
			content: step.content,
			buttons: function( event, t ) {
				var $wrap    = $( '<div class="secupress-pointer-buttons"></div>' ),
					$nav     = $( '<span class="secupress-pointer-nav"></span>' ),
					$count   = $( '<span class="secupress-pointer-step"></span>' ),
					$next    = $( '<button type="button" class="button-link secupress-pointer-next"></button>' ),
					$dismiss = $( '<a class="close" href="#"></a>' );

				$count.text( step.number + '/' + tour.total );
				$next.text( isLast ? tour.i18n.gotIt : tour.i18n.next );
				$next.on( 'click', function( e ) {
					e.preventDefault();
					e.stopPropagation();
					goNext();
				} );

				$dismiss.text( tour.i18n.dismiss );
				$dismiss.on( 'click.pointer', function( e ) {
					e.preventDefault();
					t.element.pointer( 'close' );
				} );

				$nav.append( $count ).append( $next );
				$wrap.append( $nav ).append( $dismiss );
				return $wrap;
			},
			close: function() {
				if ( moving ) {
					return;
				}
				dismissTour();
			}
		} );

		scrollToTarget( $el, function() {
			$currentEl = $el;
			$el.pointer( options ).pointer( 'open' );
		} );
	}

	$( function() {
		if ( tour.startId ) {
			$.each( steps, function( i, step ) {
				if ( step.id === tour.startId ) {
					index = i;
					return false;
				}
			} );
		}
		if ( $( currentStep().selector ).length || tour.startId ) {
			openStep();
		}
	} );
})( jQuery );
