(() => {
    const form = document.querySelector('[data-checkout-form]');
    if (!form) return;

    const methodInput = form.querySelector('[data-payment-method]');
    const options = [...form.querySelectorAll('[data-payment-option]')];
    const panels = [...form.querySelectorAll('[data-payment-panel]')];
    const sameBilling = form.querySelector('[data-billing-same]');
    const billingAddress = form.querySelector('[data-billing-address]');
    const button = form.querySelector('[data-confirm-button]');
    const selectionError = form.querySelector('[data-payment-selection-error]');

    const selectMethod = (method) => {
        methodInput.value = method;
        options.forEach((option) => {
            const selected = option.dataset.paymentOption === method;
            option.setAttribute('aria-checked', String(selected));
            option.tabIndex = selected ? 0 : -1;
        });
        panels.forEach((panel) => {
            panel.hidden = panel.dataset.paymentPanel !== method;
        });
        selectionError.hidden = true;
        const methodIsAvailable = method === 'card';
        button.disabled = button.dataset.cartUnavailable === 'true' || !methodIsAvailable;
        button.textContent = methodIsAvailable
            ? button.dataset.cardLabel
            : `${options.find((option) => option.dataset.paymentOption === method)?.dataset.paymentLabel || 'This method'} not available yet`;
    };

    const syncBilling = () => {
        const same = sameBilling.checked;
        billingAddress.hidden = same;
        billingAddress.querySelector('input').disabled = same;
    };

    options.forEach((option, index) => {
        option.addEventListener('click', () => selectMethod(option.dataset.paymentOption));
        option.addEventListener('keydown', (event) => {
            let nextIndex = index;
            if (event.key === 'ArrowDown' || event.key === 'ArrowRight') {
                nextIndex = (index + 1) % options.length;
            } else if (event.key === 'ArrowUp' || event.key === 'ArrowLeft') {
                nextIndex = (index - 1 + options.length) % options.length;
            } else if (event.key === 'Home') {
                nextIndex = 0;
            } else if (event.key === 'End') {
                nextIndex = options.length - 1;
            } else {
                return;
            }
            event.preventDefault();
            options[nextIndex].focus();
            selectMethod(options[nextIndex].dataset.paymentOption);
        });
    });
    sameBilling.addEventListener('change', syncBilling);
    const initialMethod = options.some((option) => option.dataset.paymentOption === methodInput.value)
        ? methodInput.value
        : 'card';
    selectMethod(initialMethod);
    syncBilling();

    form.addEventListener('submit', (event) => {
        if (event.submitter?.name === 'apply_promo') return;

        if (methodInput.value !== 'card') {
            event.preventDefault();
            selectionError.hidden = false;
            selectionError.tabIndex = -1;
            selectionError.focus();
            return;
        }

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
        ['name', 'phone', 'contact_address']
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
