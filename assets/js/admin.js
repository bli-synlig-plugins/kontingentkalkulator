(function () {
	var i18n = (window.KKAdmin && window.KKAdmin.i18n) || {};
	var defaults = (window.KKAdmin && window.KKAdmin.defaults) || {};
	var form = document.getElementById('kk-admin-form');
	if (!form) {
		return;
	}

	var insurances = [];
	var benefitCategories = [];
	var editId = null;
	var editCatId = null;
	var editBenId = null;
	var editBenCatId = null;
	var previewMode = 'monthly';
	var previewKind = 'dues';
	var previewChecks = {};
	var currentTab = 'satser';
	var dirty = false;

	function $(id) {
		return document.getElementById(id);
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

	function formatPercent(value) {
		if (value == null || !Number.isFinite(value)) {
			return '';
		}
		var decimals = 0;
		var scaled = value;
		while (decimals < 2 && Math.abs(scaled - Math.round(scaled)) >= 0.05) {
			scaled *= 10;
			decimals += 1;
		}
		return format(value, decimals);
	}

	function escapeHtml(value) {
		return String(value)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function field(key) {
		return form.querySelector('[data-kk-admin="' + key + '"]');
	}

	function fieldValue(key) {
		var el = field(key);
		if (!el) {
			return '';
		}
		return String(el.value || '').trim();
	}

	function textValue(key) {
		var value = fieldValue(key);
		return value !== '' ? value : (defaults[key] || '');
	}

	function setStatus(kind) {
		var status = $('kk-admin-status');
		if (!status) {
			return;
		}
		status.classList.remove('is-dirty', 'is-ok');
		if (kind === 'dirty') {
			status.textContent = i18n.dirty || 'Du har ulagrede endringer';
			status.classList.add('is-dirty');
			return;
		}
		if (kind === 'saved') {
			status.textContent = i18n.saved || 'Endringene er lagret';
			status.classList.add('is-ok');
			return;
		}
		status.textContent = i18n.clean || 'Ingen endringer';
	}

	function markDirty() {
		previewChecks = {};
		if (dirty) {
			draw();
			return;
		}
		dirty = true;
		setStatus('dirty');
		draw();
	}

	function syncHidden() {
		var hidden = $('kk-insurances');
		if (hidden) {
			hidden.value = JSON.stringify(insurances);
		}
		var categories = $('kk-benefit-categories');
		if (categories) {
			categories.value = JSON.stringify(benefitCategories);
		}
	}

	function nextId() {
		return 'ins_' + Date.now().toString(36) + Math.random().toString(36).slice(2, 7);
	}

	function nextCatId() {
		return 'cat_' + Date.now().toString(36) + Math.random().toString(36).slice(2, 7);
	}

	function nextBenId() {
		return 'ben_' + Date.now().toString(36) + Math.random().toString(36).slice(2, 7);
	}

	function loadInsurances() {
		var hidden = $('kk-insurances');
		try {
			var parsed = JSON.parse(hidden && hidden.value ? hidden.value : '[]');
			insurances = Array.isArray(parsed) ? parsed : [];
		} catch (err) {
			insurances = [];
		}
	}

	function activeInsurances() {
		return insurances.filter(function (item) {
			return item && item.enabled !== false;
		});
	}

	function insuranceSum() {
		return activeInsurances().reduce(function (sum, item) {
			var price = number(item.price);
			return sum + (price != null && price > 0 ? price : 0);
		}, 0);
	}

	function insuranceInfoText() {
		return activeInsurances().map(function (item) {
			var price = number(item.price);
			if (price == null) {
				price = 0;
			}
			var decimals = Math.abs(price - Math.round(price)) < 0.005 ? 0 : 2;
			var line = (item.name || '') + ': ' + format(price, decimals) + ' kr/mnd';
			if (item.description) {
				line += '\n' + item.description;
			}
			return line;
		}).join('\n');
	}

	function interpolate(text) {
		if (!text || text.indexOf('{') === -1) {
			return text || '';
		}
		var sats = number(fieldValue('sats_percent'));
		var fradrag = number(fieldValue('fradrag_percent'));
		var arsgrense = number(fieldValue('arsgrense'));
		return text
			.replace(/\{sats\}/g, formatPercent(sats != null ? sats : 0))
			.replace(/\{fradrag\}/g, formatPercent(fradrag != null ? fradrag : 0))
			.replace(/\{arsgrense\}/g, format(arsgrense != null ? arsgrense : 0, 0));
	}

	function setPreviewText(key, value) {
		document.querySelectorAll('#kk-preview [data-kk-hl="' + key + '"], #kk-preview-benefits [data-kk-hl="' + key + '"]').forEach(function (node) {
			if (node.matches('button')) {
				return;
			}
			node.textContent = value;
			node.hidden = value === '' && (node.matches('p') || node.matches('span.kk-hero__suffix') || node.matches('p.kk-benefits__kicker'));
		});
	}

	function setPreviewKind(kind) {
		previewKind = kind === 'benefits' ? 'benefits' : 'dues';
		var dues = $('kk-preview');
		var bens = $('kk-preview-benefits');
		if (dues) {
			dues.hidden = previewKind !== 'dues';
		}
		if (bens) {
			bens.hidden = previewKind !== 'benefits';
		}
		document.querySelectorAll('[data-kk-preview-note]').forEach(function (note) {
			note.hidden = note.getAttribute('data-kk-preview-note') !== previewKind;
		});
	}

	function setOut(key, value) {
		document.querySelectorAll('#kk-preview [data-kk-preview-out="' + key + '"]').forEach(function (node) {
			node.textContent = value;
		});
	}

	function drawList() {
		var list = $('kk-ins-list');
		var count = document.querySelector('[data-kk-ins-count]');
		var total = document.querySelector('[data-kk-ins-total]');
		if (count) {
			count.textContent = String(insurances.length);
		}
		if (total) {
			total.textContent = format(insuranceSum(), 0) + ' ' + (i18n.perMonth || 'kr/mnd');
		}
		if (!list) {
			return;
		}
		if (!insurances.length) {
			list.innerHTML = '<div class="kk-ins-empty">' + escapeHtml(i18n.empty || '') + '</div>';
			return;
		}
		list.innerHTML = insurances.map(function (item) {
			var price = number(item.price);
			if (price == null) {
				price = 0;
			}
			var off = item.enabled === false ? ' is-off' : '';
			var desc = item.description ? '<p>' + escapeHtml(item.description) + '</p>' : '';
			return (
				'<div class="kk-ins-item' + off + '" data-kk-ins-id="' + escapeHtml(item.id) + '">' +
					'<div><h3>' + escapeHtml(item.name || '') + '</h3>' + desc + '</div>' +
					'<div class="kk-ins-item__price">' + format(price, Math.abs(price - Math.round(price)) < 0.005 ? 0 : 2) + ' ' + escapeHtml(i18n.perMonth || 'kr/mnd') + '</div>' +
					'<div class="kk-ins-item__actions">' +
						'<button type="button" class="button" data-kk-ins-act="toggle">' + escapeHtml(item.enabled === false ? (i18n.show || 'Vis') : (i18n.hide || 'Skjul')) + '</button>' +
						'<button type="button" class="button" data-kk-ins-act="edit">' + escapeHtml(i18n.edit || 'Rediger') + '</button>' +
						'<button type="button" class="button" data-kk-ins-act="delete">' + escapeHtml(i18n.delete || 'Slett') + '</button>' +
					'</div>' +
				'</div>'
			);
		}).join('');
	}

	function drawPreview() {
		var preview = $('kk-preview');
		if (!preview) {
			return;
		}

		['input_title', 'input_help', 'toggle_heading', 'label_monthly_mode', 'label_hourly_mode', 'label_monthly', 'label_hourly', 'label_hours', 'suffix_kr', 'suffix_hours', 'result_title', 'hero_suffix', 'caption', 'label_salary', 'label_dues', 'label_deduction', 'label_insurance', 'label_yearly', 'row_suffix', 'note'].forEach(function (key) {
			setPreviewText(key, textValue(key));
		});

		preview.querySelectorAll('[data-kk-preview-mode]').forEach(function (button) {
			button.setAttribute('aria-pressed', button.getAttribute('data-kk-preview-mode') === previewMode ? 'true' : 'false');
		});
		preview.querySelectorAll('[data-kk-preview-fields]').forEach(function (group) {
			group.hidden = group.getAttribute('data-kk-preview-fields') !== previewMode;
		});

		var satsPercent = number(fieldValue('sats_percent'));
		var fradragPercent = number(fieldValue('fradrag_percent'));
		var arsgrense = number(fieldValue('arsgrense'));
		var salary = null;
		if (previewMode === 'hourly') {
			var hourly = number($('kk-preview-hourly') && $('kk-preview-hourly').value);
			var hours = number($('kk-preview-hours') && $('kk-preview-hours').value);
			if (hourly != null && hours != null && hourly > 0 && hours > 0) {
				salary = hourly * hours * 52 / 12;
			}
		} else {
			salary = number($('kk-preview-monthly') && $('kk-preview-monthly').value);
			if (salary != null && salary <= 0) {
				salary = null;
			}
		}

		var items = activeInsurances();
		var insSum = insuranceSum();
		var insRow = preview.querySelector('[data-kk-preview-row="insurance"]');
		var insLabel = textValue('label_insurance');
		if (insRow) {
			insRow.hidden = !(items.length && insLabel);
			var insInfo = preview.querySelector('[data-kk-preview-info-text="insurance"]');
			if (insInfo) {
				insInfo.innerHTML = escapeHtml(insuranceInfoText()).replace(/\n/g, '<br>');
			}
			var insBtn = preview.querySelector('[data-kk-preview-info="insurance"]');
			if (insBtn) {
				insBtn.hidden = !insuranceInfoText();
			}
		}

		['info_dues', 'info_deduction', 'info_yearly'].forEach(function (key) {
			var text = interpolate(textValue(key));
			var node = preview.querySelector('[data-kk-preview-info-text="' + key + '"]');
			var button = preview.querySelector('[data-kk-preview-info="' + key + '"]');
			if (node) {
				node.innerHTML = escapeHtml(text).replace(/\n/g, '<br>');
			}
			if (button) {
				button.hidden = text === '';
			}
		});

		if (salary == null || satsPercent == null || fradragPercent == null || arsgrense == null) {
			['reell', 'salary', 'dues', 'deduction', 'insurance', 'yearly'].forEach(function (key) {
				setOut(key, '–');
			});
			return;
		}

		var dues = salary * (satsPercent / 100);
		var yearlyDues = dues * 12;
		var yearlyDeduction = Math.min(yearlyDues, arsgrense) * (fradragPercent / 100);
		var reell = (yearlyDues - yearlyDeduction) / 12 - insSum;

		setOut('reell', format(Math.round(reell), 0));
		setOut('salary', formatSalary(salary));
		setOut('dues', format(dues, 2));
		setOut('deduction', formatSigned(yearlyDeduction / 12, 2));
		setOut('insurance', formatSigned(insSum, 2));
		setOut('yearly', format(Math.round(reell * 12), 0));
	}

	function loadBenefitCategories() {
		var hidden = $('kk-benefit-categories');
		try {
			var parsed = JSON.parse(hidden && hidden.value ? hidden.value : '[]');
			benefitCategories = Array.isArray(parsed) ? parsed : [];
		} catch (err) {
			benefitCategories = [];
		}
	}

	function findCategory(id) {
		return benefitCategories.find(function (item) {
			return item.id === id;
		});
	}

	function findBenefit(catId, benId) {
		var cat = findCategory(catId);
		if (!cat || !Array.isArray(cat.items)) {
			return null;
		}
		return cat.items.find(function (item) {
			return item.id === benId;
		}) || null;
	}

	function resolveExclusiveDefaults(items) {
		var seen = {};
		return items.map(function (item) {
			var copy = Object.assign({}, item);
			var group = String(copy.exclusive_group || '').trim();
			if (group && copy.default_on) {
				if (seen[group]) {
					copy.default_on = false;
				} else {
					seen[group] = true;
				}
			}
			return copy;
		});
	}

	function activeBenefitCategories() {
		return benefitCategories.filter(function (cat) {
			return cat && cat.enabled !== false;
		}).map(function (cat) {
			var items = (Array.isArray(cat.items) ? cat.items : []).filter(function (item) {
				return item && item.enabled !== false;
			});
			return {
				id: cat.id,
				name: cat.name,
				description: cat.description,
				link_text: cat.link_text,
				link_url: cat.link_url,
				items: resolveExclusiveDefaults(items),
			};
		}).filter(function (cat) {
			return cat.items.length > 0;
		});
	}

	function itemOn(item) {
		if (Object.prototype.hasOwnProperty.call(previewChecks, item.id)) {
			return !!previewChecks[item.id];
		}
		return !!item.default_on;
	}

	function paragraphsHtml(text) {
		var raw = String(text || '').trim();
		if (!raw) {
			return '';
		}
		return raw.split(/\n\s*\n/).map(function (part) {
			return '<p>' + escapeHtml(part.trim()).replace(/\n/g, '<br>') + '</p>';
		}).join('');
	}

	function splitParagraphs(text) {
		var raw = String(text || '').trim();
		if (!raw) {
			return [''];
		}
		var parts = raw.split(/\n\s*\n/).map(function (part) {
			return part.trim();
		}).filter(function (part) {
			return part !== '';
		});
		return parts.length ? parts : [''];
	}

	function readParagraphs() {
		var wrap = $('kk-cat-paragraphs');
		if (!wrap) {
			return '';
		}
		return Array.prototype.map.call(wrap.querySelectorAll('[data-kk-cat-para]'), function (field) {
			return String(field.value || '').trim();
		}).filter(function (part) {
			return part !== '';
		}).join('\n\n');
	}

	function updateParagraphRemoveButtons() {
		var wrap = $('kk-cat-paragraphs');
		if (!wrap) {
			return;
		}
		var rows = wrap.querySelectorAll('.kk-cat-para');
		rows.forEach(function (row) {
			var button = row.querySelector('[data-kk-para-remove]');
			if (button) {
				button.hidden = rows.length < 2;
			}
		});
	}

	function addParagraphField(value, focus) {
		var wrap = $('kk-cat-paragraphs');
		if (!wrap) {
			return;
		}
		var row = document.createElement('div');
		row.className = 'kk-cat-para';
		row.innerHTML =
			'<textarea data-kk-cat-para rows="3" placeholder="' + escapeHtml(i18n.paragraphPlaceholder || '') + '"></textarea>' +
			'<button type="button" class="button kk-cat-para__remove" data-kk-para-remove>' + escapeHtml(i18n.removeParagraph || 'Fjern avsnitt') + '</button>';
		var field = row.querySelector('[data-kk-cat-para]');
		field.value = value || '';
		wrap.appendChild(row);
		updateParagraphRemoveButtons();
		if (focus) {
			field.focus();
		}
	}

	function setParagraphFields(text) {
		var wrap = $('kk-cat-paragraphs');
		if (!wrap) {
			return;
		}
		wrap.innerHTML = '';
		splitParagraphs(text).forEach(function (part) {
			addParagraphField(part, false);
		});
	}

	function benefitItemHtml(catId, item) {
		var amount = number(item.amount);
		if (amount == null) {
			amount = 0;
		}
		var off = item.enabled === false ? ' is-off' : '';
		var desc = item.description ? '<p>' + escapeHtml(item.description) + '</p>' : '';
		var flags = [];
		if (item.default_on) {
			flags.push(i18n.defaultOn || 'Huket av som standard');
		}
		if (item.exclusive_group) {
			flags.push((i18n.exclusive || 'Eksklusiv gruppe') + ': ' + item.exclusive_group);
		}
		var meta = flags.length ? '<p class="kk-ben-item__meta">' + escapeHtml(flags.join(' · ')) + '</p>' : '';
		return (
			'<div class="kk-ben-item' + off + '" data-kk-ben-id="' + escapeHtml(item.id) + '" data-kk-cat-id="' + escapeHtml(catId) + '">' +
				'<div><h4>' + escapeHtml(item.name || '') + '</h4>' + desc + meta + '</div>' +
				'<div class="kk-ins-item__price">' + format(amount, Math.abs(amount - Math.round(amount)) < 0.005 ? 0 : 2) + ' ' + escapeHtml(i18n.perYear || 'kr/år') + '</div>' +
				'<div class="kk-ins-item__actions">' +
					'<button type="button" class="button" data-kk-ben-act="toggle">' + escapeHtml(item.enabled === false ? (i18n.show || 'Vis') : (i18n.hide || 'Skjul')) + '</button>' +
					'<button type="button" class="button" data-kk-ben-act="edit">' + escapeHtml(i18n.edit || 'Rediger') + '</button>' +
					'<button type="button" class="button" data-kk-ben-act="delete">' + escapeHtml(i18n.delete || 'Slett') + '</button>' +
				'</div>' +
			'</div>'
		);
	}

	function drawBenefitList() {
		var list = $('kk-ben-list');
		var count = document.querySelector('[data-kk-ben-count]');
		if (count) {
			count.textContent = String(benefitCategories.length);
		}
		if (!list) {
			return;
		}
		if (!benefitCategories.length) {
			list.innerHTML = '<div class="kk-ins-empty">' + escapeHtml(i18n.emptyCategories || '') + '</div>';
			return;
		}
		list.innerHTML = benefitCategories.map(function (cat) {
			var off = cat.enabled === false ? ' is-off' : '';
			var items = Array.isArray(cat.items) ? cat.items : [];
			var itemsHtml = items.length
				? items.map(function (item) {
					return benefitItemHtml(cat.id, item);
				}).join('')
				: '<div class="kk-ben-empty">' + escapeHtml(i18n.emptyBenefits || '') + '</div>';
			var desc = cat.description ? '<div class="kk-ben-cat__copy">' + paragraphsHtml(cat.description) + '</div>' : '';
			var link = cat.link_url ? '<p class="kk-ben-cat__link">' + escapeHtml(cat.link_text || cat.link_url) + '</p>' : '';
			return (
				'<div class="kk-ben-cat' + off + '" data-kk-cat-id="' + escapeHtml(cat.id) + '">' +
					'<div class="kk-ben-cat__head">' +
						'<div><h3>' + escapeHtml(cat.name || '') + '</h3>' + desc + link + '</div>' +
						'<div class="kk-ins-item__actions">' +
							'<button type="button" class="button" data-kk-cat-act="add">' + escapeHtml(i18n.addBenefit || 'Legg til fordel') + '</button>' +
							'<button type="button" class="button" data-kk-cat-act="toggle">' + escapeHtml(cat.enabled === false ? (i18n.show || 'Vis') : (i18n.hide || 'Skjul')) + '</button>' +
							'<button type="button" class="button" data-kk-cat-act="edit">' + escapeHtml(i18n.edit || 'Rediger') + '</button>' +
							'<button type="button" class="button" data-kk-cat-act="delete">' + escapeHtml(i18n.delete || 'Slett') + '</button>' +
						'</div>' +
					'</div>' +
					'<div class="kk-ben-cat__items">' + itemsHtml + '</div>' +
				'</div>'
			);
		}).join('');
	}

	function formatMoney(value) {
		var amount = number(value);
		if (amount == null) {
			amount = 0;
		}
		var decimals = Math.abs(amount - Math.round(amount)) < 0.005 ? 0 : 2;
		return format(amount, decimals);
	}

	function drawBenefitsPreview() {
		var root = $('kk-preview-benefits');
		var body = $('kk-preview-benefits-body');
		if (!root || !body) {
			return;
		}

		setPreviewText('benefits_title', textValue('benefits_title'));
		setPreviewText('benefits_currency', textValue('benefits_currency'));

		var currency = textValue('benefits_currency') || 'kr';
		var cats = activeBenefitCategories();
		var grand = 0;

		if (!cats.length) {
			body.innerHTML = '<p class="kk-benefits__empty">' + escapeHtml(i18n.emptyCategories || '') + '</p>';
			var emptyTotal = root.querySelector('[data-kk-ben-total]');
			if (emptyTotal) {
				emptyTotal.textContent = format(0, 0);
			}
			return;
		}

		body.innerHTML = cats.map(function (cat) {
			var catSum = 0;
			var rows = cat.items.map(function (item) {
				var on = itemOn(item);
				var amount = number(item.amount);
				if (amount == null) {
					amount = 0;
				}
				if (on) {
					catSum += amount;
				}
				var group = String(item.exclusive_group || '').trim();
				var infoBtn = item.description
					? '<button type="button" class="kk-info__btn" data-kk-preview-ben-info="' + escapeHtml(item.id) + '" aria-expanded="false">i</button>'
					: '';
				var infoText = item.description
					? '<p class="kk-info__text" data-kk-preview-ben-info-text="' + escapeHtml(item.id) + '" hidden>' + escapeHtml(item.description).replace(/\n/g, '<br>') + '</p>'
					: '';
				return (
					'<div class="kk-ben-row" data-kk-ben-row data-kk-ben-id="' + escapeHtml(item.id) + '" data-kk-group="' + escapeHtml(group) + '" data-kk-amount="' + amount + '">' +
						'<div class="kk-ben-row__main">' +
							'<span class="kk-ben-row__name">' + escapeHtml(item.name || '') + '</span>' +
							infoBtn +
							'<span class="kk-ben-row__amount"' + (on ? '' : ' hidden') + '>' + escapeHtml(currency) + ' ' + formatMoney(amount) + '</span>' +
							'<button type="button" class="kk-switch" role="switch" aria-checked="' + (on ? 'true' : 'false') + '" data-kk-preview-ben-switch aria-label="' + escapeHtml(item.name || '') + '"></button>' +
						'</div>' +
						infoText +
					'</div>'
				);
			}).join('');
			grand += catSum;
			var chevron = '<svg style="width:24px;height:24px" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--primary-color)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="wpicons wpicons--lucide lucide lucide-chevron-right-icon lucide-chevron-right wpicons-inline"><path d="m9 18 6-6-6-6"></path></svg>';
			var link = cat.link_url
				? '<p class="kk-benefits__link"><a href="' + escapeHtml(cat.link_url) + '">' + escapeHtml(cat.link_text || cat.link_url) + chevron + '</a></p>'
				: '';
			return (
				'<section class="kk-benefits__cat" data-kk-ben-cat="' + escapeHtml(cat.id) + '">' +
					'<div class="kk-benefits__cat-head">' +
						'<p class="kk-benefits__cat-title">' + escapeHtml(cat.name || '') + '</p>' +
						'<span class="kk-benefits__cat-sum">' + escapeHtml(currency) + ' ' + formatMoney(catSum) + '</span>' +
					'</div>' +
					'<div class="kk-benefits__cat-copy">' + paragraphsHtml(cat.description) + link + '</div>' +
					'<div class="kk-benefits__rows">' + rows + '</div>' +
				'</section>'
			);
		}).join('');

		var totalNode = root.querySelector('[data-kk-ben-total]');
		if (totalNode) {
			totalNode.textContent = format(Math.round(grand), 0);
		}
	}

	function togglePreviewBenefit(row) {
		if (!row) {
			return;
		}
		var id = row.getAttribute('data-kk-ben-id');
		var group = String(row.getAttribute('data-kk-group') || '').trim();
		var switchBtn = row.querySelector('[data-kk-preview-ben-switch]');
		var next = !(switchBtn && switchBtn.getAttribute('aria-checked') === 'true');
		previewChecks[id] = next;
			if (next && group) {
			var cat = row.closest('.kk-benefits__cat');
			if (cat) {
				cat.querySelectorAll('[data-kk-ben-row]').forEach(function (other) {
					if (String(other.getAttribute('data-kk-group') || '').trim() !== group) {
						return;
					}
					var otherId = other.getAttribute('data-kk-ben-id');
					if (otherId && otherId !== id) {
						previewChecks[otherId] = false;
					}
				});
			}
		}
		drawBenefitsPreview();
	}

	function showCatForm(item) {
		hideBenForm();
		editCatId = item ? item.id : null;
		$('kk-cat-name').value = item ? item.name : '';
		setParagraphFields(item ? item.description : '');
		$('kk-cat-link-text').value = item ? (item.link_text || '') : '';
		$('kk-cat-link-url').value = item ? (item.link_url || '') : '';
		$('kk-cat-name-error').hidden = true;
		$('kk-cat-save').textContent = item ? (i18n.saveCategory || 'Lagre kategori') : (i18n.addCategory || 'Legg til kategori');
		$('kk-cat-form').hidden = false;
		$('kk-cat-name').focus();
	}

	function hideCatForm() {
		editCatId = null;
		$('kk-cat-form').hidden = true;
	}

	function showBenForm(catId, item) {
		hideCatForm();
		var cat = findCategory(catId);
		if (!cat) {
			return;
		}
		editBenCatId = catId;
		editBenId = item ? item.id : null;
		$('kk-ben-name').value = item ? item.name : '';
		$('kk-ben-description').value = item ? item.description : '';
		$('kk-ben-amount').value = item && item.amount != null ? item.amount : '';
		$('kk-ben-group').value = item ? (item.exclusive_group || '') : '';
		$('kk-ben-default').checked = !!(item && item.default_on);
		$('kk-ben-name-error').hidden = true;
		$('kk-ben-amount-error').hidden = true;
		$('kk-ben-save').textContent = item ? (i18n.saveBenefit || 'Lagre fordel') : (i18n.addBenefit || 'Legg til fordel');
		var context = $('kk-ben-form-context');
		if (context) {
			context.textContent = (item ? (i18n.saveBenefit || 'Lagre fordel') : (i18n.addBenefit || 'Legg til fordel')) + ': ' + (cat.name || '');
			context.hidden = false;
		}
		$('kk-ben-form').hidden = false;
		$('kk-ben-name').focus();
	}

	function hideBenForm() {
		editBenId = null;
		editBenCatId = null;
		$('kk-ben-form').hidden = true;
		var context = $('kk-ben-form-context');
		if (context) {
			context.hidden = true;
		}
	}

	function draw() {
		syncHidden();
		drawList();
		drawBenefitList();
		drawPreview();
		drawBenefitsPreview();
	}

	function highlight(key) {
		document.querySelectorAll('#kk-preview .kk-admin-hl, #kk-preview-benefits .kk-admin-hl').forEach(function (node) {
			node.classList.remove('kk-admin-hl');
		});
		if (!key) {
			return;
		}
		var keys = [key];
		if (key === 'info_dues') {
			keys.push('label_dues');
		} else if (key === 'info_deduction') {
			keys.push('label_deduction');
		} else if (key === 'info_yearly') {
			keys.push('label_yearly');
		} else if (key === 'insurance') {
			keys = ['label_insurance'];
		}
		keys.forEach(function (name) {
			document.querySelectorAll('#kk-preview [data-kk-hl="' + name + '"], #kk-preview-benefits [data-kk-hl="' + name + '"]').forEach(function (node) {
				node.classList.add('kk-admin-hl');
			});
		});
	}

	function showInsForm(item) {
		editId = item ? item.id : null;
		$('kk-ins-name').value = item ? item.name : '';
		$('kk-ins-description').value = item ? item.description : '';
		$('kk-ins-price').value = item && item.price != null ? item.price : '';
		$('kk-ins-name-error').hidden = true;
		$('kk-ins-price-error').hidden = true;
		$('kk-ins-save').textContent = item ? (i18n.saveItem || 'Lagre forsikring') : (i18n.add || 'Legg til');
		$('kk-ins-form').hidden = false;
		$('kk-ins-name').focus();
	}

	function hideInsForm() {
		editId = null;
		$('kk-ins-form').hidden = true;
	}

	function findInsurance(id) {
		return insurances.find(function (item) {
			return item.id === id;
		});
	}

	document.querySelectorAll('[data-kk-tab]').forEach(function (button) {
		button.addEventListener('click', function () {
			var tab = button.getAttribute('data-kk-tab');
			currentTab = tab || 'satser';
			document.querySelectorAll('[data-kk-tab]').forEach(function (other) {
				other.setAttribute('aria-selected', other === button ? 'true' : 'false');
			});
			document.querySelectorAll('.kk-admin-panel').forEach(function (panel) {
				panel.hidden = panel.id !== 'kk-panel-' + tab;
			});
			setPreviewKind(tab === 'fordeler' ? 'benefits' : 'dues');
		});
	});

	form.addEventListener('input', function (event) {
		if (!event.target) {
			return;
		}
		if (event.target.closest('#kk-preview')) {
			drawPreview();
			return;
		}
		if (event.target.closest('#kk-ins-form') || event.target.closest('#kk-cat-form') || event.target.closest('#kk-ben-form')) {
			return;
		}
		if (event.target.id === 'kk-insurances' || event.target.id === 'kk-benefit-categories') {
			return;
		}
		if (event.target.closest('#kk-preview-benefits')) {
			return;
		}
		markDirty();
	});

	form.addEventListener('focusin', function (event) {
		var target = event.target;
		if (!target || !form.contains(target)) {
			return;
		}
		if (target.closest('#kk-preview') || target.closest('#kk-preview-benefits')) {
			return;
		}
		if (target.closest('#kk-ins-form') || target.closest('#kk-ins-list')) {
			setPreviewKind('dues');
			highlight('insurance');
			return;
		}
		if (target.closest('#kk-cat-form') || target.closest('#kk-ben-form') || target.closest('#kk-ben-list')) {
			setPreviewKind('benefits');
			return;
		}
		var key = target.getAttribute('data-kk-admin');
		if (key === 'benefits_title' || key === 'benefits_currency') {
			setPreviewKind('benefits');
		} else if (currentTab !== 'fordeler' && key) {
			setPreviewKind('dues');
		}
		highlight(key || null);
	});

	form.addEventListener('focusout', function () {
		window.setTimeout(function () {
			if (!form.contains(document.activeElement)) {
				highlight(null);
			}
		}, 0);
	});

	form.addEventListener('click', function (event) {
		var chip = event.target.closest('[data-kk-chip]');
		if (chip) {
			event.preventDefault();
			var textarea = $(chip.getAttribute('data-kk-for'));
			var token = chip.getAttribute('data-kk-chip');
			if (textarea && token) {
				var start = textarea.selectionStart != null ? textarea.selectionStart : textarea.value.length;
				var end = textarea.selectionEnd != null ? textarea.selectionEnd : start;
				textarea.setRangeText(token, start, end, 'end');
				textarea.focus();
				markDirty();
			}
			return;
		}

		var paraRemove = event.target.closest('[data-kk-para-remove]');
		if (paraRemove) {
			event.preventDefault();
			var paraRow = paraRemove.closest('.kk-cat-para');
			var wrap = $('kk-cat-paragraphs');
			if (paraRow && wrap && wrap.querySelectorAll('.kk-cat-para').length > 1) {
				paraRow.remove();
				updateParagraphRemoveButtons();
			}
			return;
		}

		var previewBenefits = $('kk-preview-benefits');
		if (previewBenefits && event.target.closest('#kk-preview-benefits a')) {
			event.preventDefault();
			return;
		}

		var benInfoBtn = event.target.closest('[data-kk-preview-ben-info]');
		if (benInfoBtn && previewBenefits && previewBenefits.contains(benInfoBtn)) {
			event.preventDefault();
			var benRow = benInfoBtn.closest('[data-kk-ben-row]');
			var benPanel = benRow ? benRow.querySelector('.kk-info__text') : null;
			var benExpanded = benInfoBtn.getAttribute('aria-expanded') === 'true';
			benInfoBtn.setAttribute('aria-expanded', benExpanded ? 'false' : 'true');
			if (benPanel) {
				benPanel.hidden = benExpanded;
			}
			return;
		}

		var benSwitch = event.target.closest('[data-kk-preview-ben-switch]');
		if (benSwitch && previewBenefits && previewBenefits.contains(benSwitch)) {
			event.preventDefault();
			togglePreviewBenefit(benSwitch.closest('[data-kk-ben-row]'));
			return;
		}

		var infoBtn = event.target.closest('[data-kk-preview-info]');
		if (infoBtn && $('kk-preview') && $('kk-preview').contains(infoBtn)) {
			event.preventDefault();
			var infoKey = infoBtn.getAttribute('data-kk-preview-info');
			var panel = $('kk-preview').querySelector('[data-kk-preview-info-text="' + infoKey + '"]');
			var expanded = infoBtn.getAttribute('aria-expanded') === 'true';
			infoBtn.setAttribute('aria-expanded', expanded ? 'false' : 'true');
			if (panel) {
				panel.hidden = expanded;
			}
			return;
		}

		var modeBtn = event.target.closest('[data-kk-preview-mode]');
		if (modeBtn) {
			event.preventDefault();
			previewMode = modeBtn.getAttribute('data-kk-preview-mode') === 'hourly' ? 'hourly' : 'monthly';
			drawPreview();
			return;
		}

		var insAct = event.target.closest('[data-kk-ins-act]');
		if (insAct) {
			event.preventDefault();
			var wrap = insAct.closest('[data-kk-ins-id]');
			var item = wrap ? findInsurance(wrap.getAttribute('data-kk-ins-id')) : null;
			if (!item) {
				return;
			}
			var act = insAct.getAttribute('data-kk-ins-act');
			if (act === 'edit') {
				showInsForm(item);
				return;
			}
			if (act === 'toggle') {
				item.enabled = item.enabled === false;
				markDirty();
				return;
			}
			if (act === 'delete') {
				var message = (i18n.deleteConfirm || 'Slette «%s»?').replace('%s', item.name);
				if (window.confirm(message)) {
					insurances = insurances.filter(function (entry) {
						return entry.id !== item.id;
					});
					if (editId === item.id) {
						hideInsForm();
					}
					markDirty();
				}
			}
			return;
		}

		var catAct = event.target.closest('[data-kk-cat-act]');
		if (catAct) {
			event.preventDefault();
			var catWrap = catAct.closest('[data-kk-cat-id]');
			var cat = catWrap ? findCategory(catWrap.getAttribute('data-kk-cat-id')) : null;
			if (!cat) {
				return;
			}
			var catAction = catAct.getAttribute('data-kk-cat-act');
			if (catAction === 'add') {
				showBenForm(cat.id);
				return;
			}
			if (catAction === 'edit') {
				showCatForm(cat);
				return;
			}
			if (catAction === 'toggle') {
				cat.enabled = cat.enabled === false;
				markDirty();
				return;
			}
			if (catAction === 'delete') {
				var catMessage = (i18n.deleteCategoryConfirm || 'Slette «%s» og alle fordelene i kategorien?').replace('%s', cat.name);
				if (window.confirm(catMessage)) {
					benefitCategories = benefitCategories.filter(function (entry) {
						return entry.id !== cat.id;
					});
					if (editCatId === cat.id) {
						hideCatForm();
					}
					if (editBenCatId === cat.id) {
						hideBenForm();
					}
					markDirty();
				}
			}
			return;
		}

		var benAct = event.target.closest('[data-kk-ben-act]');
		if (benAct) {
			event.preventDefault();
			var benWrap = benAct.closest('[data-kk-ben-id]');
			var benCatId = benWrap ? benWrap.getAttribute('data-kk-cat-id') : '';
			var benefit = benWrap ? findBenefit(benCatId, benWrap.getAttribute('data-kk-ben-id')) : null;
			if (!benefit) {
				return;
			}
			var benAction = benAct.getAttribute('data-kk-ben-act');
			if (benAction === 'edit') {
				showBenForm(benCatId, benefit);
				return;
			}
			if (benAction === 'toggle') {
				benefit.enabled = benefit.enabled === false;
				markDirty();
				return;
			}
			if (benAction === 'delete') {
				var benMessage = (i18n.deleteConfirm || 'Slette «%s»?').replace('%s', benefit.name);
				if (window.confirm(benMessage)) {
					var parent = findCategory(benCatId);
					if (parent && Array.isArray(parent.items)) {
						parent.items = parent.items.filter(function (entry) {
							return entry.id !== benefit.id;
						});
					}
					if (editBenId === benefit.id) {
						hideBenForm();
					}
					markDirty();
				}
			}
		}
	});

	form.addEventListener('keydown', function (event) {
		if (event.key !== 'Enter') {
			return;
		}
		if (event.target && event.target.closest('#kk-ins-form')) {
			event.preventDefault();
			$('kk-ins-save').click();
		}
		if (event.target && event.target.closest('#kk-cat-form') && !event.target.closest('[data-kk-cat-para]')) {
			event.preventDefault();
			$('kk-cat-save').click();
		}
		if (event.target && event.target.closest('#kk-ben-form') && event.target.id !== 'kk-ben-description') {
			event.preventDefault();
			$('kk-ben-save').click();
		}
	});

	$('kk-ins-add').addEventListener('click', function () {
		showInsForm();
	});

	$('kk-ins-cancel').addEventListener('click', function () {
		hideInsForm();
	});

	$('kk-ins-save').addEventListener('click', function () {
		var name = $('kk-ins-name').value.trim();
		var description = $('kk-ins-description').value.trim();
		var price = number($('kk-ins-price').value);
		$('kk-ins-name-error').hidden = !!name;
		$('kk-ins-price-error').hidden = price != null;
		if (!name || price == null) {
			return;
		}
		if (editId) {
			var current = findInsurance(editId);
			if (current) {
				current.name = name;
				current.description = description;
				current.price = price;
			}
		} else {
			insurances.push({
				id: nextId(),
				name: name,
				description: description,
				price: price,
				enabled: true,
			});
		}
		hideInsForm();
		markDirty();
	});

	$('kk-cat-add').addEventListener('click', function () {
		showCatForm();
	});

	if ($('kk-cat-para-add')) {
		$('kk-cat-para-add').addEventListener('click', function () {
			addParagraphField('', true);
		});
	}

	$('kk-cat-cancel').addEventListener('click', function () {
		hideCatForm();
	});

	$('kk-cat-save').addEventListener('click', function () {
		var name = $('kk-cat-name').value.trim();
		$('kk-cat-name-error').hidden = !!name;
		if (!name) {
			return;
		}
		if (editCatId) {
			var currentCat = findCategory(editCatId);
			if (currentCat) {
				currentCat.name = name;
				currentCat.description = readParagraphs();
				currentCat.link_text = $('kk-cat-link-text').value.trim();
				currentCat.link_url = $('kk-cat-link-url').value.trim();
			}
		} else {
			benefitCategories.push({
				id: nextCatId(),
				name: name,
				description: readParagraphs(),
				link_text: $('kk-cat-link-text').value.trim(),
				link_url: $('kk-cat-link-url').value.trim(),
				enabled: true,
				items: [],
			});
		}
		hideCatForm();
		markDirty();
	});

	$('kk-ben-cancel').addEventListener('click', function () {
		hideBenForm();
	});

	$('kk-ben-save').addEventListener('click', function () {
		var name = $('kk-ben-name').value.trim();
		var amount = number($('kk-ben-amount').value);
		$('kk-ben-name-error').hidden = !!name;
		$('kk-ben-amount-error').hidden = amount != null;
		if (!name || amount == null) {
			return;
		}
		var parent = findCategory(editBenCatId);
		if (!parent) {
			return;
		}
		if (!Array.isArray(parent.items)) {
			parent.items = [];
		}
		if (editBenId) {
			var currentBen = findBenefit(editBenCatId, editBenId);
			if (currentBen) {
				currentBen.name = name;
				currentBen.description = $('kk-ben-description').value.trim();
				currentBen.amount = amount;
				currentBen.exclusive_group = $('kk-ben-group').value.trim();
				currentBen.default_on = !!$('kk-ben-default').checked;
			}
		} else {
			parent.items.push({
				id: nextBenId(),
				name: name,
				description: $('kk-ben-description').value.trim(),
				amount: amount,
				exclusive_group: $('kk-ben-group').value.trim(),
				default_on: !!$('kk-ben-default').checked,
				enabled: true,
			});
		}
		hideBenForm();
		markDirty();
	});

	form.addEventListener('submit', function () {
		syncHidden();
	});

	loadInsurances();
	loadBenefitCategories();
	if (/[?&]settings-updated=true/.test(window.location.search)) {
		setStatus('saved');
	}
	draw();
})();
