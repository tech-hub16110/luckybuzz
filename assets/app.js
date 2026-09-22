(function () {
    'use strict';

    // ---- Audio Sound Synthesizer (Zero external dependencies) ----
    var audioCtx = null;
    function getAudioCtx() {
        if (!audioCtx && (window.AudioContext || window.webkitAudioContext)) {
            audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        }
        if (audioCtx && audioCtx.state === 'suspended') {
            audioCtx.resume();
        }
        return audioCtx;
    }

    function playSound(type) {
        try {
            if (navigator.vibrate) {
                if (type === 'lucky') {
                    navigator.vibrate([15, 30, 20]);
                } else {
                    navigator.vibrate(10);
                }
            }

            var ctx = getAudioCtx();
            if (!ctx) return;
            var osc = ctx.createOscillator();
            var gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);

            var now = ctx.currentTime;
            if (type === 'tap') {
                osc.type = 'sine';
                osc.frequency.setValueAtTime(480, now);
                osc.frequency.exponentialRampToValueAtTime(720, now + 0.06);
                gain.gain.setValueAtTime(0.12, now);
                gain.gain.linearRampToValueAtTime(0.01, now + 0.06);
                osc.start(now);
                osc.stop(now + 0.06);
            } else if (type === 'lucky') {
                osc.type = 'triangle';
                osc.frequency.setValueAtTime(440, now);
                osc.frequency.exponentialRampToValueAtTime(880, now + 0.15);
                gain.gain.setValueAtTime(0.18, now);
                gain.gain.linearRampToValueAtTime(0.01, now + 0.15);
                osc.start(now);
                osc.stop(now + 0.15);
            } else if (type === 'backspace') {
                osc.type = 'sine';
                osc.frequency.setValueAtTime(320, now);
                osc.frequency.exponentialRampToValueAtTime(200, now + 0.08);
                gain.gain.setValueAtTime(0.1, now);
                gain.gain.linearRampToValueAtTime(0.01, now + 0.08);
                osc.start(now);
                osc.stop(now + 0.08);
            }
        } catch (e) {}
    }

    // ---- Draw Countdowns ----
    var cards = Array.prototype.slice.call(document.querySelectorAll('[data-draw-at]'));

    function fmt(ms) {
        if (ms <= 0) { return 'Drawing Now'; }
        var s = Math.floor(ms / 1000);
        var d = Math.floor(s / 86400);
        var h = Math.floor((s % 86400) / 3600);
        var m = Math.floor((s % 3600) / 60);
        var sec = s % 60;
        if (d > 0) { return d + 'd ' + h + 'h left'; }
        if (h > 0) { return h + 'h ' + m + 'm left'; }
        return m + 'm ' + String(sec).padStart(2, '0') + 's left';
    }

    function tick() {
        var now = Date.now();
        cards.forEach(function (card) {
            var slot = card.querySelector('[data-clock]');
            if (!slot) { return; }
            var target = new Date(card.getAttribute('data-draw-at')).getTime();
            slot.textContent = fmt(target - now);
        });
    }

    if (cards.length) {
        tick();
        setInterval(tick, 1000);
    }

    // ---- Shopping Cart Storage ----
    var CART_STORAGE_KEY = 'luckybuzz_cart_items';

    function loadCartFromStorage() {
        try {
            var raw = localStorage.getItem(CART_STORAGE_KEY);
            if (raw) {
                var parsed = JSON.parse(raw);
                if (Array.isArray(parsed)) {
                    return parsed;
                }
            }
        } catch (e) {}
        return [];
    }

    function saveCartToStorage(items) {
        try {
            localStorage.setItem(CART_STORAGE_KEY, JSON.stringify(items || []));
        } catch (e) {}
    }

    var cartItems = loadCartFromStorage();

    // ---- PLAY PAGE LOGIC ----
    var formSingle = document.getElementById('form-single');
    var baseTicketCost = formSingle ? parseInt(formSingle.getAttribute('data-cost') || '10', 10) : 10;

    // Mode Selector (Single vs Range)
    var tabSingle = document.getElementById('tab-single');
    var tabRange = document.getElementById('tab-range');
    var paneSingle = document.getElementById('mode-single-pane');
    var paneRange = document.getElementById('mode-range-pane');

    if (tabSingle && tabRange && paneSingle && paneRange) {
        tabSingle.addEventListener('click', function () {
            tabSingle.classList.add('active');
            tabRange.classList.remove('active');
            paneSingle.style.display = 'block';
            paneRange.style.display = 'none';
            playSound('tap');
        });

        tabRange.addEventListener('click', function () {
            tabRange.classList.add('active');
            tabSingle.classList.remove('active');
            paneRange.style.display = 'block';
            paneSingle.style.display = 'none';
            playSound('tap');
        });
    }

    // --- 1. SINGLE NUMBER LOTTERY BALL SLOTS & ON-SCREEN KEYPAD ---
    var pad = document.getElementById('digits');
    var ballSlots = pad ? Array.prototype.slice.call(pad.querySelectorAll('.ball-slot')) : [];
    var hiddenNumber = document.getElementById('number');
    var quickBtn = document.getElementById('quickpick');
    var keyLuckyBtn = document.getElementById('key-lucky-btn');
    var keyBackspaceBtn = document.getElementById('key-backspace-btn');
    var keypadBtns = Array.prototype.slice.call(document.querySelectorAll('.key-btn[data-key]'));

    var digitsArr = ['', '', '', '', ''];
    var currentActiveSlot = 0;

    function renderBallSlots() {
        ballSlots.forEach(function (slot, idx) {
            var val = digitsArr[idx];
            var display = slot.querySelector('.ball-display');
            var input = slot.querySelector('.digitbox');

            if (val !== '') {
                display.textContent = val;
                slot.classList.add('filled');
                if (input) input.value = val;
            } else {
                display.textContent = '?';
                slot.classList.remove('filled');
                if (input) input.value = '';
            }

            if (idx === currentActiveSlot) {
                slot.classList.add('active');
            } else {
                slot.classList.remove('active');
            }
        });

        var fullNum = digitsArr.join('');
        if (hiddenNumber) {
            hiddenNumber.value = fullNum;
        }
        updateSingleCalc();
    }

    // Slot click to change active focus
    ballSlots.forEach(function (slot, idx) {
        slot.addEventListener('click', function () {
            currentActiveSlot = idx;
            renderBallSlots();
            playSound('tap');
        });
    });

    // Keypad number taps
    keypadBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var key = btn.getAttribute('data-key');
            if (key !== null) {
                digitsArr[currentActiveSlot] = key;
                if (currentActiveSlot < 4) {
                    currentActiveSlot++;
                }
                renderBallSlots();
                playSound('tap');
            }
        });
    });

    // Keypad backspace
    if (keyBackspaceBtn) {
        keyBackspaceBtn.addEventListener('click', function () {
            if (digitsArr[currentActiveSlot] !== '') {
                digitsArr[currentActiveSlot] = '';
            } else if (currentActiveSlot > 0) {
                currentActiveSlot--;
                digitsArr[currentActiveSlot] = '';
            }
            renderBallSlots();
            playSound('backspace');
        });
    }

    // Lucky random number generator
    function generateLuckyNumbers() {
        for (var i = 0; i < 5; i++) {
            digitsArr[i] = String(Math.floor(Math.random() * 10));
        }
        currentActiveSlot = 4;
        renderBallSlots();
        playSound('lucky');
    }

    if (quickBtn) {
        quickBtn.addEventListener('click', generateLuckyNumbers);
    }
    if (keyLuckyBtn) {
        keyLuckyBtn.addEventListener('click', generateLuckyNumbers);
    }

    // Initialize with random numbers if empty
    if (pad) {
        generateLuckyNumbers();
    }

    // --- 2. MULTIPLIER (SEM) PRESETS & MODAL ---
    var currentSem = 5;
    var semInput = document.getElementById('sem-input');
    var semTiles = Array.prototype.slice.call(document.querySelectorAll('.sem-tile'));
    var calcSemSingle = document.getElementById('calc-sem-single');
    var calcTotalSingle = document.getElementById('calc-total-single');
    var btnSingleCostTag = document.getElementById('btn-single-cost-tag');

    function setMultiplier(val) {
        val = parseInt(val, 10);
        if (isNaN(val) || val < 5) val = 5;
        if (val > 95) val = 95;
        currentSem = val;

        if (semInput) semInput.value = currentSem;

        semTiles.forEach(function (tile) {
            var tileSem = parseInt(tile.getAttribute('data-sem'), 10);
            if (tileSem === currentSem) {
                tile.classList.add('active');
            } else {
                tile.classList.remove('active');
            }
        });

        updateSingleCalc();
    }

    semTiles.forEach(function (tile) {
        tile.addEventListener('click', function () {
            var val = tile.getAttribute('data-sem');
            setMultiplier(val);
            playSound('tap');
        });
    });

    function updateSingleCalc() {
        var total = 1 * currentSem * baseTicketCost;
        if (calcSemSingle) calcSemSingle.textContent = currentSem + ' SEM';
        if (calcTotalSingle) calcTotalSingle.textContent = '₹' + total.toLocaleString();
        if (btnSingleCostTag) btnSingleCostTag.textContent = '₹' + total.toLocaleString();
    }

    // Custom Multiplier Modal
    var semModal = document.getElementById('sem-modal');
    var openSemBtn = document.getElementById('open-sem-popup-single');
    var closeSemBtn = document.getElementById('sem-modal-close');
    var backdropSem = document.getElementById('sem-modal-backdrop');
    var applySemBtn = document.getElementById('sem-modal-apply');
    var modalSemNum = document.getElementById('modal-hero-sem-num');
    var modalSemInput = document.getElementById('modal-sem-input');
    var modalSemMinus = document.getElementById('modal-sem-minus');
    var modalSemPlus = document.getElementById('modal-sem-plus');
    var modalSemChips = Array.prototype.slice.call(document.querySelectorAll('.modal-sem-chip'));

    function openMultiplierModal() {
        if (!semModal) return;
        semModal.style.display = 'flex';
        updateModalSemDisplay(currentSem);
        playSound('tap');
    }

    function closeMultiplierModal() {
        if (!semModal) return;
        semModal.style.display = 'none';
    }

    function updateModalSemDisplay(val) {
        val = Math.max(5, Math.min(95, Math.round(val / 5) * 5));
        if (modalSemNum) modalSemNum.textContent = val;
        if (modalSemInput) modalSemInput.value = val;
    }

    if (openSemBtn) openSemBtn.addEventListener('click', openMultiplierModal);
    if (closeSemBtn) closeSemBtn.addEventListener('click', closeMultiplierModal);
    if (backdropSem) backdropSem.addEventListener('click', closeMultiplierModal);

    modalSemChips.forEach(function (chip) {
        chip.addEventListener('click', function () {
            var val = parseInt(chip.getAttribute('data-sem'), 10);
            updateModalSemDisplay(val);
            playSound('tap');
        });
    });

    if (modalSemMinus) {
        modalSemMinus.addEventListener('click', function () {
            var current = parseInt(modalSemInput ? modalSemInput.value : '5', 10);
            updateModalSemDisplay(current - 5);
            playSound('tap');
        });
    }

    if (modalSemPlus) {
        modalSemPlus.addEventListener('click', function () {
            var current = parseInt(modalSemInput ? modalSemInput.value : '5', 10);
            updateModalSemDisplay(current + 5);
            playSound('tap');
        });
    }

    if (applySemBtn) {
        applySemBtn.addEventListener('click', function () {
            var val = parseInt(modalSemInput ? modalSemInput.value : '5', 10);
            setMultiplier(val);
            closeMultiplierModal();
            playSound('tap');
        });
    }

    // --- 3. RANGE / SERIES MODE LOGIC ---
    var rangeFrom = document.getElementById('range-from');
    var rangeTo = document.getElementById('range-to');
    var semRangeTiles = Array.prototype.slice.call(document.querySelectorAll('.sem-range-tile'));
    var semRangeInput = document.getElementById('sem-range-input');
    var seriesChips = Array.prototype.slice.call(document.querySelectorAll('.preset-chip[data-series-count]'));
    var calcRangeCount = document.getElementById('calc-range-count');
    var calcRangeSem = document.getElementById('calc-range-sem');
    var calcTotalRange = document.getElementById('calc-total-range');
    var currentRangeSem = 5;

    function getRangeCount() {
        if (!rangeFrom || !rangeTo) return 0;
        var f = parseInt(rangeFrom.value, 10);
        var t = parseInt(rangeTo.value, 10);
        if (isNaN(f) || isNaN(t) || t < f) return 0;
        return (t - f + 1);
    }

    function updateRangeCalc() {
        var count = getRangeCount();
        var total = count * currentRangeSem * baseTicketCost;
        if (calcRangeCount) calcRangeCount.textContent = count + ' Tickets';
        if (calcRangeSem) calcRangeSem.textContent = currentRangeSem + ' SEM';
        if (calcTotalRange) calcTotalRange.textContent = '₹' + total.toLocaleString();
    }

    if (rangeFrom && rangeTo) {
        rangeFrom.addEventListener('input', updateRangeCalc);
        rangeTo.addEventListener('input', updateRangeCalc);
    }

    seriesChips.forEach(function (chip) {
        chip.addEventListener('click', function () {
            seriesChips.forEach(function (c) { c.classList.remove('active'); });
            chip.classList.add('active');
            var addCount = parseInt(chip.getAttribute('data-series-count'), 10);
            if (rangeFrom && rangeTo) {
                var start = parseInt(rangeFrom.value || '10001', 10);
                if (isNaN(start)) start = 10001;
                var end = start + addCount - 1;
                rangeTo.value = String(end).padStart(5, '0');
                updateRangeCalc();
            }
            playSound('tap');
        });
    });

    semRangeTiles.forEach(function (tile) {
        tile.addEventListener('click', function () {
            semRangeTiles.forEach(function (t) { t.classList.remove('active'); });
            tile.classList.add('active');
            currentRangeSem = parseInt(tile.getAttribute('data-sem'), 10) || 5;
            if (semRangeInput) semRangeInput.value = currentRangeSem;
            updateRangeCalc();
            playSound('tap');
        });
    });

    if (rangeFrom && rangeTo) {
        updateRangeCalc();
    }

    // --- 4. CART MODAL & MANAGEMENT ---
    var cartModal = document.getElementById('cart-modal');
    var closeCartBtn = document.getElementById('cart-modal-close');
    var backdropCart = document.getElementById('cart-modal-backdrop');
    var btnAddCartSingle = document.getElementById('btn-add-cart-single');
    var btnAddCartRange = document.getElementById('btn-add-cart-range');
    var cartEmptyView = document.getElementById('cart-modal-empty');
    var cartFilledView = document.getElementById('cart-modal-filled');
    var cartFooter = document.getElementById('cart-modal-footer');
    var cartItemsList = document.getElementById('cart-modal-items-list');
    var cartSubtotal = document.getElementById('cart-modal-subtotal');
    var cartItemsJson = document.getElementById('cart-items-json');
    var btnClearCart = document.getElementById('btn-modal-clear-cart');

    function openCartModal() {
        if (!cartModal) return;
        renderCartItems();
        cartModal.style.display = 'flex';
        playSound('tap');
    }

    function closeCartModal() {
        if (!cartModal) return;
        cartModal.style.display = 'none';
    }

    if (closeCartBtn) closeCartBtn.addEventListener('click', closeCartModal);
    if (backdropCart) backdropCart.addEventListener('click', closeCartModal);

    function renderCartItems() {
        if (!cartItemsList) return;
        if (cartItems.length === 0) {
            if (cartEmptyView) cartEmptyView.style.display = 'block';
            if (cartFilledView) cartFilledView.style.display = 'none';
            if (cartFooter) cartFooter.style.display = 'none';
            if (btnClearCart) btnClearCart.style.display = 'none';
            return;
        }

        if (cartEmptyView) cartEmptyView.style.display = 'none';
        if (cartFilledView) cartFilledView.style.display = 'block';
        if (cartFooter) cartFooter.style.display = 'block';
        if (btnClearCart) btnClearCart.style.display = 'inline-block';

        cartItemsList.innerHTML = '';
        var totalCost = 0;

        cartItems.forEach(function (item, index) {
            var cost = item.cost || (item.count * item.sem * baseTicketCost);
            totalCost += cost;

            var row = document.createElement('div');
            row.className = 'mini-ticket-card';
            row.style.marginBottom = '8px';
            row.innerHTML = '<div style="display: flex; align-items: center; gap: 8px;">' +
                '<strong>' + (item.label || item.number) + '</strong>' +
                '<span class="pill pill-amber">' + item.sem + 'x</span>' +
                '</div>' +
                '<div style="display: flex; align-items: center; gap: 10px;">' +
                '<span style="font-weight: 800; color: var(--gold); font-family: var(--font-mono);">₹' + cost.toLocaleString() + '</span>' +
                '<button type="button" class="del-cart-item" data-idx="' + index + '" style="background:none; border:none; color:var(--red); font-size:18px; cursor:pointer;">✕</button>' +
                '</div>';
            cartItemsList.appendChild(row);
        });

        if (cartSubtotal) cartSubtotal.textContent = '₹' + totalCost.toLocaleString();
        if (cartItemsJson) cartItemsJson.value = JSON.stringify(cartItems);

        // Delete button handlers
        var delBtns = cartItemsList.querySelectorAll('.del-cart-item');
        delBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var idx = parseInt(btn.getAttribute('data-idx'), 10);
                cartItems.splice(idx, 1);
                saveCartToStorage(cartItems);
                renderCartItems();
                playSound('backspace');
            });
        });
    }

    if (btnClearCart) {
        btnClearCart.addEventListener('click', function () {
            cartItems = [];
            saveCartToStorage(cartItems);
            renderCartItems();
            playSound('backspace');
        });
    }

    if (btnAddCartSingle) {
        btnAddCartSingle.addEventListener('click', function () {
            var num = digitsArr.join('');
            if (num.length !== 5) {
                alert('Please enter all 5 digits');
                return;
            }
            cartItems.push({
                type: 'single',
                number: num,
                count: 1,
                sem: currentSem,
                cost: 1 * currentSem * baseTicketCost
            });
            saveCartToStorage(cartItems);
            openCartModal();
        });
    }

    if (btnAddCartRange) {
        btnAddCartRange.addEventListener('click', function () {
            var count = getRangeCount();
            if (count <= 0 || count > 100) {
                alert('Range must contain between 1 and 100 tickets');
                return;
            }
            var f = rangeFrom.value;
            var t = rangeTo.value;
            cartItems.push({
                type: 'range',
                from: f,
                to: t,
                label: f + ' ➔ ' + t,
                count: count,
                sem: currentRangeSem,
                cost: count * currentRangeSem * baseTicketCost
            });
            saveCartToStorage(cartItems);
            openCartModal();
        });
    }

    // Direct Single Mode Form Submit validation
    if (formSingle) {
        formSingle.addEventListener('submit', function (e) {
            var num = digitsArr.join('');
            if (num.length !== 5) {
                e.preventDefault();
                alert('Please select all 5 digits first!');
            }
        });
    }

    // Direct Range Mode Buy Button
    var btnBuyRange = document.getElementById('btn-buy-range');
    if (btnBuyRange) {
        btnBuyRange.addEventListener('click', function () {
            var count = getRangeCount();
            if (count <= 0 || count > 100) {
                alert('Range must contain between 1 and 100 tickets');
                return;
            }
            var f = rangeFrom.value;
            var t = rangeTo.value;
            cartItems = [{
                type: 'range',
                from: f,
                to: t,
                label: f + ' ➔ ' + t,
                count: count,
                sem: currentRangeSem,
                cost: count * currentRangeSem * baseTicketCost
            }];
            saveCartToStorage(cartItems);
            openCartModal();
        });
    }

    // --- 5. ADMIN CONSOLE SIDEBAR MOBILE TOGGLE ---
    var adminMenuToggle = document.getElementById('admin-menu-toggle');
    var adminSidebar = document.getElementById('admin-sidebar');
    if (adminMenuToggle && adminSidebar) {
        var backdrop = document.createElement('div');
        backdrop.className = 'sidebar-backdrop';
        document.body.appendChild(backdrop);

        adminMenuToggle.addEventListener('click', function () {
            adminSidebar.classList.toggle('open');
            backdrop.classList.toggle('active');
            playSound('tap');
        });

        backdrop.addEventListener('click', function () {
            adminSidebar.classList.remove('open');
            backdrop.classList.remove('active');
        });
    }

    // --- 6. LOTTERY SAMBAD DRAW RESULTS MODAL ---
    var sambadModal = document.getElementById('sambad-draw-modal');
    var sambadSearchInput = document.getElementById('sambad-ticket-search');
    var sambadSearchStatus = document.getElementById('sambad-search-status');

    function closeSambadModal() {
        if (!sambadModal) return;
        sambadModal.style.display = 'none';
        sambadModal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    function renderPrizeChips(containerId, countId, numbers, chipClass) {
        var container = document.getElementById(containerId);
        var countEl = document.getElementById(countId);
        if (!container) return;

        container.innerHTML = '';
        var nums = Array.isArray(numbers) ? numbers : [];
        if (countEl) {
            countEl.textContent = nums.length + (nums.length === 1 ? ' number' : ' numbers');
        }

        if (nums.length === 0) {
            container.innerHTML = '<span class="muted small">— None Recorded —</span>';
            return;
        }

        nums.forEach(function (num) {
            var chip = document.createElement('span');
            chip.className = 'prize-chip ' + (chipClass || '');
            chip.setAttribute('data-prize-num', String(num).trim());
            chip.textContent = String(num);
            container.appendChild(chip);
        });
    }

    function openSambadModal(data) {
        if (!sambadModal || !data) return;

        var titleEl = document.getElementById('sambad-modal-title');
        var subEl = document.getElementById('sambad-modal-subtitle');
        var dateEl = document.getElementById('sambad-modal-date');
        var slotEl = document.getElementById('sambad-modal-slot');
        var sourceEl = document.getElementById('sambad-modal-source');
        var p1El = document.getElementById('sambad-prize-1');

        if (titleEl) titleEl.textContent = 'Lottery Sambad Result — ' + (data.slot || '');
        if (subEl) subEl.textContent = data.date || 'Draw Winning Numbers';
        if (dateEl) dateEl.textContent = data.date || '—';
        if (slotEl) slotEl.textContent = data.slot || '—';
        if (sourceEl) sourceEl.textContent = (data.source || 'Verified') + (data.updated ? ' (Sync ' + data.updated + ')' : '');

        var prizes = data.prizes || {};

        // 1st Prize
        if (p1El) {
            var p1 = prizes['1'] && prizes['1'].length > 0 ? prizes['1'][0] : '—';
            p1El.textContent = p1;
            p1El.setAttribute('data-prize-num', String(p1).trim());
        }

        // 2nd, 3rd, 4th, 5th Prizes
        renderPrizeChips('sambad-prize-2', 'sambad-count-2', prizes['2'], '');
        renderPrizeChips('sambad-prize-3', 'sambad-count-3', prizes['3'], 'chip-sm');
        renderPrizeChips('sambad-prize-4', 'sambad-count-4', prizes['4'], 'chip-sm');
        renderPrizeChips('sambad-prize-5', 'sambad-count-5', prizes['5'], 'chip-xs');

        // Reset search
        if (sambadSearchInput) sambadSearchInput.value = '';
        if (sambadSearchStatus) sambadSearchStatus.textContent = '';

        sambadModal.style.display = 'flex';
        sambadModal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        playSound('lucky');
    }

    // Modal Trigger Listeners (Delegation)
    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('[data-sambad-payload]');
        if (trigger) {
            var raw = trigger.getAttribute('data-sambad-payload');
            if (raw) {
                try {
                    var parsed = JSON.parse(raw);
                    if (parsed) {
                        e.preventDefault();
                        openSambadModal(parsed);
                        return;
                    }
                } catch (err) {}
            }
        }

        if (e.target.closest('[data-close-sambad-modal]')) {
            e.preventDefault();
            closeSambadModal();
            playSound('tap');
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && sambadModal && sambadModal.style.display === 'flex') {
            closeSambadModal();
        }
    });

    // Quick Live Number Search in Modal
    if (sambadSearchInput) {
        sambadSearchInput.addEventListener('input', function () {
            var query = sambadSearchInput.value.trim().toUpperCase();
            var allChips = sambadModal.querySelectorAll('[data-prize-num]');
            var matchCount = 0;
            var matchedTier = '';

            allChips.forEach(function (chip) {
                var num = chip.getAttribute('data-prize-num').toUpperCase();
                chip.classList.remove('chip-highlight', 'chip-dimmed');

                if (query === '') {
                    return;
                }

                if (num.indexOf(query) !== -1 || query.indexOf(num) !== -1) {
                    chip.classList.add('chip-highlight');
                    matchCount++;
                    if (!matchedTier) {
                        var card = chip.closest('.modal-prize-card');
                        if (card) {
                            var badge = card.querySelector('.prize-card-badge strong, .prize-card-badge span');
                            if (badge) matchedTier = badge.textContent;
                        }
                    }
                } else {
                    chip.classList.add('chip-dimmed');
                }
            });

            if (sambadSearchStatus) {
                if (query === '') {
                    sambadSearchStatus.textContent = '';
                } else if (matchCount > 0) {
                    sambadSearchStatus.textContent = '🎉 ' + matchCount + ' Match found in ' + (matchedTier || 'Draw') + '!';
                    sambadSearchStatus.className = 'search-status-text status-match';
                } else {
                    sambadSearchStatus.textContent = 'No match found for ' + query + ' in this draw';
                    sambadSearchStatus.className = 'search-status-text status-nomatch';
                }
            }
        });
    }

})();

