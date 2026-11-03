(function () {
	var bound = typeof WeakSet === 'function' ? new WeakSet() : null;

	function configFrom(root) {
		var global = window.KKSettings || {};
		return {
			sats: number(root.getAttribute('data-kk-sats')) || number(global.sats) || 0,
			fradragssats: number(root.getAttribute('data-kk-fradrag')) || number(global.fradragssats) || 0,
			arsgrense: number(root.getAttribute('data-kk-arsgrense')) || number(global.arsgrense) || 0,
			insurances: Array.isArray(global.insurances) ? global.insurances : [],
		};
	}

	function number(value) {
		if (value == null) {
			return null;
		}
		if (typeof value === 'number') {
			return Number.isFinite(value) ? value : null;
		}
		var raw = String(value).trim().replace(/\u00a0/g, '').replace(/\s/g, '').replace(',', '.');
		if (raw === '' || Number.isNaN(Number(raw))) {
			return null;
		}
		var parsed = Number(raw);
		return Number.isFinite(parsed) ? parsed : null;
	}

	function format(value, decimals) {
		if (value == null || !Number.isFinite(value)) {
			return '–';
		}
		return new Intl.NumberFormat('nb-NO', {
			minimumFractionDigits: decimals,
			maximumFractionDigits: decimals,
		}).format(value);
	}

	function formatSalary(value) {
		if (value == null || !Number.isFinite(value)) {
			return '–';
		}
		var decimals = Math.abs(value - Math.round(value)) < 0.005 ? 0 : 2;
		return format(value, decimals);
	}

	function formatSigned(value, decimals) {
		if (value == null || !Number.isFinite(value)) {
			return '–';
		}
		var formatted = format(Math.abs(value), decimals);
		return value > 0 ? '−' + formatted : formatted;
	}

	function compute(input, rates) {
		var salary;
		var hoursMonth = null;
		if (input.mode === 'hourly') {
			if (input.hourly == null || input.hours == null || input.hourly < 0 || input.hours <= 0) {
				return null;
			}
			hoursMonth = input.hours * 52 / 12;
			salary = input.hourly * hoursMonth;
		} else if (input.monthly == null || input.monthly < 0) {
			return null;
		} else {
			salary = input.monthly;
		}

		var dues = salary * rates.sats;
		var yearlyDues = dues * 12;
		var yearlyDeduction = Math.min(yearlyDues, rates.arsgrense) * rates.fradragssats;
		var insurance = insuranceSum(rates.insurances);
		var reell = (yearlyDues - yearlyDeduction) / 12 - insurance;

		return {
			salary: salary,
			dues: dues,
			deduction: yearlyDeduction / 12,
			insurance: insurance,
			reell: reell,
			yearly: reell * 12,
			hourly: hoursMonth && hoursMonth > 0 ? reell / hoursMonth : null,
		};
	}

	function insuranceSum(items) {
		if (!items || !items.length) {
			return 0;
		}
		return items.reduce(function (sum, item) {
			var price = number(item && item.price);
			return sum + (price != null && price > 0 ? price : 0);
		}, 0);
	}

	function insuranceInfoText(items) {
		if (!items || !items.length) {
			return '';
		}
		return items.map(function (item) {
			var price = number(item && item.price);
			if (price == null) {
				price = 0;
			}
			var decimals = Math.abs(price - Math.round(price)) < 0.005 ? 0 : 2;
			var line = (item && item.name ? item.name : '') + ': ' + format(price, decimals) + ' kr/mnd';
			if (item && item.description) {
				line += '\n' + item.description;
			}
			return line;
		}).join('\n');
	}

	function escapeHtml(value) {
		return String(value)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function readInput(root) {
		var box = root.querySelector('[data-kk-input]');
		if (!box) {
			return { mode: 'monthly', monthly: null, hourly: null, hours: null };
		}
		var mode = box.getAttribute('data-kk-mode') === 'hourly' ? 'hourly' : 'monthly';
		return {
			mode: mode,
			monthly: number(fieldValue(box, 'monthly')),
			hourly: number(fieldValue(box, 'hourly')),
			hours: number(fieldValue(box, 'hours')),
		};
	}

	function fieldValue(box, name) {
		var field = box.querySelector('[data-kk-field="' + name + '"]');
		return field ? field.value : '';
	}

	function setText(root, key, value) {
		root.querySelectorAll('[data-kk-out="' + key + '"]').forEach(function (node) {
			node.textContent = value;
		});
	}

	function emptyResult(root) {
		['reell', 'salary', 'dues', 'deduction', 'yearly'].forEach(function (key) {
			setText(root, key, '–');
		});
	}

	function renderInsurance(root, items, amount) {
		var row = root.querySelector('[data-kk-row="insurance"]');
		if (!row) {
			return;
		}
		var has = items && items.length > 0;
		row.hidden = !has;
		if (!has) {
			return;
		}
		setText(root, 'insurance', formatSigned(amount, 2));
		var info = row.querySelector('.kk-info__text');
		if (info) {
			info.innerHTML = escapeHtml(insuranceInfoText(items)).replace(/\n/g, '<br>');
		}
	}

	function render(root) {
		var rates = configFrom(root);
		var items = rates.insurances;
		var insurance = insuranceSum(items);
		renderInsurance(root, items, insurance);
		var input = readInput(root);
		var result = compute(input, rates);
		if (!result) {
			emptyResult(root);
			return;
		}
		setText(root, 'reell', format(Math.round(result.reell), 0));
		setText(root, 'salary', formatSalary(result.salary));
		setText(root, 'dues', format(result.dues, 2));
		setText(root, 'deduction', formatSigned(result.deduction, 2));
		setText(root, 'yearly', format(Math.round(result.yearly), 0));
	}

	function toggleInfo(button) {
		var expanded = button.getAttribute('aria-expanded') === 'true';
		var panelId = button.getAttribute('aria-controls');
		var panel = panelId ? document.getElementById(panelId) : null;
		button.setAttribute('aria-expanded', expanded ? 'false' : 'true');
		if (panel) {
			panel.hidden = expanded;
		}
	}

	function setMode(box, mode) {
		box.setAttribute('data-kk-mode', mode);
		box.querySelectorAll('[data-kk-set-mode]').forEach(function (button) {
			button.setAttribute('aria-pressed', button.getAttribute('data-kk-set-mode') === mode ? 'true' : 'false');
		});
		box.querySelectorAll('[data-kk-fields]').forEach(function (group) {
			group.hidden = group.getAttribute('data-kk-fields') !== mode;
		});
	}

	function bind(root) {
		if (!root || (bound && bound.has(root)) || root.getAttribute('data-kk-ready') === '1') {
			return;
		}
		if (bound) {
			bound.add(root);
		}
		root.setAttribute('data-kk-ready', '1');

		root.addEventListener('input', function (event) {
			if (event.target && event.target.getAttribute('data-kk-field')) {
				render(root);
			}
		});

		root.addEventListener('click', function (event) {
			var infoBtn = event.target.closest('[data-kk-info]');
			if (infoBtn && root.contains(infoBtn)) {
				event.preventDefault();
				toggleInfo(infoBtn);
				return;
			}

			var button = event.target.closest('[data-kk-set-mode]');
			if (!button || !root.contains(button)) {
				return;
			}
			event.preventDefault();
			var box = root.querySelector('[data-kk-input]');
			if (!box) {
				return;
			}
			setMode(box, button.getAttribute('data-kk-set-mode') === 'hourly' ? 'hourly' : 'monthly');
			render(root);
		});

		render(root);
	}

	function scan(context) {
		(context || document).querySelectorAll('.kk-calculator').forEach(bind);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () {
			scan(document);
		});
	} else {
		scan(document);
	}

	if (typeof MutationObserver === 'function') {
		new MutationObserver(function (mutations) {
			mutations.forEach(function (mutation) {
				mutation.addedNodes.forEach(function (node) {
					if (node.nodeType !== 1) {
						return;
					}
					if (node.classList && node.classList.contains('kk-calculator')) {
						bind(node);
					}
					if (node.querySelectorAll) {
						scan(node);
					}
				});
			});
		}).observe(document.documentElement, { childList: true, subtree: true });
	}
})();
