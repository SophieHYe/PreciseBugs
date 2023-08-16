<contract>
  Display one bug

  @author Jeff Davis davis@xarg.net
  @cvs-id $Id$

  @param bug array of values as returned from bug_tracker::bug::get
  @param comments html chunk of comments
  @param style string (either "feed" or "display" -- default is display)
  @param base_url url to the package (ok for this to be empty if in the package, trailing / expected)
</contract>
<h1>Bug @bug.bug_number_display@ - @bug.summary@ [@bug.component_name@]</h1>
<p>State: @bug.pretty_state@</p>
<if @bug.found_in_version_name@ not nil><p>Found in version: @bug.found_in_version_name@</p></if>
<if @bug.fix_for_version_name@ not nil><p>Fix for version: @bug.fix_for_version_name@</p></if>
<if @bug.fixed_in_version_name@ not nil><p>Fixed in version: @bug.fixed_in_version_name@</p></if>

<multiple name="roles"><p>@roles.role_pretty@: <a href="@roles.user_url@">@roles.user_name@</a></p></multiple>

@comments;noquote@