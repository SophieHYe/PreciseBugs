<?php
  if($U->userHasPermission("Backend", "User")){
?>
  <!DOCTYPE html>
  <html lang="<?php echo $U->getSetting("site.lang"); ?>" dir="ltr">
    <head>
      <meta charset="utf-8">
      <title><?php echo $U->getLang("admin") ?> - <?php echo $U->getLang("admin.user.edit"); ?></title>
    </head>
    <body>
      <a href="<?php echo $_SERVER['PHP_SELF']; ?>?URL=mainpage"><?php echo $U->getLang("admin.back"); ?></a>
      <?php
        if(isset($_POST["N"])&& !isset($_POST["Submit"])){
          $sql = "SELECT * FROM user WHERE id='".mysqli::real_escape_string($_POST["N"])."';";
          $dbRes = mysqli_query($U->db_link, $sql);
          $userhere = False;
          while($row = mysqli_fetch_array($dbRes, MYSQLI_ASSOC)){
            $user_Type = $row["Type"];
            $user_Blocked = $row["blocked"];
            $userhere = True;
          }
          if($userhere){
            if($_SESSION["User_ID"] !== md5($_POST["N"])){
    ?>
              <form action="<?php echo $_SERVER['PHP_SELF']; ?>?URL=useredit" method="post">
                <?php
                  if($U->userHasPermission("Backend", "User","Permission")){
                ?>
                  <label for="A"><?php echo $U->getLang("admin.user.permissionLevel"); ?></label>
                  <select name="A" type="checkbox" value="<?php echo $user_Type; ?>">
                    <?php
                      // Creates a select list with all permission levels
                      foreach($U->getPermissionName(-1) as $key => $value){
                        echo "<option name='".$key."'".($key==$user_Type?" selected":"").">".$value."</option>";
                      }
                    ?>
                  </select>
                  <br />
                <?php
                  }
                  if($U->userHasPermission("Backend", "User","Block")){
                ?>
                    <label for="G"><?php echo $U->getLang("admin.user.block"); ?></label>
                    <input name="G" type="checkbox" <?php echo $user_Blocked=="1"?"checked":""; ?> /><br />
                <?php
                  }
                  if($U->userHasPermission("Backend", "User","Permission")||$U->userHasPermission("Backend", "User","Block")){
                ?>
                    <input type="submit" name="Submit"/>
                    <input type='hidden' name='N' value='<?php echo $_POST["N"]; ?>' />
                <?php
                  }
                ?>
              </form>
              <?php
                if($U->userHasPermission("Backend", "User","Search")){
              ?>
                <!-- Link to user search page (Opens in a pop-up) --> 
                <a href="javascript:window.open('index.php?URL=usersearch&Id=<?php echo $_POST["N"]; ?>', 'Search user', 'width=500,height=500,status=no,titlebar=no,location=no,toolbar=no,left=300');"><?php echo $U->getLang("admin.user.moreInformation"); ?></a>
              <?php
                }
              ?>
    <?php            
            }else{
              echo "<br />".$U->getLang("admin.user.yourself");
            }
          }else{
            echo "<br />".str_replace("%a", $_POST["N"], $U->getLang("admin.user.notFound"));
          }
        }elseif(isset($_POST["Submit"])){
          if(isset($_POST["A"])&&$U->userHasPermission("Backend", "User","Permission")){
            $permissionLevel = $_POST["A"];
          }elseif(!$U->userHasPermission("Backend", "User","Permission")){
            $permissionLevel = false;
          }
          if(isset($_POST["G"])&&$U->userHasPermission("Backend", "User","Block")){
            $b = 1;
          }elseif($U->userHasPermission("Backend", "User","Block")){
            $b = 0;
          }else{
            $b = false;
          }
          if($_SESSION["User_ID"] !== md5($_POST["N"])){
            $sql = "UPDATE User ".($permissionLevel === false?"":"SET Type='".$permissionLevel."'".($b==false?"":", ")).($b==false?"":"blocked ='".$b."' ")."WHERE Id='".mysqli::real_escape_string($_POST["N"])."';";
            $dbRes = mysqli_query($U->db_link, $sql);
            echo "<br />".$U->getLang("admin.user.changed");
          }else{
            // When the user tries to edit himself
            echo "<br />".$U->getLang("admin.user.yourself");
          }
        }else{
          $sql = "SELECT * FROM User;";
          $dbRes = mysqli_query($U->db_link, $sql);
          // Allow only values in the range from the lowest Id to the highest id
          $highestId = 0;
          // BUG: #54 Lowest ID don't work if over 10000000000000000000000000 accounts are created
          $lowestId = 10000000000000000000000000;
          while($row = mysqli_fetch_array($dbRes, MYSQLI_ASSOC)){
            if($row["Id"] > $highestId){
              $highestId = $row["Id"];
            }
            if($row["Id"] < $lowestId){
              $lowestId = $row["Id"];
            }
          }
          $text = <<<'HEREDOC'
          <form action="$_SERVER["PHP_SELF"]?URL=useredit" method="post">
            <label for="N">ID:</label><input name="N" type="number" min="%b" max="%a" />
            <input type="submit" />
          </form>
          HEREDOC;
          $text = str_replace('$_SERVER["PHP_SELF"]', $_SERVER['PHP_SELF'], $text);
          $text = str_replace('%a', $highestId, $text);
          $text = str_replace('%b', $lowestId, $text);
          echo $text;
          if($U->userHasPermission("Backend", "User","Search")){
      ?>
            <!-- Link to user search page (Opens in a pop-up) -->
            <a href="javascript:window.open('index.php?URL=usersearch', 'Search user', 'width=500,height=500,status=no,titlebar=no,location=no,toolbar=no,left=300');"><?php echo $U->getLang("admin.user.search"); ?></a>
      <?php
          }
        }
      ?>
    </body>
  </html>
<?php
  }else{
?>
  <!DOCTYPE html>
  <html lang="<?php echo $U->getSetting("site.lang"); ?>" dir="ltr">
    <head>
      <meta charset="utf-8">
      <title><?php echo $U->getLang("admin") ?> - <?php echo $U->getLang("admin.user"); ?></title>
    </head>
    <body>
      <a href="<?php echo $_SERVER['PHP_SELF']; ?>?URL=mainpage"><?php echo $U->getLang("admin.back"); ?></a>
      <p><?php echo $U->getLang("rights.error"); ?></p>
    </body>
  </html>
<?php
  }
?>
