<!--
    This partial requires a few variables to render properly.

    First off, we can render either a new account form or an
    update account form.

    (1)
    To render an update account form, supply `form_type = "UpdateAccount"`.
    To render a new account form, either omit a `form_type` or set it to
    anything else (should actually be "NewAccount" based on the PHP impl).

    (2)
    Furthermore, when rendering an update form, if the request user
    is authenticated, there **must** be a `user` supplied, pointing
    to the user being edited.
-->
<form id="edit-profile-form" method="post" {% if action %}action="{{ action }}"{% endif %}>
    <fieldset>
        <input type="hidden" name="Action" value="{{ form_type }}">
    </fieldset>

    <fieldset>
        <!-- Username -->
        <div class="item">
		<label for="id_username">
            <h2>{% trans %}Username{% endtrans %} <em>({% trans %}required{% endtrans %})</em>:</h2>
        </label>

        <input id="id_username" type="text" maxlength="16" name="U" value="{{ username }}">
        <p class="comment">
            <em>{{ "Your user name is the name you will use to login. It is visible to the general public, even if your account is inactive." | tr }}</em>
        </p>
        </div>

        {% if request.user.has_credential(creds.ACCOUNT_CHANGE_TYPE) %}
            <div class="item">
                <label for="id_type">
                    <h2>{% trans %}Account Type{% endtrans %}:</h2>
                </label>
                <select name="T" id="id_type">
                    {% for value, type in account_types %}
                        <option value="{{ value }}" {% if user.AccountType.ID == value %}selected="selected"{% endif %}>
                            {{ type | tr }}
                        </option>
                    {% endfor %}
                </select>
            </div>

            <div class="item">
                <label for="id_suspended">
                    {% trans %}Account Suspended{% endtrans %}:
                </label>

                <input class="checkbox" id="suspended" type="checkbox" name="S" {% if suspended %}checked="checked"{% endif %}>
            </div>
        {% endif %}

        {% if request.user.has_credential(creds.ACCOUNT_EDIT, approved=[user]) %}
            <div class="item">
                <div>
                    <label for="id_inactive">{% trans %}Inactive{% endtrans %}:</label>
                    <input class="checkbox" id="id_inactive" type="checkbox" name="J" {% if inactive %}checked="checked"{% endif %}>
                </div>
            </div>
        {% endif %}

        <!-- Email -->
        <div class="item">
	    <label for="id_email"><h2>
                {% trans %}Email Address{% endtrans %} <em>({% trans %}required{% endtrans %})</em>:
            </h2></label>

            <input id="id_email" type="email" maxlength="254" name="E" value="{{ email or '' }}">
            <p class="comment"><em>{{ "Please ensure you correctly entered your email "
                      "address, otherwise you will be locked out." | tr }}</em></p>
        </div>

        <!-- Hide Email -->
        <div class="item">
            <div>
                <label for="id_hide">
                    {% trans %}Hide Email Address{% endtrans %}:
                </label>

                <input class="checkbox" id="id_hide" type="checkbox" name="H" value="on" {% if H or request.user.HideEmail == 1 %}checked="checked"{% endif %}>
            </div>
            <p class="comment"><em>{{ "If you do not hide your email address, it is "
            "visible to all registered MPR users. If you hide your "
            "email address, it is visible to MPR "
            "Trusted Users only." | tr }}</em></p>
        </div>

        <!-- Backup Email -->
        <div class="item">
            <label for="id_backup_email"><h2>
                {% trans %}Backup Email Address{% endtrans %}:
            </h4></label>

            <input id="id_backup_email" type="email" maxlength="254" name="BE" value="{{ backup_email or '' }}">
            <p class="comment"><em>
            {{ "Optionally provide a secondary email address that "
            "can be used to restore your account in case you lose "
            "access to your primary email address." | tr }}
            {{ "Password reset links are always sent to both your "
            "primary and your backup email address." | tr }}
            {{ "Your backup email address is always only visible to "
            "MPR Trusted Users, independent of the %s "
            "setting." | tr
            | format("<em>%s</em>" | format("Hide Email Address" | tr))
            | safe }}
            </em></p>
        </div>

        <!-- Real Name -->
        <div class="item">
            <label for="id_realname"><h2>
                {% trans %}Real Name{% endtrans %}:
            </h2></label>

            <input id="id_realname" type="text" maxlength="32" name="R" value="{{ realname }}">
        </div>

        <!-- Homepage -->
        <div class="item">
            <label for="id_homepage"><h2>
                {% trans %}Homepage{% endtrans %}:
            </h2></label>

            <input id="id_homepage" type="text" name="HP" value="{{ homepage }}">
        </div>

        <!-- IRC Nick -->
        <div class="item">
            <label for="id_irc"><h2>
                {% trans %}IRC Nick{% endtrans %}:
            </h2></label>

            <input id="id_irc" type="text" maxlength="32" name="I" value="{{ ircnick }}">
        </div>

        <!-- PGP Key Fingerprint -->
        <div class="item">
            <label for="id_pgp"><h2>
                {% trans %}PGP Key Fingerprint{% endtrans %}:
            </h2></label>

            <input id="id_pgp" type="text" maxlength="50" name="K" value="{{ pgp }}">
        </div>

        <hr>

        <!-- Homepage -->
        <div class="item">
            <label for="id_language"><h2>
                {% trans %}Language{% endtrans %}:
            </h2></label>

            <select id="id_language" name="L">
                {% for domain, display in languages.items() %}
                    <option
                        value="{{ domain }}"
                        {% if lang == domain %}
                        selected="selected"
                        {% endif %}
                    >
                        {{ display }}
                    </option>
                {% endfor %}
            </select>
        </div>

        <!-- Homepage -->
        <div class="item">
            <label for="id_timezone"><h2>
                {% trans %}Timezone{% endtrans %}
            </h2></label>

            <select id="id_timezone" name="TZ">
                {% for current, offset in timezones.items() %}
                    <option value="{{ current }}"
                        {% if current == tz  %}
                            selected="selected"
                        {% endif %}
                    >{{ offset }}</option>
                {% endfor %}
            </select>
        </div>

    </fieldset>
    <hr>

    {% if form_type == "UpdateAccount" %}
        <fieldset>
            <p class="overhead-comment">
            {{ "If you want to change the password, enter a new password "
               "and confirm the new password by entering it again." | tr
            }}
            </p>
            <div class="item">
                <label for="id_passwd1"><h2>
                    {% trans %}Password{% endtrans %}:
                </h2></label>
                <input id="id_passwd1" type="password" name="P" value="{{ P or '' }}">
            </div>

            <div class="item">
                <label for="id_passwd2"><h2>
                    {% trans %}Re-type password{% endtrans %}:
                </h2></label>

                <input id="id_passwd2" type="password" name="C" value="{{ C or '' }}">
            </div>
        </fieldset>
    {% endif %}
    <hr>
    <fieldset>
        <p class="overhead-comment">
            {{
            "The following information is only required if you "
            "want to submit packages to the makedeb Package Repository." | tr
            }}
        </p>
        <div class="item">
            <label for="id_ssh"><h2>
                {% trans %}SSH Public Key{% endtrans %}:
            </h2></label>

            <!-- Only set PK auto-fill when we've got a NewAccount form. -->
            <textarea id="id_ssh" name="PK"
                      rows="5">{{ ssh_pk }}</textarea>
        </div>
    </fieldset>
    <hr>
    <fieldset class="notify-item">
        <legend><h2>{% trans%}Notification settings{% endtrans %}:</h2></legend>
        <div class="item">
            <label for="id_commentnotify">
                {% trans %}Notify of new comments{% endtrans %}:
            </label>

            <input class="checkbox" id="id_commentnotify" type="checkbox" name="CN" {% if cn %}checked="checked"{% endif %}>
        </div>

        <div class="item">
            <label for="id_updatenotify">
                {% trans %}Notify of package updates{% endtrans %}:
            </label>

            <input class="checkbox" id="id_updatenotify" type="checkbox" name="UN" {% if un %}checked="checked"{% endif %}>
        </div>

        <div class="item">
            <label for="id_ownershipnotify">
                {% trans %}Notify of ownership updates{% endtrans %}:
            </label>

            <input class="checkbox" id="id_ownershipnotify" type="checkbox" name="ON" {% if on %}checked="checked"{% endif %}>
        </div>
    </fieldset>
    <hr>
    <fieldset class="confirm-password">
        {% if form_type == "UpdateAccount" %}
            <legend>
                {{ "To confirm the profile changes, please enter "
                "your current password:" | tr }}
            </legend>
            <div class="item">
                <label for="id_passwd_current"><h2>{% trans %}Your current password{% endtrans %}:</h2></label>
                <input id="id_passwd_current" type="password" name="passwd" id="id_passwd_current">
            </div>
        {% else %}
            <!-- Otherwise, form_type is assumed that it's NewAccount. -->
            <div class="item">
		        <p>{{ "To protect the MPR against automated account creation, "
                      "we kindly ask you to provide the output of the following "
		              "command" | tr }} <em>({% trans %}required{% endtrans %})</em>:</p>

                <div class="code-block">
                    {% include "partials/clipboard_icons.html" %}
                    <pre><code>{{ captcha_salt | captcha_cmdline }}</code></pre>
                </div>
                <div class="item">
                    <label for="id_captcha">
                        <h2>{% trans %}Answer{% endtrans %}:</h2>
                    </label>
                    <input id="id_captcha" type="text" maxlength="6" name="captcha">
                    <input type="hidden" name="captcha_salt" value="{{ captcha_salt }}">
                </div>
            </div>
        {% endif %}
    </fieldset>

    <fieldset>
        <div class="item">
        <label></label>
            {% if form_type == "UpdateAccount" %}
                <input class="button" type="submit"
                                      value="{{ 'Update' | tr }}"> &nbsp;
            {% else %}
                <input class="button" type="submit"
                                      value="{{ 'Create' | tr }}"> &nbsp;
            {% endif %}
            <input class="button" type="reset"
                                  value="{{ 'Reset' | tr }}">
        </div>
    </fieldset>
</form>

{# vim: set ts=4 sw=4 expandtab: #}
