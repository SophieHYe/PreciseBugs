function navigate_extensions_init()
{
    // make executable the runnable extensions
    $(".navigrid-item-buttonset[run='1'],.navigrid-item-buttonset[run='true']").parent().on("dblclick", function()
    {
        // is this extension enabled?
        if($(this).find(".navigrid-item-buttonset").attr("enabled")=="0")
            return;

        var extension = $(this).find(".navigrid-item-buttonset").attr("extension");
        //location.href = NAVIGATE_APP + "?fid=extensions&act=run&extension=" + extension;
        location.href = NAVIGATE_APP + "?fid=ext_" + extension;
    });

    // open configuration on NON runnable extensions (if configuration is available)
    $(".navigrid-item-buttonset[run='']").parent().on("dblclick", function()
    {
        // is this extension enabled?
        if($(this).find(".navigrid-item-buttonset").attr("enabled")=="0")
            return;

        // has this extension a configuration option?
        if($(this).find('.navigrid-extensions-settings').length > 0)
            $(this).find('.navigrid-extensions-settings').trigger('click');
    });

    // show extension info window
    $(".navigrid-extensions-info").bind("click", function()
    {
        var extension = $(this).parent().attr("extension");

        $("#navigrid-extension-information").attr("title", $(this).parent().attr("extension-title"));
        $("#navigrid-extension-information").load("?fid=extensions&act=extension_info&extension=" + extension, function()
        {
            $("#navigrid-extension-information").dialog(
                {
                    width: 700,
                    height: 500,
                    modal: true,
                    title: "<img src=\"img/icons/silk/information.png\" align=\"absmiddle\"> " + $("#navigrid-extension-information").attr("title")
                }).dialogExtend(
                {
                    maximizable: true
                });
        });
    });

    // disable extension
    $(".navigrid-extensions-disable").on("click", function()
    {
        var extension = $(this).parent().attr("extension");
        $.post(
            NAVIGATE_APP + "?fid=extensions&act=disable",
            { extension: extension },
            function(data)
            {
                $("div#item-" + extension).find(".navigrid-extensions-enable").hide();
                $("div#item-" + extension).find(".navigrid-extensions-disable").hide();
                $("div#item-" + extension).find(".navigrid-extensions-remove").hide();

                if(data=="true")
                {
                    $("div#item-" + extension).find(".navigrid-extensions-enable").show();
                    $("div#item-" + extension).find(".navigrid-extensions-remove").show();
                    $("div#item-" + extension).find(".navigrid-item-buttonset").attr("enabled", 1);
                }
                else
                {
                    $("div#item-" + extension).find(".navigrid-extensions-disable").show();
                }

                navigate_extensions_refresh();
            });
    });

    // enable extension
    $(".navigrid-extensions-enable").on("click", function()
    {
        var extension = $(this).parent().attr("extension");
        $.post(
            NAVIGATE_APP + "?fid=extensions&act=enable",
            { extension: extension },
            function(data)
            {
                $("div#item-" + extension).find(".navigrid-extensions-enable").hide();
                $("div#item-" + extension).find(".navigrid-extensions-disable").hide();
                $("div#item-" + extension).find(".navigrid-extensions-remove").hide();

                if(data=="true")
                {
                    $("div#item-" + extension).find(".navigrid-extensions-disable").show();
                }
                else
                {
                    $("div#item-" + extension).find(".navigrid-extensions-enable").show();
                    $("div#item-" + extension).find(".navigrid-extensions-remove").show();
                }

                navigate_extensions_refresh();
            });
    });

    // add extension as favorite
    $(".navigrid-extensions-favorite").on("click", function()
    {
        var extension = $(this).parent().attr("extension");
        var add_as_favorite = ($(this).parent().attr("favorite")==0);
        var el = this;

        $.post(
            NAVIGATE_APP + "?fid=extensions&act=favorite",
            { extension: extension,
                value: (add_as_favorite? 1 : 0)
            },
            function(data)
            {
                $(el).find("img").removeClass("silk-heart_add");
                $(el).find("img").removeClass("silk-heart_delete");

                if(data=="true")
                {
                    if(add_as_favorite)
                    {
                        $(el).parent().attr("favorite", 1);
                        $(el).find("img").addClass("silk-heart_delete");
                    }
                    else
                    {
                        $(el).parent().attr("favorite", 0);
                        $(el).find("img").addClass("silk-heart_add");
                    }
                }
                else
                {
                    // show error
                    navigate_notification(navigate_lang_dictionary[56]);
                }

                navigate_extensions_refresh();
            });
    });

    $(".navigrid-extensions-settings").on("click", function()
    {
        var extension = $(this).parent().attr("extension");

        $("#navigrid-extension-options").attr("title", $(this).parent().attr("extension-title"));
        //$("#navigrid-extension-options").load("?fid=extensions&act=options&extension=" + extension, function()
        $("#navigrid-extension-options").html('<iframe width="100%" height="100%" frameborder="0" src="?fid=extensions&act=options&extension=' + extension + '"></iframe>');

        $("#navigrid-extension-options").dialog(
        {
            width: $(window).width() * 0.95,
            height: $(window).height() * 0.95,
            modal: true,
            title: "<img src=\"img/icons/silk/cog.png\" align=\"absmiddle\"> " + $("#navigrid-extension-options").attr("title")
        }).dialogExtend(
        {
            maximizable: true
        });
    });

    $(".navigrid-extensions-remove").bind("click", function()
    {
        var extension = $(this).parent().attr("extension");

        $("#navigrid-extensions-remove-confirmation").dialog(
            {
                resizable: true,
                width: 300,
                height: 150,
                modal: true,
                buttons:
                    [
                        {
                            text: navigate_lang_dictionary[190],
                            click: function()
                            {
                                $.post(
                                    NAVIGATE_APP + "?fid=extensions&act=remove&extension=" + extension,
                                    { },
                                    function(data)
                                    {
                                        if(data=="true")
                                        {
                                            $("#item-" + extension).fadeOut("slow", function(){ $("#item-" + extension).remove(); });
                                        }
                                        else
                                        {
                                            navigate_notification(navigate_lang_dictionary[56]);
                                        }
                                    }
                                );
                                $( this ).dialog( "close" );
                            }
                        },
                        {
                            text: navigate_lang_dictionary[58],
                            click: function()
                            {
                                $( this ).dialog( "close" );
                            }
                        }
                    ]
            });
        return false;
    });

    $(".navigrid-extensions-update").on("click", function()
    {
        var extension = $(this).parent().attr("extension");

        $("#navigrid-extensions-update").dialog(
            {
                resizable: false,
                width: 980,
                height: 650,
                modal: true
            }
        );

        $("#navigrid-extensions-update").find('iframe').
            css({
                "width": "960",
                "height": 600
            }).
            attr('src', 'http://www.navigatecms.com/en/marketplace/purchase?extension='+extension+'&get_update')

        return false;
    });

    $(".navigrid-item-buttonset").each(function(i, el)
    {
        $(el).hide().css("visibility", "visible");
        $(el).fadeIn();
        $(".navigrid-extensions-disable").addClass("ui-corner-right");
    });

    $("#extension-upload-button").on("click", function()
    {
        $("#extension-upload-button").parent().find("form").remove();
        $("#extension-upload-button").after('<form action="?fid=extensions&act=extension_upload" enctype="multipart/form-data" method="post"></form>');
        $("#extension-upload-button").next().append('<input type="hidden" id="_nv_csrf_token" name="_nv_csrf_token" value="'+navigatecms.csrf_token+'" />');
        $("#extension-upload-button").next().append('<input type="file" name="extension-upload" style=" display: none;" />');

        $("#extension-upload-button").next().find("input").on("change", function()
        {
            if($(this).val()!="")
            {
                $(this).parent().submit();
            }
        });
        $("#extension-upload-button").next().find("input").trigger("click");

        return false;
    });
}

function navitable_quicksearch(value)
{
    $(".navigrid-item").hide();

    if(value=="")
        $(".navigrid-item").show();
    else
    {
        $(".navigrid-item").each(function(i, el)
        {
            var item_text = $(el).text().toLowerCase();
            if( item_text.indexOf(value.toLowerCase()) >= 0 )
                $(el).fadeIn();
        });
    }
}

function navigate_extensions_refresh()
{
    $(".navigrid-extensions-enable").each(function(i, el)
    {
        if($(el).is(":visible"))
        {
            $(el).parent().parent().find("*").css("opacity", 0.5);
            $(el).parent().css("opacity", 1);
            $(el).parent().find(".navigrid-extensions-enable, .navigrid-extensions-remove").css("opacity", 0.9);
            $(el).parent().find("img").css("opacity", 1);
            $(el).parent().attr("enabled", '0');
        }
        else
        {
            $(el).parent().parent().find("*").css("opacity", 1);
            $(el).parent().attr("enabled", '1');
        }
    });
}