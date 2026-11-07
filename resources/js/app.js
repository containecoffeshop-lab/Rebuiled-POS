import './bootstrap';
import QRCode from 'qrcode';
import JsBarcode from 'jsbarcode';
import { defineCustomElement } from 'ionicons/components/ion-icon.js';
import { addIcons } from 'ionicons';
import {
    addCircleOutline,
    bagHandleOutline,
    barChartOutline,
    bluetoothOutline,
    cafeOutline,
    cartOutline,
    cashOutline,
    chevronBackOutline,
    chevronDownOutline,
    chevronForwardOutline,
    clipboardOutline,
    cubeOutline,
    fastFoodOutline,
    homeOutline,
    layersOutline,
    notificationsOutline,
    peopleOutline,
    openOutline,
    personOutline,
    pricetagOutline,
    qrCodeOutline,
    receiptOutline,
    removeCircleOutline,
    restaurantOutline,
    returnUpBackOutline,
    settingsOutline,
    sparklesOutline,
    statsChartOutline,
    timeOutline,
    walletOutline,
    barcodeOutline,
} from 'ionicons/icons';

addIcons({
    addCircleOutline,
    bagHandleOutline,
    barChartOutline,
    bluetoothOutline,
    cafeOutline,
    cartOutline,
    cashOutline,
    chevronBackOutline,
    chevronDownOutline,
    chevronForwardOutline,
    clipboardOutline,
    cubeOutline,
    fastFoodOutline,
    homeOutline,
    layersOutline,
    notificationsOutline,
    peopleOutline,
    openOutline,
    personOutline,
    pricetagOutline,
    qrCodeOutline,
    receiptOutline,
    removeCircleOutline,
    restaurantOutline,
    returnUpBackOutline,
    settingsOutline,
    sparklesOutline,
    statsChartOutline,
    timeOutline,
    walletOutline,
    barcodeOutline,
});
defineCustomElement();

document.querySelectorAll('body[data-staff-workspace] main form').forEach((form) => {
    if (form.method.toLowerCase() !== 'post'
        || form.closest('details, dialog, table, .shift-panel, [data-pos-module="kasir"], .order-card, .summary-reset')
        || form.querySelectorAll('input:not([type="hidden"]):not([type="submit"]), select, textarea').length < 1) return;

    const section = form.closest('section, article');
    const heading = form.querySelector('h2, h3, legend') || section?.querySelector('h2, h3');
    const label = heading?.textContent.trim() || 'Formulir';
    const details = document.createElement('details');
    details.className = 'feature-disclosure';
    const target = location.hash ? document.getElementById(decodeURIComponent(location.hash.slice(1))) : null;
    details.open = Boolean(document.querySelector('.alert-error')) || Boolean(target && (target.contains(form) || form.contains(target)));
    const summary = document.createElement('summary');
    summary.className = 'button button-secondary';
    summary.textContent = `Buka ${label.toLocaleLowerCase('id-ID')}`;
    form.before(details);
    details.append(summary, form);
});

document.querySelectorAll('details > summary').forEach((summary) => {
    if (summary.querySelector('.details-chevron')) return;
    const chevron = document.createElement('ion-icon');
    chevron.className = 'app-icon details-chevron';
    chevron.name = 'chevron-down-outline';
    chevron.setAttribute('aria-hidden', 'true');
    summary.append(chevron);
});

document.querySelectorAll('[data-image-preview-input]').forEach((input) => {
    const preview = document.getElementById(input.dataset.imagePreviewTarget);
    if (!(input instanceof HTMLInputElement) || !(preview instanceof HTMLImageElement)) return;
    let previewUrl;
    input.addEventListener('change', () => {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        const [file] = input.files || [];
        if (!file) {
            preview.removeAttribute('src');
            preview.hidden = true;
            return;
        }
        previewUrl = URL.createObjectURL(file);
        preview.src = previewUrl;
        preview.hidden = false;
    });
});

document.querySelectorAll('[data-theme-preview]').forEach((preview) => {
    const updatePreview = () => {
        preview.style.setProperty('--preview-primary', document.querySelector('[data-theme-color="primary"]')?.value || '#493123');
        preview.style.setProperty('--preview-accent', document.querySelector('[data-theme-color="accent"]')?.value || '#947456');
        preview.style.setProperty('--preview-background', document.querySelector('[data-theme-color="background"]')?.value || '#faf8f5');
    };
    document.querySelectorAll('[data-theme-color]').forEach((input) => input.addEventListener('input', updatePreview));
    updatePreview();
});

const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
if ('IntersectionObserver' in window && !reducedMotion) {
    const revealObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
        });
    }, { threshold: 0.12, rootMargin: '0px 0px -36px 0px' });

    document.querySelectorAll('.hero, .section-heading, .catalog-category, .menu-card, .panel, .stat-card, .order-card, .auth-card')
        .forEach((element, index) => {
            element.classList.add('scroll-reveal');
            element.style.setProperty('--reveal-delay', `${Math.min(index % 5, 4) * 55}ms`);
            revealObserver.observe(element);
        });
}

const currency = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

document.querySelectorAll('[data-live-menu-search]').forEach((form) => {
    let timer;
    const search = form.querySelector('input[type="search"]');
    const submitAfterPause = () => {
        clearTimeout(timer);
        timer = setTimeout(() => form.requestSubmit(), 350);
    };
    search?.addEventListener('input', submitAfterPause);
    form.querySelectorAll('[data-live-search-change]').forEach((field) => {
        field.addEventListener('change', () => form.requestSubmit());
    });
});

const moneyFieldNames = new Set([
    'amount', 'amount_paid', 'discount_value', 'ending_cash', 'fixed_amount',
    'earning_threshold', 'initial_payment', 'initial_total_cost', 'point_value', 'selling_price',
    'starting_cash', 'total_cost', 'unit_cost', 'value',
]);

const isMoneyField = (input) => {
    const name = input.name || '';
    const fieldName = name.match(/([a-z_]+)(?:\])?$/)?.[1] || name;
    return moneyFieldNames.has(fieldName);
};

const moneyDigits = (value) => String(value ?? '').replace(/\D/g, '');
const moneyNumber = (input) => Number(moneyDigits(input?.value) || 0);
const formattedMoney = (value) => {
    const digits = moneyDigits(value);
    return digits ? `Rp ${Number(digits).toLocaleString('id-ID')}` : '';
};

const initializeMoneyField = (input) => {
    if (!(input instanceof HTMLInputElement) || input.dataset.moneyInput || !isMoneyField(input)) return;
    input.dataset.moneyInput = 'true';
    input.dataset.moneyMin = input.min || '';
    input.dataset.moneyMax = input.max || '';
    input.type = 'text';
    input.inputMode = 'numeric';
    input.autocomplete = 'off';
    input.removeAttribute('min');
    input.removeAttribute('max');
    input.removeAttribute('step');
    const initialDigits = moneyDigits(input.value);
    input.value = initialDigits && Number(initialDigits) > 0 ? formattedMoney(initialDigits) : '';

    input.addEventListener('input', () => {
        input.value = formattedMoney(input.value);
        const value = Number(moneyDigits(input.value) || 0);
        const minimum = input.dataset.moneyMin ? Number(input.dataset.moneyMin) : null;
        const maximum = input.dataset.moneyMax ? Number(input.dataset.moneyMax) : null;
        input.setCustomValidity(
            input.value && minimum !== null && Number.isFinite(minimum) && value < minimum ? `Nilai minimal ${minimum.toLocaleString('id-ID')}.`
                : input.value && maximum !== null && Number.isFinite(maximum) && value > maximum ? `Nilai maksimal ${maximum.toLocaleString('id-ID')}.`
                    : '',
        );

        document.addEventListener('submit', (event) => {
            if (!(event.target instanceof HTMLFormElement)
                || (!event.target.matches('[data-live-menu-search]') && !event.target.matches('[data-preserve-cart-form]'))
                || !storageKey) return;
            sessionStorage.setItem(storageKey, JSON.stringify([...cart.values()]));
        });
        document.addEventListener('click', (event) => {
            if (event.target.closest('[data-preserve-cart]') && storageKey) {
                sessionStorage.setItem(storageKey, JSON.stringify([...cart.values()]));
            }
        });
    });
};

document.querySelectorAll('input[type="number"]').forEach(initializeMoneyField);
new MutationObserver((records) => {
    records.flatMap((record) => [...record.addedNodes]).forEach((node) => {
        if (!(node instanceof Element)) return;
        if (node.matches('input[type="number"]')) initializeMoneyField(node);
        node.querySelectorAll?.('input[type="number"]').forEach(initializeMoneyField);
    });
}).observe(document.documentElement, { childList: true, subtree: true });

document.addEventListener('formdata', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    const grouped = new Map();
    [...form.querySelectorAll('[data-money-input][name]')]
        .filter((input) => !input.disabled)
        .forEach((input) => {
            if (!grouped.has(input.name)) grouped.set(input.name, []);
            grouped.get(input.name).push(input);
        });
    grouped.forEach((inputs, name) => {
        event.formData.delete(name);
        inputs.forEach((input) => event.formData.append(name, moneyDigits(input.value)));
    });
}, true);

document.querySelectorAll('[data-cart-root]').forEach((root) => {
    const form = root.querySelector('[data-cart-form]');
    const list = root.querySelector('[data-cart-list]');
    const total = root.querySelector('[data-cart-total]');
    const counts = root.querySelectorAll('[data-cart-count]');
    const hiddenItems = root.querySelector('[data-cart-inputs]');
    const amountPaid = root.querySelector('[data-amount-paid]');
    const paymentMethod = root.querySelector('[data-payment-method]');
    const couponInput = root.querySelector('[name="coupon_code"]');
    const couponStatus = root.querySelector('[data-coupon-status]');
    const couponButton = root.querySelector('[data-apply-coupon]');
    const customerPhone = root.querySelector('[data-customer-phone]');
    const redeemPointsInput = root.querySelector('[data-redeem-points]');
    const loyaltyStatus = root.querySelector('[data-loyalty-status]');
    const barcodeForm = root.querySelector('[data-barcode-form]');
    const barcodeInput = root.querySelector('[data-barcode-input]');
    const barcodeFeedback = root.querySelector('[data-barcode-feedback]');
    const subtotalLabel = root.querySelector('[data-cart-subtotal]');
    const discountRow = root.querySelector('[data-discount-row]');
    const discountLabel = root.querySelector('[data-cart-discount]');
    const chargeRows = root.querySelector('[data-cart-charges]');
    const cartDialog = root.querySelector('[data-cart-dialog]');
    const menuVariantDialog = root.querySelector('[data-menu-variant-dialog]');
    const menuVariantTitle = root.querySelector('[data-menu-variant-title]');
    const menuVariantOptions = root.querySelector('[data-menu-variant-options]');
    const reviewDialog = root.querySelector('[data-checkout-dialog]');
    const openCheckoutButton = root.querySelector('[data-open-checkout]');
    const reviewItems = root.querySelector('[data-review-items]');
    const reviewSubtotal = root.querySelector('[data-review-subtotal]');
    const reviewDiscountRow = root.querySelector('[data-review-discount-row]');
    const reviewDiscount = root.querySelector('[data-review-discount]');
    const reviewCharges = root.querySelector('[data-review-charges]');
    const reviewTotal = root.querySelector('[data-review-total]');
    let appliedQuote = null;
    let quoteSequence = 0;
    let selectedCashTender = null;
    let applyingCashTender = false;
    let pendingVariantButton = null;
    const storageKey = root.dataset.cartStorageKey;
    let restoredCart = [];
    if (storageKey) {
        try {
            restoredCart = JSON.parse(sessionStorage.getItem(storageKey) || '[]');
            if (!Array.isArray(restoredCart)) restoredCart = [];
        } catch {
            sessionStorage.removeItem(storageKey);
        }
        sessionStorage.removeItem(storageKey);
    }
    const cart = new Map(
        [...JSON.parse(root.dataset.initialCart || '[]'), ...restoredCart].map((item) => {
            const type = item.type === 'product' ? 'product' : 'menu';
            const id = Number(item.id);
            const menuVariantId = type === 'menu' && item.menu_variant_id ? Number(item.menu_variant_id) : null;
            return [`${type}:${id}:${menuVariantId || 0}`, {
                id,
                type,
                menuVariantId,
                name: String(item.name),
                price: Number(item.price),
                quantity: Math.min(20, Number(item.quantity)),
            }];
        })
            .filter(([key, item]) => Number.isSafeInteger(item.id) && item.id > 0
                && Number.isSafeInteger(item.price) && item.price > 0
                && Number.isSafeInteger(item.quantity) && item.quantity > 0),
    );

    const setAmountPaid = (value) => {
        if (!amountPaid) return;
        amountPaid.value = formattedMoney(value);
        applyingCashTender = true;
        amountPaid.dispatchEvent(new Event('input', { bubbles: true }));
        applyingCashTender = false;
    };

    const render = () => {
        const items = [...cart.values()];
        const subtotal = items.reduce((sum, item) => sum + item.price * item.quantity, 0);

        list.innerHTML = items.length
            ? `<table class="cashier-cart-table">
                    <thead><tr><th>Produk</th><th>Harga</th><th>Qty</th><th>Total</th></tr></thead>
                    <tbody>${items.map((item) => `
                        <tr>
                            <td>${escapeHtml(item.name)}</td>
                            <td>${currency.format(item.price)}</td>
                            <td><div class="qty-control"><button class="qty-button" type="button" data-cart-key="${escapeHtml(`${item.type}:${item.id}:${item.menuVariantId || 0}`)}" data-change="-1" aria-label="Kurangi ${escapeHtml(item.name)}"><ion-icon name="remove-circle-outline" aria-hidden="true"></ion-icon></button><span>${item.quantity}</span><button class="qty-button" type="button" data-cart-key="${escapeHtml(`${item.type}:${item.id}:${item.menuVariantId || 0}`)}" data-change="1" aria-label="Tambah ${escapeHtml(item.name)}"><ion-icon name="add-circle-outline" aria-hidden="true"></ion-icon></button></div></td>
                            <td>${currency.format(item.price * item.quantity)}</td>
                        </tr>`).join('')}</tbody>
                </table>`
            : '<div class="cart-empty">Keranjang masih kosong. Pilih item untuk mulai.</div>';
        const quoteMatches = appliedQuote?.signature === quoteSignature();
        const discount = quoteMatches ? appliedQuote.discount : 0;
        const grandTotal = quoteMatches ? appliedQuote.grandTotal : subtotal;
        if (subtotalLabel) subtotalLabel.textContent = currency.format(subtotal);
        total.textContent = currency.format(grandTotal);
        if (discountRow) discountRow.hidden = discount === 0;
        if (discountLabel) discountLabel.textContent = `−${currency.format(discount)}`;
        if (chargeRows) {
            chargeRows.innerHTML = quoteMatches
                ? appliedQuote.charges.map((charge) => `<div class="cart-total"><span>${escapeHtml(charge.name)} (${escapeHtml(charge.code)}) · ${escapeHtml(charge.rate)}</span><span>${currency.format(charge.amount)}</span></div>`).join('')
                : '';
        }
        if (openCheckoutButton) {
            openCheckoutButton.disabled = cart.size === 0 || root.dataset.shiftActive !== 'true';
        }
        counts.forEach((element) => {
            element.textContent = String(items.reduce((sum, item) => sum + item.quantity, 0));
        });
        hiddenItems.innerHTML = items.map((item, index) => `
            <input type="hidden" name="items[${index}][${item.type === 'product' ? 'product_variant_id' : 'menu_id'}]" value="${item.id}">
            ${item.menuVariantId ? `<input type="hidden" name="items[${index}][menu_variant_id]" value="${item.menuVariantId}">` : ''}
            <input type="hidden" name="items[${index}][quantity]" value="${item.quantity}">`).join('');

        if (amountPaid && selectedCashTender === 'exact' && paymentMethod?.value === 'cash') {
            amountPaid.value = formattedMoney(grandTotal);
        } else if (amountPaid && !amountPaid.dataset.edited) {
            amountPaid.value = formattedMoney(grandTotal);
        }
        const change = moneyNumber(amountPaid) - grandTotal;
        const changeLabel = root.querySelector('[data-change-label]');
        const changeAmount = root.querySelector('[data-change-amount]');
        if (changeLabel) changeLabel.textContent = change >= 0 ? 'Kembalian' : 'Kurang bayar';
        if (changeAmount) changeAmount.textContent = currency.format(Math.abs(change));
        if (reviewDialog?.open) renderReview();
        root.querySelectorAll('[data-payment-choice]').forEach((button) => {
            button.setAttribute('aria-pressed', String(button.dataset.paymentChoice === paymentMethod?.value));
        });
    };

    const quoteSignature = () => JSON.stringify({
        items: [...cart.values()].map((item) => ({
            [item.type === 'product' ? 'product_variant_id' : 'menu_id']: item.id,
            ...(item.menuVariantId ? { menu_variant_id: item.menuVariantId } : {}),
            quantity: item.quantity,
        })),
        phone: customerPhone?.value.trim() || '',
        code: couponInput?.value.trim() || '',
        redeemPoints: redeemPointsInput?.value || '0',
    });

    const renderReview = () => {
        const items = [...cart.values()];
        const subtotal = items.reduce((sum, item) => sum + item.price * item.quantity, 0);
        const quoteMatches = appliedQuote?.signature === quoteSignature();
        const discount = quoteMatches ? appliedQuote.discount : 0;
        const grandTotal = quoteMatches ? appliedQuote.grandTotal : subtotal;

        if (reviewItems) {
            reviewItems.innerHTML = items.length
                ? `<table class="cashier-cart-table checkout-cart-table"><thead><tr><th>Produk</th><th>Qty</th><th>Total</th></tr></thead><tbody>${items.map((item) => `
                    <tr><td>${escapeHtml(item.name)}</td><td>${item.quantity}</td><td>${currency.format(item.price * item.quantity)}</td></tr>`).join('')}</tbody></table>`
                : '<p class="cart-empty">Keranjang masih kosong.</p>';
        }
        if (reviewSubtotal) reviewSubtotal.textContent = currency.format(subtotal);
        if (reviewDiscountRow) reviewDiscountRow.hidden = discount === 0;
        if (reviewDiscount) reviewDiscount.textContent = `−${currency.format(discount)}`;
        if (reviewCharges) {
            reviewCharges.innerHTML = quoteMatches
                ? appliedQuote.charges.map((charge) => `<div class="cart-total"><span>${escapeHtml(charge.name)} (${escapeHtml(charge.code)}) · ${escapeHtml(charge.rate)}</span><span>${currency.format(charge.amount)}</span></div>`).join('')
                : '';
        }
        if (reviewTotal) reviewTotal.textContent = currency.format(grandTotal);
    };

    const applyCoupon = async () => {
        if (cart.size === 0) {
            if (couponStatus) couponStatus.textContent = 'Pilih item sebelum memeriksa diskon.';
            return;
        }

        const signature = quoteSignature();
        const sequence = ++quoteSequence;
        couponButton.disabled = true;
        if (couponStatus) couponStatus.textContent = 'Memeriksa promo dan kupon…';
        try {
            const response = await fetch(root.dataset.couponUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({
                    code: couponInput?.value.trim() || null,
                    customer_phone: customerPhone?.value.trim() || null,
                    ...(redeemPointsInput ? { redeem_points: Number(redeemPointsInput.value || 0) } : {}),
                    items: [...cart.values()].map((item) => ({
                        [item.type === 'product' ? 'product_variant_id' : 'menu_id']: item.id,
                        ...(item.menuVariantId ? { menu_variant_id: item.menuVariantId } : {}),
                        quantity: item.quantity,
                    })),
                }),
            });
            const result = await response.json();
            if (!response.ok) {
                throw new Error(result.errors?.redeem_points?.[0] || result.errors?.code?.[0] || result.errors?.items?.[0] || result.message || 'Diskon tidak dapat diperiksa.');
            }

            if (sequence !== quoteSequence) return;
            appliedQuote = {
                discount: result.discount,
                charges: result.charges || [],
                grandTotal: result.grand_total,
                loyaltyDiscount: result.loyalty_discount || 0,
                loyaltyBalance: result.loyalty_points_balance || 0,
                loyaltyRedeemed: result.loyalty_points_redeemed || 0,
            };
            if (result.code && couponInput) couponInput.value = result.code;
            appliedQuote.signature = quoteSignature();
            if (couponStatus) {
                const parts = [];
                if (result.promotion_discount > 0) parts.push(`Promo ${currency.format(result.promotion_discount)}`);
                if (result.coupon_discount > 0) parts.push(`kupon ${result.code} ${currency.format(result.coupon_discount)}`);
                if (result.loyalty_discount > 0) parts.push(`poin ${currency.format(result.loyalty_discount)}`);
                couponStatus.textContent = parts.length ? `Hemat ${parts.join(' + ')}.` : 'Tidak ada promo atau kupon yang berlaku.';
            }
            if (loyaltyStatus && redeemPointsInput) {
                loyaltyStatus.textContent = `Saldo: ${result.loyalty_points_balance || 0} poin.`
                    + (result.loyalty_discount > 0 ? ` Ditukar ${result.loyalty_points_redeemed} poin = ${currency.format(result.loyalty_discount)}.` : ' Poin dapat ditukar di kasir.');
            }
            render();
        } catch (error) {
            if (sequence !== quoteSequence) return;
            appliedQuote = null;
            if (couponStatus) couponStatus.textContent = error instanceof Error ? error.message : 'Diskon gagal diperiksa.';
            if (loyaltyStatus && redeemPointsInput && Number(redeemPointsInput.value) > 0) {
                loyaltyStatus.textContent = error instanceof Error ? error.message : 'Poin gagal diperiksa.';
            }
            render();
        } finally {
            if (sequence === quoteSequence) couponButton.disabled = false;
        }
    };

    let quoteTimer;
    const scheduleQuote = () => {
        appliedQuote = null;
        quoteSequence += 1;
        render();
        if (couponStatus) couponStatus.textContent = cart.size ? 'Menghitung promo…' : '';
        clearTimeout(quoteTimer);
        if (cart.size) quoteTimer = setTimeout(applyCoupon, 250);
    };

    const addMenu = (button, selectedVariant = null) => {
        if (button.disabled) return false;
        const card = button.closest('[data-menu-card]');
        const type = card.dataset.itemType === 'product' ? 'product' : 'menu';
        const id = Number(card.dataset.itemId);
        const variants = type === 'menu' ? JSON.parse(card.dataset.itemVariants || '[]') : [];
        if (variants.length > 0 && !selectedVariant) {
            pendingVariantButton = button;
            menuVariantTitle.textContent = `Pilih varian · ${card.dataset.itemName}`;
            menuVariantOptions.innerHTML = variants.map((variant) => {
                const price = Number(card.dataset.itemPrice) + Number(variant.additional_price);
                return `<button class="menu-variant-option" type="button" data-select-menu-variant="${variant.id}">
                    <span><strong>${escapeHtml(variant.name)}</strong><small>Tambahan Rp${currency.format(Number(variant.additional_price)).replace(/[^\d.,]/g, '')}</small></span>
                    <strong>${currency.format(price)}</strong>
                </button>`;
            }).join('');
            menuVariantDialog.showModal();
            return 'variant-choice';
        }
        const menuVariantId = selectedVariant ? Number(selectedVariant.id) : null;
        const key = `${type}:${id}:${menuVariantId || 0}`;
        const existing = cart.get(key);
        cart.set(key, {
            id,
            type,
            menuVariantId,
            name: `${card.dataset.itemName}${selectedVariant ? ` · ${selectedVariant.name}` : ''}`,
            price: Number(card.dataset.itemPrice) + Number(selectedVariant?.additional_price || 0),
            quantity: Math.min(20, (existing?.quantity ?? 0) + 1),
        });
        scheduleQuote();
        return true;
    };

    root.addEventListener('click', (event) => {
        if (event.target.closest('[data-open-cart]')) {
            cartDialog?.showModal();
            return;
        }
        if (event.target.closest('[data-close-cart]')) {
            cartDialog?.close();
            return;
        }
        const selectedMenuVariant = event.target.closest('[data-select-menu-variant]');
        if (selectedMenuVariant && pendingVariantButton) {
            const card = pendingVariantButton.closest('[data-menu-card]');
            const variant = JSON.parse(card.dataset.itemVariants || '[]')
                .find((item) => Number(item.id) === Number(selectedMenuVariant.dataset.selectMenuVariant));
            if (variant) addMenu(pendingVariantButton, variant);
            menuVariantDialog.close();
            pendingVariantButton = null;
            return;
        }
        if (event.target.closest('[data-close-menu-variant]')) {
            menuVariantDialog?.close();
            pendingVariantButton = null;
            return;
        }
        if (event.target.closest('[data-open-checkout]')) {
            renderReview();
            reviewDialog?.showModal();
            return;
        }
        if (event.target.closest('[data-close-checkout]')) {
            reviewDialog?.close();
            return;
        }
        const paymentChoice = event.target.closest('[data-payment-choice]');
        if (paymentChoice && paymentMethod) {
            paymentMethod.value = paymentChoice.dataset.paymentChoice;
            paymentMethod.dispatchEvent(new Event('change', { bubbles: true }));
            return;
        }
        const cashTender = event.target.closest('[data-cash-tender]');
        if (cashTender) {
            const quoteMatches = appliedQuote?.signature === quoteSignature();
            const grandTotal = quoteMatches
                ? appliedQuote.grandTotal
                : [...cart.values()].reduce((sum, item) => sum + item.price * item.quantity, 0);
            selectedCashTender = cashTender.dataset.cashTender;
            setAmountPaid(cashTender.dataset.cashTender === 'exact' ? grandTotal : Number(cashTender.dataset.cashTender));
            return;
        }
        const applyButton = event.target.closest('[data-apply-coupon]');
        if (applyButton) {
            clearTimeout(quoteTimer);
            applyCoupon();
            return;
        }

        const addButton = event.target.closest('[data-add-menu]');
        if (addButton) {
            addMenu(addButton);
            return;
        }

        const changeButton = event.target.closest('[data-cart-key]');
        if (changeButton) {
            const key = changeButton.dataset.cartKey;
            const item = cart.get(key);
            if (!item) return;
            item.quantity += Number(changeButton.dataset.change);
            if (item.quantity <= 0) cart.delete(key);
            if (item.quantity > 20) item.quantity = 20;
            scheduleQuote();
        }
    });

    barcodeForm?.addEventListener('submit', (event) => {
        event.preventDefault();
        const barcode = barcodeInput.value.trim().toUpperCase();
        const card = [...root.querySelectorAll('[data-menu-card]')]
            .find((item) => item.dataset.itemBarcode?.toUpperCase() === barcode && barcode !== '');
        if (!card) {
            barcodeFeedback.textContent = 'Barcode tidak ditemukan pada katalog yang tersedia.';
            barcodeInput.select();
            return;
        }
        const added = addMenu(card.querySelector('[data-add-menu]'));
        barcodeFeedback.textContent = added === 'variant-choice'
            ? `Pilih varian ${card.dataset.itemName} untuk menambahkannya.`
            : added
                ? `${card.dataset.itemName} ditambahkan ke transaksi.`
                : `${card.dataset.itemName} belum dapat ditambahkan karena stok habis atau shift belum dibuka.`;
        barcodeInput.value = '';
        barcodeInput.focus();
    });

    menuVariantDialog?.addEventListener('close', () => {
        pendingVariantButton = null;
    });

    form?.addEventListener('submit', (event) => {
        if (cart.size === 0) {
            event.preventDefault();
            list.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }

        if (appliedQuote?.signature !== quoteSignature()) {
            event.preventDefault();
            if (couponStatus) couponStatus.textContent = 'Tunggu hingga total akhir selesai dihitung sebelum mengirim pesanan.';
            clearTimeout(quoteTimer);
            applyCoupon();
            return;
        }
    });

    amountPaid?.addEventListener('input', () => {
        if (!applyingCashTender) selectedCashTender = null;
        amountPaid.dataset.edited = 'true';
        render();
    });
    amountPaid?.addEventListener('focus', () => amountPaid.select());
    paymentMethod?.addEventListener('change', () => {
        if (paymentMethod.value === 'transfer') {
            selectedCashTender = null;
            amountPaid.dataset.edited = '';
        }
        render();
    });
    couponInput?.addEventListener('input', scheduleQuote);
    customerPhone?.addEventListener('input', scheduleQuote);
    redeemPointsInput?.addEventListener('input', scheduleQuote);

    render();
    if (cart.size) scheduleQuote();
});

document.querySelectorAll('[data-employee-consumption]').forEach((root) => {
    const form = root.querySelector('[data-employee-consumption-form]');
    if (!form) return;

    const cartList = root.querySelector('[data-employee-cart]');
    const hiddenItems = root.querySelector('[data-employee-inputs]');
    const totalLabel = root.querySelector('[data-employee-total]');
    const recipient = root.querySelector('[name="employee_id"]');
    const submitButton = root.querySelector('[data-record-employee-consumption]');
    const variantDialog = root.querySelector('[data-menu-variant-dialog]');
    const variantTitle = root.querySelector('[data-menu-variant-title]');
    const variantOptions = root.querySelector('[data-menu-variant-options]');
    const cart = new Map();
    let pendingButton = null;

    const renderConsumption = () => {
        const items = [...cart.values()];
        const total = items.reduce((sum, item) => sum + (item.hpp ?? 0) * item.quantity, 0);
        cartList.innerHTML = items.length
            ? `<table class="cashier-cart-table"><thead><tr><th>Menu</th><th>Qty</th><th>HPP</th></tr></thead><tbody>${items.map((item) => `
                <tr>
                    <td>${escapeHtml(item.name)}</td>
                    <td><div class="qty-control"><button class="qty-button" type="button" data-employee-key="${escapeHtml(item.key)}" data-employee-change="-1" aria-label="Kurangi ${escapeHtml(item.name)}"><ion-icon name="remove-circle-outline" aria-hidden="true"></ion-icon></button><span>${item.quantity}</span><button class="qty-button" type="button" data-employee-key="${escapeHtml(item.key)}" data-employee-change="1" aria-label="Tambah ${escapeHtml(item.name)}"><ion-icon name="add-circle-outline" aria-hidden="true"></ion-icon></button></div></td>
                    <td>${item.hpp === null ? 'Belum diketahui' : currency.format(item.hpp * item.quantity)}</td>
                </tr>`).join('')}</tbody></table>`
            : '<p class="cart-empty">Pilih menu untuk mulai.</p>';
        totalLabel.textContent = items.some((item) => item.hpp === null)
            ? 'HPP belum lengkap'
            : currency.format(total);
        hiddenItems.innerHTML = items.map((item, index) => `
            <input type="hidden" name="items[${index}][menu_id]" value="${item.id}">
            ${item.menuVariantId ? `<input type="hidden" name="items[${index}][menu_variant_id]" value="${item.menuVariantId}">` : ''}
            <input type="hidden" name="items[${index}][quantity]" value="${item.quantity}">`).join('');
        submitButton.disabled = items.length === 0 || !recipient.value || items.some((item) => item.hpp === null);
    };

    const addConsumption = (button, variant = null) => {
        const card = button.closest('[data-menu-card]');
        if (button.disabled) return;
        const id = Number(card.dataset.itemId);
        const menuVariantId = variant ? Number(variant.id) : null;
        const key = `${id}:${menuVariantId || 0}`;
        const existing = cart.get(key);
        const baseHpp = card.dataset.itemCost === '' ? null : Number(card.dataset.itemCost);
        const variantHpp = variant?.hpp === null || variant?.hpp === undefined ? baseHpp : Number(variant.hpp);
        cart.set(key, {
            id,
            key,
            menuVariantId,
            name: `${card.dataset.itemName}${variant ? ` · ${variant.name}` : ''}`,
            hpp: Number.isFinite(variantHpp) ? variantHpp : null,
            quantity: Math.min(20, (existing?.quantity || 0) + 1),
        });
        renderConsumption();
    };

    root.addEventListener('click', (event) => {
        const addButton = event.target.closest('[data-employee-add]');
        if (addButton) {
            const card = addButton.closest('[data-menu-card]');
            const variants = JSON.parse(card.dataset.itemVariants || '[]');
            if (variants.length === 0) {
                addConsumption(addButton);
                return;
            }
            pendingButton = addButton;
            variantTitle.textContent = `Pilih varian · ${card.dataset.itemName}`;
            variantOptions.innerHTML = variants.map((variant) => `
                <button class="menu-variant-option" type="button" data-select-menu-variant="${variant.id}">
                    <span><strong>${escapeHtml(variant.name)}</strong><small>${variant.hpp === null ? 'HPP belum lengkap' : 'Estimasi HPP varian'}</small></span>
                    <strong>${variant.hpp === null ? 'Belum diketahui' : currency.format(variant.hpp)}</strong>
                </button>`).join('');
            variantDialog.showModal();
            return;
        }

        const selectVariant = event.target.closest('[data-select-menu-variant]');
        if (selectVariant && pendingButton) {
            const card = pendingButton.closest('[data-menu-card]');
            const variant = JSON.parse(card.dataset.itemVariants || '[]')
                .find((item) => Number(item.id) === Number(selectVariant.dataset.selectMenuVariant));
            if (variant) addConsumption(pendingButton, variant);
            variantDialog.close();
            pendingButton = null;
            return;
        }

        if (event.target.closest('[data-close-menu-variant]')) {
            variantDialog.close();
            pendingButton = null;
            return;
        }

        const quantityButton = event.target.closest('[data-employee-key]');
        if (quantityButton) {
            const item = cart.get(quantityButton.dataset.employeeKey);
            if (!item) return;
            item.quantity += Number(quantityButton.dataset.employeeChange);
            if (item.quantity < 1) cart.delete(item.key);
            if (item.quantity > 20) item.quantity = 20;
            renderConsumption();
        }
    });

    variantDialog?.addEventListener('close', () => {
        pendingButton = null;
    });
    recipient.addEventListener('change', renderConsumption);
    renderConsumption();
});

document.querySelectorAll('[data-pos-workspace]').forEach((workspace) => {
    const moduleLinks = [...workspace.querySelectorAll('[data-pos-module-link]')];
    const modules = [...workspace.querySelectorAll('[data-pos-module]')];
    const dashboardToggle = workspace.querySelector('[data-dashboard-toggle]');
    const allowedModules = new Set(modules.map((module) => module.dataset.posModule));
    const defaultModule = allowedModules.has(workspace.dataset.defaultModule)
        ? workspace.dataset.defaultModule
        : (allowedModules.has('kasir') ? 'kasir' : modules[0]?.dataset.posModule);

    const selectModule = (name) => {
        const requestedModule = name === 'dashboard' ? 'ringkasan' : name;
        const selected = allowedModules.has(requestedModule) ? requestedModule : defaultModule;
        modules.forEach((module) => {
            module.hidden = module.dataset.posModule !== selected;
        });
        moduleLinks.forEach((link) => {
            const active = link.dataset.posModuleLink === selected;
            link.classList.toggle('active', active);
            if (active) link.setAttribute('aria-current', 'page');
            else link.removeAttribute('aria-current');
        });
        const cashierMode = selected === 'kasir';
        workspace.classList.toggle('cashier-mode', cashierMode);
        document.body.classList.toggle('pos-screen', cashierMode);
        dashboardToggle?.setAttribute('aria-label', 'Buka ringkasan');
        const url = new URL(window.location.href);
        url.searchParams.set('module', selected);
        window.history.replaceState(null, '', url);
    };

    workspace.addEventListener('click', (event) => {
        const link = event.target.closest('[data-pos-module-link]');
        if (link) selectModule(link.dataset.posModuleLink);
    });
    dashboardToggle?.addEventListener('click', () => selectModule('ringkasan'));
    workspace.querySelector('[data-sidebar-toggle]')?.addEventListener('click', (event) => {
        const button = event.currentTarget;
        const collapsed = workspace.classList.toggle('sidebar-collapsed');
        button.setAttribute('aria-expanded', String(!collapsed));
        button.setAttribute('aria-label', collapsed ? 'Maksimalkan navigasi' : 'Minimalkan navigasi');
        const label = button.querySelector('[data-sidebar-toggle-label]');
        if (label) label.textContent = collapsed ? 'Maksimalkan' : 'Minimalkan';
    });

    if (modules.length) selectModule(defaultModule);

    const badge = workspace.querySelector('[data-pending-order-badge]');
    const updatePendingOrderBadge = async () => {
        try {
            const response = await fetch(workspace.dataset.pendingOrdersUrl, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store',
            });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const { count } = await response.json();
            if (!Number.isSafeInteger(count) || count < 0) throw new Error('Jumlah pesanan tidak valid.');
            if (badge) {
                badge.textContent = String(count);
                badge.hidden = count === 0;
                badge.removeAttribute('data-refresh-error');
            }
            const shortcut = workspace.querySelector('.kitchen-shortcut');
            shortcut?.setAttribute('aria-label', `${count} pesanan menunggu pembayaran`);
            shortcut?.setAttribute('title', `${count} pesanan menunggu pembayaran`);
        } catch {
            badge?.setAttribute('data-refresh-error', 'true');
        }
    };
    updatePendingOrderBadge();
    window.setInterval(updatePendingOrderBadge, 15000);
});

const notificationCenter = document.querySelector('[data-notifications]');
if (notificationCenter) {
    const toolbar = document.querySelector('.pos-workspace .pos-toolbar-actions');
    if (toolbar) toolbar.prepend(notificationCenter);
    const toggle = notificationCenter.querySelector('[data-notification-toggle]');
    const panel = notificationCenter.querySelector('[data-notification-panel]');
    const list = notificationCenter.querySelector('[data-notification-list]');
    const badge = notificationCenter.querySelector('[data-notification-badge]');
    const readAllButton = notificationCenter.querySelector('[data-notification-read-all]');
    const soundButton = notificationCenter.querySelector('[data-notification-sound]');
    const soundStatus = notificationCenter.querySelector('[data-notification-sound-status]');
    const soundKey = `pos-notification-sound-${notificationCenter.dataset.userId}`;
    const seenKey = `pos-notification-seen-${notificationCenter.dataset.userId}`;
    let loading = false;
    let audioContext;
    let soundEnabled = false;
    let seen = null;
    let pendingSounds = 0;
    try {
        soundEnabled = localStorage.getItem(soundKey) === 'on';
        const previous = sessionStorage.getItem(seenKey);
        if (previous !== null) {
            const parsed = JSON.parse(previous);
            if (Array.isArray(parsed)) seen = new Set(parsed);
        }
    } catch (error) {
        console.error('Pengaturan notifikasi tidak dapat dibaca.', error);
        soundStatus.textContent = 'Penyimpanan browser tidak tersedia; pengaturan tidak dapat diingat.';
    }

    const soundState = (message) => {
        soundButton.textContent = soundEnabled ? 'Matikan suara' : 'Aktifkan suara';
        soundButton.setAttribute('aria-pressed', String(soundEnabled));
        if (message) soundStatus.textContent = message;
    };

    const playSound = async (count = 1) => {
        if (!soundEnabled) return false;
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) throw new Error('Audio tidak didukung browser.');
            audioContext ||= new AudioContext();
            if (audioContext.state !== 'running') {
                await Promise.race([
                    audioContext.resume(),
                    new Promise((_, reject) => window.setTimeout(() => reject(new Error('Audio memerlukan interaksi pengguna.')), 1000)),
                ]);
            }
            if (audioContext.state !== 'running') throw new Error('Audio diblokir browser.');
            for (let index = 0; index < count; index += 1) {
                const start = audioContext.currentTime + index * 0.4;
                const oscillator = audioContext.createOscillator();
                const gain = audioContext.createGain();
                oscillator.type = 'sine';
                oscillator.frequency.setValueAtTime(880, start);
                gain.gain.setValueAtTime(0.0001, start);
                gain.gain.exponentialRampToValueAtTime(0.15, start + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.3);
                oscillator.connect(gain);
                gain.connect(audioContext.destination);
                oscillator.start(start);
                oscillator.stop(start + 0.3);
            }
            soundState('Suara aktif di tab ini.');
            return true;
        } catch (error) {
            console.error('Suara notifikasi tidak dapat diputar.', error);
            soundState('Suara diblokir atau tidak didukung. Ketuk halaman, periksa volume, lalu coba lagi.');
            return false;
        }
    };

    soundState(soundEnabled ? 'Suara diaktifkan; ketuk halaman jika browser memblokirnya.' : null);
    soundButton.addEventListener('click', async () => {
        soundEnabled = !soundEnabled;
        try {
            localStorage.setItem(soundKey, soundEnabled ? 'on' : 'off');
        } catch (error) {
            console.error('Pengaturan suara tidak dapat disimpan.', error);
            soundState('Pengaturan tidak dapat disimpan di browser ini.');
        }
        if (soundEnabled) {
            if (await playSound(Math.max(1, pendingSounds))) pendingSounds = 0;
        } else {
            pendingSounds = 0;
            soundState('Suara dimatikan di perangkat ini.');
        }
    });

    document.addEventListener('pointerdown', () => {
        if (soundEnabled && pendingSounds > 0) {
            playSound(pendingSounds).then((played) => { if (played) pendingSounds = 0; });
        }
    });

    const request = async (url, method = 'GET') => {
        const response = await fetch(url, {
            method,
            headers: {
                Accept: 'application/json',
                ...(method === 'GET' ? {} : {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    'Content-Type': 'application/json',
                }),
            },
            credentials: 'same-origin',
            cache: 'no-store',
        });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        return response.json();
    };

    const updateBadge = (count) => {
        if (!Number.isSafeInteger(count) || count < 0) throw new Error('Jumlah notifikasi tidak valid.');
        badge.textContent = count > 99 ? '99+' : String(count);
        badge.hidden = count === 0;
        toggle.setAttribute('aria-label', count ? `${count} notifikasi belum dibaca` : 'Notifikasi');
    };

    const renderNotifications = (notifications) => {
        list.replaceChildren();
        if (!notifications.length) {
            const empty = document.createElement('li');
            empty.className = 'notification-empty';
            empty.textContent = 'Belum ada notifikasi.';
            list.append(empty);
            return;
        }

        notifications.forEach((notification) => {
            const item = document.createElement('li');
            item.className = `notification-item${notification.read_at ? '' : ' unread'}`;
            const link = document.createElement('a');
            link.className = 'notification-link';
            const target = new URL(notification.url || '/kasir', window.location.origin);
            link.href = target.origin === window.location.origin ? target.href : '/kasir';
            link.dataset.notificationId = notification.id;

            const title = document.createElement('strong');
            title.textContent = notification.title;
            const message = document.createElement('span');
            message.textContent = notification.message;
            const time = document.createElement('time');
            time.dateTime = notification.created_at || '';
            time.textContent = notification.created_at
                ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(notification.created_at))
                : '';

            link.append(title, message, time);
            item.append(link);
            list.append(item);
        });
    };

    const refresh = async () => {
        if (loading) return;
        loading = true;
        try {
            await request(notificationCenter.dataset.stockUrl, 'POST');
            const data = await request(notificationCenter.dataset.feedUrl);
            if (!Array.isArray(data.notifications)) throw new Error('Daftar notifikasi tidak valid.');
            const ids = data.notifications.map((notification) => notification.id);
            const newUnread = seen === null ? [] : data.notifications.filter((notification) => !seen.has(notification.id) && !notification.read_at);
            seen = new Set([...(seen || []), ...ids].slice(-200));
            try {
                sessionStorage.setItem(seenKey, JSON.stringify([...seen]));
            } catch (error) {
                console.error('Riwayat notifikasi tab tidak dapat disimpan.', error);
            }
            if (soundEnabled && newUnread.length) {
                pendingSounds += newUnread.length;
                if (await playSound(pendingSounds)) pendingSounds = 0;
            }
            updateBadge(data.unread_count);
            renderNotifications(data.notifications);
            notificationCenter.removeAttribute('data-refresh-error');
        } catch {
            notificationCenter.setAttribute('data-refresh-error', 'true');
            if (list) list.querySelector('.notification-empty')?.replaceChildren('Gagal memuat notifikasi. Coba buka kembali.');
        } finally {
            loading = false;
        }
    };

    toggle?.addEventListener('click', () => {
        const opening = panel.hidden;
        panel.hidden = !opening;
        toggle.setAttribute('aria-expanded', String(opening));
        if (opening) refresh();
    });

    notificationCenter.addEventListener('click', async (event) => {
        const link = event.target.closest('[data-notification-id]');
        if (!link) return;
        event.preventDefault();
        try {
            await request(`${notificationCenter.dataset.feedUrl}/${encodeURIComponent(link.dataset.notificationId)}/baca`, 'PATCH');
            window.location.assign(link.href);
        } catch {
            notificationCenter.setAttribute('data-refresh-error', 'true');
            list.querySelector('.notification-empty')?.replaceChildren('Notifikasi tidak dapat ditandai dibaca. Coba lagi.');
        }
    });

    readAllButton?.addEventListener('click', async () => {
        readAllButton.disabled = true;
        try {
            const data = await request(notificationCenter.dataset.readAllUrl, 'PATCH');
            updateBadge(data.unread_count);
            await refresh();
        } catch {
            notificationCenter.setAttribute('data-refresh-error', 'true');
        } finally {
            readAllButton.disabled = false;
        }
    });

    document.addEventListener('click', (event) => {
        if (!notificationCenter.contains(event.target) && !panel.hidden) {
            panel.hidden = true;
            toggle.setAttribute('aria-expanded', 'false');
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !panel.hidden) {
            panel.hidden = true;
            toggle.setAttribute('aria-expanded', 'false');
            toggle.focus();
        }
    });

    refresh();
    window.setInterval(refresh, 20000);
}

document.querySelectorAll('details').forEach((details, index, detailsList) => {
    const summary = details.querySelector(':scope > summary');
    if (!summary) return;

    const dialog = document.createElement('dialog');
    dialog.className = 'details-dialog';
    if (details.matches('[data-menu-editor]')) dialog.classList.add('menu-editor-dialog');
    dialog.id = `details-dialog-${index}`;
    dialog.setAttribute('aria-labelledby', `${dialog.id}-title`);

    const heading = document.createElement('div');
    heading.className = 'details-dialog-head';
    const title = document.createElement('h2');
    title.id = `${dialog.id}-title`;
    title.textContent = summary.textContent.trim();
    const close = document.createElement('button');
    close.className = 'button button-secondary button-small';
    close.type = 'button';
    close.textContent = 'Tutup';
    close.setAttribute('aria-label', `Tutup ${title.textContent}`);
    close.addEventListener('click', () => dialog.close());
    heading.append(title, close);
    dialog.append(heading);

    [...details.childNodes].forEach((node) => {
        if (node !== summary) dialog.append(node);
    });

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = `${summary.className} details-trigger`.trim();
    trigger.textContent = summary.textContent.trim();
    trigger.setAttribute('aria-haspopup', 'dialog');
    trigger.setAttribute('aria-controls', dialog.id);
    trigger.addEventListener('click', () => dialog.showModal());

    const shouldOpen = details.hasAttribute('data-open-after-submit');
    details.replaceWith(trigger, dialog);
    if (shouldOpen) window.setTimeout(() => dialog.showModal(), 0);
});

const printerDialog = document.querySelector('[data-printer-dialog]');
const printerStatusDot = document.querySelector('[data-printer-status-dot]');
const printerStatusLabel = document.querySelector('[data-printer-status-label]');
const printerSupport = document.querySelector('[data-printer-support]');
const printerConnectionState = document.querySelector('[data-printer-connection-state]');
const printerConnectButton = document.querySelector('[data-printer-connect]');
const printerDisconnectButton = document.querySelector('[data-printer-disconnect]');
const printerWebPrintButton = document.querySelector('[data-printer-web-print]');
const bluetoothServices = [
    'generic_access',
    'generic_attribute',
    '0000ffe0-0000-1000-8000-00805f9b34fb',
    '0000fff0-0000-1000-8000-00805f9b34fb',
    '000018f0-0000-1000-8000-00805f9b34fb',
    '0000ae30-0000-1000-8000-00805f9b34fb',
];
let printerDevice = null;
let printerCharacteristic = null;
const monitoredPrinterDevices = new WeakSet();
const printerDeviceStorageKey = 'rasa-printer-device-id';
let printerReconnectInProgress = false;

const setPrinterStatus = (state, message) => {
    if (printerStatusDot) {
        printerStatusDot.classList.toggle('connected', state === 'connected');
        printerStatusDot.classList.toggle('connecting', state === 'connecting');
    }
    if (printerStatusLabel) {
        printerStatusLabel.textContent = state === 'connected'
            ? 'Printer siap'
            : state === 'connecting' ? 'Menghubungkan' : 'Bluetooth web';
    }
    if (printerConnectionState) {
        printerConnectionState.textContent = message;
        printerConnectionState.classList.toggle('connected', state === 'connected');
    }
    if (printerDisconnectButton) printerDisconnectButton.hidden = !printerDevice?.gatt?.connected;
    if (printerWebPrintButton) printerWebPrintButton.hidden = !printerCharacteristic;
};

const disconnectPrinter = () => {
    if (printerDevice?.gatt?.connected) printerDevice.gatt.disconnect();
    printerCharacteristic = null;
    printerDevice = null;
    try {
        localStorage.removeItem(printerDeviceStorageKey);
    } catch {
        if (printerSupport) printerSupport.textContent = 'Koneksi diputus. Browser membatasi penyimpanan pilihan printer.';
    }
    setPrinterStatus('disconnected', 'Belum tersambung. Dialog cetak perangkat tetap dapat digunakan.');
};

const findPrinterWriter = async (server) => {
    const services = await server.getPrimaryServices();
    for (const service of services) {
        const characteristics = await service.getCharacteristics();
        const writable = characteristics.find((characteristic) => characteristic.properties.write
            || characteristic.properties.writeWithoutResponse);
        if (writable) return writable;
    }
    return null;
};

const connectPrinter = async (device) => {
    if (!device?.gatt) throw new Error('Perangkat ini tidak menyediakan koneksi Bluetooth LE/GATT.');
    printerDevice = device;
    if (!monitoredPrinterDevices.has(device)) {
        device.addEventListener('gattserverdisconnected', () => {
            if (printerDevice !== device) return;
            printerCharacteristic = null;
            setPrinterStatus('disconnected', 'Koneksi terputus. Cetak melalui dialog perangkat atau hubungkan kembali.');
        });
        monitoredPrinterDevices.add(device);
    }
    const server = await device.gatt.connect();
    printerCharacteristic = await findPrinterWriter(server);
    if (!printerCharacteristic) {
        setPrinterStatus('disconnected', `${device.name || 'Perangkat'} tersambung, tetapi karakteristik tulis tidak ditemukan. Gunakan dialog cetak perangkat.`);
        return false;
    }
    setPrinterStatus('connected', `${device.name || 'Printer'} tersambung. Protokol ESC/POS belum terverifikasi.`);
    return true;
};

const reconnectRememberedPrinter = async () => {
    if (!printerDialog || !navigator.bluetooth?.getDevices || !window.isSecureContext || printerReconnectInProgress) return;

    let rememberedDeviceId = '';
    try {
        rememberedDeviceId = localStorage.getItem(printerDeviceStorageKey) || '';
    } catch {
        if (printerSupport) printerSupport.textContent = 'Browser membatasi penyimpanan pilihan printer. Hubungkan printer kembali secara manual.';
        return;
    }
    if (!rememberedDeviceId) return;

    printerReconnectInProgress = true;
    setPrinterStatus('connecting', 'Menyambungkan ulang printer yang pernah dipilih…');
    try {
        const devices = await navigator.bluetooth.getDevices();
        const device = devices.find((candidate) => candidate.id === rememberedDeviceId);
        if (!device) {
            setPrinterStatus('disconnected', 'Izin printer sebelumnya tidak tersedia di browser. Hubungkan ulang atau gunakan dialog cetak perangkat.');
            return;
        }
        await connectPrinter(device);
    } catch (error) {
        setPrinterStatus('disconnected', error instanceof Error
            ? `Sambung ulang otomatis gagal: ${error.message}. Hubungkan kembali atau gunakan dialog cetak perangkat.`
            : 'Sambung ulang otomatis gagal. Hubungkan kembali atau gunakan dialog cetak perangkat.');
    } finally {
        printerReconnectInProgress = false;
    }
};

const connectPrinterButton = async () => {
    if (!navigator.bluetooth?.requestDevice || !window.isSecureContext) {
        if (printerSupport) printerSupport.textContent = 'Web Bluetooth tidak tersedia di browser/perangkat ini. Gunakan dialog cetak perangkat; Chrome iPhone tidak menyediakan Web Bluetooth.';
        return;
    }
    if (printerConnectButton) printerConnectButton.disabled = true;
    setPrinterStatus('connecting', 'Pilih printer Bluetooth LE pada jendela browser…');
    try {
        const device = await navigator.bluetooth.requestDevice({
            acceptAllDevices: true,
            optionalServices: bluetoothServices,
        });
        try {
            localStorage.setItem(printerDeviceStorageKey, device.id);
        } catch {
            if (printerSupport) printerSupport.textContent = 'Printer tersambung untuk halaman ini, tetapi browser tidak mengizinkan menyimpan pilihan untuk penyambungan ulang.';
        }
        const connected = await connectPrinter(device);
        if (!connected && printerSupport) {
            printerSupport.textContent = 'Koneksi perangkat berhasil, tetapi layanan tulis tidak terdeteksi dengan daftar layanan umum. Model/protokol printer diperlukan untuk memastikan dukungan.';
        }
    } catch (error) {
        setPrinterStatus('disconnected', error instanceof Error ? `Gagal menghubungkan: ${error.message}` : 'Gagal menghubungkan printer. Gunakan dialog cetak perangkat.');
    } finally {
        if (printerConnectButton) printerConnectButton.disabled = false;
    }
};

const asciiReceiptText = () => {
    const receipt = document.querySelector('.receipt-card');
    if (!receipt) return '';
    return receipt.innerText
        .replace(/[^\x20-\x7E\n]/g, '?')
        .replace(/\n{3,}/g, '\n\n')
        .trim();
};

document.querySelector('[data-printer-dialog-open]')?.addEventListener('click', async () => {
    printerDialog?.showModal();
    if (!navigator.bluetooth?.requestDevice || !window.isSecureContext) {
        if (printerSupport) printerSupport.textContent = 'Web Bluetooth tidak tersedia di browser/perangkat ini. Gunakan dialog cetak perangkat; Chrome iPhone tidak menyediakan Web Bluetooth.';
        setPrinterStatus('disconnected', 'Koneksi Web Bluetooth tidak didukung. Pilih printer dari dialog cetak perangkat.');
        return;
    }

    if (printerSupport) printerSupport.textContent = 'Pilih printer BLE/GATT yang dapat ditulis. Printer VSC belum diverifikasi karena nomor model/protokol belum diketahui.';
    await reconnectRememberedPrinter();
});
document.querySelector('[data-printer-dialog-close]')?.addEventListener('click', () => printerDialog?.close());
printerConnectButton?.addEventListener('click', connectPrinterButton);
printerDisconnectButton?.addEventListener('click', disconnectPrinter);
window.addEventListener('pageshow', () => { void reconnectRememberedPrinter(); });
window.addEventListener('online', () => { void reconnectRememberedPrinter(); });
document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') void reconnectRememberedPrinter();
});
void reconnectRememberedPrinter();

printerWebPrintButton?.addEventListener('click', async () => {
    if (!printerCharacteristic) {
        setPrinterStatus('disconnected', 'Belum ada karakteristik cetak yang siap. Gunakan dialog cetak perangkat.');
        return;
    }
    const text = asciiReceiptText();
    if (!text) {
        setPrinterStatus('disconnected', 'Halaman ini tidak berisi struk. Cetak via Bluetooth web hanya tersedia pada halaman struk.');
        return;
    }

    printerWebPrintButton.disabled = true;
    try {
        const payload = new TextEncoder().encode(`\x1B@\n${text}\n\n\n`);
        for (let offset = 0; offset < payload.length; offset += 20) {
            const chunk = payload.slice(offset, offset + 20);
            if (printerCharacteristic.properties.writeWithoutResponse && printerCharacteristic.writeValueWithoutResponse) {
                await printerCharacteristic.writeValueWithoutResponse(chunk);
            } else if (printerCharacteristic.writeValueWithResponse) {
                await printerCharacteristic.writeValueWithResponse(chunk);
            } else {
                await printerCharacteristic.writeValue(chunk);
            }
        }
        setPrinterStatus('connected', 'Data struk terkirim ke karakteristik BLE. Hasil/format ESC/POS bergantung model printer.');
    } catch (error) {
        setPrinterStatus('disconnected', error instanceof Error
            ? `Cetak Bluetooth gagal: ${error.message}. Gunakan dialog cetak perangkat.`
            : 'Cetak Bluetooth gagal. Gunakan dialog cetak perangkat.');
    } finally {
        printerWebPrintButton.disabled = false;
    }
});

document.querySelectorAll('[data-material-cost-form]').forEach((form) => {
    const quantity = form.querySelector('[data-cost-quantity]');
    const totalCost = form.querySelector('[data-total-cost]');
    const preview = form.querySelector('[data-unit-cost-preview]');
    const updatePreview = () => {
        const qty = Number(quantity?.value);
        const total = moneyNumber(totalCost);
        if (!qty || !Number.isFinite(qty) || !totalCost?.value || !Number.isFinite(total)) {
            preview.value = 'Masukkan jumlah dan total harga';
            return;
        }

        const unitCost = total / qty;
        preview.value = `Rp${unitCost.toLocaleString('id-ID', { maximumFractionDigits: 2 })}`;
    };

    quantity?.addEventListener('input', updatePreview);
    totalCost?.addEventListener('input', updatePreview);
});

document.querySelectorAll('[data-qr-value]').forEach((canvas) => {
    const downloadLink = canvas.parentElement.querySelector('[data-qr-download]');
    QRCode.toCanvas(canvas, canvas.dataset.qrValue, {
        width: 180,
        margin: 1,
        color: { dark: '#273b32', light: '#edf0ea' },
    }).then(() => {
        if (downloadLink) {
            downloadLink.href = canvas.toDataURL('image/png');
            downloadLink.download = `qr-meja-${canvas.dataset.tableNumber}.png`;
        }
    }).catch((error) => {
        const message = canvas.parentElement.querySelector('[data-qr-error]');
        if (message) message.textContent = error instanceof Error ? error.message : 'QR gagal dibuat.';
    });

    document.querySelectorAll('[data-print-selection]').forEach((form) => {
        let nextIndex = form.querySelectorAll('[data-print-item]').length;
        const items = form.querySelector('[data-print-items]');
        const itemOptions = items.querySelector('[name$="[item]"]').innerHTML;

        form.addEventListener('click', (event) => {
            if (event.target.closest('[data-add-print-item]')) {
                const row = document.createElement('div');
                row.className = 'inline-form';
                row.dataset.printItem = '';
                row.style.margin = '10px 0';
                row.innerHTML = `<select class="field" name="items[${nextIndex}][item]" aria-label="Menu atau produk barcode" required>${itemOptions}</select>
                    <input class="field" name="items[${nextIndex}][quantity]" type="number" min="1" max="100" value="1" aria-label="Jumlah label" required>
                    <button class="button button-danger button-small" type="button" data-remove-print-item aria-label="Hapus menu">Hapus</button>`;
                items.append(row);
                nextIndex += 1;
                return;
            }

            const removeButton = event.target.closest('[data-remove-print-item]');
            if (removeButton && items.querySelectorAll('[data-print-item]').length > 1) {
                removeButton.closest('[data-print-item]').remove();
            }
        });
    });

    document.querySelectorAll('[data-summary-chart]').forEach((chart) => {
        const points = JSON.parse(chart.dataset.points || '[]');
        const showExpenses = chart.dataset.showExpenses === 'true';
        const svgNamespace = 'http://www.w3.org/2000/svg';
        const width = 900;
        const height = 280;
        const padding = { top: 20, right: 24, bottom: 34, left: 76 };
        const plotWidth = width - padding.left - padding.right;
        const plotHeight = height - padding.top - padding.bottom;
        const values = points.flatMap((point) => [
            Number(point.income) || 0,
            showExpenses ? Number(point.expenses) || 0 : 0,
            Number(point.target) || 0,
        ]);
        const maximum = Math.max(1, ...values);
        const y = (value) => padding.top + plotHeight - (value / maximum) * plotHeight;
        const x = (index) => padding.left + (points.length < 2 ? plotWidth / 2 : index * plotWidth / (points.length - 1));
        const element = (name, attributes = {}) => {
            const node = document.createElementNS(svgNamespace, name);
            Object.entries(attributes).forEach(([key, value]) => node.setAttribute(key, String(value)));
            return node;
        };
        const formatCompact = (value) => `Rp${new Intl.NumberFormat('id-ID', { notation: 'compact', maximumFractionDigits: 1 }).format(value)}`;
        chart.replaceChildren();

        for (let tick = 0; tick <= 4; tick += 1) {
            const value = maximum * tick / 4;
            const lineY = y(value);
            chart.append(element('line', { x1: padding.left, y1: lineY, x2: width - padding.right, y2: lineY, class: 'summary-chart-gridline' }));
            const label = element('text', { x: padding.left - 10, y: lineY + 4, 'text-anchor': 'end', class: 'summary-chart-axis' });
            label.textContent = formatCompact(value);
            chart.append(label);
        }

        [0, 6, 13, 20, points.length - 1].filter((index, position, list) => index >= 0 && list.indexOf(index) === position).forEach((index) => {
            const label = element('text', { x: x(index), y: height - 8, 'text-anchor': 'middle', class: 'summary-chart-axis' });
            label.textContent = String(points[index].day);
            chart.append(label);
        });

        const drawSeries = (key, className) => {
            const series = points.map((point) => Number(point[key]) || 0);
            const path = series.map((value, index) => `${index ? 'L' : 'M'} ${x(index)} ${y(value)}`).join(' ');
            chart.append(element('path', { d: path, class: `summary-chart-line ${className}` }));
            series.forEach((value, index) => {
                const dot = element('circle', { cx: x(index), cy: y(value), r: 3, class: `summary-chart-point ${className}` });
                const title = element('title');
                title.textContent = `${points[index].date}: ${currency.format(value)}`;
                dot.append(title);
                chart.append(dot);
            });
        };

        drawSeries('income', 'income');
        if (showExpenses) drawSeries('expenses', 'expense');
        if (points.some((point) => Number(point.target) > 0)) drawSeries('target', 'target');
    });

    document.querySelectorAll('[data-barcode-print-root]').forEach((root) => {
        const errorBanner = document.querySelector('[data-print-error]');
        const printButton = document.querySelector('[data-print-labels]');
        const renderBarcodes = () => {
            try {
                root.querySelectorAll('[data-label-barcode]').forEach((svg) => {
                    JsBarcode(svg, svg.dataset.labelBarcode, {
                        format: 'CODE128',
                        displayValue: false,
                        height: 45,
                        margin: 0,
                    });

                    document.querySelectorAll('[data-stock-take-form]').forEach((form) => {
                        form.addEventListener('change', (event) => {
                            const toggle = event.target.closest('[data-stock-take-toggle]');
                            if (!toggle) return;
                            const row = toggle.closest('[data-stock-take-row]');
                            row.querySelectorAll('[data-stock-take-value]').forEach((field) => {
                                field.disabled = !toggle.checked;
                            });
                        });
                    });
                });
                if (errorBanner) errorBanner.hidden = true;
                return true;
            } catch (error) {
                if (errorBanner) {
                    errorBanner.hidden = false;
                    errorBanner.textContent = error instanceof Error ? error.message : 'Barcode gagal dibuat.';
                }
                return false;
            }
        };

        if (!renderBarcodes()) return;
        printButton?.addEventListener('click', () => {
            if (renderBarcodes()) window.print();
        });
    });
});

document.querySelectorAll('[data-purchase-form]').forEach((form) => {
    const rows = form.querySelector('[data-purchase-items]');
    const template = form.querySelector('[data-purchase-template]');
    const addButton = form.querySelector('[data-add-purchase-item]');
    const estimate = form.querySelector('[data-purchase-estimate]');
    const purchaseType = form.querySelector('[data-purchase-type]');
    let nextIndex = rows.querySelectorAll('[data-purchase-item]').length;

    const updateItemOptions = () => {
        rows.querySelectorAll('[data-purchase-item-select]').forEach((select) => {
            const selected = select.selectedOptions[0];
            if (selected?.dataset.itemType && selected.dataset.itemType !== purchaseType.value) {
                select.value = '';
            }
            select.querySelectorAll('[data-item-type]').forEach((option) => {
                option.disabled = option.dataset.itemType !== purchaseType.value;
            });
        });
    };

    const updateRows = () => {
        updateItemOptions();
        const rowElements = [...rows.querySelectorAll('[data-purchase-item]')];
        rowElements.forEach((row) => {
            const removeButton = row.querySelector('[data-remove-purchase-item]');
            removeButton.disabled = rowElements.length <= 1;
        });

        const subtotal = rowElements.reduce((sum, row) => {
            const quantity = Number(row.querySelector('[name$="[quantity]"]').value);
            const unitCost = moneyNumber(row.querySelector('[name$="[unit_cost]"]'));
            return sum + (Number.isFinite(quantity) && Number.isFinite(unitCost) ? Math.round(quantity * unitCost) : 0);
        }, 0);
        estimate.textContent = `Perkiraan subtotal: ${currency.format(subtotal)}. Nilai akhir dihitung di server.`;
    };

    purchaseType?.addEventListener('change', updateRows);
    addButton?.addEventListener('click', () => {
        const fragment = template.content.cloneNode(true);
        fragment.querySelectorAll('[id], [for], [name]').forEach((element) => {
            ['id', 'for', 'name'].forEach((attribute) => {
                if (element.hasAttribute(attribute)) {
                    element.setAttribute(attribute, element.getAttribute(attribute).replaceAll('__INDEX__', String(nextIndex)));
                }
            });
        });
        rows.append(fragment);
        nextIndex += 1;
        updateRows();
    });

    rows.addEventListener('click', (event) => {
        const removeButton = event.target.closest('[data-remove-purchase-item]');
        if (!removeButton || rows.querySelectorAll('[data-purchase-item]').length <= 1) return;
        removeButton.closest('[data-purchase-item]').remove();
        updateRows();
    });

    rows.addEventListener('input', updateRows);
    rows.addEventListener('change', updateRows);
    updateRows();
});

document.querySelectorAll('[data-charge-template]').forEach((template) => {
    const form = template.closest('section')?.querySelector('form[action*="/biaya"]');
    const rows = form?.querySelector('[data-charge-rows]');
    const addButton = form?.querySelector('[data-add-charge]');
    if (!form || !rows || !addButton) return;
    let nextIndex = Number(rows.dataset.nextIndex || 0);

    addButton.addEventListener('click', () => {
        const row = document.createElement('tbody');
        row.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(nextIndex));
        rows.append(row.firstElementChild);
        nextIndex += 1;
    });

    rows.addEventListener('click', (event) => {
        const removeButton = event.target.closest('[data-remove-charge]');
        if (removeButton) removeButton.closest('[data-charge-row]')?.remove();
    });
});

document.querySelectorAll('[data-account-template]').forEach((template) => {
    const form = template.closest('section')?.querySelector('form[action*="/rekening"]');
    const rows = form?.querySelector('[data-account-rows]');
    const addButton = form?.querySelector('[data-add-account]');
    if (!form || !rows || !addButton) return;
    let nextIndex = Number(rows.dataset.nextIndex || 0);

    addButton.addEventListener('click', () => {
        const row = document.createElement('tbody');
        row.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(nextIndex));
        rows.append(row.firstElementChild);
        nextIndex += 1;
    });

    rows.addEventListener('click', (event) => {
        const removeButton = event.target.closest('[data-remove-account]');
        if (removeButton) removeButton.closest('[data-account-row]')?.remove();
    });
});

document.querySelectorAll('[data-payment-method]').forEach((method) => {
    const form = method.closest('form');
    const bankSelector = form?.querySelector('[data-bank-selector]');
    const updateBankVisibility = () => {
        if (bankSelector) bankSelector.hidden = method.value !== 'transfer';
    };

    method.addEventListener('change', updateBankVisibility);
    updateBankVisibility();
});

function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, (character) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    })[character]);
}
