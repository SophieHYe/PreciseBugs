/*
 * See the NOTICE file distributed with this work for additional
 * information regarding copyright ownership.
 *
 * This is free software; you can redistribute it and/or modify it
 * under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation; either version 2.1 of
 * the License, or (at your option) any later version.
 *
 * This software is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this software; if not, write to the Free
 * Software Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA
 * 02110-1301 USA, or see the FSF site: http://www.fsf.org.
 */
package org.xwiki.contrib.oidc.auth.internal;

import java.net.URI;
import java.net.URISyntaxException;
import java.net.URL;
import java.util.ArrayList;
import java.util.Arrays;
import java.util.Date;
import java.util.HashMap;
import java.util.HashSet;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;
import java.util.Set;

import javax.inject.Inject;
import javax.inject.Singleton;
import javax.servlet.http.HttpSession;

import org.apache.commons.collections4.CollectionUtils;
import org.apache.commons.lang3.StringUtils;
import org.joda.time.LocalDateTime;
import org.slf4j.Logger;
import org.xwiki.component.annotation.Component;
import org.xwiki.configuration.ConfigurationSource;
import org.xwiki.container.Container;
import org.xwiki.container.Request;
import org.xwiki.container.Session;
import org.xwiki.container.servlet.ServletSession;
import org.xwiki.contrib.oidc.OIDCIdToken;
import org.xwiki.contrib.oidc.OIDCUserInfo;
import org.xwiki.contrib.oidc.internal.OIDCConfiguration;
import org.xwiki.contrib.oidc.provider.internal.OIDCManager;
import org.xwiki.contrib.oidc.provider.internal.endpoint.AuthorizationOIDCEndpoint;
import org.xwiki.contrib.oidc.provider.internal.endpoint.LogoutOIDCEndpoint;
import org.xwiki.contrib.oidc.provider.internal.endpoint.TokenOIDCEndpoint;
import org.xwiki.contrib.oidc.provider.internal.endpoint.UserInfoOIDCEndpoint;
import org.xwiki.instance.InstanceIdManager;
import org.xwiki.properties.ConverterManager;

import com.nimbusds.oauth2.sdk.Scope;
import com.nimbusds.oauth2.sdk.auth.ClientAuthenticationMethod;
import com.nimbusds.oauth2.sdk.auth.Secret;
import com.nimbusds.oauth2.sdk.http.HTTPRequest;
import com.nimbusds.oauth2.sdk.id.ClientID;
import com.nimbusds.oauth2.sdk.token.BearerAccessToken;
import com.nimbusds.openid.connect.sdk.OIDCClaimsRequest;
import com.nimbusds.openid.connect.sdk.OIDCScopeValue;
import com.nimbusds.openid.connect.sdk.claims.ClaimsSetRequest;
import com.nimbusds.openid.connect.sdk.claims.IDTokenClaimsSet;

/**
 * Various OpenID Connect authenticator configurations.
 * 
 * @version $Id$
 */
@Component(roles = OIDCClientConfiguration.class)
@Singleton
public class OIDCClientConfiguration extends OIDCConfiguration
{
    public class GroupMapping
    {
        private final Map<String, Set<String>> xwikiMapping;

        private final Map<String, Set<String>> providerMapping;

        public GroupMapping(int size)
        {
            this.xwikiMapping = new HashMap<>(size);
            this.providerMapping = new HashMap<>(size);
        }

        public Set<String> fromXWiki(String xwikiGroup)
        {
            return this.xwikiMapping.get(xwikiGroup);
        }

        public Set<String> fromProvider(String providerGroup)
        {
            return this.providerMapping.get(providerGroup);
        }

        public Map<String, Set<String>> getXWikiMapping()
        {
            return this.xwikiMapping;
        }

        public Map<String, Set<String>> getProviderMapping()
        {
            return this.providerMapping;
        }
    }

    public static final String PROP_XWIKIPROVIDER = "oidc.xwikiprovider";

    public static final String PROP_USER_NAMEFORMATER = "oidc.user.nameFormater";

    public static final String DEFAULT_USER_NAMEFORMATER =
        "${oidc.issuer.host._clean}-${oidc.user.preferredUsername._clean}";

    /**
     * @since 1.11
     */
    public static final String PROP_USER_SUBJECTFORMATER = "oidc.user.subjectFormater";

    /**
     * @since 1.18
     */
    public static final String PROP_USER_MAPPING = "oidc.user.mapping";

    /**
     * @since 1.11
     */
    public static final String DEFAULT_USER_SUBJECTFORMATER = "${oidc.user.subject}";

    public static final String PROPPREFIX_ENDPOINT = "oidc.endpoint.";

    public static final String PROP_ENDPOINT_AUTHORIZATION = PROPPREFIX_ENDPOINT + AuthorizationOIDCEndpoint.HINT;

    public static final String PROP_ENDPOINT_TOKEN = PROPPREFIX_ENDPOINT + TokenOIDCEndpoint.HINT;

    public static final String PROP_ENDPOINT_USERINFO = PROPPREFIX_ENDPOINT + UserInfoOIDCEndpoint.HINT;

    /**
     * @since 1.21
     */
    public static final String PROP_ENDPOINT_LOGOUT = PROPPREFIX_ENDPOINT + LogoutOIDCEndpoint.HINT;

    public static final String PROP_CLIENTID = "oidc.clientid";

    /**
     * @since 1.13
     */
    public static final String PROP_SECRET = "oidc.secret";

    public static final String PROP_SKIPPED = "oidc.skipped";

    /**
     * @since 1.13
     */
    public static final String PROP_ENDPOINT_TOKEN_AUTH_METHOD =
        PROPPREFIX_ENDPOINT + TokenOIDCEndpoint.HINT + ".auth_method";

    /**
     * @since 1.13
     */
    public static final String PROP_ENDPOINT_USERINFO_METHOD =
        PROPPREFIX_ENDPOINT + UserInfoOIDCEndpoint.HINT + ".method";

    /**
     * @since 1.22
     */
    public static final String PROP_ENDPOINT_USERINFO_HEADERS =
        PROPPREFIX_ENDPOINT + UserInfoOIDCEndpoint.HINT + ".headers";

    /**
     * @since 1.21
     */
    public static final String PROP_ENDPOINT_LOGOUT_METHOD = PROPPREFIX_ENDPOINT + LogoutOIDCEndpoint.HINT + ".method";

    /**
     * @since 1.12
     */
    public static final String PROP_USERINFOREFRESHRATE = "oidc.userinforefreshrate";

    /**
     * @since 1.16
     */
    public static final String PROP_SCOPE = "oidc.scope";

    public static final String PROP_USERINFOCLAIMS = "oidc.userinfoclaims";

    public static final List<String> DEFAULT_USERINFOCLAIMS = Arrays.asList(OIDCUserInfo.CLAIM_XWIKI_ACCESSIBILITY,
        OIDCUserInfo.CLAIM_XWIKI_COMPANY, OIDCUserInfo.CLAIM_XWIKI_DISPLAYHIDDENDOCUMENTS,
        OIDCUserInfo.CLAIM_XWIKI_EDITOR, OIDCUserInfo.CLAIM_XWIKI_USERTYPE);

    public static final String PROP_IDTOKENCLAIMS = "oidc.idtokenclaims";

    public static final List<String> DEFAULT_IDTOKENCLAIMS = Arrays.asList(OIDCIdToken.CLAIM_XWIKI_INSTANCE_ID);

    /**
     * @since 1.10
     */
    public static final String PROP_GROUPS_MAPPING = "oidc.groups.mapping";

    /**
     * @since 1.10
     */
    public static final String PROP_GROUPS_ALLOWED = "oidc.groups.allowed";

    /**
     * @since 1.10
     */
    public static final String PROP_GROUPS_FORBIDDEN = "oidc.groups.forbidden";

    /**
     * @since 1.27
     */
    public static final String PROP_GROUPS_PREFIX = "oidc.groups.prefix";

    /**
     * @since 1.27
     */
    public static final String PROP_GROUPS_SEPARATOR = "oidc.groups.separator";

    public static final String PROP_INITIAL_REQUEST = "xwiki.initialRequest";

    public static final String PROP_STATE = "oidc.state";

    public static final String PROP_SESSION_ACCESSTOKEN = "oidc.accesstoken";

    public static final String PROP_SESSION_IDTOKEN = "oidc.idtoken";

    public static final String PROP_SESSION_USERINFO_EXPORATIONDATE = "oidc.session.userinfoexpirationdate";

    private static final String XWIKI_GROUP_PREFIX = "XWiki.";

    @Inject
    private InstanceIdManager instance;

    @Inject
    private OIDCManager manager;

    @Inject
    private Container container;

    @Inject
    private ConverterManager converter;

    @Inject
    private Logger logger;

    @Inject
    // TODO: store configuration in custom objects
    private ConfigurationSource configuration;

    private HttpSession getHttpSession()
    {
        Session session = this.container.getSession();
        if (session instanceof ServletSession) {
            HttpSession httpSession = ((ServletSession) session).getHttpSession();

            this.logger.debug("Session: {}", httpSession.getId());

            return httpSession;
        }

        return null;
    }

    private <T> T getSessionAttribute(String name)
    {
        HttpSession session = getHttpSession();
        if (session != null) {
            return (T) session.getAttribute(name);
        }

        return null;
    }

    private <T> T removeSessionAttribute(String name)
    {
        HttpSession session = getHttpSession();
        if (session != null) {
            try {
                return (T) session.getAttribute(name);
            } finally {
                session.removeAttribute(name);
            }
        }

        return null;
    }

    private void setSessionAttribute(String name, Object value)
    {
        HttpSession session = getHttpSession();
        if (session != null) {
            session.setAttribute(name, value);
        }
    }

    private String getRequestParameter(String key)
    {
        Request request = this.container.getRequest();
        if (request != null) {
            return (String) request.getProperty(key);
        }

        return null;
    }

    public Map<String, String> getMap(String key)
    {
        List<String> list = getProperty(key, List.class);

        Map<String, String> mapping;

        if (list != null && !list.isEmpty()) {
            mapping = new HashMap<>(list.size());

            for (String listItem : list) {
                int index = listItem.indexOf('=');

                if (index != -1) {
                    mapping.put(listItem.substring(0, index), listItem.substring(index + 1));
                }
            }
        } else {
            mapping = null;
        }

        return mapping;
    }

    @Override
    protected <T> T getProperty(String key, Class<T> valueClass)
    {
        // Get property from request
        String requestValue = getRequestParameter(key);
        if (requestValue != null) {
            return this.converter.convert(valueClass, requestValue);
        }

        // Get property from session
        T sessionValue = getSessionAttribute(key);
        if (sessionValue != null) {
            return sessionValue;
        }

        // Get property from configuration
        return this.configuration.getProperty(key, valueClass);
    }

    @Override
    protected <T> T getProperty(String key, T def)
    {
        // Get property from request
        String requestValue = getRequestParameter(key);
        if (requestValue != null) {
            return this.converter.convert(def.getClass(), requestValue);
        }

        // Get property from session
        T sessionValue = getSessionAttribute(key);
        if (sessionValue != null) {
            return sessionValue;
        }

        // Get property from configuration
        return this.configuration.getProperty(key, def);
    }

    /**
     * @since 1.18
     */
    public String getSubjectFormater()
    {
        String userFormatter = getProperty(PROP_USER_SUBJECTFORMATER, String.class);
        if (userFormatter == null) {
            userFormatter = DEFAULT_USER_SUBJECTFORMATER;
        }

        return userFormatter;
    }

    /**
     * @since 1.11
     */
    public String getXWikiUserNameFormater()
    {
        String userFormatter = getProperty(PROP_USER_NAMEFORMATER, String.class);
        if (userFormatter == null) {
            userFormatter = DEFAULT_USER_NAMEFORMATER;
        }

        return userFormatter;
    }

    /**
     * @since 1.18
     */
    public Map<String, String> getUserMapping()
    {
        return getMap(PROP_USER_MAPPING);
    }

    public URL getXWikiProvider()
    {
        return getProperty(PROP_XWIKIPROVIDER, URL.class);
    }

    private Endpoint getEndPoint(String hint) throws URISyntaxException
    {
        // TODO: use URI directly when upgrading to a version of XWiki providing a URI converter
        String uriString = getProperty(PROPPREFIX_ENDPOINT + hint, String.class);

        // If no direct endpoint is provider assume it's a XWiki OIDC provider and generate the endpoint from the hint
        URI uri;
        if (uriString == null) {
            if (getProperty(PROP_XWIKIPROVIDER, String.class) != null) {
                uri = this.manager.createEndPointURI(getXWikiProvider().toString(), hint);
            } else {
                uri = null;
            }
        } else {
            uri = new URI(uriString);
        }

        // If we still don't have any endpoint URI, return null
        if (uri == null) {
            return null;
        }

        // Find custom headers
        Map<String, List<String>> headers = new LinkedHashMap<>();

        List<String> entries = getProperty(PROPPREFIX_ENDPOINT + hint + ".headers", List.class);
        if (entries != null) {
            for (String entry : entries) {
                int index = entry.indexOf(':');

                if (index > 0 && index < entry.length() - 1) {
                    headers.computeIfAbsent(entry.substring(0, index), key -> new ArrayList<>())
                        .add(entry.substring(index + 1));
                }
            }
        }

        return new Endpoint(uri, headers);
    }

    public Endpoint getAuthorizationOIDCEndpoint() throws URISyntaxException
    {
        return getEndPoint(AuthorizationOIDCEndpoint.HINT);
    }

    public Endpoint getTokenOIDCEndpoint() throws URISyntaxException
    {
        return getEndPoint(TokenOIDCEndpoint.HINT);
    }

    public Endpoint getUserInfoOIDCEndpoint() throws URISyntaxException
    {
        return getEndPoint(UserInfoOIDCEndpoint.HINT);
    }

    /**
     * @since 1.21
     */
    public Endpoint getLogoutOIDCEndpoint() throws URISyntaxException
    {
        return getEndPoint(LogoutOIDCEndpoint.HINT);
    }

    public ClientID getClientID()
    {
        String clientId = getProperty(PROP_CLIENTID, String.class);

        // Fallback on instance id
        return new ClientID(clientId != null ? clientId : this.instance.getInstanceId().getInstanceId());
    }

    /**
     * @since 1.13
     */
    public Secret getSecret()
    {
        String secret = getProperty(PROP_SECRET, String.class);
        if (StringUtils.isBlank(secret)) {
            return null;
        } else {
            return new Secret(secret);
        }
    }

    /**
     * @since 1.13
     */
    public ClientAuthenticationMethod getTokenEndPointAuthMethod()
    {
        String authMethod = getProperty(PROP_ENDPOINT_TOKEN_AUTH_METHOD, String.class);
        if ("client_secret_post".equalsIgnoreCase(authMethod)) {
            return ClientAuthenticationMethod.CLIENT_SECRET_POST;
        } else {
            return ClientAuthenticationMethod.CLIENT_SECRET_BASIC;
        }
    }

    /**
     * @since 1.13
     */
    public HTTPRequest.Method getUserInfoEndPointMethod()
    {
        return getProperty(PROP_ENDPOINT_USERINFO_METHOD, HTTPRequest.Method.GET);
    }

    /**
     * @since 1.21
     */
    public HTTPRequest.Method getLogoutEndPointMethod()
    {
        return getProperty(PROP_ENDPOINT_LOGOUT_METHOD, HTTPRequest.Method.GET);
    }

    public String getSessionState()
    {
        return getSessionAttribute(PROP_STATE);
    }

    public boolean isSkipped()
    {
        return getProperty(PROP_SKIPPED, false);
    }

    /**
     * @since 1.2
     */
    public OIDCClaimsRequest getClaimsRequest()
    {
        // TODO: allow passing the complete JSON as configuration
        OIDCClaimsRequest claimsRequest = new OIDCClaimsRequest();

        // ID Token claims
        List<String> idtokenclaims = getIDTokenClaims();
        if (idtokenclaims != null && !idtokenclaims.isEmpty()) {
            ClaimsSetRequest idtokenclaimsRequest = new ClaimsSetRequest();

            for (String claim : idtokenclaims) {
                idtokenclaimsRequest.add(claim);
            }

            claimsRequest.withIDTokenClaimsRequest(idtokenclaimsRequest);
        }

        // UserInfo claims
        List<String> userinfoclaims = getUserInfoClaims();
        if (userinfoclaims != null && !userinfoclaims.isEmpty()) {
            ClaimsSetRequest userinfoclaimsRequest = new ClaimsSetRequest();

            for (String claim : userinfoclaims) {
                userinfoclaimsRequest.add(claim);
            }

            claimsRequest.withUserInfoClaimsRequest(userinfoclaimsRequest);
        }

        return claimsRequest;
    }

    /**
     * @since 1.2
     */
    public List<String> getIDTokenClaims()
    {
        return getProperty(PROP_IDTOKENCLAIMS, DEFAULT_IDTOKENCLAIMS);
    }

    /**
     * @since 1.2
     */
    public List<String> getUserInfoClaims()
    {
        return getProperty(PROP_USERINFOCLAIMS, DEFAULT_USERINFOCLAIMS);
    }

    /**
     * @since 1.12
     */
    public int getUserInfoRefreshRate()
    {
        return getProperty(PROP_USERINFOREFRESHRATE, 600000);
    }

    /**
     * @since 1.2
     */
    public Scope getScope()
    {
        List<String> scopeValues = getProperty(PROP_SCOPE, List.class);

        if (CollectionUtils.isEmpty(scopeValues)) {
            return new Scope(OIDCScopeValue.OPENID, OIDCScopeValue.PROFILE, OIDCScopeValue.EMAIL,
                OIDCScopeValue.ADDRESS, OIDCScopeValue.PHONE);
        }

        return new Scope(scopeValues.toArray(new String[0]));
    }

    /**
     * @since 1.10
     */
    public GroupMapping getGroupMapping()
    {
        List<String> groupsMapping = getProperty(PROP_GROUPS_MAPPING, List.class);

        GroupMapping groups;

        if (groupsMapping != null && !groupsMapping.isEmpty()) {
            groups = new GroupMapping(groupsMapping.size());

            for (String groupMapping : groupsMapping) {
                int index = groupMapping.indexOf('=');

                if (index != -1) {
                    String xwikiGroup = toXWikiGroup(groupMapping.substring(0, index));
                    String providerGroup = groupMapping.substring(index + 1);

                    // Add to XWiki mapping
                    Set<String> providerGroups = groups.xwikiMapping.computeIfAbsent(xwikiGroup, k -> new HashSet<>());
                    providerGroups.add(providerGroup);

                    // Add to provider mapping
                    Set<String> xwikiGroups =
                        groups.providerMapping.computeIfAbsent(providerGroup, k -> new HashSet<>());
                    xwikiGroups.add(xwikiGroup);
                }
            }
        } else {
            groups = null;
        }

        return groups;
    }

    /**
     * @since 1.10
     */
    public String toXWikiGroup(String group)
    {
        return group.startsWith(XWIKI_GROUP_PREFIX) ? group : XWIKI_GROUP_PREFIX + group;
    }

    /**
     * @since 1.10
     */
    public List<String> getAllowedGroups()
    {
        List<String> groups = getProperty(PROP_GROUPS_ALLOWED, List.class);

        return groups != null && !groups.isEmpty() ? groups : null;
    }

    /**
     * @since 1.10
     */
    public List<String> getForbiddenGroups()
    {
        List<String> groups = getProperty(PROP_GROUPS_FORBIDDEN, List.class);

        return groups != null && !groups.isEmpty() ? groups : null;
    }

    /**
     * @since 1.27
     */
    public String getGroupPrefix()
    {
        String groupPrefix = getProperty(PROP_GROUPS_PREFIX, String.class);
        return groupPrefix != null && !groupPrefix.isEmpty() ? groupPrefix : null;
    }

    /**
     * @since 1.27
     */
    public String getGroupSeparator()
    {
        return getProperty(PROP_GROUPS_SEPARATOR, String.class);
    }

    // Session only

    /**
     * @since 1.2
     */
    public Date removeUserInfoExpirationDate()
    {
        return removeSessionAttribute(PROP_SESSION_USERINFO_EXPORATIONDATE);
    }

    /**
     * @since 1.2
     */
    public void setUserInfoExpirationDate(Date date)
    {
        setSessionAttribute(PROP_SESSION_USERINFO_EXPORATIONDATE, date);
    }

    /**
     * @since 1.2
     */
    public void resetUserInfoExpirationDate()
    {
        LocalDateTime expiration = LocalDateTime.now().plusMillis(getUserInfoRefreshRate());

        setUserInfoExpirationDate(expiration.toDate());
    }

    /**
     * @since 1.2
     */
    public BearerAccessToken getAccessToken()
    {
        return getSessionAttribute(PROP_SESSION_ACCESSTOKEN);
    }

    /**
     * @since 1.2
     */
    public void setAccessToken(BearerAccessToken accessToken)
    {
        setSessionAttribute(PROP_SESSION_ACCESSTOKEN, accessToken);
    }

    /**
     * @since 1.2
     */
    public IDTokenClaimsSet getIdToken()
    {
        return getSessionAttribute(PROP_SESSION_IDTOKEN);
    }

    /**
     * @since 1.2
     */
    public void setIdToken(IDTokenClaimsSet idToken)
    {
        setSessionAttribute(PROP_SESSION_IDTOKEN, idToken);
    }

    /**
     * @since 1.2
     */
    public URI getSuccessRedirectURI()
    {
        URI uri = getSessionAttribute(PROP_INITIAL_REQUEST);
        if (uri == null) {
            // TODO: return wiki hope page
        }

        return uri;
    }

    /**
     * @since 1.2
     */
    public void setSuccessRedirectURI(URI uri)
    {
        setSessionAttribute(PROP_INITIAL_REQUEST, uri);
    }

    /**
     * @return true if groups should be synchronized (in which case if the provider does not answer to the group claim
     *         it means the user does not belong to any group)
     * @since 1.14
     */
    public boolean isGroupSync()
    {
        String groupClaim = getGroupClaim();

        return getUserInfoClaims().contains(groupClaim);
    }
}
