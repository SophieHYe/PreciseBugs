/* jQuery Knob by aterrien, https://github.com/aterrien/jQuery-Knob, MIT License */
(function(e){if(typeof define==="function"&&define.amd){define(["jquery"],e)}else{e(jQuery)}})(function(e){"use strict";var t={},n=Math.max,r=Math.min;t.c={};t.c.d=e(document);t.c.t=function(e){return e.originalEvent.touches.length-1};t.o=function(){var n=this;this.o=null;this.$=null;this.i=null;this.g=null;this.v=null;this.cv=null;this.x=0;this.y=0;this.w=0;this.h=0;this.$c=null;this.c=null;this.t=0;this.isInit=false;this.fgColor=null;this.pColor=null;this.dH=null;this.cH=null;this.eH=null;this.rH=null;this.scale=1;this.relative=false;this.relativeWidth=false;this.relativeHeight=false;this.$div=null;this.run=function(){var t=function(e,t){var r;for(r in t){n.o[r]=t[r]}n._carve().init();n._configure()._draw()};if(this.$.data("kontroled"))return;this.$.data("kontroled",true);this.extend();this.o=e.extend({min:this.$.data("min")!==undefined?this.$.data("min"):0,max:this.$.data("max")!==undefined?this.$.data("max"):100,stopper:true,readOnly:this.$.data("readonly")||this.$.attr("readonly")==="readonly",cursor:this.$.data("cursor")===true&&30||this.$.data("cursor")||0,thickness:this.$.data("thickness")&&Math.max(Math.min(this.$.data("thickness"),1),.01)||.35,lineCap:this.$.data("linecap")||"butt",width:this.$.data("width")||200,height:this.$.data("height")||200,displayInput:this.$.data("displayinput")==null||this.$.data("displayinput"),displayPrevious:this.$.data("displayprevious"),fgColor:this.$.data("fgcolor")||"#87CEEB",inputColor:this.$.data("inputcolor"),font:this.$.data("font")||"Arial",fontWeight:this.$.data("font-weight")||"bold",inline:false,step:this.$.data("step")||1,rotation:this.$.data("rotation"),draw:null,change:null,cancel:null,release:null,format:function(e){return e},parse:function(e){return parseFloat(e)}},this.o);this.o.flip=this.o.rotation==="anticlockwise"||this.o.rotation==="acw";if(!this.o.inputColor){this.o.inputColor=this.o.fgColor}if(this.$.is("fieldset")){this.v={};this.i=this.$.find("input");this.i.each(function(t){var r=e(this);n.i[t]=r;n.v[t]=n.o.parse(r.val());r.bind("change blur",function(){var e={};e[t]=r.val();n.val(n._validate(e))})});this.$.find("legend").remove()}else{this.i=this.$;this.v=this.o.parse(this.$.val());this.v===""&&(this.v=this.o.min);this.$.bind("change blur",function(){n.val(n._validate(n.o.parse(n.$.val())))})}!this.o.displayInput&&this.$.hide();this.$c=e(document.createElement("canvas")).attr({width:this.o.width,height:this.o.height});this.$div=e('<div style="'+(this.o.inline?"display:inline;":"")+"width:"+this.o.width+"px;height:"+this.o.height+"px;"+'"></div>');this.$.wrap(this.$div).before(this.$c);this.$div=this.$.parent();if(typeof G_vmlCanvasManager!=="undefined"){G_vmlCanvasManager.initElement(this.$c[0])}this.c=this.$c[0].getContext?this.$c[0].getContext("2d"):null;if(!this.c){throw{name:"CanvasNotSupportedException",message:"Canvas not supported. Please use excanvas on IE8.0.",toString:function(){return this.name+": "+this.message}}}this.scale=(window.devicePixelRatio||1)/(this.c.webkitBackingStorePixelRatio||this.c.mozBackingStorePixelRatio||this.c.msBackingStorePixelRatio||this.c.oBackingStorePixelRatio||this.c.backingStorePixelRatio||1);this.relativeWidth=this.o.width%1!==0&&this.o.width.indexOf("%");this.relativeHeight=this.o.height%1!==0&&this.o.height.indexOf("%");this.relative=this.relativeWidth||this.relativeHeight;this._carve();if(this.v instanceof Object){this.cv={};this.copy(this.v,this.cv)}else{this.cv=this.v}this.$.bind("configure",t).parent().bind("configure",t);this._listen()._configure()._xy().init();this.isInit=true;this.$.val(this.o.format(this.v));this._draw();return this};this._carve=function(){if(this.relative){var e=this.relativeWidth?this.$div.parent().width()*parseInt(this.o.width)/100:this.$div.parent().width(),t=this.relativeHeight?this.$div.parent().height()*parseInt(this.o.height)/100:this.$div.parent().height();this.w=this.h=Math.min(e,t)}else{this.w=this.o.width;this.h=this.o.height}this.$div.css({width:this.w+"px",height:this.h+"px"});this.$c.attr({width:this.w,height:this.h});if(this.scale!==1){this.$c[0].width=this.$c[0].width*this.scale;this.$c[0].height=this.$c[0].height*this.scale;this.$c.width(this.w);this.$c.height(this.h)}return this};this._draw=function(){var e=true;n.g=n.c;n.clear();n.dH&&(e=n.dH());e!==false&&n.draw()};this._touch=function(e){var r=function(e){var t=n.xy2val(e.originalEvent.touches[n.t].pageX,e.originalEvent.touches[n.t].pageY);if(t==n.cv)return;if(n.cH&&n.cH(t)===false)return;n.change(n._validate(t));n._draw()};this.t=t.c.t(e);r(e);t.c.d.bind("touchmove.k",r).bind("touchend.k",function(){t.c.d.unbind("touchmove.k touchend.k");n.val(n.cv)});return this};this._mouse=function(e){var r=function(e){var t=n.xy2val(e.pageX,e.pageY);if(t==n.cv)return;if(n.cH&&n.cH(t)===false)return;n.change(n._validate(t));n._draw()};r(e);t.c.d.bind("mousemove.k",r).bind("keyup.k",function(e){if(e.keyCode===27){t.c.d.unbind("mouseup.k mousemove.k keyup.k");if(n.eH&&n.eH()===false)return;n.cancel()}}).bind("mouseup.k",function(e){t.c.d.unbind("mousemove.k mouseup.k keyup.k");n.val(n.cv)});return this};this._xy=function(){var e=this.$c.offset();this.x=e.left;this.y=e.top;return this};this._listen=function(){if(!this.o.readOnly){this.$c.bind("mousedown",function(e){e.preventDefault();n._xy()._mouse(e)}).bind("touchstart",function(e){e.preventDefault();n._xy()._touch(e)});this.listen()}else{this.$.attr("readonly","readonly")}if(this.relative){e(window).resize(function(){n._carve().init();n._draw()})}return this};this._configure=function(){if(this.o.draw)this.dH=this.o.draw;if(this.o.change)this.cH=this.o.change;if(this.o.cancel)this.eH=this.o.cancel;if(this.o.release)this.rH=this.o.release;if(this.o.displayPrevious){this.pColor=this.h2rgba(this.o.fgColor,"0.4");this.fgColor=this.h2rgba(this.o.fgColor,"0.6")}else{this.fgColor=this.o.fgColor}return this};this._clear=function(){this.$c[0].width=this.$c[0].width};this._validate=function(e){var t=~~((e<0?-.5:.5)+e/this.o.step)*this.o.step;return Math.round(t*100)/100};this.listen=function(){};this.extend=function(){};this.init=function(){};this.change=function(e){};this.val=function(e){};this.xy2val=function(e,t){};this.draw=function(){};this.clear=function(){this._clear()};this.h2rgba=function(e,t){var n;e=e.substring(1,7);n=[parseInt(e.substring(0,2),16),parseInt(e.substring(2,4),16),parseInt(e.substring(4,6),16)];return"rgba("+n[0]+","+n[1]+","+n[2]+","+t+")"};this.copy=function(e,t){for(var n in e){t[n]=e[n]}}};t.Dial=function(){t.o.call(this);this.startAngle=null;this.xy=null;this.radius=null;this.lineWidth=null;this.cursorExt=null;this.w2=null;this.PI2=2*Math.PI;this.extend=function(){this.o=e.extend({bgColor:this.$.data("bgcolor")||"#EEEEEE",angleOffset:this.$.data("angleoffset")||0,angleArc:this.$.data("anglearc")||360,inline:true},this.o)};this.val=function(e,t){if(null!=e){e=this.o.parse(e);if(t!==false&&e!=this.v&&this.rH&&this.rH(e)===false){return}this.cv=this.o.stopper?n(r(e,this.o.max),this.o.min):e;this.v=this.cv;this.$.val(this.o.format(this.v));this._draw()}else{return this.v}};this.xy2val=function(e,t){var i,s;i=Math.atan2(e-(this.x+this.w2),-(t-this.y-this.w2))-this.angleOffset;if(this.o.flip){i=this.angleArc-i-this.PI2}if(this.angleArc!=this.PI2&&i<0&&i>-.5){i=0}else if(i<0){i+=this.PI2}s=i*(this.o.max-this.o.min)/this.angleArc+this.o.min;this.o.stopper&&(s=n(r(s,this.o.max),this.o.min));return s};this.listen=function(){var t=this,i,s,o=function(e){e.preventDefault();var o=e.originalEvent,u=o.detail||o.wheelDeltaX,a=o.detail||o.wheelDeltaY,f=t._validate(t.o.parse(t.$.val()))+(u>0||a>0?t.o.step:u<0||a<0?-t.o.step:0);f=n(r(f,t.o.max),t.o.min);t.val(f,false);if(t.rH){clearTimeout(i);i=setTimeout(function(){t.rH(f);i=null},100);if(!s){s=setTimeout(function(){if(i)t.rH(f);s=null},200)}}},u,a,f=1,l={37:-t.o.step,38:t.o.step,39:t.o.step,40:-t.o.step};this.$.bind("keydown",function(i){var s=i.keyCode;if(s>=96&&s<=105){s=i.keyCode=s-48}u=parseInt(String.fromCharCode(s));if(isNaN(u)){s!==13&&s!==8&&s!==9&&s!==189&&(s!==190||t.$.val().match(/\./))&&i.preventDefault();if(e.inArray(s,[37,38,39,40])>-1){i.preventDefault();var o=t.o.parse(t.$.val())+l[s]*f;t.o.stopper&&(o=n(r(o,t.o.max),t.o.min));t.change(t._validate(o));t._draw();a=window.setTimeout(function(){f*=2},30)}}}).bind("keyup",function(e){if(isNaN(u)){if(a){window.clearTimeout(a);a=null;f=1;t.val(t.$.val())}}else{t.$.val()>t.o.max&&t.$.val(t.o.max)||t.$.val()<t.o.min&&t.$.val(t.o.min)}});this.$c.bind("mousewheel DOMMouseScroll",o);this.$.bind("mousewheel DOMMouseScroll",o)};this.init=function(){if(this.v<this.o.min||this.v>this.o.max){this.v=this.o.min}this.$.val(this.v);this.w2=this.w/2;this.cursorExt=this.o.cursor/100;this.xy=this.w2*this.scale;this.lineWidth=this.xy*this.o.thickness;this.lineCap=this.o.lineCap;this.radius=this.xy-this.lineWidth/2;this.o.angleOffset&&(this.o.angleOffset=isNaN(this.o.angleOffset)?0:this.o.angleOffset);this.o.angleArc&&(this.o.angleArc=isNaN(this.o.angleArc)?this.PI2:this.o.angleArc);this.angleOffset=this.o.angleOffset*Math.PI/180;this.angleArc=this.o.angleArc*Math.PI/180;this.startAngle=1.5*Math.PI+this.angleOffset;this.endAngle=1.5*Math.PI+this.angleOffset+this.angleArc;var e=n(String(Math.abs(this.o.max)).length,String(Math.abs(this.o.min)).length,2)+2;this.o.displayInput&&this.i.css({width:(this.w/2+4>>0)+"px",height:(this.w/3>>0)+"px",position:"absolute","vertical-align":"middle","margin-top":(this.w/3>>0)+"px","margin-left":"-"+(this.w*3/4+2>>0)+"px",border:0,background:"none",font:this.o.fontWeight+" "+(this.w/e>>0)+"px "+this.o.font,"text-align":"center",color:this.o.inputColor||this.o.fgColor,padding:"0px","-webkit-appearance":"none"})||this.i.css({width:"0px",visibility:"hidden"})};this.change=function(e){this.cv=e;this.$.val(this.o.format(e))};this.angle=function(e){return(e-this.o.min)*this.angleArc/(this.o.max-this.o.min)};this.arc=function(e){var t,n;e=this.angle(e);if(this.o.flip){t=this.endAngle+1e-5;n=t-e-1e-5}else{t=this.startAngle-1e-5;n=t+e+1e-5}this.o.cursor&&(t=n-this.cursorExt)&&(n=n+this.cursorExt);return{s:t,e:n,d:this.o.flip&&!this.o.cursor}};this.draw=function(){var e=this.g,t=this.arc(this.cv),n,r=1;e.lineWidth=this.lineWidth;e.lineCap=this.lineCap;if(this.o.bgColor!=="none"){e.beginPath();e.strokeStyle=this.o.bgColor;e.arc(this.xy,this.xy,this.radius,this.endAngle-1e-5,this.startAngle+1e-5,true);e.stroke()}if(this.o.displayPrevious){n=this.arc(this.v);e.beginPath();e.strokeStyle=this.pColor;e.arc(this.xy,this.xy,this.radius,n.s,n.e,n.d);e.stroke();r=this.cv==this.v}e.beginPath();e.strokeStyle=r?this.o.fgColor:this.fgColor;e.arc(this.xy,this.xy,this.radius,t.s,t.e,t.d);e.stroke()};this.cancel=function(){this.val(this.v)}};e.fn.dial=e.fn.knob=function(n){return this.each(function(){var r=new t.Dial;r.o=n;r.$=e(this);r.run()}).parent()}})


//downloadCoebotData();
//var channelCoebotData = getCoebotDataChannel(channel);
var isHighlightsLoaded = false;
var isBoirLoaded = false;

var hlstreamTable = false;

var hlstreamPlayMe = false;
var highlightsWentBeepBeep = false;

var hashPostfix = "";

function enableSidebar() {

	$('#navSidebar a.js-sidebar-link').click(function (e) {
		e.preventDefault();
		$(this).tab('show');
        //var hashExtension = HASH_DELIMITER + window.location.hash.substr(1).split(HASH_DELIMITER).splice(0,1).join(HASH_DELIMITER);
        window.location.hash = "#" + $(this).attr("href").substr(5) + hashPostfix;// + hashExtension;
        hashPostfix = "";

        $('#channelSidebarCollapse').collapse('hide');
	});

	$('#navSidebar a.js-sidebar-link').on('shown.bs.tab', function (e) {
		var tab = e.target;

        //TODO functionify tab updates!
        var tabIconHtml = $(tab).children('.sidebar-icon').html();
        var tabTitleHtml = $(tab).children('.sidebar-title').attr("data-bigtitle");

        $(".js-channel-tab-icon").html(tabIconHtml);
        $(".js-channel-tab-title").html(tabTitleHtml);
	});

    $('#navSidebar a.js-sidebar-link[href="#tab_highlights"]').on('show.bs.tab', function (e) {
        if (!isHighlightsLoaded) {
            loadChannelHighlights();
        }
    });

    $('#navSidebar a.js-sidebar-link[href="#tab_boir"]').on('show.bs.tab', function (e) {
        if (!isBoirLoaded) {
            loadChannelBoir();
            loadBoirItemData();
        }
    });

    if (channelCoebotData.shouldShowBoir) {
        $("#sidebarItemGames").removeClass("hidden");
    }
}

function tabContentLoaded() {
    if (window.location.hash != "") {
        var explodedHash = window.location.hash.substr(1).split(HASH_DELIMITER);
        var jumpToTab = explodedHash.splice(0,1);
        if (explodedHash.length >= 1) {
            hashPostfix = HASH_DELIMITER + explodedHash.join(HASH_DELIMITER);
        }

        $('#navSidebar a[href="#tab_' + jumpToTab + '"]').click();
    }

    $('#hlStreamModal').on('hidden.bs.modal', function (e) {
        window.location.hash = window.location.hash.split(HASH_DELIMITER)[0];
    });

    linkifyEverything();
}

// channel config data
var channelData = false;
var channelBoirData = false;
var channelTwitchData = false;
var twitchEmotes = false;
var channelStreamData = false;
var highlightsStats = false;
var currentHlstream = false;
var boirItemData = false;

function downloadChannelData() {
	$.ajax({
		async: false, // it's my json and i want it NOW!
		dataType: "json",
		url: "/configs/" + channel + ".json",
		success: function(json) {
            console.log("Loaded channel data");
			channelData = json;
		},
		error: function(jqXHR, textStatus, errorThrown) {
			alert("Failed to load channel data!");
			window.location = "/";
		}
	});
}

downloadChannelData();

function displayChannelTitle() {
	var titleHtml = channelCoebotData.displayName; // ((channelCoebotData.displayName&&channelCoebotData.displayName=="") ? channel : channelCoebotData.displayName);
    var tabIconHtml = $('#navSidebar a:first-child .sidebar-icon').html();
    var tabTitleHtml = $('#navSidebar a:first-child .sidebar-title').html();
    $(".js-channel-title").html(titleHtml);
    $(".js-channel-tab-icon").html(tabIconHtml);
    $(".js-channel-tab-title").html(tabTitleHtml);
}

function displayChannelOverview() {
    var html = "";

    html += '<p>Bot name: ' + channelCoebotData.botChannel + '</p>';


    var wpMoment = (channelData.sinceWp !== null) ? moment(channelData.sinceWp) : null;
    html += '<p class="whale-penis">Whale penis was last mentioned ' + wpMoment.fromNow() + '. It has been mentioned ' + Humanize.intComma(channelData.wpCount) + ' times.';


    html += '<p class="">';
    html += '<a class="btn btn-primary overview-socialbtn" href="http://www.twitch.tv/';
    html += channel + '" target="_blank"><i class="icon-twitch"></i> Twitch</a>';

    if (channelCoebotData.youtube && channelCoebotData.youtube != "") {
        html += ' <a class="btn btn-default overview-socialbtn" href="http://www.youtube.com/user/';
        html += channelCoebotData.youtube + '" target="_blank"><i class="icon-youtube-play"></i> YouTube</a>';
    }

    if (channelCoebotData.twitter && channelCoebotData.twitter != "") {
        html += ' <a class="btn btn-default overview-socialbtn" href="http://twitter.com/';
        html += channelCoebotData.twitter + '" target="_blank"><i class="icon-twitter"></i> Twitter</a>';
    }

    if (channelData.steamID && channelData.steamID != "") {
        html += ' <a class="btn btn-default overview-socialbtn" href="http://steamcommunity.com/profiles/';
        html += channelData.steamID + '" target="_blank"><i class="icon-steam"></i> Steam</a>';
    }

    if (channelData.lastfm && channelData.lastfm != "") {
        html += ' <a class="btn btn-default overview-socialbtn" href="http://www.last.fm/user/';
        html += channelData.lastfm + '" target="_blank"><i class="icon-lastfm"></i> last.fm</a>';
    }

    if (channelData.extraLifeID) {
        html += ' <a class="btn btn-default overview-socialbtn" href="http://www.extra-life.org/index.cfm?fuseaction=donorDrive.participant&participantID=';
        html += channelData.extraLifeID + '" target="_blank">Extra Life</a>';
    }

    html += '</p>';
    var ref = $(".js-channel-overview");
    ref.html(html);
}

function displayChannelCommands() {
	var tbody = $('.js-commands-tbody');
	var rows = "";
    var shouldSortTable = true;
	for (var i = 0; i < channelData.commands.length; i++) {
		var cmd = channelData.commands[i];
		var row = '<tr class="row-command row-command-access-' + cmd.restriction +'">';
        row += '<td class="js-commands-editcolumn"><span class="table-edit-btn" data-toggle="modal" data-target="#commandAddModal" data-command="' + cmd.key + '" data-accesslevel="' + cmd.restriction + '" data-response="' + cleanHtmlAttr(cmd.value) + '" data-modaltitle="Edit command"><i class="icon-pencil"></i><span class="sr-only">Edit</span></span></td>';
		row += '<td><kbd class="command">' + cmd.key + '</kbd></td>';
        row += '<td class="row-command-col-access" data-order="' + cmd.restriction + '">' + prettifyAccessLevel(cmd.restriction) + '</td>';
        row += '<td class="should-be-linkified should-be-emotified">' + prettifyStringVariables(cleanHtmlText(cmd.value)) + '</td>';
		row += '<td>' + Humanize.intComma(cmd.count) + '</td>';
		row += '</tr>';
		rows += row;
	}
    if (rows == "") {
        rows = '<tr><td colspan="5" class="text-center">' + EMPTY_TABLE_PLACEHOLDER + '</td></tr>';
        shouldSortTable = false;
    }
	tbody.html(rows);

    if (shouldSortTable) {
        $('.js-commands-table').dataTable({
            "paging": false,
            "info": false,
            "order": [[ 1, "asc" ]],
            "columnDefs": [
                { "orderable": false, "targets": 0 }
              ]
        });
    }

    if (userAccessLevel >= USER_ACCESS_LEVEL_MOD) {
        $('.js-commands-addbtn').css('display', 'block');
        $('.js-commands-editcolumn').css('display', 'table-cell');
    }

    $('#commandAddModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var command = button.data('command');
        var accessLevel = button.data('accesslevel');
        var response = button.data('response');
        var modalTitle = button.data('modaltitle');

        var modal = $(this);
        $('#commandAddModalName').val(command);
        $('#commandAddModalOldName').val(command);
        var accessLevelLabel = $('.js-commands-addmodal-accesslevel label.level' + accessLevel);
        accessLevelLabel.addClass('active');
        accessLevelLabel.find('input').attr("checked", true);
        $('#commandAddModalResponse').val(response);
        $('#commandAddModalLabel').text(modalTitle);
    });

    $('#commandAddModalSave').click(function(e) {

        var $btn = $(this).button('loading');

        $.ajax({
            data: {
                a: "setCommand",
                channel: channel,
                name: $('#commandAddModalName').val(),
                oldName: $('#commandAddModalOldName').val(),
                response: $('#commandAddModalResponse').val(),
                restriction: $('input:checked', '#commandAddModalAccessLevel').val()
            },
            dataType: "text",
            url: "/botaction.php",
            success: function(txt) {
                if (txt == "success") {
                    $btn.button('reset');
                    Messenger().post({
                      message: 'Command successfully modified!',
                      type: 'success'
                    });
                } else {
                    Messenger().post({
                      message: "Error: " + txt,
                      type: 'error'
                    });
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                Messenger().post({
                  message: "A connection error occurred.",
                  type: 'error'
                });
            },
            complete: function(jqXHR, textStatus) {
                $btn.button('reset');
            }
        });

    });
}

function displayChannelQuotes() {
	var tbody = $('.js-quotes-tbody');
	var rows = "";
    var shouldSortTable = true;
	for (var i = 0; i < channelData.quotes.length; i++) {
		var quote = channelData.quotes[i];
		var row = '<tr>';
        row += '<td class="js-quotes-editcolumn"><span class="table-edit-btn" data-toggle="modal" data-target="#quoteAddModal" data-quote="' + cleanHtmlAttr(quote.quote) + '" data-quoteid="' + (i+1) + '" data-modaltitle="Edit quote"><i class="icon-pencil"></i></span></td>';

		row += '<td>' + (i+1) + '</td>';
        row += '<td>' + cleanHtmlText(quote.quote) + '</td>';

        var tsMoment = (quote.timestamp !== null) ? moment(quote.timestamp) : null;
        var tsStr = (quote.timestamp !== null) ? tsMoment.calendar() : "Unknown";
        var cleanTs = (quote.timestamp !== null) ? cleanHtmlAttr(tsMoment.format('LLLL')) : "This quote was added before CoeBot kept track of when quotes were added.";
        var sortNum = (quote.timestamp !== null) ? quote.timestamp : 0;
        row += '<td title="' + cleanTs + '" data-order="' + sortNum + '">' + tsStr + '</td>';

		row += '</tr>';
		rows += row;
	}
    if (rows == "") {
        rows = '<tr><td colspan="4" class="text-center">' + EMPTY_TABLE_PLACEHOLDER + '</td></tr>';
        shouldSortTable = false;
    }

	tbody.html(rows);


    if (shouldSortTable) {
        $('.js-quotes-table').dataTable({
            "paging": false,
            "info": false,
            "order": [[ 1, "asc" ]],
            "columnDefs": [
                { "orderable": false, "targets": 0 }
              ]
        });
    }

    if (userAccessLevel >= USER_ACCESS_LEVEL_MOD) {
        $('.js-quotes-addbtn').css('display', 'block');
        $('.js-quotes-editcolumn').css('display', 'table-cell');
    }

    $('#quoteAddModal').on('show.bs.modal', function (event) {

        var button = $(event.relatedTarget);
        var quoteStr = button.data('quote');
        var quoteId = button.data('quoteid');
        var modalTitle = button.data('modaltitle');

        var modal = $(this);
        $('#quoteAddModalQuote').val(quoteStr);
        $('#quoteAddModalId').val(quoteId);
        $('#quoteAddModalLabel').text(modalTitle);
    });
}

function displayChannelAutoreplies() {
    var tbody = $('.js-autoreplies-tbody');
    var rows = "";
    var shouldSortTable = true;
    for (var i = 0; i < channelData.autoReplies.length; i++) {
        var reply = channelData.autoReplies[i];
        var row = '<tr>';
        row += '<td class="js-autoreplies-editcolumn"><span class="table-edit-btn" data-toggle="modal" data-target="#autoreplyAddModal" data-trigger="' + cleanHtmlAttr(reply.trigger) + '" data-response="' + cleanHtmlAttr(reply.response) + '" data-arid="' + (i+1) + '" data-modaltitle="Edit auto-reply"><i class="icon-pencil"></i></span></td>';
        row += '<td>' + (i+1) + '</td>';
        row += '<td title="RegEx: ' + cleanHtmlAttr(reply.trigger) + '">' + prettifyRegex(reply.trigger) + '</td>';
        row += '<td>' + prettifyStringVariables(cleanHtmlText(reply.response)) + '</td>';
        row += '</tr>';
        rows += row;
    }
    if (rows == "") {
        rows = '<tr><td colspan="4" class="text-center">' + EMPTY_TABLE_PLACEHOLDER + '</td></tr>';
        shouldSortTable = false;
    }

    tbody.html(rows);

    if (shouldSortTable) {
        $('.js-autoreplies-table').dataTable({
            "paging": false,
            "info": false,
            "searching": false,
            "order": [[ 1, "asc" ]],
            "columnDefs": [
                { "orderable": false, "targets": 0 }
              ]
        });
    }

    if (userAccessLevel >= USER_ACCESS_LEVEL_MOD) {
        $('.js-autoreplies-addbtn').css('display', 'block');
        $('.js-autoreplies-editcolumn').css('display', 'table-cell');
    }

    $('#autoreplyAddModal').on('show.bs.modal', function (event) {

        var button = $(event.relatedTarget);
        var arid = button.data('arid');
        var trigger = button.data('trigger');
        var response = button.data('response');
        var modalTitle = button.data('modaltitle');

        var modal = $(this);
        $('#autoreplyAddModalArid').val(arid);
        $('#autoreplyAddModalTrigger').val(trigger);
        $('#autoreplyAddModalResponse').val(response);
        $('#autoreplyAddModalLabel').text(modalTitle);
    });
}

function displayChannelScheduled() {
    var tbody = $('.js-scheduled-tbody');
    var rows = "";
    var shouldSortTable = true;
    for (var i = 0; i < channelData.scheduledCommands.length; i++) {
        var cmd = channelData.scheduledCommands[i];
        if (cmd.active) {
            var row = '<tr>';
            row += '<td><kbd class="command">' + cmd.name + '</kbd></td>';
            row += '<td><span title="Cron command: ' + cmd.pattern + '">'
            row += prettyCron.toString(cmd.pattern) + '</td>';
            row += '</tr>';
            rows += row;
        }
    }
    for (var i = 0; i < channelData.repeatedCommands.length; i++) {
        var cmd = channelData.repeatedCommands[i];
        if (cmd.active) {
            var row = '<tr>';
            row += '<td><kbd class="command">' + cmd.name + '</kbd></td>';
            row += '<td><span title="Every ' + cmd.delay + ' seconds">Every '
            row += moment().subtract(cmd.delay, 'seconds').fromNow(true) + '</span></td>';
            row += '</tr>';
            rows += row;
        }
    }
    if (rows == "") {
        rows = '<tr><td colspan="2" class="text-center">' + EMPTY_TABLE_PLACEHOLDER + '</td></tr>';
        shouldSortTable = false;
    }

    tbody.html(rows);

    if (shouldSortTable) {
        $('.js-scheduled-table').dataTable({
            "paging": false,
            "info": false,
            "searching": false
        });
    }
}

function displayChannelRegulars() {
    var tbody = $('.js-regulars-tbody');
    var rows = "";
    var shouldSortTable = true;
    for (var i = 0; i < channelData.regulars.length; i++) {
        var reg = channelData.regulars[i];
        var row = '<tr>';
        row += '<td class="text-capitalize">' + reg + '</td>';
        row += '</tr>';
        rows += row;
    }
    if (rows == "") {
        rows = '<tr><td colspan="1" class="text-center">' + EMPTY_TABLE_PLACEHOLDER + '</td></tr>';
        shouldSortTable = false;
    }

    tbody.html(rows);

    if (shouldSortTable) {
        $('.js-regulars-table').dataTable();
    }


    var subsinfoText = 'On this channel, ';
    if (channelData.subscriberRegulars) {
        subsinfoText += 'subscribers are automatically given all the same privileges as regulars.';
    } else if (channelData.subsRegsMinusLinks) {
        subsinfoText += 'subscribers are automatically given the same privileges as regulars, except they cannot post links or use the <kbd class="command">urban</kbd> command.';
    } else {
        subsinfoText += 'subscribers do not automatically receive the same privileges as regulars.';
    }

    $('.js-regulars-subsinfo').html(subsinfoText);
}

function displayChannelChatrules() {
    // var html = ""
    // html += '<h3>Banned phrases</h3>'

    if (channelCoebotData.shouldShowOffensiveWords && channelData.filterOffensive) {

        var tbody = $('.js-chatrules_offensive-tbody');
        var rows = "";
        var shouldSortTable = true;
        for (var i = 0; i < channelData.offensiveWords.length; i++) {
            var word = channelData.offensiveWords[i];
            var row = '<tr>';
            row += '<td title="RegEx: ' + cleanHtmlAttr(word) + '">' + prettifyRegex(word) + '</td>';
            row += '</tr>';
            rows += row;
        }
        if (rows == "") {
            rows = '<tr><td colspan="1" class="text-center">' + EMPTY_TABLE_PLACEHOLDER + '</td></tr>';
            shouldSortTable = false;
        }

        tbody.html(rows);

        if (shouldSortTable) {
            $('.js-chatrules_offensive-table').dataTable({
                "paging": false,
                "info": false,
                "searching": false
            });
        }
    } else {
        $('.js-chatrules_offensive').addClass("hidden");
    }

    // console.log(channelData.useFilters);

    if (channelData.useFilters) {

        var miscHtml = '';
        miscHtml += '<h3>Filter rules</h3>';

        if (channelData.filterCaps) {
            miscHtml += '<p>Messages with excessive capital letters will be censored if the message contains at least ' + channelData.filterCapsMinCapitals + ' capital letters and consists more than ' + channelData.filterCapsPercent + '% of capital letters.</p>';
        }
        if (channelData.filterLinks) {
            miscHtml += '<p>All URLs linked to by non-regulars ';
            if (channelData.subscriberRegulars) {
                miscHtml += '(excluding subscribers) ';
            } else {
                miscHtml += '(including subscribers) ';
            }
            miscHtml += ' will be censored.';
            if (channelData.permittedDomains && channelData.permittedDomains.length != 0) {
                miscHtml += ' However, the following domains are exempt from censoring: ';
                miscHtml += Humanize.oxford(channelData.permittedDomains);
            }
            miscHtml += '</p>';
        }
        // if (channelData.filterSymbols) {
        //     miscHtml += '<p>All caps will be filtered if a message contains more than ' + channelData.filterCapsPercent + '% uppercase letters.</p>'
        // }

        $(".js-chatrules_misc").html(miscHtml);
    }


    // $(".js-chatrules-div").html(html);
}


function loadChannelHighlights() {

    var explodedHash = window.location.hash.substr(0).split(HASH_DELIMITER);

    if (explodedHash.length >= 2) {
        hlstreamPlayMe = explodedHash[1];
        if (highlightsWentBeepBeep) {
            loadHlstream(hlstreamPlayMe);
            hlstreamPlayMe = false;
        }
    }

    $.ajax({
        dataType: "jsonp",
        jsonp: false,
        jsonpCallback: "loadChannelHighlightsCallback",
        url: "/oldhl/api/stats/" + channel + "&callback=loadChannelHighlightsCallback",
        success: function(json) {
            console.log("Loaded highlights stats");
            highlightsStats = json;
            isHighlightsLoaded = true;
            showChannelHighlights();
        },
        error: function(jqXHR, textStatus, errorThrown) {
            alert("Failed to load highlights!");
        }
    });
}

function showChannelHighlights() {

    var tbody = $('.js-highlights-tbody');
    var rows = "";
    var shouldSortTable = true;
    for (var i = 0; i < highlightsStats.streams.length; i++) {
        var strm = highlightsStats.streams[i];
        var row = '<tr>';

        row += '<td><span class="fake-link js-highlight-btn" data-hlid="' + strm.id + '">' + cleanHtmlText(strm.title) + '</span></td>';

        var startMoment = moment.unix(strm.start);
        var cleanStart = cleanHtmlAttr(startMoment.format('LLLL'));
        row += '<td title="' + cleanStart + '" data-order="' + strm.start + '">' + startMoment.calendar() + '</td>';

        var durationMoment = moment.duration(strm.duration, 'seconds');
        var cleanDuration = cleanHtmlAttr(stringifyDuration(durationMoment));
        row += '<td title="' + cleanDuration + '" data-order="' + strm.duration + '">' + durationMoment.humanize() + '</td>';
        row += '<td data-order="' + strm.hlcount + '">' + Humanize.intComma(strm.hlcount) + '</td>';
        row += '</tr>';


        rows += row;
    }
    if (rows == "") {
        rows = '<tr><td colspan="4" class="text-center">' + EMPTY_TABLE_PLACEHOLDER + '</td></tr>';
        shouldSortTable = false;
    }

    tbody.html(rows);

    if (shouldSortTable) {
        $('.js-highlights-table').dataTable({
            "paging": false,
            "info": false,
            "order": [[ 1, "desc" ]]
        });
    }

    $('.js-highlights-loading').addClass('hidden');
    $('.js-highlights-table').removeClass('hidden');

    $('.js-highlight-btn').click(function() {
        var hlid = $(this).attr('data-hlid');
        loadHlstream(hlid);
    });

}


function loadHlstream(id) {

    $('.js-hlstream-loaded, .js-hlstream-loaded-inline').css('display', 'none');
    $('.js-hlstream-loading').css('display', 'block');
    $('.js-hlstream-loading-inline').css('display', 'inline');

    $('#hlStreamModal').modal('show');

    window.location.hash += HASH_DELIMITER + id;

    $.ajax({
        dataType: "jsonp",
        jsonp: false,
        jsonpCallback: "loadHlstreamCallback",
        url: "/oldhl/api/hl/" + channel + "/" + id + "/&callback=loadHlstreamCallback",
        success: function(json) {
            console.log("Loaded hlstream #" + id);
            currentHlstream = json;
            showHlstream();
        },
        error: function(jqXHR, textStatus, errorThrown) {
            alert("Failed to load highlight!");
        }
    });
}

function showHlstream() {

    $('.js-hlstream-title').html(currentHlstream.title);

    $('.js-hlstream-twitchlink').attr("href", getUrlForTwitchVod(channel, currentHlstream.id));


    var playerVars = "title=" + currentHlstream.title + "&amp;channel=" + channel
    playerVars += "&amp;auto_play=false&amp;start_volume=100&amp;videoId=" + currentHlstream.id;

    var playerHtml = "";
    playerHtml += "<object bgcolor='#313131' data='https://www.twitch.tv/widgets/archive_embed_player.swf' height='472' id='player' type='application/x-shockwave-flash' width='775'>";
    playerHtml += "<param name='movie' value='https://www.twitch.tv/widgets/archive_embed_player.swf' />";
    playerHtml += "<param name='allowScriptAccess' value='always' />";
    playerHtml += "<param name='allowNetworking' value='all' />";
    playerHtml += "<param name='allowFullScreen' value='true' />";
    playerHtml += "<param name='flashvars' value='" + playerVars + "' />";
    playerHtml += "</object>";

    var playerParent = $(".js-hlstream-player-parent");
    playerParent.empty();
    playerParent.html(playerHtml);


    var tableTemplate = '';
    tableTemplate += '<table class="table table-striped js-hlstream-table">';
    tableTemplate += '<thead>';
    tableTemplate += '<tr>';
    tableTemplate += '<th><i class="sorttable-icon"></i>Time</th>';
    tableTemplate += '<th><i class="sorttable-icon"></i>Hits</th>';
    tableTemplate += '</tr>';
    tableTemplate += '</thead>';
    tableTemplate += '<tbody class="js-hlstream-tbody"></tbody>';
    tableTemplate += '</table>';

    var tableParent = $('.js-hlstream-table-parent');
    tableParent.empty();
    tableParent.html(tableTemplate);

    var tbody = $('.js-hlstream-tbody');
    var rows = "";
    var shouldSortTable = true;
    for (var i = 0; i < currentHlstream.highlights.length; i++) {
        var hl = currentHlstream.highlights[i];
        var row = '<tr>';

        var durationMoment = moment.duration(hl.position, 'seconds');
        var cleanDuration = cleanHtmlAttr(stringifyDurationShort(durationMoment, true));
        row += '<td data-order="' + hl.position + '"><span onclick="jumpHlstreamTimestamp('
        row += hl.position + ')" class="fake-link">' + cleanDuration + '</span></td>';

        row += '<td data-order="' + hl.hits + '">' + Humanize.intComma(hl.hits) + '</td>';
        row += '</tr>';

        rows += row;
    }
    if (rows == "") {
        rows = '<tr><td colspan="2" class="text-center">' + EMPTY_TABLE_PLACEHOLDER + '</td></tr>';
        shouldSortTable = false;
    }

    tbody.html(rows);

    if (shouldSortTable) {
        hlstreamTable = $('.js-hlstream-table').dataTable({
            "paging": false,
            "info": false,
            "searching": false
        });
    }

    $('.js-hlstream-loading, .js-hlstream-loading-inline').css('display', 'none');
    $('.js-hlstream-loaded').css('display', 'block');
    $('.js-hlstream-loaded-inline').css('display', 'inline');

}

function beepBeepHeresYourHighlights() {
    if (hlstreamPlayMe !== false) {
        loadHlstream(hlstreamPlayMe);
        hlstreamPlayMe = false;
    }
    highlightsWentBeepBeep = true;
}

function jumpHlstreamTimestamp(timestamp) {
    player.videoSeek(timestamp);
}


function loadChannelBoir() {

    var explodedHash = window.location.hash.substr(0).split(HASH_DELIMITER);

    if (explodedHash.length >= 2) {
        loadHlstream(parseInt(explodedHash[1]));
    }

    $.ajax({
        dataType: "json",
        url: "/configs/boir/" + channel + ".json",
        success: function(json) {
            console.log("Loaded channel BOIR data");
            channelBoirData = json;
            isBoirLoaded = true;
            showChannelBoir();
        },
        error: function(jqXHR, textStatus, errorThrown) {
            alert("Failed to load BOIR data!");
        }
    });
}

function showChannelBoir() {

    var boirContainer = $('.js-boir-container');
    var html = "";

    html += '<div class="boir-character"><strong>Character:</strong> ' + cleanHtmlText(channelBoirData.character) + "</div>";
    html += '<div class="boir-floor"><strong>Floor:</strong> ' + cleanHtmlText(channelBoirData.floor) + "</div>";
    html += '<div class="boir-seed"><strong>Seed:</strong> ' + cleanHtmlText(channelBoirData.seed) + "</div>";
    html += '<h3>Items</h3>';

    html += '<div class="well boir-items items-container">';//<div class="row">';
    for (var i = 0; i < channelBoirData.items.length; i++) {
        var item = channelBoirData.items[i];
        item = item.replace(/</gi, "&lt;");
        html += '<div class="boir-item">';//html += '<div class="col-xs-6 col-sm-3 col-md-2 boir-item">';
        html += '<div class="rebirth-item" data-item="' + cleanBoirNameForComparison(item) + '">' + item + '&nbsp;&nbsp;&nbsp;</div>';
        //html += '<span class="boir-item-subtitle">' + item + '</span>';
        html += '</div>';
    }
    html += '</div>';//</div>';
    html += '<p class="small">Item data from <a href="http://platinumgod.co.uk/rebirth" target="_blank">platinumgod.co.uk</a></em></p>'

    if (typeof channelBoirData.flyItems !== 'undefined' && typeof channelBoirData.flyProgress !== 'undefined') {
        html += '<div class="col-md-6 text-center">';
        html += '<h3>Lord of the Flies</h3>';
        html += '<input type="text" class="dial js-boir-dial js-boir-dial-fly" value="' + cleanHtmlAttr(channelBoirData.flyProgress) + '">';

        // html += '<h4>Items</h4>';
        html += '<div class="row"><div class="col-sm-8 col-sm-offset-2"><ul class="list-group">'
        for (var i = 0; i < channelBoirData.flyItems.length; i++) {
            var item = channelBoirData.flyItems[i];
            html += '<li class="list-group-item">' + cleanHtmlText(item) + '</li>';
        }
        html += '</ul></div></div>';
        html += '</div>';
    }

    if (typeof channelBoirData.guppyItems !== 'undefined' && typeof channelBoirData.guppyProgress !== 'undefined') {
        html += '<div class="col-md-6 text-center">';
        html += '<h3>Guppy</h3>';
        html += '<input type="text" class="dial js-boir-dial js-boir-dial-guppy" value="' + cleanHtmlAttr(channelBoirData.guppyProgress) + '">';

        // html += '<h4>Items</h4>';
        html += '<div class="row"><div class="col-sm-8 col-sm-offset-2"><ul class="list-group">'
        for (var i = 0; i < channelBoirData.guppyItems.length; i++) {
            var item = channelBoirData.guppyItems[i];
            html += '<li class="list-group-item">' + cleanHtmlText(item) + '</li>';
        }
        html += '</ul></div></div>';
    }
        html += '</div>';

    boirContainer.html(html);

    boiItemDataDomReady = true;
    showBoirItemData();

    $(".js-boir-dial").knob({
        'min': 0,
        'max': 3,
        'readOnly': true,
        'angleOffset':-125,
        'angleArc': 250,
        'fgColor': '#2A9FD6',
        'bgColor': '#151515',
        'format': function(v) { return v + "/3"; }
    });
    //$('.js-boir-dial-fly').val(channelBoirData.flyProgress).trigger('change');

    $('.js-boir-loading').addClass('hidden');
    $('.js-boir-loaded').removeClass('hidden');

}

function loadBoirItemData() {

    $.ajax({
        cache: true,
        dataType: "json",
        url: "/boiitemsarray.json",
        success: function(json) {
            console.log("Loaded BOIR item data");
            boirItemData = json;

            for (var i = 0; i < boirItemData.length; i++) {
                boirItemData[i].safename = cleanBoirNameForComparison(boirItemData[i].title);
            }

            boiItemDataAjaxReady = true;
            showBoirItemData();
        },
        error: function(jqXHR, textStatus, errorThrown) {
            alert("Failed to load BOIR items data!");
        }
    });
}

var boiItemDataDomReady = false;
var boiItemDataAjaxReady = false;

function showBoirItemData() {
    if (!boiItemDataDomReady || !boiItemDataAjaxReady) {
        return;
    }

    var itemSelector = $(".items-container .rebirth-item");

    itemSelector.each(function() {
        var div = $(this);
        div.html(""); // remove the placeholder title
        var item = div.attr("data-item");
        var data = findItemInBoirData(item);

        if (data != null) {
            div.addClass(data.class);
            div.attr("title", data.title);

            var content = '';
            content += '<div class="text-center text-primary"><em>&ldquo;' + data.pickup + '&rdquo;</em></div>';

            if (data.info.length > 0) {
                content += '<ul class="boir-item-infolist">';
                for (var i = 0; i < data.info.length; i++) {
                    content += '<li>' + data.info[i] + '</li>';
                }
                content += '</ul>';
            }

            if (data.unlock != "") {
                content += '<div><strong>Unlock:</strong> ' + data.unlock + '</div>';
            }

            if (data.type != "") {
                content += '<div><strong>Type:</strong> ' + data.type + '</div>';
            }

            if (data.recharge != "") {
                content += '<div><strong>Recharge Time:</strong> ' + data.recharge + '</div>';
            }

            if (data.itempool != "") {
                content += '<div><strong>Item Pool:</strong> ' + data.itempool + '</div>';
            }

            div.attr("data-content",content);
        }
    });

    itemSelector.popover({
        html: true,
        placement: 'auto top',
        trigger: 'hover'
    });
}

function findItemInBoirData(name) {
    for (var i = 0; i < boirItemData.length; i++) {
         if (boirItemData[i].safename == name) {
            return boirItemData[i];
         }
    }
    return null;
}

function cleanBoirNameForComparison(name) {
    return name.replace(/['\s]/g, "").toLowerCase();
}

function displayChannelSettings() {

    if (userAccessLevel >= USER_ACCESS_LEVEL_MOD) {
        $('#sidebarItemSettings').removeClass('hidden');
    }

    if (userAccessLevel >= USER_ACCESS_LEVEL_OWNER) {
        $('#settingsPartModalBtn').removeClass('hidden');
    }

    $('#settingsPartConfirmBtn').click(function(e) {

        var $btn = $(this).button('loading');

        $.ajax({
            data: {
                a: "part",
                channel: channel
            },
            dataType: "json",
            url: "/botaction.php",
            success: function(data) {
                if (data.status == "success") {
                    $btn.button('reset');
                    Messenger().post({
                      message: 'Sent leave request! Page will refresh in 3 seconds...',
                      type: 'success'
                    });
                    setTimeout(function() { location.reload();}, 3000);
                } else {
                    var errMsg = (typeof data.status !== 'undefined') ? data.status : data;
                    Messenger().post({
                      message: "Error: " + data.status,
                      type: 'error'
                    });
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                Messenger().post({
                  message: "A connection error occurred.",
                  type: 'error'
                });
            },
            complete: function(jqXHR, textStatus) {
                $btn.button('reset');
            }
        });

    });
}


function displayChannelReqsongs() {

    // if they aren't owner, they don't need to run this code
    if (userAccessLevel < USER_ACCESS_LEVEL_OWNER) {
        return;
    }

    $('#sidebarItemReqsongs').removeClass('hidden');

    setInterval(updateReqsongs, 5000);



}

function updateReqsongs() {
	return true; //temporary disable
    $.ajax({
        data: {
            a: "listReqsong",
            channel: channel
        },
        dataType: "json",
        url: "/botaction.php",
        success: function(data) {
            if (data.status == "success") {
                var reqsongs = data.reqsongs;

                var tbody = $('.js-reqsongs-tbody');
                var rows = "";

                for (var i = 0; i < reqsongs.length; i++) {
                    var rs = reqsongs[i];
                    var row = '<tr class="row-reqsong">';
                    row += '<td><a href="' + rs.url + '">' +  rs.url + '</a></td>';
                    row += '<td>00:00</td>';
                    row += '<td>' + rs.requestedBy + '</td>';
                    row += '<td class="js-reqsongs-deletecol"><span class="js-reqsongs-deletebtn" data-reqsong-id="' + rs.id  + '"><i class="icon-trash"></i><span class="sr-only">Delete</span></span></td>';
                    row += '</tr>';
                    rows += row;
                }
                if (rows == "") {
                    rows = '<tr><td colspan="5" class="text-center">' + EMPTY_TABLE_PLACEHOLDER + '</td></tr>';
                    shouldSortTable = false;
                }
                tbody.html(rows);


            } else {
                var errMsg = (typeof data.status !== 'undefined') ? data.status : data;
                Messenger().post({
                  message: "Error: " + data.status,
                  type: 'error'
                });
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
            Messenger().post({
              message: "Failed to connect to server...",
              type: 'error'
            });
        },
        complete: function(jqXHR, textStatus) {
            //$btn.button('reset');
        }
    });
}






// turns a Moment.js duration object into a totes professional string
function stringifyDuration(duration) {
    var str = "";

    if (duration.asDays() >= 1) {
        var days = Math.floor(duration.asDays());
        str += days + " day" + (days == 1 ?"":"s") + ", ";
        str += duration.hours() + " hour" + (duration.hours() == 1 ?"":"s") + ", ";

    } else if (duration.asHours() >= 1) {
        var hrs = Math.floor(duration.asHours());
        str += hrs + " hour" + (hrs == 1 ?"":"s") + ", ";
    }
    str += duration.minutes() + " minute" + (duration.minutes() == 1 ?"":"s") + ", ";
    str += duration.seconds() + " second" + (duration.seconds() == 1 ?"":"s");

    return str;
}


// turns a Moment.js duration object into a totes professional string, except shorter
function stringifyDurationShort(duration, shouldAddSpaces) {
    var str = "";

    var maybeASpace = shouldAddSpaces ? " " : "";

    if (duration.asHours() >= 1) {
        var hrs = Math.floor(duration.asHours());
        str += hrs + "h" + maybeASpace;
    }
    str += duration.minutes() + "m" + maybeASpace;
    str += duration.seconds() + "s";

    return str;
}


function prettifyAccessLevel(access) {
    if (access == 0) {
        return "All";
    }
    if (access == 1) {
        if (channelData.subsRegsMinusLinks||channelData.subscriberRegulars) {
            return "Subs";
        } else {
            return "Regs";
        }
    }
    if (access == 2) {
        return "Mods";
    }
    if (access == 3) {
        return "Owners";
    }
}

function colorifyAccessLevel(access) {
    if (access == 0) {
        return "#616b72"; //return "#bdc3c7";
    }
    if (access == 1) {
        return "#8e44ad";
    }
    if (access == 2) {
        return "#27ae60";
    }
    if (access == 3) {
        return "#c0392b";
    }
}

function injectEmoticons(html) {
    return html; //TEMP FIX BECAUSE EVERYTHING BROKE
    html = htmlDecode(html);
    for (var i = 0; i < twitchEmotes.length; i++) {
        var emote = twitchEmotes[i];
        if (emote.state == "active") {
            var pattern = null;
            if (emote.regex.search(/\W/g) == -1) {
                var pattern = new RegExp('\\b(' + emote.regex + ')\\b', 'gm');
            } else {
                var pattern = new RegExp('(' + emote.regex + ')', 'gm');
            }
            html = html.replace(pattern, htmlifyEmote(emote));
        }
    }
    return html;
}

function linkifyEverything() {
    console.log('linkified');
    var linkifyThese = $('.should-be-linkified');
    linkifyThese.each(function() {
        $(this).linkify();
        $(this).removeClass('should-be-linkified');
    });
    // linkifyThese.linkify();
    // linkifyThese.removeClass('.should-be-linkified');
}

function htmlDecode(input) {
    return String(input)
        .replace(/&amp;/g, '&')
        .replace(/&quot;/g, '"')
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>');
}

// generates HTML for an emote
function htmlifyEmote(emote) {
    var html = '';
    html += '<img src="' + emote.url;
    html += '" height="' + emote.height;
    html += '" width="' + emote.width;
    html += '" title="$1" class="twitch-emote">';
    return html;
}

// because twitch is too cool for consistency
function getUrlForTwitchVod(channel, id) {
    var prefix = id.substr(0,1);
    if (prefix == "a") {
        return "http://twitch.tv/" + channel + "/b/" + id.substr(1);
    }
    if (prefix == "v") {
        return "http://twitch.tv/" + channel + "/v/" + id.substr(1);
    }
    console.log("ERROR: Failed to parse id: " + id);
    return "http://twitch.tv/" + channel + "/" + id.substr(1);
}

// displays info about the Twitch channel on the overview page
function injectTwitchData() {
    var oldHtml = $('.js-channel-overview').html();
    var html = '';
    html += '<p>Views: ' + Humanize.intComma(channelTwitchData.views) + '</p>';
    html += '<p>Followers: ' + Humanize.intComma(channelTwitchData.followers) + '</p>';
    html += '<p>Joined Twitch on ' + moment(channelTwitchData.created_at).format('LL') + '</p>';
    html += oldHtml;

    $('.js-channel-overview').html(html);
}

$(document).ready(function() {

    // moment.locale('en-custom', {
    //     calendar : {
    //         lastDay : '[Yesterday at] LT',
    //         sameDay : '[Today at] LT',
    //         nextDay : '[Tomorrow at] LT',
    //         lastWeek : '[Last] dddd [at] LT',
    //         nextWeek : 'dddd [at] LT',
    //         sameElse : 'll [at] LT'
    //     }
    // });

    $.ajax({
        dataType: "jsonp",
        jsonp: "callback",
        url: "https://api.twitch.tv/kraken/channels/" + channel,
        success: function(json) {
            console.log("Loaded Twitch channel data");
            channelTwitchData = json;
            injectTwitchData();
        },
        error: function(jqXHR, textStatus, errorThrown) {
            alert("Failed to load Twitch channel data!");
        }
    });

    $.ajax({
        cache: true,
        dataType: "jsonp",
        jsonp: "callback",
        url: "https://api.twitch.tv/kraken/chat/" + channel + "/emoticons",
        success: function(json) {
            console.log("Loaded Twitch emotes");
            twitchEmotes = json.emoticons;
            //var commandsTbody = $('.js-commands-tbody');
            $('.should-be-emotified').each(function () {
                var diss = $(this);
                diss.html(injectEmoticons(diss.html()));
            });
        },
        error: function(jqXHR, textStatus, errorThrown) {
            alert("Failed to load Twitch emotes!");
        }
    });

    checkIfLiveChannel();
    setInterval(checkIfLiveChannel, 30000);

    $(".command").prepend('<span class="command-prefix">' + cleanHtmlText(channelData.commandPrefix) + '</span>');


    var commandPrefixForUrl = channelData.commandPrefix;

    if (commandPrefixForUrl == "+") {
        commandPrefixForUrl = "plus";
    }
    if (commandPrefixForUrl == "#") {
        commandPrefixForUrl = "hash";
    }
    if (commandPrefixForUrl == "&") {
        commandPrefixForUrl = "amp";
    }
    if (commandPrefixForUrl == "?") {
        commandPrefixForUrl = "qmark";
    }

    $(".js-link-commands").each(function() {
        var href = $(this).attr("href");
        $(this).attr("href", href + "/" + encodeURIComponent(commandPrefixForUrl));
    });
})


function checkIfLiveChannel() {
    checkIfLive(channel, handleChannelIsLive);
}

function handleChannelIsLive(json) {
    if (!json) {
        alert("Failed to load Twitch stream data!");
        channelStreamData = false;
    } else {
        channelStreamData = json.streams;
    }
    updateIsLive(json.streams);
}
