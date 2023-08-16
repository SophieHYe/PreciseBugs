<!-- see section "Template-API" of module manual for a list of available placeholders -->

<!-- available placeholders: action, search_term -->
<search_form>
    <form name="searchResultForm" method="post" action="%%action%%" accept-charset="UTF-8">
        <div><label for="resultSearchterm">[lang,searchterm_label,search]:</label><input type="text" name="searchterm" id="resultSearchterm" value="%%search_term%%" class="inputText" onkeyup="KAJONA.portal.search.queryBackend();" /></div><br />
        <div><label for="Submit">&nbsp;</label><input type="submit" name="Submit" value="[lang,submit_label,search]" class="button" /></div><br />
    </form>

    <div id="resultSetHeader" style="display: none;">[lang,hitlist_text1,search] <span id="spanSearchterm"></span> [lang,hitlist_text2,search] <span id="spanSearchamount"></span> [lang,hitlist_text3,search]:</div>
    <div id="searchResult"></div>

    <script type="text/javascript">
    KAJONA.portal.search =  {
        strLastQuery : "",

        queryBackend : function() {
            var strCurrentQuery = $("#resultSearchterm").val().trim();
            var searchRunning = false;
            var post_target = KAJONA_WEBPATH+"/xml.php?module=search&action=doSearch";
            var post_data = {
                searchterm : strCurrentQuery
            };

            $('#searchResult').html("<ul></ul>");
            $('#resultSetHeader').css("display", "none");
            $('#plainList').css("display", "none");

            if(strCurrentQuery.length >= 3 && strCurrentQuery != KAJONA.portal.search.strLastQuery) {
                if(searchRunning)
                    return;

                searchRunning = true;
                KAJONA.portal.search.strLastQuery = strCurrentQuery;
                $('#searchResult').html("<div style='height: 50px; background-image: url(_webpath_/templates/default/pics/default/loading.gif); background-repeat: no-repeat; background-position: center;'></div>");

                $.post(post_target, post_data, function(data, textStatus) {
                    $('#searchResult').html("<ul></ul>");
                    $("#spanSearchterm").html($(data).find("searchterm").text());
                    $("#spanSearchamount").html($(data).find("nrofresults").text());
                    $('#resultSetHeader').css("display", "block");

                    $(data).find("item").each(function() {
                        var objNode = $("<li></li>");
                        objNode.append("<a href='"+$(this).find("pagelink").find("a").attr("href")+"'>"+$(this).find("pagelink").find("a").text()+"</a>" );
                        objNode.append("<br />"+$(this).find("description").text());
                        $("#searchResult ul").append(objNode);
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
<div id="plainList" class="searchHitList">
    <ul>%%hitlist%%</ul>
    <div align="center">%%link_back%%&nbsp;&nbsp;%%link_overview%%&nbsp;&nbsp;%%link_forward%%</div>
</div>
</search_hitlist>

    <!-- available placeholders: page_link, page_description -->
<search_hitlist_hit>
<li>%%page_link%%<br />%%page_description%%</li>
</search_hitlist_hit>
