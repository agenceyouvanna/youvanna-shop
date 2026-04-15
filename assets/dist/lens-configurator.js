/*!
 * yv-shop lens configurator - vanilla JS state machine.
 * Loads spec from REST, walks steps based on user choices, posts validated config to cart.
 */
(function (w, d) {
    'use strict';

    var state = {
        spec: null,
        prices: null,
        fittingbox: null,
        config: {},
        history: [],
        currentStep: null,
        product: null,
        extra: 0,
        uploaded: null,
    };

    function cfg() {
        return w.yvShopLens || {};
    }

    function fmt(n) {
        var opts = w.yvShop || {};
        var sym = opts.currency_symbol || '€';
        return (parseFloat(n) || 0).toFixed(2).replace('.', ',') + ' ' + sym;
    }

    function resolvePath(cur) {
        var ct = state.config.correction_type || '';
        var lt = state.config.lens_type || '';
        if (ct === 'progressive') return 'progressive_range';
        if (lt === 'tinted') {
            if (cur === 'lens_type') return 'tinted_polarized';
            if (cur === 'thinning') return 'tint_color';
            if (cur === 'progressive_range') return 'tint_color';
        }
        if (lt === 'clear') {
            if (cur === 'lens_type') return 'clear_range';
            if (cur === 'thinning') return 'coating_clear';
            if (cur === 'progressive_range') return 'coating_clear';
        }
        return 'pupillary_distance';
    }

    function nextStep(currentId, chosenOption) {
        var step = state.spec.steps[currentId];
        if (!step) return null;
        var nextId = null;
        if (chosenOption && chosenOption.next) {
            nextId = chosenOption.next;
        } else if (step.next) {
            nextId = step.next;
        }
        if (nextId === '@path') {
            nextId = resolvePath(currentId);
        }
        if (nextId === 'finish') return '__summary__';
        return nextId;
    }

    function recomputeExtra() {
        var total = 0;
        if (!state.spec) return 0;
        Object.keys(state.config).forEach(function (stepId) {
            var step = state.spec.steps[stepId];
            if (!step || !step.options) return;
            var val = state.config[stepId];
            step.options.forEach(function (opt) {
                if (opt.id === val) total += parseFloat(opt.price || 0);
            });
        });
        state.extra = total;
        return total;
    }

    function updateRunningTotal() {
        recomputeExtra();
        var base = parseFloat((state.product && state.product.price) || 0);
        var el = d.querySelector('[data-lens-running-total]');
        if (el) el.textContent = fmt(base + state.extra);
    }

    function updateProgress() {
        var bar = d.querySelector('[data-lens-progress]');
        if (!bar) return;
        var crumbs = state.history.concat([state.currentStep]);
        var ratio = Math.min(0.95, crumbs.length / 8);
        bar.style.width = (ratio * 100) + '%';
    }

    function setBreadcrumb(label) {
        var el = d.querySelector('[data-lens-breadcrumb]');
        if (!el) return;
        el.textContent = label || '';
    }

    function showPanel(type) {
        d.querySelectorAll('.yv-lens-step').forEach(function (s) {
            s.hidden = (s.getAttribute('data-step-type') !== type);
        });
    }

    function renderOptions(step) {
        var host = d.querySelector('[data-lens-options]');
        host.innerHTML = '';
        (step.options || []).forEach(function (opt) {
            var btn = d.createElement('button');
            btn.type = 'button';
            btn.className = 'yv-lens-option';
            btn.setAttribute('data-lens-option', opt.id);
            var pricePart = opt.price && opt.price > 0 ? '<span class="yv-lens-option__price">+ ' + fmt(opt.price) + '</span>' : '';
            btn.innerHTML = '<span class="yv-lens-option__label">' + opt.label + '</span>' + pricePart;
            btn.addEventListener('click', function () {
                state.config[state.currentStep] = opt.id;
                state.history.push(state.currentStep);
                var next = nextStep(state.currentStep, opt);
                goTo(next);
            });
            host.appendChild(btn);
        });
    }

    function goTo(stepId) {
        if (!stepId) return;
        state.currentStep = stepId;

        if (stepId === '__summary__') {
            renderSummary();
            return;
        }

        var step = state.spec.steps[stepId];
        if (!step) return;

        var type = step.type || 'options';
        setBreadcrumb(step.label || '');

        var titleEl = d.querySelector('[data-lens-step-title]');
        var hintEl = d.querySelector('[data-lens-step-hint]');
        if (titleEl) titleEl.textContent = step.label || '';
        if (hintEl) {
            hintEl.textContent = step.hint || '';
            hintEl.hidden = !step.hint;
        }

        showPanel(type);
        if (type === 'options') renderOptions(step);
        if (type === 'prescription') bindPrescription();
        if (type === 'pupillary_distance') bindPd();

        d.querySelector('[data-lens-back]').hidden = state.history.length === 0;
        d.querySelector('[data-lens-next]').hidden = (type === 'options');
        d.querySelector('[data-lens-add]').hidden = true;
        updateRunningTotal();
        updateProgress();
    }

    function bindPrescription() {
        d.querySelectorAll('[data-lens-tab]').forEach(function (b) {
            b.onclick = function () {
                var tab = b.getAttribute('data-lens-tab');
                d.querySelectorAll('[data-lens-tab]').forEach(function (x) { x.classList.toggle('is-active', x === b); });
                d.querySelectorAll('[data-lens-tab-panel]').forEach(function (p) {
                    p.classList.toggle('is-active', p.getAttribute('data-lens-tab-panel') === tab);
                });
            };
        });
        var fileInput = d.querySelector('#yv-lens-file');
        if (fileInput && !fileInput.dataset.bound) {
            fileInput.dataset.bound = '1';
            fileInput.addEventListener('change', onFileChosen);
        }
    }

    function bindPd() {
        d.querySelectorAll('[data-lens-pd-tab]').forEach(function (b) {
            b.onclick = function () {
                var tab = b.getAttribute('data-lens-pd-tab');
                d.querySelectorAll('[data-lens-pd-tab]').forEach(function (x) { x.classList.toggle('is-active', x === b); });
                d.querySelectorAll('[data-lens-pd-panel]').forEach(function (p) {
                    p.classList.toggle('is-active', p.getAttribute('data-lens-pd-panel') === tab);
                });
            };
        });
        var start = d.querySelector('[data-lens-pd-start]');
        if (start && !start.dataset.bound) {
            start.dataset.bound = '1';
            start.addEventListener('click', launchPdCamera);
        }
    }

    function launchPdCamera() {
        var slot = d.querySelector('[data-lens-pd-slot]');
        var key = state.fittingbox && state.fittingbox.pd_key;
        if (!key) {
            slot.hidden = false;
            slot.innerHTML = '<p class="yv-lens-error">' + (cfg().i18n_no_pd_key || 'Mesure caméra non disponible.') + '</p>';
            return;
        }
        slot.hidden = false;
        slot.innerHTML = '<div id="yv-pd-widget" style="min-height:320px"></div>';
        if (!w.FTB_PD) {
            var s = d.createElement('script');
            s.src = 'https://api.fittingbox.com/pd-measurement/v1/loader.js';
            s.async = true;
            s.onload = initPdWidget;
            d.head.appendChild(s);
        } else {
            initPdWidget();
        }
    }

    function initPdWidget() {
        if (!w.FTB_PD) return;
        try {
            w.FTB_PD.init({
                apiKey: state.fittingbox.pd_key,
                target: '#yv-pd-widget',
                onResult: function (pd) {
                    state.config.pupillary_distance = { value: parseFloat(pd.value), method: 'camera' };
                    d.querySelector('[data-lens-pd]').value = pd.value;
                }
            });
        } catch (e) {
            console.warn('FittingBox PD init failed', e);
        }
    }

    function onFileChosen(e) {
        var file = e.target.files && e.target.files[0];
        if (!file) return;
        var preview = d.querySelector('[data-lens-upload-preview]');
        preview.hidden = false;
        preview.textContent = (cfg().i18n_uploading || 'Envoi en cours...');
        var fd = new FormData();
        fd.append('file', file);
        fetch((w.yvShop.rest_url || '/wp-json/yv-shop/v1/') + 'lens/upload-prescription', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-WP-Nonce': w.yvShop.nonce },
            body: fd
        }).then(function (r) {
            if (!r.ok) return r.json().then(function (j) { throw new Error(j.message || 'upload_failed'); });
            return r.json();
        }).then(function (data) {
            state.uploaded = data;
            preview.innerHTML = '<span class="yv-lens-upload__ok">✓ ' + file.name + '</span><button type="button" class="yv-lens-upload__clear" aria-label="' + (cfg().i18n_remove || 'Retirer') + '">×</button>';
            preview.querySelector('.yv-lens-upload__clear').onclick = function () {
                state.uploaded = null;
                d.querySelector('#yv-lens-file').value = '';
                preview.hidden = true;
                preview.innerHTML = '';
            };
        }).catch(function (err) {
            preview.textContent = (cfg().i18n_upload_error || 'Échec : ') + (err.message || '');
        });
    }

    function collectPrescription() {
        var rx = { right_eye: {}, left_eye: {} };
        d.querySelectorAll('[data-lens-rx]').forEach(function (i) {
            var path = i.getAttribute('data-lens-rx').split('.');
            if (i.value !== '') rx[path[0]][path[1]] = parseFloat(i.value);
        });
        if (state.uploaded) {
            rx.attachment_id = state.uploaded.attachment_id;
            rx.attachment_url = state.uploaded.url;
        }
        return rx;
    }

    function collectPd() {
        var input = d.querySelector('[data-lens-pd]');
        if (!input) return null;
        var val = parseFloat(input.value);
        if (!val) return null;
        var existing = state.config.pupillary_distance;
        return { value: val, method: (existing && existing.method === 'camera') ? 'camera' : 'manual' };
    }

    function onNext() {
        var stepId = state.currentStep;
        var step = state.spec.steps[stepId];
        if (!step) return;
        var type = step.type || 'options';

        if (type === 'prescription') {
            state.config.prescription = collectPrescription();
        }
        if (type === 'pupillary_distance') {
            var pd = collectPd();
            if (!pd) {
                alert(cfg().i18n_pd_required || 'Renseigne ton écart pupillaire.');
                return;
            }
            state.config.pupillary_distance = pd;
        }

        state.history.push(stepId);
        var next = nextStep(stepId, null);
        goTo(next);
    }

    function onBack() {
        if (!state.history.length) return;
        var prev = state.history.pop();
        delete state.config[state.currentStep];
        goTo(prev);
    }

    function renderSummary() {
        showPanel('summary');
        setBreadcrumb(cfg().i18n_recap || 'Récapitulatif');
        var host = d.querySelector('[data-lens-summary]');
        host.innerHTML = '';

        var rows = [];
        Object.keys(state.config).forEach(function (stepId) {
            var step = state.spec.steps[stepId];
            if (!step) return;
            var val = state.config[stepId];
            if (step.options) {
                var match = step.options.find(function (o) { return o.id === val; });
                if (match) rows.push({ label: step.label, value: match.label, price: match.price });
            } else if (step.type === 'pupillary_distance' && val) {
                rows.push({ label: step.label, value: val.value + ' mm', price: 0 });
            } else if (step.type === 'prescription' && val) {
                rows.push({ label: step.label, value: val.attachment_id ? (cfg().i18n_photo || 'Photo envoyée') : (cfg().i18n_manual || 'Saisie manuelle'), price: 0 });
            }
        });

        rows.forEach(function (r) {
            var row = d.createElement('div');
            row.className = 'yv-lens-summary__row';
            var priceTxt = r.price > 0 ? '<span class="yv-lens-summary__price">+ ' + fmt(r.price) + '</span>' : '';
            row.innerHTML = '<span class="yv-lens-summary__label">' + r.label + '</span><span class="yv-lens-summary__value">' + r.value + '</span>' + priceTxt;
            host.appendChild(row);
        });

        recomputeExtra();
        var base = parseFloat((state.product && state.product.price) || 0);
        d.querySelector('[data-lens-total]').textContent = fmt(base + state.extra);

        d.querySelector('[data-lens-back]').hidden = false;
        d.querySelector('[data-lens-next]').hidden = true;
        d.querySelector('[data-lens-add]').hidden = false;
        updateProgress();
    }

    function onAdd() {
        if (!state.product) return;
        var btn = d.querySelector('[data-lens-add]');
        btn.disabled = true;

        fetch((w.yvShop.rest_url || '/wp-json/yv-shop/v1/') + 'lens/validate', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': w.yvShop.nonce },
            body: JSON.stringify({ config: state.config })
        }).then(function (r) { return r.json(); }).then(function (res) {
            if (!res || !res.valid) {
                btn.disabled = false;
                alert((cfg().i18n_invalid || 'Configuration incomplète : ') + (res && res.errors ? res.errors.map(function (e) { return e.field; }).join(', ') : ''));
                return;
            }
            var base = parseFloat(state.product.price) || 0;
            w.yvShopStore.add({
                product_id: state.product.id,
                variation_id: null,
                qty: 1,
                name: state.product.name,
                price: base + (parseFloat(res.extra_price) || 0),
                image: state.product.image,
                permalink: state.product.permalink,
                lens_config: state.config
            });
            close();
            d.dispatchEvent(new CustomEvent('yv-shop:added'));
            if (w.yvShopStore && w.yvShopStore.sync) w.yvShopStore.sync().catch(function () {});
            if (w.yvShopMiniCart && w.yvShopMiniCart.open) w.yvShopMiniCart.open();
        }).catch(function () {
            btn.disabled = false;
        });
    }

    function open(product) {
        state.product = product;
        state.config = {};
        state.history = [];
        state.uploaded = null;
        state.extra = 0;
        state.currentStep = null;
        d.querySelector('#yv-lens-modal').setAttribute('aria-hidden', 'false');
        d.documentElement.classList.add('yv-lens-open');

        if (state.spec) {
            goTo(state.spec.start || 'needs_correction');
            return;
        }

        fetch((w.yvShop.rest_url || '/wp-json/yv-shop/v1/') + 'lens/spec', {
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); }).then(function (data) {
            state.spec = data.spec;
            state.prices = data.prices;
            state.fittingbox = data.fittingbox;
            goTo(state.spec.start || 'needs_correction');
        });
    }

    function close() {
        var el = d.querySelector('#yv-lens-modal');
        if (!el) return;
        el.setAttribute('aria-hidden', 'true');
        d.documentElement.classList.remove('yv-lens-open');
    }

    function bindGlobal() {
        d.addEventListener('click', function (e) {
            var t = e.target;
            if (t.closest('[data-lens-close]')) { close(); return; }
            if (t.closest('[data-lens-next]')) { onNext(); return; }
            if (t.closest('[data-lens-back]')) { onBack(); return; }
            if (t.closest('[data-lens-add]')) { onAdd(); return; }
            var opener = t.closest('[data-lens-open]');
            if (opener) {
                e.preventDefault();
                open({
                    id: parseInt(opener.getAttribute('data-product-id'), 10),
                    name: opener.getAttribute('data-product-name') || '',
                    price: parseFloat(opener.getAttribute('data-product-price')) || 0,
                    image: opener.getAttribute('data-product-image') || '',
                    permalink: location.pathname
                });
            }
        });
        d.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') close();
        });
    }

    d.addEventListener('DOMContentLoaded', bindGlobal);

    w.yvShopLensModal = { open: open, close: close };

})(window, document);
