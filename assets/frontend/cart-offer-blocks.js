( function () {
	'use strict';

	const checkoutFilters = window.wc?.blocksCheckout?.registerCheckoutFilters;
	if ( typeof checkoutFilters !== 'function' ) {
		return;
	}

	const offersByItemClass = new Map();
	let renderQueued = false;

	function queueRender() {
		if ( renderQueued ) {
			return;
		}
		renderQueued = true;
		window.requestAnimationFrame( function () {
			renderQueued = false;
			renderOfferPrices();
		} );
	}

	function priceMarkup( oldHtml, newHtml, groupClass ) {
		const wrapper = document.createElement( 'span' );
		wrapper.className = 'hlpb-block-offer-prices ' + groupClass;

		const oldPrice = document.createElement( 'del' );
		oldPrice.className = 'hlpb-offer-price-old';
		oldPrice.innerHTML = oldHtml;

		const newPrice = document.createElement( 'ins' );
		newPrice.className = 'hlpb-offer-price-new';
		newPrice.innerHTML = newHtml;

		wrapper.append( oldPrice, newPrice );
		return wrapper;
	}

	function replacePrice( target, oldHtml, newHtml, groupClass ) {
		if ( ! target || ! oldHtml || ! newHtml ) {
			return;
		}

		const signature = groupClass + '|' + oldHtml + '|' + newHtml;
		if ( target.dataset.hlpbOfferSignature === signature && target.querySelector( ':scope > .hlpb-block-offer-prices' ) ) {
			return;
		}

		target.replaceChildren( priceMarkup( oldHtml, newHtml, groupClass ) );
		target.dataset.hlpbOfferSignature = signature;
	}

	function renderOfferPrices() {
		offersByItemClass.forEach( function ( offer, itemClass ) {
			if ( ! offer.active || ! offer.has_discount ) {
				return;
			}

			document.querySelectorAll( '.' + itemClass ).forEach( function ( row ) {
				const isCheckoutSummary = row.classList.contains( 'wc-block-components-order-summary-item' );
				const unitTarget = isCheckoutSummary
					? row.querySelector( '.wc-block-components-order-summary-item__individual-prices' )
					: row.querySelector( '.wc-block-cart-item__prices .wc-block-components-product-price' );
				const lineTarget = isCheckoutSummary
					? row.querySelector( '.wc-block-components-order-summary-item__total-price .wc-block-components-product-price' )
					: row.querySelector( '.wc-block-cart-item__total-price-and-sale-badge-wrapper .wc-block-components-product-price' );

				replacePrice( unitTarget, offer.old_unit_html, offer.new_unit_html, offer.group_class );
				replacePrice( lineTarget, offer.old_line_html, offer.new_line_html, offer.group_class );
			} );
		} );
	}

	function itemClass( defaultValue, extensions, args ) {
		const offer = extensions?.hlpb?.offer;
		const cartItemKey = args?.cartItem?.key;
		if ( ! offer?.active || ! /^hlpb-offer-group-\d+$/.test( offer.group_class || '' ) || typeof cartItemKey !== 'string' ) {
			return defaultValue;
		}

		const itemClassName = 'hlpb-cart-item-' + cartItemKey.replace( /[^a-z0-9_-]/gi, '' );
		if ( itemClassName.length <= 128 ) {
			offersByItemClass.set( itemClassName, offer );
			queueRender();
			return [ defaultValue, offer.group_class, itemClassName ].filter( Boolean ).join( ' ' );
		}
		return [ defaultValue, offer.group_class ].filter( Boolean ).join( ' ' );
	}

	checkoutFilters( 'hlpb-cart-offers', {
		cartItemClass: itemClass,
	} );

	function observeCartBlocks() {
		if ( ! document.body || typeof window.MutationObserver !== 'function' ) {
			return;
		}

		const observer = new MutationObserver( queueRender );
		observer.observe( document.body, { childList: true, subtree: true } );
		queueRender();
	}

	if ( document.body ) {
		observeCartBlocks();
	} else {
		document.addEventListener( 'DOMContentLoaded', observeCartBlocks, { once: true } );
	}
} )();
