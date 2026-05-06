<?php

declare(strict_types=1);

/** @var array{nodes: list<string>, relationships: list<string>, properties: list<string>, error: ?string} $schema */
/** @var ?array{type: string, message: string} $flash */
$columns = [
    [
        'key' => 'nodes',
        'title' => 'Labels',
        'placeholder' => 'Ex.: Pessoa, Projeto, Empresa',
        'deleteHint' => 'Se for um label, todos os nodes com este label também serão excluídos.',
    ],
    [
        'key' => 'relationships',
        'title' => 'Relationships',
        'placeholder' => 'Ex.: CONHECE, TRABALHA_EM',
        'deleteHint' => 'Todos os relacionamentos deste tipo serão excluídos.',
    ],
    [
        'key' => 'properties',
        'title' => 'Property keys',
        'placeholder' => 'Ex.: nome, email, criadoEm',
        'deleteHint' => 'A chave será removida dos nodes e relationships que a usam.',
    ],
];
$initialPayload = json_encode(
    [
        'schema' => $schema,
        'columns' => $columns,
        'flash' => $flash,
    ],
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR
);
?>

<div id="schema-flash" class="mb-6" aria-live="polite">
    <?php if ($schema['error']): ?>
        <section class="rounded-2xl border border-amber-400/30 bg-amber-400/10 p-4 text-amber-100">
            <h2 class="font-semibold">Atenção</h2>
            <p class="mt-1 text-sm leading-6"><?= e($schema['error']) ?></p>
        </section>
    <?php elseif ($flash): ?>
        <section class="rounded-2xl border <?= $flash['type'] === 'success' ? 'border-emerald-400/30 bg-emerald-400/10 text-emerald-100' : 'border-rose-400/30 bg-rose-400/10 text-rose-100' ?> p-4">
            <h2 class="font-semibold"><?= $flash['type'] === 'success' ? 'Operação realizada no banco' : 'Não foi possível concluir' ?></h2>
            <p class="mt-1 text-sm leading-6"><?= e($flash['message']) ?></p>
        </section>
    <?php endif; ?>
</div>

<section id="schema-grid" class="grid gap-6 lg:grid-cols-3"></section>

<dialog id="schema-config-modal" class="w-full max-w-lg rounded-3xl border border-slate-700 bg-slate-900 p-0 text-slate-100 shadow-2xl backdrop:bg-slate-950/80">
    <div class="p-6">
        <div class="mb-5 flex items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-cyan-300">Configuração</p>
                <h4 id="schema-modal-item-title" class="mt-2 text-xl font-semibold text-white"></h4>
                <p id="schema-modal-column-title" class="mt-1 text-sm text-slate-400"></p>
            </div>
            <form method="dialog">
                <button class="rounded-full border border-slate-700 px-3 py-1 text-sm text-slate-300 transition hover:border-slate-500 hover:text-white" aria-label="Fechar modal">×</button>
            </form>
        </div>

        <form id="schema-rename-form" method="post" class="space-y-3">
            <input type="hidden" name="schema_action" value="rename">
            <input type="hidden" name="schema_type" id="schema-modal-type">
            <input type="hidden" name="current_name" id="schema-modal-current-name">
            <label class="block text-sm font-medium text-slate-200" for="schema-modal-new-name">
                Alterar nome
            </label>
            <input
                id="schema-modal-new-name"
                name="new_name"
                type="text"
                autocomplete="off"
                class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none transition focus:border-cyan-400 focus:ring-2 focus:ring-cyan-400/30"
            >
            <button
                type="submit"
                class="w-full rounded-2xl bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300 disabled:cursor-wait disabled:opacity-60 focus:outline-none focus:ring-2 focus:ring-cyan-300 focus:ring-offset-2 focus:ring-offset-slate-950"
            >
                Salvar novo nome
            </button>
        </form>

        <div class="my-5 border-t border-slate-800"></div>

        <form id="schema-delete-form" method="post">
            <input type="hidden" name="schema_action" value="delete">
            <input type="hidden" name="schema_type" id="schema-delete-type">
            <input type="hidden" name="current_name" id="schema-delete-current-name">
            <button
                type="submit"
                class="w-full rounded-2xl border border-rose-400/40 bg-rose-400/10 px-4 py-3 text-sm font-semibold text-rose-100 transition hover:border-rose-300 hover:bg-rose-400/20 disabled:cursor-wait disabled:opacity-60 focus:outline-none focus:ring-2 focus:ring-rose-300 focus:ring-offset-2 focus:ring-offset-slate-950"
            >
                Excluir este item
            </button>
            <p id="schema-delete-hint" class="mt-2 text-xs leading-5 text-slate-500"></p>
        </form>
    </div>
</dialog>

<script type="application/json" id="schema-initial-payload"><?= $initialPayload ?></script>
<script>
(() => {
    const payload = JSON.parse(document.getElementById('schema-initial-payload').textContent);
    const state = { schema: payload.schema, columns: payload.columns };
    const grid = document.getElementById('schema-grid');
    const flash = document.getElementById('schema-flash');
    const modal = document.getElementById('schema-config-modal');
    const modalTitle = document.getElementById('schema-modal-item-title');
    const modalColumn = document.getElementById('schema-modal-column-title');
    const modalType = document.getElementById('schema-modal-type');
    const modalCurrentName = document.getElementById('schema-modal-current-name');
    const modalNewName = document.getElementById('schema-modal-new-name');
    const deleteType = document.getElementById('schema-delete-type');
    const deleteCurrentName = document.getElementById('schema-delete-current-name');
    const deleteHint = document.getElementById('schema-delete-hint');

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (character) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        "'": '&#039;',
        '"': '&quot;',
    }[character]));

    const showFlash = (message, type = 'success') => {
        if (!message) {
            flash.innerHTML = '';
            return;
        }

        const classes = type === 'success'
            ? 'border-emerald-400/30 bg-emerald-400/10 text-emerald-100'
            : 'border-rose-400/30 bg-rose-400/10 text-rose-100';
        const title = type === 'success' ? 'Operação realizada no banco' : 'Não foi possível concluir';

        flash.innerHTML = `<section class="rounded-2xl border ${classes} p-4"><h2 class="font-semibold">${title}</h2><p class="mt-1 text-sm leading-6">${escapeHtml(message)}</p></section>`;
    };

    const setFormBusy = (form, busy) => {
        for (const element of form.querySelectorAll('button, input')) {
            element.disabled = busy;
        }
    };

    const submitSchemaForm = async (form) => {
        const formData = new FormData(form);
        const requestUrl = new URL(window.location.href);
        requestUrl.searchParams.set('ajax', '1');
        setFormBusy(form, true);

        try {
            const response = await fetch(requestUrl, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'fetch' },
                body: formData,
            });
            const data = await response.json();

            if (!response.ok || !data.ok) {
                throw new Error(data.flash?.message || 'Não foi possível concluir a operação.');
            }

            state.schema = data.schema;
            renderColumns();
            showFlash(data.flash?.message || 'Operação realizada no banco com sucesso.', 'success');
            form.reset();
            if (modal.open) {
                modal.close();
            }
        } catch (error) {
            showFlash(error.message, 'error');
        } finally {
            setFormBusy(form, false);
        }
    };

    const openModal = (column, item) => {
        modalTitle.textContent = item;
        modalColumn.textContent = column.title;
        modalType.value = column.key;
        modalCurrentName.value = item;
        modalNewName.value = item;
        deleteType.value = column.key;
        deleteCurrentName.value = item;
        deleteHint.textContent = `${column.deleteHint} Esta ação não pode ser desfeita.`;
        modal.showModal();
        modalNewName.focus();
        modalNewName.select();
    };

    const renderColumns = () => {
        grid.innerHTML = state.columns.map((column) => {
            const items = state.schema[column.key] || [];
            const list = items.length
                ? `<ul class="space-y-2">${items.map((item) => `
                    <li class="flex items-center justify-between gap-3 rounded-xl border border-slate-800 bg-slate-900 px-3 py-2 text-sm text-slate-100">
                        <span class="min-w-0 flex-1 truncate">${escapeHtml(item)}</span>
                        <button
                            type="button"
                            class="schema-config-button inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-slate-700 text-slate-300 transition hover:border-cyan-400 hover:bg-cyan-400/10 hover:text-cyan-200 focus:outline-none focus:ring-2 focus:ring-cyan-300 focus:ring-offset-2 focus:ring-offset-slate-950"
                            aria-label="Configurar ${escapeHtml(item)}"
                            data-schema-type="${escapeHtml(column.key)}"
                            data-schema-name="${escapeHtml(item)}"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M12 15.5A3.5 3.5 0 1 0 12 8a3.5 3.5 0 0 0 0 7.5Z" />
                                <path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.7 1.7 0 0 0-1.88-.34 1.7 1.7 0 0 0-1.03 1.56V21a2 2 0 1 1-4 0v-.09a1.7 1.7 0 0 0-1.03-1.56 1.7 1.7 0 0 0-1.88.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-1.56-1.03H3a2 2 0 1 1 0-4h.09A1.7 1.7 0 0 0 4.6 8.94a1.7 1.7 0 0 0-.34-1.88l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.7 1.7 0 0 0 1.88.34H9A1.7 1.7 0 0 0 10 3.09V3a2 2 0 1 1 4 0v.09a1.7 1.7 0 0 0 1.03 1.56 1.7 1.7 0 0 0 1.88-.34l.06-.06A2 2 0 1 1 19.8 7l-.06.06a1.7 1.7 0 0 0-.34 1.88V9a1.7 1.7 0 0 0 1.56 1H21a2 2 0 1 1 0 4h-.09A1.7 1.7 0 0 0 19.4 15Z" />
                            </svg>
                        </button>
                    </li>`).join('')}</ul>`
                : '<p class="rounded-xl border border-dashed border-slate-800 px-3 py-6 text-center text-sm text-slate-500">Nenhum item encontrado.</p>';

            return `
                <article class="flex min-h-[32rem] flex-col rounded-3xl border border-slate-800 bg-slate-900/80 p-5 shadow-xl shadow-slate-950/30">
                    <div class="mb-5 flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-2xl font-semibold text-white">${escapeHtml(column.title)}</h2>
                            <p class="mt-1 text-sm text-slate-400">${items.length} item(ns) encontrado(s) no banco atual.</p>
                        </div>
                        <span class="rounded-full bg-slate-800 px-3 py-1 text-xs font-semibold text-cyan-200">${items.length}</span>
                    </div>

                    <form method="post" class="schema-create-form mb-5">
                        <input type="hidden" name="schema_action" value="create">
                        <input type="hidden" name="schema_type" value="${escapeHtml(column.key)}">
                        <label class="mb-2 block text-sm font-medium text-slate-200" for="${escapeHtml(column.key)}-input">Novo ${escapeHtml(column.title)}</label>
                        <div class="flex gap-2">
                            <input
                                id="${escapeHtml(column.key)}-input"
                                name="name"
                                type="text"
                                autocomplete="off"
                                placeholder="${escapeHtml(column.placeholder)}"
                                class="min-w-0 flex-1 rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none transition placeholder:text-slate-500 focus:border-cyan-400 focus:ring-2 focus:ring-cyan-400/30"
                            >
                            <button type="submit" class="rounded-2xl bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300 disabled:cursor-wait disabled:opacity-60 focus:outline-none focus:ring-2 focus:ring-cyan-300 focus:ring-offset-2 focus:ring-offset-slate-950">+</button>
                        </div>
                        <p class="mt-2 text-xs leading-5 text-slate-500">Ao enviar, o item é confirmado no Neo4j e a lista é atualizada sem recarregar a página inteira.</p>
                    </form>

                    <div class="flex-1 rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
                        <h3 class="mb-3 text-sm font-semibold uppercase tracking-widest text-slate-300">Existentes no banco</h3>
                        ${list}
                    </div>
                </article>`;
        }).join('');
    };

    grid.addEventListener('submit', (event) => {
        const form = event.target.closest('form');
        if (!form) {
            return;
        }

        event.preventDefault();
        submitSchemaForm(form);
    });

    grid.addEventListener('click', (event) => {
        const button = event.target.closest('.schema-config-button');
        if (!button) {
            return;
        }

        const column = state.columns.find((candidate) => candidate.key === button.dataset.schemaType);
        if (column) {
            openModal(column, button.dataset.schemaName);
        }
    });

    document.getElementById('schema-rename-form').addEventListener('submit', (event) => {
        event.preventDefault();
        submitSchemaForm(event.currentTarget);
    });

    document.getElementById('schema-delete-form').addEventListener('submit', (event) => {
        event.preventDefault();
        if (confirm(`Excluir ${deleteCurrentName.value} do banco? ${deleteHint.textContent}`)) {
            submitSchemaForm(event.currentTarget);
        }
    });

    renderColumns();
})();
</script>
