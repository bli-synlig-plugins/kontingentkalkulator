(function () {
	var i18n = (window.KKAdmin && window.KKAdmin.i18n) || {};
	var defaults = (window.KKAdmin && window.KKAdmin.defaults) || {};
	var form = document.getElementById('kk-admin-form');
	if (!form) {
		return;
	}

	var insurances = [];
	var editId = null;
	var previewMode = 'monthly';
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
	}

	function nextId() {
		return 'ins_' + Date.now().toString(36) + Math.random().toString(36).slice(2, 7);
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
		document.querySelectorAll('#kk-preview [data-kk-hl="' + key + '"]').forEach(function (node) {
			if (node.matches('button')) {
				return;
			}
			node.textContent = value;
			node.hidden = value === '' && (node.matches('p') || node.matches('span.kk-hero__suffix'));
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
			if (hourly != null && hours != null && hourly >= 0 && hours > 0) {
				salary = hourly * hours * 52 / 12;
			}
		} else {
			salary = number($('kk-preview-monthly') && $('kk-preview-monthly').value);
			if (salary != null && salary < 0) {
				salary = null;
			}
		}

		var items = activeInsurances();
		var insSum = insuranceSum();
		var insRow = preview.querySelector('[data-kk-preview-row="insurance"]');
		var insLabel = textValue('label_insurance');
		if (insRow) {
			insRow.hidden = !(items.length && insLabel);
			setOut('insurance', formatSigned(insSum, 2));
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
			['reell', 'salary', 'dues', 'deduction', 'yearly'].forEach(function (key) {
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
		setOut('yearly', format(Math.round(reell * 12), 0));
	}

	function draw() {
		syncHidden();
		drawList();
		drawPreview();
	}

	function highlight(key) {
		document.querySelectorAll('#kk-preview .kk-admin-hl').forEach(function (node) {
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
			document.querySelectorAll('#kk-preview [data-kk-hl="' + name + '"]').forEach(function (node) {
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
			document.querySelectorAll('[data-kk-tab]').forEach(function (other) {
				other.setAttribute('aria-selected', other === button ? 'true' : 'false');
			});
			document.querySelectorAll('.kk-admin-panel').forEach(function (panel) {
				panel.hidden = panel.id !== 'kk-panel-' + tab;
			});
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
		if (event.target.closest('#kk-ins-form')) {
			return;
		}
		if (event.target.id === 'kk-insurances') {
			return;
		}
		markDirty();
	});

	form.addEventListener('focusin', function (event) {
		var target = event.target;
		if (!target || !form.contains(target)) {
			return;
		}
		if (target.closest('#kk-preview')) {
			return;
		}
		if (target.closest('#kk-ins-form') || target.closest('#kk-ins-list')) {
			highlight('insurance');
			return;
		}
		var key = target.getAttribute('data-kk-admin');
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

		var infoBtn = event.target.closest('[data-kk-preview-info]');
		if (infoBtn && $('kk-preview').contains(infoBtn)) {
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

	form.addEventListener('submit', function () {
		syncHidden();
	});

	loadInsurances();
	if (/[?&]settings-updated=true/.test(window.location.search)) {
		setStatus('saved');
	}
	draw();
})();
