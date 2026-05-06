<?php

declare(strict_types=1);

/** @var array{nodes: list<string>, relationships: list<string>, properties: list<string>, error: ?string} $schema */
/** @var ?array{type: string, message: string} $flash */
$columns = [
    [
        'key' => 'nodes',
        'title' => 'Nodes',
        'placeholder' => 'Ex.: Pessoa, Projeto, Empresa',
        'button' => 'Criar node',
        'items' => $schema['nodes'],
    ],
    [
        'key' => 'relationships',
        'title' => 'Relationships',
        'placeholder' => 'Ex.: CONHECE, TRABALHA_EM',
        'button' => 'Criar relationship',
        'items' => $schema['relationships'],
    ],
    [
        'key' => 'properties',
        'title' => 'Property keys',
        'placeholder' => 'Ex.: nome, email, criadoEm',
        'button' => 'Criar property key',
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
        <h2 class="font-semibold"><?= $flash['type'] === 'success' ? 'Criado no banco' : 'Não foi possível criar' ?></h2>
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
                    Ao enviar, uma query é executada agora no Neo4j e a lista abaixo é atualizada com o que existe no banco.
                </p>
            </form>

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
