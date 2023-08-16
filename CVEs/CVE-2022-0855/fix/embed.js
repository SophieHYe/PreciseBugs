document.addEventListener("DOMContentLoaded", function(event) {

    var tool = {
        offset: function (node) {
            var rect = node.getBoundingClientRect()
            return {
                top: rect.top,
                left: rect.left
            }
        }
    }
    var mlt = 'Multiple embed instances not allowed';
    if (window['mw-domain-search-iframe-embed-script']) {
        console.warn(mlt);
        return;
    }
    window['mw-domain-search-iframe-embed'] = true;

    if (window.top.document.querySelectorAll('#domain-search-iframe-js').length > 1) {
        // if (document.querySelectorAll('script[src*="/microweber_addon/"]').length > 1) {
        console.warn(mlt);
        return;
    }

    var $ = function (a) {
        return document.querySelector(a)
    };

    var script = $('#domain-search-iframe-js');
    // var script = $('#domain-search-iframe-js');

    if (!script) {
        return;
    }

    var path = script.src.split('/');
    path.pop()
    path.pop()
    path.pop()
    path.pop()
    path.pop()
    path = path.join('/');
    var $params = script.src.split('?')[1] || '';
    _params = $params.split('&');
    var params = {};
    for (var i = 0; i < _params.length; i++) {
        var item = _params[i].split('=');
        params[decodeURIComponent(item[0]).trim()] = decodeURIComponent(item[1]).trim();
    }


    var iframe = document.createElement("iframe");

    iframe.id = 'domain-search-iframe';
    iframe.style.width = "100%";
    iframe.style.height = "1000px";
    iframe.scrolling = 'no';
    iframe.frameBorder = 0;
    iframe.allowtransparency = 1;

    var windowhash = window.location.hash;

    // iframe.onload =       window.scrollTo(0,0);;
    if (typeof windowhash === 'string' && windowhash.indexOf('frameurl=') > -1) {
        var hash = windowhash.split('frameurl=')[1];
        var hash = decodeURI(atob(hash))
        var domain_check = (new URL(hash));
        domain_check = domain_check.hostname;

        if(window.location.hostname != domain_check){
            alert('You are trying to load a domain search from a different domain. Please use the same domain.');
            return;
        }


        //     iframe.src = hash;
    } else {

        //alert(path);
        iframe.src = path + '/index.php?m=microweber_addon&function=order_iframe&' + $params;

    }
    script.parentNode.insertBefore(iframe, script.nextSibling);

    addEventListener('message', function (e) {

        if (typeof e.data === 'string' && e.data.indexOf('frameLocation:') > -1) {
            var hash = e.data.split('frameLocation:')[1];
            //  window.location.hash = 'frameurl=' + (btoa(encodeURI(hash)))
        }

        if (typeof e.data === 'string' && e.data.indexOf('documentHeight:') > -1) {
            var height = e.data.split('documentHeight:')[1];
            if (height > 0) {
                document.getElementById("domain-search-iframe").style.height = height + 'px';
            }
        }
    }, false);

});
