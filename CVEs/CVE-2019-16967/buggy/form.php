<?php

if (isset($_REQUEST['managerdisplay'])){
  $managerdisplay = $_REQUEST['managerdisplay'];
  $subhead = '<h2>'._("Manager").' '.$managerdisplay.'</h2>';
  $delURL = '?display=manager&amp;managerdisplay='.$managerdisplay.'&amp;action=delete';
  //get details for this manager
  $thisManager = manager_get($managerdisplay);
  //create variables
  extract(manager_format_out($thisManager));
} else {
  $subhead = '<h2>'._("Add Manager").'</h2>';
  $delURL = '';
  $rall = 1;
  $wall = 1;
  $name = '';
  $secret = md5(openssl_random_pseudo_bytes(16));
  $deny = '0.0.0.0/0.0.0.0';
  $permit = '127.0.0.1/255.255.255.0';
}
$permtypes = array(
  'system' => _("system"),
  'call' => _("call"),
  'log' => _("log"),
  'verbose' => _("verbose"),
  'command' => _("command"),
  'agent' => _("agent"),
  'user' => _("user"),
  'config' => _("config"),
  'dtmf' => _("dtmf"),
  'reporting' => _("reporting"),
  'cdr' => _("cdr"),
  'dialplan' => _("dialplan"),
  'originate' => _("originate"),

);
if(isset($rall)){
  foreach ($permtypes as $key => $value) {
    $genkey = 'r'.$key;
    $$genkey = true;
  }
}
if(isset($wall)){
  foreach ($permtypes as $key => $value) {
    $genkey = 'w'.$key;
    $$genkey = true;
  }
}
?>
<?php echo $subhead; ?>
<form class="fpbx-submit" name="editMan" action="" onsubmit="return checkConf();" method="post" autocomplete="off" data-fpbx-delete="<?php echo $delURL?>" role="form">
  <input type="hidden" name="display" value="manager">
	<input type="hidden" name="action" value="<?php echo (isset($managerdisplay) ? 'edit' : 'add') ?>">
  <input type="hidden" name="view" value="form">
    <ul class="nav nav-tabs" role="tablist">
        <li data-name="managerset" class="change-tab active"><a href="#managerset" aria-controls="managerset" role="tab" data-toggle="tab"><?php echo _("General")?></a></li>
        <li data-name="managerperm" class="change-tab"><a href="#managerperm" aria-controls="managerperm" role="tab" data-toggle="tab"><?php echo _("Permissions")?></a></li>
    </ul>
    <div class="tab-content display">
        <div id="managerset" class="tab-pane active">
            <!--Manager name-->
            <div class="element-container">
              <div class="row">
                <div class="col-md-12">
                  <div class="row">
                    <div class="form-group">
                      <div class="col-md-3">
                        <label class="control-label" for="name"><?php echo _("Manager name") ?></label>
                        <i class="fa fa-question-circle fpbx-help-icon" data-for="name"></i>
                      </div>
                      <div class="col-md-9">
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo isset($name)?$name:''?>">
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="row">
                <div class="col-md-12">
                  <span id="name-help" class="help-block fpbx-help-block"><?php echo _("Name of the manager without spaces.")?></span>
                </div>
              </div>
            </div>
            <!--END Manager name-->
            <!--Manager secret-->
            <div class="element-container">
              <div class="row">
                <div class="col-md-12">
                  <div class="row">
                    <div class="form-group">
                      <div class="col-md-3">
                        <label class="control-label" for="secret"><?php echo _("Manager secret") ?></label>
                        <i class="fa fa-question-circle fpbx-help-icon" data-for="secret"></i>
                      </div>
                      <div class="col-md-9">
                        <div class="input-group">
                          <input type="password" class="form-control password-meter" id="secret" name="secret" value="<?php echo isset($secret)?$secret:''?>">
                          <span class="input-group-addon toggle-password" id="pwtoggle" data-id="secret"><i class="fa fa-eye"></i></a></span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="row">
                <div class="col-md-12">
                  <span id="secret-help" class="help-block fpbx-help-block"><?php echo _("Password for the manager.")?></span>
                </div>
              </div>
            </div>
            <!--END Manager secret-->
            <!--Deny-->
            <div class="element-container">
              <div class="row">
                <div class="col-md-12">
                  <div class="row">
                    <div class="form-group">
                      <div class="col-md-3">
                        <label class="control-label" for="deny"><?php echo _("Deny") ?></label>
                        <i class="fa fa-question-circle fpbx-help-icon" data-for="deny"></i>
                      </div>
                      <div class="col-md-9">
                        <input type="text" class="form-control" id="deny" name="deny" value="<?php echo isset($deny)?$deny:''?>">
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="row">
                <div class="col-md-12">
                  <span id="deny-help" class="help-block fpbx-help-block"><?php echo _("If you want to deny many hosts or networks, use & char as separator.<br/><br/>Example: 192.168.1.0/255.255.255.0&10.0.0.0/255.0.0.0")?></span>
                </div>
              </div>
            </div>
            <!--END Deny-->
            <!--Permit-->
            <div class="element-container">
              <div class="row">
                <div class="col-md-12">
                  <div class="row">
                    <div class="form-group">
                      <div class="col-md-3">
                        <label class="control-label" for="permit"><?php echo _("Permit") ?></label>
                        <i class="fa fa-question-circle fpbx-help-icon" data-for="permit"></i>
                      </div>
                      <div class="col-md-9">
                        <input type="text" class="form-control" id="permit" name="permit" value="<?php echo isset($permit)?$permit:''?>">
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="row">
                <div class="col-md-12">
                  <span id="permit-help" class="help-block fpbx-help-block"><?php echo _("If you want to permit many hosts or networks, use & char as separator. Look at deny example.")?></span>
                </div>
              </div>
            </div>
            <!--END Permit-->
            <!--Write Timeout-->
            <div class="element-container">
              <div class="row">
                <div class="col-md-12">
                  <div class="row">
                    <div class="form-group">
                      <div class="col-md-3">
                        <label class="control-label" for="writetimeout"><?php echo _("Write Timeout") ?></label>
                        <i class="fa fa-question-circle fpbx-help-icon" data-for="writetimeout"></i>
                      </div>
                      <div class="col-md-9">
                        <div class="input-group">
                          <input type="text" class="form-control" id="writetimeout" name="writetimeout" value="<?php echo isset($writetimeout)?$writetimeout:'100'?>">
                          <span class="input-group-addon"><?php echo _("milliseconds") ?></span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="row">
                <div class="col-md-12">
                  <span id="writetimeout-help" class="help-block fpbx-help-block"><?php echo _("Sets the timeout used by Asterisk when writing data to the AMI connection for this user")?></span>
                </div>
              </div>
            </div>
            <!--END Write Timeout-->
        </div>
        <div id="managerperm" class="tab-pane">
          <div class="well well-info">
            <?php echo _("For information on individual permissions please see the Asterisk Manager Documentation")?>
          </div>
            <table class="table table-striped">
              <thead>
                <th><?php echo ("Permission")?> </th>
                <th><?php echo ("Read")?> </th>
                <th><?php echo ("Write")?> </th>
              </thead>
              <tbody>
                <?php
                foreach ($permtypes as $type => $title) {
                  $rtype = 'r'.$type;
                  $wtype = 'w'.$type;
                  echo '<tr>';
                  echo '<td>';
                  echo $title;
                  echo '</td>';
                  echo '<td>';
                  echo '<div class="col-md-9 radioset">';
                  echo '<input type="radio" name="'.$rtype.'" id="'.$rtype.'yes" value="1" '.(isset($$rtype)?"CHECKED":"").'>';
                  echo '<label for="'.$rtype.'yes">'._("Yes").'</label>';
                  echo '<input type="radio" name="'.$rtype.'" id="'.$rtype.'no" '.(isset($$rtype)?"":"CHECKED").'>';
                  echo '<label for="'.$rtype.'no">'. _("No").'</label>';
                  echo '</div>';
                  echo '</td>';
                  echo '<td>';
                  echo '<div class="col-md-9 radioset">';
                  echo '<input type="radio" name="'.$wtype.'" id="'.$wtype.'yes" value="1" '.(isset($$wtype)?"CHECKED":"").'>';
                  echo '<label for="'.$wtype.'yes">'._("Yes").'</label>';
                  echo '<input type="radio" name="'.$wtype.'" id="'.$wtype.'no" '.(isset($$wtype)?"":"CHECKED").'>';
                  echo '<label for="'.$wtype.'no">'. _("No").'</label>';
                  echo '</div>';
                  echo '</td>';
                  echo '</tr>';
                }
                ?>
                <tr>
                  <td><b> <?php echo _("Toggle All")?> </b></td>
                  <td>
                    <div class="col-md-9 radioset">
                    <input type="radio" name="rall" id="rallyes" value="1">
                    <label for="rallyes"><?php echo _("Yes")?></label>
                    <input type="radio" name="rall" id="rallno" value="off">
                    <label for="rallno"><?php echo _("No")?></label>
                    </div>
                  </td>
                  <td>
                    <div class="col-md-9 radioset">
                    <input type="radio" name="wall" id="wallyes" value="1">
                    <label for="wallyes"><?php echo _("Yes")?></label>
                    <input type="radio" name="wall" id="wallno" value="0">
                    <label for="wallno"><?php echo _("No")?></label>
                    </div>
                  </td>
                </tr>
              <tbody>
            </table>
        </div>
    </div>
</form>
<script language="javascript">
<!--

var theForm = document.editMan;

theForm.name.focus();

$(document).ready(function(){
  $("input[name='rall']").change(function(){
    if($(this).val() == 1){
      $("input[name^='r'][type=radio]").each(function(){
        var name = $(this).prop('name');
        if(name == 'reset'){
          return;
        }
        if(name == 'rall'){
          return;
        }
        $('#'+name+'yes').prop('checked',true);
        $('#'+name+'no').prop('checked',false);
      })
    }else{
      $("input[name^='r'][type=radio").each(function(){
        var name = $(this).prop('name');
        if(name == 'reset'){
          return;
        }
        if(name == 'rall'){
          return;
        }
        $('#'+name+'no').prop('checked',true);
        $('#'+name+'yes').prop('checked',false);
      })
    }
  });
  $("input[name='wall']").change(function(){
    if($(this).val() == 1){
      $("input[name^='w']").each(function(){
        var name = $(this).prop('name');
        if(name == 'reset'){
          return;
        }
        if(name == 'wall'){
          return;
        }
        $('#'+name+'yes').prop('checked',true);
        $('#'+name+'no').prop('checked',false);
      })
    }else{
      $("input[name^='w']").each(function(){
        var name = $(this).prop('name');
        if(name == 'reset'){
          return;
        }
        if(name == 'wall'){
          return;
        }
        $('#'+name+'no').prop('checked',true);
        $('#'+name+'yes').prop('checked',false);
      })
    }
  });
});

function checkConf()
{
	var errName = "<?php echo _('The manager name cannot be empty or may not have any space in it.'); ?>";
	var errSecret = "<?php echo _('The manager secret cannot be empty.'); ?>";
	var errDeny = "<?php echo _('The manager deny is not well formatted.'); ?>";
	var errPermit = "<?php echo _('The manager permit is not well formatted.'); ?>";
	var errRead = "<?php echo _('The manager read field is not well formatted.'); ?>";
	var errWrite = "<?php echo _('The manager write field is not well formatted.'); ?>";

	defaultEmptyOK = false;
	if ((theForm.name.value.search(/\s/) >= 0) || (theForm.name.value.length == 0))
		return warnInvalid(theForm.name, errName);
	if (theForm.secret.value.length == 0)
		return warnInvalid(theForm.name, errSecret);
	// Only IP/MASK format are checked
	if (theForm.deny.value.search(/\b(?:\d{1,3}\.){3}\d{1,3}\b\/\b(?:\d{1,3}\.){3}\d{1,3}\b(&\b(?:\d{1,3}\.){3}\d{1,3}\b\/\b(?:\d{1,3}\.){3}\d{1,3}\b)*$/))
		return warnInvalid(theForm.deny, errDeny);
	if (theForm.permit.value.search(/\b(?:\d{1,3}\.){3}\d{1,3}\b\/\b(?:\d{1,3}\.){3}\d{1,3}\b(&\b(?:\d{1,3}\.){3}\d{1,3}\b\/\b(?:\d{1,3}\.){3}\d{1,3}\b)*$/))
		return warnInvalid(theForm.permit, errPermit);
	return true;
}

//-->
</script>
