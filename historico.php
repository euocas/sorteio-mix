<?php
$dataDir = __DIR__ . '/data';
$historyFile = $dataDir . '/history.json';

$history = [];

if (file_exists($historyFile)) {
  $content = file_get_contents($historyFile);
  $decoded = json_decode($content, true);

  if (is_array($decoded)) {
    $history = array_reverse($decoded);
  }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Histórico de Sorteios</title>
  <link rel="stylesheet" href="style.css">
</head>

<body class="history-page">

  <div class="container">

    <header class="site-header">

      <nav class="tabs">
        <button type="button" class="btn btn-ghost" onclick="window.location.href='index.html'">Home</button>
        <button type="button" class="btn btn-ghost" onclick="window.location.href='sorteio.php'">Sorteio</button>
        <button type="button" class="btn btn-ghost" onclick="window.location.href='sorteio_de_mapas.html'">Mapa</button>
        <button type="button" class="btn btn-ghost" onclick="window.location.href='ao_vivo.php'">Ao Vivo</button>
        <button type="button" class="btn btn-ghost" onclick="window.location.href='ranking.php'">Ranking</button>
        <button type="button" class="btn btn-ghost active" onclick="window.location.href='historico.php'">Histórico</button>
        <button type="button" class="btn btn-ghost" onclick="window.location.href='temporadas.php'">Temporadas</button>
      </nav>

      <h1>HISTÓRICO DE SORTEIOS</h1>

      <p>
        Todos os sorteios de mix já realizados ficam registrados aqui
      </p>

    </header>

    <div class="history-toolbar">
      <button type="button" class="btn btn-ghost history-clear-button" onclick="clearHistory()">Limpar histórico</button>
    </div>

    <?php if (empty($history)): ?>

      <div class="empty-msg">
        Nenhum sorteio registrado ainda.
      </div>

    <?php else: ?>

      <section class="history-list">

        <?php foreach ($history as $entry): ?>

          <?php
          $date = new DateTime($entry['date']);
          $teams = $entry['teams'] ?? [];
          $diff = abs((int) ($teams['sum1'] ?? 0) - (int) ($teams['sum2'] ?? 0));
          $match = $entry['match'] ?? null;
          $matchFinished = is_array($match) && ($match['status'] ?? '') === 'finished';
          ?>

          <article class="history-item">

            <div class="history-date">

              <span>
                📅
                <?= $date->format('d/m/Y') ?>
                às
                <?= $date->format('H:i') ?>
              </span>

              <?php if (!empty($entry['maps']['selected'][0]['name'])): ?>
                <span class="history-map">
                  Mapa: <?= htmlspecialchars($entry['maps']['selected'][0]['name']) ?>
                </span>
              <?php endif; ?>

              <span class="history-difference">
                Diferença de peso:
                <strong><?= $diff ?></strong>

                <?php if ($diff <= 2): ?>
                  <span class="history-balanced">✓ EQUILIBRADO</span>
                <?php endif; ?>
              </span>

            </div>

            <div class="teams-grid">

              <div class="team-card">

                <div class="team-title">
                  <span>TIME 1</span>

                  <span class="rank-sum-badge">
                    Peso <?= (int) ($teams['sum1'] ?? 0) ?>
                  </span>
                </div>

                <div class="team-players">

                  <?php foreach (($teams['team1'] ?? []) as $p): ?>

                    <div class="team-player">

                      <span class="player-name">
                        <?= htmlspecialchars($p['name'] ?? '') ?>
                      </span>

                      <span class="rank-pill rp-<?= (int) ($p['rank'] ?? 0) ?>">
                        R<?= (int) ($p['rank'] ?? 0) ?>
                      </span>

                    </div>

                  <?php endforeach; ?>

                </div>

              </div>

              <div class="team-card">

                <div class="team-title">
                  <span>TIME 2</span>

                  <span class="rank-sum-badge">
                    Peso <?= (int) ($teams['sum2'] ?? 0) ?>
                  </span>
                </div>

                <div class="team-players">

                  <?php foreach (($teams['team2'] ?? []) as $p): ?>

                    <div class="team-player">

                      <span class="player-name">
                        <?= htmlspecialchars($p['name'] ?? '') ?>
                      </span>

                      <span class="rank-pill rp-<?= (int) ($p['rank'] ?? 0) ?>">
                        R<?= (int) ($p['rank'] ?? 0) ?>
                      </span>

                    </div>

                  <?php endforeach; ?>

                </div>

              </div>

            </div>

            <div class="history-match-result">

              <?php if ($matchFinished): ?>

                <div class="history-match-title">
                  🏆 RESULTADO DA PARTIDA
                </div>

                <div class="history-match-score">
                  <span>TIME 1</span>
                  <strong><?= (int) $match['score1'] ?></strong>
                  <b>×</b>
                  <strong><?= (int) $match['score2'] ?></strong>
                  <span>TIME 2</span>
                </div>

                <div class="history-match-winner">
                  <?php if (($match['winner'] ?? '') === 'EMPATE'): ?>
                    🤝 EMPATE
                  <?php else: ?>
                    🏆 Vencedor: <?= htmlspecialchars((string) ($match['winner'] ?? '')) ?>
                  <?php endif; ?>
                </div>

              <?php else: ?>

                <div class="history-match-title">
                  ⏳ RESULTADO DA PARTIDA
                </div>

                <div class="history-match-pending">
                  Aguardando resultado da partida
                </div>

              <?php endif; ?>

            </div>

          </article>

        <?php endforeach; ?>

      </section>

    <?php endif; ?>

  </div>

  <div class="copy">
    <footer>
      <p>
        Desenvolvido por
        <a href="https://github.com/euocas" target="_blank" rel="noopener noreferrer">
          👾 PIXELCOPATA
        </a>
      </p>
    </footer>
  </div>

  <script src="history-clear.js"></script>

</body>
</html>
