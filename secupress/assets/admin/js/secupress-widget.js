(function() {
	function formatNumberI18n( number ) {
		var format = SecuPressi18nWidget.numberFormat || {};
		var thousandsSep = format.thousandsSep !== undefined ? format.thousandsSep : ",";
		var rounded = Math.round( number );
		var str = String( rounded );
		var result = "";
		var i = str.length;
		var count = 0;

		while ( i > 0 ) {
			i--;
			result = str.charAt( i ) + result;
			count++;
			if ( count % 3 === 0 && i > 0 ) {
				result = thousandsSep + result;
			}
		}

		return result;
	}

	function animateCounter( counterEl ) {
		if ( ! counterEl || counterEl.secupressCounterAnimated ) {
			return;
		}

		counterEl.secupressCounterAnimated = true;

		var target = parseInt( counterEl.getAttribute( "data-count" ), 10 );

		if ( isNaN( target ) || target < 0 ) {
			target = 0;
		}

		if ( window.matchMedia && window.matchMedia( "(prefers-reduced-motion: reduce)" ).matches ) {
			counterEl.textContent = formatNumberI18n( target );
			return;
		}

		var duration = SecuPressi18nWidget.counterDuration || 3000;
		var startTime = null;

		function step( timestamp ) {
			if ( ! startTime ) {
				startTime = timestamp;
			}

			var progress = Math.min( ( timestamp - startTime ) / duration, 1 );
			var eased = 1 - Math.pow( 1 - progress, 3 );
			var current = target * eased;

			counterEl.textContent = formatNumberI18n( current );

			if ( progress < 1 ) {
				requestAnimationFrame( step );
			} else {
				counterEl.textContent = formatNumberI18n( target );
			}
		}

		requestAnimationFrame( step );
	}

	function animateAllAttacksCounter() {
		var counters = document.querySelectorAll( ".secupress-attacks-overview-count[data-count]" );
		var i;

		for ( i = 0; i < counters.length; i++ ) {
			animateCounter( counters[ i ] );
		}
	}

	function alignChartPointer() {
		if ( typeof Chart === "undefined" || ! Chart.helpers || Chart.helpers.secupressPointerAligned ) {
			return;
		}

		var original = Chart.helpers.getRelativePosition;

		Chart.helpers.getRelativePosition = function( evt ) {
			var pos = original( evt );
			var canvas = evt.currentTarget || evt.srcElement;

			if ( ! canvas || ! canvas.getBoundingClientRect || ! canvas.width || ! canvas.height ) {
				return pos;
			}

			var rect = canvas.getBoundingClientRect();
			var ratio = window.devicePixelRatio || 1;
			var logicalWidth = canvas.width / ratio;
			var logicalHeight = canvas.height / ratio;

			if ( rect.width && logicalWidth ) {
				pos.x = pos.x * ( logicalWidth / rect.width );
			}
			if ( rect.height && logicalHeight ) {
				pos.y = pos.y * ( logicalHeight / rect.height );
			}

			return pos;
		};
		Chart.helpers.secupressPointerAligned = true;
	}

	var chartInstances = {};

	function destroyChartCanvas( id, height ) {
		var canvas = document.getElementById( id );

		if ( chartInstances[ id ] && chartInstances[ id ].destroy ) {
			try {
				chartInstances[ id ].destroy();
			} catch ( e ) {}
		}

		delete chartInstances[ id ];
		canvas = document.getElementById( id );

		if ( ! canvas || ! canvas.parentNode ) {
			return;
		}

		var fresh = document.createElement( "canvas" );
		fresh.id = id;
		fresh.height = height || 64;
		canvas.parentNode.replaceChild( fresh, canvas );
	}

	var tryInitChart;

	function initSecuPressCharts() {
		var chartData = SecuPressi18nWidget.typeCharts ? SecuPressi18nWidget.typeCharts.slice() : [];

		if ( SecuPressi18nWidget.overviewChart && SecuPressi18nWidget.overviewChart.data && SecuPressi18nWidget.overviewChart.data.length ) {
			chartData.push( SecuPressi18nWidget.overviewChart );
		}

		if ( ! chartData.length ) {
			return;
		}

		if ( typeof Chart === "undefined" ) {
			setTimeout( initSecuPressCharts, 100 );
			return;
		}

		alignChartPointer();

		var chartsInitialized = 0;
		var isModernChart = Chart && Chart.hasOwnProperty( "version" );

		tryInitChart = function( chart ) {
			var canvas = document.getElementById( chart.id );

			if ( ! canvas ) {
				return false;
			}

			var rect = canvas.getBoundingClientRect();

			if ( rect.width === 0 || rect.height === 0 ) {
				return false;
			}

			if ( ! canvas.width || ! canvas.height ) {
				canvas.width = canvas.offsetWidth || 200;
				canvas.height = canvas.offsetHeight || 120;
			}

			var ctx = canvas.getContext( "2d" );
			var color = chart.color || "#dc3232";
			var fill = chart.fill || "rgba(220,50,50,0.1)";
			var showPoints = chart.pointDot === undefined ? true : !! parseInt( chart.pointDot, 10 );
			var smooth = chart.bezier === undefined ? true : !! parseInt( chart.bezier, 10 );
			var spark = !! parseInt( chart.spark, 10 );
			var lineWidth = spark ? 1.5 : 2;
			var dotRadius = spark ? 2 : 3;

			if ( ! ctx ) {
				return false;
			}

			var instance = null;

			try {
				if ( isModernChart ) {
					instance = new Chart( ctx, {
						type: "line",
						data: {
							labels: chart.labels,
							datasets: [ {
								fill: true,
								backgroundColor: fill,
								borderColor: color,
								pointBackgroundColor: color,
								pointBorderColor: "#fff",
								pointHoverBackgroundColor: "#fff",
								pointHoverBorderColor: color,
								data: chart.data,
								tension: smooth ? 0.4 : 0
							} ]
						},
						options: {
							responsive: true,
							maintainAspectRatio: false,
							plugins: {
								legend: {
									display: false
								},
								tooltip: {
									enabled: true
								}
							},
							scales: {
								x: {
									display: false
								},
								y: {
									display: false
								}
							},
							elements: {
								point: {
									radius: showPoints ? dotRadius : 0
								},
								line: {
									borderWidth: lineWidth
								}
							}
						}
					} );
				} else {
					instance = new Chart( ctx ).Line( {
						labels: chart.labels,
						datasets: [ {
							fillColor: fill,
							strokeColor: color,
							pointColor: color,
							pointStrokeColor: "#fff",
							pointHighlightFill: "#fff",
							pointHighlightStroke: color,
							data: chart.data
						} ]
					}, {
						responsive: true,
						maintainAspectRatio: false,
						showScale: false,
						showTooltips: true,
						customTooltips: function( tooltip ) {
							var tip = document.getElementById( "secupress-chart-tooltip" );

							if ( ! tooltip ) {
								if ( tip ) {
									tip.style.display = "none";
								}
								return;
							}

							var canvas = tooltip.chart && tooltip.chart.canvas;

							if ( ! canvas ) {
								return;
							}

							if ( ! tip ) {
								tip = document.createElement( "div" );
								tip.id = "secupress-chart-tooltip";
								tip.className = "secupress-chart-tooltip";
								document.body.appendChild( tip );
							}

							tip.textContent = tooltip.text;
							tip.style.display = "block";

							var rect = canvas.getBoundingClientRect();
							var ratio = window.devicePixelRatio || 1;
							var logicalWidth = canvas.width / ratio;
							var logicalHeight = canvas.height / ratio;
							var scaleX = logicalWidth && rect.width ? rect.width / logicalWidth : 1;
							var scaleY = logicalHeight && rect.height ? rect.height / logicalHeight : 1;
							var tipWidth = tip.offsetWidth;
							var tipHeight = tip.offsetHeight;
							var pointX = rect.left + window.pageXOffset + tooltip.x * scaleX;
							var pointY = rect.top + window.pageYOffset + tooltip.y * scaleY;
							var left = pointX - tipWidth / 2;
							var top = pointY - tipHeight - 10;
							var minLeft = window.pageXOffset + 8;
							var maxLeft = window.pageXOffset + window.innerWidth - tipWidth - 8;

							if ( left > maxLeft ) {
								left = maxLeft;
							}

							if ( left < minLeft ) {
								left = minLeft;
							}

							if ( top < window.pageYOffset + 8 ) {
								top = pointY + 12;
							}

							tip.style.left = left + "px";
							tip.style.top = top + "px";
						},
						pointDot: showPoints,
						pointDotRadius: showPoints ? dotRadius : 0,
						datasetStroke: true,
						datasetStrokeWidth: lineWidth,
						datasetFill: true,
						bezierCurve: smooth,
						bezierCurveTension: smooth ? 0.4 : 0,
						scaleShowLabels: false,
						scaleShowGridLines: false,
						scaleShowHorizontalLines: false,
						scaleShowVerticalLines: false,
						legend: false
					} );
				}
				chartInstances[ chart.id ] = instance;
				canvas.chartInitialized = true;
				return true;
			}
			catch ( e ) {
				console.error( "SecuPress chart error for " + chart.id + ":", e );
				return false;
			}
		}

		for ( var i = 0; i < chartData.length; i++ ) {
			if ( tryInitChart( chartData[ i ] ) ) {
				chartsInitialized++;
			}
		}

		if ( chartsInitialized < chartData.length ) {
			setTimeout( function() {
				for ( var j = 0; j < chartData.length; j++ ) {
					var canvas = document.getElementById( chartData[ j ].id );

					if ( canvas && ! canvas.chartInitialized ) {
						if ( tryInitChart( chartData[ j ] ) ) {
							canvas.chartInitialized = true;
						}
					}
				}
			}, 500 );
		}
	}

	function applyOverviewPeriod( data ) {
		var countEl = document.querySelector( ".secupress-attacks-overview-count" );
		var caption = document.querySelector( ".secupress-attacks-overview-caption" );
		var lifetime = document.querySelector( ".secupress-attacks-overview-lifetime" );
		var percent = document.querySelector( ".secupress-attacks-overview-percent" );
		var chartWrap = document.querySelector( ".secupress-attacks-overview-chart" );
		var axis = document.querySelector( ".secupress-attacks-overview-axis" );

		if ( countEl ) {
			countEl.secupressCounterAnimated = false;
			countEl.setAttribute( "data-count", data.count );
			countEl.textContent = "0";
			animateCounter( countEl );
		}

		if ( caption ) {
			caption.textContent = data.caption;
		}

		if ( lifetime ) {
			if ( data.lifetime ) {
				lifetime.hidden = false;
				lifetime.textContent = data.lifetime;
			} else {
				lifetime.hidden = true;
				lifetime.textContent = "";
			}
		}

		if ( percent ) {
			percent.className = "secupress-attacks-overview-percent " + ( data.percentClass || "is-none" );
			percent.textContent = data.percentText;
		}

		if ( data.startCaption || data.endCaption ) {
			if ( ! axis && chartWrap && chartWrap.parentNode ) {
				axis = document.createElement( "div" );
				axis.className = "secupress-attacks-overview-axis";
				axis.appendChild( document.createElement( "span" ) );
				axis.appendChild( document.createElement( "span" ) );
				chartWrap.parentNode.insertBefore( axis, chartWrap.nextSibling );
			}
			if ( axis && axis.children.length >= 2 ) {
				axis.children[ 0 ].textContent = data.startCaption || "";
				axis.children[ 1 ].textContent = data.endCaption || "";
			}
		} else if ( axis && axis.parentNode ) {
			axis.parentNode.removeChild( axis );
		}

		if ( ! data.points || ! data.points.length ) {
			destroyChartCanvas( "secupress-attacks-overview-chart", 64 );
			applyTypeRows( data.types );
			return;
		}

		destroyChartCanvas( "secupress-attacks-overview-chart", 64 );
		tryInitChart( {
			id: "secupress-attacks-overview-chart",
			labels: data.labels,
			data: data.points,
			color: data.color,
			fill: data.fill,
			pointDot: data.pointDot,
			bezier: data.bezier
		} );
		applyTypeRows( data.types );
	}

	function applyTypeRows( types ) {
		var i;

		if ( ! types || ! types.length || ! tryInitChart ) {
			return;
		}

		for ( i = 0; i < types.length; i++ ) {
			var row = types[ i ];
			var item = document.querySelector( '.secupress-attack-row[data-type="' + row.type + '"]' );
			var countEl;

			if ( ! item ) {
				continue;
			}

			countEl = item.querySelector( ".secupress-attack-row-count" );

			if ( countEl ) {
				countEl.textContent = formatNumberI18n( row.count || 0 );
			}

			if ( row.count ) {
				item.classList.remove( "is-zero" );
			} else {
				item.classList.add( "is-zero" );
			}

			if ( ! row.data || ! row.data.length ) {
				destroyChartCanvas( row.id, 28 );
				continue;
			}

			destroyChartCanvas( row.id, 28 );
			tryInitChart( row );
		}
	}

	function bindOverviewRange() {
		var select = document.getElementById( "secupress-attacks-widget-range" );

		if ( ! select || select.secupressRangeBound ) {
			return;
		}

		select.secupressRangeBound = true;
		select.addEventListener( "change", function() {
			var body = new window.FormData();
			var past = select.parentNode.querySelector( ".secupress-attacks-overview-past" );

			if ( past ) {
				past.hidden = ( "1" === select.value || "all" === select.value );
			}

			body.append( "action", "secupress_attacks_widget_range" );
			body.append( "nonce", SecuPressi18nWidget.rangeNonce );
			body.append( "range", select.value );
			select.disabled = true;

			window.fetch( SecuPressi18nWidget.ajaxUrl, {
				method: "POST",
				credentials: "same-origin",
				body: body
			} ).then( function( response ) {
				return response.json();
			} ).then( function( payload ) {
				select.disabled = false;
				if ( ! payload || ! payload.success || ! payload.data ) {
					return;
				}
				applyOverviewPeriod( payload.data );
			} ).catch( function() {
				select.disabled = false;
			} );
		} );
	}

	function initSecuPressWidget() {
		animateAllAttacksCounter();
		initSecuPressCharts();
		bindOverviewRange();
	}

	function startInit() {
		if ( document.readyState === "loading" ) {
			document.addEventListener( "DOMContentLoaded", function() {
				setTimeout( initSecuPressWidget, 100 );
			} );
		} else {
			jQuery( document ).ready( function() {
				setTimeout( initSecuPressWidget, 100 );
			} );
		}
	}

	startInit();
})();
