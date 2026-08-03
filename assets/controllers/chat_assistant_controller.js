import { Controller } from '@hotwired/stimulus';

/**
 * On-page ElevenLabs ConvAI chat (eduacademy-style custom WebSocket client).
 */
export default class extends Controller {
    static values = {
        signedUrlEndpoint: { type: String, default: '/api/chat/signed-url' },
        hero: { type: Boolean, default: false },
        storageKey: { type: String, default: 'kiddo_chat_history' },
    };

    static targets = [
        'panel',
        'messages',
        'input',
        'form',
        'status',
        'statusDot',
        'toggle',
        'loginHint',
        'emptyState',
    ];

    connect() {
        this.ws = null;
        this.messages = [];
        this.currentAgentMessage = '';
        this.chatToken = null;
        this.dynamicVariables = {};
        this.signedUrl = null;
        this.configured = false;
        this.isGuest = false;
        this.initiated = false;
        this.loadHistory();
        this.renderMessages();
        this.updateStatus('idle');

        if (this.heroValue) {
            this.bootstrapSession();
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
        if (!this.panelTarget.classList.contains('hidden') && !this.ws) {
            this.bootstrapSession();
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

    async bootstrapSession() {
        this.updateStatus('connecting');
        try {
            const response = await fetch(this.signedUrlEndpointValue, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
                body: '{}',
            });

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
                this.pushAgent(
                    'Asystent jest skonfigurowany po stronie Kiddo (token OK), ale brakuje kluczy ElevenLabs. Możesz już wołać narzędzia przez /api/v1/tools z tokenem czatu.'
                );
                return;
            }

            await this.connectSocket(this.signedUrl);
        } catch (error) {
            console.error(error);
            this.updateStatus('error');
            this.pushAgent('Nie udało się połączyć z asystentem. Spróbuj ponownie za chwilę.');
        }
    }

    connectSocket(url) {
        return new Promise((resolve, reject) => {
            this.closeSocket();
            this.ws = new WebSocket(url);
            this.ws.onopen = () => {
                this.initiated = false;
                this.sendInit();
                this.sendIdentityContext();
                this.sendContextualUpdate();
                this.updateStatus('connected');
                resolve();
            };
            this.ws.onmessage = (event) => this.onSocketMessage(event);
            this.ws.onerror = (error) => {
                this.updateStatus('error');
                reject(error);
            };
            this.ws.onclose = () => {
                this.updateStatus('disconnected');
                this.ws = null;
                this.initiated = false;
            };
        });
    }

    sendInit() {
        if (!this.ws || this.ws.readyState !== WebSocket.OPEN || this.initiated) {
            return;
        }
        this.ws.send(
            JSON.stringify({
                type: 'conversation_initiation_client_data',
                conversation_config_override: {
                    conversation: {
                        text_only: true,
                    },
                },
                dynamic_variables: this.dynamicVariables,
            })
        );
        this.initiated = true;
    }

    sendIdentityContext() {
        if (!this.ws || this.ws.readyState !== WebSocket.OPEN) {
            return;
        }
        const isGuest =
            this.isGuest || this.dynamicVariables.kiddo_is_guest === 'true';
        if (isGuest) {
            this.ws.send(
                JSON.stringify({
                    type: 'contextual_update',
                    text:
                        'Gość (niezalogowany) w Kiddo.\n' +
                        'Możesz od razu pokazać ofertę: user.list_upcoming_lessons i user.get_lesson.\n' +
                        'Nie wywołuj user.me / list_children / rezerwacji / admin.* — zamiast tego poproś o zalogowanie (/login) i odświeżenie czatu.',
                })
            );
            return;
        }
        const name = this.dynamicVariables.kiddo_user_name || '';
        const email = this.dynamicVariables.kiddo_user_email || '';
        const userId = this.dynamicVariables.kiddo_user_id || '';
        const isAdmin = this.dynamicVariables.kiddo_is_admin === 'true';
        if (!userId && !email) {
            return;
        }
        if (isAdmin) {
            this.ws.send(
                JSON.stringify({
                    type: 'contextual_update',
                    text:
                        'Zalogowany administrator w Kiddo:\n' +
                        `- imię: ${name || '(brak)'}\n` +
                        `- e-mail: ${email || '(brak)'}\n` +
                        `- user_id: ${userId || '(brak)'}\n` +
                        'Możesz używać tooli admin.* (harmonogram, rezerwacje, płatności, użytkownicy, skrzynka) oraz user.* gdy potrzeba.\n' +
                        'Mutacje admin zawsze z confirm=true po wyraźnej zgodzie.',
                })
            );
            return;
        }
        this.ws.send(
            JSON.stringify({
                type: 'contextual_update',
                text:
                    'Zalogowany rodzic w Kiddo:\n' +
                    `- imię: ${name || '(brak)'}\n` +
                    `- e-mail: ${email || '(brak)'}\n` +
                    `- user_id: ${userId || '(brak)'}\n` +
                    'Na starcie wywołaj tool user.me i user.list_children. Nie pytaj ponownie o imię/e-mail, jeśli są już znane.\n' +
                    'Nie używaj tooli admin.* — wymagają ROLE_ADMIN.',
            })
        );
    }

    sendContextualUpdate() {
        if (!this.ws || this.ws.readyState !== WebSocket.OPEN || this.messages.length === 0) {
            return;
        }
        const contextText = this.messages
            .slice(-12)
            .map((m) => (m.role === 'user' ? 'User: ' : 'Assistant: ') + m.text)
            .join('\n');
        this.ws.send(
            JSON.stringify({
                type: 'contextual_update',
                text: 'Previous conversation context:\n' + contextText,
            })
        );
    }

    onSocketMessage(event) {
        let data;
        try {
            data = JSON.parse(event.data);
        } catch (e) {
            console.error('Failed to parse chat message', e);
            return;
        }

        if (data.type === 'agent_response') {
            const content =
                data.agent_response_event?.text || data.content || data.text || data.message;
            if (content) {
                this.pushAgent(content);
            }
            return;
        }

        if (data.type === 'agent_chat_response_part') {
            const part = data.text_response_part;
            if (!part) {
                return;
            }
            if (part.type === 'start') {
                this.currentAgentMessage = '';
            } else if (part.type === 'delta' && part.text) {
                this.currentAgentMessage += part.text;
                this.renderStreaming();
            } else if (part.type === 'stop') {
                if (this.currentAgentMessage) {
                    this.pushAgent(this.currentAgentMessage);
                    this.currentAgentMessage = '';
                }
            }
            return;
        }

        if (data.type === 'error') {
            console.error('ElevenLabs error', data);
            this.pushAgent('Wystąpił błąd asystenta.');
        }
    }

    async send(event) {
        event.preventDefault();
        const text = (this.inputTarget.value || '').trim();
        if (!text) {
            return;
        }

        if (!this.ws || this.ws.readyState !== WebSocket.OPEN) {
            await this.bootstrapSession();
        }
        if (!this.configured || !this.ws || this.ws.readyState !== WebSocket.OPEN) {
            this.pushUser(text);
            this.inputTarget.value = '';
            if (this.chatToken) {
                this.pushAgent(
                    'Brak połączenia WebSocket z ElevenLabs. Token czatu jest gotowy — skonfiguruj ELEVENLABS_* w .env.'
                );
            }
            return;
        }

        this.pushUser(text);
        this.inputTarget.value = '';
        this.sendInit();
        this.ws.send(
            JSON.stringify({
                type: 'user_message',
                text,
            })
        );
    }

    clear() {
        this.messages = [];
        this.currentAgentMessage = '';
        localStorage.removeItem(this.storageKeyValue);
        this.renderMessages();
        this.closeSocket();
        this.updateStatus('idle');
        if (this.heroValue || (this.hasPanelTarget && !this.panelTarget.classList.contains('hidden'))) {
            this.bootstrapSession();
        }
    }

    closeSocket() {
        if (this.ws) {
            this.ws.close();
            this.ws = null;
        }
        this.initiated = false;
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

        this.messagesTarget.querySelectorAll('[data-chat-message]').forEach((el) => el.remove());

        const appendBubble = (role, text, extraClass = '') => {
            const align = role === 'user' ? 'items-end' : 'items-start';
            const bubble =
                role === 'user'
                    ? 'bg-workshop-red text-white'
                    : 'bg-beige text-workshop-brown border border-muted';
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

        this.messages.forEach((m) => appendBubble(m.role, m.text));
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
        } catch (e) {
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
            this.statusDotTarget.className =
                'h-2 w-2 rounded-full ' + (colors[status] || 'bg-workshop-green');
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
