/**
 * /Pulse/JS/commenti.js
 * Auto-inizializza tutte le .cm-section presenti nella pagina.
 * Espone window.PulseCommenti.init(section) per uso dinamico.
 */
(function () {
    'use strict';

    const BACKEND = '/Pulse/backend/GestioneCommenti.php';

    /* ── Utility ───────────────────────────────────── */
    function esc(s) {
        return String(s).replace(/[&<>"']/g, c =>
            ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    async function api(payload) {
        const r = await fetch(BACKEND, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        return r.json();
    }

    function autoResize(ta) {
        ta.style.height = 'auto';
        ta.style.height = Math.min(ta.scrollHeight, 220) + 'px';
    }

    /* ── Render HTML commento ──────────────────────── */
    function renderItem(c, isReply = false) {
        const replyHtml = !isReply ? `
            <div class="cm-replies"></div>
            <div class="cm-reply-form" hidden>
                <div class="cm-reply-row">
                    <textarea class="cm-textarea cm-reply-input"
                        placeholder="Rispondi a @${esc(c.username)}…"
                        rows="1" maxlength="2000"></textarea>
                </div>
                <div class="cm-reply-actions">
                    <button type="button" class="cm-btn-mini cm-reply-cancel">Annulla</button>
                    <button type="button" class="cm-btn-mini accent cm-reply-send" disabled>Rispondi</button>
                </div>
            </div>` : '';

        return `
        <article class="cm-item${isReply ? ' cm-reply' : ''}" data-id="${c.id}" data-parent="${c.parent_id || ''}">
            <a href="/Pulse/utente/${encodeURIComponent(c.username)}" class="cm-avatar-link">
                <img src="${esc(c.avatar)}" alt="" class="cm-avatar cm-avatar-sm">
            </a>
            <div class="cm-body">
                <div class="cm-bubble">
                    <span class="cm-username">@${esc(c.username)}</span>
                    <p class="cm-text">${esc(c.contenuto)}</p>
                </div>
                <div class="cm-meta-row">
                    <button type="button" class="cm-action-btn cm-like-btn${c.i_liked ? ' liked' : ''}" data-id="${c.id}">
                        <i class="bi ${c.i_liked ? 'bi-heart-fill' : 'bi-heart'}"></i>
                        <span class="cm-like-count">${c.likes > 0 ? c.likes : ''}</span>
                    </button>
                    ${!isReply ? `<button type="button" class="cm-action-btn cm-reply-btn" data-id="${c.id}">Rispondi</button>` : ''}
                    ${c.is_mine ? `<button type="button" class="cm-action-btn cm-del-btn danger" data-id="${c.id}">Elimina</button>` : ''}
                    <span class="cm-ago">${esc(c.ago)}</span>
                </div>
                ${replyHtml}
            </div>
        </article>`;
    }

    /* ── Inizializza una singola sezione ───────────── */
    function initSection(section) {
        const tipo = section.dataset.targetTipo;
        const id   = +section.dataset.targetId;
        if (!tipo || !id) return;
        if (section._pulseInit) return; // evita doppia init
        section._pulseInit = true;

        const list      = section.querySelector('.cm-list');
        const mainInput = section.querySelector('.cm-main-input');
        const sendBtn   = section.querySelector('.cm-send-btn');
        const counterEl = section.querySelector('.cm-counter-val');

        /* ── Carica tutti i commenti ── */
        async function loadAll() {
            list.innerHTML = '<div class="cm-loading"><i class="bi bi-arrow-repeat cm-spin"></i></div>';
            const r = await api({ action: 'lista_commenti', tipo, id });
            list.innerHTML = '';
            if (!r.ok) { list.innerHTML = '<p class="cm-empty-msg">Impossibile caricare i commenti.</p>'; return; }

            const top    = r.commenti.filter(c => !c.parent_id);
            const byPar  = r.commenti.filter(c =>  c.parent_id);

            if (!top.length) {
                list.innerHTML = '<p class="cm-empty-msg">Ancora nessun commento.</p>';
                return;
            }

            top.forEach(c => {
                list.insertAdjacentHTML('beforeend', renderItem(c, false));
                const node    = list.lastElementChild;
                const replies = node.querySelector('.cm-replies');
                byPar.filter(r => r.parent_id === c.id)
                     .forEach(r => replies.insertAdjacentHTML('beforeend', renderItem(r, true)));
            });
        }

        /* ── Invia commento top-level ── */
        async function sendMain() {
            const text = mainInput.value.trim();
            if (!text) return;
            sendBtn.disabled = true;

            const r = await api({ action: 'aggiungi_commento', tipo, id, contenuto: text });
            if (r.ok) {
                list.querySelector('.cm-empty-msg')?.remove();
                list.insertAdjacentHTML('beforeend', renderItem(r.commento, false));
                mainInput.value = ''; autoResize(mainInput);
                if (counterEl) counterEl.textContent = '0';
                updateBadge(+1);
            } else { showSectionToast(section, r.error || 'Errore', true); }
            sendBtn.disabled = false;
        }

        /* ── Invia risposta ── */
        async function sendReply(parentNode, parentId) {
            const ta  = parentNode.querySelector('.cm-reply-input');
            const btn = parentNode.querySelector('.cm-reply-send');
            const text = ta.value.trim();
            if (!text) return;
            btn.disabled = true;

            const r = await api({ action: 'aggiungi_commento', tipo, id, contenuto: text, parent_id: parentId });
            if (r.ok) {
                parentNode.querySelector('.cm-replies').insertAdjacentHTML('beforeend', renderItem(r.commento, true));
                ta.value = ''; autoResize(ta);
                parentNode.querySelector('.cm-reply-form').hidden = true;
                updateBadge(+1);
            } else { showSectionToast(section, r.error || 'Errore', true); }
            btn.disabled = false;
        }

        /* ── Elimina commento ── */
        async function delComment(cid, node) {
            if (!confirm('Eliminare questo commento?')) return;
            const r = await api({ action: 'elimina_commento', commento_id: cid });
            if (r.ok) {
                const removed = 1 + node.querySelectorAll('.cm-reply').length;
                node.remove();
                if (!list.querySelector('.cm-item')) {
                    list.innerHTML = '<p class="cm-empty-msg">Ancora nessun commento.</p>';
                }
                updateBadge(-removed);
            } else { showSectionToast(section, r.error || 'Errore', true); }
        }

        /* ── Like su commento ── */
        async function toggleLike(cid, btn) {
            const r = await api({ action: 'toggle_like', tipo: 'commento', id: cid });
            if (r.ok) {
                btn.classList.toggle('liked', r.liked);
                btn.querySelector('i').className = `bi ${r.liked ? 'bi-heart-fill' : 'bi-heart'}`;
                btn.querySelector('.cm-like-count').textContent = r.count > 0 ? r.count : '';
            }
        }

        /* ── Aggiorna badge esterno ── */
        function updateBadge(delta) {
            const badge = document.querySelector(`[data-comm-badge="${tipo}-${id}"]`);
            if (!badge) return;
            const cur = parseInt(badge.textContent, 10) || 0;
            badge.textContent = Math.max(0, cur + delta);
        }

        /* ── Event listeners ── */
        if (mainInput) {
            mainInput.addEventListener('input', () => {
                autoResize(mainInput);
                if (counterEl) counterEl.textContent = mainInput.value.length;
                if (sendBtn) sendBtn.disabled = mainInput.value.trim().length === 0;
            });
            mainInput.addEventListener('keydown', e => {
                if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); sendMain(); }
            });
        }
        sendBtn?.addEventListener('click', sendMain);

        // Delegation su lista
        list.addEventListener('click', e => {
            const likeBtn = e.target.closest('.cm-like-btn');
            if (likeBtn) { toggleLike(+likeBtn.dataset.id, likeBtn); return; }

            const delBtn = e.target.closest('.cm-del-btn');
            if (delBtn) { delComment(+delBtn.dataset.id, delBtn.closest('.cm-item')); return; }

            const replyBtn = e.target.closest('.cm-reply-btn');
            if (replyBtn) {
                const item = replyBtn.closest('.cm-item');
                const form = item.querySelector('.cm-reply-form');
                form.hidden = !form.hidden;
                if (!form.hidden) { form.querySelector('textarea').focus(); }
                return;
            }

            const cancelBtn = e.target.closest('.cm-reply-cancel');
            if (cancelBtn) {
                cancelBtn.closest('.cm-reply-form').hidden = true;
                return;
            }

            const replyBtn2 = e.target.closest('.cm-reply-send');
            if (replyBtn2) {
                const item = replyBtn2.closest('.cm-item');
                sendReply(item, +item.dataset.id);
                return;
            }
        });

        list.addEventListener('input', e => {
            const ta = e.target.closest('.cm-reply-input');
            if (!ta) return;
            autoResize(ta);
            const btn = ta.closest('.cm-reply-form').querySelector('.cm-reply-send');
            btn.disabled = ta.value.trim().length === 0;
        });

        loadAll();
    }

    /* ── Toast locale alla sezione ────────────────── */
    function showSectionToast(section, msg, isErr = false) {
        let t = section.querySelector('.cm-section-toast');
        if (!t) {
            t = document.createElement('div');
            t.className = 'cm-section-toast';
            section.prepend(t);
        }
        t.textContent = msg;
        t.className = `cm-section-toast show${isErr ? ' err' : ''}`;
        clearTimeout(t._t);
        t._t = setTimeout(() => t.classList.remove('show'), 2600);
    }

    /* ── Bootstrap ─────────────────────────────────── */
    function boot() {
        document.querySelectorAll('.cm-section').forEach(initSection);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    window.PulseCommenti = { init: initSection };
})();