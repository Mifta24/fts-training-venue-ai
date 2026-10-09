import { createDatePicker } from './date-picker';

/**
 * Guided booking request wizard: five short steps at the booking desk — dates,
 * participants, room, contact, summary — then a reference number and
 * WhatsApp / phone / email hand-over. Progress survives leaving the page and
 * switching language (sessionStorage), and every rule is enforced again on the
 * server, which is the only source of price and availability.
 */
function initReservationWizard() {
    const root = document.querySelector('[data-wizard]');
    if (!root) return;

    const labels = JSON.parse(root.querySelector('[data-wizard-labels]').textContent);
    const form = root.querySelector('[data-wizard-form]');
    const flow = root.querySelector('[data-wizard-flow]');
    const done = root.querySelector('[data-wizard-done]');
    const steps = [...root.querySelectorAll('[data-step]')];
    const progress = [...root.querySelectorAll('[data-progress-step]')];
    const stepLabel = root.querySelector('[data-wizard-step-label]');
    const daysHint = root.querySelector('[data-days-hint]');
    const backButton = root.querySelector('[data-wizard-back]');
    const nextButton = root.querySelector('[data-wizard-next]');
    const submitButton = root.querySelector('[data-wizard-submit]');
    const errorBox = root.querySelector('[data-wizard-error]');
    const errorText = root.querySelector('[data-wizard-error-text]');
    const alternativesBox = root.querySelector('[data-wizard-alternatives]');
    const roomOptions = [...root.querySelectorAll('[data-room-option]')];
    const layoutField = root.querySelector('[data-layout-field]');
    const layoutOptions = [...root.querySelectorAll('[data-layout-option]')];
    const cateringField = root.querySelector('[data-catering-field]');
    const cateringPriceText = root.querySelector('[data-catering-price-text]');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    const config = {
        quoteUrl: root.dataset.quoteUrl,
        submitUrl: root.dataset.submitUrl,
        locale: root.dataset.locale,
        currency: root.dataset.currency,
        today: root.dataset.today,
        maxDays: Number(root.dataset.maxDays),
        draftKey: `reservation_draft_${root.dataset.venueSlug}`,
        tokenKey: `coordinator_token_${root.dataset.venueSlug}`,
    };

    const fieldStep = {
        start_date: 1, end_date: 1, participants: 2, rooms: 2, room_slug: 3, seating_layout: 3, catering: 3,
        guest_name: 4, organization: 4, event_title: 4, contact_type: 4, contact_value: 4, special_request: 4,
    };

    let current = 1;
    let quote = null;
    let busy = false;

    const text = (template, values) => Object.entries(values).reduce((out, [key, value]) => out.replace(`:${key}`, value), template);
    const money = (value) => `${config.currency} ${new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(Number(value) || 0)}`;

    function formatDate(iso) {
        const [year, month, day] = iso.split('-').map(Number);
        return new Intl.DateTimeFormat(config.locale, { day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(year, month - 1, day));
    }

    const datePicker = createDatePicker({
        root: root.querySelector('[data-date-picker]'),
        startDate: form.elements.start_date,
        endDate: form.elements.end_date,
        config,
        labels,
        formatDate,
        onChange: () => {
            clearError();
            form.dispatchEvent(new Event('input', { bubbles: true }));
        },
    });

    /** The first and the last day both count, so a one-day event is one day. */
    function daysBetween(startDate, endDate) {
        if (!startDate || !endDate) return 0;
        const [y1, m1, d1] = startDate.split('-').map(Number);
        const [y2, m2, d2] = endDate.split('-').map(Number);
        return Math.round((Date.UTC(y2, m2 - 1, d2) - Date.UTC(y1, m1 - 1, d1)) / 86400000) + 1;
    }

    function values() {
        const data = new FormData(form);
        return {
            start_date: data.get('start_date') || '',
            end_date: data.get('end_date') || '',
            participants: Number(data.get('participants')) || 0,
            rooms: Number(data.get('rooms')) || 0,
            room_slug: data.get('room_slug') || '',
            seating_layout: data.get('seating_layout') || '',
            catering: form.elements.catering.checked && !cateringField.hidden,
            guest_name: (data.get('guest_name') || '').trim(),
            organization: (data.get('organization') || '').trim(),
            event_title: (data.get('event_title') || '').trim(),
            contact_type: data.get('contact_type') || 'whatsapp',
            contact_value: (data.get('contact_value') || '').trim(),
            special_request: (data.get('special_request') || '').trim(),
        };
    }

    function saveDraft() {
        try {
            sessionStorage.setItem(config.draftKey, JSON.stringify({ step: current, values: values() }));
        } catch { /* storage unavailable: the wizard still works without it */ }
    }

    function clearDraft() {
        try {
            sessionStorage.removeItem(config.draftKey);
        } catch { /* nothing to clear */ }
    }

    function restoreDraft() {
        try {
            const draft = JSON.parse(sessionStorage.getItem(config.draftKey) || 'null');
            if (!draft) return;
            const { values: saved } = draft;
            ['start_date', 'end_date', 'participants', 'rooms', 'guest_name', 'organization', 'event_title', 'contact_value', 'special_request'].forEach((name) => {
                if (saved[name] !== undefined && saved[name] !== '' && saved[name] !== 0) form.elements[name].value = saved[name];
            });
            if (saved.contact_type) form.elements.contact_type.value = saved.contact_type;
            if (saved.room_slug) form.elements.room_slug.value = saved.room_slug;
            if (saved.seating_layout !== undefined) form.elements.seating_layout.value = saved.seating_layout;
            form.elements.catering.checked = Boolean(saved.catering);
            current = Math.min(Math.max(Number(draft.step) || 1, 1), steps.length);
        } catch { /* ignore a corrupt draft */ }
    }

    function clearError() {
        errorBox.hidden = true;
        errorText.textContent = '';
        alternativesBox.hidden = true;
        alternativesBox.querySelector('ul').replaceChildren();
    }

    function showError(message, alternatives = []) {
        errorText.textContent = message;
        errorBox.hidden = false;

        const list = alternativesBox.querySelector('ul');
        list.replaceChildren();
        alternatives.forEach((alternative) => {
            const item = document.createElement('li');
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'wizard-secondary';
            button.textContent = `${alternative.name} · ${money(alternative.total)}`;
            button.addEventListener('click', () => {
                form.elements.room_slug.value = alternative.slug;
                refreshRooms();
                clearError();
                saveDraft();
            });
            item.appendChild(button);
            list.appendChild(item);
        });
        alternativesBox.hidden = alternatives.length === 0;
    }

    function selectedOption() {
        const slug = form.elements.room_slug.value;
        return roomOptions.find((option) => option.querySelector('input').value === slug) || null;
    }

    const layoutsOf = (option) => JSON.parse(option.dataset.layouts || '{}');

    /** Participants one room holds: in the chosen seating layout, or its overall limit. */
    function capacityOf(option, layout) {
        const layouts = layoutsOf(option);
        return layout && layouts[layout] ? Number(layouts[layout]) : Number(option.dataset.capacity);
    }

    function fits(option, party, layout = '') {
        return party.participants <= capacityOf(option, layout) * party.rooms;
    }

    function refreshRooms() {
        const party = values();

        roomOptions.forEach((option) => {
            const input = option.querySelector('input');
            const suitable = fits(option, party);
            input.disabled = !suitable;
            option.classList.toggle('is-disabled', !suitable);
            option.querySelector('[data-room-hint]').textContent = suitable ? '' : labels.too_small;
            if (!suitable && input.checked) input.checked = false;
        });

        const chosen = selectedOption();

        // Only the seating layouts this room offers are shown, each with what it holds.
        const layouts = chosen ? layoutsOf(chosen) : {};
        layoutField.hidden = !chosen || Object.keys(layouts).length === 0;
        layoutOptions.forEach((option) => {
            const type = option.dataset.layoutOption;
            const offered = Boolean(layouts[type]);
            option.hidden = !offered;
            const input = option.querySelector('input');
            if (!offered) {
                input.checked = false;
                return;
            }
            const capacity = Number(layouts[type]);
            option.querySelector('[data-layout-count]').textContent = `· ${capacity}`;
            input.disabled = party.participants > capacity * party.rooms;
            option.title = text(labels.layout_hint, { count: capacity * party.rooms });
            if (input.disabled && input.checked) input.checked = false;
        });
        if (!form.elements.seating_layout.value && !layoutField.hidden) {
            layoutField.querySelector('input[value=""]').checked = true;
        }

        const cateringOffered = Boolean(chosen) && chosen.dataset.catering === '1';
        cateringField.hidden = !cateringOffered;
        if (cateringOffered) {
            cateringPriceText.textContent = `(${text(labels.catering_price, { price: money(chosen.dataset.cateringPrice) })})`;
        } else {
            form.elements.catering.checked = false;
        }
    }

    function refreshDays() {
        const startDate = form.elements.start_date.value;
        const days = daysBetween(startDate, form.elements.end_date.value);

        if (days > 0) daysHint.textContent = `${days} ${labels.days}`;
        else if (!datePicker.hasOpenDays) daysHint.textContent = labels.cal_none;
        else daysHint.textContent = startDate ? labels.cal_pick_end : labels.cal_pick_start;
    }

    function validate(step) {
        const data = values();

        if (step === 1) {
            if (!data.start_date) return { field: 'start_date', message: labels.cal_pick_start };
            if (data.start_date < config.today) return { field: 'start_date', message: labels.start_past };
            if (!data.end_date) return { field: 'end_date', message: labels.cal_pick_end };
            if (data.end_date < data.start_date) return { field: 'end_date', message: labels.end_after };
            if (daysBetween(data.start_date, data.end_date) > config.maxDays) return { field: 'end_date', message: text(labels.too_long, { max: config.maxDays }) };
        }

        if (step === 2) {
            if (data.participants < 1) return { field: 'participants', message: labels.invalid };
            if (data.rooms < 1) return { field: 'rooms', message: labels.invalid };
        }

        if (step === 3) {
            const chosen = selectedOption();
            if (!chosen) return { field: 'room_slug', message: labels.select_room };
            if (!fits(chosen, data, data.seating_layout)) return { field: 'room_slug', message: labels.too_small };
        }

        if (step === 4) {
            if (!data.guest_name) return { field: 'guest_name', message: labels.invalid };
            const valid = data.contact_type === 'email'
                ? /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.contact_value)
                : /^\+?[0-9\s\-().]{6,20}$/.test(data.contact_value);
            if (!valid) return { field: 'contact_value', message: data.contact_type === 'email' ? labels.contact_email : labels.contact_phone };
        }

        return null;
    }

    function setBusy(value, label = null) {
        busy = value;
        [backButton, nextButton, submitButton].forEach((button) => { button.disabled = value; });
        if (label) nextButton.firstChild.textContent = `${label} `;
        else nextButton.firstChild.textContent = `${labels.next} `;
    }

    function showStep(step, focus = false) {
        current = step;
        steps.forEach((element, index) => { element.hidden = index + 1 !== step; });
        progress.forEach((element, index) => {
            element.classList.toggle('is-current', index + 1 === step);
            element.classList.toggle('is-done', index + 1 < step);
            if (index + 1 === step) element.setAttribute('aria-current', 'step');
            else element.removeAttribute('aria-current');
        });
        stepLabel.textContent = `${text(labels.step_of, { current: step, total: steps.length })} · ${labels.steps[step - 1]}`;
        backButton.hidden = step === 1;
        nextButton.hidden = step === steps.length;
        submitButton.hidden = step !== steps.length;

        if (step === 2 || step === 3) refreshRooms();
        if (step === 3 && roomOptions.every((option) => option.classList.contains('is-disabled'))) showError(labels.capacity);
        if (step === steps.length) renderSummary();
        if (focus) steps[step - 1].querySelector('input:not([type="hidden"]):not([disabled]), textarea, [data-cal-focus]')?.focus({ preventScroll: true });
        saveDraft();
    }

    function goToField(field) {
        showStep(fieldStep[field] || current, true);
    }

    async function post(url, payload) {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken || '' },
            body: JSON.stringify(payload),
        });
        const body = await response.json().catch(() => ({}));
        return { ok: response.ok, status: response.status, body };
    }

    function stayPayload() {
        const { start_date, end_date, participants, rooms, room_slug, seating_layout, catering } = values();
        return { start_date, end_date, participants, rooms, room_slug, seating_layout: seating_layout || null, catering, locale: config.locale };
    }

    function handleFailure(result) {
        if (result.status === 422 && result.body.errors) {
            const [field, messages] = Object.entries(result.body.errors)[0];
            showError(messages[0], result.body.alternatives || []);
            if (fieldStep[field] && fieldStep[field] !== current) showStep(fieldStep[field]);
            return;
        }
        showError(labels.error_generic);
    }

    async function next() {
        if (busy || current >= steps.length) return;
        clearError();

        const problem = validate(current);
        if (problem) {
            showError(problem.message);
            form.elements[problem.field]?.focus?.();
            return;
        }

        if (current === 3) {
            setBusy(true, labels.checking);
            try {
                const result = await post(config.quoteUrl, stayPayload());
                if (!result.ok) {
                    handleFailure(result);
                    return;
                }
                quote = result.body;
            } catch {
                showError(labels.error_network);
                return;
            } finally {
                setBusy(false);
            }
        }

        showStep(current + 1, true);
    }

    function summaryRow(label, value, step) {
        const wrapper = document.createElement('div');
        const term = document.createElement('dt');
        term.textContent = label;
        const detail = document.createElement('dd');
        const span = document.createElement('span');
        span.textContent = value;
        const edit = document.createElement('button');
        edit.type = 'button';
        edit.textContent = labels.edit;
        edit.addEventListener('click', () => showStep(step, true));
        detail.append(span, edit);
        wrapper.append(term, detail);
        return wrapper;
    }

    function renderSummary() {
        const data = values();
        const chosen = selectedOption();
        const days = daysBetween(data.start_date, data.end_date);
        const layoutName = data.seating_layout
            ? form.querySelector(`[data-layout-option="${data.seating_layout}"] span`)?.firstChild?.textContent.trim()
            : '';

        const room = [chosen ? chosen.dataset.name : '', layoutName, data.catering ? labels.catering_short : ''].filter(Boolean).join(' · ');
        const rows = [
            summaryRow(labels.dates, `${formatDate(data.start_date)} → ${formatDate(data.end_date)} (${days} ${labels.days})`, 1),
            summaryRow(labels.group, `${labels.participants}: ${data.participants} · ${labels.rooms}: ${data.rooms}`, 2),
            summaryRow(labels.room, room, 3),
        ];
        if (data.event_title || data.organization) rows.push(summaryRow(labels.programme, [data.event_title, data.organization].filter(Boolean).join(' · '), 4));
        rows.push(summaryRow(labels.contact, `${data.guest_name} · ${labels[data.contact_type]}: ${data.contact_value}`, 4));
        if (data.special_request) rows.push(summaryRow(labels.special, data.special_request, 4));

        root.querySelector('[data-summary]').replaceChildren(...rows);
        root.querySelector('[data-summary-total]').textContent = quote ? money(quote.grand_total) : '';

        const discount = root.querySelector('[data-summary-discount]');
        const hasDiscount = Boolean(quote && quote.discount_percent > 0);
        discount.hidden = !hasDiscount;
        if (hasDiscount) {
            discount.querySelector('[data-summary-discount-percent]').textContent = `−${quote.discount_percent}%`;
            discount.querySelector('[data-summary-discount-amount]').textContent = `−${money(quote.discount_total)}`;
        }
    }

    function showDone(result) {
        flow.hidden = true;
        done.hidden = false;
        root.querySelector('[data-done-reference]').textContent = result.reference;

        [['whatsapp', 'whatsapp_url'], ['phone', 'phone_url'], ['email', 'email_url']].forEach(([name, key]) => {
            const link = root.querySelector(`[data-done-${name}]`);
            const url = result.handover?.[key];
            link.hidden = !url;
            if (url) link.href = url;
        });
    }

    async function submit(event) {
        event.preventDefault();
        if (busy || current !== steps.length) return;
        clearError();

        const problem = [1, 2, 3, 4].map(validate).find(Boolean);
        if (problem) {
            showError(problem.message);
            goToField(problem.field);
            return;
        }

        setBusy(true);
        submitButton.textContent = labels.sending;

        try {
            const result = await post(config.submitUrl, {
                ...stayPayload(),
                guest_name: values().guest_name,
                organization: values().organization || null,
                event_title: values().event_title || null,
                contact_type: values().contact_type,
                contact_value: values().contact_value,
                special_request: values().special_request,
                guest_token: localStorage.getItem(config.tokenKey) || undefined,
            });

            if (!result.ok) {
                handleFailure(result);
                return;
            }

            clearDraft();
            showDone(result.body);
        } catch {
            showError(labels.error_network);
        } finally {
            setBusy(false);
            submitButton.textContent = labels.submit;
        }
    }

    function reset() {
        form.reset();
        datePicker.clear();
        quote = null;
        clearError();
        done.hidden = true;
        flow.hidden = false;
        clearDraft();
        refreshDays();
        showStep(1, true);
    }

    function preselect(slug) {
        if (!flow.hidden) {
            refreshRooms();
            const option = roomOptions.find((candidate) => candidate.querySelector('input').value === slug);
            if (option && !option.querySelector('input').disabled) {
                form.elements.room_slug.value = slug;
                refreshRooms();
                saveDraft();
            }
        }
    }

    form.addEventListener('input', () => { refreshDays(); if (current <= 3) refreshRooms(); saveDraft(); });
    form.addEventListener('change', () => { refreshRooms(); saveDraft(); });
    form.addEventListener('submit', submit);
    nextButton.addEventListener('click', next);
    backButton.addEventListener('click', () => { clearError(); showStep(Math.max(1, current - 1), true); });
    root.querySelector('[data-wizard-reset]').addEventListener('click', reset);
    form.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && event.target.tagName !== 'TEXTAREA' && current < steps.length) {
            event.preventDefault();
            next();
        }
    });

    restoreDraft();
    datePicker.show();
    refreshDays();

    if (root.dataset.availabilityUrl) {
        fetch(root.dataset.availabilityUrl, { headers: { Accept: 'application/json' } })
            .then((response) => (response.ok ? response.json() : Promise.reject(new Error('availability unavailable'))))
            .then((availability) => {
                datePicker.setAvailability(availability.dates);
                refreshDays();
            })
            .catch(() => { /* the server still checks every day when the guest continues */ });
    }

    refreshRooms();
    showStep(current);
    preselect(root.dataset.preselectRoom || '');
}

document.addEventListener('DOMContentLoaded', initReservationWizard);
