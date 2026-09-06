<?php
session_start();
require_once __DIR__ . '/leaderboard.php';

$players = [
  1 => ['APK', 'GUIIIZERA', 'GUSMA1', 'MAX', 'KVARA', 'COMPLETE 1', 'DNLZIN', 'PDZIKA', 'JAMMER'],
  2 => ['BAIANO', 'LEOZOX', 'KUSH', 'LEVI', 'TODDY', 'COMPLETE 2'],
  3 => ['XAUS', 'JONAS', 'SANTIAGO', 'JOTAV', 'COMPLETE 3'],
  4 => ['JV (PUTIFERO)', 'PIXELCOPATA', 'FALKES', 'GRIMM', 'AVESTRUZ', 'COMPLETE 4', 'SIMO'],
  5 => ['PESCADOR', 'LULA', 'CARAMELO', 'MARKEZ', 'KABAL', 'PANCO', 'COMPLETE 5'],
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
$rawSelected = [];
$drawMode = 'rank';
$rankingMissingCount = 0;

function recordDrawHistory(array $teams, array $selected): bool
{
  $historyFile = __DIR__ . '/data/history.json';
  $handle = fopen($historyFile, 'c+');
  if (!$handle) {
    return false;
  }

  flock($handle, LOCK_EX);
  rewind($handle);
  $content = stream_get_contents($handle);
  $history = json_decode($content ?: '', true);
  $history = is_array($history) ? $history : [];
  $history[] = [
    'date' => date('c'),
    'selected' => array_values(array_map('strval', $selected)),
    'teams' => $teams,
  ];
  ftruncate($handle, 0);
  rewind($handle);
  $written = fwrite($handle, json_encode($history, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
  fflush($handle);
  flock($handle, LOCK_UN);
  fclose($handle);
  return $written !== false;
}

function readTodayDrawHistory(): array
{
  $historyFile = __DIR__ . '/data/history.json';
  if (!file_exists($historyFile)) {
    return [];
  }

  $handle = fopen($historyFile, 'r');
  if (!$handle) {
    return [];
  }

  flock($handle, LOCK_SH);
  $content = stream_get_contents($handle);
  flock($handle, LOCK_UN);
  fclose($handle);

  $history = json_decode($content ?: '', true);
  if (!is_array($history)) {
    return [];
  }

  $today = date('Y-m-d');
  $todayHistory = array_values(array_filter($history, static function (array $entry) use ($today): bool {
    try {
      return (new DateTimeImmutable((string) ($entry['date'] ?? '')))->format('Y-m-d') === $today;
    } catch (Throwable $exception) {
      return false;
    }
  }));

  return array_reverse($todayHistory);
}

function normalizeLeaderboardName(string $name): string
{
  $name = str_replace('$', 's', $name);
  $name = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name;
  return strtolower((string) preg_replace('/[^a-z0-9]/i', '', $name));
}

function findLeaderboardPlayer(string $name, array $leaderboard): ?array
{
  $normalizedName = normalizeLeaderboardName($name);
  $prefixMatch = null;
  $prefixLength = PHP_INT_MAX;

  foreach ($leaderboard as $leaderboardPlayer) {
    if (!is_array($leaderboardPlayer) || !isset($leaderboardPlayer['name'])) {
      continue;
    }

    $candidateName = normalizeLeaderboardName((string) $leaderboardPlayer['name']);
    if ($candidateName === $normalizedName) {
      return $leaderboardPlayer;
    }

    if (
      $normalizedName !== '' && $candidateName !== '' &&
      (str_starts_with($candidateName, $normalizedName) || str_starts_with($normalizedName, $candidateName)) &&
      strlen($candidateName) < $prefixLength
    ) {
      $prefixMatch = $leaderboardPlayer;
      $prefixLength = strlen($candidateName);
    }
  }

  return $prefixMatch;
}

function enrichPlayerFromLeaderboard(array $player, array $leaderboard): array
{
  $rankingPlayer = findLeaderboardPlayer($player['name'], $leaderboard);
  if ($rankingPlayer === null) {
    return array_merge($player, [
      'points' => null,
      'score' => 0,
      'steam_id' => null,
      'avatar_url' => null,
      'rankingName' => null,
    ]);
  }

  $points = is_numeric($rankingPlayer['points'] ?? null) ? $rankingPlayer['points'] : null;

  return array_merge($player, [
    'name' => (string) ($rankingPlayer['name'] ?? $player['name']),
    'points' => $points,
    'score' => $points === null ? 0 : max(0, $points),
    'steam_id' => $rankingPlayer['steam_id'] ?? null,
    'avatar_url' => $rankingPlayer['avatar_url'] ?? null,
    'rankingName' => $rankingPlayer['name'] ?? null,
  ]);
}

function buildRankingTeams(array $playerObjects): array
{
  $playerCount = count($playerObjects);
  $teamSize = intdiv($playerCount, 2);
  $bestMask = 0;
  $bestDifference = PHP_INT_MAX;

  for ($mask = 0; $mask < (1 << $playerCount); $mask++) {
    if (substr_count(decbin($mask), '1') !== $teamSize) {
      continue;
    }

    $sum1 = 0;
    for ($index = 0; $index < $playerCount; $index++) {
      if ($mask & (1 << $index)) {
        $sum1 += $playerObjects[$index]['score'];
      }
    }

    $total = array_sum(array_column($playerObjects, 'score'));
    $difference = abs($sum1 - ($total - $sum1));
    if ($difference < $bestDifference) {
      $bestDifference = $difference;
      $bestMask = $mask;
    }
  }

  $team1 = [];
  $team2 = [];
  $sum1 = 0;
  $sum2 = 0;

  foreach ($playerObjects as $index => $player) {
    if ($bestMask & (1 << $index)) {
      $team1[] = $player;
      $sum1 += $player['score'];
    } else {
      $team2[] = $player;
      $sum2 += $player['score'];
    }
  }

  shuffle($team1);
  shuffle($team2);

  return ['team1' => $team1, 'team2' => $team2, 'sum1' => $sum1, 'sum2' => $sum2];
}

if (isset($_GET['ranking_api'])) {
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  echo json_encode(fetchLeaderboard(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $selected = $_POST['players'] ?? [];
  $rawSelected = $selected;
  $drawMode = ($_POST['draw_mode'] ?? 'rank') === 'ranking' ? 'ranking' : 'rank';

  if (count($selected) !== 10) {
    $error = 'Selecione exatamente 10 jogadores! Você selecionou ' . count($selected) . '.';
  } else {
    $playerObjects = [];
    $leaderboard = $drawMode === 'ranking' ? fetchLeaderboard() : [];

    if ($drawMode === 'ranking' && $leaderboard === []) {
      $error = 'Ranking indisponível. Tente novamente.';
    }

    foreach ($selected as $entry) {
      [$rank, $name] = explode('|', $entry, 2);
      $player = ['name' => $name, 'rank' => (int) $rank, 'score' => (int) $rank];

      if ($drawMode === 'ranking') {
        $player = enrichPlayerFromLeaderboard($player, $leaderboard);
        $player['score'] = $player['points'] === null ? 0 : max(0, $player['points']);
        if ($player['points'] === null) {
          $rankingMissingCount++;
        }
      }

      $playerObjects[] = $player;
    }

    if ($error === '') {
      if ($drawMode === 'ranking') {
        $teams = buildRankingTeams($playerObjects);
      } else {
        // Preserve the existing rank-based balancing exactly.
        usort($playerObjects, fn($a, $b) => $a['rank'] <=> $b['rank']);
        $team1 = [];
        $team2 = [];
        $sum1 = 0;
        $sum2 = 0;

        foreach ($playerObjects as $player) {
          if (count($team1) < 5 && (count($team2) >= 5 || $sum1 <= $sum2)) {
            $team1[] = $player;
            $sum1 += $player['rank'];
          } else {
            $team2[] = $player;
            $sum2 += $player['rank'];
          }
        }

        shuffle($team1);
        shuffle($team2);
        $teams = ['team1' => $team1, 'team2' => $team2, 'sum1' => $sum1, 'sum2' => $sum2];
      }

      $teams['mode'] = $drawMode;
      $teams['missingRanking'] = $rankingMissingCount;
      recordDrawHistory($teams, $rawSelected);
      $_SESSION['draw_result'] = [
        'teams' => $teams,
        'rawSelected' => $rawSelected,
        'drawMode' => $drawMode,
      ];
      header('Location: sorteio.php#resultado');
      exit;
    }
  }
}

if (isset($_SESSION['draw_result']) && $_SERVER['REQUEST_METHOD'] === 'GET') {
  $drawResult = $_SESSION['draw_result'];
  unset($_SESSION['draw_result']);
  $teams = $drawResult['teams'];
  $rawSelected = $drawResult['rawSelected'];
  $drawMode = $drawResult['drawMode'];
}

$todayHistory = readTodayDrawHistory();

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
    <header class="site-header">

      <!-- MENU EM ABAS -->
      <nav class="tabs">
        <button type="button" class="btn btn-ghost" onclick="window.location.href='index.html'">Home</button>
        <button type="button" class="btn btn-ghost active" onclick="window.location.href='sorteio.php'">Sorteio</button>
        <button type="button" class="btn btn-ghost" onclick="window.location.href='sorteio_de_mapas.html'">Mapa</button>
        <button type="button" class="btn btn-ghost" onclick="window.location.href='ao_vivo.php'">Ao Vivo</button>
        <button type="button" class="btn btn-ghost" onclick="window.location.href='ranking.php'">Ranking</button>
        <button type="button" class="btn btn-ghost" onclick="window.location.href='historico.php'">Histórico</button>
        <button type="button" class="btn btn-ghost" onclick="window.location.href='temporadas.php'">Temporadas</button>
      </nav>

      <h1>⚡ SORTEIO DE MIX ⚡</h1>
      <p>Selecione 10 jogadores para gerar dois times equilibrados</p>

      <div class="draw-mode" aria-label="Modo de sorteio">
        <span class="draw-mode-label">Modo de sorteio</span>
        <div class="draw-mode-options">
          <button type="button" class="draw-mode-option<?= $drawMode === 'rank' ? ' active' : '' ?>" data-mode="rank" onclick="selectDrawMode('rank')">Ranking</button>
          <button type="button" class="draw-mode-option<?= $drawMode === 'ranking' ? ' active' : '' ?>" data-mode="ranking" onclick="selectDrawMode('ranking')">Ranking por Pontuação</button>
        </div>
        <p id="rankingStatus" class="draw-mode-status" aria-live="polite"></p>
      </div>

    </header>

    <div class="live-banner">
      <span><span class="live-dot"></span>Transmissão ao vivo ativa — quem estiver na página "Ao Vivo" vê suas escolhas em tempo real.</span>
      <button type="button" class="btn btn-ghost" onclick="startLiveDraw()">🔄 Iniciar novo sorteio ao vivo</button>
    </div>

    <?php if ($error): ?>
      <div class="error-msg">⚠ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="search-bar">
      <input
        type="text"
        id="playerSearch"
        class="search-input"
        placeholder="🔎 Buscar jogador pelo nome..."
        oninput="filterPlayers(this.value)"
        autocomplete="off">
    </div>

    <form method="POST" id="sortForm">
      <input type="hidden" name="draw_mode" id="drawMode" value="<?= htmlspecialchars($drawMode) ?>">
      <div class="counter-bar">
        <div>
          <div class="counter-text">Jogadores selecionados</div>
          <div class="counter-num"><span id="selCount">0</span> / 10</div>
        </div>
        <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
          <button type="button" class="btn btn-ghost" onclick="clearAll()">Limpar</button>
          <button type="submit" class="btn btn-primary" id="sortSubmit">⚡ Sortear Times</button>
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
                <label class="player-label" id="lbl-<?= md5($val) ?>" data-player-name="<?= htmlspecialchars($name) ?>">
                  <input type="checkbox" name="players[]" value="<?= htmlspecialchars($val) ?>"
                    onchange="updateCounter(this)"
                    <?= (isset($_POST['players']) && in_array($val, $_POST['players'])) ? 'checked' : '' ?>>
                  <img class="player-avatar player-list-avatar" alt="" hidden>
                  <span class="player-name-text"><?= htmlspecialchars($name) ?></span>
                  <span class="player-points" hidden></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </form>

    <?php if ($teams): ?>
      <div class="results-section">
        <h2><?= $teams['mode'] === 'ranking' ? 'Sorteio por Ranking por Pontuação' : 'Sorteio por Ranking' ?></h2>
        <div class="teams-grid">
          <div class="team-card team-1">
            <div class="team-title">
              <span>TIME 1</span>
              <span class="rank-sum-badge"><?= $teams['mode'] === 'ranking' ? 'Pontuação total: ' . number_format($teams['sum1'], 0, ',', '.') . ' pts' : 'Peso ' . number_format($teams['sum1'], 0, ',', '.') ?></span>
            </div>
            <div class="team-players">
              <?php foreach ($teams['team1'] as $p): ?>
                <div class="team-player">
                  <span class="player-name"><?= htmlspecialchars($p['name']) ?></span>
                  <?php if ($teams['mode'] === 'ranking'): ?>
                    <span class="player-score">Ranking por Pontuação: <?= htmlspecialchars((string) ($p['points'] ?? 0)) ?> pts</span>
                  <?php endif; ?>
                  <span class="rank-pill rp-<?= $p['rank'] ?>">R<?= $p['rank'] ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="team-card team-2">
            <div class="team-title">
              <span>TIME 2</span>
              <span class="rank-sum-badge"><?= $teams['mode'] === 'ranking' ? 'Pontuação total: ' . number_format($teams['sum2'], 0, ',', '.') . ' pts' : 'Peso ' . number_format($teams['sum2'], 0, ',', '.') ?></span>
            </div>
            <div class="team-players">
              <?php foreach ($teams['team2'] as $p): ?>
                <div class="team-player">
                  <span class="player-name"><?= htmlspecialchars($p['name']) ?></span>
                  <?php if ($teams['mode'] === 'ranking'): ?>
                    <span class="player-score">Ranking por Pontuação: <?= htmlspecialchars((string) ($p['points'] ?? 0)) ?> pts</span>
                  <?php endif; ?>
                  <span class="rank-pill rp-<?= $p['rank'] ?>">R<?= $p['rank'] ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <div class="balance-info">
          Diferença <?= $teams['mode'] === 'ranking' ? 'de pontuação' : 'de peso' ?> entre os times:
          <strong><?= number_format(abs($teams['sum1'] - $teams['sum2']), 0, ',', '.') ?></strong>
          <?php if ($teams['mode'] === 'ranking' && $teams['missingRanking'] > 0): ?>
            <br><span class="ranking-warning"><?= $teams['missingRanking'] ?> jogador(es) sem pontuação; peso neutro 0 utilizado.</span>
          <?php elseif (abs($teams['sum1'] - $teams['sum2']) <= 2): ?>
            — Times bem equilibrados ✓
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <section class="today-history" aria-labelledby="today-history-title">
      <div class="today-history-header">
        <h2 id="today-history-title">HISTÓRICO DE SORTEIOS DE HOJE</h2>
        <p>Times sorteados hoje, pra ajudar a não repetir sempre a mesma combinação. Reseta à meia-noite.</p>
      </div>

      <?php if (empty($todayHistory)): ?>
        <div class="today-history-empty">Nenhum sorteio realizado hoje.</div>
      <?php else: ?>
        <div class="today-history-list">
          <?php foreach ($todayHistory as $entry): ?>
            <?php
            $entryTeams = $entry['teams'] ?? [];
            $entryDate = new DateTimeImmutable((string) ($entry['date'] ?? 'now'));
            $entryMode = ($entryTeams['mode'] ?? 'rank') === 'ranking' ? 'Ranking por Pontuação' : 'Ranking';
            $teamNames = static function (array $team): string {
              return implode(', ', array_map(static fn(array $player): string => (string) ($player['name'] ?? ''), $team));
            };
            ?>
            <article class="today-history-card">
              <div class="today-history-card-header">
                <time datetime="<?= htmlspecialchars((string) ($entry['date'] ?? '')) ?>">
                  <?= $entryDate->format('d/m, H:i') ?>
                </time>
                <span class="today-history-meta">
                  <?php if (!empty($entry['maps']['selected'][0]['name'])): ?>
                    Mapa: <?= htmlspecialchars($entry['maps']['selected'][0]['name']) ?> ·
                  <?php endif; ?>
                  <?= htmlspecialchars($entryMode) ?>
                </span>
              </div>
              <div class="today-history-team">
                <strong>Time A<?php if (isset($entryTeams['sum1'])): ?> (<?= $entryMode === 'Ranking por Pontuação' ? number_format($entryTeams['sum1'], 0, ',', '.') . ' pts' : 'Peso ' . $entryTeams['sum1'] ?>)<?php endif; ?>:</strong>
                <span><?= htmlspecialchars($teamNames($entryTeams['team1'] ?? [])) ?></span>
              </div>
              <div class="today-history-team">
                <strong>Time B<?php if (isset($entryTeams['sum2'])): ?> (<?= $entryMode === 'Ranking por Pontuação' ? number_format($entryTeams['sum2'], 0, ',', '.') . ' pts' : 'Peso ' . $entryTeams['sum2'] ?>)<?php endif; ?>:</strong>
                <span><?= htmlspecialchars($teamNames($entryTeams['team2'] ?? [])) ?></span>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </div>


  <script>
    const drawModeInput = document.getElementById('drawMode');
    const rankingStatus = document.getElementById('rankingStatus');
    const sortForm = document.getElementById('sortForm');
    const sortSubmit = document.getElementById('sortSubmit');
    let rankingReady = drawModeInput.value !== 'ranking';
    let leaderboardPlayers = [];

    function normalizeLeaderboardName(name) {
      return String(name || '')
        .replace(/\$/g, 's')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]/g, '');
    }

    function findRankingPlayer(name) {
      const normalizedName = normalizeLeaderboardName(name);
      let prefixMatch = null;
      let shortestName = Infinity;

      for (const player of leaderboardPlayers) {
        const candidateName = normalizeLeaderboardName(player.name);
        if (candidateName === normalizedName) return player;

        if (normalizedName && candidateName &&
          (candidateName.startsWith(normalizedName) || normalizedName.startsWith(candidateName)) &&
          candidateName.length < shortestName) {
          prefixMatch = player;
          shortestName = candidateName.length;
        }
      }

      return prefixMatch;
    }

    function updateRankingPlayerList() {
      document.querySelectorAll('.player-label').forEach(label => {
        const player = findRankingPlayer(label.dataset.playerName);
        const avatar = label.querySelector('.player-list-avatar');
        const name = label.querySelector('.player-name-text');
        const points = label.querySelector('.player-points');

        if (drawModeInput.value !== 'ranking') {
          avatar.hidden = true;
          points.hidden = true;
          name.textContent = label.dataset.playerName;
          return;
        }

        name.textContent = player?.name || label.dataset.playerName;
        if (player?.avatar_url) {
          avatar.src = player.avatar_url;
          avatar.hidden = false;
          avatar.onerror = () => {
            avatar.hidden = true;
          };
        } else {
          avatar.hidden = true;
        }

        if (player && Number.isFinite(Number(player.points))) {
          points.textContent = `${Number(player.points)} pts`;
          points.hidden = false;
        } else {
          points.textContent = 'Sem pontuação';
          points.hidden = false;
        }
      });
    }

    function selectDrawMode(mode) {
      drawModeInput.value = mode === 'ranking' ? 'ranking' : 'rank';
      document.querySelectorAll('.draw-mode-option').forEach(button => {
        button.classList.toggle('active', button.dataset.mode === drawModeInput.value);
      });

      if (drawModeInput.value === 'ranking') {
        loadRankingForDraw();
      } else {
        rankingReady = true;
        leaderboardPlayers = [];
        updateRankingPlayerList();
        rankingStatus.textContent = '';
        rankingStatus.classList.remove('error');
        sortSubmit.disabled = false;
      }
    }

    async function loadRankingForDraw() {
      rankingReady = false;
      sortSubmit.disabled = true;
      rankingStatus.textContent = 'Carregando ranking...';
      rankingStatus.classList.remove('error');

      try {
        const response = await fetch('sorteio.php?ranking_api=1', {
          cache: 'no-store',
          headers: {
            'Accept': 'application/json'
          }
        });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);

        const data = await response.json();
        const leaderboard = Array.isArray(data) ? data : (data.players || data.leaderboard || []);
        if (!leaderboard.length) throw new Error('empty leaderboard');

        leaderboardPlayers = leaderboard;
        updateRankingPlayerList();
        rankingReady = true;
        sortSubmit.disabled = false;
        rankingStatus.textContent = `Ranking por Pontuação carregado • ${leaderboard.length} jogadores`;
      } catch (error) {
        console.error('Erro ao carregar ranking para o sorteio:', error);
        rankingStatus.textContent = 'Não foi possível carregar o ranking do servidor.';
        rankingStatus.classList.add('error');
      }
    }

    sortForm.addEventListener('submit', event => {
      if (drawModeInput.value === 'ranking' && !rankingReady) {
        event.preventDefault();
        alert('Ranking indisponível. Tente novamente.');
      }
    });

    if (drawModeInput.value === 'ranking') {
      loadRankingForDraw();
    }

    function filterPlayers(query) {
      const normalize = (str) => str
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, ''); // remove acentos

      const term = normalize(query.trim());

      document.querySelectorAll('.rank-card').forEach(card => {
        let anyVisible = false;

        card.querySelectorAll('.player-label').forEach(label => {
          const name = label.textContent.trim();
          const matches = term === '' || normalize(name).includes(term);
          label.style.display = matches ? '' : 'none';
          if (matches) anyVisible = true;
        });

        // esconde o card inteiro do rank se nenhum jogador bater com a busca
        card.style.display = anyVisible ? '' : 'none';
      });
    }

    let liveTimer = null;

    function sendLiveSelection() {
      const checked = Array.from(document.querySelectorAll('input[type="checkbox"]:checked')).map(cb => cb.value);
      clearTimeout(liveTimer);
      liveTimer = setTimeout(() => {
        fetch('live_state.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            action: 'update_selection',
            selected: checked
          })
        }).catch(() => {});
      }, 250);
    }

    function startLiveDraw() {
      fetch('live_state.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          action: 'reset'
        })
      }).then(() => {
        clearAll();
        alert('Sorteio ao vivo reiniciado! Envie o link da página "Ao Vivo" para o pessoal acompanhar.');
      }).catch(() => {
        alert('Não consegui avisar a página Ao Vivo (verifique a conexão), mas você pode continuar o sorteio normalmente.');
      });
    }

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
        return;
      }

      sendLiveSelection();
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

  <?php if ($teams): ?>
    <script>
      // Assim que o resultado é calculado, publica no estado ao vivo e no histórico.
      fetch('live_state.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          action: 'finish_draw',
          record_history: false,
          selected: <?= json_encode($rawSelected, JSON_UNESCAPED_UNICODE) ?>,
          teams: <?= json_encode($teams, JSON_UNESCAPED_UNICODE) ?>
        })
      }).catch(() => {});
    </script>
  <?php endif; ?>

  <div class="copy">
    <footer>
      <p>
        Desenvolvido por
        <a
          href="https://github.com/euocas"
          target="_blank"
          rel="noopener noreferrer">
          👾 PIXELCOPATA
        </a>
      </p>
    </footer>
  </div>
</body>

</html>