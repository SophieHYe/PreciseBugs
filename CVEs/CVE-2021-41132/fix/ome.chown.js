

  // Copyright (C) 2020 University of Dundee & Open Microscopy Environment.
  // All rights reserved.

  // This program is free software: you can redistribute it and/or modify
  // it under the terms of the GNU Affero General Public License as
  // published by the Free Software Foundation, either version 3 of the
  // License, or (at your option) any later version.

  // This program is distributed in the hope that it will be useful,
  // but WITHOUT ANY WARRANTY; without even the implied warranty of
  // MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  // GNU Affero General Public License for more details.

  // You should have received a copy of the GNU Affero General Public License
  // along with this program.  If not, see <http://www.gnu.org/licenses/>.


$(function() {

    if (typeof window.OME === "undefined") { window.OME={}; }

    var $chownform = $("#chown-form");
    var datatree;
    // Ojbects selected in jsTree
    var selobjs = [];
    var dataOwners = [];
    var loadingExps = false;
    var exps = [];
    var dryrunInProgress = false;
    var dryrunJobId;
    var $okbtn;
    var dryrunTimout;

    // template literals not supported on IE 11 (1.3% global browser share)
    var templateText = `
        <!-- Hidden fields for objects. e.g. name='Image' value='1,2,3' -->
        <% _.each(selobjs, function(obj, idx) { %>
            <input name='<%- obj.split("=")[0] %>' value='<%- obj.split("=")[1] %>' hidden/>
        <% }) %>

        <!-- List target new owners -->

        <% if (loadingExps) { %>
            <p>Loading users...</p>
        <% } else if (exps.length > 0) { %>
            <h1>Please choose new owner for the selected data:</h1>

            <% _.each(exps, function(exp, idx) { %>
                <label>
                    <input name='owner_id' type='radio' value='<%= exp['@id'] %>'/>
                    <%- exp.FirstName%> <%- exp.LastName %>
                </label>
                <br/>
            <% }) %>
        <% } else { %>
            <p>No users found</p>
        <% } %>
        <hr/>

        <!-- Show dry-run here -->
        <div class='dryrun'>
        <% if (dryrunInProgress) { %>
            <p style='margin-bottom:0'>
                <img alt='Loading' src='<%= static_url %>../webgateway/img/spinner.gif' />
                Checking which objects will be moved...
                <button title="Cancel dry-run" type="button">Cancel</button>
            </p>
        <% } %>
        </div>
        <hr/>
    `
    var template = _.template(templateText);

    // Update the $chownform with current state
    function render() {

        var html = template({
            selobjs: selobjs,
            exps: exps,
            loadingExps: loadingExps,
            dryrunInProgress: dryrunInProgress,
            static_url: WEBCLIENT.URLS.static_webclient,
        });
        $chownform.html(html);
    }

    // external entry point, called by jsTree right-click menu
    window.OME.handleChown = function() {
        // gid, gname, oid
        $chownform.dialog({"title": "Change Owner",
            height: 450,
            width: 400});
        $chownform.dialog('open');

        // Add selected items to chown form as hidden inputs
        selobjs = OME.get_tree_selection().split("&");  // E.g. Image=1,2&Dataset=3
        datatree = $.jstree.reference('#dataTree');
        dataOwners = _.uniq(datatree.get_selected(true).map(function(s){return s.data.obj.ownerId}));
        $okbtn = $('.chown_confirm_dialog .ui-dialog-buttonset button:nth-child(2)');

        loadUsers();    // this will then call dryRun()

        render();
    };

    function setupEvents() {

        // When user chooses target Owner, do chown dry-run...
        $chownform.on("click", ".dryrun button", function (event) {
            cancelDryRun();
        });
    }

    setupEvents();

    function loadUsers() {
        // Need to find users we can move selected objects to.
        // Object owner must be member of current group.
        var gid = WEBCLIENT.active_group_id;
        var url = WEBCLIENT.URLS.api_base + "m/experimentergroups/" + gid + "/experimenters/";
        loadingExps = true;
        $.getJSON(url, function (data) {
            loadingExps = false;
            // Other group members (ignore current owner if just 1)
            exps = data.data;
            if (dataOwners.length === 1) {
                exps = exps.filter(function (exp) {
                    return exp['@id'] != dataOwners[0];
                });
            }

            // we can do dry-run with any user (result is always the same)
            if (exps.length > 0) {
                dryRun(exps[0]['@id']);
            }

            render();
        });
    }

    // We do a chown 'dryRun' to check for loss of annotations etc.
    function dryRun(ownerId) {
        dryrunInProgress = true;
        var dryRunUrl = WEBCLIENT.URLS.webindex + "chownDryRun/",
            data = { 'owner_id': ownerId };
            selobjs.forEach(o => {
                data[o.split('=')[0]] = o.split("=")[1];
            });

        $.post(dryRunUrl, data, function (jobId) {
            dryrunJobId = jobId;
            // keep polling for dry-run completion...
            var getDryRun = function () {
                var url = WEBCLIENT.URLS.webindex + "activities_json/",
                    data = { 'jobId': jobId };
                $.get(url, data, function (dryRunData) {
                    if (dryRunData.finished) {
                        dryrunJobId = undefined;
                        // Handle chown errors by showing message...
                        if (dryRunData.error) {
                            var errMsg = dryRunData.error;
                            // More assertive error message
                            errMsg = errMsg.replace("may not move", "Cannot move");
                            var errHtml = "<img style='vertical-align: middle; position:relative; top:-3px' src='" +
                                static_url + "../webgateway/img/failed.png'> ";
                            // In messages, replace Image[123] with link to image
                            var getLinkHtml = function (imageId) {
                                var id = imageId.replace("Image[", "").replace("]", "");
                                return "<a href='" + webindex_url + "?show=image-" + id + "'>" + imageId + "</a>";
                            };
                            errHtml += errMsg.replace(/Image\[([0-9]*)\]/g, getLinkHtml);
                            $('.dryrun', $chownform).html(errHtml);
                            $okbtn.hide();
                            return;
                        }
                        dryrunInProgress = false;

                        // formatDryRun is in ome.chgrp.js
                        var showParents = true;
                        let html = OME.formatDryRun(dryRunData, showParents);
                        html = "<p><b style='font-weight: bold'>Change owner of:</b> " + html + '</p>';
                        // replace spinner and 'Cancel' button...
                        $('.dryrun', $chownform).html(html);
                    } else {
                        // try again...
                        dryrunTimout = setTimeout(getDryRun, 2000);
                    }
                });
            };
            getDryRun();
        });
    };

    function cancelDryRun() {
        if (dryrunTimout) {
            clearTimeout(dryrunTimout);
        }
        if (!dryrunJobId) return;

        var dryRunUrl = WEBCLIENT.URLS.webindex + "activities_json/";
        $.ajax({
            url: dryRunUrl,
            type: 'DELETE',
            data: JSON.stringify({jobId: dryrunJobId}),
            success: function (result) {
                // Do something with the result
            }
        });
    }

    // set-up the dialog
    $chownform.dialog({
        dialogClass: 'chown_confirm_dialog',
        autoOpen: false,
        resizable: true,
        height: 350,
        width:520,
        modal: true,
        buttons: {
            "Cancel": function() {
                cancelDryRun();
                $( this ).dialog( "close" );
            },
            "OK": function () {
                $chownform.submit();
            },
        },
        close: function (event, ui) {
            cancelDryRun();
        }
    });

    // handle chown 
    $chownform.ajaxForm({
        beforeSubmit: function(data, $form){
            var owner_data = data.filter(d => d.name === 'owner_id');
            // Don't submit if we haven't populated the form with users etc.
            if (owner_data.length === 0) {
                OME.alert_dialog("Please choose target user.");
                return false;
            }
        },
        success: function(data) {
            // If we're viewing 'All Members' we don't need to change anything in the tree
            if (WEBCLIENT.active_user.id != -1) {
                // Otherwise, we need to remove selected nodes
                var inst = $.jstree.reference('#dataTree');
                inst.get_selected(true).forEach(function(node){
                    inst.delete_node(node);
                });
            }
            $chownform.dialog( "close" );
            OME.showActivities();
        }
    });

});
