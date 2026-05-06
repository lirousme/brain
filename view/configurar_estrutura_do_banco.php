<?php

declare(strict_types=1);

/** @var array{nodes: list<string>, relationships: list<string>, properties: list<string>, error: ?string} $schema */
/** @var ?array{type: string, message: string} $flash */
$columns = [
    [
        'key' => 'nodes',
        'title' => 'Labels',
        'placeholder' => 'Ex.: Pessoa, Projeto, Empresa',
        'button' => '+',
        'items' => $schema['nodes'],
    ],
    [
        'key' => 'relationships',
        'title' => 'Relationships',
        'placeholder' => 'Ex.: CONHECE, TRABALHA_EM',
        'button' => '+',
        'items' => $schema['relationships'],
    ],
    [
        'key' => 'properties',
        'title' => 'Property keys',
        'placeholder' => 'Ex.: nome, email, criadoEm',
        'button' => '+',
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

<?php if ($flash): ?>
    <section class="mb-6 rounded-2xl border <?= $flash['type'] === 'success' ? 'border-emerald-400/30 bg-emerald-400/10 text-emerald-100' : 'border-rose-400/30 bg-rose-400/10 text-rose-100' ?> p-4">
        <h2 class="font-semibold"><?= $flash['type'] === 'success' ? 'Operação realizada no banco' : 'Não foi possível concluir' ?></h2>
        <p class="mt-1 text-sm leading-6"><?= e($flash['message']) ?></p>
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

            <form method="post" class="mb-5">
                <input type="hidden" name="schema_action" value="create">
                <input type="hidden" name="schema_type" value="<?= e($column['key']) ?>">
                <label class="mb-2 block text-sm font-medium text-slate-200" for="<?= e($column['key']) ?>-input">
                    Novo <?= e($column['title']) ?>
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
                        <?= e($column['button']) ?>
                    </button>
                </div>
                <p class="mt-2 text-xs leading-5 text-slate-500">
                    Ao enviar, o token de schema é criado no Neo4j sem criar nodes, relationships ou propriedades de exemplo.
                </p>
            </form>

            <div class="flex-1 rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-widest text-slate-300">Existentes no banco</h3>
                <?php if ($column['items']): ?>
                    <ul class="space-y-2">
                        <?php foreach ($column['items'] as $item): ?>
                            <?php
                                $modalId = 'schema-modal-' . md5($column['key'] . ':' . $item);
                                $deleteMessage = json_encode(
                                    'Excluir ' . $item . ' do banco? Se for um label, todos os nodes com este label também serão excluídos. Esta ação não pode ser desfeita.',
                                    JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
                                );
                            ?>
                            <li class="flex items-center justify-between gap-3 rounded-xl border border-slate-800 bg-slate-900 px-3 py-2 text-sm text-slate-100">
                                <span class="min-w-0 flex-1 truncate"><?= e($item) ?></span>
                                <button
                                    type="button"
                                    class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-slate-700 text-slate-300 transition hover:border-cyan-400 hover:bg-cyan-400/10 hover:text-cyan-200 focus:outline-none focus:ring-2 focus:ring-cyan-300 focus:ring-offset-2 focus:ring-offset-slate-950"
                                    aria-label="Configurar <?= e($item) ?>"
                                    onclick="document.getElementById('<?= e($modalId) ?>').showModal()"
                                >
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M12 15.5A3.5 3.5 0 1 0 12 8a3.5 3.5 0 0 0 0 7.5Z" />
                                        <path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.7 1.7 0 0 0-1.88-.34 1.7 1.7 0 0 0-1.03 1.56V21a2 2 0 1 1-4 0v-.09a1.7 1.7 0 0 0-1.03-1.56 1.7 1.7 0 0 0-1.88.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-1.56-1.03H3a2 2 0 1 1 0-4h.09A1.7 1.7 0 0 0 4.6 8.94a1.7 1.7 0 0 0-.34-1.88l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.7 1.7 0 0 0 1.88.34H9A1.7 1.7 0 0 0 10 3.09V3a2 2 0 1 1 4 0v.09a1.7 1.7 0 0 0 1.03 1.56 1.7 1.7 0 0 0 1.88-.34l.06-.06A2 2 0 1 1 19.8 7l-.06.06a1.7 1.7 0 0 0-.34 1.88V9a1.7 1.7 0 0 0 1.56 1H21a2 2 0 1 1 0 4h-.09A1.7 1.7 0 0 0 19.4 15Z" />
                                    </svg>
                                </button>
                                <dialog id="<?= e($modalId) ?>" class="w-full max-w-lg rounded-3xl border border-slate-700 bg-slate-900 p-0 text-slate-100 shadow-2xl backdrop:bg-slate-950/80">
                                    <div class="p-6">
                                        <div class="mb-5 flex items-start justify-between gap-4">
                                            <div>
                                                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-cyan-300">Configuração</p>
                                                <h4 class="mt-2 text-xl font-semibold text-white"><?= e($item) ?></h4>
                                                <p class="mt-1 text-sm text-slate-400"><?= e($column['title']) ?></p>
                                            </div>
                                            <form method="dialog">
                                                <button class="rounded-full border border-slate-700 px-3 py-1 text-sm text-slate-300 transition hover:border-slate-500 hover:text-white" aria-label="Fechar modal">×</button>
                                            </form>
                                        </div>

                                        <form method="post" class="space-y-3">
                                            <input type="hidden" name="schema_action" value="rename">
                                            <input type="hidden" name="schema_type" value="<?= e($column['key']) ?>">
                                            <input type="hidden" name="current_name" value="<?= e($item) ?>">
                                            <label class="block text-sm font-medium text-slate-200" for="<?= e($modalId) ?>-new-name">
                                                Alterar nome
                                            </label>
                                            <input
                                                id="<?= e($modalId) ?>-new-name"
                                                name="new_name"
                                                type="text"
                                                value="<?= e($item) ?>"
                                                autocomplete="off"
                                                class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none transition focus:border-cyan-400 focus:ring-2 focus:ring-cyan-400/30"
                                            >
                                            <button
                                                type="submit"
                                                class="w-full rounded-2xl bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300 focus:outline-none focus:ring-2 focus:ring-cyan-300 focus:ring-offset-2 focus:ring-offset-slate-950"
                                            >
                                                Salvar novo nome
                                            </button>
                                        </form>

                                        <div class="my-5 border-t border-slate-800"></div>

                                        <form method="post" onsubmit="return confirm(<?= e($deleteMessage) ?>);">
                                            <input type="hidden" name="schema_action" value="delete">
                                            <input type="hidden" name="schema_type" value="<?= e($column['key']) ?>">
                                            <input type="hidden" name="current_name" value="<?= e($item) ?>">
                                            <button
                                                type="submit"
                                                class="w-full rounded-2xl border border-rose-400/40 bg-rose-400/10 px-4 py-3 text-sm font-semibold text-rose-100 transition hover:border-rose-300 hover:bg-rose-400/20 focus:outline-none focus:ring-2 focus:ring-rose-300 focus:ring-offset-2 focus:ring-offset-slate-950"
                                            >
                                                Excluir este item
                                            </button>
                                            <p class="mt-2 text-xs leading-5 text-slate-500">
                                                Labels apagarão todos os seus nodes; relationships serão apagados; property keys serão removidas de nodes e relationships.
                                            </p>
                                        </form>
                                    </div>
                                </dialog>
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
