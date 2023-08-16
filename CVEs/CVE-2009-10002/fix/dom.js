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
 * Dom
 * Shortcuts for common DOM operations.
 */

function createText(txt) {
  return document.createTextNode(txt); 
}

function createEl(el, opt_className) {
  var el = document.createElement(el); 
  if (opt_className) el.className = opt_className;
  return el;
}

function getEl(id) {
  return document.getElementById(id);
}

function query(sel) {
  return document.querySelectorAll(sel);
}

function clickElement(el) {
  var evt = document.createEvent('MouseEvents');
  evt.initMouseEvent('click', true, true, window,
      0, 0, 0, 0, 0, false, false, false, false, 0, null);
  el.dispatchEvent(evt);
}