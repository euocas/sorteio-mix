function requestHistoryPassword() {
  return new Promise(resolve => {
    const overlay = document.createElement('div');
    overlay.className = 'history-password-modal';
    overlay.innerHTML = `
      <form class="history-password-dialog" aria-label="Confirmar limpeza do histórico">
        <h2>Limpar histórico</h2>
        <p>Digite a senha para continuar.</p>
        <label for="historyClearPassword">Senha</label>
        <input id="historyClearPassword" type="password" autocomplete="current-password" required>
        <div class="history-password-actions">
          <button type="button" class="btn btn-ghost">Cancelar</button>
          <button type="submit" class="btn btn-primary">Confirmar</button>
        </div>
      </form>
    `;

    const form = overlay.querySelector('form');
    const input = overlay.querySelector('input');
    const cancel = overlay.querySelector('button[type="button"]');
    const close = value => {
      overlay.remove();
      resolve(value);
    };

    form.addEventListener('submit', event => {
      event.preventDefault();
      close(input.value);
    });
    cancel.addEventListener('click', () => close(null));
    overlay.addEventListener('click', event => {
      if (event.target === overlay) close(null);
    });
    document.addEventListener('keydown', function onKeydown(event) {
      if (event.key !== 'Escape') return;
      document.removeEventListener('keydown', onKeydown);
      close(null);
    });

    document.body.appendChild(overlay);
    input.focus();
  });
}

async function clearHistory() {
  const password = await requestHistoryPassword();
  if (password === null) return;
  if (!window.confirm('Esta ação apagará todo o histórico de sorteios. Deseja continuar?')) return;

  try {
    const response = await fetch('live_state.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'clear_history', password })
    });
    const result = await response.json();
    if (!response.ok || !result.ok) throw new Error(result.error || 'Erro ao limpar histórico');
    window.location.reload();
  } catch (error) {
    alert(error.message === 'senha incorreta' ? 'Senha incorreta.' : 'Não foi possível limpar o histórico.');
  }
}
