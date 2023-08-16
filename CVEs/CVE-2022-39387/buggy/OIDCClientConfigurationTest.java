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
import java.util.Arrays;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;

import org.junit.jupiter.api.Test;
import org.xwiki.configuration.ConfigurationSource;
import org.xwiki.test.junit5.mockito.ComponentTest;
import org.xwiki.test.junit5.mockito.InjectMockComponents;
import org.xwiki.test.junit5.mockito.MockComponent;

import static org.junit.jupiter.api.Assertions.assertEquals;
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
}
