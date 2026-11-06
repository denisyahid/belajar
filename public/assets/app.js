(() => {
  const form = document.getElementById('test-form');
  if (!form) return;
  const cards = [...form.querySelectorAll('[data-question]')];
  const nav = document.getElementById('question-nav');
  const previous = document.getElementById('prev-button');
  const next = document.getElementById('next-button');
  const submit = document.getElementById('submit-button');
  const progressLabel = document.getElementById('progress-label');
  const progressBar = document.getElementById('progress-bar');
  let current = 0;
  // Native validation cannot focus a hidden question; the submit handler validates all cards instead.
  cards.forEach(card => card.querySelectorAll('input').forEach(input => { input.required = false; }));
  const buttons = cards.map((card, index) => {
    const button = document.createElement('button');
    button.type = 'button';
    button.textContent = String(index + 1).padStart(2, '0');
    button.setAttribute('aria-label', `Pertanyaan ${index + 1}`);
    button.addEventListener('click', () => show(index, true));
    nav.appendChild(button);
    return button;
  });
  function update() {
    let answered = 0;
    cards.forEach((card, i) => {
      const isAnswered = !!card.querySelector('input:checked');
      if (isAnswered) answered++;
      buttons[i].classList.toggle('answered', isAnswered);
      buttons[i].classList.toggle('current', i === current);
      if (i === current) buttons[i].setAttribute('aria-current', 'step');
      else buttons[i].removeAttribute('aria-current');
    });
    progressLabel.textContent = `${answered} / ${cards.length} terjawab`;
    progressBar.style.width = `${answered / cards.length * 100}%`;
  }
  function show(index, scroll = false) {
    current = index;
    cards.forEach((card, i) => { card.hidden = i !== current; });
    previous.hidden = current === 0;
    next.hidden = current === cards.length - 1;
    submit.hidden = current !== cards.length - 1;
    update();
    if (scroll) cards[current].scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
  }
  form.classList.add('js-ready');
  form.addEventListener('change', update);
  previous.addEventListener('click', () => show(Math.max(0, current - 1), true));
  next.addEventListener('click', () => show(Math.min(cards.length - 1, current + 1), true));
  form.addEventListener('submit', event => {
    const unanswered = cards.findIndex(card => !card.querySelector('input:checked'));
    if (unanswered !== -1) {
      event.preventDefault();
      show(unanswered);
      cards[unanswered].querySelector('input').focus();
      alert(`Mohon jawab pertanyaan nomor ${unanswered + 1} sebelum mengirimkan tes.`);
    } else if (!confirm('Kirim semua jawaban Anda? Jawaban tidak dapat diubah setelah dikirim.')) {
      event.preventDefault();
    } else {
      submit.disabled = true;
      submit.textContent = 'Mengirim jawaban...';
    }
  });
  show(0);
})();
