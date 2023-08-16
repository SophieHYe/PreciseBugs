/**
 * Copyright 2009 Daniel Pupius (http://code.google.com/p/fittr/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 *
 * Easy Exif
 * Adds a link that allows you to view the EXIF information for a phone inline
 * without navigating to a separate page.  Shows a limited subset for brevity.
 */
 
var exif = (function() {
  
  var EXIF_TAG_WHITELIST = {
    FileSize: 1,
    MIMEType: 1,
    Model: 1,
    ExposureTime: 1,
    FNumber: 1,
    ISO: 1,
    ExposureCompensation: 1,
    FocalLength: 1,
    Lens: 1
  };
  
  var link;
  if (page.getPhotoId()) {
    link = addPhotoLink('+ EXIF', 'Show selected EXIF information inline.');
    if (!link) return;
    link.id = 'xxxExifLinkEl';
    link.style.cssFloat = 'left';
    link.addEventListener('click', toggleExifDisplay);
  }
  
  var showing = false;
  function toggleExifDisplay(e) {
    var el = getExifEl();
    var linkEl = getEl('xxxExifLinkEl');
    if (showing) {
      linkEl.innerHTML = '+ EXIF';
      el.style.height = '0';
    } else {
      linkEl.innerHTML = '&ndash; EXIF';
      el.style.height = el.scrollHeight + 'px';
    }
    showing = !showing;
  }
  
  function getExifEl() {
    var el = getEl('fittr-exif-dislay');
    if (!el) {
      el = createEl('div');
      el.id = 'fittr-exif-dislay';
      el.innerHTML = '<i>Loading</i>';
      link.parentNode.parentNode.insertBefore(el, link.parentNode.nextSibling);

      api.makeApiRequest(api.RequestType.GET_EXIF, {photo_id: page.getPhotoId()}, function(data) {
        el.innerHTML = '';
        if (data.status == 200 && data.content.stat == 'ok') {
          var table = createEl('table', 'fittr-exif-table');
          var seen = {};
          for (var i = 0; i < data.content.photo.exif.length; i++) {
            var item = data.content.photo.exif[i];
            if (item.tag in EXIF_TAG_WHITELIST && !(item.tag in seen)) {
              seen[item.tag] = 1;
              var value = item.clean ? item.clean._content : item.raw._content;
              var tr = createEl('tr');
              var td1 = createEl('td');
              td1.appendChild(createText(item.label));
              tr.appendChild(td1);
              var td2 = createEl('td');
              td2.appendChild(createText(value));
              tr.appendChild(td2);
              table.appendChild(tr);
            }
          }
          el.appendChild(table);
        } else {
          var error = createEl('i');
          if (data.status == 200 && data.content.stat == 'fail') {
            error.appendChild(createText('Error loading EXIF : ' + data.content.message));
          } else {
            error.appendChild(createText('Error loading EXIF, status: ' + data.status));
          }
          el.appendChild(error);
        }  
        el.style.height = el.scrollHeight + 'px';
      });
    }
    return el;
  }
  
  return {
    toggle: toggleExifDisplay
  };
})();
