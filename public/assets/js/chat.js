// Attachments: upload first, store only the server-generated reference.
let chatAttachment = null;
let attachmentUploading = false;
let chatSending = false;
let attachmentVersion = 0;

function attachmentFeedback(text = '') {
    const element = document.getElementById('attachment_feedback');
    if (element) { element.textContent = text; }
}
function closeAttachmentMenu() {
    const menu = document.getElementById('attachment_menu');
    if (menu) { menu.hidden = true; }
    document.getElementById('chat_add')?.setAttribute('aria-expanded', 'false');
}
function clearAttachment() {
    attachmentVersion++;
    chatAttachment = null;
    const preview = document.getElementById('attachment_preview');
    if (preview) { preview.hidden = true; }
    const image = document.getElementById('attachment_thumbnail');
    if (image) { image.removeAttribute('src'); }
    const input = document.getElementById('attachment_file');
    if (input) { input.value = ''; }
    attachmentFeedback();
}
function attachmentBusy() {
    for (const id of ['chat_add', 'attachment_file', 'attachment_remove', 'chat_send', 'model_list']) {
        const element = document.getElementById(id);
        if (element) { element.disabled = attachmentUploading || chatSending; }
    }
}
async function uploadChatImage(file) {
    if (!file || attachmentUploading || chatSending) { return; }
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 8 * 1024 * 1024) {
        attachmentFeedback('JPEG, PNG vagy WebP kép szükséges, legfeljebb 8 MB.');
        return;
    }
    const version = ++attachmentVersion;
    attachmentUploading = true;
    attachmentBusy();
    attachmentFeedback('Kép feltöltése…');
    try {
        const data = new FormData();
        data.append('image', file);
        const response = await fetch('/chat_ajax/uploadimage', {method: 'POST', body: data});
        const result = await response.json();
        if (!response.ok || !result.success || !result.path) {
            throw new Error(result.error || 'A feltöltés sikertelen.');
        }
        if (version !== attachmentVersion) { return; }
        chatAttachment = {path: result.path, name: file.name};
        document.getElementById('attachment_thumbnail').src = result.path;
        document.getElementById('attachment_name').textContent = file.name;
        document.getElementById('attachment_preview').hidden = false;
        attachmentFeedback();
    } catch (error) {
        if (version === attachmentVersion) { attachmentFeedback(error.message); }
    } finally {
        attachmentUploading = false;
        attachmentBusy();
    }
}
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('chat_add')?.addEventListener('click', () => {
        const menu = document.getElementById('attachment_menu');
        if (!menu) { return; }
        const open = menu.hidden;
        closeChatMenus();
        menu.hidden = !open;
        document.getElementById('chat_add').setAttribute('aria-expanded', String(open));
        if (open) { document.getElementById('attach_image')?.focus(); }
    });
    document.getElementById('attach_image')?.addEventListener('click', () => {
        closeAttachmentMenu();
        document.getElementById('attachment_file').click();
    });
    document.getElementById('attachment_file')?.addEventListener('change', event => {
        uploadChatImage(event.target.files[0]);
    });
    document.getElementById('attachment_remove')?.addEventListener('click', clearAttachment);
});
document.addEventListener('click', event => {
    if (!event.target.closest('.attachment-actions')) { closeAttachmentMenu(); }
});
document.addEventListener('keydown', event => {
    if (event.key === 'Escape') { closeAttachmentMenu(); }
});

function showLoader()
  {
    const loader = document.getElementById('_loader');

    if (loader)
      {
        loader.classList.remove('done');
        loader.classList.add('loader');
      }
  }


function hideLoader()
  {
    const loader = document.getElementById('_loader');

    if (loader)
      {
        loader.classList.add('done');

        setTimeout(() =>
          {
            loader.classList.remove('loader', 'done');
          }, 300);
      }
  }

let activeVoiceAudio = null;
let voicePlaybackToken = 0;
let voiceEnabled = false;
let sttRecognition = null;
let sttRecognitionActive = false;
let sttRecorder = null;
let sttStream = null;
let sttChunks = [];

function updateVoiceToggle(button)
  {
    if (!button)
      {
        return;
      }

    button.classList.toggle('active', voiceEnabled);
    button.setAttribute('aria-pressed', voiceEnabled ? 'true' : 'false');
  }

function sttMessage(key)
  {
    return window.LANG && LANG[key] ? LANG[key] : '';
  }

function sttSettings()
  {
    const chat = document.querySelector('.chat-layout');

    return {
      provider: (chat?.dataset.sttProvider || '').toLowerCase(),
      language: chat?.dataset.sttLanguage || 'hu'
    };
  }

function browserLanguage(language)
  {
    const value = String(language || '').trim();

    if (value === '' || value.toLowerCase() === 'auto')
      {
        return navigator.language || 'hu-HU';
      }

    return value.includes('-')
      ? value
      : value + '-' + value.toUpperCase();
  }

function setMicState(active)
  {
    const button = document.getElementById('btn_mic');

    if (!button)
      {
        return;
      }

    button.classList.toggle('active', active);
    button.setAttribute('aria-pressed', active ? 'true' : 'false');
  }

function appendTranscription(text)
  {
    const input = document.getElementById('chat_message');
    const value = String(text || '').trim();

    if (!input || value === '')
      {
        return;
      }

    input.value += (input.value.trim() === '' ? '' : ' ') + value;
    input.focus();
  }

function toggleSpeechInput()
  {
    const settings = sttSettings();
    console.log(settings);
    if (settings.provider === 'browser')
      {
        toggleBrowserRecognition(browserLanguage(settings.language));
        return;
      }

    if (settings.provider === 'whisper')
      {
        toggleWhisperRecording();
        return;
      }

    setStatus(sttMessage('STT_ERROR_PROVIDER'));
  }

function toggleBrowserRecognition(language)
  {
    if (sttRecognitionActive && sttRecognition)
      {
        sttRecognitionActive = false;
        sttRecognition.stop();
        return;
      }

    const Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;

    if (!Recognition)
      {
        setStatus(sttMessage('STT_ERROR_BROWSER_UNAVAILABLE'));
        return;
      }

    if (!sttRecognition)
      {
        sttRecognition = new Recognition();
        sttRecognition.continuous = true;
        sttRecognition.interimResults = false;
        sttRecognition.lang = language;

        sttRecognition.onstart = function()
          {
            console.log('[STT] Browser recognition started');
            sttRecognitionActive = true;
            setMicState(true);
            setStatus(sttMessage('STT_BROWSER_LISTENING'));
          };

        sttRecognition.onresult = function(event)
          {
            for (let index = event.resultIndex; index < event.results.length; index++)
              {
                if (event.results[index].isFinal)
                  {
                    appendTranscription(event.results[index][0].transcript);
                  }
              }
          };

        sttRecognition.onerror = function(event)
          {
            console.error('[STT] Browser recognition error:', event.error, event.message);
            if (event.error !== 'no-speech')
              {
                console.error('Browser STT error:', event.error);
                setStatus(sttMessage('STT_ERROR_BROWSER'));
              }
          };

        sttRecognition.onend = function()
          {
            console.log('[STT] Browser recognition ended');

            if (sttRecognitionActive)
              {
                setTimeout(function()
                  {
                    if (!sttRecognitionActive)
                      {
                        return;
                      }

                    try
                      {
                        sttRecognition.start();
                      }
                    catch (error)
                      {
                        sttRecognitionActive = false;
                        setMicState(false);
                        console.error('Browser STT restart failed:', error);
                      }
                  }, 200);

                return;
              }

            setMicState(false);
            setStatus('');
          };
      }

    try
      {
        sttRecognition.lang = language;
        sttRecognitionActive = true;
        sttRecognition.start();
      }
    catch (error)
      {
        sttRecognitionActive = false;
        console.error('Browser STT start failed:', error);
        setMicState(false);
      }
  }

async function toggleWhisperRecording()
  {
    if (sttRecorder && sttRecorder.state === 'recording')
      {
        sttRecorder.stop();
        return;
      }

    try
      {
        sttStream = await navigator.mediaDevices.getUserMedia({
          audio: {
            channelCount: 1,
            echoCancellation: true,
            noiseSuppression: true
          }
        });

        const mimeType = MediaRecorder.isTypeSupported('audio/webm;codecs=opus')
          ? 'audio/webm;codecs=opus'
          : '';

        sttRecorder = mimeType === ''
          ? new MediaRecorder(sttStream)
          : new MediaRecorder(sttStream, {mimeType});

        sttChunks = [];

        sttRecorder.addEventListener('dataavailable', function(event)
          {
            if (event.data.size > 0)
              {
                sttChunks.push(event.data);
              }
          });

        sttRecorder.addEventListener('stop', submitWhisperRecording, {once: true});
        sttRecorder.start();
        setMicState(true);
        setStatus(sttMessage('STT_WHISPER_RECORDING'));
      }
    catch (error)
      {
        console.error('Microphone access failed:', error);
        setMicState(false);
        setStatus(sttMessage('STT_ERROR_MICROPHONE'));
      }
  }

async function submitWhisperRecording()
  {
    const mimeType = sttRecorder?.mimeType || 'audio/webm';
    const blob = new Blob(sttChunks, {type: mimeType});

    sttChunks = [];
    setMicState(false);

    if (sttStream)
      {
        sttStream.getTracks().forEach(function(track)
          {
            track.stop();
          });
        sttStream = null;
      }

    if (blob.size === 0)
      {
        setStatus(sttMessage('STT_ERROR_AUDIO'));
        return;
      }

    setStatus(sttMessage('STT_WHISPER_STARTING'));

    const processingTimer = window.setTimeout(function()
      {
        setStatus(sttMessage('STT_WHISPER_TRANSCRIBING'));
      }, 350);

    try
      {
        const data = new FormData();
        data.append('audio_blob', blob, 'recording.webm');

        const response = await fetch('/main/transcribe', {
          method: 'POST',
          body: data
        });

        const result = await response.json();

        if (!result.success)
          {
            window.clearTimeout(processingTimer);
            setStatus(sttMessage(result.error || 'STT_ERROR_SERVER'));
            return;
          }

        window.clearTimeout(processingTimer);
        appendTranscription(result.text || '');
        setStatus('');
      }
    catch (error)
      {
        window.clearTimeout(processingTimer);
        console.error('Whisper STT failed:', error);
        setStatus(sttMessage('STT_ERROR_SERVER'));
      }
  }

function toggleSpeakerState()
  {
    const button = document.getElementById('btn_speaker');

    voiceEnabled = !voiceEnabled;
    updateVoiceToggle(button);

    if (!voiceEnabled)
      {
        stopVoicePlayback();
      }
  }

function initializeVoiceToggle()
  {
    const button = document.getElementById('btn_speaker');

    if (!button)
      {
        return;
      }

    voiceEnabled = button.classList.contains('active');
    button.setAttribute('aria-pressed', voiceEnabled ? 'true' : 'false');

    /* Older markup already calls toggleSpeakerState() inline. */
    if (!button.hasAttribute('onclick'))
      {
        button.addEventListener('click', toggleSpeakerState);
      }
  }

function voiceChunks(text, maxLength = 220)
  {
    const sentences = String(text || '')
      .trim()
      .match(/[^.!?]+(?:[.!?]+|$)/gu) || [];

    const chunks = [];
    let chunk = '';

    function pushChunk(value)
      {
        const cleaned = value.trim();

        if (cleaned !== '')
          {
            chunks.push(cleaned);
          }
      }

    sentences.forEach(function(sentence)
      {
        sentence = sentence.trim();

        if (sentence === '')
          {
            return;
          }

        if ((chunk + ' ' + sentence).trim().length <= maxLength)
          {
            chunk = (chunk + ' ' + sentence).trim();
            return;
          }

        pushChunk(chunk);
        chunk = '';

        while (sentence.length > maxLength)
          {
            const splitAt = sentence.lastIndexOf(' ', maxLength);
            const index = splitAt > 0 ? splitAt : maxLength;

            pushChunk(sentence.slice(0, index));
            sentence = sentence.slice(index).trim();
          }

        chunk = sentence;
      });

    pushChunk(chunk);

    return chunks;
  }

function stopVoicePlayback()
  {
    voicePlaybackToken += 1;

    if (activeVoiceAudio)
      {
        activeVoiceAudio.pause();
        activeVoiceAudio = null;
      }
  }

function playVoiceText(text)
  {
    const chunks = voiceChunks(text);

    if (chunks.length === 0)
      {
        return;
      }

    stopVoicePlayback();

    const token = voicePlaybackToken;
    const createAudio = function(index)
      {
        const verify = index === 0 ? '&verify=1' : '';
        const audio = new Audio(
            '/main/voice?text=' + encodeURIComponent(chunks[index]) + verify
        );

        audio.preload = 'auto';

        return audio;
      };

    let index = 0;
    let currentAudio = createAudio(index);
    let nextAudio = null;

    const preloadNext = function()
      {
        const nextIndex = index + 1;

        if (nextIndex < chunks.length)
          {
            nextAudio = createAudio(nextIndex);
            nextAudio.load();
          }
      };

    const playCurrent = function()
      {
        if (token !== voicePlaybackToken || !currentAudio)
          {
            activeVoiceAudio = null;
            return;
          }

        const audio = currentAudio;
        activeVoiceAudio = audio;

        audio.addEventListener('ended', function()
          {
            if (token !== voicePlaybackToken)
              {
                return;
              }

            index += 1;
            currentAudio = nextAudio;
            nextAudio = null;
            preloadNext();
            playCurrent();
          }, {once: true});
        audio.addEventListener('error', function()
          {
            if (token === voicePlaybackToken)
              {
                console.error('Voice playback failed.');
                activeVoiceAudio = null;
              }
          }, {once: true});

        audio.play().catch(function(error)
          {
            console.error('Voice playback could not start:', error);
          });
      };

    playCurrent();
    preloadNext();
  }

document.addEventListener('DOMContentLoaded', () =>
  {
    initializeVoiceToggle();

    const micButton = document.getElementById('btn_mic');

    if (micButton)
      {
        //micButton.addEventListener('click', toggleSpeechInput);
micButton.addEventListener('click', () =>
  {
    console.log('[STT] Mic button clicked');
    toggleSpeechInput();
  });        
      }

    const modelList = document.getElementById('model_list');

    if (!modelList)
      {
        return;
      }

    modelList.addEventListener('change', () =>
      {
        changeModel(modelList.value);
      });
  });


async function changeModel(modelId)
  {
    if (chatSending || attachmentUploading) { return; }
    clearAttachment();

    const data = new FormData();
    data.append('id', modelId);

    showLoader();

    try
      {
        const response = await fetch('/model_ajax/changemodel',
          {
            method: 'POST',
            body: data
          });

        const result = await response.json();

        if (!result.success)
          {
            return;
          }

        document.getElementById('model_list').innerHTML    = result.models;
        document.getElementById('chat_list').innerHTML     = result.titles;
        document.getElementById('modelimage').src          = result.image;
        document.getElementById('chat_messages').innerHTML = '';
        document.getElementById('chat_message').value       = '';
      }
    catch (error)
      {
        console.error('Model change failed:', error);
      }
    finally
      {
        hideLoader();
      }
  }

async function changeChat(chatId)
  {
    if (chatSending || attachmentUploading) { return; }
    clearAttachment();

    showLoader();

    try
      {
        const data = new FormData();
        data.append('id', chatId);

        const response = await fetch('/chat_ajax/changechat',
          {
            method: 'POST',
            body: data
          });

        const result = await response.json();

        if (!result.success)
          {
            return;
          }

        const messages = document.getElementById('chat_messages');
        const input    = document.getElementById('chat_message');

        if (messages)
          {
            messages.innerHTML = result.messages;
            messages
              .querySelectorAll('.model-message-content')
              .forEach(function(element)
                {
                  renderCode(element);
                });            
            messages.scrollTop = messages.scrollHeight;
          }

        if (input)
          {
            input.value = '';
          }

        document.querySelectorAll('.chat-item.active')
          .forEach(function(item)
            {
              item.classList.remove('active');
            });

        document.getElementById('chat_session_' + chatId)
          ?.classList.add('active');
      }
    catch (error)
      {
        console.error('Chat change error:', error);
      }
    finally
      {
        hideLoader();
      }
  }  

async function newChat()
  {
    if (chatSending || attachmentUploading) { return; }
    clearAttachment();

    showLoader();

    try
      {
        const response = await fetch('/chat_ajax/newchat',
          {
            method: 'POST'
          });

        const result = await response.json();

        if (!result.success)
          {
            return;
          }

        const messages = document.getElementById('chat_messages');
        const input    = document.getElementById('chat_message');

        if (messages)
          {
            messages.innerHTML = '';
          }

        document.querySelectorAll('.chat-item.active')
          .forEach(function(item)
            {
              item.classList.remove('active');
            });

        if (input)
          {
            input.value = '';
            input.focus();
          }
      }
    catch (error)
      {
        console.error('New chat error:', error);
      }
    finally
      {
        hideLoader();
      }
  }

function closeChatMenus(except = null)
  {
    document.querySelectorAll('.chat-item-dropdown')
      .forEach(function(menu)
        {
          if (menu !== except)
            {
              menu.hidden = true;
            }
        });
  }

function cancelChatRename(input)
  {
    const item = input.closest('.chat-item');

    if (!item)
      {
        return;
      }

    input.hidden = true;
    item.querySelector('.chat-item-main').hidden = false;
  }

function startChatRename(item)
  {
    const input = item.querySelector('.chat-rename-input');
    const main = item.querySelector('.chat-item-main');

    if (!input || !main)
      {
        return;
      }

    closeChatMenus();
    main.hidden = true;
    input.hidden = false;
    input.focus();
    input.select();
  }

async function saveChatRename(input)
  {
    const chatId = Number(input.dataset.chatId || 0);
    const name = input.value.trim();

    if (chatId <= 0 || name === '')
      {
        cancelChatRename(input);
        return;
      }

    try
      {
        const data = new FormData();
        data.append('id', chatId);
        data.append('name', name);

        const response = await fetch('/chat_ajax/renamechat',
          {
            method: 'POST',
            body: data
          });

        const result = await response.json();

        if (!result.success)
          {
            return;
          }

        const item = input.closest('.chat-item');
        const title = item?.querySelector('.chat-title');

        if (title)
          {
            title.textContent = result.name;
          }

        input.value = result.name;
        cancelChatRename(input);
      }
    catch (error)
      {
        console.error('Chat rename error:', error);
      }
  }

async function deleteChat(chatId, item)
  {
    if (chatId <= 0 || !item)
      {
        return;
      }

    try
      {
        const data = new FormData();
        data.append('id', chatId);

        const response = await fetch('/chat_ajax/deletechat',
          {
            method: 'POST',
            body: data
          });

        const result = await response.json();

        if (!result.success)
          {
            return;
          }

        item.remove();

        if (result.was_active)
          {
            newChat();
          }
      }
    catch (error)
      {
        console.error('Chat delete error:', error);
      }
  }

let chatStatusTimer = null;

/**
 * Start chat status polling.
 */
function startChatStatus()
  {
    stopChatStatus();

    chatStatusTimer = setInterval(
      async () =>
        {
          try
            {
              const response = await fetch(
                '/status_ajax/getstatus',
                {
                  method: 'GET',
                  cache: 'no-store'
                }
              );

              const result = await response.json();

              if (
                  result.success &&
                  result.status
              )
                {
                  setStatus(result.status);
                }
            }
          catch (error)
            {
              console.error(
                  'Status polling error:',
                  error
              );
            }
        },
      500
    );
  }

/**
 * Stop chat status polling.
 */
function stopChatStatus()
  {
    if (chatStatusTimer !== null)
      {
        clearInterval(chatStatusTimer);
        chatStatusTimer = null;
      }
  }

function setStatus(message = '')
  {
    const status = document.getElementById('status');

    if (status)
      {
        status.textContent = message;
      }
  }

async function sendMessage()
  {
    if (chatSending || attachmentUploading) { return; }

    const input    = document.getElementById('chat_message');
    const messages = document.getElementById('chat_messages');

    if (!input || !messages)
      {
        return;
      }

    const message = input.value.trim();

    if (message === '')
      {
        return;
      }

    chatSending = true;
    attachmentBusy();
    const sentAttachment = chatAttachment;

    /*
     * Temporary user message.
     * This is displayed immediately while Mara is working.
     */
    const pendingUserRow = document.createElement('div');
    pendingUserRow.className = 'message-row user';

    const pendingText = document.createElement('div');
    pendingText.className = 'message-text';
    pendingText.textContent = message;

    pendingUserRow.appendChild(pendingText);
    messages.appendChild(pendingUserRow);

    /*
     * Clear input immediately.
     */
    input.value = '';
    input.focus();

    messages.scrollTop = messages.scrollHeight;

    showLoader();
    setStatus(LANG.STATUS_WORKING);
    startChatStatus();

    try
      {
        const data = new FormData();

        data.append('message', message);
        if (sentAttachment) { data.append('image_path', sentAttachment.path); }

        const rateUser = document.getElementById('rate_user');

        data.append(
          'rate_user',
          rateUser && rateUser.checked ? '1' : '0'
        );

        const response = await fetch(
          '/chat_ajax/sendmessage',
          {
            method: 'POST',
            body: data
          }
        );

        const result = await response.json();

        if (!result.success)
          {
            throw new Error(result.error || 'Az üzenetküldés sikertelen.');
          }

        clearAttachment();
        if (typeof result.titles === 'string') {
            const chatList = document.getElementById('chat_list');
            if (chatList) chatList.innerHTML = result.titles;
        }
        /*
         * Replace temporary user message with
         * the server-rendered message template.
         */
        if (
            result.user_html &&
            pendingUserRow.isConnected
        )
          {
            pendingUserRow.outerHTML = result.user_html;
          }

        /*
         * Add assistant response.
         */
        if (result.model_html)
          {
            messages.insertAdjacentHTML(
              'beforeend',
              result.model_html
            );
          }
        if (result.rating_html)
          {
            showRating(result.rating_html);
          }
        const testMode = document.getElementById('test_mode');
        if (
            testMode?.checked &&
            result.metrics_html
        )
          {
            showMetrics(result.metrics_html);
          }          
        /*
         * Text format & syntax
         */
        messages
          .querySelectorAll('.model-message-content')
          .forEach(function(element)
            {
              renderCode(element);
            });

        messages.scrollTop = messages.scrollHeight;

        if (voiceEnabled && result.voice_text)
          {
            playVoiceText(result.voice_text);
          }

        if (result.rating_html)
          {
            showRating(result.rating_html);
          }
      }
    catch (error)
      {
        console.error('Send message error:', error);
        attachmentFeedback(error.message);
        pendingUserRow.classList.add('message-send-error');
        pendingUserRow.title = 'A feldolgozás nem fejeződött be. Ellenőrizd a beszélgetést újraküldés előtt.';
      }
    finally
      {
        stopChatStatus();
        setStatus('');
        hideLoader();
        chatSending = false;
        attachmentBusy();
      }
  }

function renderCode(element)
  {
    if (!element)
      {
        return;
      }
//console.log('RAW MODEL TEXT:', element.textContent);
    element.innerHTML = marked.parse(element.textContent);

    element.querySelectorAll('pre code').forEach(function(code)
      {
        const lang = (code.className || '')
            .replace('language-', '')
            .toLowerCase();

        switch (lang)
          {
            case 'php':
              w3CodeColor(code, 'php');
              break;

            case 'js':
            case 'javascript':
              w3CodeColor(code, 'js');
              break;

            case 'html':
              w3CodeColor(code, 'html');
              break;

            case 'css':
              w3CodeColor(code, 'css');
              break;
          }
      });
  }

async function getUserRating(messageId)
  {
    try
      {
        const formData = new FormData();

        formData.append('id', messageId);

        const response = await fetch('/chat_ajax/getUserRating',
          {
            method: 'POST',
            body: formData
          });

        const data = await response.json();

        if (!data.success)
          {
            return;
          }

        showRating(data.html || '');
      }
    catch (error)
      {
        console.error('User rating error:', error);
      }
  }

  function showRating(html = '')
  {
    const rating = document.getElementById('user_rating');

    if (!rating)
      {
        return;
      }

    rating.innerHTML = html;
  }

  function showMetrics(html = '')
  {
    const metrics = document.getElementById('model_metrics');

    if (!metrics)
      {
        return;
      }

    metrics.innerHTML = html;
  }

  function updateTestMode()
  {
    const testMode = document.getElementById('test_mode');
    const metrics  = document.getElementById('model_metrics');

    if (!testMode || !metrics)
      {
        return;
      }

    metrics.style.display = testMode.checked
        ? ''
        : 'none';
  }








const sendButton = document.getElementById('chat_send');

if (sendButton)
  {
    sendButton.addEventListener('click', sendMessage);
  }

document.addEventListener('DOMContentLoaded', function()
  {
    const chatInput = document.getElementById('chat_message');

    if (!chatInput)
      {
        return;
      }

    chatInput.addEventListener('keydown', function(event)
      {
        if (event.key === 'Enter' && !event.shiftKey)
          {
            event.preventDefault();
            sendMessage();
          }
      });
  });

document.addEventListener('click', function(event)
  {
    const speakButton = event.target.closest('[data-message-action="speak"]');

    if (speakButton)
      {
        event.preventDefault();
        playVoiceText(speakButton.dataset.voiceText || '');
        return;
      }

    const menuButton = event.target.closest('[data-chat-menu]');

    if (menuButton)
      {
        event.preventDefault();
        event.stopPropagation();

        const menu = menuButton.parentElement
          ?.querySelector('.chat-item-dropdown');

        if (menu)
          {
            const open = menu.hidden;
            closeChatMenus(menu);
            menu.hidden = !open;
          }

        return;
      }

    const renameButton = event.target.closest('[data-chat-rename]');

    if (renameButton)
      {
        event.preventDefault();
        event.stopPropagation();
        startChatRename(renameButton.closest('.chat-item'));
        return;
      }

    const deleteButton = event.target.closest('[data-chat-delete]');

    if (deleteButton)
      {
        event.preventDefault();
        event.stopPropagation();

        const item = deleteButton.closest('.chat-item');
        const chatId = Number(
          item?.querySelector('.chat-rename-input')?.dataset.chatId || 0
        );

        if (window.confirm(deleteButton.dataset.deleteConfirm || 'Delete this chat?'))
          {
            deleteChat(chatId, item);
          }

        return;
      }

    closeChatMenus();
  });

document.addEventListener('keydown', function(event)
  {
    const input = event.target.closest('.chat-rename-input');

    if (!input || input.hidden)
      {
        return;
      }

    if (event.key === 'Enter')
      {
        event.preventDefault();
        saveChatRename(input);
      }

    if (event.key === 'Escape')
      {
        event.preventDefault();
        cancelChatRename(input);
      }
  });

document.addEventListener('DOMContentLoaded', () =>
  {
    const testMode = document.getElementById('test_mode');

    updateTestMode();

    if (testMode)
      {
        testMode.addEventListener('change', updateTestMode);
      }
  });   