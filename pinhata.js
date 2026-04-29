const campeoes = [
  { nome: "João", nick: "JMaster" },
  { nome: "Maria", nick: "MQueen" },
  { nome: "Pedro", nick: "PZera" },
  { nome: "Lucas", nick: "LKiller" },
  { nome: "Ana", nick: "AStar" }
];

const container = document.getElementById("pinhata-container");

// controle da mensagem
let mensagemMostrada = false;

/* =========================
   PINHATA
========================= */
function criarPinhata(campeao, delay = 0) {
  const pinhata = document.createElement("div");
  pinhata.classList.add("pinhata");

  const inner = document.createElement("div");
  inner.classList.add("pinhata-inner");

  // posição aleatória
  pinhata.style.left = Math.random() * 85 + "%";

  // tempo de queda
  const duracao = 5 + Math.random() * 3;
  pinhata.style.animationDuration = duracao + "s";
  pinhata.style.animationDelay = delay + "s";

  inner.innerHTML = `
    <img src="./img/pinhata.webp">
    <div class="nome">
      ${campeao.nome}<br>
      <small>${campeao.nick}</small>
    </div>
  `;

  pinhata.appendChild(inner);
  container.appendChild(pinhata);

  // fim da animação
  setTimeout(() => {
    explodirConfete(pinhata);
    pinhata.remove();

    // loop infinito
    criarPinhata(campeao, 0);

  }, (duracao + delay) * 1000);
}

/* =========================
   CONFETE NORMAL
========================= */
function explodirConfete(origem) {
  const rect = origem.getBoundingClientRect();

  for (let i = 0; i < 25; i++) {
    const confete = document.createElement("div");
    confete.classList.add("confete");

    confete.style.left = rect.left + rect.width / 2 + "px";
    confete.style.top = rect.top + rect.height / 2 + "px";

    const x = (Math.random() - 0.5) * 200 + "px";
    const y = (Math.random() - 0.5) * 200 + "px";

    confete.style.setProperty("--x", x);
    confete.style.setProperty("--y", y);

    confete.style.background =
      ["#a855f7", "#10b981", "#f59e0b", "#ef4444", "#3b82f6"][
        Math.floor(Math.random() * 5)
      ];

    container.appendChild(confete);

    setTimeout(() => confete.remove(), 1000);
  }
}

/* =========================
   MENSAGEM FINAL
========================= */
function mostrarMensagemFinal() {
  if (mensagemMostrada) return;
  mensagemMostrada = true;

  const el = document.getElementById("mensagem-final");
  const overlay = document.getElementById("overlay");

  // fundo escuro
  overlay.classList.add("ativo");

  // mostra mensagem
  el.classList.add("ativa");

  // tremor tela
  document.body.classList.add("tremor");
  setTimeout(() => {
    document.body.classList.remove("tremor");
  }, 400);

  // confete central
  explodirConfeteCentro();

  // ⏱️ fica 3 segundos
  setTimeout(() => {
    el.classList.add("saindo");
    overlay.classList.remove("ativo");

    setTimeout(() => {
      el.classList.remove("ativa", "saindo");
      mensagemMostrada = false;
    }, 600);

  }, 3000);
}

/* =========================
   CONFETE CENTRAL
========================= */
function explodirConfeteCentro() {
  const centerX = window.innerWidth / 2;
  const centerY = window.innerHeight / 2;

  for (let i = 0; i < 80; i++) {
    const confete = document.createElement("div");
    confete.classList.add("confete");

    confete.style.left = centerX + "px";
    confete.style.top = centerY + "px";

    const x = (Math.random() - 0.5) * 400 + "px";
    const y = (Math.random() - 0.5) * 400 + "px";

    confete.style.setProperty("--x", x);
    confete.style.setProperty("--y", y);

    confete.style.background =
      ["#a855f7", "#10b981", "#f59e0b", "#ef4444", "#3b82f6"][
        Math.floor(Math.random() * 5)
      ];

    container.appendChild(confete);

    setTimeout(() => confete.remove(), 1000);
  }
}

/* =========================
   INICIALIZAÇÃO
========================= */
window.addEventListener("load", () => {
  campeoes.forEach((c, i) => {
    criarPinhata(c, i * 0.8);
  });

  setTimeout(() => {
    mostrarMensagemFinal();
  }, 800);
});