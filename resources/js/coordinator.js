/**
 * AI Coordinator chat panel. Vanilla JS, no framework: sends guest messages to
 * the Laravel backend and renders both plain text and the structured
 * ui_payload the AI's tools attach (room cards, price quotes, booking
 * confirmations, handover notices) as real DOM, not markdown-in-a-bubble.
 */
function initCoordinator() {
    const root = document.getElementById('coordinator-app');
    const widget = document.querySelector('[data-chat-widget]');
    if (!root || !widget) return;

    const config = {
        startUrl: root.dataset.startUrl,
        messageUrl: root.dataset.messageUrl,
        historyUrl: root.dataset.historyUrl,
        storageKey: root.dataset.storageKey,
        locale: root.dataset.locale,
        currency: root.dataset.currency,
        lobbyUrl: root.dataset.lobbyUrl,
        roomUrlTemplate: root.dataset.roomUrl,
        teamUrl: root.dataset.teamUrl,
        reservationUrlTemplate: root.dataset.reservationUrl,
        labels: {
            placeholder: root.dataset.labelPlaceholder,
            send: root.dataset.labelSend,
            open: root.dataset.labelOpen,
            close: root.dataset.labelClose,
            intro: root.dataset.labelIntro,
            cateringIncluded: root.dataset.labelCateringIncluded,
            maxParticipants: root.dataset.labelMaxParticipants,
            noCatering: root.dataset.labelNoCatering,
            day: root.dataset.labelDay,
            discount: root.dataset.labelDiscount,
            noAvailability: root.dataset.labelNoAvailability,
            bookingReceived: root.dataset.labelBookingReceived,
            reference: root.dataset.labelReference,
            thinking: root.dataset.labelThinking,
            handedOver: root.dataset.labelHandedOver,
            statusSent: root.dataset.labelStatusSent,
            statusWaiting: root.dataset.labelStatusWaiting,
            statusReplied: root.dataset.labelStatusReplied,
            draftTitle: root.dataset.labelDraftTitle,
            draftBody: root.dataset.labelDraftBody,
            draftKeep: root.dataset.labelDraftKeep,
            draftDiscard: root.dataset.labelDraftDiscard,
            viewDetails: root.dataset.labelViewDetails,
            roomDetailsQuestion: root.dataset.labelRoomDetailsQuestion,
            bookNow: root.dataset.labelBookNow,
            menuHeading: root.dataset.labelMenuHeading,
            team: root.dataset.labelTeam,
            error: root.dataset.labelError,
            slow: root.dataset.labelSlow,
            retry: root.dataset.labelRetry,
        },
    };

    const messagesEl = root.querySelector('[data-messages]');
    const formEl = root.querySelector('[data-chat-form]');
    const inputEl = root.querySelector('[data-chat-input]');
    const submitEl = root.querySelector('[data-chat-submit]');
    const statusBanner = root.querySelector('[data-status-banner]');
    const chatStatus = root.querySelector('[data-chat-status]');
    const thinkingIndicator = root.querySelector('[data-thinking-indicator]');
    const draftDialog = root.querySelector('[data-draft-dialog]');
    const draftKeep = root.querySelector('[data-draft-keep]');
    const draftDiscard = root.querySelector('[data-draft-discard]');
    const chatLog = root.querySelector('#coordinator-chat-log');
    const closeButton = widget.querySelector('[data-chat-close]');
    const launcher = widget.querySelector('[data-chat-launcher]');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    let ready = false;
    let busy = false;
    let handedOver = false;
    let knownMessageCount = 0;
    let pollTimer = null;
    let isOpen = false;
    let draftDialogOpen = false;

    function setChatStatus(text, tone = '') {
        if (!chatStatus) return;

        chatStatus.textContent = text || '';
        chatStatus.className = `chat-status${text ? ` is-${tone}` : ''}`;
        chatStatus.hidden = !text;
    }

    function showDraftDialog() {
        if (!draftDialog) return;

        draftDialogOpen = true;
        draftDialog.hidden = false;
        draftDialog.setAttribute('aria-hidden', 'false');
        requestAnimationFrame(() => draftKeep?.focus());
    }

    function hideDraftDialog({ focusInput = false } = {}) {
        if (!draftDialog) return;

        draftDialogOpen = false;
        draftDialog.hidden = true;
        draftDialog.setAttribute('aria-hidden', 'true');
        if (focusInput) inputEl.focus();
    }

    function syncPanelState() {
        root.classList.toggle('is-open', isOpen);
        root.dataset.chatState = isOpen ? 'open' : 'closed';
        chatLog?.setAttribute('aria-hidden', String(!isOpen));
        formEl?.setAttribute('aria-hidden', String(!isOpen));
        launcher?.setAttribute('aria-expanded', String(isOpen));
    }

    function openPanel({ focus = false } = {}) {
        isOpen = true;
        syncPanelState();
        scrollToBottom();

        if (focus) {
            requestAnimationFrame(() => inputEl.focus());
        }
    }

    function finishClosePanel({ restoreFocus = true } = {}) {
        isOpen = false;
        syncPanelState();
        hideDraftDialog();
        if (restoreFocus) launcher?.focus();
    }

    function closePanel({ confirmDraft = true, restoreFocus = true } = {}) {
        if (confirmDraft && inputEl?.value.trim()) {
            showDraftDialog();
            return;
        }

        finishClosePanel({ restoreFocus });
    }

    closeButton?.addEventListener('click', () => closePanel());
    launcher?.addEventListener('click', () => openPanel({ focus: true }));
    draftKeep?.addEventListener('click', () => hideDraftDialog({ focusInput: true }));
    draftDiscard?.addEventListener('click', () => {
        inputEl.value = '';
        finishClosePanel();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;

        if (draftDialogOpen) {
            hideDraftDialog({ focusInput: true });
            return;
        }

        if (isOpen) closePanel();
    });
    syncPanelState();

    function money(value) {
        const n = Number(value) || 0;
        const numberLocale = config.locale === 'ja' ? 'ja-JP' : config.locale === 'en' ? 'en-US' : 'id-ID';
        return `${config.currency} ${new Intl.NumberFormat(numberLocale, { maximumFractionDigits: 0 }).format(n)}`;
    }

    // Cloudflare drops a request that is still unanswered after 100s, so give
    // up just before that and let the guest retry instead of waiting forever.
    const API_TIMEOUT_MS = 95000;

    async function api(url, body) {
        let response;

        try {
            response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken || '',
                },
                body: JSON.stringify(body || {}),
                signal: AbortSignal.timeout(API_TIMEOUT_MS),
            });
        } catch (err) {
            const error = new Error(err.name === 'TimeoutError' ? 'Request timed out' : 'Network error');
            error.status = err.name === 'TimeoutError' ? 408 : 0;
            throw error;
        }

        if (!response.ok) {
            const payload = await response.json().catch(() => ({}));
            const error = new Error(payload.message || `Request failed (${response.status})`);
            error.status = response.status;
            throw error;
        }

        return response.json();
    }

    function avatarMark() {
        const el = document.createElement('span');
        el.className = 'chat-host-avatar';
        el.setAttribute('aria-hidden', 'true');
        return el;
    }

    function bubble(role, text) {
        const wrap = document.createElement('div');
        wrap.className =
            role === 'guest' ? 'flex justify-end' : 'flex items-end justify-start gap-2';

        if (role !== 'guest') {
            wrap.appendChild(avatarMark());
        }

        const inner = document.createElement('div');
        inner.className = role === 'guest' ? 'chat-bubble is-guest' : 'chat-bubble is-host';
        inner.textContent = text;

        wrap.appendChild(inner);
        return wrap;
    }

    function card(children) {
        const wrap = document.createElement('div');
        wrap.className = 'flex justify-start';
        const inner = document.createElement('div');
        inner.className = 'w-full max-w-[92%] space-y-2';
        children.forEach((c) => inner.appendChild(c));
        wrap.appendChild(inner);
        return wrap;
    }

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));

    /** "24 classroom · 18 U-shape": the first thing anyone planning a training asks. */
    function layoutsLabel(room) {
        return (room.seating_layouts || [])
            .slice(0, 3)
            .map((layout) => `${layout.capacity} ${String(layout.type).replace('_', '-')}`)
            .join(' · ');
    }

    function roomResultCard(room) {
        const el = document.createElement('div');
        el.className = 'chat-card';
        const facts = [room.size_sqm ? `${room.size_sqm} m²` : '', `${room.capacity} ${config.labels.maxParticipants}`, room.floor_label].filter(Boolean).join(' · ');
        el.innerHTML = `
            ${room.thumbnail_url ? `<img src="${escapeHtml(room.thumbnail_url)}" alt="${escapeHtml(room.name)}" class="h-28 w-full object-cover">` : ''}
            <div class="p-3">
                <p class="chat-card-tag">${escapeHtml(facts)}</p>
                <p class="mt-1 font-semibold">${escapeHtml(room.name)}</p>
                <p class="mt-1 text-sm font-semibold">${money(room.total_price)} <span class="font-normal opacity-60">/ ${room.days} ${config.labels.day}</span></p>
                ${layoutsLabel(room) ? `<p class="mt-1 text-xs opacity-70">${escapeHtml(layoutsLabel(room))}</p>` : ''}
                ${room.discount_percent > 0 ? `<p class="chat-card-tag mt-1">−${room.discount_percent}% ${config.labels.discount}</p>` : ''}
                <button type="button" class="chat-card-button" data-detail-slug="${escapeHtml(room.room_slug)}">
                    ${config.labels.viewDetails}
                </button>
            </div>
        `;
        el.querySelector('[data-detail-slug]').addEventListener('click', () => {
            sendMessage(config.labels.roomDetailsQuestion.replace(':room', room.name));
        });
        return el;
    }

    function roomDetailCard(room) {
        const el = document.createElement('div');
        el.className = 'chat-card';

        const images = (room.images || [])
            .slice(0, 4)
            .map((img) => `<img src="${escapeHtml(img.url)}" alt="${escapeHtml(img.alt || room.name)}" class="h-16 w-full rounded object-cover">`)
            .join('');

        el.innerHTML = `
            <div class="p-3">
                <p class="chat-card-tag">${escapeHtml([room.floor_label, room.size_sqm ? `${room.size_sqm} m²` : ''].filter(Boolean).join(' · '))}</p>
                <p class="mt-1 font-semibold">${escapeHtml(room.name)}</p>
                <p class="mt-1 text-xs opacity-70">${escapeHtml(room.description || '')}</p>
                <div class="mt-2 grid grid-cols-4 gap-1">${images}</div>
                <dl class="mt-2 grid grid-cols-2 gap-1 text-xs opacity-70">
                    <div>${room.capacity} ${config.labels.maxParticipants}</div>
                    <div>${room.catering_included ? config.labels.cateringIncluded : config.labels.noCatering}</div>
                    <div class="col-span-2">${escapeHtml(layoutsLabel(room))}</div>
                </dl>
            </div>
        `;
        return el;
    }

    function availabilityCard(payload) {
        const el = document.createElement('div');
        el.className = 'chat-card p-3';

        if (!payload.available) {
            el.innerHTML = `<p class="text-sm opacity-70">${config.labels.noAvailability}</p>`;
            return el;
        }

        const q = payload.quote;
        el.innerHTML = `
            <p class="font-semibold">${escapeHtml(q.name)}</p>
            <p class="chat-card-tag mt-1">${q.start_date} → ${q.end_date} · ${q.days} ${config.labels.day}</p>
            ${q.discount_percent > 0 ? `<p class="mt-1 text-xs opacity-70"><s>${money(q.subtotal)}</s> · −${q.discount_percent}% ${config.labels.discount}</p>` : ''}
            <p class="mt-1 text-base font-semibold">${money(q.grand_total)}</p>
        `;
        return el;
    }

    function bookingConfirmationCard(payload) {
        const b = payload.booking;
        const el = document.createElement('div');
        el.className = 'chat-card p-3';
        el.style.borderLeft = '4px solid var(--marker)';
        el.innerHTML = `
            <p class="text-sm font-semibold">${config.labels.bookingReceived}</p>
            <p class="chat-card-tag mt-1">${config.labels.reference}: ${escapeHtml(b.booking_reference)}</p>
            <p class="mt-1 text-xs opacity-70">${escapeHtml(b.name)} · ${b.start_date} → ${b.end_date} · ${b.days} ${config.labels.day}</p>
            <p class="mt-1 text-sm font-semibold">${money(b.total_price)}</p>
        `;
        return el;
    }

    function handoverCard() {
        const el = document.createElement('div');
        el.className = 'chat-card p-3 text-xs';
        el.style.borderLeft = '4px solid #f3b743';
        el.textContent = config.labels.handedOver;
        return el;
    }

    function renderUiPayload(uiPayload) {
        if (!uiPayload || !uiPayload.length) return;

        uiPayload.forEach((payload) => {
            if (payload.type === 'room_results') {
                messagesEl.appendChild(card(payload.rooms.map(roomResultCard)));
            } else if (payload.type === 'room_detail') {
                messagesEl.appendChild(card([roomDetailCard(payload.room)]));
            } else if (payload.type === 'availability') {
                messagesEl.appendChild(card([availabilityCard(payload)]));
            } else if (payload.type === 'booking_confirmation') {
                messagesEl.appendChild(card([bookingConfirmationCard(payload)]));
            } else if (payload.type === 'handover') {
                messagesEl.appendChild(card([handoverCard()]));
            }
        });
    }

    function scrollToBottom() {
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function setBusy(value) {
        busy = value;
        const disabled = busy || !ready || handedOver;
        inputEl.disabled = disabled;
        submitEl.disabled = disabled;
        if (launcher) launcher.disabled = busy || !ready;
        root.setAttribute('aria-busy', String(busy));
        document.body.classList.toggle('is-thinking', busy);
        if (thinkingIndicator) {
            thinkingIndicator.hidden = !busy;
        }
        document.querySelectorAll('[data-hero-quick-message], [data-ask-ai-button], [data-quick-message]')
            .forEach((button) => { button.disabled = disabled; });
        submitEl.textContent = busy ? config.labels.thinking : config.labels.send;
        if (busy) scrollToBottom();
    }

    function showHandedOverBanner() {
        handedOver = true;
        statusBanner.textContent = config.labels.handedOver;
        statusBanner.classList.remove('hidden');
        setChatStatus(config.labels.statusWaiting, 'waiting');
        setBusy(busy);
        startPolling();
    }

    function clearHandedOverBanner() {
        handedOver = false;
        statusBanner.classList.add('hidden');
        setBusy(busy);
        stopPolling();
    }

    function startPolling() {
        if (pollTimer) return;
        pollTimer = setInterval(pollForStaffReplies, 4000);
    }

    function stopPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    async function pollForStaffReplies() {
        const guestToken = localStorage.getItem(config.storageKey);
        if (!guestToken) return;

        try {
            const response = await fetch(`${config.historyUrl}?guest_token=${encodeURIComponent(guestToken)}`, {
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) return;

            const data = await response.json();

            if (data.messages.length > knownMessageCount) {
                const newMessages = data.messages.slice(knownMessageCount);
                newMessages.forEach(renderMessage);
                if (newMessages.some((message) => message.role === 'staff')) {
                    setChatStatus(config.labels.statusReplied, 'replied');
                }
                window.venueSound?.play('incoming');
                knownMessageCount = data.messages.length;
                scrollToBottom();
            }

            if (data.status !== 'handed_over') {
                clearHandedOverBanner();
            }
        } catch {
            // transient network hiccup — try again on the next tick
        }
    }

    function renderMessage(message) {
        if (message.role === 'guest') {
            messagesEl.appendChild(bubble('guest', message.content));
        } else if (message.role === 'assistant') {
            if (message.content) {
                messagesEl.appendChild(bubble('assistant', message.content));
            }
            renderUiPayload(message.ui_payload);
            const chips = actionChips(message.suggested_actions);
            if (chips) messagesEl.appendChild(chips);
        } else if (message.role === 'staff') {
            messagesEl.appendChild(card([staffBubble(message.content)]));
            setChatStatus(config.labels.statusReplied, 'replied');
        } else if (message.role === 'system') {
            messagesEl.appendChild(bubble('assistant', message.content));
        }
    }

    function quickMenuCard() {
        const template = document.querySelector('[data-quick-menu-items]');
        if (!template) return null;

        const el = document.createElement('div');
        el.className = 'chat-card';

        const heading = document.createElement('p');
        heading.className = 'chat-card-tag border-b border-[var(--line)] px-3 py-2';
        heading.textContent = config.labels.menuHeading;
        el.appendChild(heading);

        const list = document.createElement('div');
        list.className = 'divide-y divide-[var(--line)]';

        template.content.querySelectorAll('[data-quick-message]').forEach((source) => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'flex w-full items-center justify-between px-3 py-2.5 text-left text-sm hover:bg-[#ffd84a55]';
            item.innerHTML = `<span>${source.textContent}</span><span class="opacity-40">→</span>`;
            item.addEventListener('click', () => sendMessage(source.dataset.quickMessage));
            list.appendChild(item);
        });

        el.appendChild(list);
        return el;
    }

    function staffBubble(text) {
        const el = document.createElement('div');
        el.className = 'chat-bubble is-host';
        el.style.borderLeft = '3px solid #5fb8e0';
        el.innerHTML = `<p class="chat-card-tag mb-0.5" style="color:#2f7fa3">${config.labels.team}</p>`;
        el.append(document.createTextNode(text));
        return el;
    }

    const sceneNames = { home: 'reception', lobby: 'reception', info: 'reception', rooms: 'rooms', room: 'room_detail', facilities: 'facilities', facility: 'facility_detail', reservation: 'reservation', team: 'handover' };

    /**
     * Where the guest is in the venue, so the coordinator can answer for "this
     * room" or the booking on screen. Never includes name or contact data.
     */
    function uiContext() {
        const pageScene = document.querySelector('[data-lobby]')?.dataset.scene || 'lobby';
        const context = { scene: sceneNames[pageScene] || 'reception' };

        const lastSegment = decodeURIComponent(window.location.pathname.split('/').pop());
        if (pageScene === 'room') context.selected_room = lastSegment;
        if (pageScene === 'facility') context.selected_facility = Number(lastSegment);

        if (pageScene === 'reservation') {
            try {
                const draft = JSON.parse(sessionStorage.getItem(`reservation_draft_${root.dataset.venueSlug}`) || 'null');
                const { start_date, end_date, participants, rooms, room_slug } = draft?.values || {};
                context.reservation = { start_date, end_date, participants, rooms, room_slug };
                if (room_slug) context.selected_room = room_slug;
            } catch { /* no draft to share */ }
        }

        return context;
    }

    function actionChips(actions) {
        if (!actions || !actions.length) return null;

        const roomUrl = (slug) => config.roomUrlTemplate.replace('__SLUG__', encodeURIComponent(slug));
        const reservationUrl = (slug) => config.reservationUrlTemplate.replace('__SLUG__', encodeURIComponent(slug));
        const targets = {
            view_room: [config.labels.viewDetails, (action) => roomUrl(action.room)],
            reserve: [config.labels.bookNow, (action) => reservationUrl(action.room)],
            team: [config.labels.team, () => config.teamUrl],
        };

        const wrap = document.createElement('div');
        wrap.className = 'flex flex-wrap gap-2 pl-10';

        actions.forEach((action) => {
            const target = targets[action.action];
            if (!target) return;
            const link = document.createElement('a');
            link.href = target[1](action);
            link.className = 'chat-chip';
            link.textContent = target[0];
            link.addEventListener('click', (event) => {
                closePanel({ confirmDraft: false, restoreFocus: false });

                // Another page: move on the way the agenda does. A panel on
                // this page is just a hash change, so leave it to the browser.
                if (link.pathname !== window.location.pathname && window.venueStage) {
                    event.preventDefault();
                    window.venueStage.leave(link.href);
                }
            });
            wrap.appendChild(link);
        });

        return wrap.childElementCount ? wrap : null;
    }

    function errorCard(text, err) {
        const el = document.createElement('div');
        el.className = 'chat-card p-3 text-sm';
        el.style.borderLeft = '3px solid var(--danger)';

        const message = document.createElement('p');
        message.textContent = err.status === 429 ? config.labels.slow : config.labels.error;

        const actions = document.createElement('div');
        actions.className = 'mt-3 flex flex-wrap gap-2';

        const retry = document.createElement('button');
        retry.type = 'button';
        retry.className = 'chat-mini-button is-primary';
        retry.textContent = config.labels.retry;
        retry.addEventListener('click', () => {
            el.remove();
            sendMessage(text, { retry: true });
        });

        const team = document.createElement('a');
        team.href = config.teamUrl;
        team.className = 'chat-mini-button';
        team.textContent = config.labels.team;
        team.addEventListener('click', (event) => {
            closePanel({ confirmDraft: false, restoreFocus: false });

            if (window.venueStage) {
                event.preventDefault();
                window.venueStage.leave(team.href);
            }
        });

        actions.append(retry, team);
        el.append(message, actions);
        return el;
    }

    async function sendMessage(text, { retry = false } = {}) {
        if (!text.trim() || handedOver || busy || !ready) return;

        const guestToken = localStorage.getItem(config.storageKey);
        if (!guestToken) return;

        openPanel();
        if (!retry) messagesEl.appendChild(bubble('guest', text));
        window.venueSound?.play('sent');
        scrollToBottom();
        setBusy(true);

        try {
            const data = await api(config.messageUrl, { guest_token: guestToken, message: text, ...uiContext() });
            renderMessage(data.message);
            setChatStatus(config.labels.statusSent, 'sent');
            window.venueSound?.play('incoming');
            knownMessageCount += 2; // the guest message just sent + the reply just rendered
            if (data.status === 'handed_over') {
                showHandedOverBanner();
            }
        } catch (err) {
            messagesEl.appendChild(errorCard(text, err));
        } finally {
            setBusy(false);
            scrollToBottom();
        }
    }

    async function boot() {
        let guestToken = localStorage.getItem(config.storageKey);

        if (!guestToken) {
            const data = await api(config.startUrl, { locale: config.locale });
            guestToken = data.guest_token;
            localStorage.setItem(config.storageKey, guestToken);
            messagesEl.appendChild(bubble('assistant', config.labels.intro));
            const menu = quickMenuCard();
            if (menu) messagesEl.appendChild(card([menu]));
            return;
        }

        try {
            const response = await fetch(`${config.historyUrl}?guest_token=${encodeURIComponent(guestToken)}`, {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) throw new Error('history_unavailable');

            const data = await response.json();

            if (!data.messages.length) {
                messagesEl.appendChild(bubble('assistant', config.labels.intro));
                const menu = quickMenuCard();
                if (menu) messagesEl.appendChild(card([menu]));
            } else {
                data.messages.forEach(renderMessage);
            }
            knownMessageCount = data.messages.length;

            if (data.status === 'handed_over') {
                showHandedOverBanner();
            }
            if (data.messages.some((message) => message.role === 'staff')) {
                setChatStatus(config.labels.statusReplied, 'replied');
            }
        } catch {
            localStorage.removeItem(config.storageKey);
            return boot();
        }

        scrollToBottom();
    }

    formEl.addEventListener('submit', (event) => {
        event.preventDefault();
        if (!ready || busy || handedOver || !inputEl.value.trim()) return;
        const text = inputEl.value;
        inputEl.value = '';
        sendMessage(text);
    });

    document.querySelectorAll('[data-ask-ai-button]').forEach((button) => {
        button.addEventListener('click', () => {
            const roomCard = button.closest('[data-room-card]');
            const roomName = roomCard?.dataset.roomName || '';
            openPanel();
            sendMessage(`Tell me more about the ${roomName}`);
        });
    });

    // Hero quick-start menu — same topics as the in-chat menu, always
    // visible up front so guests see what the AI can do immediately.
    document.querySelectorAll('[data-hero-quick-message]').forEach((button) => {
        button.addEventListener('click', () => {
            openPanel();
            sendMessage(button.dataset.heroQuickMessage);
        });
    });

    // A menu pick in the same scene: the coordinator answers right where the guest stands.
    window.addEventListener('coordinator:ask', (event) => sendMessage(event.detail.message));

    setBusy(true);
    boot().then(() => {
        ready = true;
        scrollToBottom();
    }).catch(() => {
        statusBanner.textContent = root.dataset.labelConnectionError;
        statusBanner.classList.remove('hidden');
        messagesEl.appendChild(bubble('assistant', config.labels.intro));
    }).finally(() => {
        setBusy(false);

        // The guest chose this topic from the menu on the previous scene:
        // let the new scene settle for a beat, then carry on the conversation.
        const topic = window.takePendingCoordinatorTopic?.();
        if (topic && ready) window.setTimeout(() => sendMessage(topic), 650);
    });
}

document.addEventListener('DOMContentLoaded', initCoordinator);

function initRoomGallery() {
    document.querySelectorAll('[data-room-gallery]').forEach((gallery) => {
        const main = gallery.querySelector('[data-room-gallery-main]');
        const thumbs = [...gallery.querySelectorAll('[data-room-thumb]')];
        if (!main) return;

        thumbs.forEach((thumb) => {
            thumb.addEventListener('click', () => {
                main.src = thumb.dataset.src;
                main.alt = thumb.dataset.alt || '';
                thumbs.forEach((other) => other.toggleAttribute('aria-current', other === thumb));
            });
        });
    });
}

document.addEventListener('DOMContentLoaded', initRoomGallery);
