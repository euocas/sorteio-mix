<?php
require_once __DIR__ . '/leaderboard.php';

// Endpoint interno para o JavaScript.
// Ex.: ranking.php?api=1
if (isset($_GET['api'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    $players = fetchLeaderboard();

    echo json_encode($players, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ranking</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/png" href="src/icon.png">

    <style>
        /* =========================================================
           RANKING — estilos isolados para não interferir nas outras páginas
           ========================================================= */

        .ranking-page {
            min-height: 100vh;
        }

        .ranking-page .ranking-wrap {
            width: min(1100px, calc(100% - 32px));
            margin: 0 auto;
            padding: 0 0 70px;
        }

        .ranking-page .ranking-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .ranking-page .ranking-title {
            margin: 0;
            color: #fff;
            font-size: clamp(28px, 3vw, 38px);
            font-weight: 900;
            letter-spacing: -1.2px;
            text-transform: uppercase;
        }

        .ranking-page .ranking-subtitle {
            margin: 9px 0 0;
            color: #8f8f8f;
            font-size: 14px;
        }

        .ranking-page .ranking-status {
            margin-top: 8px;
            color: #666;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .1em;
            text-transform: uppercase;
        }

        .ranking-page .ranking-status.online {
            color: #7f7f7f;
        }

        .ranking-page .ranking-status.error {
            color: #f47b20;
        }

        .ranking-page .ranking-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            margin-bottom: 18px;
            padding-top: 2px;
        }

        .ranking-page .ranking-label {
            color: #777;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .14em;
            text-transform: uppercase;
        }

        .ranking-page .ranking-search {
            width: 240px;
            padding: 11px 13px;
            background: #101010;
            color: #fff;
            border: 1px solid #292929;
            border-radius: 0;
            outline: none;
            font: inherit;
        }

        .ranking-page .ranking-search:focus {
            border-color: #f47b20;
        }

        .ranking-page .ranking-table {
            width: 100%;
            border-collapse: collapse;
            background: #0d0d0d;
            border: 1px solid #262626;
        }

        .ranking-page .ranking-table th {
            padding: 14px 16px;
            color: #737373;
            background: #111;
            border-bottom: 1px solid #292929;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .13em;
            text-align: left;
            text-transform: uppercase;
        }

        .ranking-page .ranking-table td {
            padding: 15px 16px;
            color: #ddd;
            border-bottom: 1px solid #202020;
            font-size: 14px;
        }

        .ranking-page .ranking-table tbody tr {
            transition: background .15s ease;
        }

        .ranking-page .ranking-table tbody tr:hover {
            background: #151515;
        }

        .ranking-page .ranking-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .ranking-page .position {
            width: 65px;
            color: #777;
            font-weight: 900;
        }

        .ranking-page .top-position {
            color: #f47b20;
        }

        .ranking-page .player-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .ranking-page .player-avatar {
            width: 34px;
            height: 34px;
            display: grid;
            place-items: center;
            background: #191919;
            border: 1px solid #303030;
            color: #aaa;
            font-size: 12px;
            font-weight: 900;
        }

        .ranking-page .player-name {
            color: #fff;
            font-weight: 800;
        }

        .ranking-page .player-avatar-img {
            object-fit: cover;
            border-radius: 0;
        }

        .ranking-page .player-rank {
            margin-top: 3px;
            color: #777;
            font-size: 10px;
            font-weight: 700;
        }

        .ranking-page .ranking-error {
            padding: 28px 16px !important;
            color: #f47b20 !important;
            text-align: center;
        }

        .ranking-page .points {
            color: #f47b20;
            font-weight: 900;
        }

        .ranking-page .win {
            color: #ddd;
        }

        .ranking-page .loss {
            color: #777;
        }

        .ranking-page .rate {
            font-weight: 800;
        }

        .ranking-page .top-card {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }

        .ranking-page .top-player {
            min-width: 0;
            padding: 20px;
            background: #0d0d0d;
            border: 1px solid #292929;
            position: relative;
        }

        .ranking-page .top-player:first-child {
            border-color: #f47b20;
        }

        .ranking-page .top-number {
            color: #f47b20;
            font-size: 11px;
            font-weight: 900;
            letter-spacing: .12em;
            text-transform: uppercase;
        }

        .ranking-page .top-name {
            margin-top: 10px;
            color: #fff;
            font-size: 21px;
            font-weight: 900;
        }

        .ranking-page .top-points {
            margin-top: 5px;
            color: #888;
            font-size: 12px;
        }

        .ranking-page .empty {
            padding: 35px;
            color: #777;
            text-align: center;
        }

        @media (max-width: 760px) {
            .ranking-page .ranking-wrap {
                width: 100%;
                padding-top: 0;
            }

            .ranking-page .top-card {
                grid-template-columns: 1fr;
            }

            .ranking-page .ranking-status {
                margin-top: 8px;
                color: #666;
                font-size: 10px;
                font-weight: 800;
                letter-spacing: .1em;
                text-transform: uppercase;
            }

            .ranking-page .ranking-status.online {
                color: #7f7f7f;
            }

            .ranking-page .ranking-status.error {
                color: #f47b20;
            }

            .ranking-page .ranking-toolbar {
                align-items: stretch;
                flex-direction: column;
            }

            .ranking-page .ranking-search {
                width: 100%;
                box-sizing: border-box;
            }

            .ranking-page .ranking-table {
                min-width: 700px;
            }

            .ranking-page .table-scroll {
                overflow-x: auto;
                border: 1px solid #262626;
            }

            .ranking-page .ranking-table {
                border: 0;
            }
        }
    </style>
</head>

<body class="ranking-page">

    <div class="container ranking-wrap">

        <header class="ranking-header site-header">

            <!-- LOGO TOPO -->

      <img src="src/logo.png" alt="Mix da Família GC" class="logo-topo">
            <nav class="tabs">
                <button type="button" class="btn btn-ghost" onclick="window.location.href='index.html'">Home</button>
                <button type="button" class="btn btn-ghost" onclick="window.location.href='sorteio.php'">Sorteio</button>
                <button type="button" class="btn btn-ghost" onclick="window.location.href='sorteio_de_mapas.html'">Mapa</button>
                <button type="button" class="btn btn-ghost" onclick="window.location.href='ao_vivo.php'">Ao Vivo</button>
                <button type="button" class="btn btn-ghost" onclick="window.location.href='historico.php'">Histórico</button>
                <button type="button" class="btn btn-ghost active" onclick="window.location.href='ranking.php'">Ranking</button>
                <button type="button" class="btn btn-ghost" onclick="window.location.href='temporadas.php'">Temporadas</button>
            </nav>

            <h1 class="ranking-title">RANKING</h1>
            <p class="ranking-subtitle">Pontuação acumulada dos jogadores registrados no servidor.</p>
            <div id="rankingStatus" class="ranking-status">Conectando ao servidor...</div>
        </header>


        <section id="topCard" class="top-card" aria-label="Top 3 jogadores"></section>

        <div class="ranking-toolbar">
            <span class="ranking-label">Classificação geral</span>
            <input
                id="rankingSearch"
                class="ranking-search"
                type="search"
                placeholder="Buscar jogador..."
                autocomplete="off">
        </div>

        <div class="table-scroll">
            <table class="ranking-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Jogador</th>
                        <th>Pontos</th>
                        <th>Kills</th>
                        <th>Deaths</th>
                        <th>Headshots</th>
                        <th>HS%</th>
                    </tr>
                </thead>

                <tbody id="rankingBody"></tbody>
            </table>

            <div id="rankingEmpty" class="empty" hidden>
                Nenhum jogador encontrado.
            </div>
        </div>

    </div>

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

    <script>
        const RANKING_API = 'ranking.php?api=1';

        const search = document.getElementById('rankingSearch');
        const body = document.getElementById('rankingBody');
        const topCard = document.getElementById('topCard');
        const empty = document.getElementById('rankingEmpty');
        const status = document.getElementById('rankingStatus');

        let players = [];

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function number(value) {
            return Number(value || 0).toLocaleString('pt-BR');
        }

        function winRate(player) {
            const kills = Number(player.kills || 0);
            const deaths = Number(player.deaths || 0);

            // O endpoint fornecido não traz partidas/vitórias.
            // Por isso, exibimos K/D e HS com os dados que realmente existem.
            return deaths > 0 ? (kills / deaths).toFixed(2) : kills.toFixed(2);
        }

        function renderTop() {
            topCard.innerHTML = players.slice(0, 3).map((player, index) => `
                <article class="top-player">
                    <div class="top-number">${index + 1}º LUGAR</div>
                    <div class="top-name">${escapeHtml(player.name)}</div>
                    <div class="top-points">${number(player.points)} pontos</div>
                </article>
            `).join('');
        }

        function renderTable() {
            const term = search.value.trim().toLowerCase();

            const filtered = players.filter(player =>
                String(player.name || '').toLowerCase().includes(term)
            );

            body.innerHTML = filtered.map(player => {
                const position = players.indexOf(player) + 1;
                const avatar = player.avatar_url || '';
                const initial = String(player.name || '?').trim().charAt(0).toUpperCase();

                return `
                    <tr data-player="${escapeHtml(String(player.name || '').toLowerCase())}">
                        <td class="position ${position <= 3 ? 'top-position' : ''}">
                            ${position}
                        </td>
                        <td>
                            <div class="player-cell">
                                ${avatar
                                    ? `<img class="player-avatar player-avatar-img"
                                            src="${escapeHtml(avatar)}"
                                            alt=""
                                            loading="lazy"
                                            onerror="this.style.display='none';this.nextElementSibling.style.display='grid';">
                                       <span class="player-avatar player-avatar-fallback" style="display:none">${escapeHtml(initial)}</span>`
                                    : `<span class="player-avatar">${escapeHtml(initial)}</span>`
                                }
                                <div>
                                    <div class="player-name">${escapeHtml(player.name)}</div>
                                    <div class="player-rank">${escapeHtml(player.rank || 'Sem patente')}</div>
                                </div>
                            </div>
                        </td>
                        <td class="points">${number(player.points)}</td>
                        <td class="win">${number(player.kills)}</td>
                        <td class="loss">${number(player.deaths)}</td>
                        <td>${number(player.headshots)}</td>
                        <td class="rate">${escapeHtml(String(player.hs_pct ?? 0))}%</td>
                    </tr>
                `;
            }).join('');

            empty.hidden = filtered.length !== 0;
        }

        async function loadRanking() {
            try {
                const response = await fetch(RANKING_API, {
                    cache: 'no-store',
                    headers: {
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const data = await response.json();

                // O Response enviado é diretamente um array de jogadores.
                players = Array.isArray(data) ? data : (data.players || data.leaderboard || []);

                players.sort((a, b) => Number(b.points || 0) - Number(a.points || 0));

                renderTop();
                renderTable();
                status.textContent = `Servidor conectado • ${players.length} jogadores`;
                status.className = 'ranking-status online';
            } catch (error) {
                console.error('Erro ao carregar ranking:', error);
                status.textContent = 'Não foi possível conectar ao servidor';
                status.className = 'ranking-status error';
                body.innerHTML = `
                    <tr>
                        <td colspan="7" class="ranking-error">
                            Não foi possível carregar o ranking.
                        </td>
                    </tr>
                `;
            }
        }

        search.addEventListener('input', renderTable);

        loadRanking();

        // Atualiza automaticamente para acompanhar alterações no servidor.
        setInterval(loadRanking, 30000);
    </script>

</body>

</html>