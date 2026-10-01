function w3CodeColor(elmnt, mode) {
  var lang = (mode || "html");
  var elmntObj = (document.getElementById(elmnt) || elmnt);
  var elmntTxt = elmntObj.innerHTML;
  var tagcolor = "#ddd";
  var tagnamecolor = "#f43a7b";
  var attributecolor = "#7aba32";
  var attributevaluecolor = "#e8d16d";
  var commentcolor = "grey";
  var cssselectorcolor = "brown";
  var csspropertycolor = "red";
  var csspropertyvaluecolor = "#1ab1db";
  var cssdelimitercolor = "white";
  var cssimportantcolor = "red";  
  var jscolor = "#ddd";
  var jskeywordcolor = "#1ab1db";
  var jsstringcolor = "#f7dd5b";
  var jsnumbercolor = "#9060ea";
  var jspropertycolor = "#1ab1db";
  var jsoperatorcolor = "red";
  var phpcolor = "white";
  //elmntObj.style.fontFamily = "Courier New";
  if (!lang) {lang = "html"; }
  if (lang == "html") {elmntTxt = htmlMode(elmntTxt);}
  if (lang == "css") {elmntTxt = cssMode(elmntTxt);}
  if (lang == "js") {elmntTxt = jsMode(elmntTxt);}
  if (lang == "php")  {elmntTxt = phpMode(elmntTxt);}
  elmntObj.innerHTML = elmntTxt;

  function extract(str, start, end, func, repl) {
    var s, e, d = "", a = [];
    while (str.search(start) > -1) {
      s = str.search(start);
      e = str.indexOf(end, s);
      if (e == -1) {e = str.length;}
      if (repl) {
        a.push(func(str.substring(s, e + (end.length))));      
        str = str.substring(0, s) + repl + str.substr(e + (end.length));
      } else {
        d += str.substring(0, s);
        d += func(str.substring(s, e + (end.length)));
        str = str.substr(e + (end.length));
      }
    }
    this.rest = d + str;
    this.arr = a;
  }
  function htmlMode(txt) {
    var rest = txt, done = "", php, comment, angular, startpos, endpos, note, i;
    comment = new extract(rest, "&lt;!--", "--&gt;", commentMode, "W3HTMLCOMMENTPOS");
    rest = comment.rest;
    while (rest.indexOf("&lt;") > -1) {
      note = "";
      startpos = rest.indexOf("&lt;");
      if (rest.substr(startpos, 9).toUpperCase() == "&LT;STYLE") {note = "css";}
      if (rest.substr(startpos, 10).toUpperCase() == "&LT;SCRIPT") {note = "javascript";}
      if (rest.substr(startpos, 8).toUpperCase() == "&LT;?PHP") {note = "php";}
      endpos = rest.indexOf("&gt;", startpos); if (note == 'php') {console.log('REST: ', rest.substring(startpos, endpos));}
      if (endpos == -1) {endpos = rest.length;}
        done += rest.substring(0, startpos);
        if (note == "php") {done += phpMode(rest.substring(startpos, endpos + 4));} else {done += tagMode(rest.substring(startpos, endpos + 4));}
        rest = rest.substr(endpos + 4); 
      if (note == "css") {
        endpos = rest.indexOf("&lt;/style&gt;");
        if (endpos > -1) {
          done += cssMode(rest.substring(0, endpos));
          rest = rest.substr(endpos);
        }
      }
      if (note == "javascript") {
        endpos = rest.indexOf("&lt;/script&gt;");
        if (endpos > -1) {
          done += jsMode(rest.substring(0, endpos));
          rest = rest.substr(endpos);
        }
      }      
    }
    rest = done + rest;
    for (i = 0; i < comment.arr.length; i++) {
        rest = rest.replace("W3HTMLCOMMENTPOS", comment.arr[i]);
    }
    return rest;
  }
  function tagMode(txt) {
    var rest = txt, done = "", startpos, endpos, result;
    while (rest.search(/(\s|<br>)/) > -1) {    
      startpos = rest.search(/(\s|<br>)/);
      endpos = rest.indexOf("&gt;");
      if (endpos == -1) {endpos = rest.length;}
      done += rest.substring(0, startpos);
      done += attributeMode(rest.substring(startpos, endpos));
      rest = rest.substr(endpos);
    }
    result = done + rest;
    result = "<span style=color:" + tagcolor + ">&lt;</span>" + result.substring(4);
    if (result.substr(result.length - 4, 4) == "&gt;") {
      result = result.substring(0, result.length - 4) + "<span style=color:" + tagcolor + ">&gt;</span>";
    }
    return "<span style=color:" + tagnamecolor + ">" + result + "</span>";
  }
  function attributeMode(txt) {
    var rest = txt, done = "", startpos, endpos, singlefnuttpos, doublefnuttpos, spacepos;
    while (rest.indexOf("=") > -1) {
      endpos = -1;
      startpos = rest.indexOf("=");
      singlefnuttpos = rest.indexOf("'", startpos);
      doublefnuttpos = rest.indexOf('"', startpos);
      spacepos = rest.indexOf(" ", startpos + 2);
      if (spacepos > -1 && (spacepos < singlefnuttpos || singlefnuttpos == -1) && (spacepos < doublefnuttpos || doublefnuttpos == -1)) {
        endpos = rest.indexOf(" ", startpos);      
      } else if (doublefnuttpos > -1 && (doublefnuttpos < singlefnuttpos || singlefnuttpos == -1) && (doublefnuttpos < spacepos || spacepos == -1)) {
        endpos = rest.indexOf('"', rest.indexOf('"', startpos) + 1);
      } else if (singlefnuttpos > -1 && (singlefnuttpos < doublefnuttpos || doublefnuttpos == -1) && (singlefnuttpos < spacepos || spacepos == -1)) {
        endpos = rest.indexOf("'", rest.indexOf("'", startpos) + 1);
      }
      if (!endpos || endpos == -1 || endpos < startpos) {endpos = rest.length;}
      done += rest.substring(0, startpos);
      done += attributeValueMode(rest.substring(startpos, endpos + 1));
      rest = rest.substr(endpos + 1);
    }
    return "<span style=color:" + attributecolor + ">" + done + rest + "</span>";
  }
  function attributeValueMode(txt) {
    return "<span style=color:" + attributevaluecolor + ">" + txt + "</span>";
  }
  function commentMode(txt) {
    return "<span style=color:" + commentcolor + ">" + txt + "</span>";
  }
  function cssMode(txt) {
    var rest = txt, done = "", s, e, comment, i, midz, c, cc;
    comment = new extract(rest, /\/\*/, "*/", commentMode, "W3CSSCOMMENTPOS");
    rest = comment.rest;
    while (rest.search("{") > -1) {
      s = rest.search("{");
      midz = rest.substr(s + 1);
      cc = 1;
      c = 0;
      for (i = 0; i < midz.length; i++) {
        if (midz.substr(i, 1) == "{") {cc++; c++}
        if (midz.substr(i, 1) == "}") {cc--;}
        if (cc == 0) {break;}
      }
      if (cc != 0) {c = 0;}
      e = s;
      for (i = 0; i <= c; i++) {
        e = rest.indexOf("}", e + 1);
      }
      if (e == -1) {e = rest.length;}
      done += rest.substring(0, s + 1);
      done += cssPropertyMode(rest.substring(s + 1, e));
      rest = rest.substr(e);
    }
    rest = done + rest;
    rest = rest.replace(/{/g, "<span style=color:" + cssdelimitercolor + ">{</span>");
    rest = rest.replace(/}/g, "<span style=color:" + cssdelimitercolor + ">}</span>");
    for (i = 0; i < comment.arr.length; i++) {
        rest = rest.replace("W3CSSCOMMENTPOS", comment.arr[i]);
    }
    return "<span style=color:" + cssselectorcolor + ">" + rest + "</span>";
  }
  function cssPropertyMode(txt) {
    var rest = txt, done = "", s, e, n, loop;
    if (rest.indexOf("{") > -1 ) { return cssMode(rest); }
    while (rest.search(":") > -1) {
      s = rest.search(":");
      loop = true;
      n = s;
      while (loop == true) {
        loop = false;
        e = rest.indexOf(";", n);
        if (rest.substring(e - 5, e + 1) == "&nbsp;") {
          loop = true;
          n = e + 1;
        }
      }
      if (e == -1) {e = rest.length;}
      done += rest.substring(0, s);
      done += cssPropertyValueMode(rest.substring(s, e + 1));
      rest = rest.substr(e + 1);
    }
    return "<span style=color:" + csspropertycolor + ">" + done + rest + "</span>";
  }
  function cssPropertyValueMode(txt) {
    var rest = txt, done = "", s;
    rest = "<span style=color:" + cssdelimitercolor + ">:</span>" + rest.substring(1);
    while (rest.search(/!important/i) > -1) {
      s = rest.search(/!important/i);
      done += rest.substring(0, s);
      done += cssImportantMode(rest.substring(s, s + 10));
      rest = rest.substr(s + 10);
    }
    result = done + rest;    
    if (result.substr(result.length - 1, 1) == ";" && result.substr(result.length - 6, 6) != "&nbsp;" && result.substr(result.length - 4, 4) != "&lt;" && result.substr(result.length - 4, 4) != "&gt;" && result.substr(result.length - 5, 5) != "&amp;") {
      result = result.substring(0, result.length - 1) + "<span style=color:" + cssdelimitercolor + ">;</span>";
    }
    return "<span style=color:" + csspropertyvaluecolor + ">" + result + "</span>";
  }
  function cssImportantMode(txt) {
    return "<span style=color:" + cssimportantcolor + ";font-weight:bold;>" + txt + "</span>";
  }
  function jsMode(txt) {
    var rest = txt, done = "", esc = [], i, cc, tt = "", sfnuttpos, dfnuttpos, compos, comlinepos, keywordpos, numpos, mypos, dotpos, operatorpos, y;
    for (i = 0; i < rest.length; i++)  {
      cc = rest.substr(i, 1);
      if (cc == "\\") {
        esc.push(rest.substr(i, 2));
        cc = "W3JSESCAPE";
        i++;
      }
      tt += cc;
    }
    rest = tt;
    y = 1;
    while (y == 1) {
      sfnuttpos = getPos(rest, "'", "'", jsStringMode);
      dfnuttpos = getPos(rest, '"', '"', jsStringMode);
      compos = getPos(rest, /\/\*/, "*/", commentMode);
      comlinepos = getPos(rest, /\/\//, "\n", commentMode);      
      numpos = getNumPos(rest, jsNumberMode);
      keywordpos = getKeywordPos("js", rest, jsKeywordMode);
      dotpos = getDotPos(rest, jsPropertyMode);
      operatorpos = getOperatorPos("js", rest, jsOperatorMode);
      if (Math.max(numpos[0], sfnuttpos[0], dfnuttpos[0], compos[0], comlinepos[0], keywordpos[0], dotpos[0], operatorpos[0]) == -1) {break;}
      mypos = getMinPos(numpos, sfnuttpos, dfnuttpos, compos, comlinepos, keywordpos, dotpos, operatorpos);
      if (mypos[0] == -1) {break;}
      if (mypos[0] > -1) {
        done += rest.substring(0, mypos[0]);
        done += mypos[2](rest.substring(mypos[0], mypos[1]));
        rest = rest.substr(mypos[1]);
      }
    }
    rest = done + rest;
    for (i = 0; i < esc.length; i++) {
      rest = rest.replace("W3JSESCAPE", esc[i]);
    }
    return "<span style=color:" + jscolor + ">" + rest + "</span>";
  }
  function jsStringMode(txt) {
    return "<span style=color:" + jsstringcolor + ">" + txt + "</span>";
  }
  function jsKeywordMode(txt) {
    return "<span style=color:" + jskeywordcolor + ">" + txt + "</span>";
  }
  function jsNumberMode(txt) {
    return "<span style=color:" + jsnumbercolor + ">" + txt + "</span>";
  }
  function jsPropertyMode(txt) {
    return "<span style=color:" + jspropertycolor + ">" + txt + "</span>";
  }
  function jsOperatorMode(txt) {
    return "<span style=color:" + jsoperatorcolor + ">" + txt + "</span>";
  }  
  function getDotPos(txt, func) {
    var x, i, j, s, e, arr = [".","<", " ", ";", "(", "+", ")", "[", "]", ",", "&", ":", "{", "}", "/" ,"-", "*", "|", "%"];
    s = txt.indexOf(".");
    if (s > -1) {
      x = txt.substr(s + 1);
      for (j = 0; j < x.length; j++) {
        cc = x[j];
        for (i = 0; i < arr.length; i++) {
          if (cc.indexOf(arr[i]) > -1) {
            e = j;
            return [s + 1, e + s + 1, func];
          }
        }
      }
    }
    return [-1, -1, func];
  }
  function getMinPos() {
    var i, arr = [];
    for (i = 0; i < arguments.length; i++) {
      if (arguments[i][0] > -1) {
        if (arr.length == 0 || arguments[i][0] < arr[0]) {arr = arguments[i];}
      }
    }
    if (arr.length == 0) {arr = arguments[i];}
    return arr;
  }
  function getKeywordPos(typ, txt, func) {
    var words, i, pos, rpos = -1, rpos2 = -1, patt;
    if (typ == "js") {
      words = ["abstract","arguments","boolean","break","byte","case","catch","char","class","const","continue","debugger","default","delete",
      "do","double","document","else","enum","eval","export","extends","false","final","finally","float","for","function","goto","if","implements","import",
      "in","instanceof","int","interface","let","long","location","NaN","native","new","null","package","private","protected","public","return","short","static",
      "super","switch","synchronized","this","throw","throws","transient","true","try","typeof","var","void","volatile","while","with","window","yield"];
    } else
    if (typ == "php") {
      words =  ['and','or','xor','array','as','break','case','cfunction','class','const','continue','d','eclare','default','die','do','else',
                'elseif','enddeclare','endfor','endforeach','endif','endswitch','endwhile','extends','for','foreach','function','include',
                'include_once','global','if','new','old_function','return','static','switch','use','require','require_once',
                'var','while','abstract','interface','public','implements','extends','private','protected','throw'];
    }
    for (i = 0; i < words.length; i++) {
      pos = txt.indexOf(words[i]);
      if (pos > -1) {
        patt = /\W/g;
        if (txt.substr(pos + words[i].length,1).match(patt) && txt.substr(pos - 1,1).match(patt)) {
          if (pos > -1 && (rpos == -1 || pos < rpos)) {
            rpos = pos;
            rpos2 = rpos + words[i].length;
          }
        }
      } 
    }
    return [rpos, rpos2, func];
  }
  function getFuncPos(typ, txt, func) {
    var words, i, pos, rpos = -1, rpos2 = -1, patt;
    if (typ == "js") {
      words = [];
    } else
    if (typ == "php") {
      words =  ['abs','acos','acosh','addcslashes','addslashes',
                'array_change_key_case','array_chunk','array_combine','array_count_values','array_diff',
                'array_diff_assoc','array_diff_key','array_diff_uassoc','array_diff_ukey','array_fill',
                'array_filter','array_flip','array_intersect','array_intersect_assoc','array_intersect_key',
                'array_intersect_uassoc','array_intersect_ukey','array_key_exists','array_keys','array_map',
                'array_merge','array_merge_recursive','array_multisort','array_pad','array_pop','array_product',
                'array_push','array_rand','array_reduce','array_reverse','array_search','array_shift',
                'array_slice','array_splice','array_sum','array_udiff','array_udiff_assoc',
                'array_udiff_uassoc','array_uintersect','array_uintersect_assoc',
                'array_uintersect_uassoc','array_unique','array_unshift','array_values','array_walk',
                'array_walk_recursive','atan','atan2','atanh','base64_decode','base64_encode','base_convert',
                'basename','bcadd','bccomp','bcdiv','bcmod','bcmul','bindec','bindtextdomain','bzclose','bzcompress',
                'bzdecompress','bzerrno','bzerror','bzerrstr','bzflush','bzopen','bzread','bzwrite','ceil','chdir',
                'checkdate','checkdnsrr','chgrp','chmod','chop','chown','chr','chroot','chunk_split','class_exists',
                'closedir','closelog','copy','cos','cosh','count','count_chars','date','decbin','dechex','decoct',
                'deg2rad','delete','ebcdic2ascii','echo','empty','end','ereg','ereg_replace','eregi','eregi_replace','error_log',
                'error_reporting','escapeshellarg','escapeshellcmd','eval','exec','exit','exp','explode','extension_loaded',
                'feof','fflush','fgetc','fgetcsv','fgets','fgetss','file_exists','file_get_contents','file_put_contents',
                'fileatime','filectime','filegroup','fileinode','filemtime','fileowner','fileperms','filesize','filetype',
                'floatval','flock','floor','flush','fmod','fnmatch','fopen','fpassthru','fprintf','fputcsv','fputs','fread','fscanf',
                'fseek','fsockopen','fstat','ftell','ftok','getallheaders','getcwd','getdate','getenv','gethostbyaddr','gethostbyname',
                'gethostbynamel','getimagesize','getlastmod','getmxrr','getmygid','getmyinode','getmypid','getmyuid','getopt',
                'getprotobyname','getprotobynumber','getrandmax','getrusage','getservbyname','getservbyport','gettext',
                'gettimeofday','gettype','glob','gmdate','gmmktime','ini_alter','ini_get','ini_get_all','ini_restore','ini_set',
                'interface_exists','intval','ip2long','is_a','is_array','is_bool','is_callable','is_dir','is_double',
                'is_executable','is_file','is_finite','is_float','is_infinite','is_int','is_integer','is_link','is_long',
                'is_nan','is_null','is_numeric','is_object','is_readable','is_real','is_resource','is_scalar','is_soap_fault',
                'is_string','is_subclass_of','is_uploaded_file','is_writable','is_writeable','mkdir','mktime','nl2br',
                'parse_ini_file','parse_str','parse_url','passthru','pathinfo','print','readlink','realpath','rewind','rewinddir','rmdir',
                'round','str_ireplace','str_pad','str_repeat','str_replace','str_rot13','str_shuffle','str_split',
                'str_word_count','strcasecmp','strchr','strcmp','strcoll','strcspn','strftime','strip_tags','stripcslashes',
                'stripos','stripslashes','stristr','strlen','strnatcasecmp','strnatcmp','strncasecmp','strncmp','strpbrk',
                'strpos','strptime','strrchr','strrev','strripos','strrpos','strspn','strstr','strtok','strtolower','strtotime',
                'strtoupper','strtr','strval','substr','substr_compare'];
    }
    for (i = 0; i < words.length; i++) {
      pos = txt.indexOf(words[i]);
      if (pos > -1) {
        patt = /\W/g;
        if (txt.substr(pos + words[i].length,1).match(patt) && txt.substr(pos - 1,1).match(patt)) {
          if (pos > -1 && (rpos == -1 || pos < rpos)) {
            rpos = pos;
            rpos2 = rpos + words[i].length;
          }
        }
      } 
    }
    return [rpos, rpos2, func];
  }  
  function getPos(txt, start, end, func) {
    var s, e;
    s = txt.search(start);
    e = txt.indexOf(end, s + (end.length));
    if (e == -1) {e = txt.length;}
    return [s, e + (end.length), func];
  }
  function getNumPos(txt, func) {
    var arr = ["<br>", " ", ";", "(", "+", ")", "[", "]", ",", "&", ":", "{", "}", "/" ,"-", "*", "|", "%", "="], i, j, c, startpos = 0, endpos, word;
    for (i = 0; i < txt.length; i++) {
      for (j = 0; j < arr.length; j++) {
        c = txt.substr(i, arr[j].length);
        if (c == arr[j]) {
          if (c == "-" && (txt.substr(i - 1, 1) == "e" || txt.substr(i - 1, 1) == "E")) {
            continue;
          }
          endpos = i;
          if (startpos < endpos) {
            word = txt.substring(startpos, endpos);
            if (!isNaN(word)) {return [startpos, endpos, func];}
          }
          i += arr[j].length;
          startpos = i;
          i -= 1;
          break;
        }
      }
    }  
    return [-1, -1, func];
  }
  function getOperatorPos(typ, txt, func) {
    var words, i, pos, rpos = -1, rpos2 = -1, patt;
    if (typ == "js") {
      words = ["+","-","*","|","^","%","$","=","?"];
    } else
    if (typ == "php") {
      words = ["+","-","*","|","^","%","="];
    }    
    for (i = 0; i < words.length; i++) {
      pos = txt.indexOf(words[i]);
      if (pos > -1) {
        patt = /\W/g;
          if (pos > -1 && (rpos == -1 || pos < rpos)) {
            rpos = pos;
            rpos2 = rpos + words[i].length;
          }        
      } 
    }
    return [rpos, rpos2, func];
  }
  function phpMode(txt) {
    var rest = txt, done = "", esc = [], i, cc, tt = "", sfnuttpos, dfnuttpos, compos, comlinepos, keywordpos, numpos, mypos, dotpos, operatorpos, y;
    for (i = 0; i < rest.length; i++)  {
      cc = rest.substr(i, 1);
      if (cc == "\\") {
        esc.push(rest.substr(i, 2));
        cc = "W3JSESCAPE";
        i++;
      }
      tt += cc;
    }
    rest = tt;
    y = 1;
    while (y == 1) {
      sfnuttpos = getPos(rest, "'", "'", jsStringMode);
      dfnuttpos = getPos(rest, '"', '"', jsStringMode);
      compos = getPos(rest, /\/\*/, "*/", commentMode);
      comlinepos = getPos(rest, /\/\//, "\n", commentMode);      
      numpos = getNumPos(rest, jsNumberMode);
      keywordpos = getKeywordPos("php", rest, jsKeywordMode);
      dotpos = getDotPos(rest, jsPropertyMode);
      operatorpos = getOperatorPos("php", rest, jsOperatorMode);
      if (Math.max(numpos[0], sfnuttpos[0], dfnuttpos[0], compos[0], comlinepos[0], keywordpos[0], dotpos[0], operatorpos[0]) == -1) {break;}
      mypos = getMinPos(numpos, sfnuttpos, dfnuttpos, compos, comlinepos, keywordpos, dotpos, operatorpos);
      if (mypos[0] == -1) {break;}
      if (mypos[0] > -1) {
        done += rest.substring(0, mypos[0]);
        done += mypos[2](rest.substring(mypos[0], mypos[1]));
        rest = rest.substr(mypos[1]);
      }
    }
    rest = done + rest;
    for (i = 0; i < esc.length; i++) {
      rest = rest.replace("W3JSESCAPE", esc[i]);
    }
    return "<span style=color:" + phpcolor + ">" + rest + "</span>";
  }    
}