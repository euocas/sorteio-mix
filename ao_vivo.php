<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sorteio Ao Vivo</title>
  <link rel="stylesheet" href="style.css">
  <link rel="icon" type="image/png" href="src/icon.png">
</head>

<body class="live-page">

  <div class="container">

    <!-- =====================================================
         CABEÇALHO
    ====================================================== -->

    <header class="site-header">

    <!-- LOGO TOPO -->

      <img src="src/logo.png" alt="Mix da Família GC" class="logo-topo">

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
          class="btn btn-ghost active"
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
          class="btn btn-ghost"
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


      <!-- TÍTULO -->

      <div class="live-title">

        <div class="live-title-line">

          <span class="live-dot"></span>

          <h1>SORTEIO AO VIVO</h1>

        </div>

        <p>
          Esta página atualiza sozinha — não precisa recarregar.
        </p>

      </div>

    </header>


    <!-- =====================================================
         STATUS
    ====================================================== -->

    <div
      id="statusBox"
      class="status-box idle">
      Conectando à transmissão...
    </div>


    <!-- =====================================================
         JOGADORES AO VIVO
    ====================================================== -->

    <div id="liveArea"></div>


    <!-- =====================================================
         RESULTADO
    ====================================================== -->

    <div id="resultsArea"></div>

    <div id="mapsArea"></div>


    <!-- =====================================================
         ÚLTIMA ATUALIZAÇÃO
    ====================================================== -->

    <div
      id="lastUpdate"
      class="last-update"></div>

  </div>


  <!-- =======================================================
       RODAPÉ
  ======================================================== -->

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


  <!-- =======================================================
       JAVASCRIPT
  ======================================================== -->

  <script>
    const rankLabels = {

      1: {
        label: 'RANK 1',
        icon: '🔥'
      },

      2: {
        label: 'RANK 2',
        icon: '⚡'
      },

      3: {
        label: 'RANK 3',
        icon: '🎯'
      },

      4: {
        label: 'RANK 4',
        icon: '🧨'
      },

      5: {
        label: 'RANK 5',
        icon: '🔫'
      }

    };


    /* =====================================================
       PARSE DO JOGADOR
    ====================================================== */

    function parseEntry(entry) {

      const parts = String(entry).split('|');

      const rank = parseInt(parts[0] || '0', 10);
      const name = parts[2] || parts[1] || '';

      return {
        rank,
        name
      };

    }


    /* =====================================================
       ESTADO IDLE
    ====================================================== */

    function renderIdle() {

      const box =
        document.getElementById('statusBox');

      box.className =
        'status-box idle';

      box.textContent =
        '⏳ Aguardando o sorteador iniciar a seleção dos jogadores...';

      document.getElementById(
        'liveArea'
      ).innerHTML = '';

      document.getElementById(
        'resultsArea'
      ).innerHTML = '';

      document.getElementById(
        'mapsArea'
      ).innerHTML = '';

    }


    /* =====================================================
       ESTADO SELECIONANDO
    ====================================================== */

    function renderSelecting(state) {

      const box =
        document.getElementById('statusBox');

      box.className =
        'status-box selecting';

      if (state.draw_mode === 'vacancies') {
        box.textContent =
          `🎯 Sortear Vagas: ${state.selected.length} jogadores selecionados • ${Number(state.vacancy_count || 1)} vaga(s)`;
      } else {
        box.textContent =
          `🎯 Selecionando jogadores: ${state.selected.length} / 10`;
      }


      const chips = state.selected
        .map(entry => {

          const {
            rank,
            name
          } = parseEntry(entry);

          const rankInfo =
            rankLabels[rank] || {
              icon: '•',
              label: `RANK ${rank}`
            };

          return `
            <span class="live-chip rank-${rank}">
              <span class="live-chip-icon">
                ${rankInfo.icon}
              </span>

              <span class="live-chip-name">
                ${escapeHtml(name)}
              </span>

              <span class="live-chip-rank">
                R${rank}
              </span>
            </span>
          `;

        })
        .join('');


      document.getElementById(
        'liveArea'
      ).innerHTML = `

        <section class="live-selection reveal">

          <div class="live-section-label">
            JOGADORES SELECIONADOS
          </div>

          <div class="live-players">
            ${chips}
          </div>

        </section>

      `;


      document.getElementById(
        'resultsArea'
      ).innerHTML = '';

      document.getElementById(
        'mapsArea'
      ).innerHTML = '';

    }


    /* =====================================================
       RESULTADO — SORTEIO DE VAGAS
    ====================================================== */

    function renderVacancyDone(state) {
      const box = document.getElementById('statusBox');

      box.className = 'status-box done';

      const result = state.vacancy_result || {};
      const selected = Array.isArray(state.selected) ? state.selected : [];

      box.textContent =
        `🎯 Sorteio de vagas concluído: ${Number(result.vacancies || 0)} vaga(s) entre ${selected.length} jogadores`;

      const selectedChips = selected.map(entry => {
        const { rank, name } = parseEntry(entry);
        const rankInfo = rankLabels[rank] || {
          icon: '•',
          label: `RANK ${rank}`
        };

        return `
          <span class="live-chip rank-${rank}">
            <span class="live-chip-icon">${rankInfo.icon}</span>
            <span class="live-chip-name">${escapeHtml(name)}</span>
            <span class="live-chip-rank">R${rank}</span>
          </span>
        `;
      }).join('');

      document.getElementById('liveArea').innerHTML = `
        <section class="live-selection reveal">
          <div class="live-section-label">JOGADORES SELECIONADOS</div>
          <div class="live-players">
            ${selectedChips}
          </div>
        </section>
      `;

      
      const winners = Array.isArray(result.winners) ? result.winners : [];
      const notSelected = Array.isArray(result.notSelected) ? result.notSelected : [];
      const vacancies = Number(result.vacancies || winners.length || 0);
      const total = Number(result.total || state.selected?.length || 0);

      const winnerHtml = winners.map((player, index) => `
        <div class="vacancy-player winner">
          <span class="vacancy-check">✓</span>
          <span>${index + 1}. ${escapeHtml(player.name || 'Jogador')}</span>
        </div>
      `).join('');

      const notSelectedHtml = notSelected.map(player => `
        <span>${escapeHtml(player.name || 'Jogador')}</span>
      `).join('');

      document.getElementById('resultsArea').innerHTML = `
        <section class="results-section reveal vacancy-result">
          <div class="live-result-heading">
            <div>
              <span class="live-result-kicker">RESULTADO FINAL</span>
              <h2>🎯 ${vacancies} ${vacancies === 1 ? 'JOGADOR SORTEADO' : 'JOGADORES SORTEADOS'}</h2>
            </div>
            <div class="live-result-status">FINALIZADO</div>
          </div>

          <div class="vacancy-result-header">
            <div>
              <span class="vacancy-kicker">${vacancies} ${vacancies === 1 ? 'VAGA' : 'VAGAS'}</span>
              <p>${vacancies} vaga(s) sorteada(s) entre ${total} jogador(es) selecionado(s).</p>
            </div>
          </div>

          <div class="vacancy-winners">
            ${winnerHtml}
          </div>

          ${notSelected.length ? `
            <div class="vacancy-not-selected">
              <strong>Não sorteados</strong>
              <div>${notSelectedHtml}</div>
            </div>
          ` : ''}
        </section>
      `;

      document.getElementById('mapsArea').innerHTML = '';
    }

    /* =====================================================
       ESTADO FINALIZADO
    ====================================================== */

    function renderDone(state) {

      const box =
        document.getElementById('statusBox');

      box.className =
        'status-box done';

      box.textContent =
        '🏆 Sorteio concluído!';


      document.getElementById(
        'liveArea'
      ).innerHTML = '';


      const teams = state.teams;

      if (
        !teams ||
        !Array.isArray(teams.team1) ||
        !Array.isArray(teams.team2)
      ) {

        document.getElementById(
          'resultsArea'
        ).innerHTML = `
          <div class="empty-msg">
            Resultado recebido, mas os dados dos times estão incompletos.
          </div>
        `;

        return;

      }


      const diff =
        Math.abs(
          Number(teams.sum1) -
          Number(teams.sum2)
        );


      function teamHtml(
        team,
        sum,
        title
      ) {

        const players = team
          .map(p => `

            <div class="team-player">

              <span class="player-name">
                ${escapeHtml(p.name)}
              </span>

              <span class="rank-pill rp-${Number(p.rank)}">
                R${Number(p.rank)}
              </span>

            </div>

          `)
          .join('');


        return `

          <div class="team-card">

            <div class="team-title">

              <span>
                ${title}
              </span>

              <span class="rank-sum-badge">
                Peso ${Number(sum)}
              </span>

            </div>

            <div class="team-players">

              ${players}

            </div>

          </div>

        `;

      }


      document.getElementById(
        'resultsArea'
      ).innerHTML = `

        <section class="results-section reveal">

          <div class="live-result-heading">

            <div>

              <span class="live-result-kicker">
                RESULTADO FINAL
              </span>

              <h2>
                🏆 TIMES SORTEADOS
              </h2>

            </div>

            <div class="live-result-status">
              FINALIZADO
            </div>

          </div>


          <div class="teams-grid">

            ${teamHtml(
              teams.team1,
              teams.sum1,
              'TIME 1'
            )}

            ${teamHtml(
              teams.team2,
              teams.sum2,
              'TIME 2'
            )}

          </div>


          <div class="balance-info">

            Diferença de peso entre os times:

            <strong>
              ${diff}
            </strong>

            ${
              diff <= 2
                ? ' — Times bem equilibrados ✓'
                : ''
            }

          </div>

          ${renderMatchResult(state.match)}

        </section>

      `;

    }

    function renderMatchResult(match) {
      if (match && match.status === 'finished') {
        const winner = match.winner === 'EMPATE'
          ? '🤝 EMPATE'
          : `🏆 Vencedor: ${escapeHtml(match.winner)}`;

        return `
          <div class="match-result-panel match-finished">
            <div class="match-result-kicker">RESULTADO DA PARTIDA</div>

            <div class="match-score">
              <span>TIME 1</span>
              <strong>${Number(match.score1)}</strong>
              <b>×</b>
              <strong>${Number(match.score2)}</strong>
              <span>TIME 2</span>
            </div>

            <div class="match-winner">${winner}</div>
          </div>
        `;
      }

      return `
        <div class="match-result-panel match-pending">
          <div class="match-result-kicker">RESULTADO DA PARTIDA</div>
          <p class="match-result-help">
            Informe o placar quando a partida terminar.
          </p>

          <div class="match-score-form">
            <label>
              <span>TIME 1</span>
              <input id="matchScore1" type="number" min="0" step="1" inputmode="numeric">
            </label>

            <span class="match-score-x">×</span>

            <label>
              <span>TIME 2</span>
              <input id="matchScore2" type="number" min="0" step="1" inputmode="numeric">
            </label>

            <button type="button" class="btn btn-primary" onclick="finishMatch()">
              🏆 Finalizar partida
            </button>
          </div>

          <div id="matchResultMessage" class="match-result-message" aria-live="polite"></div>
        </div>
      `;
    }

    async function finishMatch() {
      const input1 = document.getElementById('matchScore1');
      const input2 = document.getElementById('matchScore2');
      const message = document.getElementById('matchResultMessage');

      if (!input1 || !input2) return;

      const score1 = Number(input1.value);
      const score2 = Number(input2.value);

      if (!Number.isInteger(score1) || !Number.isInteger(score2) || score1 < 0 || score2 < 0) {
        message.textContent = 'Informe um placar válido para os dois times.';
        return;
      }

      message.textContent = 'Salvando resultado...';

      try {
        const response = await fetch('live_state.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            action: 'finish_match',
            draw_id: window.currentDrawId || '',
            score1,
            score2
          })
        });

        const data = await response.json();

        if (!response.ok || !data.ok) {
          throw new Error(data.error || 'Não foi possível salvar o resultado.');
        }

        message.textContent = 'Resultado salvo!';
        poll();
      } catch (error) {
        message.textContent = error.message || 'Erro ao salvar o resultado.';
      }
    }

    function renderMaps(mapsState) {
      const area = document.getElementById('mapsArea');
      if (!mapsState || mapsState.status !== 'done' || !Array.isArray(mapsState.selected) || !mapsState.selected.length) {
        area.innerHTML = '';
        return;
      }

      const cards = mapsState.selected.map(map => `
        <article class="live-map-card">
          <img src="${escapeHtml(map.image || map.img || '')}" alt="${escapeHtml(map.name || 'Mapa')}" class="live-map-image">
          <div class="live-map-name">${escapeHtml(map.name || 'Mapa')}</div>
        </article>
      `).join('');

      area.innerHTML = `
        <section class="live-maps-section reveal">
          <div class="live-section-label">SORTEIO DE MAPAS</div>
          <div class="live-maps-grid">${cards}</div>
        </section>
      `;
    }


    /* =====================================================
       SEGURANÇA — HTML
    ====================================================== */

    function escapeHtml(value) {

      return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    }

    let lastRenderedSignature = '';
    let lastUpdatedAt = '';


    /* =====================================================
       POLLING
    ====================================================== */

    function poll() {

      fetch(
          'live_state.php?action=state&_=' + Date.now(), {
            cache: 'no-store',
            headers: {
              'Cache-Control': 'no-cache'
            }
          }
        )

        .then(response => {

          if (!response.ok) {
            throw new Error(
              'HTTP ' + response.status
            );
          }

          return response.json();

        })

        .then(data => {

          if (!data.ok) {
            return;
          }


          const state =
            data.state;

          window.currentDrawId = state.draw_id || '';

          const renderSignature = JSON.stringify({
            status: state.status,
            selected: state.selected || [],
            teams: state.teams || null,
            maps: state.maps || null,
            match: state.match || null,
            vacancy_result: state.vacancy_result || null,
            draw_mode: state.draw_mode || null,
            vacancy_count: state.vacancy_count || null,
            draw_id: state.draw_id || null,
          });

          if (renderSignature !== lastRenderedSignature) {
            if (state.status === 'idle') {
              renderIdle();
            } else if (state.status === 'selecting') {
              renderSelecting(state);
            } else if (state.status === 'done') {
              renderDone(state);
            } else if (state.status === 'vacancies_done') {
              renderVacancyDone(state);
            }

            renderMaps(state.maps);
            lastRenderedSignature = renderSignature;
          }


          if (state.updated_at && state.updated_at !== lastUpdatedAt) {

            const d =
              new Date(
                state.updated_at
              );


            document.getElementById(
                'lastUpdate'
              ).textContent =

              'Última atualização: ' +

              d.toLocaleTimeString(
                'pt-BR'
              );

            lastUpdatedAt = state.updated_at;

          }

        })

        .catch(() => {

          const box =
            document.getElementById(
              'statusBox'
            );


          box.className =
            'status-box error';


          box.textContent =
            '⚠ Não foi possível conectar à transmissão. Tentando novamente...';

        });

    }


    /* =====================================================
       INICIALIZAÇÃO
    ====================================================== */

    poll();

    setInterval(
      poll,
      2000
    );
  </script>

</body>

</html>