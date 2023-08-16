<!-- see section "Template-API" of module manual for a list of available placeholders -->

<!-- available placeholders: action, search_term -->
<search_form>
    <form name="searchResultFormSmall" method="post" action="%%action%%" accept-charset="UTF-8">
        <input type="text" name="searchterm" id="resultSearchtermSmall" value="%%search_term%%"
               onkeyup="KAJONA.portal.searchSmall.queryBackend();"
               onblur="window.setTimeout( function() { $('#searchResultSmall').css('display', 'none');}, 200)" autocomplete="off" />
    </form>

    <div id="searchResultSmall" ></div>
    <div id="resultSetHeaderSmall" style="display: none;">[lang,hitlist_text1,search] <span id="spanSearchtermSmall"></span> [lang,hitlist_text2,search] <span id="spanSearchamountSmall"></span> [lang,hitlist_text3,search]:</div>


    <script type="text/javascript">
    KAJONA.portal.searchSmall =  {
        strLastQuery : "",

        queryBackend : function() {
            var strCurrentQuery = $("#resultSearchtermSmall").val().trim();
            var searchRunning = false;
            var post_target = KAJONA_WEBPATH+"/xml.php?module=search&action=doSearch";
            var post_data = {
                searchterm : strCurrentQuery
            };

            $('#searchResultSmall').html("<ul></ul>");
            $('#plainListSmall').css("display", "none");
            $('#searchResultSmall').css("display", "block");

            if(strCurrentQuery.length >= 2 && strCurrentQuery != KAJONA.portal.searchSmall.strLastQuery) {
                if(searchRunning)
                    return;

                searchRunning = true;
                KAJONA.portal.searchSmall.strLastQuery = strCurrentQuery;
                $('#searchResultSmall').html("<div style='height: 50px; width: 50px; background-image: url(_webpath_/templates/default/pics/default/loading.gif); background-repeat: no-repeat; background-position: center;'></div>");

                $.post(post_target, post_data, function(data, textStatus) {


                    $("#spanSearchtermSmall").html($(data).find("searchterm").text());
                    $("#spanSearchamountSmall").html($(data).find("nrofresults").text());

                    $('#searchResultSmall').html($("#resultSetHeaderSmall").html());
                    $('#searchResultSmall').append($("<ul></ul>"));

                    $(data).find("item").each(function() {
                        var objNode = $("<li></li>");
                        objNode.append("<a href='"+$(this).find("pagelink").find("a").attr("href")+"'>"+$(this).find("pagelink").find("a").text()+"</a>" );
                        objNode.append("<br />"+$(this).find("description").text());
                        $("#searchResultSmall ul").append(objNode);
                        searchRunning = false;
                    });
                }, "xml");
            }
        }
    };
    </script>
</search_form>


    <!-- available placeholders: hitlist, search_term, search_nrresults, link_back, link_overview, link_forward -->
<search_hitlist>
<div id="plainListSmall" class="searchHitList" style="display: none;">
    <ul>%%hitlist%%</ul>
    <div align="center">%%link_back%%&nbsp;&nbsp;%%link_overview%%&nbsp;&nbsp;%%link_forward%%</div>
</div>
</search_hitlist>

<!-- available placeholders: page_link, page_description -->
<search_hitlist_hit>
<li>%%page_link%%<br />%%page_description%%</li>
</search_hitlist_hit>
