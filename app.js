(() => {
  // This discourages ordinary copying, but cannot prevent screenshots or inspection.
  const isQuestion = target => target instanceof Element && !!target.closest('.question-card, .protected-questions');
  const selectionIncludesQuestions = () => {
    const selection = window.getSelection();
    return !!selection && !selection.isCollapsed && [...document.querySelectorAll('.question-card, .protected-questions')].some(node => selection.containsNode(node, true));
  };
  ['copy', 'cut', 'contextmenu', 'dragstart', 'selectstart'].forEach(type => {
    document.addEventListener(type, event => {
      if (isQuestion(event.target) || (['copy', 'cut'].includes(type) && selectionIncludesQuestions())) event.preventDefault();
    });
  });
  document.addEventListener('keydown', event => {
    if ((event.ctrlKey || event.metaKey) && ['c', 'x', 'a'].includes(event.key.toLowerCase()) && (isQuestion(event.target) || selectionIncludesQuestions())) event.preventDefault();
  });
  const form = document.getElementById('test-form');
  if (!form) return;
  const cards = [...form.querySelectorAll('[data-question]')];
  const nav = document.getElementById('question-nav');
  const previous = document.getElementById('prev-button');
  const next = document.getElementById('next-button');
  const submit = document.getElementById('submit-button');
  const progressLabel = document.getElementById('progress-label');
  const progressBar = document.getElementById('progress-bar');
  const timer = document.getElementById('timer');
  const status = document.getElementById('save-status');
  const csrf = form.querySelector('input[name="csrf"]').value;
  const endAt = performance.now() + Number(form.dataset.remaining) * 1000;
  const pending = new Map();
  let current = 0, saving = false, expired = false, retryTimer = null;
  cards.forEach(card => card.querySelectorAll('input').forEach(input => { input.required = false; }));
  const buttons = cards.map((card, index) => {
    const button = document.createElement('button');
    button.type = 'button'; button.textContent = String(index + 1).padStart(2, '0');
    button.setAttribute('aria-label', `Pertanyaan ${index + 1}`);
    button.addEventListener('click', () => show(index, true)); nav.appendChild(button); return button;
  });
  function update() {
    let answered = 0;
    cards.forEach((card, i) => {
      const isAnswered = !!card.querySelector('input:checked');
      if (isAnswered) answered++;
      buttons[i].classList.toggle('answered', isAnswered);
      buttons[i].classList.toggle('current', i === current);
      if (i === current) buttons[i].setAttribute('aria-current', 'step'); else buttons[i].removeAttribute('aria-current');
    });
    progressLabel.textContent = `${answered} / ${cards.length} terjawab`;
    progressBar.style.width = `${answered / cards.length * 100}%`;
  }
  function show(index, scroll = false) {
    current = index; cards.forEach((card, i) => { card.hidden = i !== current; });
    previous.hidden = current === 0; next.hidden = current === cards.length - 1; submit.hidden = current !== cards.length - 1;
    update();
    if (scroll) cards[current].scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
  }
  async function saveNext() {
    if (saving || expired || !pending.size) return;
    saving = true;
    const [id, answer] = pending.entries().next().value;
    status.textContent = 'Menyimpan jawaban…'; status.classList.remove('save-error');
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 10000);
    try {
      const body = new URLSearchParams({ csrf, question_id: id, answer });
      const response = await fetch('save_answer.php', { method: 'POST', body, signal: controller.signal, credentials: 'same-origin' });
      const data = await response.json();
      if (data.expired) { expired = true; status.textContent = 'Waktu habis. Menyiapkan hasil…'; window.location.reload(); return; }
      if (!response.ok || !data.saved) throw new Error(data.error || 'Tidak dapat menyimpan.');
      if (pending.get(id) === answer) pending.delete(id);
      status.textContent = pending.size ? 'Menyimpan jawaban…' : 'Jawaban tersimpan otomatis.';
    } catch (_) {
      status.textContent = 'Jawaban belum tersimpan. Periksa koneksi; aplikasi akan mencoba lagi.';
      status.classList.add('save-error');
      retryTimer = setTimeout(() => { retryTimer = null; saveNext(); }, 3000);
    } finally {
      clearTimeout(timeout); saving = false;
      if (!retryTimer && pending.size && !expired) saveNext();
    }
  }
  function tick() {
    const seconds = Math.max(0, Math.ceil((endAt - performance.now()) / 1000));
    timer.textContent = `${String(Math.floor(seconds / 60)).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`;
    timer.parentElement.classList.toggle('timer-warning', seconds <= 300);
    if (seconds === 0 && !expired) {
      expired = true;
      form.querySelectorAll('input[type="radio"], button').forEach(el => { el.disabled = true; });
      status.textContent = 'Waktu habis. Jawaban yang tersimpan sedang dikirim…';
      // Allow an in-flight save to finish; the server independently rejects late changes.
      setTimeout(() => window.location.reload(), saving ? 1500 : 0);
    }
  }
  form.classList.add('js-ready');
  form.addEventListener('change', event => {
    update(); const input = event.target;
    if (input instanceof HTMLInputElement && input.type === 'radio' && input.checked) {
      const match = input.name.match(/^answer\[(\d+)\]$/);
      if (match) { pending.set(match[1], input.value); saveNext(); }
    }
  });
  window.addEventListener('online', () => { clearTimeout(retryTimer); retryTimer = null; saveNext(); });
  previous.addEventListener('click', () => show(Math.max(0, current - 1), true));
  next.addEventListener('click', () => show(Math.min(cards.length - 1, current + 1), true));
  form.addEventListener('submit', event => {
    if (expired || performance.now() >= endAt) { event.preventDefault(); tick(); return; }
    const unanswered = cards.findIndex(card => !card.querySelector('input:checked'));
    if (unanswered !== -1) { event.preventDefault(); show(unanswered, true); cards[unanswered].querySelector('input').focus(); alert(`Mohon jawab pertanyaan nomor ${unanswered + 1} sebelum mengirimkan tes.`); }
    else if (!confirm('Kirim semua jawaban Anda? Jawaban tidak dapat diubah setelah dikirim.')) event.preventDefault();
    else { submit.disabled = true; submit.textContent = 'Mengirim jawaban...'; }
  });
  show(0); tick(); setInterval(tick, 1000);
})();
