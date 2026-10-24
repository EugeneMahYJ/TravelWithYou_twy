(() => {
    const form = document.querySelector('[data-checkout-form]');
    if (!form) return;

    const methodInput = form.querySelector('[data-payment-method]');
    const tabs = [...form.querySelectorAll('[data-payment-tab]')];
    const panels = [...form.querySelectorAll('[data-payment-panel]')];
    const sameBilling = form.querySelector('[data-billing-same]');
    const billingAddress = form.querySelector('[data-billing-address]');
    const button = form.querySelector('[data-confirm-button]');

    const selectMethod = (method) => {
        methodInput.value = method;
        tabs.forEach((tab) => {
            const selected = tab.dataset.paymentTab === method;
            tab.setAttribute('aria-selected', String(selected));
        });
        panels.forEach((panel) => {
            panel.hidden = panel.dataset.paymentPanel !== method;
        });
    };

    const syncBilling = () => {
        const same = sameBilling.checked;
        billingAddress.hidden = same;
        billingAddress.querySelector('input').disabled = same;
    };

    tabs.forEach((tab) => tab.addEventListener('click', () => selectMethod(tab.dataset.paymentTab)));
    sameBilling.addEventListener('change', syncBilling);
    selectMethod(methodInput.value || 'card');
    syncBilling();

    form.addEventListener('submit', (event) => {
        if (event.submitter?.name === 'apply_promo') return;

        form.querySelectorAll('[data-client-error]').forEach((node) => node.remove());
        const errors = [];
        const check = (name, message, valid) => {
            const field = form.elements.namedItem(name);
            if (!valid(field)) {
                errors.push(field);
                const error = document.createElement('small');
                error.className = 'field-error';
                error.setAttribute('role', 'alert');
                error.dataset.clientError = '';
                error.textContent = message;
                field.insertAdjacentElement('afterend', error);
            }
        };
        ['name', 'phone', 'contact_address', 'traveller_name', 'traveller_dob', 'nationality', 'passport_number', 'passport_expiry']
            .forEach((name) => check(name, 'Required', (field) => Boolean(field.value.trim())));
        check('email', 'Enter a valid email address', (field) => /^\S+@\S+\.\S+$/.test(field.value));

        if (methodInput.value === 'card') {
            const number = form.elements.namedItem('card_number');
            if (number) check('card_number', 'Card number must be 16 digits', (field) => /^\d{16}$/.test(field.value.replace(/\s/g, '')));
            const cardName = form.elements.namedItem('card_name');
            if (cardName) check('card_name', 'Required', (field) => Boolean(field.value.trim()));
            const expiry = form.elements.namedItem('card_expiry');
            if (expiry) check('card_expiry', 'Required', (field) => /^(0[1-9]|1[0-2])\/\d{2}$/.test(field.value));
            const cvc = form.elements.namedItem('card_cvc');
            if (cvc) check('card_cvc', '3 or 4 digits', (field) => /^\d{3,4}$/.test(field.value));
        }

        [
            ['terms', 'Please accept the Terms & Conditions'],
            ['privacy', 'Please accept the Privacy Policy'],
            ['cancellation', 'Please acknowledge the cancellation policy'],
        ].forEach(([name, message]) => {
            const field = form.elements.namedItem(name);
            if (!field.checked) {
                errors.push(field);
                const error = document.createElement('small');
                error.className = 'field-error';
                error.setAttribute('role', 'alert');
                error.dataset.clientError = '';
                error.textContent = message;
                field.closest('.checkout-checkbox').insertAdjacentElement('afterend', error);
            }
        });

        if (errors.length) {
            event.preventDefault();
            errors[0].focus();
            let toast = document.querySelector('[data-checkout-validation-toast]');
            if (!toast) {
                toast = document.createElement('p');
                toast.className = 'checkout-validation-toast';
                toast.setAttribute('role', 'alert');
                toast.dataset.checkoutValidationToast = '';
                form.prepend(toast);
            }
            toast.textContent = 'Please fix the highlighted fields';
            return;
        }

        if (button) {
            button.disabled = true;
            button.textContent = 'Processing…';
        }
    });
})();
