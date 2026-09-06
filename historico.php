<?php
$dataDir = __DIR__ . '/data';
$historyFile = $dataDir . '/history.json';

$history = [];

if (file_exists($historyFile)) {
  $content = file_get_contents($historyFile);
  $decoded = json_decode($content, true);

  if (is_array($decoded)) {
    $history = array_reverse($decoded); // mais recente primeiro
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

    <!-- =====================================================
             CABEÇALHO
        ====================================================== -->

    <header class="site-header">

      <!-- MENU -->
      <nav class="tabs">

        <button
          type="button"
          class="btn btn-ghost"
          onclick="window.location.href='index.html'">
          Home
        </button>

        <button
          type="button"
          class="btn btn-ghost"
          onclick="window.location.href='sorteio.php'">
          Sorteio
        </button>

        <button
          type="button"
          class="btn btn-ghost"
          onclick="window.location.href='sorteio_de_mapas.html'">
          Mapa
        </button>

        <button
          type="button"
          class="btn btn-ghost"
          onclick="window.location.href='ao_vivo.php'">
          Ao Vivo
        </button>

        <button
          type="button"
          class="btn btn-ghost"
          onclick="window.location.href='ranking.php'">
          Ranking
        </button>

        <button
          type="button"
          class="btn btn-ghost active"
          onclick="window.location.href='historico.php'">
          Histórico
        </button>

        <button
          type="button"
          class="btn btn-ghost"
          onclick="window.location.href='temporadas.php'">
          Temporadas
        </button>

      </nav>

      <h1>🕘 HISTÓRICO DE SORTEIOS</h1>

      <p>
        Todos os sorteios de mix já realizados ficam registrados aqui
      </p>

    </header>

    <div class="history-toolbar">
      <button type="button" class="btn btn-ghost history-clear-button" onclick="clearHistory()">Limpar histórico</button>
    </div>


    <!-- =====================================================
             CONTEÚDO
        ====================================================== -->

    <?php if (empty($history)): ?>

      <div class="empty-msg">
        Nenhum sorteio registrado ainda.
      </div>

    <?php else: ?>

      <section class="history-list">

        <?php foreach ($history as $entry): ?>

          <?php
          $date = new DateTime($entry['date']);
          $teams = $entry['teams'];

          $diff = abs(
            $teams['sum1'] - $teams['sum2']
          );
          ?>

          <article class="history-item">

            <!-- DATA / RESULTADO -->
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


            <!-- TIMES -->
            <div class="teams-grid">

              <!-- TIME 1 -->
              <div class="team-card">

                <div class="team-title">

                  <span>TIME 1</span>

                  <span class="rank-sum-badge">
                    Peso <?= $teams['sum1'] ?>
                  </span>

                </div>

                <div class="team-players">

                  <?php foreach ($teams['team1'] as $p): ?>

                    <div class="team-player">

                      <span class="player-name">
                        <?= htmlspecialchars($p['name']) ?>
                      </span>

                      <span class="rank-pill rp-<?= (int) $p['rank'] ?>">
                        R<?= (int) $p['rank'] ?>
                      </span>

                    </div>

                  <?php endforeach; ?>

                </div>

              </div>


              <!-- TIME 2 -->
              <div class="team-card">

                <div class="team-title">

                  <span>TIME 2</span>

                  <span class="rank-sum-badge">
                    Peso <?= $teams['sum2'] ?>
                  </span>

                </div>

                <div class="team-players">

                  <?php foreach ($teams['team2'] as $p): ?>

                    <div class="team-player">

                      <span class="player-name">
                        <?= htmlspecialchars($p['name']) ?>
                      </span>

                      <span class="rank-pill rp-<?= (int) $p['rank'] ?>">
                        R<?= (int) $p['rank'] ?>
                      </span>

                    </div>

                  <?php endforeach; ?>

                </div>

              </div>

            </div>

          </article>

        <?php endforeach; ?>

      </section>

    <?php endif; ?>

  </div>


  <!-- =====================================================
         RODAPÉ
    ====================================================== -->

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
