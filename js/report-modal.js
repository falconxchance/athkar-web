(function () {
  var openButtons = Array.prototype.slice.call(document.querySelectorAll('.report-trigger-button'));
  var modal = document.getElementById('report-modal');
  var form = document.getElementById('report-form');
  if (!openButtons.length || !modal || !form) return;

  var statusEl = document.getElementById('report-form-status');
  var submitBtn = document.getElementById('report-submit-btn') || form.querySelector('button[type="submit"]');
  var firstInput = form.querySelector('select, textarea, input[type="text"], input[type="email"]');
  var startedAtInput = form.querySelector('input[name="form_started_at"]');
  var honeypotInput = form.querySelector('input[name="company_name"]');
  var captchaTokenInput = form.querySelector('input[name="captcha_token"]');
  var captchaAnswerInput = form.querySelector('input[name="captcha_answer"]');
  var captchaLabel = document.getElementById('report-captcha-label');

  function currentConfig() {
    return window.AthkarReportConfig || {};
  }

  function textValue(name, fallback) {
    var config = currentConfig();
    var v = config && typeof config[name] === 'string' && config[name].trim() !== '' ? config[name] : fallback;
    return v;
  }

  function setOpen(open) {
    modal.hidden = !open;
    modal.classList.toggle('is-hidden', !open);
    document.body.classList.toggle('report-modal-open', !!open);
    if (open) {
      window.setTimeout(function () {
        try { if (firstInput) firstInput.focus(); } catch (e) {}
      }, 20);
    }
  }

  function setStatus(message, isError) {
    if (!statusEl) return;
    statusEl.hidden = !message;
    statusEl.textContent = message || '';
    statusEl.classList.toggle('is-error', !!isError);
    statusEl.classList.toggle('is-success', !!message && !isError);
  }


  function setStartedNow() {
    if (startedAtInput) startedAtInput.value = String(Math.floor(Date.now() / 1000));
  }

  function updateChallenge(question, token) {
    if (captchaLabel) {
      if (typeof question === 'string' && question.trim() !== '') {
        captchaLabel.dataset.question = question;
        var promptTemplate = textValue('captchaPromptTemplate', 'Quick check: solve {question}');
        captchaLabel.textContent = promptTemplate.replace('{question}', question);
      }
    }
    if (captchaTokenInput && typeof token === 'string') captchaTokenInput.value = token;
    if (captchaAnswerInput) captchaAnswerInput.value = '';
    setStartedNow();
  }

  function fetchChallenge() {
    return fetch('/api/report.php?challenge=1', {
      method: 'GET',
      headers: { 'Accept': 'application/json' }
    })
      .then(function (response) {
        return response.json().catch(function () { return {}; }).then(function (data) {
          if (!response.ok || !data || !data.token || !data.question) {
            throw new Error(textValue('errorText', 'Unable to send your report right now.'));
          }
          updateChallenge(String(data.question || ''), String(data.token || ''));
          return data;
        });
      })
      .catch(function () {
        setStartedNow();
        return null;
      });
  }

  function resetVisibleFields() {
    Array.prototype.forEach.call(form.elements || [], function (field) {
      if (!field || field.type === 'hidden' || field.disabled) return;
      if (field.tagName === 'SELECT') field.selectedIndex = 0;
      else if (field.type === 'checkbox' || field.type === 'radio') field.checked = false;
      else field.value = '';
    });
  }

  function getPayload() {
    var fd = new FormData(form);
    return {
      item_key: String(fd.get('item_key') || '').trim(),
      section_slug: String(fd.get('section_slug') || '').trim(),
      lang: String(fd.get('lang') || '').trim(),
      page_context: String(fd.get('page_context') || '').trim(),
      issue_type: String(fd.get('issue_type') || '').trim(),
      reporter_name: String(fd.get('reporter_name') || '').trim(),
      reporter_email: String(fd.get('reporter_email') || '').trim(),
      message: String(fd.get('message') || '').trim(),
      form_started_at: String(fd.get('form_started_at') || '').trim(),
      company_name: String(fd.get('company_name') || '').trim(),
      captcha_token: String(fd.get('captcha_token') || '').trim(),
      captcha_answer: String(fd.get('captcha_answer') || '').trim(),
      source_url: String((currentConfig() && currentConfig().sourceUrl) || window.location.href || '').trim()
    };
  }

  openButtons.forEach(function (openBtn) {
    openBtn.addEventListener('click', function () {
      setStatus('', false);
      if (honeypotInput) honeypotInput.value = '';
      resetVisibleFields();
      setOpen(true);
      fetchChallenge();
    });
  });

  modal.addEventListener('click', function (event) {
    if (event.target && event.target.closest('[data-report-close]')) setOpen(false);
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && !modal.hidden) setOpen(false);
  });

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    var payload = getPayload();
    if (!payload.issue_type || !payload.message) {
      setStatus(textValue('validationText', 'Please choose an issue type and add a short explanation.'), true);
      return;
    }
    if (!payload.captcha_answer) {
      setStatus(textValue('captchaText', 'Please solve the quick check correctly before sending.'), true);
      return;
    }
    var startedAt = parseInt(payload.form_started_at || '0', 10);
    var nowSeconds = Math.floor(Date.now() / 1000);
    if (!startedAt || (nowSeconds - startedAt) < 3) {
      setStatus(textValue('spamText', 'Please wait a few seconds and try again.'), true);
      return;
    }
    setStatus('', false);
    if (submitBtn) submitBtn.disabled = true;
    fetch('/api/report.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify(payload)
    })
      .then(function (response) {
        return response.json().catch(function () { return {}; }).then(function (data) {
          if (!response.ok) throw new Error((data && data.error) || textValue('errorText', 'Unable to send your report right now.'));
          return data || {};
        });
      })
      .then(function () {
        setStatus(textValue('successText', 'Thank you. Your report has been sent.'), false);
        resetVisibleFields();
        fetchChallenge();
        window.setTimeout(function () {
          setOpen(false);
          setStatus('', false);
        }, 900);
      })
      .catch(function (error) {
        setStatus(error && error.message ? error.message : textValue('errorText', 'Unable to send your report right now.'), true);
      })
      .finally(function () {
        if (submitBtn) submitBtn.disabled = false;
      });
  });
})();
