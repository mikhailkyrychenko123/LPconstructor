(function () {
	'use strict';
	var config = window.HLPBCartData;
	if (!config || !config.active || !config.products || !Object.keys(config.products).length) return;

	var section = document.getElementById('shop');
	var options = document.getElementById('productOptions');
	var offerGrid = document.getElementById('offerGrid');
	var hero = document.getElementById('heroImage');
	var thumbs = document.querySelector('.gallery-thumbs');
	var addButton = document.getElementById('addToCart');
	var mobileAdd = document.getElementById('mobileAdd');
	if (!section || !options || !offerGrid || !hero || !addButton) return;

	var settings = config.settings || {};
	var modes = config.modes || {};
	var products = config.products || {};
	var enabledModes = ['buy', 'mix', 'bundle'].filter(function (key) {
		return modes[key] && Number(modes[key].enabled) === 1 && allowedProducts(key).length >= ('buy' === key ? 1 : 2);
	});
	if (!enabledModes.length) return;

	section.classList.add('hlpb-cart-live');
	section.style.setProperty('--hlpb-cart-accent', settings.accentColor || '#ef555c');
	section.style.backgroundColor = settings.background || '#fff';
	var state = { mode: enabledModes[0], quantity: { buy: 1, mix: 2, bundle: 1 }, items: { buy: [], mix: [], bundle: [] }, gifts: {}, giftOptIn: {}, gift: null, gallery: [], timer: 8 * 60 + 42 };
	var toast = document.getElementById('toast');
	var currencyFormat = config.currencyFormat || {};
	var currency = config.currency || 'USD';

	function allowedProducts(mode) {
		var ids = (modes[mode] && modes[mode].products ? modes[mode].products : []).map(Number).filter(function (id, index, list) { return products[id] && list.indexOf(id) === index; });
		return 'buy' === mode ? ids.slice(0, 1) : ids;
	}
	function escapeHtml(value) {
		return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[char]; });
	}
	function money(value) {
		var amount = Number(value || 0);
		try {
			return new Intl.NumberFormat(document.documentElement.lang || undefined, {
				style: 'currency', currency: currency,
				minimumFractionDigits: Number(currencyFormat.decimals == null ? 2 : currencyFormat.decimals),
				maximumFractionDigits: Number(currencyFormat.decimals == null ? 2 : currencyFormat.decimals)
			}).format(amount);
		} catch (error) {
			return (config.currencySymbol || '$') + amount.toFixed(2);
		}
	}
	function round(value) { return Math.round((Number(value) + Number.EPSILON) * 100) / 100; }
	function attrKey(key) { return String(key).replace(/^attribute_/, ''); }
	function defaultAttributes(product) {
		if (!product || !product.variations || !product.variations.length) return {};
		var first = product.variations[0].attributes || {};
		var defaults = product.defaultAttributes || {};
		var result = {};
		(product.attributes || []).forEach(function (attr) {
			var key = attrKey(attr.key);
			result[key] = defaults[key] || first['attribute_' + key] || first[key] || '';
		});
		return result;
	}
	function makeItem(productId) {
		var product = products[productId];
		var item = { product_id: Number(productId), variation_id: 0, attributes: defaultAttributes(product), price: Number(product ? product.price : 0) };
		resolveVariation(item);
		return item;
	}
	function productVariation(item) {
		var product = products[item.product_id];
		if (!product) return null;
		if ('variable' !== product.type) return { id: 0, price: Number(product.price), image: product.image, gallery: product.gallery || [] };
		if (!(product.attributes || []).every(function (attr) { return String(item.attributes[attrKey(attr.key)] || '') !== ''; })) return null;
		return (product.variations || []).find(function (variation) {
			return (product.attributes || []).every(function (attr) {
				var key = attrKey(attr.key);
				var wanted = String(item.attributes[key] || '');
				var available = variation.attributes && (variation.attributes['attribute_' + key] !== undefined ? variation.attributes['attribute_' + key] : variation.attributes[key]);
				return '' === String(available || '') || String(available) === wanted;
			});
		}) || null;
	}
	function resolveVariation(item) {
		var product = products[item.product_id];
		var variation = productVariation(item);
		if (!product) return null;
		if ('variable' === product.type && !variation) {
			item.variation_id = 0;
			return null;
		}
		item.variation_id = variation ? Number(variation.id || 0) : 0;
		item.price = Number(variation ? variation.price : product.price);
		return variation;
	}
	function getItems(mode) {
		return state.items[mode] || [];
	}
	function ensureItems(mode) {
		var ids = allowedProducts(mode);
		var count = 'bundle' === mode ? state.quantity[mode] * 2 : state.quantity[mode];
		var list = getItems(mode);
		while (list.length < count) {
			var position = list.length;
			var productId = 'bundle' === mode ? ids[position % 2] : ids[position % ids.length];
			list.push(makeItem(productId));
		}
		list.length = count;
		if ('bundle' === mode) {
			list.forEach(function (item, index) {
				var required = ids[index % 2];
				if (item.product_id !== required) list[index] = makeItem(required);
			});
		}
		state.items[mode] = list;
		return list;
	}
	function currentRule(mode, quantity) {
		var rules = (modes[mode] && modes[mode].rules) || {};
		return rules[quantity] || rules[String(quantity)] || { type: 'none', value: 0 };
	}
	function hasDistinctMixProducts() {
		return new Set(getItems('mix').map(function (item) { return Number(item.product_id); })).size >= 2;
	}
	function canSelectMixProduct(index, productId) {
		if ('mix' !== state.mode) return true;
		var otherProducts = getItems('mix').filter(function (_, itemIndex) { return itemIndex !== index; }).map(function (item) { return Number(item.product_id); });
		return !otherProducts.length || otherProducts.some(function (id) { return id !== Number(productId); });
	}
	function offerTotal(base, rule) {
		var value = Math.max(0, Number(rule.value || 0));
		if ('percent' === rule.type) return round(base * (1 - Math.min(value, 100) / 100));
		if ('amount' === rule.type) return round(Math.max(0, base - value));
		if ('fixed_total' === rule.type) return round(Math.min(base, value));
		return round(base);
	}
	function setText(selector, value) {
		var node = section.querySelector(selector);
		if (node) node.textContent = value;
	}
	function paymentMarkup(text) {
		return String(text || '').split(',').map(function (name) {
			var label = name.trim(); var key = label.toLowerCase().replace(/\s+/g, ' ');
			if (!label) return '';
			if ('visa' === key) return '<span class="pay-visa">VISA</span>';
			if ('mastercard' === key || 'master card' === key) return '<span class="pay-master" aria-label="Mastercard"><i></i><i></i></span>';
			if ('apple pay' === key) return '<span class="pay-apple">Pay</span>';
			if ('google pay' === key) return '<span class="pay-google"><b>G</b> Pay</span>';
			if ('paypal' === key) return '<span class="pay-paypal">PayPal</span>';
			return '<span>' + escapeHtml(label) + '</span>';
		}).join('');
	}
	function prepareMarkup() {
		var eyebrow = section.querySelector('.buy-panel .eyebrow');
		var heading = section.querySelector('.buy-panel h1');
		var rating = section.querySelector('.rating');
		var countdown = section.querySelector('.countdown');
		if (eyebrow) eyebrow.textContent = settings.eyebrow || '';
		if (heading) heading.textContent = settings.title || '';
		var starCount = Math.max(1, Math.min(5, Number(settings.ratingStars || 5)));
		if (rating) rating.innerHTML = '<span class="stars" aria-label="' + starCount + ' out of 5 stars">' + '★'.repeat(starCount) + '</span><b>' + escapeHtml(settings.rating || '5') + '</b> <span>(' + escapeHtml(settings.rating_count || '') + ' reviews)</span>';
		if (countdown) countdown.hidden = !settings.countdown;
		setText('#countdownLabel', settings.countdownText || 'Offer ends in');
		if (addButton) {
			var icon = addButton.querySelector('.icon');
			addButton.textContent = settings.buttonText || 'ADD TO CART';
			if (icon) addButton.prepend(icon);
		}
		if (mobileAdd) mobileAdd.textContent = settings.buttonText || 'ADD TO CART';
		section.querySelectorAll('.mode-tab').forEach(function (button) {
			var mode = button.dataset.mode;
			button.hidden = enabledModes.indexOf(mode) < 0;
			button.textContent = settings.modeLabels && settings.modeLabels[mode] ? settings.modeLabels[mode] : button.textContent;
		});
		var points = section.querySelector('.service-points');
		if (points && Array.isArray(settings.servicePoints)) {
			points.querySelectorAll('div').forEach(function (row, index) {
				var point = settings.servicePoints[index] || {};
				var title = row.querySelector('b'); var text = row.querySelector('small');
				if (title) title.textContent = point.title || '';
				if (text) text.textContent = point.text || '';
			});
		}
		var payments = section.querySelector('.payments');
		if (payments) payments.innerHTML = paymentMarkup(settings.paymentMethods);
		var referenceGift = section.querySelector('.gallery > .sock-gift');
		if (referenceGift) referenceGift.remove();
		var loader = document.createElement('div');
		loader.className = 'hlpb-cart-loading';
		loader.setAttribute('aria-hidden', 'true');
		loader.innerHTML = '<span class="hlpb-cart-spinner"></span><span class="hlpb-cart-loading-label">Updating your selection…</span>';
		section.appendChild(loader);
		var error = document.createElement('p');
		error.className = 'hlpb-cart-error'; error.hidden = true; error.setAttribute('role', 'alert');
		options.parentNode.insertBefore(error, options.nextSibling);
		ensureGiftWrap();
	}
	function ensureGiftWrap() {
		var old = section.querySelector('.hlpb-gift-box');
		if (old) old.remove();
		var rule = currentRule(state.mode, Number(state.quantity[state.mode]));
		var giftId = Number(rule.gift_product_id || 0); var product = products[giftId];
		if (!['free', 'discounted'].includes(rule.gift_mode) || !giftId || !product) { state.gift = null; return; }
		var giftKey = state.mode + '-' + state.quantity[state.mode];
		if (state.giftOptIn[giftKey] === undefined) state.giftOptIn[giftKey] = true;
		if (!state.gifts[giftKey] || Number(state.gifts[giftKey].product_id) !== Number(product.id)) state.gifts[giftKey] = makeItem(product.id);
		state.gift = state.gifts[giftKey];
		var variation = resolveVariation(state.gift);
		var image = variation && variation.image ? variation.image : product.image;
		var free = 'free' === rule.gift_mode;
		var original = Number(variation ? variation.price : product.price);
		var giftPrice = free ? 0 : ('fixed_price' === rule.gift_discount_type ? Math.min(original, Number(rule.gift_discount_value || 0)) : original * (1 - Math.min(100, Number(rule.gift_discount_value || 0)) / 100));
		var isFreeGift = free || giftPrice <= 0;
		var percentOff = 'discounted' === rule.gift_mode && 'percent' === rule.gift_discount_type ? Math.min(100, Number(rule.gift_discount_value || 0)) : 0;
		var giftTitle = isFreeGift ? 'FREE ' + product.name : (percentOff > 0 ? percentOff + '% OFF ' + product.name : product.name);
		var giftDescription = isFreeGift ? escapeHtml(rule.gift_label || 'With your selected offer, your gift is included.') : (percentOff > 0 ? escapeHtml(rule.gift_label ? rule.gift_label + ' (included with ' + percentOff + '% off).' : 'With your selected offer, your gift is included with ' + percentOff + '% off.') : (rule.gift_label ? escapeHtml(rule.gift_label) : 'Special offer: <del>' + escapeHtml(money(original)) + '</del> ' + escapeHtml(money(giftPrice))));
		var giftControls = renderVariantControls(state.gift, product, 'gift');
		var showGiftOptions = state.giftOptIn[giftKey] !== false;
		var giftFields = giftControls || !variation ? '<div class="hlpb-gift-fields"' + (showGiftOptions ? '' : ' hidden') + '><strong class="hlpb-gift-options-title">Gift options · ' + escapeHtml(product.name) + '</strong>' + giftControls + (!variation ? '<div class="hlpb-item-unavailable">Choose all available gift options</div>' : '') + '</div>' : '';
		var box = document.createElement('div');
		box.className = 'hlpb-gift-box';
		box.innerHTML = '<div class="sock-gift"><svg class="icon gift-icon" aria-hidden="true"><use href="#i-gift"></use></svg><div class="hlpb-gift-copy"><strong>' + escapeHtml(giftTitle) + '</strong><span>' + giftDescription + '</span></div><label class="hlpb-gift-toggle"><input type="checkbox" data-gift-opt-in="' + escapeHtml(giftKey) + '" ' + (showGiftOptions ? 'checked' : '') + '><span>Add gift</span></label>' + (image && (image.thumb || image.src) ? '<img class="hlpb-gift-photo" src="' + escapeHtml(image.thumb || image.src) + '" alt="' + escapeHtml(image.alt || product.name) + '" loading="lazy">' : '') + '</div>' + giftFields;
		options.after(box);
	}
	function productSelect(item, allowed, index, mode) {
		if ('bundle' === mode || allowed.length < 2) return '';
		return '<select class="hlpb-product-select" data-product-slot="' + index + '" aria-label="Choose product">' + allowed.map(function (id) { var disabled = 'mix' === mode && !canSelectMixProduct(index, id) ? ' disabled' : ''; return '<option value="' + id + '" ' + (Number(item.product_id) === Number(id) ? 'selected' : '') + disabled + '>' + escapeHtml(products[id].name) + '</option>'; }).join('') + '</select>';
	}
	function renderVariantControls(item, product, slot) {
		if (!product || !product.attributes || !product.attributes.length) return '';
		return '<div class="hlpb-variation-controls">' + product.attributes.map(function (attr, index) {
			var key = attrKey(attr.key); var current = String(item.attributes[key] || '');
			var values = (attr.options || []).map(function (option) { return option && typeof option === 'object' ? option : { value: String(option), label: String(option) }; });
			if ('color-image-picker' === attr.type) {
				return '<fieldset class="hlpb-variation-field"><legend>' + escapeHtml(attr.label || ('Option ' + (index + 1))) + '</legend><div class="hlpb-swatch-options" role="group" aria-label="' + escapeHtml(attr.label || 'Choose option') + '">' + values.map(function (option) {
					var style = option.image ? '--hlpb-swatch-image:url(&quot;' + escapeHtml(option.image) + '&quot;)' : '--hlpb-swatch-color:' + (option.color || '#e8e8e8');
					return '<button type="button" class="hlpb-swatch-choice" data-variation-slot="' + escapeHtml(slot) + '" data-attribute-key="' + escapeHtml(key) + '" data-variation-value="' + escapeHtml(option.value) + '" aria-label="' + escapeHtml(option.label) + '" aria-pressed="' + (String(option.value) === current) + '" style="' + style + '"><span>' + escapeHtml(option.label) + '</span></button>';
				}).join('') + '</div></fieldset>';
			}
			if ('button' === attr.type) {
				return '<fieldset class="hlpb-variation-field"><legend>' + escapeHtml(attr.label || ('Option ' + (index + 1))) + '</legend><div class="hlpb-attribute-buttons" role="group" aria-label="' + escapeHtml(attr.label || 'Choose option') + '">' + values.map(function (option) {
					return '<button type="button" class="hlpb-attribute-choice" data-variation-slot="' + escapeHtml(slot) + '" data-attribute-key="' + escapeHtml(key) + '" data-variation-value="' + escapeHtml(option.value) + '" aria-pressed="' + (String(option.value) === current) + '">' + escapeHtml(option.label) + '</button>';
				}).join('') + '</div></fieldset>';
			}
			return '<label class="hlpb-variation-field"><span>' + escapeHtml(attr.label || ('Option ' + (index + 1))) + '</span><select class="hlpb-variation-select" data-variation-slot="' + escapeHtml(slot) + '" data-attribute-key="' + escapeHtml(key) + '"><option value="">Choose ' + escapeHtml(attr.label || 'option') + '</option>' + values.map(function (option) { return '<option value="' + escapeHtml(option.value) + '" ' + (String(option.value) === current ? 'selected' : '') + '>' + escapeHtml(option.label || String(option.value).replace(/-/g, ' ')) + '</option>'; }).join('') + '</select></label>';
		}).join('') + '</div>';
	}
	function renderCard(item, index, mode, allowed, title) {
		var product = products[item.product_id];
		var variation = resolveVariation(item);
		var image = variation && variation.image ? variation.image : product.image;
		var alt = (image && image.alt) || product.name;
		var selector = productSelect(item, allowed, index, mode);
		return '<article class="product-option" data-item-index="' + index + '"><img class="product-photo" src="' + escapeHtml(image && image.thumb || image && image.src || '') + '" alt="' + escapeHtml(alt) + '" loading="lazy"><div class="product-info"><div class="product-head"><b class="item-title">' + escapeHtml(title || product.name) + '</b>' + selector + '</div>' + renderVariantControls(item, product, index) + (!variation ? '<div class="hlpb-item-unavailable">Choose all available options</div>' : '') + '</div></article>';
	}
	function renderItems() {
		var mode = state.mode; var allowed = allowedProducts(mode); var items = ensureItems(mode);
		var productNumbers = {};
		var html = items.map(function (item, index) {
			var productId = Number(item.product_id);
			productNumbers[productId] = (productNumbers[productId] || 0) + 1;
			var title = (products[productId] ? products[productId].name : '') + ' ' + productNumbers[productId];
			return renderCard(item, index, mode, allowed, title);
		}).join('');
		options.innerHTML = html;
		options.dataset.mode = mode; options.dataset.count = items.length;
	}
	function renderOffers() {
		var mode = state.mode;
		var quantities = 'mix' === mode ? [2, 3, 4] : [1, 2, 3, 4];
		offerGrid.innerHTML = quantities.map(function (quantity) {
			var active = Number(state.quantity[mode]) === quantity;
			var selected = ensureItems(mode); var prices = selected.map(function (item) { var product = products[item.product_id]; var variation = resolveVariation(item); return Number(variation ? variation.price : product.price); });
			var base = 0;
			if ('bundle' === mode) base = (Number(prices[0] || 0) + Number(prices[1] || 0)) * quantity;
			else if (prices.length) base = (prices.reduce(function (sum, price) { return sum + price; }, 0) / prices.length) * quantity;
			var rule = currentRule(mode, quantity); var total = offerTotal(base, rule);
			var label = 'bundle' === mode ? quantity + (quantity === 1 ? ' SET' : ' SETS') : ('mix' === mode ? quantity + ' ITEMS' : quantity + (quantity === 1 ? ' ITEM' : ' ITEMS'));
			var totalLabel = 'bundle' === mode ? 'bundle total' : ('mix' === mode ? 'mix total' : 'offer total');
			var saving = Math.max(0, base - total);
			return '<button class="offer-card ' + (quantity === 4 ? 'best' : '') + '" type="button" data-qty="' + quantity + '" aria-pressed="' + active + '">' + (quantity === 4 ? '<span class="best-label">BEST VALUE</span>' : '') + '<b>' + label + '</b><strong>' + money(total) + '</strong><small>' + totalLabel + '</small>' + (saving > 0 ? '<em>Save ' + money(saving) + '</em>' : '') + '</button>';
		}).join('');
		offerGrid.onclick = function (event) {
			var button = event.target.closest('[data-qty]'); if (!button) return;
			state.quantity[mode] = Number(button.dataset.qty); showLoading(function () { renderItems(); renderOffers(); ensureGiftWrap(); updatePreview(); });
		};
	}
	function onProductChange(event) {
		var target = event.target.closest('[data-product-slot]'); if (!target) return;
		var mode = state.mode; var index = Number(target.dataset.productSlot); var id = Number(target.value); var item = getItems(mode)[index];
		if (item && products[id] && allowedProducts(mode).indexOf(id) !== -1 && canSelectMixProduct(index, id)) { getItems(mode)[index] = makeItem(id); showLoading(function () { renderItems(); updatePreview(); }); }
	}
	function onVariationChange(event, targetMode) {
		var field = event.target.closest('[data-variation-slot]'); if (!field) return;
		var isGift = 'gift' === targetMode || 'gift' === field.dataset.variationSlot; var mode = state.mode; var items = isGift ? [state.gift] : getItems(mode); var index = isGift ? 0 : Number(field.dataset.variationSlot); var item = items[index];
		if (!item) return;
		item.attributes[field.dataset.attributeKey] = field.value;
		resolveVariation(item);
		showLoading(function () {
			if (isGift) {
				ensureGiftWrap();
			} else {
				var card = options.querySelector('[data-item-index="' + index + '"]'); var product = products[item.product_id]; var variant = productVariation(item); var image = variant && variant.image ? variant.image : product.image;
				if (card && image) { var photo = card.querySelector('.product-photo'); if (photo) { photo.src = image.thumb || image.src; photo.alt = image.alt || product.name; } }
			}
			updatePreview();
			if (!isGift && 0 === index) updateGallery(item);
		});
	}
	function onVariationSwatchClick(event, targetMode) {
		var field = event.target.closest('button[data-variation-value][data-variation-slot]'); if (!field) return;
		var isGift = 'gift' === targetMode || 'gift' === field.dataset.variationSlot; var mode = state.mode; var items = isGift ? [state.gift] : getItems(mode); var index = isGift ? 0 : Number(field.dataset.variationSlot); var item = items[index];
		if (!item) return;
		item.attributes[field.dataset.attributeKey] = field.dataset.variationValue;
		var group = field.parentNode;
		if (group) group.querySelectorAll('[data-variation-value]').forEach(function (button) { button.setAttribute('aria-pressed', String(button === field)); });
		resolveVariation(item);
		showLoading(function () {
			if (isGift) {
				ensureGiftWrap();
			} else {
				var card = options.querySelector('[data-item-index="' + index + '"]'); var product = products[item.product_id]; var variant = productVariation(item); var image = variant && variant.image ? variant.image : product.image;
				if (card && image) { var photo = card.querySelector('.product-photo'); if (photo) { photo.src = image.thumb || image.src; photo.alt = image.alt || product.name; } }
			}
			updatePreview();
			if (!isGift && 0 === index) updateGallery(item);
		});
	}
	function lineItems(mode) { return ensureItems(mode); }
	function currentTotals() {
		var items = lineItems(state.mode); var base = items.reduce(function (sum, item) { var product = products[item.product_id]; var variant = resolveVariation(item); return sum + Number(variant ? variant.price : product.price); }, 0);
		var rule = currentRule(state.mode, Number(state.quantity[state.mode])); var total = offerTotal(base, rule);
		var giftProduct = products[Number(rule.gift_product_id || 0)]; var giftVariation = state.gift ? resolveVariation(state.gift) : null; var giftPrice = 0;
		var giftKey = state.mode + '-' + state.quantity[state.mode]; var giftOptedIn = !!state.gift && state.giftOptIn[giftKey] !== false;
		if (giftOptedIn && giftProduct && state.gift && giftVariation && ['free', 'discounted'].includes(rule.gift_mode)) {
			var original = Number(giftVariation.price);
			giftPrice = 'free' === rule.gift_mode ? 0 : ('fixed_price' === rule.gift_discount_type ? Math.min(original, Number(rule.gift_discount_value || 0)) : original * (1 - Math.min(100, Number(rule.gift_discount_value || 0)) / 100));
			base += original; total += giftPrice;
		}
		var giftExpected = giftOptedIn && Number(rule.gift_product_id || 0) > 0 && ['free', 'discounted'].includes(rule.gift_mode);
		var mixValid = 'mix' !== state.mode || hasDistinctMixProducts();
		return { base: round(base), total: round(total), saving: round(Math.max(0, base - total)), valid: mixValid && items.every(function (item) { return resolveVariation(item) !== null; }) && (!giftExpected || (!!giftProduct && !!giftVariation)) };
	}
	function updateSummary() {
		var totals = currentTotals();
		setText('#oldTotal', money(totals.base)); setText('#saveTotal', '- ' + money(totals.saving)); setText('#payTotal', money(totals.total)); setText('#mobilePay', money(totals.total));
		if (addButton) addButton.disabled = !totals.valid;
		if (mobileAdd) mobileAdd.disabled = !totals.valid;
		section.querySelectorAll('.mode-tab').forEach(function (button) { button.setAttribute('aria-pressed', String(button.dataset.mode === state.mode)); });
		var copy = settings.modeCopy && settings.modeCopy[state.mode] ? settings.modeCopy[state.mode] : {};
		var mixValid = 'mix' !== state.mode || hasDistinctMixProducts();
		var defaultHint = 'buy' === state.mode ? 'One product, up to 4 items. Choose a colour or size for each.' : ('mix' === state.mode ? 'Choose 2 different products. Extra items can repeat either.' : 'Each set includes one of both products.');
		setText('#quantityHeading', copy.quantity_heading || 'STEP 1 · CHOOSE QUANTITY'); setText('#optionsHeading', copy.items_heading || 'STEP 2 · PICK EACH ITEM'); setText('#modeHint', !mixValid ? 'Choose 2 different products to continue.' : (copy.hint || defaultHint));
		var modeHint = section.querySelector('#modeHint'); if (modeHint) modeHint.classList.toggle('is-error', !mixValid);
		setText('#subtotalLabel', copy.summary || 'Items total:');
		var savingsLabel = section.querySelector('.saving span'); var totalLabel = section.querySelector('.you-pay span');
		if (savingsLabel) savingsLabel.textContent = settings.savingLabel || 'You save:';
		if (totalLabel) totalLabel.textContent = settings.totalLabel || 'You pay:';
	}
	function updatePreview() { renderOffers(); updateSummary(); }
	function updateGallery(item) {
		var product = products[item.product_id]; var variation = resolveVariation(item); var images = variation && variation.gallery && variation.gallery.length ? variation.gallery : (product.gallery || []);
		if (variation && variation.image && (!images.length || images[0].src !== variation.image.src)) images.unshift(variation.image);
		if (!images.length && variation && variation.image) images = [variation.image];
		state.gallery = images;
		if (!images.length) return;
		if (thumbs) {
			thumbs.innerHTML = images.map(function (image, index) {
				return '<button class="thumb ' + (0 === index ? 'active' : '') + '" type="button" aria-label="Show ' + escapeHtml(image.alt || product.name) + '" aria-pressed="' + (0 === index) + '" data-gallery-index="' + index + '"><img src="' + escapeHtml(image.thumb || image.src) + '" alt="" loading="lazy"></button>';
			}).join('');
			thumbs.onclick = function (event) { var button = event.target.closest('[data-gallery-index]'); if (button) showSlide(Number(button.dataset.galleryIndex)); };
		}
		showSlide(0);
	}
	function showSlide(index) {
		if (!state.gallery.length) return;
		index = (index + state.gallery.length) % state.gallery.length; var image = state.gallery[index];
		hero.src = image.src; hero.srcset = ''; hero.alt = image.alt || '';
		if (thumbs) thumbs.querySelectorAll('.thumb').forEach(function (button) { var active = Number(button.dataset.galleryIndex) === index; button.classList.toggle('active', active); button.setAttribute('aria-pressed', String(active)); });
	}
	function showLoading(callback) {
		section.classList.add('hlpb-is-loading');
		window.setTimeout(function () { callback(); window.setTimeout(function () { section.classList.remove('hlpb-is-loading'); }, 240); }, 160);
	}
	function showError(message) {
		var error = section.querySelector('.hlpb-cart-error'); if (error) { error.textContent = message || 'Could not add this offer to your cart.'; error.hidden = false; }
		if (toast) { toast.textContent = message || 'Could not add this offer to your cart.'; toast.classList.add('show'); }
	}
	function currentSelections() {
		return lineItems(state.mode).map(function (item) { return { product_id: item.product_id, variation_id: item.variation_id, attributes: item.attributes }; });
	}
	function onGiftOptInChange(event) {
		var input = event.target.closest('input[data-gift-opt-in]');
		if (!input) return;
		state.giftOptIn[input.dataset.giftOptIn] = input.checked;
		var giftFields = input.closest('.hlpb-gift-box') && input.closest('.hlpb-gift-box').querySelector('.hlpb-gift-fields');
		if (giftFields) giftFields.hidden = !input.checked;
		updateSummary();
	}
	function addToCheckout() {
		var totals = currentTotals();
		if ('mix' === state.mode && !hasDistinctMixProducts()) { showError('Choose at least two different products for your mix.'); return; }
		if (!totals.valid) { showError('Please select all required product options first.'); return; }
		var data = new FormData(); data.append('action', config.action); data.append('ajax', '1'); data.append('landing_id', config.landingId); data.append('token', config.token); data.append('mode', state.mode); data.append('quantity', state.quantity[state.mode]); data.append('items', JSON.stringify(currentSelections()));
		var giftKey = state.mode + '-' + state.quantity[state.mode]; var includeGift = !!state.gift && state.giftOptIn[giftKey] !== false;
		data.append('include_gift', includeGift ? '1' : '0');
		if (includeGift) data.append('gift_item', JSON.stringify({ product_id: state.gift.product_id, variation_id: state.gift.variation_id, attributes: state.gift.attributes }));
		section.classList.add('hlpb-is-loading');
		fetch(config.endpoint, { method: 'POST', body: data, credentials: 'same-origin', redirect: 'follow' }).then(function (response) { return response.json(); }).then(function (result) {
			if (result && result.success && result.data && result.data.redirect) { window.location.assign(result.data.redirect); return; }
			throw new Error(result && result.data && result.data.message ? result.data.message : 'WooCommerce could not add this offer to your cart.');
		}).catch(function (error) { section.classList.remove('hlpb-is-loading'); showError(error.message); });
	}
	function initTabs() {
		section.querySelectorAll('.mode-tab').forEach(function (button) {
			button.addEventListener('click', function () {
				if (enabledModes.indexOf(button.dataset.mode) < 0 || state.mode === button.dataset.mode) return;
				state.mode = button.dataset.mode; showLoading(function () { renderItems(); renderOffers(); ensureGiftWrap(); updateSummary(); updateGallery(getItems(state.mode)[0]); });
			});
		});
	}
	function initCountdown() {
		var counter = document.getElementById('countdown'); if (!counter || !settings.countdown) return;
		function tick() { var min = Math.floor(state.timer / 60); var sec = state.timer % 60; counter.textContent = String(min).padStart(2, '0') + ':' + String(sec).padStart(2, '0'); if (state.timer > 0) state.timer--; }
		tick(); window.setInterval(tick, 1000);
	}

	prepareMarkup();
	enabledModes.forEach(function (mode) { ensureItems(mode); });
	initTabs();
	options.addEventListener('change', onProductChange);
	section.addEventListener('change', function (event) { onGiftOptInChange(event); onVariationChange(event); });
	section.addEventListener('click', function (event) { onVariationSwatchClick(event); });
	if (addButton) addButton.addEventListener('click', addToCheckout);
	if (mobileAdd) mobileAdd.addEventListener('click', addToCheckout);
	var prev = section.querySelector('.arrow-prev'); var next = section.querySelector('.arrow-next');
	if (prev) prev.addEventListener('click', function () { var current = Math.max(0, state.gallery.findIndex(function (image) { return image.src === hero.src; })); showSlide(current - 1); });
	if (next) next.addEventListener('click', function () { var current = Math.max(0, state.gallery.findIndex(function (image) { return image.src === hero.src; })); showSlide(current + 1); });
	renderItems(); renderOffers(); updateSummary(); updateGallery(getItems(state.mode)[0]); initCountdown();
})();
