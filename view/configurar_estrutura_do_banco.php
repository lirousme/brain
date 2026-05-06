<?php

declare(strict_types=1);

/** @var array{nodes: list<string>, relationships: list<string>, properties: list<string>, error: ?string} $schema */
$columns = [
    [
        'key' => 'nodes',
        'title' => 'Nodes',
        'placeholder' => 'Ex.: Pessoa, Projeto, Empresa',
        'button' => 'Adicionar node',
        'items' => $schema['nodes'],
    ],
    [
        'key' => 'relationships',
        'title' => 'Relationships',
        'placeholder' => 'Ex.: CONHECE, TRABALHA_EM',
        'button' => 'Adicionar relationship',
        'items' => $schema['relationships'],
    ],
    [
        'key' => 'properties',
        'title' => 'Property keys',
        'placeholder' => 'Ex.: nome, email, criadoEm',
        'button' => 'Adicionar property key',
        'items' => $schema['properties'],
    ],
];
?>
<?php if ($schema['error']): ?>
    <section class="mb-6 rounded-2xl border border-amber-400/30 bg-amber-400/10 p-4 text-amber-100">
        <h2 class="font-semibold">Atenção</h2>
        <p class="mt-1 text-sm leading-6"><?= e($schema['error']) ?></p>
    </section>
<?php endif; ?>

<section class="grid gap-6 lg:grid-cols-3">
    <?php foreach ($columns as $column): ?>
        <article class="flex min-h-[32rem] flex-col rounded-3xl border border-slate-800 bg-slate-900/80 p-5 shadow-xl shadow-slate-950/30">
            <div class="mb-5 flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-semibold text-white"><?= e($column['title']) ?></h2>
                    <p class="mt-1 text-sm text-slate-400">
                        <?= count($column['items']) ?> item(ns) encontrado(s) no banco atual.
                    </p>
                </div>
                <span class="rounded-full bg-slate-800 px-3 py-1 text-xs font-semibold text-cyan-200">
                    <?= e((string) count($column['items'])) ?>
                </span>
            </div>

            <form class="schema-add-form mb-5" data-target="<?= e($column['key']) ?>">
                <label class="mb-2 block text-sm font-medium text-slate-200" for="<?= e($column['key']) ?>-input">
                    Novo <?= e($column['title']) ?> desejado
                </label>
                <div class="flex gap-2">
                    <input
                        id="<?= e($column['key']) ?>-input"
                        name="name"
                        type="text"
                        autocomplete="off"
                        placeholder="<?= e($column['placeholder']) ?>"
                        class="min-w-0 flex-1 rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none transition placeholder:text-slate-500 focus:border-cyan-400 focus:ring-2 focus:ring-cyan-400/30"
                    >
                    <button
                        type="submit"
                        class="rounded-2xl bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300 focus:outline-none focus:ring-2 focus:ring-cyan-300 focus:ring-offset-2 focus:ring-offset-slate-950"
                    >
                        +
                    </button>
                </div>
                <p class="mt-2 text-xs leading-5 text-slate-500">
                    Esta ação adiciona o item somente na lista local abaixo; nenhuma query de criação será executada.
                </p>
            </form>

            <div class="mb-5 rounded-2xl border border-cyan-400/20 bg-cyan-400/5 p-4">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h3 class="text-sm font-semibold uppercase tracking-widest text-cyan-200">Planejados pelo usuário</h3>
                    <button type="button" class="clear-planned text-xs font-medium text-slate-400 hover:text-cyan-200" data-target="<?= e($column['key']) ?>">limpar</button>
                </div>
                <ul class="planned-list space-y-2" data-list="<?= e($column['key']) ?>"></ul>
                <p class="empty-planned text-sm text-slate-500" data-empty="<?= e($column['key']) ?>">Nenhum item planejado ainda.</p>
            </div>

            <div class="flex-1 rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-widest text-slate-300">Existentes no banco</h3>
                <?php if ($column['items']): ?>
                    <ul class="space-y-2">
                        <?php foreach ($column['items'] as $item): ?>
                            <li class="rounded-xl border border-slate-800 bg-slate-900 px-3 py-2 text-sm text-slate-100">
                                <?= e($item) ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="rounded-xl border border-dashed border-slate-800 px-3 py-6 text-center text-sm text-slate-500">
                        Nenhum item encontrado.
                    </p>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const storagePrefix = 'neo4j-schema-planned:';

    const readItems = (key) => JSON.parse(localStorage.getItem(storagePrefix + key) || '[]');
    const writeItems = (key, items) => localStorage.setItem(storagePrefix + key, JSON.stringify(items));

    const render = (key) => {
        const list = document.querySelector(`[data-list="${key}"]`);
        const empty = document.querySelector(`[data-empty="${key}"]`);
        const items = readItems(key);

        list.innerHTML = '';
        empty.classList.toggle('hidden', items.length > 0);

        items.forEach((item, index) => {
            const li = document.createElement('li');
            li.className = 'flex items-center justify-between gap-3 rounded-xl border border-cyan-400/20 bg-slate-950 px-3 py-2 text-sm text-cyan-50';

            const span = document.createElement('span');
            span.textContent = item;

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'text-xs font-semibold text-slate-400 hover:text-rose-300';
            button.textContent = 'remover';
            button.addEventListener('click', () => {
                const nextItems = readItems(key).filter((_, itemIndex) => itemIndex !== index);
                writeItems(key, nextItems);
                render(key);
            });

            li.append(span, button);
            list.appendChild(li);
        });
    };

    document.querySelectorAll('.schema-add-form').forEach((form) => {
        const key = form.dataset.target;
        render(key);

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            const input = form.elements.name;
            const value = input.value.trim();

            if (!value) {
                input.focus();
                return;
            }

            const items = readItems(key);
            if (!items.some((item) => item.toLocaleLowerCase() === value.toLocaleLowerCase())) {
                items.push(value);
                writeItems(key, items);
            }

            input.value = '';
            input.focus();
            render(key);
        });
    });

    document.querySelectorAll('.clear-planned').forEach((button) => {
        button.addEventListener('click', () => {
            writeItems(button.dataset.target, []);
            render(button.dataset.target);
        });
    });
});
</script>
