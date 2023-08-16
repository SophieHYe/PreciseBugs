ad_page_contract {
    Page for viewing and editing one patch.

    @author Peter Marklund (peter@collaboraid.biz)
    @date 2002-09-04
    @cvs-id $Id$
} {
    patch_number:integer,notnull
    mode:optional
    cancel_edit:optional    
    edit:optional
    accept:optional
    refuse:optional
    delete:optional    
    reopen:optional
    comment:optional
    download:boolean,optional
    {desc_format "text/html"}
}

# Assert read permission (should this check be in the request processor?)
permission::require_permission -object_id [ad_conn package_id] -privilege read

# Initialize variables related to the request that we'll need
set package_id [ad_conn package_id]
set user_id [ad_conn user_id]
# Does the user have write privilege on the project?
set write_p [permission::permission_p -object_id $package_id -privilege write]

set submitter_id [bug_tracker::get_patch_submitter -patch_number $patch_number]

set user_is_submitter_p [expr { $submitter_id ne "" && $user_id == $submitter_id }]
set write_or_submitter_p [expr {$write_p || $user_is_submitter_p}]
set project_name [bug_tracker::conn project_name]
set package_key [ad_conn package_key]
set view_patch_url [export_vars -base [ad_conn url] { patch_number }]
set patch_status [db_string patch_status {}]

# Is this project using multiple versions?
set versions_p [bug_tracker::versions_p]

# Abort editing and return to view mode if the user hit cancel on the edit form
if { ([info exists cancel_edit] && $cancel_edit ne "") } {
    ad_returnredirect $view_patch_url
    ad_script_abort
}

# If the download link was clicked - return the text content of the patch
if { ([info exists download] && $download ne "") } {
    
    set patch_content [db_string get_patch_content {}]
    set outputheaders [ns_conn outputheaders]
    ns_set cput $outputheaders "Content-Disposition" "attachment; filename=patch-${patch_number}.txt"
    doc_return 200 "text/plain" $patch_content
    ad_script_abort
}

# Initialize the page mode variable
# We are in view mode per default
if { ![info exists mode] } {
    if { ([info exists edit] && $edit ne "") } {
        set mode edit
    } elseif { ([info exists accept] && $accept ne "") } {        
        set mode accept
    } elseif { ([info exists refuse] && $refuse ne "") } {
        set mode refuse
    } elseif { ([info exists delete] && $delete ne "") } {
        set mode delete
    } elseif { ([info exists reopen] && $reopen ne "") } {
        set mode reopen
    } elseif { ([info exists comment] && $comment ne "") } {
        set mode comment
    } else {
        set mode view
    }
}

# Specify which fields in the form are editable
# And check that the user is permitted to take the chosen action
switch -- $mode {
    edit {
        if { ![expr {$write_p || $user_is_submitter_p}] } {
            ad_return_forbidden "[_ bug-tracker.Permission]" "[_ bug-tracker.You_2]"
            ad_script_abort
        }

        set edit_fields {component_id summary generated_from_version apply_to_version}
    }
    accept {
        permission::require_permission -object_id $package_id -privilege write

        # The user should indicate which version the patch is applied to
        set edit_fields { applied_to_version }
    }
    refuse {
        permission::require_permission -object_id $package_id -privilege write

        set edit_fields {}
    }
    reopen {
        # User must have write permission to reopen a refused patch
        if { $patch_status eq "refused" && !$write_p } {
            ad_return_forbidden "[_ bug-tracker.Permission]" "[_ bug-tracker.You_3]"
            ad_script_abort
        } elseif { $patch_status eq "deleted" && !($user_is_submitter_p || $write_p)} {
            ad_return_forbidden "[_ bug-tracker.Permission]" "[_ bug-tracker.You_4]"
            ad_script_abort
        }

        set edit_fields {}
    }
    delete {
        # Only the submitter can delete a patch (admins can refuse it)
        if { !$user_is_submitter_p } {
            ad_return_forbidden "[_ bug-tracker.Permission]" "[_ bug-tracker.You_5]"
            ad_script_abort
        }
        set edit_fields {}
    }
    comment {
        set edit_fields {}
    }
    view {
        set edit_fields {}
    }
    default {
	ad_return_forbidden [_ bug-tracker.Permission] "Invalid mode specified"
	ad_script_abort
    }
}

foreach field $edit_fields {
    set field_editable_p($field) 1
}

if { $mode ne "view" } {
    auth::require_login
}    

# XXX FIXME TODO editing a patch invokes filename::validate, which is too paranoid...

# Create the form
switch -- $mode {
      view {
          form create patch -has_submit 1 -cancel_url [export_vars -base [ad_conn url] -url { patch_number }]
      } 
      default {
          form create patch -html { enctype multipart/form-data } -cancel_url [export_vars -base [ad_conn url] -url { patch_number }]
      }
}

# Create the elements of the form
element create patch patch_number \
        -datatype integer \
        -widget   hidden

element create patch patch_number_i \
        -datatype integer \
        -widget   inform \
        -label    "[_ bug-tracker.Patch_1]"

element create patch component_id \
        -datatype text \
        -widget [ad_decode [info exists field_editable_p(component_id)] 1 select inform] \
        -label "[_ bug-tracker.Component]" \
        -options [bug_tracker::components_get_options]

if {$mode eq "view"} {
    element create patch fixes_bugs \
        -datatype text \
        -widget inform \
        -label "[_ bug-tracker.Fix_2]"
}

element create patch summary  \
        -datatype text \
        -widget [ad_decode [info exists field_editable_p(summary)] 1 text inform] \
        -label "[_ bug-tracker.Summary]" \
        -html { size 50 }

element create patch submitter \
        -datatype text \
        -widget inform \
        -label "[_ bug-tracker.Submitted]"

element create patch status \
        -widget inform \
        -datatype text \
        -label "[_ bug-tracker.Status]"

element create patch generated_from_version \
        -datatype text \
        -widget [ad_decode [info exists field_editable_p(generated_from_version)] 1 select inform] \
        -label "[_ bug-tracker.Generated]" \
        -options [bug_tracker::version_get_options -include_unknown] \
        -optional

element create patch apply_to_version \
        -datatype text \
        -widget [ad_decode [info exists field_editable_p(apply_to_version)] 1 select inform] \
        -label "[_ bug-tracker.Apply_2]" \
        -options [bug_tracker::version_get_options -include_undecided] \
        -optional

element create patch applied_to_version \
        -datatype text \
        -widget [ad_decode [info exists field_editable_p(applied_to_version)] 1 select inform] \
        -label "[_ bug-tracker.Applied]" \
        -options [bug_tracker::version_get_options -include_undecided] \
        -optional

switch -- $mode {
    edit - comment - accept - refuse - reopen - delete {
        element create patch description  \
	    -datatype text \
	    -widget comment \
	    -label "[_ bug-tracker.Description]" \
	    -html { cols 60 rows 13 } \
	    -optional
        
        element create patch desc_format \
	    -datatype text \
	    -widget select \
	    -label "[_ bug-tracker.Description_1]" \
	    -options { { "[_ bug-tracker.Plain]" plain } { "[_ bug-tracker.HTML]" html } { "[_ bug-tracker.Preformatted]" pre } }

    }
    default {
        # View mode
        element create patch description \
	    -datatype text \
	    -widget inform \
	    -label "[_ bug-tracker.Description]"
    }
}

# In accept mode - give the user the ability to select associated
# bugs to be resolved
if {$mode eq "accept"} {

    element create patch resolve_bugs \
            -datatype integer \
            -widget checkbox \
            -label "[_ bug-tracker.Resolve_1]" \
            -options [bug_tracker::get_mapped_bugs -patch_number $patch_number -only_open_p 1] \
            -optional
}

if {$mode eq "edit"} {
    # Edit mode - display the file upload widget for patch content
    element create patch patch_file \
          -datatype file \
          -widget file \
          -label "[_ bug-tracker.Patch_2]" \
          -optional
} 

element create patch mode \
        -datatype text \
        -widget hidden \
        -value $mode

set page_title [_ bug-tracker.Patch_3]
set Patches_name [bug_tracker::conn Patches]
set context [list [list "patch-list" "$Patches_name"] $page_title]

if { [form is_request patch] } {
    # The form was requested

    db_1row patch {} -column_array patch
    set patch(generated_from_version_name) [ad_decode $patch(generated_from_version) "" "[_ bug-tracker.Unknown]" [bug_tracker::version_get_name -version_id $patch(generated_from_version)]]
    set patch(apply_to_version_name) [ad_decode $patch(apply_to_version) "" "[_ bug-tracker.Undecided]" [bug_tracker::version_get_name -version_id $patch(apply_to_version)]]
    set patch(applied_to_version_name) [bug_tracker::version_get_name -version_id $patch(applied_to_version)]

    if {$user_id != 0} {
	set submitter_email_display "(<a href=\"mailto:$patch(submitter_email)\">$patch(submitter_email)</a>)"
    } else {
	set submitter_email_display ""
    }

    # When the user is taking an action that should change the status of the patch
    # - update the status (the new status will show up in the form)
    switch -- $mode {
        accept {
            set patch(status) accepted
        }
        refuse {
            set patch(status) refused
        }
        delete {
            set patch(status) deleted
        }
        reopen {
            set patch(status) open
        }
    }

    element set_properties patch patch_number \
            -value $patch(patch_number)
    element set_properties patch patch_number_i \
            -value $patch(patch_number)
    element set_properties patch component_id \
            -value [ad_decode [info exists field_editable_p(component_id)] 1 $patch(component_id) $patch(component_name)]
    if {$mode eq "view"} {
        set bugs_name [bug_tracker::conn bugs]
	set map_to_bugs [_ bug-tracker.Map] 
        set map_new_bug_link [ad_decode $write_or_submitter_p "1" "\[ <a href=\"map-patch-to-bugs?patch_number=$patch(patch_number)\">$map_to_bugs</a> \]" ""]
        element set_properties patch fixes_bugs \
            -value "[bug_tracker::get_bug_links -patch_id $patch(patch_id) -patch_number $patch(patch_number) -write_or_submitter_p $write_or_submitter_p] <br>$map_new_bug_link"
    }
    element set_properties patch summary \
            -value [ad_decode [info exists field_editable_p(summary)] 1 $patch(summary) "<b>$patch(summary)</b>"]
    element set_properties patch submitter \
            -value "
    [acs_community_member_link -user_id $patch(submitter_user_id) \
            -label "$patch(submitter_first_names) $patch(submitter_last_name)"] $submitter_email_display"

    element set_properties patch status \
            -value [ad_decode [info exists field_editable_p(status)] 1 $patch(status) [bug_tracker::patch_status_pretty $patch(status)]]
    element set_properties patch generated_from_version \
            -value [ad_decode [info exists field_editable_p(generated_from_version)] 1 $patch(generated_from_version) $patch(generated_from_version_name)]
    element set_properties patch apply_to_version \
            -value [ad_decode [info exists field_editable_p(apply_to_version)] 1 $patch(apply_to_version) $patch(apply_to_version_name)]
    element set_properties patch applied_to_version \
            -value [ad_decode [info exists field_editable_p(applied_to_version)] 1 $patch(applied_to_version) $patch(applied_to_version_name)]

    set deleted_p [string equal $patch(status) "deleted"]

    if { ( $patch(status) eq "open" && $mode ne "accept" ) || $patch(status) eq "refused" } {
        element set_properties patch applied_to_version -widget hidden
    }

    # Description/Actions/History
    set patch_id $patch(patch_id)
    set action_html ""
    db_foreach actions {} {
        set comment $comment_text
        append action_html "<b>$action_date_pretty [bug_tracker::patch_action_pretty $action] by $actor_first_names $actor_last_name</b>
        <blockquote>[bug_tracker::bug_convert_comment_to_html -comment $comment -format $comment_format]</blockquote>"
    }

    if {$mode eq "view"} {
        element set_properties patch description -value $action_html
    } else {

	set patch_pretty_name $patch(now_pretty)
	set patch_action_pretty_mode [bug_tracker::patch_action_pretty $mode]
	set bt_user_first_names [bug_tracker::conn user_first_names]
	set bt_user_last_name [bug_tracker::conn user_last_name]

        element set_properties patch description \
            -history $action_html \
            -header [_ bug-tracker.Patch_Header ] \
            -value ""
    }

    # Now that we have the patch summary we can make the page title more informative

    set Patch_name [bug_tracker::conn Patch]
    set patch_summary $patch(summary)
    set page_title [_ bug-tracker.Patch_Page_Title]

    # Create the buttons
    # If the user has submitted the patch he gets full write access on the patch
    set user_is_submitter_p [expr {$patch(submitter_user_id) == [ad_conn user_id]}]
    if {$mode eq "view"} {
        set button_form_export_vars [export_vars -form { patch_number }]
        multirow create button name label

        if { $write_p || $user_is_submitter_p } {
            multirow append button "comment" "[_ bug-tracker.Comment]"
            multirow append button "edit" "[_ bug-tracker.Edit]"
        }

        switch -- $patch(status) {
            open {
                if { $write_p } {
                    multirow append button "accept" "[_ bug-tracker.Accept]"
                    multirow append button "refuse" "[_ bug-tracker.Refuse]"
                }

                # Only the submitter can cancel the patch
                if { $user_is_submitter_p } {
                    multirow append button "delete" "[_ bug-tracker.Delete]"
                }
            }
            accepted {
                if { $write_p } {
                    multirow append button "reopen" "[_ bug-tracker.Reopen]"
                }
            }
            refused {
                if { $write_p } {
                    multirow append button "reopen" "[_ bug-tracker.Reopen]"    
                }
            }
            deleted {
                if { $write_p || $user_is_submitter_p } {
                    multirow append button "reopen" "[_ bug-tracker.Reopen]"
                }
            }
        }
    }    

    # Check that the user is permitted to change the patch
    if { $mode ne "view" && !$write_p && !$user_is_submitter_p } {
        ns_log notice "$patch(submitter_user_id) doesn't have write on object $patch(patch_id)"
        ad_return_forbidden "[_ bug-tracker.Permission]" "<blockquote>
        [_ bug-tracker.You_6]
        </blockquote>"
        ad_script_abort
    }    

    if { !$versions_p } {
        element set_properties patch generated_from_version -widget hidden
    }
}

if { [form is_valid patch] } {
    # A valid submit of the form

    set update_exprs [list]

    form get_values patch patch_number

    foreach column $edit_fields {
        set $column [element get_value patch $column]
        lappend update_exprs "$column = :$column"
        if {$column eq "summary"} { 
            set new_title "Patch \#$patch_number: $summary"
        }
    }
    
    switch -- $mode {
        accept {
            set status accepted
            lappend update_exprs "status = :status"
        }
        refuse {
            set status refused
            lappend update_exprs "status = :status"            
        }
        reopen {
            set status open
            lappend update_exprs "status = :status"
        }
        edit {
            # Get the contents of any new uploaded patch file
            set content [bug_tracker::get_uploaded_patch_file_content]

            if { $content ne "" } {
                lappend update_exprs "content = :content"
            } 
        }
        delete {
            set status deleted
            lappend update_exprs "status = :status"            
        }
    }

    db_transaction {
        set patch_id [db_string patch_id {}]

        if { [llength $update_exprs] > 0 } {
            db_dml update_patch {}
        }
        if {[info exists new_title] && $new_title ne ""} { 
            db_dml update_patch_title {update acs_objects set title = :new_title where object_id = :patch_id}
        }
        set action_id [db_nextval "acs_object_id_seq"]

	foreach column { description desc_format } {
	    if {[element exists patch $column]} {
		set $column [element get_value patch $column]
	    }
        }

        set action $mode
        db_dml patch_action {}

        if {$mode eq "accept"} {
            # Resolve any bugs that the user selected
            set resolve_bugs [element get_values patch resolve_bugs]

            foreach bug_number $resolve_bugs {

                set resolve_description "[_ bug-tracker.Fixed_2]"                
                set workflow_id [bug_tracker::bug::get_instance_workflow_id]
                set bug_id [bug_tracker::get_bug_id -bug_number $bug_number -project_id $package_id]
                set case_id [workflow::case::get_id \
                                 -workflow_short_name "[bug_tracker::bug::workflow_short_name]" \
                                 -object_id $bug_id]
                set action_id [workflow::action::get_id -workflow_id $workflow_id -short_name "resolve"]
                set enabled_action_id [db_string get_enabled_action_id ""]
                         
                bug_tracker::bug::edit \
                    -bug_id $bug_id \
                    -enabled_action_id $enabled_action_id \
                    -description $resolve_description \
                    -desc_format "text/html" \
                    -array bug_row
            }
        }
    }

    ad_returnredirect $view_patch_url
    ad_script_abort
}

ad_return_template
