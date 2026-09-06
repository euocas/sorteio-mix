<?php
require_once __DIR__ . '/leaderboard.php';

const SEASONS_FILE = __DIR__ . '/data/seasons.json';

function seasonReadAll($handle): array
{
  rewind($handle);
  $content = stream_get_contents($handle);
  $data = json_decode($content ?: '', true);
  return is_array($data) ? $data : [];
}

function seasonMonthKey(DateTimeImmutable $date): string
{
  return $date->format('Y-m');
}

function seasonLabel(int $year, int $month): string
{
  $months = [
    1 => 'Janeiro',
    2 => 'Fevereiro',
    3 => 'Marco',
    4 => 'Abril',
    5 => 'Maio',
    6 => 'Junho',
    7 => 'Julho',
    8 => 'Agosto',
    9 => 'Setembro',
    10 => 'Outubro',
    11 => 'Novembro',
    12 => 'Dezembro',
  ];

  return ($months[$month] ?? 'Mes') . ' ' . $year;
}

function seasonSnapshot(array $players, int $year, int $month, string $key, string $createdAt): array
{
  usort($players, static function (array $left, array $right): int {
    return (float) ($right['points'] ?? 0) <=> (float) ($left['points'] ?? 0);
  });

  $snapshotPlayers = [];
  foreach ($players as $index => $player) {
    if (!is_array($player) || !isset($player['name']) || !is_numeric($player['points'] ?? null)) {
      continue;
    }

    $snapshotPlayers[] = [
      'position' => count($snapshotPlayers) + 1,
      'id' => $player['steam_id'] ?? null,
      'name' => (string) $player['name'],
      'avatar' => $player['avatar_url'] ?? null,
      'rank' => $player['rank'] ?? null,
      'points' => $player['points'],
      'kills' => $player['kills'] ?? null,
      'deaths' => $player['deaths'] ?? null,
      'headshots' => $player['headshots'] ?? null,
      'hs_pct' => $player['hs_pct'] ?? null,
    ];
  }

  return [
    'key' => $key,
    'name' => seasonLabel($year, $month),
    'year' => $year,
    'month' => $month,
    'created_at' => $createdAt,
    'updated_at' => $createdAt,
    'closed' => false,
    'players' => $snapshotPlayers,
  ];
}

function seasonWriteAll($handle, array $seasons): bool
{
  ftruncate($handle, 0);
  rewind($handle);
  $written = fwrite($handle, json_encode($seasons, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  fflush($handle);
  return $written !== false;
}

function synchronizeCurrentSeason(): array
{
  if (!is_dir(dirname(SEASONS_FILE))) {
    @mkdir(dirname(SEASONS_FILE), 0755, true);
  }

  $players = fetchLeaderboard();
  if ($players === []) {
    return seasonReadStored();
  }

  $now = new DateTimeImmutable('now');
  $key = seasonMonthKey($now);
  $handle = fopen(SEASONS_FILE, 'c+');
  if (!$handle) {
    return seasonReadStored();
  }

  flock($handle, LOCK_EX);
  $seasons = seasonReadAll($handle);
  $timestamp = $now->format(DateTimeInterface::ATOM);

  foreach ($seasons as $oldKey => &$season) {
    if ($oldKey !== $key && !empty($season['closed'])) {
      continue;
    }
    if ($oldKey !== $key) {
      $season['closed'] = true;
    }
  }
  unset($season);

  if (!isset($seasons[$key]) || empty($seasons[$key]['closed'])) {
    $createdAt = $seasons[$key]['created_at'] ?? $timestamp;
    $seasons[$key] = seasonSnapshot($players, (int) $now->format('Y'), (int) $now->format('n'), $key, $createdAt);
    $seasons[$key]['updated_at'] = $timestamp;
  }

  seasonWriteAll($handle, $seasons);
  flock($handle, LOCK_UN);
  fclose($handle);

  return $seasons;
}

function seasonReadStored(): array
{
  if (!file_exists(SEASONS_FILE)) {
    return [];
  }

  $handle = fopen(SEASONS_FILE, 'r');
  if (!$handle) {
    return [];
  }

  flock($handle, LOCK_SH);
  $seasons = seasonReadAll($handle);
  flock($handle, LOCK_UN);
  fclose($handle);
  return $seasons;
}
