C.P.Sub 公告系統
=======
<h4>前言</h4>

PHP 公告系統，用 CSV 格式建構出來的小型 PHP 程式。

基本上就是強化舊版的功能，改寫一下內部架構，並套用了 Bootstrap ，所以自行更換樣式！

如果使用上有遇到什麼問題，或是有程式上的建議、架構上的建議、甚至是功能上的建議，都歡迎來信告知。

當然，最重要的是要記得 Bug 回報～

=======

<h4>安裝方式</h4>

<h3>1. Server 健康檢查</h3>
<ul>
  <li>環境：PHP 5.3 以上 (建議)</li>
  <li>PHP.ini 設定：
    <ul>
      <ol>short_open_tag = on;</ol>
      <ol>file_uploads = on;</ol>
      <ol>allow_url_fopen = on;</ol>
    </ul>
  </li>
</ul>
<h3>2. 上傳至 FTP 目錄</h3>
<h3>3. 修改資料夾/目錄權限，改成 777</h3>
<ul> 
 <li>cpsub/upload/</li>
 <li>cpsub/db/article.txt</li>
 <li>cpsub/db/settings.txt</li>
</ul>
<h3>4. 修改帳號密碼</h3>
<ul>
 <li>開啟 cpsub/config/config.php</li>
 <li>修改陣列數值</li>
 <code>
          $add_user	= array("username" => "admin", // 帳號
					"password" => "admin", // 密碼
					"nickname" => "管理員" // 管理員
					); 
 </code>
</ul>
<h3>5. 大功告成，開啟瀏覽器觀看！</h3>

=======

<h4>版本更新</h4>

<b>2016-11-30: v5.1</b><br>
<li>1.加入最後瀏覽時間，以阻擋過度瀏覽而導致文章消失的問題</li>
<li>2.修改一些小 Bug</li>
<li>3.加入 IP Checker ，但好像沒什麼用</li>



=======

This is a PHP bulletin project that made with CSV file system (which is not using SQL as the database)

If you have any question or suggestion about this project, please contact with me by using E-mail, Facebook message, or Twitter.

<h4>Installation</h4>

<h3>1. Server Configuration</h3>
<ul>
  <li>Environment：PHP 5.3 or higher</li>
  <li>PHP.ini config：
    <ul>
      <ol>short_open_tag = on;</ol>
      <ol>file_uploads = on;</ol>
      <ol>allow_url_fopen = on;</ol>
    </ul>
  </li>
</ul>
<h3>2. Upload the whole "cpsub" folder to your server</h3>
<h3>3. Update the folder/files's permissions to 777</h3>
<ul> 
 <li>cpsub/upload/</li>
 <li>cpsub/db/article.txt</li>
 <li>cpsub/db/settings.txt</li>
</ul>
<h3>4. Update the user name and user password</h3>
<ul>
 <li>Open cpsub/config/config.php</li>
 <li>Edit the array values</li>
 <code>
          $add_user	= array("username" => "admin", // account
					"password" => "admin", // password
					"nickname" => "Admin" // Nickname
					); 
 </code>
</ul>
<h3>5. Finish!!</h3>


=======

<h4>Relsease Logs</h4>

<b>2017-4-6: v5.21</b><br>
<li>1. Filter Update</li>

<b>2017-3-27: v5.2</b><br>
<li>1. XSS issues resolved</li>

<b>2016-11-30: v5.1</b><br>
<li>1. Added a last-time-viewed date column to prevent the data format loses</li>
<li>2. Remove some bugs</li>
<li>2. Added an IP Checker</li>



=======
=======

Demo 網址：http://cooltey.org/cpsub/

目前程式版本 Current Version：v5.21

作者 Author：Cooltey Feng

E-mail：coolteygame@gmail.com

Facebook：http://www.facebook.com/cooltey

Twitter：http://twitter.com/cooltey

網站 My Website：http://www.cooltey.org

若有問題，歡迎交流！

Contact me if you have any question!

=======
