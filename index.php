<?php

declare(strict_types=1);

$autoload = __DIR__ . '/vendor/autoload.php';
$fallbackLib = __DIR__ . '/lib/neo4j_schema.php';

if (is_file($autoload)) {
    require_once $autoload;
} else {
    require_once $fallbackLib;
}

$views = [
    'configurar_estrutura_do_banco' => __DIR__ . '/view/configurar_estrutura_do_banco.php',
];

$currentView = $_GET['view'] ?? 'configurar_estrutura_do_banco';
$viewFile = $views[$currentView] ?? $views['configurar_estrutura_do_banco'];
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        neo4j_create_schema_item($_POST['schema_type'] ?? '', $_POST['name'] ?? '');
        $flash = [
            'type' => 'success',
            'message' => 'Estrutura criada no banco com sucesso.',
        ];
    } catch (Throwable $exception) {
        $flash = [
            'type' => 'error',
            'message' => $exception->getMessage(),
        ];
    }
}

$schema = neo4j_schema_overview();
?>
<!doctype html>
<html lang="pt-BR" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Configuração da estrutura do banco Neo4j</title>
    <script>
        tailwind = { config: { darkMode: 'class' } };
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
    <div class="mx-auto flex min-h-screen w-full max-w-7xl flex-col px-4 py-6 sm:px-6 lg:px-8">
        <header class="mb-8 rounded-3xl border border-slate-800 bg-slate-900/70 p-6 shadow-2xl shadow-slate-950/40 backdrop-blur">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-sm font-medium uppercase tracking-[0.3em] text-cyan-300">Neo4j + Bolt</p>
                    <h1 class="mt-2 text-3xl font-bold tracking-tight text-white">Configurar estrutura do banco</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-300">
                        Visualize os labels, relacionamentos e chaves de propriedades existentes. Ao usar os campos abaixo, o item é criado imediatamente no banco atual.
                    </p>
                </div>
                <div class="rounded-2xl border border-cyan-400/20 bg-cyan-400/10 px-4 py-3 text-sm text-cyan-100">
                    <span class="block text-xs uppercase tracking-widest text-cyan-300">Conexão</span>
                    <code class="break-all"><?= e(neo4j_bolt_url()) ?></code>
                </div>
            </div>
        </header>

        <main class="flex-1">
            <?php require $viewFile; ?>
        </main>
    </div>
</body>
</html>
