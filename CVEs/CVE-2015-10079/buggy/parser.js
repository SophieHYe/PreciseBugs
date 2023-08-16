/*
    OmnomIRC COPYRIGHT 2010,2011 Netham45
                       2012-2014 Sorunome
                       2015      juju2143

    This file contains snippets of code taken from OmnomIRC.
*/

function parseLinks(text,nav)
{
  text = text.replace(/(\x01)/g,"");
  if (!text || text === null || text === undefined){
    return;
  }
  text = text.replace(/http:\/\/img.codewalr\.us\//g,"\x01img.codewalr.us/");
  text = text.replace(/http:\/\/codewalr\.us\//g,"\x01codewalr.us/");
  text = text.replace(RegExp("(^|.)(((f|ht)(tp|tps):\/\/)[^\\s\x02\x03\x0f\x16\x1d\x1f]*)","g"),'$1<a target="_blank" href="$2"'+(nav?' class="navbar-link"':'')+'>$2</a>');
  text = text.replace(RegExp("(^|\\s)(www\\.[^\\s\x02\x03\x0f\x16\x1d\x1f]*)","g"),'$1<a target="_blank" href="http://$2"'+(nav?' class="navbar-link"':'')+'>$2</a>');
  text = text.replace(RegExp("(^|.)\x01(img.codewalr.us\/[^\\s\x02\x03\x0f\x16\x1d\x1f]*)","g"),'$1<a target="_top" href="http://$2"'+(nav?' class="navbar-link"':'')+'><img src="http://$2" class="picture" /></a>');
  text = text.replace(RegExp("(^|.)\x01([^\\s\x02\x03\x0f\x16\x1d\x1f]*)","g"),'$1<a target="_top" href="http://$2"'+(nav?' class="navbar-link"':'')+'>http://$2</a>');
  return text;
}

function parseColors(colorStr)
{
  var arrayResults = [],
  s,
  textDecoration = {
    fg:'-1',
    bg:'-1',
    underline:false,
    bold:false,
    italic:false
  },
  i,didChange;
  if(!colorStr){
    return '';
  }
  arrayResults = colorStr.split(RegExp('([\x02\x03\x0f\x16\x1d\x1f])'));
  colorStr='<span>';
  for(i=0;i<arrayResults.length;i++){
    didChange = true;
    switch(arrayResults[i])
    {
      case '\x03': // color
        s = arrayResults[i+1].replace(/^([0-9]{1,2}),([0-9]{1,2})(.*)/,'$1:$2');
        if(s == arrayResults[i+1]){ // we didn't change background
          s = arrayResults[i+1].replace(/^([0-9]{1,2}).*/,'$1');
          textDecoration.fg = s;
          if(s == arrayResults[i+1]){
            arrayResults[i+1] = '';
          }else{
            arrayResults[i+1] = arrayResults[i+1].substr(s.length);
          }
        }else{ // we also changed background
          textDecoration.fg = s.split(':')[0];
          textDecoration.bg = s.split(':')[1];
          if(s == arrayResults[i+1]){
            arrayResults[i+1] = '';
          }else{
            arrayResults[i+1] = arrayResults[i+1].substr(s.length);
          }
        }
        break;
      case '\x02': // bold
        textDecoration.bold = !textDecoration.bold;
        break;
      case '\x1d': // italic
        textDecoration.italic = !textDecoration.italic;
        break;
      case '\x16': // swap fg and bg
        s = textDecoration.fg;
        textDecoration.fg = textDecoration.bg;
        textDecoration.bg = s;
        if(textDecoration.fg=='-1'){
          textDecoration.fg = '0';
        }
        if(textDecoration.bg=='-1'){
          textDecoration.bg = '1';
        }
        break;
      case '\x1f': // underline
        textDecoration.underline = !textDecoration.underline;
        break;
      case '\x0f': // reset
        textDecoration = {
          fg:'-1',
          bg:'-1',
          underline:false,
          bold:false,
          italic:false
        }
        break;
      default:
        didChange = false;
    }
    if(didChange){
      colorStr += '</span>';
      colorStr += '<span class="'+(textDecoration.fg!=-1?'c'+parseInt(textDecoration.fg)+' ':'')+(textDecoration.bg!=-1?'b'+parseInt(textDecoration.bg)+' ':'')+(textDecoration.bold?'bold ':'')+(textDecoration.underline?'underline ':'')+(textDecoration.italic?'italic':'')+'">';
    }else{
      colorStr+=arrayResults[i];
    }
  }
  colorStr += '</span>';
  /*Strip codes*/
  colorStr = colorStr.replace(/(\x03|\x02|\x1F|\x09|\x0F)/g,'');
  return colorStr;
}

function parseSmileys(s){
  if(settings.smileys)
    var smileys = settings.smileys;
  else
    var smileys = [];
  var addStuff = '';
  if(!s){
    return '';
  }
  $.each(smileys,function(i,smiley){
    s = s.replace(RegExp(smiley.regex,'g'),smiley.replace.split('ADDSTUFF').join(addStuff).split('PIC').join(smiley.pic).split('ALT').join(smiley.alt));
  });
  return s;
}

function parseMessage(s,nav,noSmileys)
{
  if(nav==undefined || !nav){
    nav = false;
  }
  if(noSmileys==undefined || !noSmileys){
    noSmileys = false;
  }
  s = (s=="\x00"?'':s); //fix 0-string bug
  s = $('<span/>').text(s).html();
  s = parseLinks(s, nav);
  if(noSmileys===false){
    s = parseSmileys(s);
  }
  s = parseColors(s);
  return s;
}
