/* ===== Cancel Option 2 Buy — הזמנה סיטונאית (פרונט) ===== */
(function () {
    'use strict';

    if (typeof CO2BW === 'undefined') { return; }
    var S = CO2BW.strings || {};

    /* ---- עזר: POST ל-endpoint של wc_ajax ---- */
    function post(url, data) {
        var fd = new FormData();
        fd.append('nonce', CO2BW.nonce);
        Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
        return fetch(url, { method: 'POST', credentials: 'same-origin', body: fd })
            .then(function (r) { return r.json(); });
    }

    /* ---- בורר כמות (± ) ---- */
    document.addEventListener('click', function (e) {
        var inc = e.target.closest('[data-co2b-inc]');
        var dec = e.target.closest('[data-co2b-dec]');
        if (!inc && !dec) { return; }
        var box = e.target.closest('[data-co2b-qty]');
        if (!box) { return; }
        var input = box.querySelector('.co2b-ws-qty-input');
        if (!input) { return; }
        var val = parseInt(input.value, 10) || 1;
        val = dec ? Math.max(1, val - 1) : val + 1;
        input.value = val;
        input.dispatchEvent(new Event('co2b:qtychange', { bubbles: true }));
    });

    /* ---- עדכון באדג' הכפתור הצף ---- */
    function setCount(n) {
        var fab = document.getElementById('co2b-ws-fab');
        var badge = document.getElementById('co2b-ws-fab-badge');
        if (badge) { badge.textContent = n; }
        if (fab) { fab.classList.toggle('is-visible', n > 0); }
    }

    /* ---- הוספה להזמנה (עמוד מוצר / רשימה / קטלוג) ---- */
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-co2b-add]');
        if (!btn) { return; }
        e.preventDefault();
        var pid = btn.getAttribute('data-pid');
        var qtyInput = btn.parentNode ? btn.parentNode.querySelector('.co2b-ws-qty-input') : null;
        var qty = qtyInput ? (parseInt(qtyInput.value, 10) || 1) : 1;
        btn.disabled = true;
        post(CO2BW.ajax.add, { pid: pid, qty: qty }).then(function (res) {
            btn.disabled = false;
            if (res && res.success) {
                setCount(res.data.count);
                var label = btn.querySelector('span');
                if (label) {
                    var old = label.textContent;
                    btn.classList.add('is-added');
                    label.textContent = '✓ ' + S.added;
                    setTimeout(function () { label.textContent = old; btn.classList.remove('is-added'); }, 1600);
                }
            } else {
                alert((res && res.data && res.data.message) || S.error);
            }
        }).catch(function () { btn.disabled = false; alert(S.error); });
    });

    /* ---- עמוד העגלה: עדכון/מחיקה ---- */
    var cartWrap = document.getElementById('co2b-ws-cartwrap');
    if (cartWrap) {
        var cartEl = document.getElementById('co2b-ws-cart');
        var sumEl = document.getElementById('co2b-ws-summary');
        var debounces = {};

        function applyFragment(res) {
            if (!res || !res.success) { return; }
            if (cartEl) { cartEl.innerHTML = res.data.cart_html; }
            if (sumEl) { sumEl.innerHTML = res.data.totals_html; }
            setCount(res.data.count);
            syncSubmitState();
        }

        /* שינוי כמות בשורה */
        cartWrap.addEventListener('co2b:qtychange', function (e) {
            var row = e.target.closest('.co2b-ws-row');
            if (!row) { return; }
            var pid = row.getAttribute('data-pid');
            var qty = parseInt(e.target.value, 10) || 1;
            clearTimeout(debounces[pid]);
            debounces[pid] = setTimeout(function () {
                post(CO2BW.ajax.update, { pid: pid, qty: qty }).then(applyFragment);
            }, 300);
        });
        cartWrap.addEventListener('change', function (e) {
            if (e.target.classList.contains('co2b-ws-cart-qty')) {
                e.target.dispatchEvent(new Event('co2b:qtychange', { bubbles: true }));
            }
        });

        /* מחיקת שורה */
        cartWrap.addEventListener('click', function (e) {
            var rm = e.target.closest('[data-co2b-remove]');
            if (!rm) { return; }
            var row = rm.closest('.co2b-ws-row');
            if (!row) { return; }
            post(CO2BW.ajax.remove, { pid: row.getAttribute('data-pid') }).then(applyFragment);
        });

        /* ---- טופס שליחה ---- */
        var form = document.getElementById('co2b-ws-form');
        var errEl = document.getElementById('co2b-ws-form-error');
        var submitBtn = document.getElementById('co2b-ws-submit');

        function syncSubmitState() {
            var box = sumEl ? sumEl.querySelector('.co2b-ws-sumbox') : null;
            if (!box || !submitBtn) { return; }
            var ok = box.getAttribute('data-meets-min') === '1' && box.getAttribute('data-empty') === '0';
            submitBtn.disabled = !ok;
        }
        syncSubmitState();

        function showError(msg) {
            if (!errEl) { return; }
            errEl.textContent = msg;
            errEl.hidden = false;
        }

        if (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                if (errEl) { errEl.hidden = true; }

                var fields = ['full_name', 'phone', 'email', 'invoice_name', 'address', 'city'];
                var data = {};
                var invalid = false;
                fields.forEach(function (name) {
                    var el = form.querySelector('[name="' + name + '"]');
                    var v = el ? el.value.trim() : '';
                    data[name] = v;
                    if (el) {
                        el.classList.toggle('co2b-ws-invalid', v === '');
                        if (v === '') { invalid = true; }
                    }
                });
                var ackS = document.getElementById('co2b-ack-shipping');
                var ackU = document.getElementById('co2b-ack-unloading');
                if (invalid) { showError(S.required); return; }
                if (!(ackS && ackS.checked) || !(ackU && ackU.checked)) { showError(S.confirmReq); return; }

                data.ack_shipping = '1';
                data.ack_unloading = '1';

                submitBtn.disabled = true;
                submitBtn.textContent = '...שולח';
                post(CO2BW.ajax.submit, data).then(function (res) {
                    if (res && res.success) {
                        cartWrap.innerHTML =
                            '<div class="co2b-ws-done">' +
                            '<div class="co2b-ws-done-check">✓</div>' +
                            '<h3>' + (res.data.message || '') + '</h3>' +
                            '<p>מספר הזמנה: <strong>' + res.data.number + '</strong></p>' +
                            '</div>';
                        setCount(0);
                        window.scrollTo({ top: cartWrap.offsetTop - 40, behavior: 'smooth' });
                    } else {
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'שליחת ההזמנה הסיטונאית';
                        showError((res && res.data && res.data.message) || S.error);
                    }
                }).catch(function () {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'שליחת ההזמנה הסיטונאית';
                    showError(S.error);
                });
            });
        }
    }

    /* אתחול באדג' */
    setCount(parseInt(CO2BW.count, 10) || 0);
})();
