//-----------------------------------------------------------------------------
//  Modal nyitása
//-----------------------------------------------------------------------------
function modalOpen(mid)
  {
    document.getElementById(mid).style.visibility = 'visible';
    document.getElementById(mid).style.opacity    = 1;
    return false;
  }
//-----------------------------------------------------------------------------
//  Modal nyitása
//-----------------------------------------------------------------------------
function modalClose(mid)
  {
    document.getElementById(mid).style.visibility = 'hidden';
    document.getElementById(mid).style.opacity    = 0;
    return false;
  }  
//-----------------------------------------------------------------------------
// Adat törlése
//-----------------------------------------------------------------------------
function delete_callback(id, href)
  {   
    wXHR.onsuccess = function(event)
      {
        document.getElementById('_loader').className = '';
        var jsonData = JSON.parse(event.responseText);  
        if (jsonData.msg) {_alert(jsonData.msg);}
        if (jsonData.href) {window.location.href = jsonData.href;} else {modalClose('modal_pconfirm');}
      }        
    document.getElementById('_loader').className = 'loader';            
    wXHR.post(DIR_HOST+'/mara/'+href+'delete', {'id': id});
    return false;               
  }
//-----------------------------------------------------------------------------
// Sidebar nyitása / zárása
//-----------------------------------------------------------------------------
function toggleSidebar(force = null)
{
    const body = document.body;
    const toggle = document.getElementById('sidebar-toggle');

    const open = force === null
        ? !body.classList.contains('sidebar-open')
        : force;

    body.classList.toggle('sidebar-open', open);

    if (toggle) {
        toggle.setAttribute(
            'aria-expanded',
            open ? 'true' : 'false'
        );
    }
}
/* ==========================================================================
   MODEL PARAMETERS
   ========================================================================== */

document.addEventListener('DOMContentLoaded', function () {

    document.querySelectorAll('.range-group').forEach(group => {

        const range  = group.querySelector('input[type="range"]');
        const number = group.querySelector('.range-number');

        if (!range || !number) {
            return;
        }

        // Slider -> számmező
        range.addEventListener('input', function () {
            number.value = range.value;
        });

        // Számmező -> slider
        number.addEventListener('input', function () {

            if (number.value === '') {
                return;
            }

            let value = Number(number.value);

            if (!Number.isFinite(value)) {
                return;
            }

            const min = Number(range.min);
            const max = Number(range.max);

            value = Math.max(min, Math.min(max, value));

            range.value = value;
        });

        // Elhagyáskor igazítsuk a tényleges sliderértékhez
        number.addEventListener('change', function () {

            if (number.value === '') {
                number.value = range.value;
                return;
            }

            number.value = range.value;
        });

    });


    /* --------------------------------------------------------------
       REPEAT LAST N - speciális -1 állapot
       -------------------------------------------------------------- */

    const repeatLastAuto =
        document.getElementById('repeat-last-auto');

    const repeatLastRange =
        document.getElementById('param-repeat-last-n');

    const repeatLastNumber =
        document.getElementById('number-repeat-last-n');

    if (repeatLastAuto && repeatLastRange && repeatLastNumber) {

        const repeatLastItem =
            repeatLastRange.closest('.parameter-item');

        function updateRepeatLastN()
        {
            const automatic = repeatLastAuto.checked;

            repeatLastRange.disabled = automatic;
            repeatLastNumber.disabled = automatic;

            repeatLastItem.classList.toggle(
                'is-auto',
                automatic
            );
        }

        repeatLastAuto.addEventListener(
            'change',
            updateRepeatLastN
        );

        updateRepeatLastN();
    }

});
/* ==========================================================================
   MODEL EDITOR - PSYCHE
   ========================================================================== */

document.addEventListener('DOMContentLoaded', function () {

    const psycheSwitch =
        document.getElementById('model-psyche-enabled');

    const psycheContent =
        document.getElementById('model-psyche-content');

    if (!psycheSwitch || !psycheContent) {
        return;
    }

    function updatePsyche()
    {
        psycheContent.hidden = !psycheSwitch.checked;
    }

    psycheSwitch.addEventListener('change', updatePsyche);

    updatePsyche();

});
/* ==========================================================================
   MODEL EDITOR - TABS
   ========================================================================== */

document.addEventListener('DOMContentLoaded', function () {

    const tabs =
        document.querySelectorAll('[data-model-tab]');

    const panels =
        document.querySelectorAll('[data-model-panel]');

    if (!tabs.length || !panels.length) {
        return;
    }


    tabs.forEach(tab => {

        tab.addEventListener('click', function () {

            const target =
                this.dataset.modelTab;


            tabs.forEach(item => {
                item.classList.remove('active');
            });


            panels.forEach(panel => {

                panel.hidden =
                    panel.dataset.modelPanel !== target;

            });


            this.classList.add('active');

        });

    });

});

/* ==========================================================================
   SETTINGS - SPEECH RECOGNITION
   ========================================================================== */

document.addEventListener('DOMContentLoaded', function () {

    const sttSelect = document.getElementById('stt-provider');

    if (!sttSelect) {
        return;
    }

    const sttProviders =
        document.querySelectorAll('.stt-provider');


    function updateSttProvider()
    {
        const provider = sttSelect.value;

        sttProviders.forEach(item => {

            const visible =
                item.dataset.sttProvider === provider;

            item.hidden = !visible;

        });
    }


    sttSelect.addEventListener(
        'change',
        updateSttProvider
    );


    // Oldal betöltésekor is állítsuk be
    updateSttProvider();

});
//-----------------------------------------------------------------------------
// Setting page tabs
//-----------------------------------------------------------------------------
document.addEventListener('DOMContentLoaded', function () {

    const tabs = document.querySelectorAll('.settings-tab');
    const panels = document.querySelectorAll('.settings-panel');

    tabs.forEach(tab => {

        tab.addEventListener('click', function () {

            const target = this.dataset.tab;

            // Tabok kikapcsolása
            tabs.forEach(item => {
                item.classList.remove('active');
            });

            // Panelek elrejtése
            panels.forEach(panel => {
                panel.classList.remove('active');
                panel.hidden = true;
            });

            // Kiválasztott tab
            this.classList.add('active');

            // Kiválasztott panel
            const targetPanel =
                document.getElementById('settings-' + target);

            if (targetPanel) {
                targetPanel.hidden = false;
                targetPanel.classList.add('active');
            }

        });

    });

});
/* ==========================================================================
   SETTINGS - MODEL PROVIDER
   ========================================================================== */

document.addEventListener('DOMContentLoaded', function () {

    const providerSelect =
        document.getElementById('model-provider');

    const providerDataInput =
        document.getElementById('provider_data');

    if (!providerSelect || !providerDataInput) {
        return;
    }

    const providerFields =
        document.querySelectorAll('.settings-provider-fields');

    const providerUrl =
        document.getElementById('provider_url');

    const llamaModelDir =
        document.getElementById('llamacpp_model_dir');

    const testButton =
        document.getElementById('provider-test');

    const providerStatus =
        document.getElementById('provider-status');

    let currentProvider = providerSelect.value;


    /* --------------------------------------------------------------
       LOAD SELECTED PROVIDER DATA
       -------------------------------------------------------------- */

    function updateProviderFields()
    {
        const provider = providerSelect.value;
        const data = providerData[provider] || {};

        providerFields.forEach(field => {

            field.hidden =
                field.dataset.provider !== provider;

        });

        if (providerUrl) {
            providerUrl.value = data.url || '';
        }

        if (llamaModelDir) {
            llamaModelDir.value = data.model_dir || '';
        }
    }


    /* --------------------------------------------------------------
       STORE CURRENT PROVIDER DATA
       -------------------------------------------------------------- */

    function storeProviderFields()
    {
        if (!providerData[currentProvider]) {
            providerData[currentProvider] = {};
        }

        if (providerUrl) {
            providerData[currentProvider].url =
                providerUrl.value;
        }

        if (currentProvider === 'llamacpp' && llamaModelDir) {
            providerData[currentProvider].model_dir =
                llamaModelDir.value;
        }

        if (providerDataInput) {
            providerDataInput.value =
                JSON.stringify(providerData);
        }
    }


    /* --------------------------------------------------------------
       PROVIDER CHANGE
       -------------------------------------------------------------- */

    providerSelect.addEventListener('change', function () {

        // Előző provider módosításainak megőrzése
        storeProviderFields();

        // Új provider lesz az aktuális
        currentProvider = providerSelect.value;

        // Új provider adatainak betöltése
        updateProviderFields();

        // Hidden JSON frissítése
        if (providerDataInput) {
            providerDataInput.value =
                JSON.stringify(providerData);
        }

        // Régi teszteredmény törlése
        if (providerStatus) {
            providerStatus.textContent = '';
            providerStatus.classList.remove(
                'success',
                'error'
            );
        }

    });


    /* --------------------------------------------------------------
       PROVIDER CONNECTION TEST
       -------------------------------------------------------------- */

    if (testButton && providerStatus && providerUrl) {

        testButton.addEventListener('click', async function () {

            providerStatus.textContent = '...';
            providerStatus.classList.remove(
                'success',
                'error'
            );

            const data = new FormData();

            data.append(
                'provider',
                providerSelect.value
            );

            data.append(
                'url',
                providerUrl.value
            );

            try {

                const response = await fetch(
                    '/main/providertest',
                    {
                        method: 'POST',
                        body: data
                    }
                );

                const result = await response.json();

                providerStatus.textContent =
                    result.message;

                providerStatus.classList.toggle(
                    'success',
                    result.success
                );

                providerStatus.classList.toggle(
                    'error',
                    !result.success
                );

            } catch (error) {

                providerStatus.textContent =
                    'Connection error';

                providerStatus.classList.remove(
                    'success'
                );

                providerStatus.classList.add(
                    'error'
                );
            }

        });
    }


    /* --------------------------------------------------------------
       FORM SUBMIT
       -------------------------------------------------------------- */

    const settingsForm =
        providerSelect.closest('form');

    if (settingsForm) {

        settingsForm.addEventListener(
            'submit',
            function () {

                // Az utoljára szerkesztett provider adatai is
                // kerüljenek bele a mentendő JSON-ba.
                storeProviderFields();

            }
        );
    }


    /* --------------------------------------------------------------
       INITIAL STATE
       -------------------------------------------------------------- */

    updateProviderFields();

    if (providerDataInput) {
        providerDataInput.value =
            JSON.stringify(providerData);
    }

});

/* ==========================================================================
   MODEL EDITOR - CAPABILITIES / TOOLS
   ========================================================================== */

document.addEventListener('DOMContentLoaded', function () {

    const toolsSwitch =
        document.getElementById('cap-tools');

    const toolsBox =
        document.getElementById('model-tools');

    const toolSelect =
        document.getElementById('tool-select');

    const toolAdd =
        document.getElementById('tool-add');

    const toolList =
        document.getElementById('active-tools');

    const toolValue =
        document.getElementById('builtin-tools');


    if (!toolsSwitch || !toolsBox) {
        return;
    }


    /* ----------------------------------------------------------
       TOOLS ENABLE / DISABLE
       ---------------------------------------------------------- */

    function updateToolsState()
    {
        toolsBox.classList.toggle(
            'is-disabled',
            !toolsSwitch.checked
        );
    }

    toolsSwitch.addEventListener(
        'change',
        updateToolsState
    );

    updateToolsState();


    if (!toolSelect || !toolAdd || !toolList || !toolValue) {
        return;
    }


    /* ----------------------------------------------------------
       READ CURRENT TOOLS
       ---------------------------------------------------------- */

    function getTools()
    {
        return Array.from(
            toolList.querySelectorAll('.model-tool-item')
        ).map(item => item.dataset.tool);
    }


    /* ----------------------------------------------------------
       UPDATE HIDDEN VALUE
       ---------------------------------------------------------- */

    function updateToolValue()
    {
        toolValue.value =
            JSON.stringify(getTools());
    }


    /* ----------------------------------------------------------
       CREATE TOOL
       ---------------------------------------------------------- */

    function createTool(tool)
    {
        const item = document.createElement('div');

        item.className = 'model-tool-item';
        item.dataset.tool = tool;

        const name = document.createElement('span');
        name.textContent = tool;

        const remove = document.createElement('button');

        remove.type = 'button';
        remove.className = 'model-tool-remove';
        remove.title = 'Tool eltávolítása';
        remove.textContent = '×';

        item.appendChild(name);
        item.appendChild(remove);

        toolList.appendChild(item);
    }


    /* ----------------------------------------------------------
       ADD TOOL
       ---------------------------------------------------------- */

    toolAdd.addEventListener('click', function () {

        const tool = toolSelect.value;

        if (!tool) {
            return;
        }

        if (getTools().includes(tool)) {
            return;
        }

        createTool(tool);

        toolSelect.value = '';

        updateToolValue();
    });


    /* ----------------------------------------------------------
       REMOVE TOOL
       ---------------------------------------------------------- */

    toolList.addEventListener('click', function (event) {

        const button =
            event.target.closest('.model-tool-remove');

        if (!button) {
            return;
        }

        const item =
            button.closest('.model-tool-item');

        if (item) {
            item.remove();
            updateToolValue();
        }

    });


    updateToolValue();

});

/* ==========================================================================
   MODEL EDITOR - RAG
   ========================================================================== */

document.addEventListener('DOMContentLoaded', function () {

    const ragEnabled =
        document.getElementById('rag-enabled');

    const ragSettings =
        document.getElementById('rag-settings');

    const ragSelect =
        document.getElementById('rag-item-select');

    const ragAdd =
        document.getElementById('rag-item-add');

    const ragList =
        document.getElementById('rag-item-list');

    const ragValue =
        document.getElementById('rag-items');


    if (!ragEnabled || !ragSettings) {
        return;
    }


    /* ----------------------------------------------------------
       ENABLE / DISABLE
       ---------------------------------------------------------- */

    function updateRagState()
    {
        ragSettings.classList.toggle(
            'is-disabled',
            !ragEnabled.checked
        );
    }

    ragEnabled.addEventListener(
        'change',
        updateRagState
    );

    updateRagState();


    if (!ragSelect || !ragAdd || !ragList || !ragValue) {
        return;
    }


    /* ----------------------------------------------------------
       CURRENT ITEMS
       ---------------------------------------------------------- */

    function getRagItems()
    {
        return Array.from(
            ragList.querySelectorAll('.model-tool-item')
        ).map(item => item.dataset.ragId);
    }


    function updateRagValue()
    {
        ragValue.value =
            JSON.stringify(getRagItems());
    }


    /* ----------------------------------------------------------
       ADD
       ---------------------------------------------------------- */

    ragAdd.addEventListener('click', function () {

        const id =
            ragSelect.value;

        const option =
            ragSelect.options[ragSelect.selectedIndex];

        if (!id) {
            return;
        }

        if (getRagItems().includes(id)) {
            return;
        }


        const item =
            document.createElement('div');

        item.className = 'model-tool-item';
        item.dataset.ragId = id;


        const name =
            document.createElement('span');

        name.textContent =
            option.textContent.trim();


        const remove =
            document.createElement('button');

        remove.type = 'button';
        remove.className = 'model-tool-remove';
        remove.title = 'Tudásanyag eltávolítása';
        remove.textContent = '×';


        item.appendChild(name);
        item.appendChild(remove);

        ragList.appendChild(item);

        ragSelect.value = '';

        updateRagValue();

    });


    /* ----------------------------------------------------------
       REMOVE
       ---------------------------------------------------------- */

    ragList.addEventListener('click', function (event) {

        const button =
            event.target.closest('.model-tool-remove');

        if (!button) {
            return;
        }

        const item =
            button.closest('.model-tool-item');

        if (item) {
            item.remove();
            updateRagValue();
        }

    });


    updateRagValue();

});

/* ==========================================================================
   KNOWLEDGE EDITOR - TABS
   ========================================================================== */

document.addEventListener('DOMContentLoaded', function () {

    const tabs =
        document.querySelectorAll('[data-knowledge-tab]');

    const panels =
        document.querySelectorAll('[data-knowledge-panel]');

    if (!tabs.length || !panels.length) {
        return;
    }

    tabs.forEach(tab => {

        tab.addEventListener('click', function () {

            const target =
                this.dataset.knowledgeTab;

            tabs.forEach(item => {
                item.classList.remove('active');
            });

            panels.forEach(panel => {

                panel.hidden =
                    panel.dataset.knowledgePanel !== target;

            });

            this.classList.add('active');

        });

    });

});

/* ==========================================================================
   KNOWLEDGE - DELETE
   ========================================================================== */

document.addEventListener('DOMContentLoaded', function () {

    document.querySelectorAll('.knowledge-delete').forEach(function (button) {

        button.addEventListener('click', function (event) {

            event.preventDefault();
            event.stopPropagation();

            const id = button.dataset.id;

            _confirm(
                LANG.MSG_YOU_WANT_DELETE,
                async function () {

                    modalClose('modal_pconfirm');

                    const data = new FormData();
                    data.append('id', id);

                    try
                      {
                        const response = await fetch(
                            '/main/ragdelete',
                            {
                                method: 'POST',
                                body: data
                            }
                        );

                        const result = await response.json();

                        if (!result.success)
                          {
                            throw new Error(
                                'Knowledge delete failed'
                            );
                          }

                        window.location.reload();
                      }
                    catch (error)
                      {
                        console.error(
                            'Knowledge delete error:',
                            error
                        );
                      }
                }
            );

        });

    });

});

/* ==========================================================================
   SETTINGS - XTTS
   ========================================================================== */

document.addEventListener('DOMContentLoaded', function () {

    const testButton =
        document.getElementById('xtts-test');

    const status =
        document.getElementById('xtts-status');

    const serviceStatus =
        document.getElementById('xtts-service-status');

    const url =
        document.querySelector(
            '[name="tts_data[xtts][url]"]'
        );

    if (!testButton || !status || !url) {
        return;
    }


    /* --------------------------------------------------------------
       XTTS TEST
       -------------------------------------------------------------- */

    async function testXTTS()
    {
        const data = new FormData();

        data.append('provider', 'xtts');
        data.append('url', url.value);

        try {

            const response = await fetch(
                '/main/ttstest',
                {
                    method: 'POST',
                    body: data
                }
            );

            return await response.json();

        } catch (error) {

            return {
                success: false,
                message: 'Connection error'
            };
        }
    }


    /* --------------------------------------------------------------
       SERVICE STATUS
       -------------------------------------------------------------- */

    async function updateXTTSStatus()
    {
        if (!serviceStatus) {
            return;
        }

        const dot =
            serviceStatus.querySelector('.status-dot');

        const text =
            serviceStatus.querySelector('.status-text');

        const result = await testXTTS();

        serviceStatus.classList.toggle(
            'running',
            result.success
        );

        serviceStatus.classList.toggle(
            'stopped',
            !result.success
        );

        if (text) {
            text.textContent =
                result.success
                    ? serviceStatus.dataset.running
                    : serviceStatus.dataset.stopped;
        }
    }


    /* --------------------------------------------------------------
       CONNECTION TEST BUTTON
       -------------------------------------------------------------- */

    testButton.addEventListener('click', async function () {

        status.textContent = '...';

        status.classList.remove(
            'success',
            'error'
        );

        const result = await testXTTS();

        status.textContent =
            result.message;

        status.classList.toggle(
            'success',
            result.success
        );

        status.classList.toggle(
            'error',
            !result.success
        );

        // A fejléc státuszát is frissítjük.
        updateXTTSStatus();

    });


    /* --------------------------------------------------------------
       INITIAL STATUS
       -------------------------------------------------------------- */

    updateXTTSStatus();

});

/* ==========================================================================
   SETTINGS - PIPER
   ========================================================================== */

document.addEventListener('DOMContentLoaded', function () {

    const testButton =
        document.getElementById('piper-test');

    const status =
        document.getElementById('piper-status');

    const serviceStatus =
        document.getElementById('piper-service-status');

    const binary =
        document.querySelector(
            '[name="tts_data[piper][binary]"]'
        );

    const modelDir =
        document.querySelector(
            '[name="tts_data[piper][model_dir]"]'
        );

    if (!testButton || !status || !binary || !modelDir) {
        return;
    }


    /* --------------------------------------------------------------
       PIPER TEST
       -------------------------------------------------------------- */

    async function testPiper()
      {
        const data = new FormData();

        data.append('provider', 'piper');
        data.append('binary', binary.value);
        data.append('model_dir', modelDir.value);

        try
          {
            const response = await fetch(
                '/main/ttstest',
                {
                    method: 'POST',
                    body: data
                }
            );

            return await response.json();
          }
        catch (error)
          {
            return {
                success: false,
                message: 'Connection error'
            };
          }
      }


    /* --------------------------------------------------------------
       PIPER STATUS
       -------------------------------------------------------------- */

    async function updatePiperStatus()
      {
        if (!serviceStatus) {
            return;
        }

        const text =
            serviceStatus.querySelector('.status-text');

        const result = await testPiper();

        serviceStatus.classList.toggle(
            'running',
            result.success
        );

        serviceStatus.classList.toggle(
            'stopped',
            !result.success
        );

        if (text)
          {
            text.textContent =
                result.success
                    ? serviceStatus.dataset.running
                    : serviceStatus.dataset.stopped;
          }
      }


    /* --------------------------------------------------------------
       CONNECTION TEST BUTTON
       -------------------------------------------------------------- */

    testButton.addEventListener('click', async function () {

        status.textContent = '...';

        status.classList.remove(
            'success',
            'error'
        );

        const result = await testPiper();

        status.textContent =
            result.message;

        status.classList.toggle(
            'success',
            result.success
        );

        status.classList.toggle(
            'error',
            !result.success
        );

        updatePiperStatus();

    });


    /* --------------------------------------------------------------
       INITIAL STATUS
       -------------------------------------------------------------- */

    updatePiperStatus();

});

/* ==========================================================================
   SETTINGS - ESPEAK
   ========================================================================== */

document.addEventListener('DOMContentLoaded', function () {

    const testButton =
        document.getElementById('espeak-test');

    const status =
        document.getElementById('espeak-status');

    const serviceStatus =
        document.getElementById('espeak-service-status');

    const binary =
        document.querySelector(
            '[name="tts_data[espeak][binary]"]'
        );

    const voice =
        document.querySelector(
            '[name="tts_data[espeak][voice]"]'
        );

    if (!testButton || !status || !binary || !voice) {
        return;
    }


    /* --------------------------------------------------------------
       ESPEAK TEST
       -------------------------------------------------------------- */

    async function testEspeak()
      {
        const data = new FormData();

        data.append('provider', 'espeak');
        data.append('binary', binary.value);
        data.append('voice', voice.value);

        try
          {
            const response = await fetch(
                '/main/ttstest',
                {
                    method: 'POST',
                    body: data
                }
            );

            return await response.json();
          }
        catch (error)
          {
            return {
                success: false,
                message: 'Connection error'
            };
          }
      }


    /* --------------------------------------------------------------
       ESPEAK STATUS
       -------------------------------------------------------------- */

    async function updateEspeakStatus()
      {
        if (!serviceStatus) {
            return;
        }

        const text =
            serviceStatus.querySelector('.status-text');

        const result = await testEspeak();

        serviceStatus.classList.toggle(
            'running',
            result.success
        );

        serviceStatus.classList.toggle(
            'stopped',
            !result.success
        );

        if (text)
          {
            text.textContent =
                result.success
                    ? serviceStatus.dataset.running
                    : serviceStatus.dataset.stopped;
          }
      }


    /* --------------------------------------------------------------
       CONNECTION TEST BUTTON
       -------------------------------------------------------------- */

    testButton.addEventListener('click', async function () {

        status.textContent = '...';

        status.classList.remove(
            'success',
            'error'
        );

        const result = await testEspeak();

        status.textContent =
            result.message;

        status.classList.toggle(
            'success',
            result.success
        );

        status.classList.toggle(
            'error',
            !result.success
        );

        updateEspeakStatus();

    });


    /* --------------------------------------------------------------
       INITIAL STATUS
       -------------------------------------------------------------- */

    updateEspeakStatus();

});

/* ==========================================================================
   VOICES
   ========================================================================== */
document.addEventListener('DOMContentLoaded', function () {

/* --------------------------------------------------------------
  XTTS PARAMETERS
  -------------------------------------------------------------- */
    const voiceXttsSpeed =
        document.getElementById('voice-xtts-speed');

    const voiceXttsSpeedNumber =
        document.getElementById('voice-xtts-speed-number');

    const voiceXttsTemperature =
        document.getElementById('voice-xtts-temperature');

    const voiceXttsTemperatureNumber =
        document.getElementById('voice-xtts-temperature-number');
/* --------------------------------------------------------------
  PIPER PARAMETERS
  -------------------------------------------------------------- */
    const voicePiperLengthScale =
        document.getElementById('voice-piper-length-scale');

    const voicePiperLengthScaleNumber =
        document.getElementById('voice-piper-length-scale-number');

    const voicePiperNoiseW =
        document.getElementById('voice-piper-noise-w');

    const voicePiperNoiseWNumber =
        document.getElementById('voice-piper-noise-w-number');
/* --------------------------------------------------------------
  PIPER PARAMETERS
  -------------------------------------------------------------- */
    const voiceFieldsEspeak =
        document.getElementById('voice-fields-espeak');

    const voiceEspeakVoice =
        document.getElementById('voice-espeak-voice');

    const voiceEspeakPitch =
        document.getElementById('voice-espeak-pitch');

    const voiceEspeakPitchNumber =
        document.getElementById('voice-espeak-pitch-number');

    const voiceEspeakSpeed =
        document.getElementById('voice-espeak-speed');

    const voiceEspeakSpeedNumber =
        document.getElementById('voice-espeak-speed-number');


    const voiceFieldsPiper =
        document.getElementById('voice-fields-piper');

    const voiceSamplePiper =
        document.getElementById('voice-sample-piper');

    const voiceSave =
        document.getElementById('voice-save');

    const voiceFieldsXTTS =
        document.getElementById('voice-fields-xtts');

    const voiceSample =
        document.getElementById('voice-sample');

    const voiceReftext =
        document.getElementById('voice-reftext');

    const voiceEditor =
        document.getElementById('voice-editor');

    const voiceId =
        document.getElementById('voice-id');

    const voiceName =
        document.getElementById('voice-name');

    const voiceProvider =
        document.getElementById('voice-provider');

    const voiceAdd =
        document.getElementById('voice-add');

    if (!voiceEditor || !voiceProvider) {
    return;
    }

    function updateVoiceProviderFields()
      {
        const provider = voiceProvider.value;

        voiceFieldsXTTS.hidden =
            provider !== 'xtts';

        voiceFieldsPiper.hidden =
            provider !== 'piper';

        voiceFieldsEspeak.hidden =
            provider !== 'espeak';
      }    

    /* --------------------------------------------------------------
      OPEN EXISTING VOICE
      -------------------------------------------------------------- */

    document.querySelectorAll('.voice-item').forEach(function (item) {

        item.addEventListener('click', function (event) {

            if (event.target.closest('.voice-delete'))
              {
                return;
              }

            const id = parseInt(
                item.dataset.id,
                10
            );

            const voice = voiceData.find(function (voice) {
                return parseInt(voice.id, 10) === id;
            });

            if (!voice)
              {
                return;
              }

            document.querySelectorAll('.voice-item').forEach(function (row) {
                row.classList.remove('active');
            });

            item.classList.add('active');

            voiceId.value =
                voice.id;

            voiceName.value =
                voice.name ?? '';

            voiceProvider.value =
                voice.provider ?? '';

            voiceSample.value =
                voice.sample ?? '';

            voiceReftext.value =
                voice.reftext ?? '';

            voiceSamplePiper.value =
                voice.provider === 'piper'
                    ? (voice.sample ?? '')
                    : '';                

            const parameters =
                voice.parameters ?? {};

            /* ESPEAK */

            voiceEspeakVoice.value =
                parameters.voice ?? 'mb-hu1';

            voiceEspeakPitch.value =
                parameters.pitch ?? 70;

            voiceEspeakPitchNumber.value =
                parameters.pitch ?? 70;

            voiceEspeakSpeed.value =
                parameters.speed ?? 100;

            voiceEspeakSpeedNumber.value =
                parameters.speed ?? 100;

            /* XTTS */

            voiceXttsSpeed.value =
                parameters.speed ?? 1.00;

            voiceXttsSpeedNumber.value =
                parameters.speed ?? 1.00;

            voiceXttsTemperature.value =
                parameters.temperature ?? 0.65;

            voiceXttsTemperatureNumber.value =
                parameters.temperature ?? 0.65;

            /* PIPER */

            voicePiperLengthScale.value =
                parameters.length_scale ?? 1.00;

            voicePiperLengthScaleNumber.value =
                parameters.length_scale ?? 1.00;

            voicePiperNoiseW.value =
                parameters.noise_w ?? 0.80;

            voicePiperNoiseWNumber.value =
                parameters.noise_w ?? 0.80;                

            updateVoiceProviderFields();

            voiceEditor.hidden = false;
        });

    });


    /* --------------------------------------------------------------
      NEW VOICE
      -------------------------------------------------------------- */

    if (voiceAdd)
      {
        voiceAdd.addEventListener('click', function () {

            document.querySelectorAll('.voice-item').forEach(function (row) {
                row.classList.remove('active');
            });

            voiceId.value = '';
            voiceName.value = '';

            if (voiceProvider.options.length > 0)
              {
                voiceProvider.selectedIndex = 0;
              }
            voiceSample.value = '';
            voiceReftext.value = '';
            voiceSamplePiper.value = ''; 
            /* ESPEAK */
            voiceEspeakVoice.value = 'mb-hu1';
            voiceEspeakPitch.value = 70;
            voiceEspeakPitchNumber.value = 70;
            voiceEspeakSpeed.value = 100;
            voiceEspeakSpeedNumber.value = 100;                       
            /* XTTS */
            voiceXttsSpeed.value = 1.00;
            voiceXttsSpeedNumber.value = 1.00;
            voiceXttsTemperature.value = 0.65;
            voiceXttsTemperatureNumber.value = 0.65;
            /* PIPER */
            voicePiperLengthScale.value = 1.00;
            voicePiperLengthScaleNumber.value = 1.00;
            voicePiperNoiseW.value = 0.80;
            voicePiperNoiseWNumber.value = 0.80;
            updateVoiceProviderFields();

            voiceEditor.hidden = false;
        });
      }

    /* --------------------------------------------------------------
      CHANGE PROVIDER
      -------------------------------------------------------------- */

    voiceProvider.addEventListener('change', function () {
        updateVoiceProviderFields();
    });
    
/* --------------------------------------------------------------
  SAVE VOICE
  -------------------------------------------------------------- */

    voiceSave.addEventListener('click', async function () {

        const data = new FormData();

        data.append(
            'id',
            voiceId.value
        );

        data.append(
            'name',
            voiceName.value.trim()
        );

        data.append(
            'provider',
            voiceProvider.value
        );

        let sample  = '';
        let reftext = '';

        if (voiceProvider.value === 'xtts')
          {
            sample  = voiceSample.value.trim();
            reftext = voiceReftext.value.trim();
            data.append(
                'parameters[speed]',
                voiceXttsSpeed.value
            );
            data.append(
                'parameters[temperature]',
                voiceXttsTemperature.value
            );            
          }
        else if (voiceProvider.value === 'piper')
          {
            sample = voiceSamplePiper.value.trim();
            data.append(
                'parameters[length_scale]',
                voicePiperLengthScale.value
            );

            data.append(
                'parameters[noise_w]',
                voicePiperNoiseW.value
            );            
          }
        else if (voiceProvider.value === 'espeak')
          {
            data.append(
                'parameters[voice]',
                voiceEspeakVoice.value.trim()
            );

            data.append(
                'parameters[pitch]',
                voiceEspeakPitch.value
            );

            data.append(
                'parameters[speed]',
                voiceEspeakSpeed.value
            );
          }          

          data.append(
              'sample',
              sample
          );

          data.append(
              'reftext',
              reftext
          );

        try
          {
            const response = await fetch('/main/voicesave', {
                method: 'POST',
                body: data
            });

            const result = await response.json();

            if (!result.success)
              {
                throw new Error('Voice save failed');
              }

            window.location.reload();
          }
        catch (error)
          {
            console.error('Voice save error:', error);
          }

    }); 
    
/* --------------------------------------------------------------
  ESPEAK RANGES
  -------------------------------------------------------------- */

      voiceEspeakPitch.addEventListener('input', function () {
          voiceEspeakPitchNumber.value =
              voiceEspeakPitch.value;
      });

      voiceEspeakPitchNumber.addEventListener('input', function () {
          voiceEspeakPitch.value =
              voiceEspeakPitchNumber.value;
      });

      voiceEspeakSpeed.addEventListener('input', function () {
          voiceEspeakSpeedNumber.value =
              voiceEspeakSpeed.value;
      });

      voiceEspeakSpeedNumber.addEventListener('input', function () {
          voiceEspeakSpeed.value =
              voiceEspeakSpeedNumber.value;
      });

    /* --------------------------------------------------------------
      XTTS RANGES
      -------------------------------------------------------------- */

    voiceXttsSpeed.addEventListener('input', function () {
        voiceXttsSpeedNumber.value =
            voiceXttsSpeed.value;
    });

    voiceXttsSpeedNumber.addEventListener('input', function () {
        voiceXttsSpeed.value =
            voiceXttsSpeedNumber.value;
    });

    voiceXttsTemperature.addEventListener('input', function () {
        voiceXttsTemperatureNumber.value =
            voiceXttsTemperature.value;
    });

    voiceXttsTemperatureNumber.addEventListener('input', function () {
        voiceXttsTemperature.value =
            voiceXttsTemperatureNumber.value;
    });


    /* --------------------------------------------------------------
      PIPER RANGES
      -------------------------------------------------------------- */

    voicePiperLengthScale.addEventListener('input', function () {
        voicePiperLengthScaleNumber.value =
            voicePiperLengthScale.value;
    });

    voicePiperLengthScaleNumber.addEventListener('input', function () {
        voicePiperLengthScale.value =
            voicePiperLengthScaleNumber.value;
    });

    voicePiperNoiseW.addEventListener('input', function () {
        voicePiperNoiseWNumber.value =
            voicePiperNoiseW.value;
    });

    voicePiperNoiseWNumber.addEventListener('input', function () {
        voicePiperNoiseW.value =
            voicePiperNoiseWNumber.value;
    });      
      
/* --------------------------------------------------------------
   DELETE VOICE
   -------------------------------------------------------------- */

      document.querySelectorAll('.voice-delete').forEach(function (button) {

          button.addEventListener('click', function (event) {

              event.stopPropagation();

              const id =
                  button.dataset.id;

              _confirm(
                  'Biztosan törölni szeretnéd ezt a hangot?',
                  async function () {

                      modalClose('modal_pconfirm');

                      const data =
                          new FormData();

                      data.append(
                          'id',
                          id
                      );

                      try
                        {
                          const response = await fetch(
                              '/main/voicedelete',
                              {
                                  method: 'POST',
                                  body: data
                              }
                          );

                          const result =
                              await response.json();

                          if (!result.success)
                            {
                              throw new Error(
                                  'Voice delete failed'
                              );
                            }

                          window.location.reload();
                        }
                      catch (error)
                        {
                          console.error(
                              'Voice delete error:',
                              error
                          );
                        }
                  }
              );

          });

      });      
      
});
/* --------------------------------------------------------------
   WHISPER-TEST
   -------------------------------------------------------------- */
document.addEventListener('DOMContentLoaded', function () {

    const testButton =
        document.getElementById('whisper-test');

    const serviceStatus =
        document.getElementById('whisper-status');

    const host =
        document.querySelector(
            '[name="whisper_host"]'
        );

    const port =
        document.querySelector(
            '[name="whisper_port"]'
        );

    if (!testButton || !serviceStatus || !host || !port) {
        return;
    }


    /* --------------------------------------------------------------
       WHISPER TEST
       -------------------------------------------------------------- */

    async function testWhisper()
    {
        const data = new FormData();

        data.append('provider', 'whisper');
        data.append('host', host.value);
        data.append('port', port.value);

        try {

            const response = await fetch(
                '/main/stttest',
                {
                    method: 'POST',
                    body: data
                }
            );

            return await response.json();

        } catch (error) {

            return {
                success: false,
                message: 'Connection error'
            };
        }
    }


    /* --------------------------------------------------------------
       SERVER STATUS
       -------------------------------------------------------------- */

    async function updateWhisperStatus()
    {
        const text =
            serviceStatus.querySelector(
                '#whisper-status-text'
            );

        const result = await testWhisper();

        serviceStatus.classList.toggle(
            'running',
            result.success
        );

        serviceStatus.classList.toggle(
            'stopped',
            !result.success
        );

        if (text) {
            text.textContent =
                result.success
                    ? serviceStatus.dataset.running
                    : serviceStatus.dataset.stopped;
        }
    }


    /* --------------------------------------------------------------
       CONNECTION TEST BUTTON
       -------------------------------------------------------------- */

    testButton.addEventListener('click', async function () {

        const result = await testWhisper();

        serviceStatus.classList.toggle(
            'running',
            result.success
        );

        serviceStatus.classList.toggle(
            'stopped',
            !result.success
        );

        const text =
            serviceStatus.querySelector(
                '#whisper-status-text'
            );

        if (text) {
            text.textContent = result.message;
        }

    });


    /* --------------------------------------------------------------
       INITIAL STATUS
       -------------------------------------------------------------- */

    updateWhisperStatus();

}); 

document.addEventListener('DOMContentLoaded', function () {
/* --------------------------------------------------------------
   DELETE MODEL
   -------------------------------------------------------------- */

  document.querySelectorAll('.model-delete-btn').forEach(function (button) {

      button.addEventListener('click', function (event) {

          event.preventDefault();
          event.stopPropagation();

          const id =
              button.dataset.id;

          _confirm(
              LANG.MSG_YOU_WANT_DELETE,
              async function () {

                  modalClose('modal_pconfirm');

                  const data =
                      new FormData();

                  data.append(
                      'id',
                      id
                  );

                  try
                    {
                      const response = await fetch(
                          '/main/modeldelete',
                          {
                              method: 'POST',
                              body: data
                          }
                      );

                      const result =
                          await response.json();

                      if (!result.success)
                        {
                          throw new Error(
                              'Model delete failed'
                          );
                        }

                      window.location.reload();
                    }
                  catch (error)
                    {
                      console.error(
                          'Model delete error:',
                          error
                      );
                    }
              }
          );

      });

  });
}); 


//-----------------------------------------------------------------------------
//  
//-----------------------------------------------------------------------------
function loadGalleryImage(img) 
  {
    let filename = img.split('/').pop().split('?')[0];
    document.getElementById('model-avatar').src = img;
    document.getElementById('hidden-image-input').value = filename;
  }
function addImage(mid)
{
    document.getElementById(mid).style.display = 'block';
}

function closeImageModal(mid)
{
    document.getElementById(mid).style.display = 'none';
}
// Settings log viewer: reads only while its tab is visible.
document.addEventListener('DOMContentLoaded', function () {
    const panel = document.getElementById('settings-logs');
    if (!panel) return;
    const content = document.getElementById('logs-content');
    const status = document.getElementById('logs-status');
    const auto = document.getElementById('logs-auto');
    const refresh = document.getElementById('logs-refresh');
    let controller = null;
    let version = 0;
    let timer = null;
    let raw = '';
    function visible() {
        return panel.classList.contains('active') && !panel.hidden && !document.hidden;
    }
    function cancel() {
        version++;
        if (controller) controller.abort();
        controller = null;
        refresh.disabled = false;
        clearTimeout(timer);
    }
    function schedule() {
        clearTimeout(timer);
        if (visible() && auto.checked) timer = setTimeout(read, 5000);
    }
    async function read() {
        if (!visible()) return;
        cancel();
        const current = version;
        const selected = panel.querySelector('[name="log_view_source"]:checked');
        if (!selected) return;
        controller = new AbortController();
        refresh.disabled = true;
        status.textContent = 'Betöltés…';
        try {
            const response = await fetch('/main/logread?source=' + encodeURIComponent(selected.value), {
                cache: 'no-store', signal: controller.signal,
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.error || 'A napló nem olvasható.');
            if (current !== version || !visible()) return;
            raw = result.text;
            const box = content.parentElement;
            const follow = box.scrollHeight - box.scrollTop - box.clientHeight < 40 || !content.textContent;
            content.textContent = raw;
            if (typeof w3CodeColor === 'function') w3CodeColor(content, 'log');
            if (follow) box.scrollTop = box.scrollHeight;
            status.textContent = raw ? result.lines + ' sor · ' + new Date(result.updated).toLocaleTimeString() + (result.limited ? ' · csak a napló vége' : '') : 'A napló üres.';
        } catch (error) {
            if (current !== version || error.name === 'AbortError') return;
            raw = '';
            content.textContent = '';
            status.textContent = error.message;
        } finally {
            if (current === version) {
                controller = null;
                refresh.disabled = false;
                schedule();
            }
        }
    }
    refresh.addEventListener('click', read);
    panel.querySelectorAll('[name="log_view_source"]').forEach(input => input.addEventListener('change', function () {
        raw = ''; content.textContent = ''; read();
    }));
    auto.addEventListener('change', schedule);
    document.querySelectorAll('.settings-tab').forEach(tab => tab.addEventListener('click', function () {
        cancel();
        if (visible()) read();
    }));
    document.addEventListener('visibilitychange', function () {
        cancel();
        if (visible() && auto.checked) read();
    });
    window.addEventListener('pagehide', cancel);
    document.getElementById('logs-copy').addEventListener('click', async function () {
        try {
            if (navigator.clipboard && window.isSecureContext) await navigator.clipboard.writeText(raw);
            else {
                const area = document.createElement('textarea');
                area.value = raw;
                area.style.position = 'fixed'; area.style.opacity = '0';
                document.body.appendChild(area);
                try {
                    area.select();
                    if (!document.execCommand('copy')) throw new Error('A másolás nem engedélyezett.');
                } finally { area.remove(); }
            }
            status.textContent = 'Napló másolva.';
        } catch (error) { status.textContent = 'Nem sikerült másolni; a szöveg kézzel is kijelölhető.'; }
    });
});
