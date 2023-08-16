<?php include '../../../wp-load.php'; ?>
<!DOCTYPE html>
<!--

    DIASPORA* Advanced Sharer
       by Bartimeo | bartimeo@joindiaspora.com
       based on Diaspora* Sharing Service by Jason Robinson | jaywink@iliketoast.net
       
       This code is completely free. Don't hesitate to copy, modify or distribute it.

-->
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title><?php _e( 'Share on Diaspora', 'share-on-diaspora' ); ?></title>
<style>
body{font-family:Helvetica, Helvetica, Arial, sans-serif;font-size:15px;max-height:100%;margin:0;padding:0 0 2em; background: #1b4053; color: #fff}
a{color:#88FFFF;text-decoration:none;-webkit-transition:opacity .1s ease;-moz-transition:opacity .1s ease;-o-transition:opacity .1s ease;transition:opacity .1s ease;cursor:pointer}
a:hover{opacity:.85;text-decoration:underline}
span.clear{clear:both;display:block}
header{background-color:#222;-webkit-box-shadow:0 0 2px #777;-moz-box-shadow:0 0 2px #777;box-shadow:0 0 2px #777;filter:progid:DXImageTransform.Microsoft.gradient(GradientType=0,startColorstr=rgb(35,30,30),endColorstr=#231e1e);-ms-filter:"progid:DXImageTransform.Microsoft.gradient (GradientType=0, startColorstr=rgba(35, 30, 30, 0.95), endColorstr=#231e1e)";background-image:0 0 100%;color:#dadada;padding:8px 10px}
header h2{float:left;font-size:inherit;font-weight:inherit;border-right:1px solid #aaa;margin:0;padding:.6em 20px .6em 10px}
header #sharedet{text-align:center;width:auto;margin:0}
header #sharedet div{display:block;height:1.2em;max-height:1.2em;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;-o-text-overflow:ellipsis;color:#fafafa;padding:0 10px 0 20px}
header #sharedet #sharetitle{font-weight:700}
section{width:auto;float:left;margin:10px 0 0;padding:0 20px}
section h3{font-size:inherit;font-weight:inherit;color:#88FFFF}
section#podlist{border-right:1px solid #ccc;min-width:200px}
section#podlist a{display:block;border:1px solid #eee;text-decoration:none;color:#444;-webkit-transition:border .2s ease, color .2s ease;-moz-transition:border .2s ease, color .2s ease;-o-transition:border .2s ease, color .2s ease;transition:border .2s ease, color .2s ease;margin:0 0 6px;padding:3px 6px}
section#podlist a.lastpod{color:#fff;border-color:#bbcad0}
section#podlist a:hover{border:1px solid #888;color:#000}
#podurl{width:200px;font-size:15px;border:1px solid silver;-webkit-transition:.1s border-color ease;-moz-transition:1s border-color ease;-o-transition:.1s border-color ease;transition:1s border-color ease;margin:0;padding:3px 6px;background: #82A6B6;color:white;} 
#podurlsm{margin:6px 0 0;padding:4px 10px}
#sharetitle{display:block;width:100%;height:1.2em;text-align:center;border:1px solid silver;-webkit-transition:.1s border-color ease;-moz-transition:1s border-color ease;-o-transition:.1s border-color ease;transition:1s border-color ease;background: #82A6B6;color:white;resize: none;}
#sharepodlist{background:#82A6B6;width:268px;padding:5px;font-size:16px;line-height:1;border:0;border-radius:0;height:34px;transition:1s border-color ease;color:#fff}
#podurl:focus, #sharetitle:focus, #sharepodlist:focus{border:1px solid #ff0000}
:focus{outline:none}
input.error,input#podurl.error{border:1px solid #a00}
span.check{display:inline-block;background:#f5f5f5;margin:10px 6px 3px;border:1px solid #eee;padding-left:3px}
span.check label{display:inline-block;font-size:14px;-webkit-transition:color .2s ease;-moz-transition:color .2s ease;-o-transition:color .2s ease;transition:color .2s ease;padding:5px 6px 4px 0}
div.bot_opt{position:fixed;bottom:0;height:1.6em;border-top:1px solid #eee;left:0;right:0;width:auto;background:#82A6B6}div.bot_opt label{display:inline-block;width:auto;left:0;right:0;position:absolute;padding-left:1.6em;top:0;bottom:0;height:auto;-webkit-transition:color .2s ease;-moz-transition:color .2s ease;-o-transition:color .2s ease;transition:color .2s ease;margin:0}.advanced{padding:10px 0 0 20px}#contopt{apadding-top:6px;line-height:1.2em}#contopt input[type=checkbox]{margin-left:0}a.openao,.opttit{font-size:13px}section#podlist a.hidepod,.hideopt{display:none}span.check input:checked + label,div.bot_opt :checked + label{color:#060}
</style>

<script type="text/javascript">
function extras(a) {
    var b = document.getElementById("remember").checked;
    var c = document.getElementById("markdown").checked;
    var d = document.getElementById("shorten").checked;
    var e = document.getElementById("norem").checked;
    if (b) {
        localStorage["remember"] = "true";
        if (e) {
            localStorage["lastPod"] = a
        }
    } else {
        localStorage["remember"] = "false"
    }
    if (d) {
        var f = new XMLHttpRequest;
        f.open("GET", "http://api.bitly.com/v3/shorten?login=bartimeo&apiKey=R_5fe8386a052e3f3d6ece604eab0c59db&format=txt&domain=j.mp&longUrl=" + url);
        f.onreadystatechange = function() {
            if (f.readyState == 4) {
                if (f.status == 200) {
                    console.log(f.responseText);
                    url = f.responseText
                } else {
                    console.log("Problem?", f)
                }
            }
        };
        f.send()
    }
    if (!e) {
        recuerda(a)
    } else {
        localStorage["forget"] = "true"
    }
    return true
}

function forget(a) {
    localStorage.removeItem("lastPod");
    localStorage.removeItem("lastPod2");
    localStorage.removeItem("lastPod3");
    var b = document.getElementsByClassName("hidepod");
    for (i = 0; i < b.length; i++) {
        b[i].className = "dpod"
    }
    var c = document.getElementsByClassName("lastpod");
    for (j = 0; j < c.length; j++) {
        c[j].parentNode.removeChild(c[j])
    }
    if ((document.getElementsByClassName("hidepod") || document.getElementsByClassName("lastpod")) && !a) {
        forget("again")
    }
}

function share(a) {
    title = document.getElementById("sharetitle").value;
    if (a !== "") {
        extras(a);
        var b = "http://" + a + "/bookmarklet?url=" + encodeURIComponent(url) + "&title=" + encodeURIComponent(title);
        if (notes !== "") {
            b += "&notes=" + encodeURIComponent(notes)
        }
        b += "&jump=doclose";
        location.href = b
    } else {
        document.getElementById("podurl").className = "error"
    }
}

function recuerda(a) {
    if (localStorage["lastPod"] && localStorage["lastPod"] !== a) {
        if (localStorage["lastPod2"] && localStorage["lastPod2"] !== a) {
            localStorage["lastPod3"] = localStorage["lastPod2"]
        }
        localStorage["lastPod2"] = localStorage["lastPod"]
    }
    localStorage["lastPod"] = a;
    return true
}

function updlinks() {
    var a = document.getElementsByClassName("dpod");
    for (i = 0; i < a.length; i++) {
        var b = "http://" + a[i].title + "/bookmarklet?url=" + encodeURIComponent(url) + "&title=" + encodeURIComponent(title);
        if (notes !== "") {
            b += "&notes=" + encodeURIComponent(notes)
        }
        b += "&jump=doclose";
        a[i].href = b;
        a[i].onclick = function() {
            extras(this.title)
        }
    }
}

function crealinks() {
    var a = document.getElementsByClassName("dpod");
    if (localStorage["forget"] === "true") {
        document.getElementById("norem").checked = "checked"
    } else {
        if (localStorage["lastPod"]) {
            var b = localStorage["lastPod"];
            var c = document.createElement("a");
            c.className = "dpod lastpod";
            c.title = b;
            if (document.getElementsByTagName("body")[0].innerText) {
                c.innerText = b
            } else {
                c.textContent = b
            }
            for (i = 0; i < a.length; i++) {
                if (a[i].title === b) {
                    a[i].className = "dpod hidepod"
                }
            }
            document.getElementById("podlist").insertBefore(c, document.getElementById("first"));
            if (localStorage["lastPod2"]) {
                var b = localStorage["lastPod2"];
                var c = document.createElement("a");
                c.className = "dpod lastpod";
                c.title = b;
                if (document.getElementsByTagName("body")[0].innerText) {
                    c.innerText = b
                } else {
                    c.textContent = b
                }
                for (i = 0; i < a.length; i++) {
                    if (a[i].title === b) {
                        a[i].className = "dpod hidepod"
                    }
                }
                document.getElementById("podlist").insertBefore(c, document.getElementById("first"));
                if (localStorage["lastPod3"]) {
                    var b = localStorage["lastPod3"];
                    var c = document.createElement("a");
                    c.className = "dpod lastpod";
                    c.title = b;
                    if (document.getElementsByTagName("body")[0].innerText) {
                        c.innerText = b
                    } else {
                        c.textContent = b
                    }
                    for (i = 0; i < a.length; i++) {
                        if (a[i].title === b) {
                            a[i].className = "dpod hidepod"
                        }
                    }
                    document.getElementById("podlist").insertBefore(c, document.getElementById("first"))
                }
            }
        }
    }
    updlinks();
    document.getElementById("openao").onclick = function() {
        document.getElementById("contopt").className = "showopt";
        document.getElementById("openao").style.display = "none"
    };
    document.getElementById("delete").onclick = function() {
        forget()
    };
    document.getElementById("markdown").onchange = function() {
        if (document.getElementById("markdown").checked) {
            title = "[" + title + "](" + url + ")";
            url = " "
        } else {
            title = oldtit;
            url = oldurl
        }
        updlinks()
    };
    document.getElementById("shorten").onchange = function() {
        if (document.getElementById("shorten").checked) {
            var a = new XMLHttpRequest;
            a.open("GET", "http://api.bitly.com/v3/shorten?login=bartimeo&apiKey=R_5fe8386a052e3f3d6ece604eab0c59db&format=txt&domain=j.mp&longUrl=" + url);
            a.onreadystatechange = function() {
                if (a.readyState == 4) {
                    if (a.status == 200) {
                        console.log(a.responseText);
                        shurl = decodeURIComponent(encodeURIComponent(a.responseText).replace("%0A", ""));
                        if (document.getElementById("markdown").checked) {
                            title = "[" + oldtitle + "](" + shurl + ")";
                            url = " "
                        } else {
                            url = shurl
                        }
                        updlinks()
                    } else {
                        console.log("Problem?", a)
                    }
                }
            };
            a.send()
        } else {
            if (document.getElementById("markdown").checked) {
                title = "[" + oldtitle + "](" + oldurl + ")";
                url = " "
            } else {
                title = oldtit;
                url = oldurl
            }
            updlinks()
        }
    }
}

function redirect() {
    if (title === "" && url === "") {
        document.getElementsByTagName("body")[0].innerHTML = "";
        location.href = "about"
    } else {
        if (localStorage["remember"] && localStorage["remember"] === "true" && localStorage["lastPod"] && redir !== "false") {
            document.getElementsByTagName('body')[0].innerHTML = "Sharing <b>" + title + "</b> (" + url + ") to " + localStorage["lastPod"];
            var a = "http://" + localStorage["lastPod"] + "/bookmarklet?url=" + encodeURIComponent(url) + "&title=" + encodeURIComponent(title);
            if (notes !== "") {
                a += "&notes=" + encodeURIComponent(notes)
            }
            a += "&jump=doclose";
            location.href = a;
            return true
        } else {
            document.getElementById("sharetitle").value = title;
            document.getElementById("shareurl").innerHTML = url;
            crealinks();
            return false
        }
    }
}

function par(a) {
    var b = "[\\?&]" + a + "=([^&]*)";
    var c = new RegExp(b);
    var d = window.location.href;
    var e = c.exec(d);
    if (e == null) return "";
    else return e[1]
}
var title = decodeURIComponent(par("title"));
var url = decodeURIComponent(par("url"));
var notes = decodeURIComponent(par("notes"));
var redir = decodeURIComponent(par("redirect"));
var oldtit = title;
var oldurl = url;
var oldnot = notes;
window.onload = function() {
    redirect()
}
</script>
</head>
<body>
<header>
<h2><?php _e( 'Sharing', 'share-on-diaspora' ); ?></h2>
<div id=sharedet>
    <div><textarea id=sharetitle></textarea></div>
    <div id=shareurl></div>
</div>
</header>
<section id=podlist><h3><?php _e( 'Choose your Diaspora* pod', 'share-on-diaspora' ); ?></h3>
<select id=sharepodlist onchange="share(this.options[this.selectedIndex].value)">
<option>- <?php _e( 'Select from the list', 'share-on-diaspora' ); ?> -</option>
<?php
$options_array = get_option( 'share-on-diaspora-settings' );
$podlist = array_keys( $options_array['podlist'] );
$podlist_all_array = get_option( 'dpu-podlist' );
if ( $podlist_all_array && ! empty( $podlist_all_array ) ) {
	//DPU plugin active, podlist not empty
	$podlist_all = $podlist_all_array;
}
elseif ( ! empty( $options_array['podlist-all'] ) ) {
	//DPU not active, but we can use $options_array['podlist-all']
	$podlist_all = $options_array['podlist-all'];
}
else {
	$podlist_all = array('example.com');
}
$podlist_all_keys = array_keys( $podlist_all );
foreach ( $podlist as &$i ) {
	print '<option  value="' . $i .'" class=dpod title="'.$i.'">'.$i.'</option>';
}
?>
</select>
<h3><?php _e( 'or choose from history', 'share-on-diaspora' ); ?></h3>
</section>
<section id=podinput><h3><?php _e( 'or introduce your pod URL', 'share-on-diaspora' ); ?></h3><form onsubmit="var url = document.getElementById('podurl').value; url = url.replace(/.*?:\/\//g, ''); share(url); return false"><input id=podurl placeholder="<?php echo(sprintf( __( 'Example: %s', 'share-on-diaspora' ), $podlist_all[array_rand( $podlist_all_keys )] )); ?>" type="text" list="datalist1" autocomplete="on"/>
<datalist id="datalist1">
<?php
foreach ( $podlist_all as $value ) {
	print '<option>' . $value . '</option>';
}
?>
</datalist><br><input type=submit id=podurlsm value="<?php _e( 'Share', 'share-on-diaspora' ); ?>"></form><hr>
<small>by <a href="http://wordpress.org/plugins/share-on-diaspora/">Share on Diaspora</a> plugin for <a href="http://wordpress.org/">WordPress</a>.</small></section><span class=clear></span><div class=bot_opt><input type=checkbox id=remember><label for=remember><?php _e( "Remember my choice, don't ask again", 'share-on-diaspora' ); ?></label></div><div class=advanced><a class=openao id=openao><?php _e( 'Advanced options', 'share-on-diaspora' ); ?></a><div class=hideopt id=contopt><span class=opttit><?php _e( 'Advanced options', 'share-on-diaspora' ); ?></span><br><input type=checkbox id=markdown><label for=markdown><?php _e( 'Use Markdown syntax for link', 'share-on-diaspora' ); ?></label><br><input type=checkbox id=shorten><label for=shorten><?php _e( 'Shorten URL (j.mp)', 'share-on-diaspora' ); ?></label><br><input type=checkbox id=norem><label for=norem><?php _e( 'Never remember my last 3 pods', 'share-on-diaspora' ); ?></label><br><a class=delete id=delete><?php _e( 'Forget my last 3 pods', 'share-on-diaspora' ); ?></a></div></div>
</body>
</html>
