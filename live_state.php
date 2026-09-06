<?php

/**
 * live_state.php
 * Endpoint de dados para o sorteio "ao vivo".
 *
 * GET  ?action=state    -> retorna o estado atual do sorteio em andamento
 * GET  ?action=history  -> retorna o histórico de sorteios já concluídos
 * POST action=update_selection -> host envia quais jogadores estão marcados agora
 * POST action=finish_draw      -> host envia o resultado final (times) e ele é gravado no histórico
 * POST action=finish_map_draw  -> host envia o resultado atual dos mapas
 * POST action=reset            -> host limpa o estado ao vivo para começar um novo sorteio
 *
 * Todo o estado fica em arquivos JSON dentro de /data (não em banco de dados),
 * então não precisa de nenhuma configuração extra além de permissão de escrita na pasta.
 */

header('Content-Type: application/json; charset=utf-8');

$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) {
    @mkdir($dataDir, 0755, true);
}

$liveFile    = $dataDir . '/live_state.json';
$historyFile = $dataDir . '/history.json';

function readJsonFile(string $file, $default)
{
    if (!file_exists($file)) {
        return $default;
    }
    $fp = fopen($file, 'r');
    if (!$fp) {
        return $default;
    }
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
    if (!$fp) {
        return false;
    }
    flock($fp, LOCK_EX);
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return true;
}

function emptyState(string $status = 'idle'): array
{
    return [
        'status'     => $status,   // idle | selecting | done
        'selected'   => [],
        'teams'      => null,
        'maps'       => null,
        'updated_at' => date('c'),
    ];
}

$method = $_SERVER['REQUEST_METHOD'];

// ---------- GET ----------
if ($method === 'GET') {
    $action = $_GET['action'] ?? 'state';

    if ($action === 'history') {
        $history = readJsonFile($historyFile, []);
        $history = array_reverse($history); // mais recente primeiro
        echo json_encode(['ok' => true, 'history' => $history], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $state = readJsonFile($liveFile, emptyState());
    echo json_encode(['ok' => true, 'state' => $state], JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------- POST ----------
if ($method === 'POST') {
    $raw   = file_get_contents('php://input');
    $input = json_decode($raw, true);

    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'payload inválido']);
        exit;
    }

    $action = $input['action'] ?? '';

    if ($action === 'update_selection') {
        $selected = $input['selected'] ?? [];
        $state = [
            'status'     => 'selecting',
            'selected'   => array_values(array_map('strval', $selected)),
            'teams'      => null,
            'maps'       => null,
            'updated_at' => date('c'),
        ];
        writeJsonFile($liveFile, $state);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'finish_draw') {
        $teams    = $input['teams'] ?? null;
        $selected = $input['selected'] ?? [];

        if (!$teams) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'faltando "teams"']);
            exit;
        }

        $now = date('c');

        $state = [
            'status'     => 'done',
            'selected'   => array_values(array_map('strval', $selected)),
            'teams'      => $teams,
            'maps'       => null,
            'updated_at' => $now,
        ];
        writeJsonFile($liveFile, $state);

        // sorteio.php pode registrar o histórico no servidor antes do redirect.
        // Chamadas antigas continuam registrando por padrão.
        if (($input['record_history'] ?? true) !== false) {
            $history   = readJsonFile($historyFile, []);
            $history[] = [
                'date'  => $now,
                'teams' => $teams,
            ];
            writeJsonFile($historyFile, $history);
        }

        echo json_encode(['ok' => true]);
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

        // Vincula o resultado do mapa ao último sorteio de times salvo.
        $history = readJsonFile($historyFile, []);
        if (!empty($history)) {
            $historyIndex = count($history) - 1;
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
