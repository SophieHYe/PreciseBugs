<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"> 
<!-- Read config.ini settings & apply them -->
<cfset iniFile = expandPath("/config.ini")>
<cfset DSN = getProfileString(iniFile, "coldfusion", "DSN")>
<cfset CurrentStyle = getProfileString(iniFile, "settings", "style")>
<!-- Read config.ini settings & apply them -->

<cfinclude template = "/system/functions/checkusersession.cfm" />
<cfinclude template = "/system/functions/loadsettings.cfm" />

<html xmlns="http://www.w3.org/1999/xhtml"> 
<head> 
	<cfinclude template = "/system/functions/hdr_generic.cfm" />
	<cfinclude template = "/system/functions/hdr_user.cfm" />

<cfoutput>
	<script type="text/javascript"> 
		function doPostComment() {
			comment = $("##our_comment").val();
			story = $("##storyid").val();
			uid = $("##uid").val();
			
			if( comment.length < 1 ) {
				return false;
			}
			
			$("##OurNewComment > ##Comment").html("<strong>#user.username#</strong> on NOW<br />" + comment);
			$("##OurNewComment").show("slow");
			$(".CommentInput").hide("slow");
			$.post("/system/functions/postcomment.cfm", { comment: comment, story: story, uid: uid });
		
		}
	</script>
</cfoutput>
</head> 

<body id="news_body"> 
 
<div class="mainBox"> 
	<cfinclude template = "/system/header.cfm" />
	<cfinclude template = "navigation.cfm" />

	<div class="mid" id="midcontent"> 

<cfquery name="RecentStories" datasource="#DSN#">
	SELECT id,title
	FROM cms_news
	ORDER BY id DESC
	LIMIT 25
</cfquery>

<cfif not isdefined('url.story')>
	<cfquery name="ThisStory" datasource="#DSN#">
		SELECT *
		FROM cms_news
		ORDER BY id DESC
		LIMIT 1
	</cfquery>
	<cfset url.story = #ThisStory.id#>
<cfelse>
	<cfquery name="ThisStory" datasource="#DSN#">
		SELECT *
		FROM cms_news
		WHERE id = #url.story#
		LIMIT 1
	</cfquery>
</cfif>
<cfquery name="Author" datasource="#DSN#">
	SELECT *
	FROM users
	WHERE id = #ThisStory.author#
	LIMIT 1
</cfquery>

<cfoutput>
	<input type="hidden" value="#url.story#" name="storyid" id="storyid" />
	<input type="hidden" value="#user.id#" name="uid" id="uid" />
</cfoutput>

<!-- CONTENT, BRO -->
<div class="column" id="column1">
	<div class="contentBox">
		<div class="boxHeader">Recent News</div>
		<div class="boxContent">
			<cfoutput query="RecentStories"><a href="?story=#id#">#title# &raquo;</a><br /></cfoutput>
		</div>
	</div>
</div>
		
<div class="column" id="column2">
<cfoutput>
	<div class="contentBox">
		<div class="boxHeader">#ThisStory.title#</div>
		<div class="boxContent">
			<div class="story">#ThisStory.longstory#</div>
			<div class="extrainfo">
				<div class="poster">Author: #Author.username#</div>
				<div class="date">Published: #DateFormat(dateAdd("s", ThisStory.published, "01/01/1970"))#</div>
			</div>
		</div>
	</div>
</cfoutput>

<cfquery name="Comments" datasource="#DSN#">
	SELECT *
	FROM cms_comments
	WHERE story = #url.story#
	ORDER BY id ASC
</cfquery>

	<div class="boxContent">
		<cfset style = "left">
		<cfoutput query="Comments">
			<cfquery name="Author" datasource="#DSN#">
				SELECT *
				FROM users
				WHERE id = #author#
				LIMIT 1
			</cfquery>
			<div class="UserComment #style#">
				<div class="Avatar"><img src="http://www.habbo.com/habbo-imaging/avatarimage?figure=#author.look#<cfif style is "right">&direction=4</cfif>" alt="#author.username#" /></div>
				<p class="triangle-border #style# <cfif author.rank is 7>staff</cfif>" id="Comment">
					<strong>#author.username#</strong> on #DateFormat(dateAdd("s", date, "01/01/1970"))#<br />
					#comment#
				</p>
			</div>
			<cfif style is "left"><cfset style = "right"><cfelse><cfset style = "left"></cfif>
		</cfoutput>
		<cfoutput>
			<div class="UserComment #style#" style="display: none" id="OurNewComment">
				<div class="Avatar"><img src="http://www.habbo.com/habbo-imaging/avatarimage?figure=#user.look#<cfif style is "right">&direction=4</cfif>" alt="#user.username#" /></div>
				<p class="triangle-border #style#" id="Comment">
					placeholder
				</p>
			</div>
			<div class="CommentInput">
				<div class="MyAvatar"><img src="http://www.habbo.com/habbo-imaging/avatarimage?figure=#user.look#" alt="#user.username#" /></div>
				<div class="Comment"><textarea class="triangle-border left" onfocus="this.value=''; setbg('##e5fff3');" id="our_comment">Enter your comment here...</textarea></div>
				<div class="Submitbtn right">
				    <button type="submit" class="positive" name="submitcomment" onmousedown="doPostComment();">Submit</button>
   				</div>
			</div>
		</cfoutput>
	</div>
</div>
		
<cfinclude template = "/system/sideads.cfm" />
<!-- /CONTENT, BRO -->

	</div> 

	<cfinclude template = "/system/footer.cfm" />
</div> 

</body> 
</html>