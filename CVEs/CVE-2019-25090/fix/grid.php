<?php
$dataurl = "ajax.php?module=arimanager&command=grid";
?>
<div id="toolbar-all">
    <a class="btn btn-default" href="?display=arimanager&view=form">
        <i class="fa fa-plus"></i> <span><?php echo _('Add User')?></span>
    </a>
</div>
 <table id="ariusergrid" data-escape="true" data-url="<?php echo $dataurl?>" data-cache="false" data-toolbar="#toolbar-all" data-maintain-selected="true" data-show-columns="true" data-show-toggle="true" data-toggle="table" data-pagination="true" data-search="true" class="table table-striped">
    <thead>
            <tr>
            <th data-field="name"><?php echo _("Username")?></th>
            <th data-field="read_only" data-formatter="roFormatter"><?php echo _("Read Only")?></th>
            <th data-field="id" data-formatter="linkFormatter"><?php echo _("Actions")?></th>
        </tr>
    </thead>
</table>
<script type="text/javascript">
  function roFormatter(value){
    var html = '';
    if (value == "1"){
      html += _("Yes");
    }else{
      html += _("No");
    }
    return html;
  }
  function linkFormatter(value){
    var html = '<a href="?display=arimanager&view=form&user='+value+'"><i class="fa fa-pencil"></i></a>';
    html += '&nbsp;<a href="?display=arimanager&action=delete&user='+value+'" class="delAction"><i class="fa fa-trash"></i></a>';
    return html;
  }
</script>
