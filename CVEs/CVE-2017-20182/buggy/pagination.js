// Don't wait for the whole page to be loaded.
// (include script at the bottom of the page.)

var Pagination = new function() {

    this.init = function(processHash, document_containers, setHash) {
		// For each pagination node
        var containers = new Array();

        // Create new containers Array after each initialisation
        // (init can be called several times, when the pagination is
        // nested in a tabbed container.)

        for (var i in document_containers)
            $(document_containers[i]).find('.paginate-container').each(function() {
                var container = $(this);
                // Only execute once for each container.
                // (remember that init() may be called multiple times,
                // also after content was dynamically changed, because
                // paginators could be added on the fly.)
                if (! container.hasClass('paginate-container-initialized'))
                {
                    container.addClass('paginate-container-initialized');
                    containers.push(container);
                    set_up(container);
                }
            });

		// Make setHash = true the default
        if (setHash == undefined)
            setHash = true;

        function scroll(hash) {
            var target = $('[id=' + hash + ']');
            if (target.length) {
                  var offset = target.offset().top;
                  $('html,body').animate({scrollTop: offset}, 350);
            }
        }

        function set_up(container) {
            // Find prev/next links
            var prev = container.find('.pagination .prev');
            var next = container.find('.pagination .next');

            var prev_url = null, next_url = null;

            if (prev.length)
                prev_url = prev.attr('href');
            if (next.length)
                next_url = next.attr('href');

            function ajax(url, handler) {
                // URL should start with a slash, but cannot start with two slashes.
                // (Otherwise we have an XSS vulnerability.)
                if (url[0] != '/' || url[1] == '/')
                    url = (''+location).replace( /[#\?].*/, '') + url;

                // Append 'xhr' to make sure all content is loaded.
                if (url.indexOf('&xhr') < 0 && url.indexOf('?xhr') < 0)
                    if (url.indexOf('?') < 0)
                        url += '?xhr';
                    else
                        url += '&xhr';

                $.ajax({
                        type: 'GET',
                        url: url,
                        datatype: "html",
                        cache: true,
                        success: handler,
                        error: function(xhr, ajaxOptions, thrownError) {
                            handler($('<div/>').append(
                                    $('<strong>').append(''+xhr.status + ' ' + thrownError)).html());
                        }
                });
            }

            function showLoader() {
                var loader = $('.paginate-loading').eq(0);

                for (var i in containers)
                {
                    // Keep containers height
                    var height = containers[i].height();
                    if (height < 50) height = 50;
                    containers[i].css('height', height + 'px');

                    // Show loader
                    containers[i].empty();
                    containers[i].append(loader.html());
                }
            }

            // Replace page AJAX handler
            function replacePage(url, receivedHtml) {
				// Set location hash
				if (setHash)
					location.hash = 'page:' + url;


                for (var i in containers) {
					// Empty the paginate nodes
                    containers[i].empty();
                    containers[i].css('height', '');

                    // Fill the paginate containers with the new content
                    receivedHtml = $.trim(receivedHtml);
                    $(receivedHtml).find('.paginate-container').eq(i).each(function() {
                        containers[i].append($(this).html());
                        set_up(containers[i]);
                    });
                }

                // Trigger event (Only add trigger to $(document) )
                $(document).trigger('paginatorPageReplaced', [ containers ]);

                // When the URL contains a hash, scroll to that element
                if (location.hash.substr(1).indexOf('#') > 0) {
                    var hash = (''+location.hash).replace( /.*#/, '');
                    scroll(hash);
                }
                // Otherwise, make sure that the top of the clicked paginator is visible
                else {
                    var paginatorTop = container.offset().top;

                    if (paginatorTop < $(window).scrollTop() || paginatorTop > $(window).scrollTop() + $(window).height()) {
                        $('html,body').animate({scrollTop: paginatorTop}, 350);
                    }
                }
            }

            // Process hash (link with # opened from bookmark)
            if (processHash && location.hash.match( /^#page/)) {
                var url = location.hash.replace( /^#page:/, '');
                container.empty();
                ajax(url, function(html) { replacePage(url,html); });
            }
            processHash = false; // Never process twice. (set_up is called recursively)

            // Preload previous page
            var previousPageHtml = null;
            var nextPageHtml = null;
            var clickedPrevious = false;
            var clickedNext = false;

            var preload = $('.pagination_preload').size() > 0;

            if (preload) {
				if (prev_url)
                    ajax(prev_url, function(html) {
                        previousPageHtml = html;
                        if (clickedPrevious) replacePage(prev_url, html);
                    });
                if (next_url)
                    ajax(next_url, function(html) {
                        nextPageHtml = html;
                        if (clickedNext) replacePage(next_url, html);
                    });

                // Handle clicks on the prev/next buttons.
                prev.click(function() {
                    showLoader();
                    if (previousPageHtml)
                        replacePage(prev_url, previousPageHtml);
                    else
                        clickedPrevious = true;
                    return false;
                });

                next.click(function() {
                    showLoader();
                    if (nextPageHtml)
                        replacePage(next_url, nextPageHtml);
                    else
                        clickedNext = true;
                    return false;
                });
            }

            // The digg-paginator also contains direct links to other pages
            // then only the prev and next page.
            container.find('.pagination a, .pagination-helper a, a.pagination-helper').each(function() {

                $(this).click(function (){
                	var url = $(this).attr('href');
                    showLoader();

                    if (url[0] == '#') {
                        var hash = url.replace( /#/, '');
                        scroll(hash);
                    }
                    else
                        ajax(url, function(html) {
							replacePage(url, html);
                        });
                    return false;
                });
            });

            // Pagination forms are forms for which the result is displayed in the paginator
            // They always have method="get" and action="?"
            container.find('form.pagination-form').each(function() {
                var form = $(this);
                if (form.attr('method') == 'get' && form.attr('action').match(/.*?/)) {
                    // Submit handler
                    form.submit(function() {
                        // Build submit query string
                        var url = form.attr('action') + form.serialize();

                        // AJAX request
                        ajax(url, function(html) {
                            replacePage(url, html);
                        });
                        return false;
                    });
                }
            });
        }
    };
};


(function() {
    Pagination.init(true, [ $(document) ]);

    // When a XHR block has been loaded, call init() again,
    // because it may contain a paginator.
        // The location may have been filled with the tab url, so
        // don't process it. (xhr can be nested inside tabs, so even in that case.
    $(document).bind('xhrLoaded', function(e, containers) { Pagination.init(false, containers); });
    $(document).bind('tabLoaded', function(e, containers) { Pagination.init(false, containers); });
}) ();
