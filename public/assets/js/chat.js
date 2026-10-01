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

function updateVoiceToggle(button)
  {
    if (!button)
      {
        return;
      }

    button.classList.toggle('active', voiceEnabled);
    button.setAttribute('aria-pressed', voiceEnabled ? 'true' : 'false');
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
            console.error('Send message failed:', result);
            return;
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
      }
    finally
      {
        stopChatStatus();
        setStatus('');
        hideLoader();
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
