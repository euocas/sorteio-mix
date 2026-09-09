<?php
session_start();
require_once __DIR__ . '/leaderboard.php';

// Lista manual do Sorteio: edite somente esta distribuição para mover ou
// adicionar participantes entre os ranks. O Ranking nunca altera estes grupos.
$players = [
  1 => [
    ['name' => 'APK'],
    ['name' => 'GUIIIZERA'],
    ['name' => 'GUSMA1'],
    ['name' => 'MAX'],
    ['name' => 'KVARA'],
    ['name' => 'COMPLETE 1'],
    ['name' => 'DNLZIN'],
    ['name' => 'PDZIKA'],
    ['name' => 'JAMMER'],
  ],

  2 => [
    ['name' => 'BAIANO'],
    ['name' => 'POWERZIN'],
    ['name' => 'LEOZOX'],
    ['name' => 'KUSH'],
    ['name' => 'TODDY'],
    ['name' => 'RAZEC'],
    ['name' => 'COMPLETE 2'],
  ],

  3 => [
    ['name' => 'SCHAUSS'],
    ['name' => 'JONAS'],
    ['name' => 'MAKAROV'],
    ['name' => 'GRIMM'],
    ['name' => 'JOTAV', 'lookup' => 'BOY MAGUINHO'],
    ['name' => 'COMPLETE 3'],
  ],

  4 => [
    ['name' => 'JV (PUTIFERO)'],
    ['name' => 'PIXELCOPATA'],
    ['name' => 'FALKES'],
    ['name' => 'AVESTRUZ'],
    ['name' => 'COMPLETE 4'],
    ['name' => 'SIMO'],
    ['name' => 'MATHEURO'],

  ],

  5 => [
    ['name' => 'PESCADOR'],
    ['name' => 'LULA', 'lookup' => 'https://www.twitch.tv/objecctt'],
    ['name' => 'MARKEZ'],
    ['name' => 'KABAL'],
    ['name' => 'PANCO'],
    ['name' => 'RONY. RUIM'],
    ['name' => 'COMPLETE 5'],
  ]
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
$vacancyCount = 1;
$vacancyResult = null;
$drawId = null;

function publishTeamLiveState(array $selected, array $teams, ?string $drawId = null): bool
{
  $liveFile = __DIR__ . '/data/live_state.json';

  $state = [
    'status' => 'done',
    'selected' => array_values(array_map('strval', $selected)),
    'teams' => $teams,
    'maps' => null,
    'match' => null,
    'vacancy_result' => null,
    'draw_mode' => $teams['mode'] ?? 'rank',
    'vacancy_count' => null,
    'draw_id' => $drawId,
    'updated_at' => date('c'),
  ];

  $handle = fopen($liveFile, 'c+');
  if (!$handle) {
    return false;
  }

  flock($handle, LOCK_EX);
  ftruncate($handle, 0);
  rewind($handle);
  $written = fwrite(
    $handle,
    json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
  );
  fflush($handle);
  flock($handle, LOCK_UN);
  fclose($handle);

  return $written !== false;
}

function publishVacancyLiveState(array $selected, array $vacancyResult, ?string $drawId = null): bool
{
  $liveFile = __DIR__ . '/data/live_state.json';

  $state = [
    'status' => 'vacancies_done',
    'selected' => array_values(array_map('strval', $selected)),
    'teams' => null,
    'maps' => null,
    'match' => null,
    'vacancy_result' => $vacancyResult,
    'draw_mode' => 'vacancies',
    'vacancy_count' => min(10, max(1, (int) ($vacancyResult['vacancies'] ?? 1))),
    'draw_id' => $drawId,
    'updated_at' => date('c'),
  ];

  $handle = fopen($liveFile, 'c+');
  if (!$handle) {
    return false;
  }

  flock($handle, LOCK_EX);
  ftruncate($handle, 0);
  rewind($handle);
  $written = fwrite(
    $handle,
    json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
  );
  fflush($handle);
  flock($handle, LOCK_UN);
  fclose($handle);

  return $written !== false;
}

function recordDrawHistory(array $teams, array $selected, string $drawId): bool
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
    'id' => $drawId,
    'date' => date('c'),
    'selected' => array_values(array_map('strval', $selected)),
    'teams' => $teams,
    'match' => [
      'status' => 'pending',
      'score1' => null,
      'score2' => null,
      'winner' => null,
    ],
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

function normalizePlayerName(string $name): string
{
  $name = str_replace('$', 's', $name);
  $name = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name;
  $name = strtolower((string) preg_replace('/[^a-z0-9]/i', '', $name));
  // Steam nicknames frequently stretch characters (e.g. JAMMMMMER). They
  // remain the same manual player after repeated characters are compressed.
  return (string) preg_replace('/(.)\1+/', '$1', $name);
}

function isCompletePlaceholder(string $name): bool
{
  return preg_match('/^COMPLETE\s+\d+$/i', trim($name)) === 1;
}

function findLeaderboardPlayerByName(string $name, array $leaderboard): ?array
{
  $needle = normalizePlayerName($name);
  if ($needle === '') {
    return null;
  }

  $partialMatches = [];
  $closestMatch = null;
  $closestDistance = PHP_INT_MAX;
  $closestThreshold = 0;
  $closestIsUnique = true;
  foreach ($leaderboard as $candidate) {
    if (!is_array($candidate) || !isset($candidate['name'])) {
      continue;
    }
    $candidateName = normalizePlayerName((string) $candidate['name']);
    if ($candidateName === $needle) {
      return $candidate;
    }
    if ($candidateName !== '' && (str_starts_with($candidateName, $needle) || str_contains($candidateName, $needle))) {
      $partialMatches[] = $candidate;
    }

    // Name-only entries are allowed for manual maintenance. For abbreviated
    // aliases, accept a close match only when its first three characters match
    // and no equally-close player exists.
    if ($candidateName !== '') {
      $distance = levenshtein($needle, $candidateName);
      if ($distance < $closestDistance) {
        $closestMatch = $candidate;
        $closestDistance = $distance;
        $closestThreshold = strlen($needle) >= 3 && substr($candidateName, 0, 3) === substr($needle, 0, 3)
          ? 3
          : (strlen($needle) >= 2 && substr($candidateName, 0, 2) === substr($needle, 0, 2) ? 2 : 1);
        $closestIsUnique = true;
      } elseif ($distance === $closestDistance) {
        $closestIsUnique = false;
      }
    }
  }

  // A name-only entry is accepted only when it identifies one player. This
  // preserves manual editing without silently assigning another player's score.
  if (count($partialMatches) === 1) {
    return $partialMatches[0];
  }

  return $closestIsUnique && $closestDistance <= $closestThreshold ? $closestMatch : null;
}

function enrichPlayerFromLeaderboard(array $player, array $leaderboardById, array $leaderboard): array
{
  $configuredId = trim((string) ($player['id'] ?? ''));
  $rankingPlayer = $configuredId !== ''
    ? ($leaderboardById[$configuredId] ?? null)
    : (isCompletePlaceholder((string) $player['name']) ? null : findLeaderboardPlayerByName((string) ($player['lookup'] ?? $player['name']), $leaderboard));

  if ($rankingPlayer === null) {
    return array_merge($player, [
      'points' => null,
      'score' => 0,
      'id' => $configuredId === '' ? null : $configuredId,
      'avatar_url' => null,
      'rankingName' => null,
    ]);
  }

  $points = is_numeric($rankingPlayer['points'] ?? null) ? $rankingPlayer['points'] : null;

  return array_merge($player, [
    // The configured name remains the label and rank membership; Ranking only
    // supplies the official ID and current points.
    'name' => (string) $player['name'],
    'points' => $points,
    'score' => $points === null ? 0 : max(0, $points),
    'id' => $rankingPlayer['id'],
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

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  $selected = isset($_POST['players']) && is_array($_POST['players']) ? $_POST['players'] : [];
  $rawSelected = $selected;
  $requestedMode = $_POST['draw_mode'] ?? 'rank';
  $drawMode = in_array($requestedMode, ['rank', 'ranking', 'vacancies'], true) ? $requestedMode : 'rank';
  $vacancyCount = min(10, max(1, (int) ($_POST['vacancies'] ?? 1)));

  if ($drawMode === 'vacancies') {
    if (count($selected) < 1) {
      $error = 'Selecione pelo menos 1 jogador para sortear as vagas.';
    } elseif ($vacancyCount > count($selected)) {
      $error = 'O número de vagas não pode ser maior que o número de jogadores selecionados.';
    } else {
      $vacancyPlayers = [];
      foreach ($selected as $entry) {
        [$rank, $id, $name, $lookup] = array_pad(explode('|', $entry, 4), 4, '');
        $vacancyPlayers[] = [
          'id' => $id === '' ? null : $id,
          'name' => $name,
          'lookup' => $lookup,
          'rank' => (int) $rank,
        ];
      }

      shuffle($vacancyPlayers);
      $winners = array_slice($vacancyPlayers, 0, $vacancyCount);
      $notSelected = array_slice($vacancyPlayers, $vacancyCount);
      $vacancyResult = [
        'winners' => $winners,
        'notSelected' => $notSelected,
        'total' => count($vacancyPlayers),
        'vacancies' => $vacancyCount,
      ];

      // ID exclusivo para identificar este sorteio ao vivo.
      $drawId = 'vac_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));

      // Publica imediatamente no Ao Vivo.
      // O sorteio de vagas NÃO é gravado no histórico.
      publishVacancyLiveState($rawSelected, $vacancyResult, $drawId);

      $_SESSION['vacancy_result'] = [
        'result' => $vacancyResult,
        'rawSelected' => $rawSelected,
        'drawMode' => 'vacancies',
        'drawId' => $drawId,
      ];

      header('Location: sorteio.php#resultado-vagas');
      exit;
    }
  } elseif (count($selected) !== 10) {
    $error = 'Selecione exatamente 10 jogadores! Você selecionou ' . count($selected) . '.';
  } else {
    $playerObjects = [];
    $leaderboard = $drawMode === 'ranking' ? fetchLeaderboard() : [];
    $leaderboardById = indexLeaderboardById($leaderboard);

    if ($drawMode === 'ranking' && $leaderboardById === []) {
      $error = 'Ranking indisponível. Tente novamente.';
    }

    foreach ($selected as $entry) {
      [$rank, $id, $name, $lookup] = array_pad(explode('|', $entry, 4), 4, '');
      $player = ['id' => $id === '' ? null : $id, 'name' => $name, 'lookup' => $lookup, 'rank' => (int) $rank, 'score' => (int) $rank];

      if ($drawMode === 'ranking') {
        $player = enrichPlayerFromLeaderboard($player, $leaderboardById, $leaderboard);
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

      // ID único para vincular o placar a este sorteio.
      $drawId = 'mix_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
      recordDrawHistory($teams, $rawSelected, $drawId);

      // Publica o resultado no Ao Vivo diretamente no servidor.
      // Isso evita uma condição de corrida com o update_selection
      // que pode acontecer enquanto a página de sorteio está mudando.
      publishTeamLiveState($rawSelected, $teams, $drawId);

      $_SESSION['draw_result'] = [
        'teams' => $teams,
        'rawSelected' => $rawSelected,
        'drawMode' => $drawMode,
        'drawId' => $drawId,
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
  $drawId = $drawResult['drawId'] ?? null;
}

if (isset($_SESSION['vacancy_result']) && $_SERVER['REQUEST_METHOD'] === 'GET') {
  $vacancySession = $_SESSION['vacancy_result'];
  unset($_SESSION['vacancy_result']);

  $vacancyResult = $vacancySession['result'] ?? $vacancySession;
  $vacancyRawSelected = $vacancySession['rawSelected'] ?? [];
  $vacancyDrawId = $vacancySession['drawId'] ?? null;

  $drawMode = 'vacancies';
  $vacancyCount = (int) ($vacancyResult['vacancies'] ?? 1);
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
  <link rel="icon" type="image/png" href="src/icon.png">

</head>

<body>

  <div class="container">
    <header class="site-header">

      <!-- LOGO TOPO -->

      <img src="src/logo.png" alt="Mix da Família GC" class="logo-topo">

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

      <h1>SORTEIO DE MIX</h1>
      <p id="drawDescription">Selecione 10 jogadores para gerar dois times equilibrados</p>

      <div class="draw-mode" aria-label="Modo de sorteio">
        <span class="draw-mode-label">Modo de sorteio</span>
        <div class="draw-mode-options">
          <button type="button" class="draw-mode-option<?= $drawMode === 'rank' ? ' active' : '' ?>" data-mode="rank" onclick="selectDrawMode('rank')"> Montar Times</button>
          <button type="button" class="draw-mode-option<?= $drawMode === 'ranking' ? ' active' : '' ?>" data-mode="ranking" onclick="selectDrawMode('ranking')"> Ranking por Pontuação</button>
          <button type="button" class="draw-mode-option<?= $drawMode === 'vacancies' ? ' active' : '' ?>" data-mode="vacancies" onclick="selectDrawMode('vacancies')"> Sortear Vagas</button>
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
          <div class="counter-num"><span id="selCount">0</span> <span id="selLimit">/ 10</span></div>
        </div>
        <div class="draw-actions">
          <div class="vacancy-control" id="vacancyControl" hidden>
            <label for="vacancies">Vagas disponíveis</label>
            <div class="vacancy-stepper">
              <button type="button" class="vacancy-step" onclick="changeVacancies(-1)" aria-label="Diminuir número de vagas">−</button>
              <div class="vacancy-value">
                <input type="number" name="vacancies" id="vacancies" min="1" max="10" value="<?= min(10, max(1, $vacancyCount)) ?>" aria-label="Número de vagas">
                <span>vagas</span>
              </div>
              <button type="button" class="vacancy-step" onclick="changeVacancies(1)" aria-label="Aumentar número de vagas">+</button>
            </div>
          </div>
          <button type="button" class="btn btn-ghost" onclick="clearAll()">Limpar</button>
          <button type="submit" class="btn btn-primary" id="sortSubmit">Sortear Times</button>
        </div>
      </div>

      <div class="ranks-grid" id="manualRanksGrid">
        <?php foreach ($players as $rank => $rankPlayers): ?>
          <div class="rank-card rank-<?= $rank ?>">
            <div class="rank-header">
              <span><?= $rankLabels[$rank]['icon'] ?></span>
              <span><?= $rankLabels[$rank]['label'] ?></span>
            </div>
            <div class="rank-players">
              <?php foreach ($rankPlayers as $player): ?>
                <?php $val = $rank . '|' . ($player['id'] ?? '') . '|' . $player['name'] . '|' . ($player['lookup'] ?? ''); ?>
                <label class="player-label" id="lbl-<?= md5($val) ?>" data-player-id="<?= htmlspecialchars((string) ($player['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-player-name="<?= htmlspecialchars($player['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" data-player-lookup="<?= htmlspecialchars((string) ($player['lookup'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-player-rank="<?= $rank ?>">
                  <input type="checkbox" name="players[]" value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>"
                    onchange="updateCounter(this)"
                    <?= (isset($_POST['players']) && in_array($val, $_POST['players'])) ? 'checked' : '' ?>>
                  <img class="player-avatar player-list-avatar" alt="" hidden>
                  <span class="player-name-text"><?= htmlspecialchars($player['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                  <span class="player-points" hidden></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="points-ranking-list" id="pointsRankingList" hidden aria-label="Jogadores ordenados por pontuação"></div>
    </form>

    <?php if ($vacancyResult): ?>
      <section class="vacancy-result results-section" id="resultado-vagas">
        <div class="vacancy-result-header">
          <div>
            <span class="vacancy-kicker">Resultado</span>
            <h2><?= $vacancyResult['vacancies'] ?> <?= $vacancyResult['vacancies'] === 1 ? 'JOGADOR SORTEADO' : 'JOGADORES SORTEADOS' ?></h2>
            <p><?= $vacancyResult['vacancies'] ?> vaga(s) sorteada(s) entre <?= $vacancyResult['total'] ?> jogador(es) selecionado(s).</p>
          </div>
          <button type="button" class="btn btn-primary" onclick="document.getElementById('sortForm').scrollIntoView({behavior:'smooth', block:'start'})">🎲 Sortear novamente</button>
        </div>
        <div class="vacancy-winners">
          <?php foreach ($vacancyResult['winners'] as $p): ?>
            <div class="vacancy-player winner">
              <span class="vacancy-check">✓</span>
              <span><?= htmlspecialchars($p['name']) ?></span>
              <span class="rank-pill rp-<?= $p['rank'] ?>">R<?= $p['rank'] ?></span>
            </div>
          <?php endforeach; ?>
        </div>
        <?php if (!empty($vacancyResult['notSelected'])): ?>
          <div class="vacancy-not-selected">
            <strong>Não sorteados</strong>
            <div>
              <?php foreach ($vacancyResult['notSelected'] as $p): ?>
                <span><?= htmlspecialchars($p['name']) ?></span>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      </section>
    <?php endif; ?>

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
                    <span class="player-score">Ranking por Pontuação: <?= $p['points'] === null ? 'Sem pontuação' : number_format((float) $p['points'], 0, ',', '.') . ' pts' ?></span>
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
                    <span class="player-score">Ranking por Pontuação: <?= $p['points'] === null ? 'Sem pontuação' : number_format((float) $p['points'], 0, ',', '.') . ' pts' ?></span>
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
        <button type="button" class="btn btn-ghost history-clear-button" onclick="clearHistory()">Limpar histórico</button>
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
    const manualRanksGrid = document.getElementById('manualRanksGrid');
    const pointsRankingList = document.getElementById('pointsRankingList');
    const vacancyControl = document.getElementById('vacancyControl');
    const vacanciesInput = document.getElementById('vacancies');
    const drawDescription = document.getElementById('drawDescription');
    const selLimit = document.getElementById('selLimit');
    let rankingReady = drawModeInput.value !== 'ranking';
    let leaderboardById = new Map();
    let leaderboardPlayers = [];

    function changeVacancies(delta) {
      const current = Number(vacanciesInput.value) || 1;
      const next = Math.min(10, Math.max(1, current + delta));
      vacanciesInput.value = next;
    }

    vacanciesInput.addEventListener('input', () => {
      if (vacanciesInput.value === '') return;
      const value = Math.min(10, Math.max(1, Number(vacanciesInput.value) || 1));
      vacanciesInput.value = value;
    });

    function normalizePlayerName(name) {
      return String(name || '')
        .replace(/\$/g, 's')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]/g, '')
        .replace(/(.)\1+/g, '$1');
    }

    function isCompletePlaceholder(name) {
      return /^COMPLETE\s+\d+$/i.test(String(name || '').trim());
    }

    function findRankingPlayerByName(name) {
      const needle = normalizePlayerName(name);
      if (!needle || isCompletePlaceholder(name)) return null;

      const partialMatches = [];
      let closestMatch = null;
      let closestDistance = Infinity;
      let closestThreshold = 0;
      let closestIsUnique = true;
      for (const player of leaderboardPlayers) {
        const candidateName = normalizePlayerName(player.name);
        if (candidateName === needle) return player;
        if (candidateName && (candidateName.startsWith(needle) || candidateName.includes(needle))) {
          partialMatches.push(player);
        }
        if (candidateName) {
          const distance = levenshteinDistance(needle, candidateName);
          if (distance < closestDistance) {
            closestMatch = player;
            closestDistance = distance;
            closestThreshold = needle.length >= 3 && candidateName.slice(0, 3) === needle.slice(0, 3) ?
              3 :
              (needle.length >= 2 && candidateName.slice(0, 2) === needle.slice(0, 2) ? 2 : 1);
            closestIsUnique = true;
          } else if (distance === closestDistance) {
            closestIsUnique = false;
          }
        }
      }
      if (partialMatches.length === 1) return partialMatches[0];
      return closestIsUnique && closestDistance <= closestThreshold ? closestMatch : null;
    }

    function levenshteinDistance(left, right) {
      const row = Array.from({
        length: right.length + 1
      }, (_, index) => index);
      for (let leftIndex = 1; leftIndex <= left.length; leftIndex++) {
        let previous = row[0];
        row[0] = leftIndex;
        for (let rightIndex = 1; rightIndex <= right.length; rightIndex++) {
          const current = row[rightIndex];
          row[rightIndex] = Math.min(
            row[rightIndex] + 1,
            row[rightIndex - 1] + 1,
            previous + (left[leftIndex - 1] === right[rightIndex - 1] ? 0 : 1)
          );
          previous = current;
        }
      }
      return row[right.length];
    }

    function updateRankingPlayerList() {
      document.querySelectorAll('.player-label').forEach(label => {
        const configuredId = label.dataset.playerId;
        const player = configuredId ?
          leaderboardById.get(configuredId) :
          findRankingPlayerByName(label.dataset.playerLookup || label.dataset.playerName);
        const avatar = label.querySelector('.player-list-avatar');
        const name = label.querySelector('.player-name-text');
        const points = label.querySelector('.player-points');
        const checkbox = label.querySelector('input[type="checkbox"]');

        if (drawModeInput.value !== 'ranking') {
          if (player?.avatar_url) {
            avatar.src = player.avatar_url;
            avatar.hidden = false;
            avatar.onerror = () => {
              avatar.hidden = true;
            };
          } else {
            avatar.hidden = true;
          }
          points.hidden = true;
          name.textContent = label.dataset.playerName;
          return;
        }

        name.textContent = label.dataset.playerName;
        if (player?.id && !configuredId) {
          label.dataset.playerId = String(player.id);
          checkbox.value = `${label.dataset.playerRank}|${player.id}|${label.dataset.playerName}|${label.dataset.playerLookup || ''}`;
        }
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
          points.textContent = `${Number(player.points).toLocaleString('pt-BR')} pts`;
          points.hidden = false;
        } else {
          points.textContent = 'Sem pontuação';
          points.hidden = false;
        }
      });

      if (drawModeInput.value === 'ranking') {
        renderPointRanking();
      }
    }

    function rankingPlayerForLabel(label) {
      return label.dataset.playerId ?
        leaderboardById.get(label.dataset.playerId) :
        findRankingPlayerByName(label.dataset.playerLookup || label.dataset.playerName);
    }

    function renderPointRanking() {
      const labels = Array.from(document.querySelectorAll('.player-label'));
      labels.forEach((label, index) => {
        if (!label._manualParent) {
          label._manualParent = label.parentElement;
          label._manualIndex = index;
        }
      });

      labels.sort((left, right) => {
        const leftPoints = Number(rankingPlayerForLabel(left)?.points);
        const rightPoints = Number(rankingPlayerForLabel(right)?.points);
        const leftScore = Number.isFinite(leftPoints) ? leftPoints : -1;
        const rightScore = Number.isFinite(rightPoints) ? rightPoints : -1;
        return rightScore - leftScore || left.dataset.playerName.localeCompare(right.dataset.playerName, 'pt-BR');
      }).forEach(label => pointsRankingList.appendChild(label));

      manualRanksGrid.hidden = true;
      pointsRankingList.hidden = false;
    }

    function restoreManualRanks() {
      const labelsByParent = new Map();
      Array.from(pointsRankingList.querySelectorAll('.player-label')).forEach(label => {
        const labels = labelsByParent.get(label._manualParent) || [];
        labels.push(label);
        labelsByParent.set(label._manualParent, labels);
      });
      labelsByParent.forEach((labels, parent) => {
        labels.sort((left, right) => left._manualIndex - right._manualIndex).forEach(label => parent.appendChild(label));
      });

      pointsRankingList.hidden = true;
      manualRanksGrid.hidden = false;
    }

    function selectDrawMode(mode) {
      drawModeInput.value = ['rank', 'ranking', 'vacancies'].includes(mode) ? mode : 'rank';
      document.querySelectorAll('.draw-mode-option').forEach(button => {
        button.classList.toggle('active', button.dataset.mode === drawModeInput.value);
      });

      const vacancyMode = drawModeInput.value === 'vacancies';
      vacancyControl.hidden = !vacancyMode;
      vacancyControl.style.display = vacancyMode ? 'flex' : 'none';
      selLimit.textContent = vacancyMode ? '/ livre' : '/ 10';
      drawDescription.textContent = vacancyMode ?
        'Selecione os jogadores que concorrem às vagas e deixe o sistema sortear quem entra.' :
        'Selecione 10 jogadores para gerar dois times equilibrados';
      sortSubmit.textContent = vacancyMode ? 'Sortear Jogadores' : 'Sortear Times';

      if (drawModeInput.value === 'ranking') {
        renderPointRanking();
        loadRankingForDraw();
      } else {
        rankingReady = true;
        restoreManualRanks();
        updateRankingPlayerList();
        loadPlayerAvatars();
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
        const response = await fetch('ranking.php?api=1', {
          cache: 'no-store',
          headers: {
            'Accept': 'application/json'
          }
        });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);

        const data = await response.json();
        const leaderboard = Array.isArray(data) ? data : (data.players || data.leaderboard || []);
        if (!leaderboard.length) throw new Error('empty leaderboard');

        leaderboardById = new Map(
          leaderboard
          .filter(player => player && player.id !== null && player.id !== undefined && player.id !== '')
          .map(player => [String(player.id), player])
        );
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

    // The normal rank draw keeps its manual ranking rules, but still loads the
    // official player directory so its cards can show the same avatars.
    async function loadPlayerAvatars() {
      try {
        const response = await fetch('ranking.php?api=1', {
          cache: 'no-store',
          headers: {
            'Accept': 'application/json'
          }
        });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);

        const data = await response.json();
        const leaderboard = Array.isArray(data) ? data : (data.players || data.leaderboard || []);
        leaderboardById = new Map(
          leaderboard
          .filter(player => player && player.id !== null && player.id !== undefined && player.id !== '')
          .map(player => [String(player.id), player])
        );
        leaderboardPlayers = leaderboard;
        updateRankingPlayerList();
      } catch (error) {
        console.error('Erro ao carregar avatares para o sorteio:', error);
      }
    }

    sortForm.addEventListener('submit', event => {
      // Evita que o update_selection agendado sobrescreva o resultado final
      // no live_state.json depois que o sorteio for enviado.
      clearTimeout(liveTimer);

      const checkedCount = document.querySelectorAll('input[type="checkbox"]:checked').length;

      if (drawModeInput.value === 'ranking' && !rankingReady) {
        event.preventDefault();
        alert('Ranking indisponível. Tente novamente.');
        return;
      }

      if (drawModeInput.value === 'vacancies') {
        const vacancies = Number(vacanciesInput.value);
        if (checkedCount < 1) {
          event.preventDefault();
          alert('Selecione pelo menos 1 jogador.');
          return;
        }
        if (!Number.isInteger(vacancies) || vacancies < 1 || vacancies > 10 || vacancies > checkedCount) {
          event.preventDefault();
          alert(`Informe entre 1 e ${checkedCount} vaga(s).`);
          return;
        }
        return;
      }

      if (checkedCount !== 10) {
        event.preventDefault();
        alert(`Selecione exatamente 10 jogadores. Você selecionou ${checkedCount}.`);
      }
    });

    selectDrawMode(drawModeInput.value);

    function filterPlayers(query) {
      const normalize = (str) => str
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, ''); // remove acentos

      const term = normalize(query.trim());

      if (drawModeInput.value === 'ranking') {
        pointsRankingList.querySelectorAll('.player-label').forEach(label => {
          const matches = term === '' || normalize(label.textContent).includes(term);
          label.style.display = matches ? '' : 'none';
        });
        return;
      }

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
            selected: checked,
            draw_mode: drawModeInput.value,
            vacancy_count: Number(vacanciesInput.value) || 1
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

      if (drawModeInput.value !== 'vacancies' && checked.length > 10) {
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

  <script src="history-clear.js"></script>

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