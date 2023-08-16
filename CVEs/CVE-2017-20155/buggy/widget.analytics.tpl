<div id="analytics-panel-widget">
    <div id="tab1" class="x-hide-display">
        <div id="tab1-holder">
        <table class="classy" style="width:100%">
        
        <thead>
        <tr>
            <th>{$_langs.date}</th>
            <th>{$_langs.visits}</th>
            <th>{$_langs.unique_visitors}</th>
            <th>{$_langs.pageviews_visits}</th>
            <th>{$_langs.pageviews}</th>
            <th>{$_langs.site_time}</th>
            <th>{$_langs.new_visits}</th>
            <th>{$_langs.bounce_rate}</th>
        </tr>
        </thead>
        <tbody>
        {foreach from=$visitsarr.rows item=visits}
        <tr class="{cycle values=',odd'}">
                <td>{$visits.0}</td>
                <td>{$visits.1}</td>
                <td>{$visits.2}</td>
                <td>{$visits.4|number_format:2:",":"."}</td>
                <td>{$visits.3}</td>
                <td>{$visits.5|number_format:2}</td>
                <td>{$visits.6|number_format:2:",":"."} %</td>
                <td>{$visits.7|number_format:2:",":"."} %</td>
        </tr>
        {foreachelse}
        <tr>
            <th colspan="5">{$_langs.connection_error}</th>
        </tr>
        {/foreach}
        <tr>
                <td>{$_langs.total}</td>
                <td>{$visitsarr.totalsForAllResults.visits}</td>
                <td>{$visitsarr.totalsForAllResults.visitors}</td>
                <td>{$visitsarr.totalsForAllResults.pageviewsPerVisit|number_format:2:",":"."}</td>
                <td>{$visitsarr.totalsForAllResults.pageviews}</td>
                <td>{$visitsarr.totalsForAllResults.avgTimeOnSite|number_format:2}</td>
                <td>{$visitsarr.totalsForAllResults.percentNewVisits|number_format:2:",":"."} %</td>
                <td>{$visitsarr.totalsForAllResults.visitBounceRate|number_format:2:",":"."} %</td>
        </tr>
        </tbody>
        </table>
        </div>
    </div>
    <div id="tab2" class="x-hide-display">
				
        <div id="tab2-holder">
            <h2>{$_langs.top_sources}</h2>
            <table class="classy" style="width: 48%; float:left; margin-right:2%;">
            
	            <thead>
	            <tr>
	                <th>{$_langs.sources}</th>
	                <th>{$_langs.visits}</th>
	                <th>% {$_langs.new_visits}</th>
	            </tr>
	            </thead>
	            <tbody>
	            {$i = 0}
	            {foreach from=$toptrafficsource.rows item=toptraffic}
		            {if $i == 5}{break}{/if}
			            <tr class="{cycle values=',odd'}">
			                    <td>{$toptraffic.0}</td>
			                    <td>{$toptraffic.1}</td>
			                    <td>{$toptraffic.5|number_format:2:",":"."} %</td>
			            </tr>
		            {$i = $i+1}
		            {foreachelse}
		            <tr>
		                <th colspan="5">{$_langs.connection_error}</th>
		            </tr>
	            {/foreach}
            </tbody>
            </table>
            <table class="classy" style="width: 48%; float:left; margin-right:2%;">
	            <thead>
	            <tr>
	                <th>{$_langs.keywords}</th>
	                <th>{$_langs.visits}</th>
	                <th>% {$_langs.new_visits}</th>
	            </tr>
	            </thead>
	            <tbody>

	            {$i = 0}
	            {foreach from=$keywords.rows item=keyword}
		            {if $keyword.keyword != '(not set)'}
			            {if $i == 5}{break}{/if}
			            <tr class="{cycle values=',odd'}">
				                    <td>{$keyword.0}</td>
				                    <td>{$keyword.1}</td>
				                    <td>{$keyword.3|number_format:2:",":"."} %</td>
				            </tr>
			            {$i = $i+1}
		            {/if}
	            {foreachelse}
	            <tr>
	                <th colspan="5">{$_langs.connection_error}</th>
	            </tr>
	            {/foreach}
            </tbody>
            </table>
            <p style="clear:both;"></p>
            <h2>{$_langs.referring_sites}</h2>
            <table class="classy" style="width: 100%;">
            
            <thead>
            <tr>
                <th>{$_langs.sources}</th>
                <th>{$_langs.visits}</th>
                <th>{$_langs.pages_visits}</th>
                <th>{$_langs.average_site_time}</th>
                <th>% {$_langs.new_visits}</th>
                <th>{$_langs.bounce_rate}</th>
            </tr>
            </thead>
            <tbody>
            {$i = 0}
            {foreach from=$toptrafficsource.rows item=trafficreffered}
                 {if $trafficreffered.0 != 'google' && $trafficreffered.0 != '(direct)' && $trafficreffered.0 != 'localhost' && $trafficreffered.0 != 'bing' && $trafficreffered.0 != 'google.nl'}
                 {if $i == 10}{break}{/if}

            <tr class="{cycle values=',odd'}">
                    <td>{$trafficreffered.0}</td>
                    <td>{$trafficreffered.1}</td>
                    <td>{$trafficreffered.3|number_format:2:",":"."} %</td>
                    <td>{$trafficreffered.4|number_format:2}</td>
                    <td>{$trafficreffered.5|number_format:2:",":"."} %</td>
                    <td>{$trafficreffered.6|number_format:2:",":"."} %</td>
            </tr>
            {$i = $i+1}

            {/if}
            {foreachelse}
            <tr>
                <th colspan="5">{$_langs.connection_error}</th>
            </tr>
            {/foreach}

            </tbody>
            </table>
        </div>
    </div>
    <div id="tab3" class="x-hide-display">
        <h2>{$_langs.top_landing_pages}</h2>
            <table class="classy" style="width: 100%;">
        <thead>
        <tr>
            <th style="width: 40%;">{$_langs.page}</th>
            <th style="width: 20%;">{$_langs.entrances}</th>
            <th style="width: 20%;">{$_langs.bounces}</th>
            <th style="width: 20%;">{$_langs.bounce_rate}</th>
        </tr>
        </thead>
        <tbody>
        {$i = 0}
        {foreach from=$toplandingspages.rows item=toppage}
        {if $i == 10}{break}{/if}
        <tr class="{cycle values=',odd'}">
                <td>{$toppage.0}</td>
                <td>{$toppage.1}</td>
                <td>{$toppage.2}</td>
                <td>{$toppage.3|number_format:2:",":"."} %</td>

        </tr>
         {$i = $i+1}
        {foreachelse}
        <tr>
            <th colspan="5">{$_langs.connection_error}</th>
        </tr>
        {/foreach}
        </tbody>
        </table>
        <h2>{$_langs.top_exit_pages}</h2>
            <table class="classy" style="width: 100%;">
        <thead>
        <tr>
            <th style="width: 40%;">{$_langs.page}</th>
            <th style="width: 20%;">{$_langs.exits}</th>
            <th style="width: 20%;">{$_langs.pageviews}</th>
            <th style="width: 20%;">% {$_langs.exit}</th>
        </tr>
        </thead>
        <tbody>
        {$i = 0}
         {foreach from=$topexitpages.rows item=exitpage}
        {if $i == 10}{break}{/if}
        <tr class="{cycle values=',odd'}">
                <td>{$exitpage.0}</td>
                <td>{$exitpage.1}</td>
                <td>{$exitpage.2}</td>
                <td>{$exitpage.3|number_format:2:",":"."} %</td>

        </tr>
         {$i = $i+1}
        {foreachelse}
        <tr>
            <th colspan="5">{$_langs.connection_error}</th>
        </tr>
        {/foreach}
        </tbody>
        </table>

    </div>
    <div id="tab4" class="x-hide-display">
        <div id="goals-holder">
        <h2>{$_langs.goals_part1} {$general.allGoals} {$_langs.goals_part2}</h2>
        <table class="classy" style="width: 48%;">
        <thead>
        <tr>
            <th>{$_langs.goals}</th>
            <th>{$_langs.conversions}</th>
        </tr>
        </thead>
        <tbody>
        {foreach from=$goalstable item=goal}
        <tr class="{cycle values=',odd'}">
            <td>{$goal.goalname}</td>
            <td>{$goal.completions}</td>
        </tr>
         {/foreach}
        </tbody>
        </table>
        </div>

    </div>
    <div id="tab5" class="x-hide-display">
            <table class="classy" style="width: 100%;">
        <thead>
        <tr>
            <th>{$_langs.keywords}</th>
            <th>{$_langs.visits}</th>
            <th>{$_langs.pages_visits}</th>
            <th>{$_langs.average_site_time}</th>
            <th>% {$_langs.new_visits}</th>
            <th>{$_langs.bounce_rate}</th>
        </tr>
        </thead>
        <tbody>
        {$i = 0}
         {foreach from=$keywords.rows item=keyword}
         {if $keyword.keyword != '(not set)'}
         {if $i == 20}{break}{/if}
        <tr class="{cycle values=',odd'}">
                <td>{$keyword.0}</td>
                <td>{$keyword.1}</td>
                <td>{$keyword.2|number_format:2:",":"."} %</td>
                <td>{$keyword.3|number_format:2}</td>
                <td>{$keyword.4|number_format:2:",":"."} %</td>
                <td>{$keyword.5|number_format:2:",":"."} %</td>
        </tr>
        {$i = $i+1}
        {/if}
        {foreachelse}
        <tr>
            <th colspan="5">{$_langs.connection_error}</th>
        </tr>
        {/foreach}
        </tbody>
        </table>

    </div>
    <div id="tab6" class="x-hide-display">
            <table class="classy" style="width: 100%;">
        <thead>
        <tr>
            <th>{$_langs.search_keyword}</th>
            <th>{$_langs.search_uniques}</th>
            <th>{$_langs.search_result_views}</th>
            <th>% {$_langs.search_exits}</th>
            <th>{$_langs.search_duration}</th>
            <th>{$_langs.search_depth}</th>
        </tr>
        </thead>
        <tbody>
        {$i = 0}
         {foreach from=$sitesearches.rows item=sitesearch}
         {if $i == 20}{break}{/if}
        <tr class="{cycle values=',odd'}">
                <td>{$sitesearch.0}</td>
                <td>{$sitesearch.1}</td>
                <td>{$sitesearch.2}</td>
                <td>{$sitesearch.3|number_format:2:",":"."}  %</td>
                <td>{$sitesearch.4}</td>
                <td>{$sitesearch.5}</td>
        </tr>
        {$i = $i+1}
        {foreachelse}
        <tr>
            <th colspan="5">{$_langs.no_result}</th>
        </tr>
        {/foreach}
        </tbody>
        </table>

    </div>
</div>
