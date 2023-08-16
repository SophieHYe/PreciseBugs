{% extends "partials/layout.html" %}
{% block body %}
    <div class="box">
	{% if not request.user.is_authenticated() %}
            <h2>{% trans %}Accounts{% endtrans %}</h2>
	    <hr>
	    {% trans %}You must log in to view user information.{% endtrans %}
	{% else %}
	    <h2>{% trans %}Accounts{% endtrans %} - {{ user.Username }}</h2>
	    <hr>
            <div class="arch-bio-entry">
		<div class="item">
		    <p class="caption">{% trans %}Username{% endtrans %}:</p>
		    <p class="data">{{ user.Username }}</p>
		</div>
		<div class="item">
		    <p class="caption">{% trans %}Account Type{% endtrans %}:</p>
		    <p class="data">{{ user.AccountType }}</p>
		</div>
		<div class="item">
		    <p class="caption">{% trans %}Email Address{% endtrans %}:</p>
                    {% if request.user != user and user.HideEmail == 1 %}
		    <p class="data">None</a></p>
                    {% else %}
		    <p class="data"><a href="mailto:{{ user.Email }}">{{ user.Email }}</a></p>
                    {% endif %}
		</div>
		<div class="item">
		    <p class="caption">{% trans %}Real Name{% endtrans %}:</p>
		    {% if user.Realname != "" %}
		        <p class="data">{{ user.Realname }}</p>
		    {% else %}
			<p class="data">None</p>
		    {% endif %}
		</div>
		<div class="item">
		    <p class="caption">{% trans %}Homepage{% endtrans %}:</p>
		    {% if user.Homepage %}
		        <p class="data"><a href="{{ user.Homepage }}" rel="nofollow">{{ user.Homepage }}</a></p>
		    {% else %}
			<p class="data">None</p>
		    {% endif %}
		</div>
		<div class="item">
		    <p class="caption">{% trans %}IRC Nick{% endtrans %}:</p>
		    {% if user.IRCNick %}
			<p class="data">{{ user.IRCNick }}</p>
		    {% else %}
			<p class="data">None</p>
		    {% endif %}
		</div>
		<div class="item">
		    <p class="caption">{% trans %}PGP Key Fingerprint{% endtrans %}:</p>
		    {% if pgp_key != "" %}
			<p class="data">{{ pgp_key }}</p>
		    {% else %}
			<p class="data">None</p>
		    {% endif %}
		</div>
		<div class="item">
		    <p class="caption">{% trans %}Status{% endtrans %}:</p>
		    {% if not user.InactivityTS %}
			<p class="data">{{ "Active" | tr }}</p>
		    {% else %}
		        {% set inactive_ds = user.InactivityTS | dt | as_timezone(timezone) %}
			<p class="data">{{ "Inactive since %s" | tr | format(inactive_ds.strftime("%Y-%m-%d %H:%M")) }}</p>
		    {% endif %}
		</div>
		<div class="item">
		    <p class="caption">{% trans %}Registration date{% endtrans %}:</p>
		    <p class="data">{{ user.RegistrationTS.strftime("%Y-%m-%d") }}</p>
		</div>
		{% if login_ts %}
		    <div class="item">
			<p class="caption">{% trans %}Last Login{% endtrans %}:</p>
			{% set login_ds = login_ts | dt | as_timezone(timezone) %}
			<p class="data">{{ login_ds.strftime("%Y-%m-%d") }}</p>
		    </div>
		{% endif %}
		<div class="item">
		    <p class="caption">{% trans %}Links{% endtrans %}:</p>
		    <div class="multidata">
			<p class="data">{{ "%sView this user's packages%s" | tr | format('<a href="/packages/?K=%s&SeB=m">' | format(user.Username), "</a>") | safe }}</p>
			{% if request.user.can_edit_user(user) %}
			    <p class="data">{{ "%sEdit this user's account%s" | tr | format('<a href="/account/%s/edit">' | format(user.Username), "</a>") | safe }}</p>
			{% endif %}
			{% if request.user.has_credential(creds.ACCOUNT_LIST_COMMENTS, approved=[user]) %}
			    <p class="data">{{ "%sList this user's comments%s" | tr | format('<a href="/account/%s/comments">' | format(user.Username), "</a>") | safe }}</p>
			{% endif %}
		    </div>
	        </div>
	    </div>
	{% endif %}
    </div>
{% endblock %}
</html>
{# vim: set sw=4 expandtab: #}
