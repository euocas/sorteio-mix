<?php
require_once __DIR__ . '/season_store.php';

$seasons = synchronizeCurrentSeason();
uksort($seasons, static fn(string $left, string $right): int => strcmp($right, $left));
$selectedKey = $_GET['season'] ?? array_key_first($seasons);
if (!is_string($selectedKey) || !isset($seasons[$selectedKey])) {
  $selectedKey = array_key_first($seasons);
}
$selectedSeason = $selectedKey !== null ? $seasons[$selectedKey] : null;
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Temporadas Passadas</title>
  <link rel="stylesheet" href="style.css">
  <link rel="icon" type="image/png" href="src/icon.png">
</head>

<body class="season-page">
  <!-- LOGO TOPO -->

      <img src="src/logo.png" alt="Mix da Família GC" class="logo-topo">
      
  <div class="container season-wrap">
    <header class="site-header season-header">
      <nav class="tabs">
        <button type="button" class="btn btn-ghost" onclick="window.location.href='index.html'">Home</button>
        <button type="button" class="btn btn-ghost" onclick="window.location.href='sorteio.php'">Sorteio</button>
        <button type="button" class="btn btn-ghost" onclick="window.location.href='sorteio_de_mapas.html'">Mapa</button>
        <button type="button" class="btn btn-ghost" onclick="window.location.href='ao_vivo.php'">Ao Vivo</button>
        <button type="button" class="btn btn-ghost" onclick="window.location.href='historico.php'">Histórico</button>
        <button type="button" class="btn btn-ghost" onclick="window.location.href='ranking.php'">Ranking</button>
        <button type="button" class="btn btn-ghost active" onclick="window.location.href='temporadas.php'">Temporadas</button>
      </nav>
      <h1>TEMPORADAS</h1>
      <p>Ranking congelado de temporadas anteriores, apenas para consulta. Não altera nem conta para a pontuação atual.</p>
    </header>

    <?php if ($selectedSeason): ?>
      <section class="season-selector" aria-label="Selecionar temporada">
        <label for="seasonSelect">Temporada</label>
        <select id="seasonSelect" onchange="window.location.href='temporadas.php?season=' + encodeURIComponent(this.value)">
          <?php foreach ($seasons as $key => $season): ?>
            <option value="<?= htmlspecialchars($key) ?>" <?= $key === $selectedKey ? 'selected' : '' ?>>
              <?= htmlspecialchars($season['name']) ?><?= empty($season['closed']) ? ' (em andamento)' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </section>

      <section class="season-card" aria-label="Ranking da temporada <?= htmlspecialchars($selectedSeason['name']) ?>">
        <div class="season-card-header">
          <div>
            <h2><?= htmlspecialchars($selectedSeason['name']) ?></h2>
            <span><?= empty($selectedSeason['closed']) ? 'Temporada atual em andamento' : 'Snapshot congelado' ?></span>
          </div>
          <span><?= count($selectedSeason['players']) ?> jogadores</span>
        </div>

        <?php if (!empty($selectedSeason['players'])): ?>
          <div class="season-player-list">
            <?php foreach ($selectedSeason['players'] as $player): ?>
              <article class="season-player">
                <span class="season-position"><?= str_pad((string) $player['position'], 2, '0', STR_PAD_LEFT) ?></span>
                <?php if (!empty($player['avatar'])): ?>
                  <img class="season-avatar" src="<?= htmlspecialchars($player['avatar']) ?>" alt="" loading="lazy">
                <?php else: ?>
                  <span class="season-avatar season-avatar-fallback"><?= htmlspecialchars(strtoupper(substr($player['name'], 0, 1))) ?></span>
                <?php endif; ?>
                <span class="season-player-name"><?= htmlspecialchars($player['name']) ?></span>
                <span class="season-player-rank"><?= htmlspecialchars((string) ($player['rank'] ?? 'Sem patente')) ?></span>
                <span class="season-player-points"><?= htmlspecialchars((string) $player['points']) ?> pts</span>
                <span class="season-stats">
                  K: <?= htmlspecialchars((string) ($player['kills'] ?? '-')) ?>
                  D: <?= htmlspecialchars((string) ($player['deaths'] ?? '-')) ?>
                  HS: <?= htmlspecialchars((string) ($player['hs_pct'] ?? '-')) ?>%
                </span>
              </article>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p class="season-empty">Esta temporada não possui jogadores salvos.</p>
        <?php endif; ?>
      </section>
    <?php else: ?>
      <section class="season-empty-panel">
        <p>Ainda não existem temporadas registradas.</p>
        <span>O primeiro snapshot será criado quando o ranking atual for sincronizado.</span>
      </section>
    <?php endif; ?>
  </div>

  <div class="copy">
    <footer>
      <p>
        Desenvolvido por
        <a href="https://github.com/euocas" target="_blank" rel="noopener noreferrer">👾 PIXELCOPATA</a>
      </p>
    </footer>
  </div>
</body>

</html>