// ==========================================================================
// ✨ FEJLESZTŐI BEÁLLÍTÁSOK (PILÓTAFÜLKE KONFIGURÁCIÓ) ✨
// ==========================================================================
const AUTOMATIC_STOP_WORD = "enter"; 
const SHORTCUT_KEY_CODE = "KeyW"; 
const SHORTCUT_KEY_LETTER = "w";
const RECOGNITION_LANG = "hu-HU";

// Csak a tiszta HTML ID stringeket tároljuk itt, nem a fizikai elemet!
const INPUT_ELEMENT_ID = "chat_message";
const STATUS_ELEMENT_ID = "status";
const MIC_BUTTON_ID = "btn_mic"; // A mikrofon gömböc gombod ID-ja az animációhoz
// ==========================================================================

const humanFriendlyKey = SHORTCUT_KEY_LETTER.toUpperCase();
let recorder = null;      
let recognition = null;   
let browserRecognitionActive = false;
let chunks = [];   
let audioStream = null;
let msg = '';

// --- 🌐 SPEECH RECOGNITION INICIALIZÁLÁS ---
/*try {
  const proto = window.SpeechRecognition || window.webkitSpeechRecognition;
  if (proto) {
    recognition = new proto();
    recognition.lang = RECOGNITION_LANG;           
    recognition.continuous = true;        
    recognition.interimResults = true;    
    statusview("Valós idejű szövegfelismerés inicializálva.");
  } else {
    statusview("Figyelmeztetés: A böngésző nem támogatja a SpeechRecognition-t.", 1);
  }
} catch (e) {
  statusview("Inicializációs hiba:", e);
}*/

// --- 🔴 ATOMBIZTOS LEÁLLÍTÓ FÜGGVÉNY ---
function stopRecording() {
  statusview("stopRecording() végrehajtása elindult!");
  
  // Vizuális visszajelzés: levesszük a piros aktív stílust a gombról
  const micBtn = document.getElementById(MIC_BUTTON_ID);
  if (micBtn) { 
    micBtn.classList.remove("toggle-active"); 
    document.getElementById(MIC_BUTTON_ID).innerHTML = '<img src="'+DIR_HOST+'/assets/images/mic.png">';
  }

  if (recognition) {
    try { recognition.stop(); } catch(e) { console.log(e); }
  }
  
  if (recorder && (recorder.state === "active" || recorder.state === "recording")) {
    try { recorder.stop(); } catch(e) { console.log(e); }
    statusview("Felvétel leállítva. Küldés...");
  } else {
    statusview("Készen áll.");
  }
} 

function triggerVoiceToggle()
{
    // A beszédfelismerés globálisan ki van kapcsolva
    if (parseInt(SPEECH_ENABLED) !== 1)
    {
        statusview("A beszédfelismerés nincs engedélyezve.");
        return;
    }

    // Böngésző saját beszédfelismerése
    if (SPEECH === "Browser")
    {
        triggerBrowserRecognition();
        return;
    }

    // Helyi Whisper
    if (SPEECH === "Whisper")
    {
        if (!recorder || recorder.state === "inactive" || recorder.state === "")
        {
            startRecordingSequence();
        }
        else if (
            recorder &&
            (recorder.state === "active" || recorder.state === "recording")
        )
        {
            stopRecording();
        }

        return;
    }

    statusview("Ismeretlen beszédfelismerő: " + SPEECH, 1);
}
function triggerBrowserRecognition()
{
    // Ha már fut, akkor most MI állítjuk le
    if (browserRecognitionActive && recognition)
    {
        browserRecognitionActive = false;
        recognition.stop();
        return;
    }

    // Első használatkor létrehozzuk
    if (!recognition)
    {
        const SpeechRecognition =
            window.SpeechRecognition ||
            window.webkitSpeechRecognition;

        if (!SpeechRecognition)
        {
            statusview(
                "A böngésző nem támogatja a SpeechRecognition API-t.",
                1
            );
            return;
        }

        recognition = new SpeechRecognition();

        recognition.lang = RECOGNITION_LANG;
        recognition.continuous = true;
        recognition.interimResults = false;

        recognition.onstart = function()
        {
            browserRecognitionActive = true;
            setMicState(true);
            statusview("Böngészős beszédfelismerés...");
        };

        recognition.onresult = function(event)
        {
            // Continuous módban nem mindig a 0. eredmény az új!
            for (let i = event.resultIndex; i < event.results.length; i++)
            {
                if (event.results[i].isFinal)
                {
                    const text =
                        event.results[i][0].transcript.trim();

                    if (text !== "")
                    {
                        const input =
                            document.getElementById(INPUT_ELEMENT_ID);

                        if (input)
                        {
                            input.value +=
                                (input.value.trim() !== "" ? " " : "") +
                                text;

                            input.focus();
                        }
                    }
                }
            }
        };

        recognition.onerror = function(event)
        {
            // A csend nem valódi hiba
            if (event.error === "no-speech")
            {
                console.log(
                    "SpeechRecognition: no-speech, várunk tovább..."
                );
                return;
            }

            statusview(
                "Böngészős beszédfelismerési hiba: " +
                event.error,
                1
            );
        };

        recognition.onend = function()
        {
            // Ha még aktívnak tekintjük, akkor nem mi
            // állítottuk le: Chrome szakította meg.
            if (browserRecognitionActive)
            {
                setTimeout(function()
                {
                    try
                    {
                        recognition.start();
                    }
                    catch (e)
                    {
                        console.log(e);
                    }
                }, 200);

                return;
            }

            setMicState(false);
            statusview("Készen áll.");
        };
    }

    // Első indítás
    try
    {
        recognition.start();
    }
    catch (e)
    {
        console.log(e);
        browserRecognitionActive = false;
        setMicState(false);
    }
}
function setMicState(active)
{
    const micBtn = document.getElementById(MIC_BUTTON_ID);

    if (!micBtn) return;

    if (active)
    {
        micBtn.classList.add("toggle-active");
        micBtn.innerHTML =
            '<img src="' +
            DIR_HOST +
            '/assets/images/mic_hover.png">';
    }
    else
    {
        micBtn.classList.remove("toggle-active");
        micBtn.innerHTML =
            '<img src="' +
            DIR_HOST +
            '/assets/images/mic.png">';
    }
}
// --- 🎙️ AZ INDÍTÁSI FOLYAMAT MECHANIZMUSA ---
async function startRecordingSequence() {
  try {
    statusview("Mikrofon indítása...");
    audioStream = await navigator.mediaDevices.getUserMedia({ audio: true });
    
    // Vizuális visszajelzés: a mikrofon gömböcöt átszínezzük aktív pirosra/feketére
    const micBtn = document.getElementById(MIC_BUTTON_ID);
    if (micBtn) { 
      micBtn.classList.add("toggle-active");
      document.getElementById(MIC_BUTTON_ID).innerHTML = '<img src="'+DIR_HOST+'/assets/images/mic_hover.png">';
    }

    chunks = []; 
    recorder = new MediaRecorder(audioStream);
    
    recorder.ondataavailable = event => {
      if (event.data.size > 0) chunks.push(event.data); 
    };

    recorder.onstop = async () => { 
      statusview("MediaRecorder onstop esemény lefutott.");
      
      if (audioStream) {
        audioStream.getTracks().forEach(track => track.stop());
        statusview("Mikrofon sávok lezárva.");
      }

      const mimeType = recorder.mimeType || 'audio/webm';
      const blob = new Blob(chunks, { type: mimeType }); 
      chunks = []; 

      const formData = new FormData();
      // 🎯 A hangfájlt küldjük a te saját PHP backend szerverednek!
      formData.append("audio_blob", blob);

      statusview("Hangfájl feldolgozása a helyi PHP backenddel...");
      
      try {
        // 🎯 AZ ABSZOLÚT GYŐZTES URL: A globális DIR_HOST változóval kényszerítjük ki a pontos útvonalat!
        // Így a kérés garantáltan a http://localhost/mara/index.php-ra fog menni, megkerülve a 404-et!
        const response = await fetch(DIR_HOST + "/proc/transcribeAudio", {     
          method: "POST",         
          body: formData 
        });


        if (response.ok) {
          const result = await response.json();
          
          if (result.text && result.text.trim() !== "") {
            statusview("Sikeres offline szövegfelismerés!");
            
            const activeTextArea = document.getElementById(INPUT_ELEMENT_ID);
            if (activeTextArea) {
              activeTextArea.value = result.text.trim();
              
              // 🚀 TRIGGER: Azonnal beküldjük a szöveget mara-nek!
              //ollama_send_message();
            }
          } else {
            statusview("Nem észlelt szót a rendszer.", 1);
          }
        } else {
          statusview("PHP átírási hiba (Kód: " + response.status + ")", 1);
        }
      } catch (err) {
        statusview("Nem érhető el a PHP backend!", err);
      }

      setTimeout(() => { statusview("Készen áll."); }, 3000);
    };



    recorder.start();
    if (recognition) recognition.start();
    statusview(`Felvétel... (Mondd: '${AUTOMATIC_STOP_WORD}' vagy nyomj Alt+${humanFriendlyKey}-et a stophoz)`);

  } catch (err) {
    statusview("Mikrofon hiba: " + err.message, err);
    setMicState(true);
  }
}

// --- ⌨️ BILLENTYŰZET FIGYELÉSE ---
document.addEventListener('keydown', function(e) { 
  const isTargetKey = e.code === SHORTCUT_KEY_CODE || e.key === SHORTCUT_KEY_LETTER || e.key === SHORTCUT_KEY_LETTER.toUpperCase();
  
  if (isTargetKey && e.altKey) {
    e.preventDefault();
    msg = "Alt + " + humanFriendlyKey + " megnyomva. Aktuális állapot: " + (recorder ? recorder.state : "nincs"); 
    statusview(msg);
    
    // Meghívjuk az univerzális ravaszt
    triggerVoiceToggle();
  }
});

// --- 🎙️ MIKROFON GÖMBÖC INTEGRÁCIÓ (Ezt hívja a HTML onclick eseménye) ---
function toggleVoiceInput() {
  console.log("🎙️ Mikrofon gömböcre kattintottak! Hangfelvétel indítása/leállítása.");
  
  // Pontosan ugyanazt az univerzális ravaszt hívja meg, mint a billentyűzet!
  triggerVoiceToggle();
}


// --- 📺 DINAMIKUS STÁTUSZ KIJELZŐ MOTOR ---
function statusview(text, error){
  const currentStatusElement = document.getElementById(STATUS_ELEMENT_ID);
  if (currentStatusElement){
      currentStatusElement.textContent = text;
  }
  if (error){
      if (error == 1) { console.log(text); } else { console.log(error); }
  }
}