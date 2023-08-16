<cfcomponent output="false" extends="farcry.core.packages.forms.forms" displayname="Solr Pro Search" hint="Handles searching Solr collections">
	<cfproperty ftSeq="110" ftFieldset="General" name="q" type="string" default="" hint="The search text criteria" ftLabel="Search" ftClass="solr-search-criteria" />
	<cfproperty ftSeq="120" ftFieldset="General" name="operator" type="string" default="" hint="The operator used for the search" ftLabel="Search Operator" ftType="list" ftList="any:Any of these words,all:All of these words,phrase:These words as a phrase" />
	<cfproperty ftSeq="130" ftFieldset="General" name="lContentTypes" type="string" default="" hint="The content types to be searched" ftLabel="Content Types" ftType="list" ftListData="getContentTypeList" />
	<cfproperty ftSeq="140" ftFieldset="General" name="orderBy" type="string" default="rank" hint="The sort order of the results" ftLabel="Sort Order" ftType="list" ftList="rank:Relevance,date:Date" />
	
	<cfproperty name="bSearchPerformed" type="boolean" default="false" hint="Will be true if any search has been performed" />
	
	<cffunction name="getContentTypeList" access="public" output="false" returntype="string" hint="Returns a list used to populate the lCollections field dropdown selection">
		<cfargument name="objectid" required="true" hint="The objectid of this object" />
		
		<cfset var oContentType = application.fapi.getContentType("solrProContentType") />
		<cfset var qContentTypes = oContentType.getAllContentTypes() />
		<cfset var lResult = ":All" />
		
		<cfloop query="qContentTypes">
			<cfif qContentTypes.bEnableSearch eq 1>
				<cfset lResult = listAppend(lResult, "#qContentTypes.contentType[qContentTypes.currentRow]#:#qContentTypes.title[qContentTypes.currentRow]#") />
			</cfif>
		</cfloop>
		
		<cfreturn lResult />
	</cffunction>
	
	<cffunction name="getSearchResults" access="public" output="false" returntype="struct" hint="Returns a structure containing extensive information of the search results">
		<cfargument name="objectid" required="true" hint="The objectid of the solrProSearch object containing the details of the search" />
		<cfargument name="bSpellcheck" required="false" default="true" hint="enable/disable spellchecker" />
		<cfargument name="rows" required="false" default="10" />
		<cfargument name="page" required="false" default="1" />
		<cfargument name="bHighlight" required="false" type="boolean" default="true" hint="enable/disable highlighting" />
		<cfargument name="hlFragSize" required="false" type="numeric" default="200" hint="The length in characters of each highlight snippet" />
		<cfargument name="hlSnippets" required="false" type="numeric" default="3" hint="The number of highlighting snippets to return" />
		<cfargument name="hlPre" required="false" type="string" default="<strong>" hint="HTML to use to wrap instances of search terms" />
		<cfargument name="hlPost" required="false" type="string" default="</strong>" hint="HTML to use to wrap instances of search terms" />
		<cfargument name="bLogSearch" required="false" type="boolean" default="#application.fapi.getConfig(key = 'solrserver', name = 'bLogSearches', default = true)#" hint="Log the search criteria and number of results?" />
		<cfargument name="bCleanString" required="false" type="boolean" default="true" />
		<cfargument name="bFilterBySite" required="false" type="boolean" default="true" hint="If using a single Solr core for multiple sites, do you want to filter results for only this site (true) or for all sites (false)?" />
		<cfargument name="customQueryString" required="false" type="string" hint="If you want to use a custom query string, you can pass it along here" />
		<cfargument name="customParams" required="false" type="struct" hint="If you want to use a custom Solr parameters, you can pass them along here" />
		<cfargument name="bLowerCaseString" required="false" type="boolean" default="true" />
		
		<!--- calculate the start row --->
		<cfset var startRow = ((arguments.page - 1) * arguments.rows) />
		<cfset var stResult = { bSearchPerformed = 0 } />
		<cfset var stSearchForm = getData(objectid = arguments.objectid) />
		<cfset var oContentType = application.fapi.getContentType("solrProContentType") />
		<cfset var params = {} />
		
		<cfif stSearchForm.bSearchPerformed eq 1>
			
			<!--- convert search criteria into a proper solr query string (using chosen operator (any,all,phrase) and target collection, if specified) --->
			
			<!--- spellcheck --->
			<cfif arguments.bSpellcheck is true>
				<cfset params["spellcheck"] = true />
				<cfset params["spellcheck.count"] = 1 />
				<cfset params["spellcheck.q"] = stSearchForm.q />
				<cfif listLen(stSearchForm.q, " ") gt 1>
					<cfset params["spellcheck.dictionary"] = "phrase" />
				<cfelse>
					<cfset params["spellcheck.dictionary"] = "default" />
				</cfif>
				<cfset params["spellcheck.build"] = false />
				<cfset params["spellcheck.onlyMorePopular"] = true />
				<cfset params["spellcheck.collate"] = true />
			<cfelse>
				<cfset params["spellcheck"] = false />
			</cfif>

			<cfif structKeyExists(arguments,"customQueryString")>
				<cfset var q = arguments.customQueryString />
			<cfelse>
				<cfset var q = oContentType.buildQueryString(searchString = stSearchForm.q, operator = stSearchForm.operator, lContentTypes = stSearchForm.lContentTypes, bCleanString = arguments.bCleanString, bFilterBySite = arguments.bFilterBySite, bLowerCaseString = arguments.bLowerCaseString) />
			</cfif>
			
			<!--- get the field list for the content type(s) we are searching --->
			<!--- if doing a "PHRASE" search, remove all PHONETIC fields. to match Google and other search engine functionality --->
			<cfset var lContentTypeIds = "" />
			<cfset var ct = "" />
			<cfloop list="#stSearchForm.lContentTypes#" index="ct">
				<cfset lContentTypeIds = listAppend(lContentTypeIds, oContentType.getByContentType(ct).objectid) />
			</cfloop>
			<cfset params["qf"] = oContentType.getFieldListForTypes(
				lContentTypes = lContentTypeIds,
				bIncludePhonetic = (stSearchForm.operator neq "phrase"), 
				bIncludeNonString = false,
				bUseCache = true,
				bFlushCache = false
			) />

			<!--- return the score --->
			<cfset params["fl"] = "*,score" />
			
			<!--- apply the sort --->
			<cfif stSearchForm.orderby eq "date">
				<cfset params["sort"] = "datetimelastupdated desc" />
			<cfelseif stSearchForm.orderby eq "dateAsc">
				<cfset params["sort"] = "datetimelastupdated asc" />
			</cfif>
			
			<!--- get highlighting --->
			<cfif arguments.bHighlight>
				<cfset params["hl"] = true />
				<cfset params["hl.fragsize"] = arguments.hlFragSize />
				<cfset params["hl.snippets"] = arguments.hlSnippets />
				<cfset params["hl.fl"] = "fcsp_highlight" />
				<cfset params["hl.simple.pre"] = arguments.hlPre />
				<cfset params["hl.simple.post"] = arguments.hlPost />
			</cfif>
			
			<!--- Custom params override generated params --->
			<cfif structKeyExists(arguments,"customParams")>
				<cfset structAppend(params,arguments.customParams,"yes") />
			</cfif>
			
			<cfset stResult = oContentType.search(q = trim(q), start = startRow, rows = arguments.rows, params = params) />
			<cfset stResult.bSearchPerformed = 1 />
			
			<cfif arguments.bSpellcheck and structKeyExists(stResult, "spellcheck")>
				<cfset stResult.suggestion = getSuggestion(
					linkURL = application.fapi.getLink(objectid = request.navid), 
					spellcheck = stResult.spellcheck, 
					q = stSearchForm.q,
					operator = stSearchForm.operator,
					lContentTypes = stSearchForm.lContentTypes,
					orderby = stSearchForm.orderby,
					startWrap = '<strong>', 
					endWrap = '</strong>'
				) />
			<cfelse>
				<cfset stResult.suggestion = "" />
			</cfif>
			
			<!--- ensure log is enabled, only log search for page 1 --->
			<cfif arguments.bLogSearch and arguments.page eq 1>
				<!--- log the search and result stats --->
				<cfset var oLog = application.fapi.getContentType("solrProSearchLog") />
				<cfset var stLog = {
					numResults = stResult.totalResults,
					q = stSearchForm.q,
					lContentTypes = stSearchForm.lContentTypes,
					operator = stSearchForm.operator,
					orderBy = stSearchForm.orderBy,
					suggestion = stResult.suggestion
				} />
				<cfset oLog.createData(stLog) />
			</cfif>
			
		</cfif>
		
		<cfreturn stResult />
		
	</cffunction>
	
	<cffunction name="getSuggestion" access="public" output="false" returntype="string" hint="Returns suggestion text based on results from solr">
		
		<cfargument name="spellcheck" type="array" required="true" />
		
		<cfargument name="q" type="string" required="true" />
		<cfargument name="operator" type="string" required="false" default="any" />
		<cfargument name="lContentTypes" type="string" required="false" default="" />
		<cfargument name="orderby" type="string" required="false" default="rank" />
		
		<cfargument name="startWrap" type="string" required="false" default="<strong>" />
		<cfargument name="endWrap" type="string" required="false" default="</strong>" />
		<cfargument name="linkUrl" type="string" required="false" default="#application.fapi.getLink(objectid = request.navid)#" />
		
		<!--- if we have no spell check info, just return empty string --->
		<cfif not arrayLen(arguments.spellcheck)>
			<cfreturn "" />
		</cfif>
		
		<!--- build the suggestion --->
		<cfset var suggestion = arguments.q />
		<cfset var s = "" />
		<cfloop array="#arguments.spellcheck#" index="s">
			<!--- create one w/ the wrap --->
			<cfset suggestion = trim(reReplaceNoCase(suggestion,"^#s.token# | #s.token# | #s.token#$|^#s.token#$"," " & arguments.startWrap & s.suggestions[1] & arguments.endWrap & " ","ALL")) />
			<!--- and one w/o --->
			<cfset arguments.q = trim(reReplaceNoCase(arguments.q,"^#s.token# | #s.token# | #s.token#$|^#s.token#$"," " & s.suggestions[1] & " ","ALL")) />
		</cfloop>
		
		<!--- build the url for the link --->
		<cfset var addValues = {
			"q" = arguments.q,
			"operator" = arguments.operator,
			"orderby" = arguments.orderby
		} />
		<cfif len(trim(arguments.lContentTypes))>
			<cfset addValues["lContentTypes"] = arguments.lContentTypes />
		</cfif>
		<cfset arguments.linkUrl = application.fapi.fixUrl(
			url = arguments.linkUrl, 
			addValues = addValues
		) />
		
		<!--- build the HTML and return it --->
		<cfset var str = "" />
		<cfsavecontent variable="str">
			<cfoutput>Did you mean <a href="#arguments.linkUrl#">#application.stPlugins.farcrysolrpro.oCustomFunctions.xmlSafeText(suggestion)#</a>?</cfoutput>
		</cfsavecontent>
		
		<cfreturn trim(str) />
		
	</cffunction>
	
</cfcomponent>