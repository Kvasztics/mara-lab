var DIR_ROOT       = ''; 
var DIR_HOST       = '';
var VOICE_ENABLED  = 0;
var VOICE          = '';
var SPEECH_ENABLED = 0;
var SPEECH         = '';
var LANG           = [];

function getSettings(url)
{
  var xhr = new XMLHttpRequest();
  if (xhr)
    { 
      xhr.onreadystatechange = function()
        {                 
          if (xhr.readyState == 4)
            {
              var jsonData   = JSON.parse(xhr.responseText);
              DIR_ROOT       = jsonData.root;  
              DIR_HOST       = jsonData.host;
              VOICE_ENABLED  = jsonData.voice_start;
              VOICE          = jsonData.voice;
              SPEECH_ENABLED = jsonData.recognition_enabled;
              SPEECH         = jsonData.recognition;              
              LANG           = jsonData.lang; console.log(DIR_HOST);
            }          
        }
      xhr.open("POST", url, true);
      xhr.send();          
    } 
}