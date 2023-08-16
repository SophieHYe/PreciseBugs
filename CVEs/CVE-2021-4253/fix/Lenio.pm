=pod
Lenio - Web-based Facilities Management Software
Copyright (C) 2013 A Beverley

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU Affero General Public License as
published by the Free Software Foundation, either version 3 of the
License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU Affero General Public License for more details.

You should have received a copy of the GNU Affero General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
=cut

package Lenio;

use CtrlO::Crypt::XkcdPassword;
use Dancer2;
use Dancer2::Core::Cookie;
use DateTime::Format::Strptime;
use JSON qw(encode_json);
use Lenio::Calendar;
use Lenio::Config;
use Lenio::Email;
use Session::Token;
use Text::CSV;

use Dancer2::Plugin::DBIC;
use Dancer2::Plugin::Auth::Extensible;
use Dancer2::Plugin::LogReport;

set behind_proxy => config->{behind_proxy};

Lenio::Config->instance(
    config => config,
);

our $VERSION = '0.1';

# There should never be exceptions from DBIC, so we want to panic them to
# ensure they get notified at the correct level.
schema->exception_action(sub {
    # Older versions of DBIC use this handler during expected exceptions.
    # Temporary hack: do not panic these as DBIC does not catch them
    die $_[0] if $_[0] =~ /^Unable to satisfy requested constraint/; # Expected
    die $_[0] if $_[0] =~ /MySQL server has gone away/; # Expected
    panic @_; # Not expected
});

my $dateformat = config->{lenio}->{dateformat};

my $password_generator = CtrlO::Crypt::XkcdPassword->new;

sub _update_csrf_token
{   session csrf_token => Session::Token->new(length => 32)->get;
}

hook before => sub {

    # Used to display error messages
    return if param 'error';

    my $user = logged_in_user

    my $method      = request->method;
    my $path        = request->path;
    my $query       = request->query_string;
    my $username    = $user && $user->{username};
    my $description = $user
        ? qq(User "$username" made "$method" request to "$path")
        : qq(Unauthenticated user made "$method" request to "$path");
    $description .= qq( with query "$query") if $query;
    # Log to audit
    rset('Audit')->user_action(description => $description, url => $path, method => $method, login_id => $user && $user->{id});

    $user
        or return;

    header "X-Frame-Options" => "DENY"; # Prevent clickjacking

    if (!session 'csrf_token')
    {
        _update_csrf_token();
    }

    if (request->is_post)
    {
        # Protect against CSRF attacks
        panic __x"csrf-token missing for path {path}", path => request->path
            if !param 'csrf_token';
        error __x"Suspected attack: CSRF token does not match that in the session"
            if param('csrf_token') ne session('csrf_token');

        # If it's a potential login, change the token
        _update_csrf_token()
            if request->path eq '/login';
    }

    my $login = rset('Login')->find($user->{id});

    # Do not try and get sites etc if logging out. User may have received "no
    # sites associated" error and be trying to logout, in which case we don't
    # want to run the following code as it will generate errors
    return if request->uri eq '/logout';

    # Sites associated with the user 
    forward '/error', { 'error' => 'There are no sites associated with this username' }
        unless $login->sites;
 
    # Select individual site and check user has access
    if ( query_parameters->get('site') && query_parameters->get('site') eq 'all' ) {
        session site_id => '';
        session group_id => undef;
    }
    elsif ($login->is_admin && query_parameters->get('group'))
    {
        session site_id => undef;
        session group_id => query_parameters->get('group');
    }
    elsif ( query_parameters->get('site') ) {
        session site_id => query_parameters->get('site')
            if $login->has_site(query_parameters->get('site'));
        session group_id => undef;
    }
    elsif (!defined(session 'site_id') && !defined(session 'group_id')) {
        session(site_id => ($login->sites)[0]->id) unless (defined session('site_id'));
    }

    my $contractors = session('contractors') || {};
    if (my $contractor_id = query_parameters->get('contractor'))
    {
        if ($contractors->{$contractor_id}) {
            delete $contractors->{$contractor_id};
        } else {
            $contractors->{$contractor_id} = 1;
        }
    }
    elsif (defined query_parameters->get('contractor')) # clear all
    {
        $contractors = {};
    }
    session 'contractors' => $contractors;

    my $site_ids = $login->is_admin && session('site_id')
                 ? session('site_id')
                 : $login->is_admin && session('group_id') && rset('Group')->find(session 'group_id')
                 ? rset('Group')->find(session 'group_id')->site_ids
                 : session('site_id')
                 ? session('site_id')
                 : $login->site_ids;
    var site_ids => $site_ids;

    session 'fy' => query_parameters->get('fy') if query_parameters->get('fy');
    session 'fy' => Lenio::FY->new(site_id => session('site_id'), schema => schema)->year
        if !session('fy');

    var login => $login;
};

hook before_template => sub {
    my $tokens = shift;

    my $base = $tokens->{base} || request->base;
    $tokens->{url}->{css}  = "${base}css";
    $tokens->{url}->{js}   = "${base}js";
    $tokens->{url}->{page} = $base;
    $tokens->{url}->{page} =~ s!.*/!!; # Remove trailing slash
    $tokens->{scheme}    ||= request->scheme; # May already be set for phantomjs requests
    $tokens->{hostlocal}   = config->{gads}->{hostlocal};

    $tokens->{messages}   = session('messages');
    $tokens->{csrf_token} = session 'csrf_token';
    $tokens->{login}      = var('login');
    $tokens->{groups}     = [schema->resultset('Group')->ordered];
    $tokens->{contractors} = [rset('Contractor')->ordered];
    $tokens->{contractors_selected} = session 'contractors';
    $tokens->{company_name} = config->{lenio}->{invoice}->{company};
    $tokens->{logo} = config->{lenio}->{logo};
};

get '/' => require_login sub {

    # Deal with sort options
    if (query_parameters->get('sort'))
    {
        session task_desc => session('task_sort') && session('task_sort') eq query_paremeters->get('sort') ? !session('task_desc') : 0;
        session task_sort => query_parameters->get('sort');
    }

    # Overdue tasks for non-global items are not being used so are now removed
    # from the template. Code retained here anyway for time being.
    my $local = var('login')->is_admin ? 0 : 1; # Only show local tasks for non-admin
    my @overdue = rset('Task')->overdue(
        site_id   => var('site_ids'),
        login     => var('login'),
        local     => $local,
        sort      => session('task_sort'),
        sort_desc => session('task_desc'),
    );
    template 'index' => {
        dateformat => config->{lenio}->{dateformat},
        tasks      => \@overdue,
        page       => 'index'
    };
};

sub login_page_handler
{
    my $messages = session('messages') || undef;
    success __"A password reset request has been sent if the email address
           entered was valid" if defined param('reset_sent');
    if (defined param('login_failed'))
    {
        status 401;
        report {is_fatal=>0}, ERROR => "Username or password not valid";
    }
    template login => {
        page                => 'login',
        new_password        => request->parameters->get('new_password'),
        password_code_valid => request->parameters->get('password_code_valid'),
        reset_code          => request->parameters->get('new_password') || request->parameters->get('password_code_valid'),
    };
}

get '/logout' => sub {
    app->destroy_session;
    rset('Audit')->logout(logged_in_user->{username}, logged_in_user->{id}) = @_;
    forwardHome();
};

# Dismiss a notice
post '/close/:id' => require_login sub {
    my $notice = rset('LoginNotice')->find(route_parameters->get('id')) or return;
    $notice->delete if $notice->login_id == var('login')->id;
};

any ['get', 'post'] => '/user/:id' => require_login sub {

    my $is_admin = var('login')->is_admin;
    my $id       = $is_admin ? route_parameters->get('id') : var('login')->id;

    my $email_comment = body_parameters->get('email_comment') ? 1 : 0;
    my $email_ticket  = body_parameters->get('email_ticket') ? 1 : 0;
    my $only_mine  = body_parameters->get('only_mine') ? 1 : 0;
    if (!$id && $is_admin && body_parameters->get('submit'))
    {
        my $email = body_parameters->get('email')
            or error "Please enter an email address for the new user";
        # check existing
        rset('Login')->active_rs->search({ email => $email })->count
            and error __x"The email address {email} already exists", email => $email;
        my $newuser = create_user username => $email, email => $email, realm => 'dbic', email_welcome => 1;
        $id = $newuser->{id};
        # Default to on
        $email_comment = 1;
        $email_ticket  = 1;
    }

    my $login    = $id && rset('Login')->find($id);
    $id && !$login and error "User ID {id} not found", id => $id;

    if ($is_admin && body_parameters->get('delete'))
    {
        if (process sub { $login->disable })
        {
            forwardHome({ success => "User has been deleted successfully" }, 'users');
        }
    }

    if (body_parameters->get('submit')) {
        $login->username(body_parameters->get('email'));
        $login->email(body_parameters->get('email'));
        $login->firstname(body_parameters->get('firstname'));
        $login->surname(body_parameters->get('surname'));
        $login->email_comment($email_comment);
        $login->email_ticket($email_ticket);
        $login->only_mine($only_mine);

        $login->is_admin(body_parameters->get('is_admin') ? 1 : 0)
            if $is_admin;
        if ($is_admin && !$login->is_admin)
        {
            my @org_ids = body_parameters->get_all('org_ids');
            $login->update_orgs(@org_ids);
        }
        if (process sub { $login->update_or_insert } )
        {
            my $forward = $is_admin ? 'users' : '';
            forwardHome({ success => "User has been submitted successfully" }, $forward);
        }
    }

    my @orgs = rset('Org')->all;
    template 'user' => {
        id         => $id,
        orgs       => \@orgs,
        edit_login => $login,
        page       => 'user'
    };
};

get '/users/?' => require_login sub {

    var('login')->is_admin
        or error "You do not have access to this page";

    template 'users' => {
        logins    => [rset('Login')->active],
        page      => 'user'
    };
};

any ['get', 'post'] => '/group/:id' => require_login sub {

    var('login')->is_admin
        or error "You do not have access to this page";

    my $id = route_parameters->get('id');

    my $group = ($id && rset('Group')->find($id)) || rset('Group')->new({});

    if (body_parameters->get('submit'))
    {
        $group->name(body_parameters->get('name'));
        $group->set_site_ids([body_parameters->get_all('site_ids')]);
        if (process sub { $group->write })
        {
            forwardHome(
                { success => 'The group has been successfully updated' }, 'groups' );
        }
    }

    template 'group' => {
        group => $group,
        sites => [rset('Site')->ordered_org->all],
        page  => 'group'
    };
};

get '/groups/?' => require_login sub {

    var('login')->is_admin
        or error "You do not have access to this page";

    template 'groups' => {
        groups => [rset('Group')->ordered],
        page   => 'groups'
    };
};

any ['get', 'post'] => '/contractor/?:id?' => require_login sub {

    var('login')->is_admin
        or forwardHome({ danger => 'You do not have permission to view contractors' });

    my $contractor;
    my $id = route_parameters->get('id');
    if (defined $id)
    {
        $contractor = rset('Contractor')->find($id) || rset('Contractor')->new({});
    }

    if (body_parameters->get('delete'))
    {
        if (process (sub { $contractor->delete } ) )
        {
            forwardHome({ success => 'Contractor has been successfully deleted' }, 'contractor');
        }
    }

    if (body_parameters->get('submit'))
    {
        $contractor->name(body_parameters->get('name'));
        if (process sub { $contractor->update_or_insert })
        {
            forwardHome({ success => 'Contractor has been successfully added' }, 'contractor');
        }
    }

    template 'contractor' => {
        id          => $id,
        contractor  => $contractor,
        contractors => [rset('Contractor')->ordered],
        page        => 'contractor'
    };
};

any ['get', 'post'] => '/notice/?:id?' => require_login sub {

    var('login')->is_admin
        or forwardHome({ danger => 'You do not have permission to view notice settings' });

    my $id = route_parameters->get('id');
    my $notice = defined $id && (rset('Notice')->find($id) || rset('Notice')->new({}));

    if (body_parameters->get('delete'))
    {
        if (process (sub { $notice->delete } ) )
        {
            forwardHome({ success => 'The notice has been successfully deleted' }, 'notice');
        }
    }

    if (body_parameters->get('submit'))
    {
        $notice->text(body_parameters->get('text'));
        if (process sub { $notice->update_or_insert })
        {
            forwardHome({ success => 'The notice has been successfully created' }, 'notice');
        }
    }

    template 'notice' => {
        id      => $id,
        notice  => $notice,
        notices => [rset('Notice')->all_with_count],
        page    => 'notice'
    };
};
 
any ['get', 'post'] => '/check_edit/:id' => require_login sub {

    my $id = route_parameters->get('id');

    my $check = ($id && rset('Task')->find($id)) || rset('Task')->new({ site_check => 1, global => 0 });

    my $site_id = ($check && ($check->site_tasks)[0] && ($check->site_tasks)[0]->site_id) || body_parameters->get('site_id');
    error "You do not have access to this check"
        if $id && !var('login')->has_site($site_id);

    if (body_parameters->get('submitcheck') && !$check->deleted)
    {
        $check->name(body_parameters->get('name'));
        $check->description(body_parameters->get('description'));
        $check->period_qty(body_parameters->get('period_qty'));
        $check->period_unit(body_parameters->get('period_unit'));
        $check->set_site_id(body_parameters->get('site_id'));
        if (process sub { $check->update_or_insert })
        {
            forwardHome(
                { success => 'The site check has been successfully updated' }, 'task' );
        }
    }

    if (body_parameters->get('submit_name'))
    {
        my $checkitem = body_parameters->get('checkitemid')
            ? rset('CheckItem')->find(body_parameters->get('checkitemid'))
            : rset('CheckItem')->create({ task_id => $id });
        error "You do not have access to this check item"
            if body_parameters->get('checkitemid') && $checkitem->task->id != $check->id;
        $checkitem->name(body_parameters->get('checkitem'));
        if (process sub { $checkitem->insert_or_update } )
        {
            my $status = body_parameters->get('checkitemid') ? 'updated' : 'added';
            forwardHome(
                { success => "The check item has been $status successfully" }, "check_edit/$id" );
        }
    }

    if (body_parameters->get('submit_options'))
    {
        my $checkitem = rset('CheckItem')->find(body_parameters->get('checkitemid'));
        error "You do not have access to this check item"
            if body_parameters->get('checkitemid') && $checkitem->task->id != $check->id;
        if (process sub {
            $checkitem->update({ has_custom_options => body_parameters->get('has_custom_options') ? 1 : 0 });
            my @options = body_parameters->get_all('check_option');
            # options are id followed by name
            my %existing;
            while (@options)
            {
                my $option_id = shift @options;
                my $option_name = shift @options;
                next if !$option_id && !$option_name;
                my $option = $option_id
                    ? rset('CheckItemOption')->find($option_id)
                    : rset('CheckItemOption')->new({ check_item_id => $checkitem->id });
                error "You do not have access to this check item option"
                    if $option_id && $option->check_item_id != $checkitem->id;
                $option->name($option_name);
                $option->insert_or_update;
                $existing{$option->id} = 1;
            }
            foreach my $ci (rset('CheckItemOption')->search({ check_item_id => $checkitem->id })->all)
            {
                $ci->update({ is_deleted => 1 }) if !$existing{$ci->id};
            }
        })
        {
            forwardHome(
                { success => "The check item has been updated successfully" }, "check_edit/$id" );
        }
    }

    if (body_parameters->get('delete'))
    {
        if (process sub { $check->update({ deleted => DateTime->now }) })
        {
            forwardHome(
                { success => 'The check has been successfully deleted' }, 'task' );
        }
    }

    template 'check_edit' => {
        check       => $check,
        site_id     => session('site_id'),
        page        => 'check_edit'
    };
};

get '/checks/?' => require_login sub {

    my $site_id = session 'site_id'
        or error __"Please select a single site before viewing site checks";

    template 'checks' => {
        site        => rset('Site')->find(session 'site_id'),
        site_checks => [rset('Task')->site_checks($site_id)],
        dateformat  => config->{lenio}->{dateformat},
        page        => 'check',
    };
};

any ['get', 'post'] => '/check/?:task_id?/?:check_done_id?/?' => require_login sub {

    my $task_id       = route_parameters->get('task_id');
    my $check_done_id = route_parameters->get('check_done_id');
    my $check         = rset('Task')->find($task_id);

    my $site_id = session 'site_id'
        or error __"Please select a single site before viewing site checks";

    my $check_done = $check_done_id ? rset('CheckDone')->find($check_done_id) : rset('CheckDone')->new({});

    my $check_site_id = ($check->site_tasks)[0]->site_id;
    error "You do not have access to this check"
        unless var('login')->has_site($check_site_id);

    if (body_parameters->get('submit_check_done'))
    {
        my $site_task_id = $check_done_id ? $check_done->site_task_id : rset('SiteTask')->search({
            task_id => $task_id,
            site_id => $site_id,
        })->next->id;
        # Log the completion of a site check
        # Check user has permission first
        error __x"You do not have permission for site ID {id}", id => $site_id
            unless var('login')->has_site_task( $site_task_id );

        my $datetime = _to_dt(body_parameters->get('completed')) || DateTime->now;

        $check_done->datetime($datetime);
        $check_done->comment(body_parameters->get('comment'));
        $check_done->site_task_id($site_task_id);
        $check_done->login_id(var('login')->id);
        $check_done->update_or_insert;

        my $params = body_parameters;
        foreach my $key (keys %$params)
        {
            next unless $key =~ /^item([0-9]+)/;
            my $check_item_id = $1;
            my $check_item = rset('CheckItem')->find($check_item_id)
                or error "Check item not found";
            $check_item->task_id == $check->id
                or error "Check item is not valid";
            if ($check_item->has_custom_options)
            {
                my $check_item_done = rset('CheckItemDone')->update_or_create({
                    check_item_id => $check_item_id,
                    check_done_id => $check_done->id,
                    status_custom => param("item$check_item_id") || undef,
                });
            }
            else {
                my $check_item_done = rset('CheckItemDone')->update_or_create({
                    check_item_id => $check_item_id,
                    check_done_id => $check_done->id,
                    status        => param("item$check_item_id") || undef,
                });
            }
        }
        forwardHome({ success => "Check has been recorded successfully" }, 'checks');
    }

    template 'check' => {
        check       => rset('Task')->find($task_id),
        check_done  => $check_done,
        dateformat  => config->{lenio}->{dateformat},
        page        => 'check',
    };
};

get '/ticket/view/:id?' => require_login sub {
    my $id = route_parameters->get('id');
    redirect '/ticket'
        unless $id =~ /^[0-9]+$/;
    redirect "/ticket/$id";
};

any ['get', 'post'] => '/ticket/:id?' => require_login sub {

    my $date    = query_parameters->get('date');
    my $id      = route_parameters->get('id');

    # Check for comment deletion
    if (my $comment_id = body_parameters->get('delete_comment'))
    {
        error "You do not have access to delete comments"
            unless var('login')->is_admin;
        if (my $comment = rset('Comment')->find($comment_id))
        {
            my $ticket_id = $comment->ticket_id;
            if (process sub { $comment->delete })
            {
                forwardHome({ success => "Comment has been successfully deleted" }, "ticket/$ticket_id");
            }
        }
        else {
            error "Comment id {id} not found", id => $comment_id;
        }
    }

    # task_id can be specified in posted form or prefilled in ticket url
    my $task;
    if (my $task_id = body_parameters->get('task_id') || query_parameters->get('task_id'))
    {
        $task = rset('Task')->find($task_id);
    }

    my $ticket;
    if (defined($id) && $id)
    {
        $ticket = rset('Ticket')->find($id)
            or error __x"Ticket ID {id} not found", id => $id;
        # Check whether the user has access to this ticket
        error __x"You do not have permission for ticket ID {id}", id => $id
            unless var('login')->has_site($ticket->site_id);
        # Existing ticket, get task from DB
        $task = $ticket->task;
    }
    elsif (defined($id) && !body_parameters->get('submit'))
    {
        my $site_id = query_parameters->get('site_id')
            ? int(query_parameters->get('site_id'))
            : session('site_id');
        # If applicable, Prefill ticket fields with initial values based on task
        if ($task)
        {
            my $sid  = $task->site_task_local && $task->site_task_local->site_id; # site_id associated with local task
            # See if the user has permission to view associated task
            if ( var('login')->is_admin
                || (!$task->global && var('login')->has_site($sid))
            ) {
                $ticket = rset('Ticket')->new({
                    name        => $task->name,
                    description => $task->description,
                    planned     => $date,
                    actionee    => $task->global ? 'external' : 'local',
                    task_id     => $task->id,
                    site_id     => $site_id,
                });
            }
        }
        else {
            $ticket = rset('Ticket')->new({
                site_id => $site_id,
            });
        }
    }
    elsif (defined($id))
    {
        # New ticket submitted, create base object to be updated
        $ticket = rset('Ticket')->new({
            created_by => logged_in_user->{id},
            created_at => DateTime->now,
        });
    }

    if ( body_parameters->get('attach') ) {
        my $upload = request->upload('newattach')
            or error __"Please select a file to upload";
        my $attach = {
            name        => $upload->basename,
            ticket_id   => $id,
            upload      => $upload,
            mimetype    => $upload->type,
        };

        if (process sub { rset('Attach')->create_with_file($attach) })
        {
            my $args = {
                login    => var('login'),
                template => 'ticket/attach',
                ticket   => $ticket,
                url      => "/ticket/".$ticket->id,
                subject  => "Ticket ".$ticket->id." attachment added - ",
                attach   => {
                    data      => $upload->content,
                    mime_type => $upload->type,
                },
            };
            my $email = Lenio::Email->new(
                config   => config,
                schema   => schema,
                uri_base => request->uri_base,
                site     => $ticket->site, # rset('Site')->find(param 'site_id'),
            );
            $email->send($args);
            success __"File has been added successfully";
        }
    }

    if ( body_parameters->get('attachrm') ) {
        error __"You do not have permission to delete attachments"
            unless var('login')->is_admin;

        if (process sub { rset('Attach')->find(body_parameters->get('attachrm'))->delete })
        {
            success __"Attachment has been deleted successfully";
        }
    }

    if (body_parameters->get('delete'))
    {
        error __"You do not have permission to delete this ticket"
            unless var('login')->is_admin || $ticket->actionee eq 'local';
        if (process sub { $ticket->delete })
        {
            forwardHome({ success => "Ticket has been successfully deleted" }, 'tickets');
        }
    }

    if (body_parameters->get('cancel_ticket'))
    {
        error __"You do not have permission to cancel this ticket"
            unless var('login')->is_admin || $ticket->actionee eq 'local';
        if (process sub { $ticket->update({ cancelled => DateTime->now }) })
        {
            forwardHome({ success => "Ticket has been successfully cancelled" }, 'tickets');
        }
    }

    # Comment can be added on ticket creation or separately.  Create the
    # object, which will be added at ticket insertion time or otherwise later.
    my $comment = body_parameters->get('comment')
        && rset('Comment')->new({
            text      => body_parameters->get('comment'),
            login_id  => var('login')->id,
            datetime  => DateTime->now,
        });

    if (body_parameters->get('submit'))
    {
        # Find out if this is related to locally created task.
        # If so, allow dates to be input
        my $global = $task && $task->global;

        my $completed   = (var('login')->is_admin || !$global) && _to_dt(param('completed'));
        my $planned     = (var('login')->is_admin || !$global) && _to_dt(param('planned'));
        my $provisional = (var('login')->is_admin || !$global) && _to_dt(param('provisional'));

        $ticket->name(body_parameters->get('name'));
        $ticket->description(body_parameters->get('description'));
        $ticket->contractor_invoice(body_parameters->get('contractor_invoice'));
        $ticket->contractor_id(body_parameters->get('contractor') || undef);
        $ticket->cost_planned(body_parameters->get('cost_planned') || undef);
        $ticket->cost_actual(body_parameters->get('cost_actual') || undef);
        $ticket->actionee(body_parameters->get('actionee'));
        $ticket->report_received(body_parameters->get('report_received') ? 1 : 0);
        $ticket->invoice_sent(body_parameters->get('invoice_sent') ? 1 : 0);
        $ticket->completed($completed);
        $ticket->planned($planned);
        $ticket->provisional($provisional);
        $ticket->task_id($task && $task->id);
        $ticket->site_id(body_parameters->get('site_id'));

        # A normal user cannot edit a ticket that has already been created,
        # unless it is related to a locally created task
        if ($id)
        {
            error __"You do not have permission to edit this ticket"
                unless var('login')->is_admin || $ticket->actionee eq 'local';
        }
        else {
            error __"You do not have permission to create a service item ticket"
                if $global && !var('login')->is_admin;
        }

        my $was_local = $id && $ticket->actionee eq 'local'; # Need old setting to see if to send email
        if (process sub { $ticket->update_or_insert })
        {
            # XXX Ideally the comment would be written as a relationship
            # at the same time as the ticket, but I couldn't get it to
            # work ($ticket->comments([ .. ]) appears to do nothing)
            if ($comment)
            {
                $comment->ticket_id($ticket->id);
                $comment->insert;
            }
            my $template; my $subject; my $status;
            if ($id)
            {
                $template = 'ticket/update';
                $subject  = "Ticket ".$ticket->id." updated - ";
                $status   = 'updated';
            }
            else {
                $template = 'ticket/new';
                $subject  = "New ticket ID ".$ticket->id." - ";
                $status   = 'created';
            }
            my $args = {
                login       => var('login'),
                template    => $template,
                ticket      => $ticket,
                url         => "/ticket/".$ticket->id,
                subject     => $subject,
            };
            # Assume send update to admin
            my $send_email = 1;
            # Do not send email update if new ticket and local, or was local and still is local only
            $send_email = 0 if ((!$id && $ticket->actionee eq 'local') || ($id && $ticket->actionee eq 'local' && $was_local));
            # Do not send email if local site task
            $send_email = 0 if $task && !$task->global;
            if ($send_email)
            {
                my $email = Lenio::Email->new(
                    config   => config,
                    schema   => schema,
                    uri_base => request->uri_base,
                    site     => $ticket->site,
                );
                $email->send($args);
            }
            forwardHome(
                { success => "Ticket ".$ticket->id." has been successfully $status" }, 'ticket/'.$ticket->id );
        }
    }

    if (my $submit = body_parameters->get('addcomment'))
    {
        $comment->ticket_id($ticket->id);
        if ($submit eq 'private' && var('login')->is_admin)
        {
            $comment->admin_only(1);
            if (process sub { $comment->insert })
            {
                forwardHome(
                    { success => "Comment has been added successfully" }, 'ticket/'.$ticket->id );
            }
        }
        else {
            if (process sub { $comment->insert })
            {
                my $args = {
                    login       => var('login'),
                    template    => 'ticket/comment',
                    url         => "/ticket/$id",
                    ticket      => $ticket,
                    subject     => "Ticket ".$ticket->id." updated - ",
                    comment     => body_parameters->get('comment'),
                };
                my $email = Lenio::Email->new(
                    config   => config,
                    schema   => schema,
                    uri_base => request->uri_base,
                    site     => $ticket->site,
                );
                $email->send($args);
                forwardHome(
                    { success => "Comment has been added successfully" }, 'ticket/'.$ticket->id );
            }
        }
    }

    template 'ticket' => {
        id           => $id,
        ticket       => $ticket,
        contractors  => [rset('Contractor')->ordered],
        dateformat   => config->{lenio}->{dateformat},
        page         => 'ticket'
    };
};

get '/tickets/?' => require_login sub {

    # Deal with sort options
    if (query_parameters->get('sort'))
    {
        if (my $order = query_parameters->get('order'))
        {
            session ticket_desc => $order eq 'desc' ? 1 : 0;
        }
        else {
            session ticket_desc => session('ticket_sort') && session('ticket_sort') eq query_parameters->get('sort') ? !session('ticket_desc') : 0;
        }
        session ticket_sort => query_parameters->get('sort');
    }

    # Set filtering of tickets based on drop-down
    my $ticket_filter = session 'ticket_filter';

    my $filter_names = {
        reactive        => {
            url   => 'reactive',
            group => 'type',
            name  => 'Reactive tickets',
        },
        task            => {
            url   => 'task',
            group => 'type',
            name  => 'Tickets for services',
        },
        not_planned     => {
            url   => 'not-planned',
            group => 'status',
            name  => 'Not planned',
        },
        planned         => {
            url   => 'planned',
            group => 'status',
            name  => 'Planned but not completed',
        },
        completed       => {
            url   => 'completed',
            group => 'status',
            name  => 'Completed',
        },
        cancelled       => {
            url   => 'cancelled',
            group => 'status',
            name  => 'Cancelled',
        },
        admin           => {
            url   => 'admin',
            group => 'actionee',
            name  => 'Action currently on '.config->{lenio}->{invoice}->{company},
        },
        contractor      => {
            url   => 'contractor',
            group => 'actionee',
            name  => 'Action on contractor',
        },
        local_action    => {
            url   => 'local-action',
            group => 'actionee',
            name  => 'Action currently with site',
        },
        local_site      => {
            url   => 'local-site',
            group => 'actionee',
            name  => 'To be rectified in-house',
        },
        this_month      => {
            url   => 'this-month',
            group => 'dates',
            name  => 'This month',
        },
        next_month      => {
            url   => 'next-month',
            group => 'dates',
            name  => 'Next month',
        },
        this_fy         => {
            url   => 'this-fy',
            group => 'dates',
            name  => 'This financial year',
        },
        blank           => {
            url   => 'blank',
            group => 'dates',
            name  => 'Dates blank',
        },
        no_invoice      => {
            url   => 'no-invoice',
            group => 'ir',
            name  => 'Tickets without invoice',
        },
        no_invoice_sent => {
            url   => 'no-invoice-sent',
            group => 'ir',
            name  => 'Tickets without invoice sent',
        },
        no_report       => {
            url   => 'no-report',
            group => 'ir',
            name  => 'Tickets without report',
        },
    };

    if (defined query_parameters->get('filter-type'))
    {
        if (my $tt = query_parameters->get('filter-type'))
        {
            $ticket_filter->{type}->{reactive} = !!query_parameters->get('set')
                if $tt eq 'reactive';
            $ticket_filter->{type}->{task} = !!query_parameters->get('set')
                if $tt eq 'task';
        }
        else {
            # Clear
            delete $ticket_filter->{type};
        }
    }

    if (defined query_parameters->get('filter-status'))
    {
        if (my $tt = query_parameters->get('filter-status'))
        {
            $ticket_filter->{status}->{not_planned} = !!query_parameters->get('set')
                if $tt eq 'not-planned';
            $ticket_filter->{status}->{planned} = !!query_parameters->get('set')
                if $tt eq 'planned';
            $ticket_filter->{status}->{completed} = !!query_parameters->get('set')
                if $tt eq 'completed';
            $ticket_filter->{status}->{cancelled} = !!query_parameters->get('set')
                if $tt eq 'cancelled';
        }
        else {
            # Clear
            delete $ticket_filter->{status};
        }
    }

    if (defined query_parameters->get('filter-actionee'))
    {
        if (my $tt = query_parameters->get('filter-actionee'))
        {
            $ticket_filter->{actionee}->{admin} = !!query_parameters->get('set')
                if $tt eq 'admin';
            $ticket_filter->{actionee}->{contractor} = !!query_parameters->get('set')
                if $tt eq 'contractor';
            $ticket_filter->{actionee}->{local_action} = !!query_parameters->get('set')
                if $tt eq 'local-action';
            $ticket_filter->{actionee}->{local_site} = !!query_parameters->get('set')
                if $tt eq 'local-site';
        }
        else {
            # Clear
            delete $ticket_filter->{actionee};
        }
    }

    if (defined query_parameters->get('filter-dates'))
    {
        if (my $tt = query_parameters->get('filter-dates'))
        {
            $ticket_filter->{dates}->{this_month} = !!query_parameters->get('set')
                if $tt eq 'this-month';
            $ticket_filter->{dates}->{next_month} = !!query_parameters->get('set')
                if $tt eq 'next-month';
            $ticket_filter->{dates}->{this_fy} = !!query_parameters->get('set')
                if $tt eq 'this-fy';
            $ticket_filter->{dates}->{blank} = !!query_parameters->get('set')
                if $tt eq 'blank';
        }
        else {
            # Clear
            delete $ticket_filter->{dates};
        }
    }

    if (defined query_parameters->get('filter-ir'))
    {
        if (my $tt = query_parameters->get('filter-ir'))
        {
            $ticket_filter->{ir}->{no_invoice} = !!query_parameters->get('set')
                if $tt eq 'no-invoice';
            $ticket_filter->{ir}->{no_invoice_sent} = !!query_parameters->get('set')
                if $tt eq 'no-invoice-sent';
            $ticket_filter->{ir}->{no_report} = !!query_parameters->get('set')
                if $tt eq 'no-report';
        }
        else {
            # Clear
            delete $ticket_filter->{ir};
        }
    }

    session ticket_filter => $ticket_filter;

    if (defined query_parameters->get('task_id'))
    {
        if (my $task_id = query_parameters->get('task_id'))
        {
            session task_id => $task_id
                if $task_id =~ /^[0-9]+$/;
        }
        else {
            session task_id => undef;
        }
    }

    my $task = session('task_id') && rset('Task')->find(session('task_id'));

    my @tickets = rset('Ticket')->summary(
        login     => var('login'),
        site_id   => query_parameters->get('site_id') || var('site_ids'),
        sort      => session('ticket_sort'),
        sort_desc => session('ticket_desc'),
        task_id   => $task && $task->id,
        filter    => $ticket_filter,
    );

    my @selected_filters;
    foreach my $type (keys %$ticket_filter)
    {
        foreach my $name (keys %{$ticket_filter->{$type}})
        {
            push @selected_filters, $name if $ticket_filter->{$type}->{$name};
        }
    };

    template 'tickets' => {
        task             => $task, # Tickets related to task
        site_tasks       => [rset('Task')->site_tasks_grouped(site_ids => var('site_ids'))],
        tickets          => \@tickets,
        sort             => session('ticket_sort'),
        sort_desc        => session('ticket_desc'),
        dateformat       => config->{lenio}->{dateformat},
        ticket_filter    => session('ticket_filter'),
        selected_filters => \@selected_filters,
        filter_names     => $filter_names,
        page             => 'ticket'
    };
};

get '/attach/:file' => require_login sub {
    my $file = rset('Attach')->find(route_parameters->get('file'))
        or error __x"File ID {id} not found", id => route_parameters->get('file');
    my $site_id = $file->ticket->site_id;
    if ( var('login')->has_site($site_id))
    {
        send_file( $file->content, content_type => $file->mimetype, system_path => 1 );
    } else {
        forwardHome(
            { danger => 'You do not have permission to view this file' } );
    }
};

any ['get', 'post'] => '/invoice/:id' => require_login sub {

    my $id      = route_parameters->get('id');
    my $invoice = defined $id && (rset('Invoice')->find($id) || rset('Invoice')->new({}));
    my $ticket  = query_parameters->get('ticket') && rset('Ticket')->find(query_parameters->get('ticket'));

    if (defined query_parameters->get('download'))
    {
        my %options = %{config->{lenio}->{invoice}};
        $options{dateformat} = config->{lenio}->{dateformat};
        my $pdf = $invoice->pdf(%options);
	return send_file(
	    \$pdf,
	    content_type => 'application/pdf',
	    filename     => (config->{lenio}->{invoice}->{prefix}).$invoice->id.".pdf",
	);
    }

    var('login')->is_admin
        or forwardHome({ danger => 'You do not have permission to edit invoices' });

    if (body_parameters->get('delete'))
    {
        if (process (sub { $invoice->delete } ) )
        {
            forwardHome({ success => 'The invoice has been successfully deleted' }, 'invoices');
        }
    }

    if (body_parameters->get('submit'))
    {
        $ticket or error __"No ticket specified to create the invoice for";
        $invoice->description(body_parameters->get('description'));
        $invoice->number(body_parameters->get('number'));
        $invoice->disbursements(body_parameters->get('disbursements') || undef);
        $invoice->ticket_id($ticket->id);
        $invoice->datetime(DateTime->now)
            if !$id;
        if (process sub { $invoice->update_or_insert })
        {
            # Email new invoice to users
            my %options = %{config->{lenio}->{invoice}};
            $options{dateformat} = config->{lenio}->{dateformat};
            my $pdf = $invoice->pdf(%options);
            my $args = {
                login    => var('login'),
                template => 'ticket/invoice',
                ticket   => $ticket,
                url      => "/ticket/".$ticket->id,
                subject  => "Ticket ".$ticket->id." invoice added - ",
                attach   => {
                    data      => $pdf,
                    mime_type => 'application/pdf',
                },
            };
            my $email = Lenio::Email->new(
                config   => config,
                schema   => schema,
                uri_base => request->uri_base,
                site     => $ticket->site, # rset('Site')->find(param 'site_id'),
            );
            $email->send($args);

            my $action = $id ? 'updated' : 'created';
            $id = $invoice->id;
            forwardHome({ success => "The invoice has been successfully $action" }, "invoice/$id");
        }

    }

    template 'invoice' => {
        id      => $id,
        invoice => $invoice,
        ticket  => $ticket,
        page    => 'invoice'
    };
};

get '/invoices' => require_login sub {

    if (query_parameters->get('sort'))
    {
        session invoice_desc => session('invoice_sort') && session('invoice_sort') eq query_parameters->get('sort') ? !session('invoice_desc') : 0;
        session invoice_sort => query_parameters->get('sort');
    }

    my @invoices = rset('Invoice')->summary(
        login     => var('login'),
        site_id   => var('site_ids'),
        sort      => session('invoice_sort'),
        sort_desc => session('invoice_desc'),
    );

    template 'invoices' => {
        invoices => \@invoices,
        page     => 'invoice'
    };
};

any ['get', 'post'] => '/task/?:id?' => require_login sub {

    my $action;
    my $id = route_parameters->get('id');

    if (var('login')->is_admin)
    {
        session('site_id') or error "Please select a single site first";
        if (body_parameters->get('taskadd'))
        {
            rset('SiteTask')->find_or_create({ task_id => body_parameters->get('taskadd'), site_id => session('site_id') });
        }
        if (body_parameters->get('taskrm'))
        {
            rset('SiteTask')->search({ task_id => body_parameters->get('taskrm'), site_id => session('site_id') })->delete;
        }
    }

    my $task = defined($id) && ($id && rset('Task')->find($id) || rset('Task')->new({}));

    my @tasks; my @tasks_local; my @adhocs;

    if ($task && $task->id)
    {
        # Check whether the user has access to this task
        my @sites = map { $_->site_id } $task->site_tasks->all;
        forwardHome(
            { danger => "You do not have permission for service item $id" } )
                unless var('login')->is_admin || (!$task->global && var('login')->has_site(@sites));
    }

    if (body_parameters->get('delete'))
    {
        my $site_id = $task->site_task_local && $task->site_task_local->site_id; # Site ID for local tasks
        if (var('login')->is_admin)
        {
            if (process sub { $task->delete })
            {
                    forwardHome({ success => 'Service item has been successfully deleted' }, 'task' );
            }
        }
        elsif (var('login')->has_site($site_id))
        {
            if (process sub { $task->delete })
            {
                    forwardHome({ success => 'Service item has been successfully deleted' }, 'task' );
            }
        }
        else {
            error __x"You do not have permission to delete task ID {id}", id => $id;
        }
    }

    if ( var('login')->is_admin && body_parameters->get('tasktype_add') )
    {
        if (process sub { rset('Tasktype')->create({name => body_parameters->get('tasktype_name')}) })
        {
            forwardHome(
                { success => 'Task type has been added' }, "task/$id" );
        }
    }

    my $download = {
        default_from => DateTime->now->subtract(months => 1),
        default_to   => DateTime->now,
    };
    if (body_parameters->get('download_site_checks'))
    {
        session('site_id') or error "Please select a single site first";
        my $from = _to_dt(body_parameters->get('download_from') || $download->{default_from});
        my $to = _to_dt(body_parameters->get('download_to') || $download->{default_to});
        my $csv = rset('CheckDone')->summary_csv(
            from       => $from,
            to         => $to,
            site_id    => session('site_id'),
            dateformat => $dateformat,
        );

        my $site = rset('Site')->find(session 'site_id')->org->name;
        utf8::encode($csv);
        return send_file(
            \$csv,
            content_type => 'text/csv; chrset="utf-8"',
            filename     => "$site site checks ".$from->strftime($dateformat)." to ".$to->strftime($dateformat).".csv"
        );
    }

    if (body_parameters->get('populate'))
    {
        session('site_id') or error "Please select a single site first";
        my $year = body_parameters->get('populate_from');
        if (process sub { rset('Task')->populate_tickets(
                site_id  => session('site_id'),
                from     => $year,
                to       => session('fy'),
                login_id => var('login')->id,
            ) })
        {
            forwardHome({ success => 'Tickets have been populated successfully' }, 'task' );
        }
    }

    if ( body_parameters->get('submit') )
    {
        session('site_id') or error "Please select a single site first";
        if (var('login')->is_admin)
        {
            $task->global(1);
        }
        else
        {
            $task->set_site_id(session('site_id'));
            $task->global(0);
        }

        $task->name(body_parameters->get('name'));
        $task->description(body_parameters->get('description'));
        $task->contractor_requirements(body_parameters->get('contractor_requirements'));
        $task->evidence_required(body_parameters->get('evidence_required'));
        $task->statutory(body_parameters->get('statutory'));
        $task->tasktype_id(body_parameters->get('tasktype_id') || undef); # Fix empty string from form
        $task->period_qty(body_parameters->get('period_qty'));
        $task->period_unit(body_parameters->get('period_unit'));

        if (process sub { $task->update_or_insert })
        {
                forwardHome({ success => 'Service item has been successfully created' }, 'task' );
        }
    }

    else
    {
        session('site_id') or error "Please select a single site first";
        my $csv = (session('site_id') && query_parameters->get('csv')) || ""; # prevent warnings. not for all sites

        if ($csv eq 'service')
        {

            my $csvout = rset('Task')->csv(
                site_id    => session('site_id'),
                global     => 1,
                fy         => session('fy'),
                dateformat => $dateformat,
            );

            my $now = DateTime->now->ymd;
            my $site = rset('Site')->find(session 'site_id')->org->name;
            # XXX Is this correct? We can't send native utf-8 without getting the error
            # "Strings with code points over 0xFF may not be mapped into in-memory file handles".
            # So, encode the string (e.g. "\x{100}"  becomes "\xc4\x80) and then send it,
            # telling the browser it's utf-8
            utf8::encode($csvout);
            return send_file(
                \$csvout,
                content_type => 'text/csv; chrset="utf-8"',
                filename     => "$site service items $now.csv"
            );
        }

        if (var('login')->is_admin && query_parameters->get('sla') && query_parameters->get('sla') eq 'pdf')
        {
            session('site_id') or error "Please select a single site first";
            my $site = rset('Site')->find(session 'site_id');

            my $pdf = rset('Task')->sla(
                fy         => session('fy'),
                site       => $site,
                dateformat => $dateformat,
                %{config->{lenio}->{invoice}},
            );

            my $now = DateTime->now->ymd;
            return send_file(
                \$pdf->content,
                content_type => 'application/pdf',
                filename     => $site->org->name." Service Level Agreement $now.pdf"
            );
        }

        if (var('login')->is_admin && query_parameters->get('finsum') && query_parameters->get('finsum') eq 'pdf')
        {
            session('site_id') or error "Please select a single site first";
            my $site = rset('Site')->find(session 'site_id');

            my $pdf = rset('Task')->finsum(
                fy         => session('fy'),
                site       => $site,
                dateformat => $dateformat,
                %{config->{lenio}->{invoice}},
            );

            my $now = DateTime->now->ymd;
            return send_file(
                \$pdf->content,
                content_type => 'application/pdf',
                filename     => $site->org->name." Financial Summary $now.pdf"
            );
        }

        # Get all the global tasks.
        @tasks = rset('Task')->summary(site_id => var('site_ids'), global => 1, fy => session('fy'), onlysite => 1);

        # Get any adhoc tasks
        @adhocs = rset('Ticket')->summary(
            login        => var('login'),
            site_id      => var('site_ids'),
            task_tickets => 0,
            fy           => session('site_id') && session('fy'),
            filter       => {
                type => {
                    reactive => 1,
                },
                costs => {
                    actual => 1,
                },
            },
        ) if var('site_ids');
        if ($csv eq 'reactive')
        {
            my $csv = Text::CSV->new;
            my @headings = qw/title cost_planned cost_actual completed contractor/;
            $csv->combine(@headings);
            my $csvout = $csv->string."\n";
            my ($cost_planned_total, $cost_actual_total);
            foreach my $adhoc (@adhocs)
            {
                my @row = (
                    $adhoc->name,
                    $adhoc->cost_planned,
                    $adhoc->cost_actual,
                    $adhoc->completed && $adhoc->completed->strftime($dateformat),
                    $adhoc->contractor && $adhoc->contractor->name,
                );
                $csv->combine(@row);
                $csvout .= $csv->string."\n";
                $cost_planned_total += ($adhoc->cost_planned || 0);
                $cost_actual_total  += ($adhoc->cost_actual || 0);
            }
            $csv->combine('Totals:', sprintf("%.2f", $cost_planned_total), sprintf("%.2f", $cost_actual_total),'','');
            $csvout .= $csv->string."\n";
            my $now = DateTime->now->ymd;
            my $site = rset('Site')->find(session 'site_id')->org->name;
            utf8::encode($csvout); # See comment above
            return send_file(
                \$csvout,
                content_type => 'text/csv; chrset="utf-8"',
                filename     => "$site reactive $now.csv"
            );
        }
        # Get all the local tasks
        @tasks_local = rset('Task')->summary(site_id => var('site_ids'), global => 0, onlysite => 1, fy => session('fy'));
        $action = '';
    }

    my $show_populate = ! grep $_->get_column('cost_planned'), @tasks;

    template 'task' => {
        show_populate    => $show_populate,
        dateformat       => $dateformat,
        download         => $download,
        action           => $action,
        site             => rset('Site')->find(session 'site_id'),
        site_checks      => [rset('Task')->site_checks(session 'site_id')],
        task             => $task,
        tasks            => \@tasks,
        all_tasks        => [rset('Task')->global->all],
        tasks_local      => \@tasks_local,
        tasktypes        => [rset('Tasktype')->all],
        adhocs           => \@adhocs,
        page             => 'task'
    };
};

get '/data' => require_login sub {

    my $utc_offset = query_parameters->get('utc_offset') * -1; # Passed from calendar plugin as query parameter
    my $from  = DateTime->from_epoch( epoch => ( query_parameters->get('from') / 1000 ) )->add( minutes => $utc_offset );
    my $to    = DateTime->from_epoch( epoch => ( query_parameters->get('to') / 1000 ) )->add(minutes => $utc_offset );
    my $login = var('login');

    my @tasks;
    my @sites = rset('Site')->search({
        'me.id' => var('site_ids'),
    });
    foreach my $site (@sites) {
        my $calendar = Lenio::Calendar->new(
            from           => $from,
            to             => $to,
            site           => $site,
            multiple_sites => @sites > 1,
            contractors    => session('contractors'),
            login          => $login,
            schema         => schema,
            dateformat     => $dateformat,
        );
        push @tasks, $calendar->tasks;
        push @tasks, $calendar->checks
            if !$login->is_admin;
    }
    _send_json ({
        success => 1,
        result => \@tasks
    });
};

sub forwardHome {
    my ($message, $page, %options) = @_;

    if ($message)
    {
        my ($type) = keys %$message;
        my $lroptions = {};
        # Check for option to only display to user (e.g. passwords)
        $lroptions->{to} = 'error_handler' if $options{user_only};

        if ($type eq 'danger')
        {
            $lroptions->{is_fatal} = 0;
            report $lroptions, ERROR => $message->{$type};
        }
        else {
            report $lroptions, NOTICE => $message->{$type}, _class => 'success';
        }
    }
    $page ||= '';
    redirect "/$page";
}

sub _send_json
{   header "Cache-Control" => "max-age=0, must-revalidate, private";
    content_type 'application/json';
    encode_json(shift);
}

sub password_generator
{   $password_generator->xkcd( words => 3 );
}

sub _to_dt
{   my $parser = DateTime::Format::Strptime->new(
         pattern   => '%Y-%m-%d',
         time_zone => 'local',
    );
    $parser->parse_datetime(shift);
}

true;
