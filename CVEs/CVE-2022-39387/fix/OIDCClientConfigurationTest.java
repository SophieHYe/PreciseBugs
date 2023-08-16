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

import java.net.MalformedURLException;
import java.net.URI;
import java.net.URISyntaxException;
import java.net.URL;
import java.util.Arrays;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;

import org.junit.jupiter.api.Test;
import org.xwiki.configuration.ConfigurationSource;
import org.xwiki.container.Container;
import org.xwiki.container.servlet.ServletRequest;
import org.xwiki.contrib.oidc.provider.internal.OIDCManager;
import org.xwiki.contrib.oidc.provider.internal.endpoint.TokenOIDCEndpoint;
import org.xwiki.properties.ConverterManager;
import org.xwiki.test.junit5.mockito.ComponentTest;
import org.xwiki.test.junit5.mockito.InjectMockComponents;
import org.xwiki.test.junit5.mockito.MockComponent;

import com.xpn.xwiki.web.XWikiServletRequestStub;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertFalse;
import static org.junit.jupiter.api.Assertions.assertNull;
import static org.junit.jupiter.api.Assertions.assertTrue;
import static org.mockito.Mockito.when;

/**
 * Validate {@link OIDCClientConfiguration}.
 * 
 * @version $Id$
 */
@ComponentTest
class OIDCClientConfigurationTest
{
    @InjectMockComponents
    private OIDCClientConfiguration configuration;

    @MockComponent
    private ConfigurationSource sourceConfiguration;

    @MockComponent
    private Container container;

    @MockComponent
    private OIDCManager manager;

    @MockComponent
    private ConverterManager converterManager;

    @Test
    void getUserInfoOIDCEndpoint() throws URISyntaxException
    {
        assertNull(this.configuration.getUserInfoOIDCEndpoint());

        URI uri = new URI("/endpoint");
        when(this.sourceConfiguration.getProperty(OIDCClientConfiguration.PROP_ENDPOINT_USERINFO, String.class))
            .thenReturn(uri.toString());

        Endpoint endpoint = this.configuration.getUserInfoOIDCEndpoint();

        assertEquals(uri, endpoint.getURI());
        assertTrue(endpoint.getHeaders().isEmpty());

        List<String> list = Arrays.asList("key1:value11", "key1:value12", "key2:value2", "alone", ":", "");

        when(this.sourceConfiguration.getProperty(OIDCClientConfiguration.PROP_ENDPOINT_USERINFO_HEADERS, List.class))
            .thenReturn(list);

        Map<String, List<String>> headers = new LinkedHashMap<>();
        headers.put("key1", Arrays.asList("value11", "value12"));
        headers.put("key2", Arrays.asList("value2"));

        endpoint = this.configuration.getUserInfoOIDCEndpoint();

        assertEquals(uri, endpoint.getURI());
        assertEquals(headers, endpoint.getHeaders());
    }

    @Test
    void getPropertyOrder() throws MalformedURLException, URISyntaxException
    {
        String provider = "http://urlprovider";
        URI urlauthorization = new URI("http://urlauthorization");

        XWikiServletRequestStub requestStub = new XWikiServletRequestStub(new URL("http://url"), null);

        when(this.container.getRequest()).thenReturn(new ServletRequest(requestStub));
        when(this.sourceConfiguration.getProperty(OIDCClientConfiguration.PROP_SKIPPED, false)).thenReturn(false);

        assertFalse(this.configuration.isSkipped());
        assertNull(this.configuration.getXWikiProvider());
        assertNull(this.configuration.getAuthorizationOIDCEndpoint());
        assertNull(this.configuration.getAuthorizationOIDCEndpoint());
        assertNull(this.configuration.getTokenOIDCEndpoint());

        requestStub.put(OIDCClientConfiguration.PROP_SKIPPED, "true");
        when(this.converterManager.convert(Boolean.class, "true")).thenReturn(true);

        assertTrue(this.configuration.isSkipped());

        requestStub.put(OIDCClientConfiguration.PROP_GROUPS_ALLOWED, "true");

        assertNull(this.configuration.getAllowedGroups());

        requestStub.put(OIDCClientConfiguration.PROP_XWIKIPROVIDER, provider.toString());
        requestStub.put(OIDCClientConfiguration.PROP_ENDPOINT_AUTHORIZATION, urlauthorization.toString());
        when(this.manager.createEndPointURI(provider, TokenOIDCEndpoint.HINT)).thenReturn(new URI(provider));

        assertEquals(urlauthorization, this.configuration.getAuthorizationOIDCEndpoint().getURI());
        assertEquals(provider, this.configuration.getTokenOIDCEndpoint().getURI().toString());
    }
}
