'use strict';

document.addEventListener('DOMContentLoaded', () => {
  const byId = id => document.getElementById(id);
  const texts = JSON.parse(byId('image-translations').textContent);
  const api = document.body.dataset.api;
  const form = byId('image-form');
  const backendSelect = byId('image-backend');
  const startButton = byId('startBtn');
  const status = byId('image-status');
  const profiles = new Map();
  const fields = [
    'negative_prompt', 'steps', 'cfg_scale', 'sampler_name',
    'scheduler', 'modelSelect', 'width', 'height', 'seed', 'image_count', 'denoising_strength', 'upscaler_select', 'upscale_factor'
  ];
  let currentBackend = backendSelect.value;
  let optionsReady = false;
  let generating = false;
  let optionsVersion = 0;
  let activeJob = null;
  let stopping = false;
  let selectedImage = '';
  let preparing = false;
  let imageLoadVersion = 0;
  const normalSizes = [...byId('image_size').options].map(option => ({
    value: option.value,
    text: option.text
  }));

  function updateSizes() {
    const qwenEdit = backendSelect.value === 'qwen2' &&
      byId('img2img-mode').checked;
    const mode = qwenEdit ? 'qwen-edit' : 'normal';
    const select = byId('image_size');
    if (select.dataset.sizeMode !== mode) {
      const previous = select.value;
      const sizes = qwenEdit
        ? normalSizes.filter(option =>
            ['992x992', '1152x864'].includes(option.value))
        : normalSizes;
      select.replaceChildren(...sizes.map(option =>
        new Option(option.text, option.value)));
      select.value = sizes.some(option => option.value === previous)
        ? previous : (qwenEdit ? '992x992' : '1024x1024');
      select.dataset.sizeMode = mode;
    }
    const [width, height] = select.value.split('x');
    byId('width').value = width;
    byId('height').value = height;
  }

  function updateEditing() {
    const forge = backendSelect.value === 'forge';
    byId('img2img-mode').disabled = !optionsReady || generating || preparing;
    updateSizes();
    byId('denoising-group').hidden = !forge || !byId('img2img-mode').checked;
    byId('denoising_strength').disabled =
      !forge || !byId('img2img-mode').checked || generating || preparing;
    const upscaleDisabled = !forge || !optionsReady || generating || preparing;
    byId('upscaler_select').disabled = upscaleDisabled;
    byId('upscale_factor').disabled = upscaleDisabled;
    byId('upscaleBtn').disabled = upscaleDisabled || !selectedImage ||
      !byId('upscaler_select').value;
  }

  byId('img2img-mode').addEventListener('change', () => {
    if (backendSelect.value === 'qwen2' && byId('img2img-mode').checked) {
      byId('steps').value = '40';
      updateRanges();
    }
    updateEditing();
  });

  async function imageDataURL(url) {
    const parsed = new URL(url, location.href);
    if (parsed.origin !== location.origin ||
        !['http:', 'https:', 'blob:'].includes(parsed.protocol)) {
      throw new Error(texts.IMG_ERROR_IMAGE_INVALID);
    }
    const response = await fetch(url, {
      credentials: 'same-origin',
      cache: 'no-store'
    });
    if (!response.ok) throw new Error(texts.IMG_ERROR_IMAGE_INVALID);
    const blob = await response.blob();
    if (blob.size > 10 * 1024 * 1024) {
      throw new Error(texts.IMG_ERROR_IMAGE_LIMIT);
    }
    if (!['image/png', 'image/jpeg', 'image/webp'].includes(blob.type)) {
      throw new Error(texts.IMG_ERROR_IMAGE_INVALID);
    }
    return new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onload = () => resolve(reader.result);
      reader.onerror = () => reject(new Error(texts.IMG_ERROR_IMAGE_INVALID));
      reader.readAsDataURL(blob);
    });
  }

  async function prepareQwenReference(dataURL, width, height) {
    const image = new Image();
    await new Promise((resolve, reject) => {
      image.onload = resolve;
      image.onerror = () => reject(new Error(texts.IMG_ERROR_IMAGE_INVALID));
      image.src = dataURL;
    });
    if (!image.naturalWidth || !image.naturalHeight ||
        image.naturalWidth > 8192 || image.naturalHeight > 8192 ||
        image.naturalWidth * image.naturalHeight > 32000000) {
      throw new Error(texts.IMG_ERROR_IMAGE_LIMIT);
    }

    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const context = canvas.getContext('2d');
    if (!context) throw new Error(texts.IMG_ERROR_IMAGE_INVALID);
    context.imageSmoothingEnabled = true;
    context.imageSmoothingQuality = 'high';
    const scale = Math.max(width / image.naturalWidth, height / image.naturalHeight);
    const cropWidth = width / scale;
    const cropHeight = height / scale;
    context.drawImage(
      image,
      (image.naturalWidth - cropWidth) / 2,
      (image.naturalHeight - cropHeight) / 2,
      cropWidth, cropHeight,
      0, 0, width, height
    );
    return canvas.toDataURL('image/png');
  }

  function clearImage() {
    imageLoadVersion++;
    if (selectedImage.startsWith('blob:')) URL.revokeObjectURL(selectedImage);
    selectedImage = '';
    byId('result-image').removeAttribute('src');
    byId('result-image').hidden = true;
    byId('placeholder').hidden = false;
    byId('image-download').removeAttribute('href');
    byId('image-download').hidden = true;
    updateEditing();
  }

  function chooseImage() {
    if (!generating && !preparing) byId('image-upload').click();
  }

  byId('imgContainer').addEventListener('click', chooseImage);
  byId('imgContainer').addEventListener('keydown', event => {
    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      chooseImage();
    }
  });

  byId('image-upload').addEventListener('change', event => {
    const file = event.target.files[0];
    event.target.value = '';
    if (!file || generating || preparing) return;

    if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type)) {
      message(status, texts.IMG_ERROR_IMAGE_INVALID, true);
      return;
    }
    if (file.size > 10 * 1024 * 1024) {
      message(status, texts.IMG_ERROR_IMAGE_LIMIT, true);
      return;
    }

    const version = ++imageLoadVersion;
    const url = URL.createObjectURL(file);
    const probe = new Image();
    probe.onload = () => {
      if (version !== imageLoadVersion || generating || preparing) {
        URL.revokeObjectURL(url);
        return;
      }
      if (probe.naturalWidth > 8192 || probe.naturalHeight > 8192 ||
          probe.naturalWidth * probe.naturalHeight > 32000000) {
        URL.revokeObjectURL(url);
        message(status, texts.IMG_ERROR_IMAGE_LIMIT, true);
        return;
      }
      showImage(url);
      message(status, '');
    };
    probe.onerror = () => {
      URL.revokeObjectURL(url);
      if (version === imageLoadVersion) {
        message(status, texts.IMG_ERROR_IMAGE_INVALID, true);
      }
    };
    probe.src = url;
  });

  byId('image_size').addEventListener('change', () => {
    const [width, height] = byId('image_size').value.split('x');
    byId('width').value = width;
    byId('height').value = height;
  });

  function updateRanges() {
    ['steps', 'cfg_scale', 'denoising_strength'].forEach(id => {
      byId(id + '-value').value = byId(id).value;
    });
  }

  ['steps', 'cfg_scale', 'denoising_strength'].forEach(id => {
    byId(id).addEventListener('input', updateRanges);
  });

  function message(element, text, error = false) {
    element.textContent = text;
    element.classList.toggle('error', error);
  }

  async function request(path, options = {}) {
    const response = await fetch(api + path, {
      credentials: 'same-origin',
      ...options
    });
    let data;
    try {
      data = await response.json();
    } catch {
      throw new Error(texts.IMG_ERROR_REQUEST);
    }
    if (!response.ok || !data.success) {
      throw new Error(data.error || texts.IMG_ERROR_REQUEST);
    }
    return data;
  }

  function rememberProfile() {
    profiles.set(currentBackend, Object.fromEntries(
      fields.map(id => [id, byId(id).value])
    ));
  }

  function fillSelect(id, values, preferred = '') {
    const select = byId(id);
    select.replaceChildren(...values.map(value => new Option(value, value)));
    const match = values.find(value => value === preferred)
      || values.find(value => value.toLowerCase() === preferred.toLowerCase());
    if (match) select.value = match;
  }

  async function loadBackend() {
    const version = ++optionsVersion;
    const backend = backendSelect.value;
    currentBackend = backend;
    optionsReady = false;
    startButton.disabled = true;

    const forge = backend === 'forge';
    byId('image_count').disabled = true;
    document.querySelectorAll('[data-forge-only]').forEach(element => {
      element.hidden = !forge;
      element.querySelectorAll('input, select').forEach(input => {
        input.disabled = !forge;
      });
    });

    const saved = profiles.get(backend);
    const defaults = {
      negative_prompt: forge
        ? 'blurry, low quality, distorted, extra limbs, bad anatomy, deformed'
        : '',
      steps: '20',
      cfg_scale: forge ? '7' : '1',
      sampler_name: forge ? 'DPM++ 2M' : 'Euler',
      scheduler: 'karras',
      modelSelect: '',
      width: '1024',
      height: '1024',
      seed: '-1',
      image_count: '1',
      denoising_strength: '0.55',
      upscaler_select: 'Lanczos',
      upscale_factor: '2'
    };
    const values = saved || defaults;
    fields.forEach(id => {
      if (byId(id).tagName !== 'SELECT') byId(id).value = values[id];
    });
    byId('image_size').value = values.width + 'x' + values.height;
    byId('image_count').value = forge ? (values.image_count || '1') : '1';
    byId('upscale_factor').value = values.upscale_factor || '2';
    updateRanges();

    message(status, '');
    try {
      const data = await request('/options?backend=' + encodeURIComponent(backend));
      if (version !== optionsVersion) return;
      fillSelect('sampler_name', data.samplers, values.sampler_name);
      fillSelect('scheduler', data.schedulers, values.scheduler);
      fillSelect('modelSelect', data.models, values.modelSelect);
      fillSelect('upscaler_select', data.upscalers || [], values.upscaler_select || 'Lanczos');
      optionsReady = true;
    } catch (error) {
      if (version !== optionsVersion) return;
      message(status, error.message || texts.IMG_ERROR_CAPABILITIES, true);
    } finally {
      if (version === optionsVersion) {
        startButton.disabled = !optionsReady || generating;
        byId('image_count').disabled = !forge || !optionsReady || generating;
        updateEditing();
      }
    }
  }

  function showImage(url) {
    imageLoadVersion++;
    if (selectedImage.startsWith('blob:') && selectedImage !== url) {
      URL.revokeObjectURL(selectedImage);
    }
    selectedImage = url;
    const image = byId('result-image');
    image.src = url;
    image.hidden = false;
    byId('placeholder').hidden = true;
    const link = byId('image-download');
    link.href = url;
    link.hidden = false;
    updateEditing();
  }

  function imageButton(url, name, className = '') {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = className;
    button.dataset.imageUrl = url;
    const image = document.createElement('img');
    image.src = url;
    image.alt = name;
    image.loading = 'lazy';
    button.append(image);
    button.addEventListener('click', () => {
      if (generating || preparing) return;
      showImage(url);
      if (className === 'gallery-image') {
        document.querySelector('[data-tab="generate"]').click();
      }
    });
    return button;
  }

  function galleryCard(item) {
    const card = document.createElement('div');
    card.className = 'gallery-card';
    card.dataset.imageUrl = item.image;
    card.append(imageButton(item.image, item.name, 'gallery-image'));

    const remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'image-delete';
    remove.textContent = '×';
    remove.title = texts.IMG_DELETE;
    remove.setAttribute('aria-label', texts.IMG_DELETE);

    remove.addEventListener('click', event => {
      event.stopPropagation();
      if (remove.disabled) return;
      _confirm(texts.IMG_DELETE_CONFIRM, async () => {
        modalClose('modal_pconfirm');
        remove.disabled = true;
      const galleryStatus = byId('gallery-status');
      message(galleryStatus, '');

      try {
        await request('/deleteimage', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({
            csrf_token: document.querySelector('meta[name="csrf-token"]').content,
            name: item.name
          })
        });

        document.querySelectorAll('[data-image-url]').forEach(element => {
          if (element.dataset.imageUrl === item.image) element.remove();
        });

        if (byId('result-image').getAttribute('src') === item.image) {
          clearImage();
        }

        message(galleryStatus, byId('gallery').children.length
          ? texts.IMG_DELETED : texts.IMG_GALLERY_EMPTY
        );
      } catch (error) {
        message(galleryStatus, error.message || texts.IMG_ERROR_DELETE, true);
      } finally {
        remove.disabled = false;
      }
      });
    });

    card.append(remove);
    return card;
  }

  async function refreshGallery() {
    const galleryStatus = byId('gallery-status');
    const button = byId('gallery-refresh');
    button.disabled = true;
    message(galleryStatus, '');
    try {
      const data = await request('/gallery');
      byId('gallery').replaceChildren(...data.images.map(galleryCard));
      if (!data.images.length) {
        message(galleryStatus, texts.IMG_GALLERY_EMPTY);
      }
    } catch (error) {
      message(galleryStatus, error.message, true);
    } finally {
      button.disabled = false;
    }
  }

  backendSelect.addEventListener('change', () => {
    rememberProfile();
    loadBackend();
  });

  startButton.addEventListener('click', async event => {
    if (!generating) return;
    event.preventDefault();
    if (stopping || !activeJob || backendSelect.value !== 'forge') return;

    const job = activeJob;
    stopping = true;
    startButton.disabled = true;
    message(status, texts.IMG_STOPPING);

    try {
      const data = await request('/interrupt', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
          csrf_token: document.querySelector('meta[name="csrf-token"]').content,
          job_id: job
        })
      });
      if (activeJob !== job) return;
      if (data.cancel_requested) {
        message(status, texts.IMG_STOPPING);
      } else {
        stopping = false;
        startButton.disabled = false;
      }
    } catch (error) {
      if (activeJob !== job) return;
      stopping = false;
      startButton.disabled = false;
      message(status, error.message || texts.IMG_ERROR_INTERRUPT, true);
    }
  });

  form.addEventListener('submit', event => {
    event.preventDefault();
    runImageAction('generate');
  });

  byId('upscaleBtn').addEventListener('click', () => runImageAction('upscale'));
  byId('upscaler_select').addEventListener('change', updateEditing);

  async function runImageAction(action) {
    const upscaling = action === 'upscale';
    if (generating || preparing || !optionsReady ||
        (!upscaling && !form.reportValidity())) return;
    if (upscaling && backendSelect.value !== 'forge') return;

    const prompt = byId('prompt').value.trim();
    if (!prompt && !upscaling) {
      message(status, texts.IMG_ERROR_PROMPT, true);
      return;
    }

    const jobId = Array.from(
      crypto.getRandomValues(new Uint8Array(16)),
      value => value.toString(16).padStart(2, '0')
    ).join('');

    const payload = {
      job_id: jobId,
      csrf_token: document.querySelector('meta[name="csrf-token"]').content,
      backend: backendSelect.value,
      prompt,
      negative_prompt: byId('negative_prompt').value,
      steps: Number(byId('steps').value),
      cfg_scale: Number(byId('cfg_scale').value),
      width: Number(byId('width').value),
      height: Number(byId('height').value),
      seed: Number(byId('seed').value),
      image_count: backendSelect.value === 'forge'
        ? Number(byId('image_count').value) : 1,
      sampler: byId('sampler_name').value
    };

    if (payload.width % 32 || payload.height % 32) {
      message(status, texts.IMG_ERROR_PARAMS, true);
      return;
    }

    if (payload.backend === 'forge') {
      payload.model = byId('modelSelect').value;
      payload.scheduler = byId('scheduler').value;
    }

    payload.mode = upscaling ? 'upscale'
      : byId('img2img-mode').checked ? 'img2img' : 'txt2img';

    if (upscaling) {
      payload.upscaler = byId('upscaler_select').value;
      payload.upscale_factor = Number(byId('upscale_factor').value);
    }

    if (payload.mode === 'img2img' || upscaling) {
      if (!selectedImage) {
        message(status, texts.IMG_ERROR_IMAGE_MISSING, true);
        return;
      }
      payload.denoising_strength = Number(byId('denoising_strength').value);
      preparing = true;
      startButton.disabled = true;
      backendSelect.disabled = true;
      byId('image_count').disabled = true;
      updateEditing();
      try {
        payload.init_image = await imageDataURL(selectedImage);
        if (payload.backend === 'qwen2' && payload.mode === 'img2img') {
          payload.init_image = await prepareQwenReference(
            payload.init_image, payload.width, payload.height
          );
        }
      } catch (error) {
        message(status, error.message || texts.IMG_ERROR_IMAGE_INVALID, true);
        return;
      } finally {
        preparing = false;
        backendSelect.disabled = false;
        startButton.disabled = !optionsReady;
        byId('image_count').disabled = backendSelect.value !== 'forge' || !optionsReady;
        updateEditing();
      }
    }

    rememberProfile();
    generating = true;
    updateEditing();
    activeJob = jobId;
    stopping = false;
    const forgeJob = payload.backend === 'forge' && !upscaling;
    startButton.disabled = !forgeJob;
    startButton.textContent = forgeJob ? texts.IMG_GEN_STOP : texts.IMG_GEN_START;
    startButton.classList.toggle('is-stopping', forgeJob);
    byId('image-progress-box').hidden = upscaling;
    byId('image-progress').value = 0;
    byId('image-progress-value').textContent = '0%';
    backendSelect.disabled = true;
    byId('image_count').disabled = true;
    byId('imgContainer').setAttribute('aria-busy', 'true');
    const started = performance.now();
    let progressError = '';
    const updateTime = () => {
      if (stopping) {
        message(status, texts.IMG_STOPPING);
      } else if (progressError) {
        message(status, progressError, true);
      } else {
        message(status,
          `${upscaling ? texts.IMG_UPSCALING : texts.IMG_BUSY} ${Math.floor((performance.now() - started) / 1000)} ${texts.IMG_SECONDS}`
        );
      }
    };
    updateTime();
    const timer = setInterval(updateTime, 1000);
    let progressTimer = null;
    let lastProgress = 0;

    async function pollProgress() {
      if (activeJob !== jobId) return;
      try {
        const data = await request('/progress?job_id=' + encodeURIComponent(jobId));
        if (activeJob !== jobId) return;
        progressError = '';
        if (data.active && typeof data.progress === 'number') {
          lastProgress = Math.max(lastProgress,
            Math.min(99, Math.floor(data.progress * 100))
          );
          byId('image-progress').value = lastProgress;
          byId('image-progress-value').textContent = lastProgress + '%';
        }
      } catch (error) {
        if (activeJob !== jobId) return;
        progressError = error.message || texts.IMG_ERROR_REQUEST;
      }
      if (activeJob === jobId) {
        progressTimer = setTimeout(pollProgress, 1200);
      }
    }

    if (!upscaling) progressTimer = setTimeout(pollProgress, 1200);

    try {
      const data = await request(upscaling ? '/upscale' : '/generate', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(payload)
      });
      clearInterval(timer);
      const images = (Array.isArray(data.images)
        ? data.images : [data.image]
      ).filter(url => typeof url === 'string' && url !== '');
      if (images.length) {
        showImage(images[0]);
        if (!upscaling) byId('generated_seed').value = data.seed ?? '';
      }
      byId('batchGallery').prepend(
        ...images.map(url => imageButton(url, upscaling ? texts.IMG_UPSCALE_READY : prompt))
      );
      while (byId('batchGallery').children.length > 20) {
        byId('batchGallery').lastElementChild.remove();
      }
      if (data.cancelled) {
        message(status, texts.IMG_CANCELLED);
      } else {
        byId('image-progress').value = 100;
        byId('image-progress-value').textContent = '100%';
        message(status, `${upscaling ? texts.IMG_UPSCALE_READY : texts.IMG_READY} ${data.elapsed} ${texts.IMG_SECONDS}`);
      }
    } catch (error) {
      clearInterval(timer);
      message(status, error.message, true);
    } finally {
      clearInterval(timer);
      activeJob = null;
      clearTimeout(progressTimer);
      stopping = false;
      generating = false;
      updateEditing();
      startButton.textContent = texts.IMG_GEN_START;
      startButton.classList.remove('is-stopping');
      backendSelect.disabled = false;
      startButton.disabled = !optionsReady;
      byId('image_count').disabled = backendSelect.value !== 'forge' || !optionsReady;
      byId('imgContainer').setAttribute('aria-busy', 'false');
    }
  }

  document.querySelectorAll('.settings-tab').forEach(tab => {
    tab.addEventListener('click', () => {
      document.querySelectorAll('.settings-tab').forEach(item => {
        item.classList.toggle('active', item === tab);
      });
      document.querySelectorAll('.settings-tab-panel').forEach(panel => {
        panel.hidden = panel.id !== 'tab-' + tab.dataset.tab;
      });
      if (tab.dataset.tab === 'gallery') refreshGallery();
    });
  });

  document.querySelectorAll('[data-server-action]').forEach(button => {
    button.addEventListener('click', async () => {
      const backend = button.dataset.serverBackend;
      const action = button.dataset.serverAction;
      const target = byId('server-status-' + backend);
      const buttons = [...document.querySelectorAll('[data-server-action]')];
      buttons.forEach(item => { item.disabled = true; });
      message(target, texts.IMG_SERVER_WORKING);

      try {
        const data = await request('/server', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({
            csrf_token: document.querySelector('meta[name="csrf-token"]').content,
            backend,
            action
          })
        });

        if (action === 'start') {
          message(target, texts.IMG_SERVER_STARTING);
          const deadline = performance.now() + 90000;
          let ready = false;

          while (performance.now() < deadline) {
            await new Promise(resolve => setTimeout(resolve, 2000));
            const check = await request('/server', {
              method: 'POST',
              headers: {'Content-Type': 'application/json'},
              body: JSON.stringify({
                csrf_token: document.querySelector('meta[name="csrf-token"]').content,
                backend,
                action: 'test'
              })
            });

            if (check.reachable) {
              ready = true;
              message(target, check.message);
              break;
            }
          }

          if (!ready) {
            message(target, texts.IMG_SERVER_START_TIMEOUT, true);
          } else if (backend === backendSelect.value && !generating) {
            rememberProfile();
            await loadBackend();
          }
          return;
        }

        message(target, data.message, data.reachable === false);

        if (backend === backendSelect.value && !generating) {
          if (action === 'test' && data.reachable) {
            rememberProfile();
            await loadBackend();
          } else if (action === 'stop' || data.reachable === false) {
            optionsReady = false;
            startButton.disabled = true;
            byId('image_count').disabled = true;
            updateEditing();
          }
        }
      } catch (error) {
        message(target, error.message || texts.IMG_SERVER_CONTROL_ERROR, true);
      } finally {
        buttons.forEach(item => { item.disabled = false; });
      }
    });
  });

  byId('gallery-refresh').addEventListener('click', refreshGallery);
  loadBackend();
});
