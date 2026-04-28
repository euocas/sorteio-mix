<?php
$players = [
  1 => ['ABK', 'APK', 'GUIIIZERA', 'GUSMA1', 'MAX', 'TAROTO', 'KVARA',],
  2 => ['PDZIKA', 'BAIANO', 'LEOZOX', 'KUSH', 'LEVI', 'LULU', 'POWERZIN', 'FOFÃO', 'TODDY'],
  3 => ['XAUS', 'JONAS', 'SANTIAGO', 'NEGOTRUFA', 'GRIMM', 'PREDRAZA', 'JOTAV'],
  4 => ['JV (PUTIFERO)', 'PIXEL', 'FALKES', 'LEE', 'MOREL', 'AVESTRUZ'],
  5 => ['PESCADOR', 'LULA', 'CARAMELO', 'MARKEZ', 'KABAL', 'PANCO'],
];

foreach ($players as &$rankList) {
  shuffle($rankList);
}
unset($rankList);

$rankLabels = [
  1 => ['label' => 'RANK 1', 'icon' => '🔥'],
  2 => ['label' => 'RANK 2', 'icon' => '⚡'],
  3 => ['label' => 'RANK 3', 'icon' => '🎯'],
  4 => ['label' => 'RANK 4', 'icon' => '🧨'],
  5 => ['label' => 'RANK 5', 'icon' => '🔫'],
];

$error = '';
$teams = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $selected = $_POST['players'] ?? [];

  if (count($selected) !== 10) {
    $error = 'Selecione exatamente 10 jogadores! Você selecionou ' . count($selected) . '.';
  } else {
    // Build player objects with rank
    $playerObjects = [];
    foreach ($selected as $entry) {
      [$rank, $name] = explode('|', $entry, 2);
      $playerObjects[] = ['name' => $name, 'rank' => (int)$rank];
    }

    // Balanced sort: pair highest + lowest rank
    usort($playerObjects, fn($a, $b) => $a['rank'] <=> $b['rank']);

    $team1 = [];
    $team2 = [];
    $sum1 = 0;
    $sum2 = 0;

    // Greedy balanced assignment
    foreach ($playerObjects as $player) {
      if (count($team1) < 5 && (count($team2) >= 5 || $sum1 <= $sum2)) {
        $team1[] = $player;
        $sum1 += $player['rank'];
      } else {
        $team2[] = $player;
        $sum2 += $player['rank'];
      }
    }

    // Shuffle each team for randomness within balance
    shuffle($team1);
    shuffle($team2);

    $teams = ['team1' => $team1, 'team2' => $team2, 'sum1' => $sum1, 'sum2' => $sum2];
  }
}

$rankColors = [
  1 => ['bg' => '#e8f5e9', 'border' => '#4caf50', 'text' => '#1b5e20', 'badge' => '#4caf50'],
  2 => ['bg' => '#e3f2fd', 'border' => '#2196f3', 'text' => '#0d47a1', 'badge' => '#2196f3'],
  3 => ['bg' => '#fff8e1', 'border' => '#ff9800', 'text' => '#e65100', 'badge' => '#ff9800'],
  4 => ['bg' => '#fce4ec', 'border' => '#e91e63', 'text' => '#880e4f', 'badge' => '#e91e63'],
  5 => ['bg' => '#f3e5f5', 'border' => '#9c27b0', 'text' => '#4a148c', 'badge' => '#9c27b0'],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sorteio de Times</title>
  <link rel="stylesheet" href="style.css">
</head>

<body>
  <div class="container">
    <header>

      <!-- BOTÕES DE NAVEGAÇÃO -->

      <button type="button" class="btn btn-ghost" onclick="window.location.href='/index.html'">
        Home
      </button>
      <button type="button" class="btn btn-ghost" onclick="window.location.href='sorteio_de_mapas.html'">
        Sortear Mapas
      </button>
      <button type="button" class="btn btn-ghost" onclick="window.location.href='sorteio.php'">
        Voltar
      </button>



      <h1>⚡ SORTEIO DE MIX ⚡</h1>
      <p>Selecione 10 jogadores para gerar dois times equilibrados</p>

    </header>

    <?php if ($error): ?>
      <div class="error-msg">⚠ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" id="sortForm">
      <div class="counter-bar">
        <div>
          <div class="counter-text">Jogadores selecionados</div>
          <div class="counter-num"><span id="selCount">0</span> / 10</div>
        </div>
        <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
          <button type="button" class="btn btn-ghost" onclick="clearAll()">Limpar</button>
          <button type="submit" class="btn btn-primary">⚡ Sortear Times</button>
        </div>
      </div>

      <div class="ranks-grid">
        <?php foreach ($players as $rank => $names): ?>
          <div class="rank-card rank-<?= $rank ?>">
            <div class="rank-header">
              <span><?= $rankLabels[$rank]['icon'] ?></span>
              <span><?= $rankLabels[$rank]['label'] ?></span>
            </div>
            <div class="rank-players">
              <?php foreach ($names as $name): ?>
                <?php $val = $rank . '|' . $name; ?>
                <label class="player-label" id="lbl-<?= md5($val) ?>">
                  <input type="checkbox" name="players[]" value="<?= htmlspecialchars($val) ?>"
                    onchange="updateCounter(this)"
                    <?= (isset($_POST['players']) && in_array($val, $_POST['players'])) ? 'checked' : '' ?>>
                  <?= htmlspecialchars($name) ?>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </form>

    <?php if ($teams): ?>
      <div class="results-section">
        <h2>🏆 Times Sorteados</h2>
        <div class="teams-grid">
          <div class="team-card team-1">
            <div class="team-title">
              <span>TIME 1</span>
              <span class="rank-sum-badge">Peso <?= $teams['sum1'] ?></span>
            </div>
            <div class="team-players">
              <?php foreach ($teams['team1'] as $p): ?>
                <div class="team-player">
                  <span class="player-name"><?= htmlspecialchars($p['name']) ?></span>
                  <span class="rank-pill rp-<?= $p['rank'] ?>">R<?= $p['rank'] ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="team-card team-2">
            <div class="team-title">
              <span>TIME 2</span>
              <span class="rank-sum-badge">Peso <?= $teams['sum2'] ?></span>
            </div>
            <div class="team-players">
              <?php foreach ($teams['team2'] as $p): ?>
                <div class="team-player">
                  <span class="player-name"><?= htmlspecialchars($p['name']) ?></span>
                  <span class="rank-pill rp-<?= $p['rank'] ?>">R<?= $p['rank'] ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <div class="balance-info">
          Diferença de peso entre os times: <strong><?= abs($teams['sum1'] - $teams['sum2']) ?></strong>
          <?php if (abs($teams['sum1'] - $teams['sum2']) <= 2): ?> — Times bem equilibrados ✓<?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>


  <script>
    function updateCounter(checkbox) {
      const checked = document.querySelectorAll('input[type="checkbox"]:checked');
      document.getElementById('selCount').textContent = checked.length;
      const lbl = checkbox.closest('label');
      if (checkbox.checked) lbl.classList.add('checked');
      else lbl.classList.remove('checked');

      if (checked.length > 10) {
        checkbox.checked = false;
        lbl.classList.remove('checked');
        document.getElementById('selCount').textContent = 10;
        alert('Você só pode selecionar 10 jogadores!');
      }
    }

    function clearAll() {
      document.querySelectorAll('input[type="checkbox"]').forEach(cb => {
        cb.checked = false;
        cb.closest('label').classList.remove('checked');
      });
      document.getElementById('selCount').textContent = '0';
    }

    // Restore checked state on load
    document.querySelectorAll('input[type="checkbox"]:checked').forEach(cb => {
      cb.closest('label').classList.add('checked');
    });
    document.getElementById('selCount').textContent =
      document.querySelectorAll('input[type="checkbox"]:checked').length;
  </script>
</body>

</html>