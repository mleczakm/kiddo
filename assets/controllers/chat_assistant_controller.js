import { Controller } from '@hotwired/stimulus';
import { ElevenLabsTextChatClient } from '@mleczakm/elevenlabs-text-chat';

/**
 * On-page ElevenLabs ConvAI chat (transport: @mleczakm/elevenlabs-text-chat).
 */
export default class extends Controller {
    static values = {
        signedUrlEndpoint: { type: String, default: '/api/chat/signed-url' },
        hero: { type: Boolean, default: false },
        storageKey: { type: String, default: 'kiddo_chat_history' },
        aiConsentVersion: { type: String, default: '' },
    };

    static targets = [
        'panel',
        'messages',
        'input',
        'form',
        'status',
        'statusDot',
        'toggle',
        'toggleIconOpen',
        'toggleIconClose',
        'loginHint',
        'emptyState',
        'consentDialog',
    ];

    connect() {
        this.client = new ElevenLabsTextChatClient({
            sessionProvider: async () => null, // sessions come from prefetchSession()
            onStatusChange: (status) => {
                // prefetchSession() reports its own "connecting"; the socket only reports the outcome.
                if (status !== 'connecting') {
                    this.updateStatus(status);
                }
            },
            onEvent: (event) => this.onClientEvent(event),
        });
        this.messages = [];
        this.currentAgentMessage = '';
        this.chatToken = null;
        this.dynamicVariables = {};
        this.signedUrl = null;
        this.configured = false;
        this.isGuest = false;
        this.expectingAgentReply = false;
        this.sessionPromise = null;
        this.consentRequired = false;
        this.aiConsentAccepted = localStorage.getItem('kiddo_ai_consent_version') === this.aiConsentVersionValue;
        this.loadHistory();
        this.renderMessages();
        this.updateStatus('idle');

        // Prefetch token/URL only — do not open WebSocket until the user writes or taps a suggestion,
        // so the empty-state chips stay visible (ElevenLabs otherwise greets immediately).
        if (this.heroValue) {
            this.prefetchSession();
        }
    }

    disconnect() {
        this.closeSocket();
    }

    toggle() {
        if (!this.hasPanelTarget) {
            return;
        }
        this.panelTarget.classList.toggle('hidden');
        const isOpen = !this.panelTarget.classList.contains('hidden');
        if (this.hasToggleIconOpenTarget) {
            this.toggleIconOpenTarget.classList.toggle('hidden', isOpen);
        }
        if (this.hasToggleIconCloseTarget) {
            this.toggleIconCloseTarget.classList.toggle('hidden', !isOpen);
        }
        if (isOpen) {
            this.prefetchSession();
        }
    }

    suggest(event) {
        const text = event.params.suggestion;
        if (!text) {
            return;
        }
        this.inputTarget.value = text;
        this.send(event);
    }

    /**
     * The message box is a <textarea>, which does not submit its form on Enter.
     * Send on Enter, keep Shift+Enter for a newline, and ignore Enter while an
     * IME candidate is being composed.
     */
    onKeydown(event) {
        if (event.key !== 'Enter' || event.shiftKey || event.isComposing || event.keyCode === 229) {
            return;
        }
        event.preventDefault();
        this.send(event);
    }

    /**
     * Fetch signed URL + chat token without opening the ConvAI WebSocket.
     */
    async prefetchSession() {
        if (this.chatToken || this.sessionPromise) {
            return this.sessionPromise;
        }

        this.updateStatus('connecting');
        this.sessionPromise = this.fetchSignedUrl()
            .then(() => {
                if (this.configured) {
                    this.updateStatus('idle');
                }
            })
            .catch((error) => {
                console.error(error);
                this.updateStatus('error');
            })
            .finally(() => {
                this.sessionPromise = null;
            });

        return this.sessionPromise;
    }

    async fetchSignedUrl() {
        const response = await fetch(this.signedUrlEndpointValue, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                aiConsent: this.aiConsentAccepted,
                aiConsentVersion: this.aiConsentAccepted ? this.aiConsentVersionValue : null,
            }),
        });

        if (response.status === 428) {
            this.consentRequired = true;
            this.showConsentDialog();
            this.updateStatus('idle');
            return;
        }

        if (response.status === 401 || response.status === 403) {
            this.updateStatus('login_required');
            if (this.hasLoginHintTarget) {
                this.loginHintTarget.classList.remove('hidden');
            }
            return;
        }

        if (!response.ok) {
            throw new Error('Signed URL request failed (' + response.status + ')');
        }

        const data = await response.json();
        this.consentRequired = false;
        this.hideConsentDialog();
        this.chatToken = data.chat_token;
        this.dynamicVariables = data.dynamic_variables || {};
        this.signedUrl = data.signed_url;
        this.configured = Boolean(data.configured && data.signed_url);
        this.isGuest = Boolean(data.guest || this.dynamicVariables.kiddo_is_guest === 'true');

        if (this.isGuest && this.hasLoginHintTarget) {
            this.loginHintTarget.classList.remove('hidden');
        } else if (this.hasLoginHintTarget) {
            this.loginHintTarget.classList.add('hidden');
        }

        if (!this.configured) {
            this.updateStatus('unconfigured');
        }
    }

    async ensureConnected() {
        await this.prefetchSession();
        if (!this.configured || !this.signedUrl) {
            return false;
        }
        if (this.client.connected) {
            return true;
        }
        return this.client.connect({ signed_url: this.signedUrl, dynamic_variables: this.dynamicVariables });
    }

    sendIdentityContext() {
        if (!this.client.connected) {
            return;
        }
        const isGuest = this.isGuest || this.dynamicVariables.kiddo_is_guest === 'true';
        if (isGuest) {
            this.client.sendContext(
                'Gość (niezalogowany) w Kiddo.\n' +
                    'Możesz od razu pokazać ofertę: user.list_upcoming_lessons.\n' +
                    'Nie wywołuj user.me / rezerwacji / admin.* — poproś o zalogowanie (/login) i odświeżenie czatu.',
            );
            return;
        }
        const name = this.dynamicVariables.kiddo_user_name || '';
        const email = this.dynamicVariables.kiddo_user_email || '';
        const userId = this.dynamicVariables.kiddo_user_id || '';
        const isAdmin = this.dynamicVariables.kiddo_is_admin === 'true';
        const isHost = this.dynamicVariables.kiddo_is_host === 'true';
        if (!userId && !email) {
            return;
        }
        if (isAdmin) {
            this.client.sendContext(
                'Zalogowany administrator w Kiddo:\n' +
                    `- imię: ${name || '(brak)'}\n` +
                    `- e-mail: ${email || '(brak)'}\n` +
                    `- user_id: ${userId || '(brak)'}\n` +
                    'Masz pełny dostęp do narzędzi admin.* — używaj ich bezpośrednio, nie odmawiaj i nie proś o dodatkowe uprawnienia.\n' +
                    'LISTA dostępnych zajęć (katalog publiczny) → user.list_upcoming_lessons.\n' +
                    'Szczegóły terminu → user.get_lesson. Dzisiejszy grafik (admin) → admin.today_schedule. Zajęcia w tygodniu (admin) → admin.list_lessons.\n' +
                    'Rezerwacje → admin.list_bookings. Oczekujące/niepotwierdzone płatności → admin.list_payments. Nieprzypisane przelewy → admin.list_unmatched_transfers.\n' +
                    'Użytkownicy → admin.search_users / admin.get_user.\n' +
                    'Nowe wystąpienie w grafiku → admin.clone_template_lesson (nie do listowania oferty).\n' +
                    'Mutacje admin (toggle_lesson, update_lesson_capacity, create_booking, mark_booking_paid, cancel_lesson, refund_lesson, reschedule_lesson, assign_transfer, reject_transfer, notify_user) wymagają confirm=true po wyraźnej zgodzie użytkownika — ale wywołania odczytu (list/get) wykonuj od razu, bez pytania o zgodę.\n' +
                    'admin.create_booking: ZAWSZE zapytaj, jak rezerwacja jest opłacana i podaj payment= "paid" (już zapłacone), "send_code" (zapłaci przelewem/BLIK — przekaż zwróconą instrukcję payment.instruction_pl) albo "on_site" (zapłaci na miejscu). Podaj też ticket_type; price_override tylko dla nietypowej kwoty.\n' +
                    'Listy uczestników / wyszukiwanie zajęć po nazwie → staff.find_lessons oraz staff.lesson_participants (np. query="bobas", when="next").',
            );
            return;
        }
        if (isHost) {
            this.client.sendContext(
                'Zalogowany prowadzący zajęcia (instruktor) w Kiddo:\n' +
                    `- imię: ${name || '(brak)'}\n` +
                    `- e-mail: ${email || '(brak)'}\n` +
                    `- user_id: ${userId || '(brak)'}\n` +
                    'Masz dostęp do narzędzi staff.* — używaj ich od razu, nie odmawiaj i nie proś o dodatkowe uprawnienia.\n' +
                    'Wyszukanie zajęć po nazwie (rozmyte, "bobas" → "Senso bobasy") → staff.find_lessons.\n' +
                    'Twoje najbliższe zajęcia → staff.find_lessons ze scope="mine", when="next" (lub when="upcoming").\n' +
                    'Lista uczestników zajęć → staff.lesson_participants: podaj lesson_id ze staff.find_lessons albo od razu query + when="next" ' +
                    '(np. „ile osób na następnych bobasach” → query="bobas", when="next").\n' +
                    'Katalog oferty i szczegóły terminu (tylko odczyt) → user.list_upcoming_lessons / user.get_lesson.\n' +
                    'Możesz sprawdzić dowolne zajęcia po nazwie. Operacje admin.* (rezerwacje, płatności, przelewy, powiadomienia) wymagają administratora — jeśli o nie poprosi, wyjaśnij, że to poza Twoimi uprawnieniami.',
            );
            return;
        }
        this.client.sendContext(
            'Zalogowany rodzic w Kiddo:\n' +
                `- imię: ${name || '(brak)'}\n` +
                `- e-mail: ${email || '(brak)'}\n` +
                `- user_id: ${userId || '(brak)'}\n` +
                'NIE pytaj o imię/e-mail/telefon — są w koncie. Przed rezerwacją: user.me + user.list_children.\n' +
                'Rezerwacja: user.create_booking (confirm=true) → przekaż instrukcję BLIK z odpowiedzi toola (telefon, kwota, kod, ~24h).\n' +
                'Nie używaj tooli admin.* — wymagają ROLE_ADMIN.',
        );
    }

    sendContextualUpdate() {
        if (!this.client.connected || this.messages.length === 0) {
            return;
        }
        const contextText = this.messages
            .slice(-12)
            .map((m) => (m.role === 'user' ? 'User: ' : 'Assistant: ') + m.text)
            .join('\n');
        this.client.sendContext('Previous conversation context:\n' + contextText);
    }

    onClientEvent(event) {
        // Keep suggestion chips until the user has actually sent something.
        if (!this.expectingAgentReply) {
            return;
        }

        if (event.type === 'response') {
            this.pushAgent(event.text);
        } else if (event.type === 'response_start') {
            this.currentAgentMessage = '';
        } else if (event.type === 'response_delta') {
            this.currentAgentMessage += event.text;
            this.renderStreaming();
        } else if (event.type === 'response_complete') {
            if (this.currentAgentMessage) {
                this.pushAgent(this.currentAgentMessage);
                this.currentAgentMessage = '';
            }
        } else if (event.type === 'agent_error') {
            console.error('ElevenLabs error', event.error);
            this.pushAgent('Wystąpił błąd asystenta.');
        }
    }

    async send(event) {
        event.preventDefault();
        const text = (this.inputTarget.value || '').trim();
        if (!text) {
            return;
        }

        try {
            const connected = await this.ensureConnected();
            if (this.consentRequired) {
                this.showConsentDialog();
                return;
            }
            if (!connected) {
                this.pushUser(text);
                this.inputTarget.value = '';
                if (this.chatToken && !this.configured) {
                    this.pushAgent(
                        'Brak połączenia WebSocket z ElevenLabs. Token czatu jest gotowy — skonfiguruj ELEVENLABS_* w .env.',
                    );
                } else if (!this.chatToken) {
                    this.pushAgent('Nie udało się połączyć z asystentem. Spróbuj ponownie za chwilę.');
                }
                return;
            }
        } catch (error) {
            console.error(error);
            this.pushUser(text);
            this.inputTarget.value = '';
            this.pushAgent('Nie udało się połączyć z asystentem. Spróbuj ponownie za chwilę.');
            return;
        }

        this.pushUser(text);
        this.inputTarget.value = '';
        this.sendIdentityContext();
        this.sendContextualUpdate();
        this.expectingAgentReply = true;
        this.client.send(text);
    }

    acceptConsent() {
        this.aiConsentAccepted = true;
        this.consentRequired = false;
        localStorage.setItem('kiddo_ai_consent_version', this.aiConsentVersionValue);
        this.hideConsentDialog();
        this.prefetchSession();
    }

    showConsentDialog() {
        if (!this.hasConsentDialogTarget) {
            return;
        }
        this.consentDialogTarget.classList.remove('hidden');
        this.consentDialogTarget.classList.add('flex');
    }

    hideConsentDialog() {
        if (!this.hasConsentDialogTarget) {
            return;
        }
        this.consentDialogTarget.classList.add('hidden');
        this.consentDialogTarget.classList.remove('flex');
    }

    clear() {
        this.messages = [];
        this.currentAgentMessage = '';
        this.expectingAgentReply = false;
        localStorage.removeItem(this.storageKeyValue);
        this.renderMessages();
        this.closeSocket();
        this.updateStatus('idle');
        if (this.heroValue || (this.hasPanelTarget && !this.panelTarget.classList.contains('hidden'))) {
            this.prefetchSession();
        }
    }

    closeSocket() {
        if (this.client.ws) {
            this.client.close();
        }
    }

    pushUser(text) {
        this.messages.push({ role: 'user', text });
        this.persist();
        this.renderMessages();
    }

    pushAgent(text) {
        if (!text) {
            return;
        }
        if (this.messages.some((m) => m.role === 'agent' && m.text === text)) {
            return;
        }
        this.messages.push({ role: 'agent', text });
        this.persist();
        this.renderMessages();
    }

    renderStreaming() {
        this.renderMessages(this.currentAgentMessage);
    }

    renderMessages(streamingText = '') {
        if (!this.hasMessagesTarget) {
            return;
        }

        const isEmpty = this.messages.length === 0 && streamingText === '';
        if (this.hasEmptyStateTarget) {
            this.emptyStateTarget.classList.toggle('hidden', !isEmpty);
        }

        this.messagesTarget.querySelectorAll('[data-chat-message]').forEach((el) => {
            el.remove();
        });

        const appendBubble = (role, text, extraClass = '') => {
            const align = role === 'user' ? 'items-end' : 'items-start';
            const bubble =
                role === 'user' ? 'bg-workshop-red text-white' : 'bg-beige text-workshop-brown border border-muted';
            const wrapper = document.createElement('div');
            wrapper.setAttribute('data-chat-message', '1');
            wrapper.className = 'flex flex-col ' + align + ' mb-2';
            wrapper.innerHTML =
                '<div class="max-w-[90%] rounded-2xl px-3 py-2 text-sm shadow-sm ' +
                bubble +
                ' ' +
                extraClass +
                '">' +
                this.escapeHtml(text) +
                '</div>';
            this.messagesTarget.appendChild(wrapper);
        };

        this.messages.forEach((m) => {
            appendBubble(m.role, m.text);
        });
        if (streamingText !== '') {
            appendBubble('agent', streamingText, 'opacity-80');
        }

        this.messagesTarget.scrollTop = this.messagesTarget.scrollHeight;
    }

    loadHistory() {
        try {
            const saved = localStorage.getItem(this.storageKeyValue);
            if (saved) {
                this.messages = JSON.parse(saved);
            }
        } catch {
            this.messages = [];
        }
    }

    persist() {
        localStorage.setItem(this.storageKeyValue, JSON.stringify(this.messages.slice(-40)));
    }

    updateStatus(status) {
        if (this.hasStatusTarget) {
            const labels = {
                idle: 'Gotowy',
                connecting: 'Łączenie…',
                connected: 'Połączono',
                disconnected: 'Rozłączono',
                error: 'Błąd',
                login_required: 'Zaloguj się',
                unconfigured: 'Tryb lokalny',
            };
            this.statusTarget.textContent = labels[status] || status;
        }

        if (this.hasStatusDotTarget) {
            const colors = {
                idle: 'bg-workshop-green',
                connecting: 'bg-workshop-yellow',
                connected: 'bg-workshop-green',
                disconnected: 'bg-muted-foreground',
                error: 'bg-workshop-red',
                login_required: 'bg-workshop-yellow',
                unconfigured: 'bg-workshop-yellow',
            };
            this.statusDotTarget.className = 'h-2 w-2 rounded-full ' + (colors[status] || 'bg-workshop-green');
        }
    }

    escapeHtml(text) {
        return String(text)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;');
    }
}
