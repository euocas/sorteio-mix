<?php
const LEADERBOARD_API = 'https://mix-dashboard-ho50.onrender.com/api/leaderboard';

function fetchLeaderboard(): array
{
  $json = false;

  if (function_exists('curl_init')) {
    $ch = curl_init(LEADERBOARD_API);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_CONNECTTIMEOUT => 8,
      CURLOPT_TIMEOUT => 15,
      CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'User-Agent: Mix-Ranking/1.0'
      ],
    ]);

    $json = curl_exec($ch);
    curl_close($ch);
  }

  if ($json === false || $json === null) {
    $context = stream_context_create([
      'http' => [
        'method' => 'GET',
        'timeout' => 15,
        'header' => "Accept: application/json\r\nUser-Agent: Mix-Ranking/1.0\r\n"
      ]
    ]);

    $json = @file_get_contents(LEADERBOARD_API, false, $context);
  }

  if (!$json) {
    return [];
  }

  $data = json_decode($json, true);

  if (!is_array($data)) {
    return [];
  }

  $players = $data['players'] ?? $data['leaderboard'] ?? $data;
  if (!is_array($players)) {
    return [];
  }

  // The ranking API is the canonical player source.  Normalize its identifier
  // once here so every consumer (Ranking and Sorteio) uses the same key.
  return array_values(array_map(static function ($player): array {
    $player = is_array($player) ? $player : [];
    $id = $player['id'] ?? $player['steam_id'] ?? $player['steamId'] ?? $player['steamid'] ?? null;
    $player['id'] = $id === null || $id === '' ? null : (string) $id;
    return $player;
  }, $players));
}

function indexLeaderboardById(array $players): array
{
  $indexed = [];
  foreach ($players as $player) {
    if (!is_array($player) || empty($player['id'])) {
      continue;
    }
    $indexed[(string) $player['id']] = $player;
  }
  return $indexed;
}
