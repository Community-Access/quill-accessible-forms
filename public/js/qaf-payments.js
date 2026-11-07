/**
 * QUILL Accessible Forms: the checkout after a paid form is sent.
 *
 * The site has already saved the answers and worked out the amount; this
 * script only lets the payer choose a provider and pay. It never decides
 * whether a payment succeeded: every result is checked by the site, which
 * asks the provider itself (payments/status) or hears from the provider's
 * signed webhook.
 *
 * Screen readers: the checkout heading takes focus when it appears; progress
 * is spoken from one polite status region; problems from one alert region,
 * in words. Every control is a native radio button or button.
 *
 * @package QAF
 */
(function () {
	'use strict';

	if (typeof window.qafPayments === 'undefined') {
		return;
	}
	var cfg = window.qafPayments;
	var t = cfg.i18n;
	var STORE = 'qaf_payment_return_';

	function fmt(text, value) {
		return String(text).replace('%s', value);
	}

	function el(tag, attrs, text) {
		var node = document.createElement(tag);
		Object.keys(attrs || {}).forEach(function (k) {
			if (k === 'hidden') {
				node.hidden = !!attrs[k];
			} else {
				node.setAttribute(k, attrs[k]);
			}
		});
		if (text) {
			node.textContent = text;
		}
		return node;
	}

	function post(path, body) {
		var headers = { 'Content-Type': 'application/json' };
		if (window.qafPublic && window.qafPublic.nonce) {
			headers['X-WP-Nonce'] = window.qafPublic.nonce;
		}
		return fetch(cfg.restUrl + path, {
			method: 'POST',
			credentials: 'same-origin',
			headers: headers,
			body: JSON.stringify(body)
		}).then(function (r) {
			return r.json().then(function (json) {
				if (!r.ok) {
					var err = new Error((json && json.message) || t.network);
					err.data = json;
					throw err;
				}
				return json;
			});
		});
	}

	function loadScript(src) {
		return new Promise(function (resolve, reject) {
			var s = document.createElement('script');
			s.src = src;
			s.async = true;
			s.onload = resolve;
			s.onerror = reject;
			document.head.appendChild(s);
		});
	}

	/**
	 * One checkout, for one submitted form.
	 */
	function Checkout(wrapper, payment, formId) {
		this.wrapper = wrapper;
		this.payment = payment;
		this.formId = formId;
		this.busy = false;
		this.build();
	}

	Checkout.prototype.build = function () {
		var p = this.payment;
		var base = 'qaf-checkout-' + this.formId;
		var old = this.wrapper.querySelector('.qaf-checkout');
		if (old) {
			old.parentNode.removeChild(old);
		}
		var section = el('section', { 'class': 'qaf-checkout', 'aria-labelledby': base + '-heading' });
		this.section = section;
		this.heading = el('h3', { id: base + '-heading', tabindex: '-1' }, p.returned ? t.returned : fmt(t.heading, p.spoken));
		section.appendChild(this.heading);
		section.appendChild(el('p', {}, t.intro));
		if (p.test_mode) {
			section.appendChild(el('p', { 'class': 'qaf-checkout-test' }, t.testMode));
		}

		var self = this;
		if (p.providers.length > 1) {
			var fs = el('fieldset', { 'class': 'qaf-checkout-choice' });
			fs.appendChild(el('legend', {}, t.choose));
			p.providers.forEach(function (prov, i) {
				var id = base + '-' + prov.id;
				var wrap = el('div', { 'class': 'qaf-checkout-option' });
				var input = el('input', { type: 'radio', name: base + '-provider', id: id, value: prov.id });
				if (i === 0) {
					input.checked = true;
				}
				input.addEventListener('change', function () {
					if (input.checked) {
						self.choose(prov);
					}
				});
				wrap.appendChild(input);
				wrap.appendChild(el('label', { 'for': id }, prov.id === 'stripe' ? t.card : t.paypal));
				fs.appendChild(wrap);
			});
			section.appendChild(fs);
		}

		this.panel = el('div', { 'class': 'qaf-checkout-panel' });
		section.appendChild(this.panel);
		this.status = el('p', { 'class': 'qaf-checkout-status', role: 'status', 'aria-live': 'polite' });
		section.appendChild(this.status);
		this.alert = el('p', { 'class': 'qaf-checkout-error', role: 'alert' });
		section.appendChild(this.alert);
		this.retry = el('button', { type: 'button', 'class': 'qaf-button qaf-checkout-retry', hidden: true }, t.tryAgain);
		this.retry.addEventListener('click', function () {
			if (self.retry.onclick) {
				return;
			}
			self.retry.hidden = true;
			if (!self.current) {
				// Back from a redirect with no checkout on the page: start over.
				var url = new URL(window.location.href);
				url.searchParams.delete('qaf_payment_return');
				window.location.href = url.toString();
				return;
			}
			self.choose(self.current);
		});
		section.appendChild(this.retry);
		this.receipt = el('p', { 'class': 'qaf-checkout-receipt', hidden: true });
		section.appendChild(this.receipt);

		var messages = this.wrapper.querySelector('.qaf-messages');
		if (messages && messages.nextSibling) {
			this.wrapper.insertBefore(section, messages.nextSibling);
		} else {
			this.wrapper.appendChild(section);
		}
		this.heading.focus();

		if (p.status && p.status !== 'pending' && p.status !== 'failed' && p.status !== 'cancelled') {
			this.refresh();
			return;
		}
		this.choose(p.providers[0]);
	};

	Checkout.prototype.say = function (text) {
		this.status.textContent = text;
	};

	Checkout.prototype.fail = function (text) {
		this.alert.textContent = text;
		this.retry.hidden = false;
	};

	Checkout.prototype.clear = function () {
		this.alert.textContent = '';
		this.retry.hidden = true;
	};

	Checkout.prototype.choose = function (prov) {
		if (!prov) {
			return;
		}
		this.current = prov;
		this.clear();
		this.panel.textContent = '';
		if (prov.id === 'stripe') {
			this.stripe(prov);
		} else {
			this.paypal(prov);
		}
	};

	Checkout.prototype.stripe = function (prov) {
		var self = this;
		if (typeof window.Stripe !== 'function') {
			this.fail(t.noScript);
			return;
		}
		this.say(t.loading);
		post('start', { token: this.payment.token, provider: 'stripe' }).then(function (reply) {
			if (reply.status && reply.status.done) {
				self.show(reply.status);
				return;
			}
			var stripe = window.Stripe(prov.key);
			var elements = stripe.elements({ clientSecret: reply.client_secret });
			var group = el('div', { 'class': 'qaf-checkout-card', role: 'group', 'aria-label': t.cardDetails });
			var mount = el('div', { 'class': 'qaf-checkout-element' });
			group.appendChild(mount);
			self.panel.appendChild(group);
			var pay = el('button', { type: 'button', 'class': 'qaf-button qaf-button-primary qaf-checkout-pay' }, fmt(t.payButton, self.payment.spoken));
			self.panel.appendChild(pay);
			var element = elements.create('payment');
			element.on('ready', function () {
				self.say(t.loaded);
			});
			element.mount(mount);
			pay.addEventListener('click', function () {
				if (self.busy) {
					return;
				}
				self.busy = true;
				pay.disabled = true;
				self.clear();
				self.say(t.working);
				try {
					window.sessionStorage.setItem(STORE + self.formId, self.payment.token);
				} catch (e) { /* private browsing: the in-page result still works */ }
				var back = new URL(window.location.href);
				back.searchParams.set('qaf_payment_return', String(self.formId));
				stripe.confirmPayment({
					elements: elements,
					confirmParams: { return_url: back.toString() },
					redirect: 'if_required'
				}).then(function (result) {
					self.busy = false;
					pay.disabled = false;
					if (result.error) {
						// Stripe's message is written for the payer; the site still checks.
						self.refresh(result.error.message);
						return;
					}
					self.refresh();
				});
			});
		}).catch(function (err) {
			self.fail(err.message || t.network);
		});
	};

	Checkout.prototype.paypal = function (prov) {
		var self = this;
		var p = this.payment;
		this.say(t.loading);
		var src = 'https://www.paypal.com/sdk/js?client-id=' + encodeURIComponent(prov.key) +
			'&currency=' + encodeURIComponent(p.currency) +
			'&intent=' + (p.capture === 'manual' ? 'authorize' : 'capture') +
			'&components=buttons';
		var ready = window.paypal ? Promise.resolve() : loadScript(src);
		ready.then(function () {
			var box = el('div', { 'class': 'qaf-checkout-paypal' });
			self.panel.appendChild(box);
			return window.paypal.Buttons({
				createOrder: function () {
					self.clear();
					return post('start', { token: p.token, provider: 'paypal' }).then(function (reply) {
						if (reply.status && reply.status.done) {
							self.show(reply.status);
							throw new Error('done');
						}
						return reply.order_id;
					});
				},
				onApprove: function () {
					self.say(t.checking);
					// Approval is not payment: the site captures and checks.
					return post('finish', { token: p.token }).then(function (status) {
						self.show(status);
					}).catch(function (err) {
						self.fail(err.message || t.network);
					});
				},
				onCancel: function () {
					self.fail(t.paypalClosed);
				},
				onError: function (err) {
					if (err && err.message === 'done') {
						return;
					}
					self.fail(t.network);
				}
			}).render(box).then(function () {
				self.say(t.paypalReady);
			});
		}).catch(function () {
			self.fail(t.noScript);
		});
	};

	Checkout.prototype.refresh = function (providerMessage) {
		var self = this;
		this.say(t.checking);
		post('status', { token: this.payment.token }).then(function (status) {
			self.show(status, providerMessage);
		}).catch(function (err) {
			self.fail(err.message || t.network);
		});
	};

	Checkout.prototype.show = function (status, extra) {
		this.say(status.message);
		if (status.done) {
			this.panel.textContent = '';
			var choice = this.section.querySelector('.qaf-checkout-choice');
			if (choice) {
				choice.hidden = true;
			}
			try {
				window.sessionStorage.removeItem(STORE + this.formId);
			} catch (e) { /* nothing stored */ }
			if (status.receipt_url && /^https:\/\//.test(status.receipt_url)) {
				this.receipt.textContent = '';
				var a = el('a', { href: status.receipt_url, target: '_blank', rel: 'noopener' }, t.receipt);
				this.receipt.appendChild(a);
				this.receipt.hidden = false;
			}
			this.heading.focus();
			return;
		}
		if (status.status === 'failed' || status.status === 'cancelled') {
			this.fail(extra ? extra : status.message);
		} else if (extra) {
			this.fail(extra);
		} else {
			// Pending: offer a check rather than a second payment.
			this.retry.textContent = t.checkAgain;
			this.retry.hidden = false;
			var self = this;
			this.retry.onclick = function () {
				self.retry.onclick = null;
				self.retry.textContent = t.tryAgain;
				self.refresh();
			};
		}
	};

	// After a paid form is sent.
	document.addEventListener('qaf:submitted', function (event) {
		var result = event.detail || {};
		if (!result.payment || !result.payment.token) {
			return;
		}
		var form = event.target;
		var wrapper = form.closest('.qaf-form-wrapper') || form.parentNode;
		new Checkout(wrapper, result.payment, form.dataset.formId || '0');
	});

	// Back from a bank page or 3-D Secure after a redirect.
	document.addEventListener('DOMContentLoaded', function () {
		var params = new URLSearchParams(window.location.search);
		var formId = params.get('qaf_payment_return');
		if (!formId) {
			return;
		}
		var token = null;
		try {
			token = window.sessionStorage.getItem(STORE + formId);
		} catch (e) { /* no storage */ }
		var form = document.querySelector('.qaf-form[data-form-id="' + formId.replace(/[^0-9]/g, '') + '"]');
		if (!token || !form) {
			return;
		}
		var wrapper = form.closest('.qaf-form-wrapper') || form.parentNode;
		// A status other than pending makes the checkout ask the site at once.
		new Checkout(wrapper, { token: token, spoken: '', providers: [], status: 'checking', returned: true }, formId);
	});
}());
