<?php
/**
 * live_state.php
 *
 * GET  ?action=state
 * GET  ?action=history
 * POST action=update_selection
 * POST action=finish_draw
 * POST action=finish_vacancy
 * POST action=finish_match
 * POST action=finish_map_draw
 * POST action=reset
 */

header('Content-Type: application/json; charset=utf-8');

$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) {
    @mkdir($dataDir, 0755, true);
}

$liveFile = $dataDir . '/live_state.json';
$historyFile = $dataDir . '/history.json';

const HISTORY_CLEAR_PASSWORD_HASH = '$2y$10$wpg2hM9IOJh9GU7npTkklu8OL//AOY5FCjH4MTSpAIETCdCiOgyQO';

function readJsonFile(string $file, $default)
{
    if (!file_exists($file)) return $default;

    $fp = fopen($file, 'r');
    if (!$fp) return $default;

    flock($fp, LOCK_SH);
    $content = stream_get_contents($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    $data = json_decode($content, true);
    return $data === null ? $default : $data;
}

function writeJsonFile(string $file, $data): bool
{
    $fp = fopen($file, 'c');
    if (!$fp) return false;

    flock($fp, LOCK_EX);
    ftruncate($fp, 0);
    rewind($fp);
    $ok = fwrite($fp, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    return $ok !== false;
}

function emptyState(string $status = 'idle'): array
{
    return [
        'status' => $status,
        'selected' => [],
        'teams' => null,
        'maps' => null,
        'match' => null,
        'vacancy_result' => null,
        'draw_mode' => null,
        'vacancy_count' => null,
        'draw_id' => null,
        'updated_at' => date('c'),
    ];
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'state';

    if ($action === 'history') {
        $history = readJsonFile($historyFile, []);
        echo json_encode([
            'ok' => true,
            'history' => array_reverse($history)
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $state = readJsonFile($liveFile, emptyState());
    echo json_encode(['ok' => true, 'state' => $state], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);

    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'payload inválido']);
        exit;
    }

    $action = $input['action'] ?? '';

    if ($action === 'update_selection') {
        $selected = $input['selected'] ?? [];
        $drawMode = in_array(($input['draw_mode'] ?? 'rank'), ['rank', 'ranking', 'vacancies'], true)
            ? $input['draw_mode']
            : 'rank';
        $vacancyCount = min(10, max(1, (int) ($input['vacancy_count'] ?? 1)));

        $state = [
            'status' => 'selecting',
            'selected' => array_values(array_map('strval', $selected)),
            'teams' => null,
            'maps' => null,
            'match' => null,
            'vacancy_result' => null,
            'draw_mode' => $drawMode,
            'vacancy_count' => $drawMode === 'vacancies' ? $vacancyCount : null,
            'draw_id' => null,
            'updated_at' => date('c'),
        ];

        writeJsonFile($liveFile, $state);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'clear_history') {
        $password = (string) ($input['password'] ?? '');

        if (!password_verify($password, HISTORY_CLEAR_PASSWORD_HASH)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'senha incorreta']);
            exit;
        }

        if (!writeJsonFile($historyFile, [])) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'não foi possível limpar o histórico']);
            exit;
        }

        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'finish_draw') {
        $teams = $input['teams'] ?? null;
        $selected = $input['selected'] ?? [];
        $drawId = trim((string) ($input['draw_id'] ?? ''));

        if (!$teams) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'faltando "teams"']);
            exit;
        }

        $now = date('c');

        $state = [
            'status' => 'done',
            'selected' => array_values(array_map('strval', $selected)),
            'teams' => $teams,
            'maps' => null,
            'match' => [
                'status' => 'pending',
                'score1' => null,
                'score2' => null,
                'winner' => null,
            ],
            'draw_id' => $drawId !== '' ? $drawId : null,
            'updated_at' => $now,
        ];

        writeJsonFile($liveFile, $state);

        // Só cria histórico aqui para chamadas antigas que não vieram do sorteio.php.
        if (($input['record_history'] ?? true) !== false) {
            $history = readJsonFile($historyFile, []);
            $history[] = [
                'id' => $drawId !== ''
                    ? $drawId
                    : 'mix_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)),
                'date' => $now,
                'teams' => $teams,
                'match' => [
                    'status' => 'pending',
                    'score1' => null,
                    'score2' => null,
                    'winner' => null,
                ],
            ];
            writeJsonFile($historyFile, $history);
        }

        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'finish_vacancy') {
        $selected = $input['selected'] ?? [];
        $vacancyResult = $input['vacancy_result'] ?? null;
        $drawId = trim((string) ($input['draw_id'] ?? ''));

        if (!is_array($vacancyResult)
            || !isset($vacancyResult['winners'])
            || !is_array($vacancyResult['winners'])
            || !isset($vacancyResult['notSelected'])
            || !is_array($vacancyResult['notSelected'])
            || !isset($vacancyResult['vacancies'])
        ) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'resultado de vagas inválido']);
            exit;
        }

        $now = date('c');

        $state = [
            'status' => 'vacancies_done',
            'selected' => array_values(array_map('strval', $selected)),
            'teams' => null,
            'maps' => null,
            'match' => null,
            'vacancy_result' => $vacancyResult,
            'draw_mode' => 'vacancies',
            'vacancy_count' => min(10, max(1, (int) $vacancyResult['vacancies'])),
            'draw_id' => $drawId !== '' ? $drawId : null,
            'updated_at' => $now,
        ];

        writeJsonFile($liveFile, $state);

        // Sorteio de vagas não entra no histórico.
        echo json_encode(['ok' => true, 'draw_id' => $state['draw_id']], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'finish_match') {
        $score1 = filter_var($input['score1'] ?? null, FILTER_VALIDATE_INT);
        $score2 = filter_var($input['score2'] ?? null, FILTER_VALIDATE_INT);
        $drawId = trim((string) ($input['draw_id'] ?? ''));

        if ($score1 === false || $score2 === false || $score1 < 0 || $score2 < 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'placar inválido']);
            exit;
        }

        $winner = $score1 === $score2
            ? 'EMPATE'
            : ($score1 > $score2 ? 'TIME 1' : 'TIME 2');

        $match = [
            'status' => 'finished',
            'score1' => (int) $score1,
            'score2' => (int) $score2,
            'winner' => $winner,
            'updated_at' => date('c'),
        ];

        $state = readJsonFile($liveFile, emptyState());
        $state['match'] = $match;
        $state['updated_at'] = date('c');

        if ($drawId !== '') {
            $state['draw_id'] = $drawId;
        }

        writeJsonFile($liveFile, $state);

        $history = readJsonFile($historyFile, []);
        $historyIndex = -1;

        if ($drawId !== '') {
            foreach ($history as $index => $entry) {
                if ((string) ($entry['id'] ?? '') === $drawId) {
                    $historyIndex = $index;
                    break;
                }
            }
        }

        // Compatibilidade com históricos antigos que ainda não possuem ID.
        if ($historyIndex < 0 && !empty($history)) {
            $historyIndex = count($history) - 1;
        }

        if ($historyIndex >= 0) {
            $history[$historyIndex]['match'] = $match;
            writeJsonFile($historyFile, $history);
        }

        echo json_encode([
            'ok' => true,
            'match' => $match,
            'draw_id' => $drawId
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'finish_map_draw') {
        $maps = $input['maps'] ?? null;

        if (!is_array($maps) || empty($maps['selected']) || !is_array($maps['selected'])) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'faltando "maps.selected"']);
            exit;
        }

        $state = readJsonFile($liveFile, emptyState());

        $state['maps'] = [
            'status' => 'done',
            'selected' => array_values($maps['selected']),
        ];
        $state['updated_at'] = date('c');

        writeJsonFile($liveFile, $state);

        $history = readJsonFile($historyFile, []);
        $historyIndex = -1;
        $drawId = trim((string) ($state['draw_id'] ?? ''));

        if ($drawId !== '') {
            foreach ($history as $index => $entry) {
                if ((string) ($entry['id'] ?? '') === $drawId) {
                    $historyIndex = $index;
                    break;
                }
            }
        }

        if ($historyIndex < 0 && !empty($history)) {
            $historyIndex = count($history) - 1;
        }

        if ($historyIndex >= 0) {
            $history[$historyIndex]['maps'] = [
                'status' => 'done',
                'selected' => array_values($maps['selected']),
            ];
            writeJsonFile($historyFile, $history);
        }

        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'reset') {
        writeJsonFile($liveFile, emptyState('idle'));
        echo json_encode(['ok' => true]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'ação desconhecida']);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'método não permitido']);
