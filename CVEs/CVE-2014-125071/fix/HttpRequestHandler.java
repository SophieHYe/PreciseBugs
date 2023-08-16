/**
 * This file is part of the Gribbit Web Framework.
 * 
 *     https://github.com/lukehutch/gribbit
 * 
 * @author Luke Hutchison
 * 
 * --
 * 
 * @license Apache 2.0 
 * 
 * Copyright 2015 Luke Hutchison
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * 
 *     http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
package gribbit.request;

import static io.netty.handler.codec.http.HttpHeaderNames.ACCEPT_ENCODING;
import static io.netty.handler.codec.http.HttpHeaderNames.CACHE_CONTROL;
import static io.netty.handler.codec.http.HttpHeaderNames.CONNECTION;
import static io.netty.handler.codec.http.HttpHeaderNames.CONTENT_ENCODING;
import static io.netty.handler.codec.http.HttpHeaderNames.CONTENT_LENGTH;
import static io.netty.handler.codec.http.HttpHeaderNames.CONTENT_TYPE;
import static io.netty.handler.codec.http.HttpHeaderNames.DATE;
import static io.netty.handler.codec.http.HttpHeaderNames.ETAG;
import static io.netty.handler.codec.http.HttpHeaderNames.EXPECT;
import static io.netty.handler.codec.http.HttpHeaderNames.EXPIRES;
import static io.netty.handler.codec.http.HttpHeaderNames.LAST_MODIFIED;
import static io.netty.handler.codec.http.HttpHeaderNames.PRAGMA;
import static io.netty.handler.codec.http.HttpHeaderNames.SERVER;
import static io.netty.handler.codec.http.HttpHeaderNames.SET_COOKIE;
import static io.netty.handler.codec.http.HttpHeaderValues.GZIP;
import static io.netty.handler.codec.http.HttpHeaderValues.KEEP_ALIVE;
import gribbit.auth.CSRF;
import gribbit.auth.Cookie;
import gribbit.auth.User;
import gribbit.response.ErrorResponse;
import gribbit.response.HTMLPageResponse;
import gribbit.response.HTMLResponse;
import gribbit.response.NotModifiedResponse;
import gribbit.response.Response;
import gribbit.response.flashmsg.FlashMessage;
import gribbit.route.RouteHandler;
import gribbit.route.RouteHandlerAuthAndValidatedEmailRequired;
import gribbit.route.RouteHandlerAuthRequired;
import gribbit.route.RouteInfo;
import gribbit.server.GribbitServer;
import gribbit.server.config.GribbitProperties;
import gribbit.server.siteresources.CacheExtension;
import gribbit.server.siteresources.CacheExtension.HashInfo;
import gribbit.util.Log;
import gribbit.util.WebUtils;
import io.netty.buffer.ByteBuf;
import io.netty.buffer.ByteBufOutputStream;
import io.netty.buffer.Unpooled;
import io.netty.channel.ChannelFuture;
import io.netty.channel.ChannelFutureListener;
import io.netty.channel.ChannelHandlerContext;
import io.netty.channel.DefaultFileRegion;
import io.netty.channel.SimpleChannelInboundHandler;
import io.netty.handler.codec.http.DefaultFullHttpRequest;
import io.netty.handler.codec.http.DefaultFullHttpResponse;
import io.netty.handler.codec.http.DefaultHttpResponse;
import io.netty.handler.codec.http.FullHttpRequest;
import io.netty.handler.codec.http.FullHttpResponse;
import io.netty.handler.codec.http.HttpChunkedInput;
import io.netty.handler.codec.http.HttpContent;
import io.netty.handler.codec.http.HttpHeaderUtil;
import io.netty.handler.codec.http.HttpHeaders;
import io.netty.handler.codec.http.HttpMethod;
import io.netty.handler.codec.http.HttpRequest;
import io.netty.handler.codec.http.HttpResponseStatus;
import io.netty.handler.codec.http.HttpVersion;
import io.netty.handler.codec.http.LastHttpContent;
import io.netty.handler.codec.http.ServerCookieEncoder;
import io.netty.handler.codec.http.multipart.Attribute;
import io.netty.handler.codec.http.multipart.DefaultHttpDataFactory;
import io.netty.handler.codec.http.multipart.DiskAttribute;
import io.netty.handler.codec.http.multipart.DiskFileUpload;
import io.netty.handler.codec.http.multipart.FileUpload;
import io.netty.handler.codec.http.multipart.HttpDataFactory;
import io.netty.handler.codec.http.multipart.HttpPostRequestDecoder;
import io.netty.handler.codec.http.multipart.HttpPostRequestDecoder.EndOfDataDecoderException;
import io.netty.handler.codec.http.multipart.InterfaceHttpData;
import io.netty.handler.codec.http.multipart.InterfaceHttpData.HttpDataType;
import io.netty.handler.codec.http.websocketx.CloseWebSocketFrame;
import io.netty.handler.codec.http.websocketx.PingWebSocketFrame;
import io.netty.handler.codec.http.websocketx.PongWebSocketFrame;
import io.netty.handler.codec.http.websocketx.TextWebSocketFrame;
import io.netty.handler.codec.http.websocketx.WebSocketFrame;
import io.netty.handler.codec.http.websocketx.WebSocketServerHandshaker;
import io.netty.handler.codec.http.websocketx.WebSocketServerHandshakerFactory;
import io.netty.handler.ssl.NotSslRecordException;
import io.netty.handler.ssl.SslHandler;
import io.netty.handler.stream.ChunkedFile;
import io.netty.util.CharsetUtil;

import java.io.File;
import java.io.IOException;
import java.io.RandomAccessFile;
import java.net.InetAddress;
import java.net.InetSocketAddress;
import java.net.URI;
import java.nio.charset.Charset;
import java.time.Instant;
import java.time.ZoneId;
import java.time.ZonedDateTime;
import java.time.format.DateTimeFormatter;
import java.util.ArrayList;
import java.util.HashSet;
import java.util.List;
import java.util.Map.Entry;
import java.util.zip.GZIPOutputStream;

public class HttpRequestHandler extends SimpleChannelInboundHandler<Object> {

    private Request request;

    private boolean closeAfterWrite = false;
    private boolean addKeepAliveHeader = false;

    // -----------------------------------------------------------------------------------------------------------------

    // Use disk for HTTP data if size >16kB 
    private static final HttpDataFactory factory = new DefaultHttpDataFactory(DefaultHttpDataFactory.MINSIZE);

    static {
        // Delete temporary files on exit
        DiskFileUpload.deleteOnExitTemporaryFile = true;
        DiskAttribute.deleteOnExitTemporaryFile = true;
        // Use system temp directory
        DiskFileUpload.baseDirectory = null;
        DiskAttribute.baseDirectory = null;
    }

    // -----------------------------------------------------------------------------------------------------------------

    private HttpPostRequestDecoder decoder;

    private void destroyDecoder() {
        if (request != null) {
            try {
                // Release any temporary files
                request.releasePostFileUploadParams();
            } catch (Exception e) {
            }
        }
        if (decoder != null) {
            try {
                decoder.cleanFiles();
                decoder.destroy();
                decoder = null;
            } catch (Exception e) {
            }
        }
    }

    // -----------------------------------------------------------------------------------------------------------------

    /**
     * Set Date and cache-related headers.
     * 
     * TODO: read http://www.mobify.com/blog/beginners-guide-to-http-cache-headers/ TODO: read
     * https://www.mnot.net/cache_docs/
     */
    private void setDateAndCacheHeaders(HttpHeaders httpHeaders, ZonedDateTime timeNow, long lastModifiedEpochSeconds,
            long hashKeyMaxRemainingAgeSeconds, String hashKey) {
        // Date header uses server time, and should use the same clock as Expires and Last-Modified
        httpHeaders.set(DATE, timeNow.format(DateTimeFormatter.RFC_1123_DATE_TIME));

        // Last-Modified is used to determine whether a Not Modified response should be returned on the next request
        if (lastModifiedEpochSeconds > 0) {
            // Add last modified header to cacheable resources. This is needed because Chrome sends
            // "Cache-Control: max-age=0" when the user types in a URL and hits enter, or hits refresh.
            // In these circumstances, sending back "Cache-Control: public, max-age=31536000" does
            // no good, because the browser has already requested the resource rather than relying on
            // its cache. By setting the last modified header for all cacheable resources, we can
            // at least send "Not Modified" as a response if the resource has not been modified,
            // which doesn't save on roundtrips, but at least saves on re-transferring the resources
            // to the browser when they're already in the browser's cache.
            httpHeaders.set(
                    LAST_MODIFIED,
                    ZonedDateTime.ofInstant(Instant.ofEpochSecond(lastModifiedEpochSeconds), ZoneId.of("UTC")).format(
                            DateTimeFormatter.RFC_1123_DATE_TIME));
        }

        // Cache hashed URIs forever (or for the specified amount of time)
        if (hashKey != null) {
            // Negative max age => cache indefinitely (although the spec only allows for one year, or 31536000 seconds)
            long maxAgeSeconds = hashKeyMaxRemainingAgeSeconds < 0 ? 31536000 : hashKeyMaxRemainingAgeSeconds;

            // Cache hash URIs for one year
            httpHeaders.set(CACHE_CONTROL, "public, max-age=" + maxAgeSeconds);
            httpHeaders.set(EXPIRES, timeNow.plusSeconds(maxAgeSeconds).format(DateTimeFormatter.RFC_1123_DATE_TIME));
            httpHeaders.set(ETAG, hashKey);
        }

        if (hashKey == null && lastModifiedEpochSeconds == 0) {
            // Disable caching for all resources that don't have a last modified time (all static file
            // resources have a last modified time, so these headers will never be set for files, and 
            // they won't be set for dynamic resources that don't manually set a last modified time).
            // Without these headers, the server will not have a last modified timestamp to check
            // against its own timestamp on subsequent requests, so cannot return Not Modified.
            // This is the minimum necessary set of headers for disabling caching, see http://goo.gl/yXGd2x
            httpHeaders.add(CACHE_CONTROL, "no-cache, no-store, must-revalidate"); // HTTP 1.1
            httpHeaders.add(PRAGMA, "no-cache"); // HTTP 1.0
            httpHeaders.add(EXPIRES, "0"); // Proxies
        }

    }

    // -----------------------------------------------------------------------------------------------------------------

    /** Call the route handler for the given route. */
    private static Response getResponseForRoute(RouteInfo route, Request req, User user) throws Exception {
        Response response;
        try {
            // Call the RestHandler for the route
            response = route.callHandler(req, user);

        } catch (Exception e) {
            Log.exception("Exception while handling URI " + req.getURI(), e);
            try {
                // Call Internal Server Error handler on exception
                response = GribbitServer.siteResources.getInternalServerErrorRoute().callHandler(req, user);
            } catch (Exception e1) {
                // Fallback in case there's an exception in the Internal Server Error handler
                Log.exception("Error in internal server error handler while handling URI " + req.getURI(), e1);
                response = new ErrorResponse(HttpResponseStatus.INTERNAL_SERVER_ERROR, "Internal Server Error");
            }
        }
        return response;
    }

    // -----------------------------------------------------------------------------------------------------------------

    /** Call the given error handler route. */
    private static Response getResponseForErrorHandlerRoute(RouteInfo route, Request req, User user) throws Exception {
        // Temporarily replace the method on the original route with a GET request on the error handler's route
        String origURI = req.getURI();
        HttpMethod origMethod = req.getMethod();
        req.setURI(route.getRoutePath());
        req.setMethod(HttpMethod.GET);
        // Call the error handler
        Response response = getResponseForRoute(route, req, user);
        req.setURI(origURI);
        req.setMethod(origMethod);
        return response;
    }

    // -----------------------------------------------------------------------------------------------------------------

    /** Serve a static file. */
    private void serveStaticFile(String reqURI, String hashKey, File staticResourceFile, long lastModifiedEpochSeconds,
            ChannelHandlerContext ctx) throws Exception {

        RandomAccessFile fileToServe = null;
        try {
            // Create new RandomAccessFile (which allows us to find file length etc.)
            fileToServe = new RandomAccessFile(staticResourceFile, "r");

            // -----------------------------------------
            // Serve a static file (not authenticated)
            // -----------------------------------------

            DefaultHttpResponse httpRes = new DefaultHttpResponse(HttpVersion.HTTP_1_1, HttpResponseStatus.OK);
            httpRes.headers().add("Server", GribbitServer.SERVER_IDENTIFIER);

            long fileLength = fileToServe.length();
            httpRes.headers().set(CONTENT_LENGTH, Long.toString(fileLength));
            WebUtils.setContentTypeHeaders(httpRes.headers(), staticResourceFile.getPath());

            // If the file contents have changed since the last time the file was hashed,
            // schedule the file to be hashed in the background so that future references to the
            // file's URI in a src/href attribute of served HTML templates will include a hash
            // URI rather than the original URI for the file, allowing the browser to cache the
            // file indefinitely until it changes.
            CacheExtension.updateHashURI(reqURI, staticResourceFile);

            // If file was already cached, and the request URI included the hash key, then this is
            // the first time this client has fetched this file since the browser cache was last
            // cleared. Mark this resource as indefinitely cached. If the file is not being served
            // on a hash URI, then at least set the Last-Modified header, so that if the client
            // requests the same unmodified resource again on the same non-hash URI, the server can
            // return Not Modified instead of serving the contents of the file.
            setDateAndCacheHeaders(httpRes.headers(), ZonedDateTime.now(), lastModifiedEpochSeconds, //
                    hashKey != null ? /* cache indefinitely: */-1 : /* ignored if hashKey == null: */0, hashKey);

            if (addKeepAliveHeader) {
                httpRes.headers().add(CONNECTION, KEEP_ALIVE);
            }

            // Write HTTP headers to channel
            ctx.write(httpRes);

            // Write file content to channel.
            // Can add ChannelProgressiveFutureListener to sendFileFuture if we need to track
            // progress (e.g. to update user's UI over a web socket to show download progress.)
            ChannelFuture sendFileFuture;
            ChannelFuture lastContentFuture;
            if (ctx.pipeline().get(SslHandler.class) == null) {
                // Use FileRegions if possible, which supports zero-copy / mmio
                sendFileFuture =
                        ctx.write(new DefaultFileRegion(fileToServe.getChannel(), 0, fileLength),
                                ctx.newProgressivePromise());
                // Write the end marker
                lastContentFuture = ctx.writeAndFlush(LastHttpContent.EMPTY_LAST_CONTENT);
            } else {
                // Can't use FileRegions / zero-copy with SSL
                sendFileFuture =
                        ctx.write(new HttpChunkedInput(new ChunkedFile(fileToServe, 0, fileLength, 1)),
                                ctx.newProgressivePromise());
                // HttpChunkedInput will write the end marker (LastHttpContent) for us.
                // See https://github.com/netty/netty/commit/4ba2ce3cbbc55391520cfc98a7d4227630fbf978
                lastContentFuture = sendFileFuture;
            }

            //            sendFileFuture.addListener(new ChannelProgressiveFutureListener() {
            //                @Override
            //                public void operationProgressed(ChannelProgressiveFuture future, long progress, long total) {
            //                    if (total < 0) { // total unknown
            //                        System.err.println(future.channel() + " Transfer progress: " + progress);
            //                    } else {
            //                        System.err.println(future.channel() + " Transfer progress: " + progress + " / " + total);
            //                    }
            //                }
            //
            //                @Override
            //                public void operationComplete(ChannelProgressiveFuture future) {
            //                    System.err.println(future.channel() + " Transfer complete.");
            //                }
            //            });

            // Close connection after flush if needed, and close file after flush 
            final RandomAccessFile fileToClose = fileToServe;
            lastContentFuture.addListener(new ChannelFutureListener() {
                @Override
                public void operationComplete(ChannelFuture future) {
                    if (closeAfterWrite) {
                        future.channel().close();
                    }
                    try {
                        fileToClose.close();
                    } catch (IOException e) {
                    }
                }
            });

        } catch (Exception e) {
            if (fileToServe != null) {
                try {
                    fileToServe.close();
                } catch (IOException e1) {
                }
            }
            throw new RuntimeException("Exception serving static file", e);
        }
    }

    // -----------------------------------------------------------------------------------------------------------------

    /** Serve an HTTP response (anything other than a static file). */
    private void serveHttpResponse(String reqURI, Response response, boolean isHEAD, boolean acceptEncodingGzip,
            ZonedDateTime timeNow, boolean hashTheResponse, long hashKeyMaxRemainingAgeSeconds, String hashKey,
            ChannelHandlerContext ctx) throws Exception {

        // Add any pending flash messages to the response, if the response is an HTML page
        if (response instanceof HTMLPageResponse) {
            // Add flash messages to response template, if any
            ArrayList<FlashMessage> flashMessages = request.getFlashMessages();
            if (flashMessages != null) {
                // Render pending flash messages into the HTML page
                ((HTMLPageResponse) response).setFlashMessages(flashMessages);
                // Clear the flash message cookie
                response.deleteCookie(Cookie.FLASH_COOKIE_NAME);
            }
        } else {
            // Store any un-displayed flash messages back in the cookie
            ArrayList<FlashMessage> flashMessages = request.getFlashMessages();
            if (flashMessages != null) {
                response.setCookie(new Cookie(Cookie.FLASH_COOKIE_NAME, "/",
                        FlashMessage.toCookieString(flashMessages), 60));
            }
        }

        // Get the content of the response as a byte buffer.
        ByteBuf content;
        String contentType;
        if (response instanceof HTMLResponse) {
            // If "?_getmodel=1" is appended to the request URL of a request that returns an HTML response,
            // then the data model backing the response will be returned as JSON instead of rendered HTML.
            boolean isGetModelRequest = GribbitProperties.ALLOW_GET_MODEL && request.isGetModelRequest();
            content = ((HTMLResponse) response).getContent(isGetModelRequest);
            contentType = isGetModelRequest ? "application/json;charset=utf-8" : response.getContentType();
        } else {
            // Not a "getmodel" request, just get the content from the response
            content = response.getContent();
            contentType = response.getContentType();
        }
        byte[] contentBytes = content.array();

        // Gzip content if the configuration property is set to allow gzip, and the client supports gzip encoding,
        // and the content size is larger than 1kb, and the content type is compressible 
        ByteBuf gzippedContent = null;
        if (GribbitProperties.CONTENT_GZIP && //
                acceptEncodingGzip //
                && content.readableBytes() > 1024 //
                && WebUtils.isCompressibleContentType(contentType)) {
            gzippedContent = Unpooled.buffer(/* initialCapacity = */content.readableBytes());
            // TODO: compare speed to using JZlib.GZIPOutputStream
            GZIPOutputStream gzipStream = new GZIPOutputStream(new ByteBufOutputStream(gzippedContent));
            gzipStream.write(contentBytes);
            gzipStream.close();
        }

        // Create a FullHttpResponse object that wraps the response status and content
        HttpResponseStatus status = response.getStatus();
        DefaultFullHttpResponse httpRes = new DefaultFullHttpResponse(HttpVersion.HTTP_1_1, status, //
                gzippedContent != null ? gzippedContent : content);
        httpRes.headers().set(CONTENT_TYPE, contentType);
        httpRes.headers().set(CONTENT_LENGTH,
                Integer.toString(gzippedContent != null ? gzippedContent.readableBytes() : content.readableBytes()));
        if (gzippedContent != null) {
            httpRes.headers().set(CONTENT_ENCODING, GZIP);
        }

        httpRes.headers().add(SERVER, GribbitServer.SERVER_IDENTIFIER);

        // Add an Accept-Encoding: gzip header to the response to let the client know that in future
        // it can send compressed requests. (This header is probably ignored by most clients, because
        // on initial request they don't know yet if the server can accept compressed content, but
        // there must be clients out there that look for this header and compress content on the
        // second and subsequent requests? See http://stackoverflow.com/a/1450163/3950982 )
        httpRes.headers().add(ACCEPT_ENCODING, "gzip");

        // Set date and cache headers
        setDateAndCacheHeaders(httpRes.headers(), timeNow, response.getLastModifiedEpochSeconds(),
                hashKeyMaxRemainingAgeSeconds, hashKey);

        // If the response needs hashing, and the response does not have an error status, then schedule the
        // content of the response for hashing, and store a mapping from the original request URI to the
        // hash URI so that future HTML responses that have src/href attributes that contain this request
        // URI will replace this request URI with the hash URI instead. This will mean the client will
        // fetch that hash URI only once until it expires in the cache, so that on subsequent requests,
        // the linked resource won't even be requested from the server.
        if (hashTheResponse && status == HttpResponseStatus.OK) {
            CacheExtension.updateHashURI(reqURI, content, response.getLastModifiedEpochSeconds());
        }

        // Release the content ByteBuf after last usage if gzippedContent is being used instead
        if (gzippedContent != null) {
            content.release();
        }

        if (isHEAD) {
            // Don't return a body for HEAD requests (but still return the content length,
            // set in the header above)
            httpRes.content().clear();
        }

        // Delete requested cookies in the response
        HashSet<String> cookiesToDelete = response.getCookiesToDelete();
        if (cookiesToDelete != null) {
            for (String cookieName : cookiesToDelete) {
                // Log.fine("Cookie to delete for req " + reqURI + " : " + cookieName);
                ArrayList<Cookie> allCookiesWithName = request.getAllCookiesWithName(cookieName);
                if (allCookiesWithName != null) {
                    for (Cookie cookie : allCookiesWithName) {
                        // Delete all cookies with the requested name (there may be multiple cookies
                        // with this name but with different paths)
                        String deleteCookieStr =
                                ServerCookieEncoder.encode(Cookie.deleteCookie(cookie).toNettyCookie());
                        httpRes.headers().add(SET_COOKIE, deleteCookieStr);
                    }
                }
            }
        }

        // Set requested cookies in the response
        ArrayList<Cookie> cookiesToSet = response.getCookiesToSet();
        if (cookiesToSet != null) {
            for (Cookie cookie : cookiesToSet) {
                if (cookiesToDelete != null && cookiesToDelete.contains(cookie.getName())) {
                    Log.warning("Tried to delete and set the cookie \"" + cookie.getName()
                            + "\" in the same response -- ignoring the set request");
                } else {
                    String setCookieStr = ServerCookieEncoder.encode(cookie.toNettyCookie());
                    httpRes.headers().add(SET_COOKIE, setCookieStr);
                }
            }
        }

        // Add any custom headers from the Response object
        if (response.getCustomHeaders() != null) {
            for (Entry<String, String> header : response.getCustomHeaders().entrySet()) {
                httpRes.headers().add(header.getKey(), header.getValue());
            }
        }

        // Close the connection after serving the response if the response status is anything other than OK
        if (addKeepAliveHeader && status == HttpResponseStatus.OK) {
            httpRes.headers().add(CONNECTION, KEEP_ALIVE);
        }

        // closeAfterWrite = true;  // FIXME: test this, it doesn't seem to work ====================================================================

        if (ctx.channel().isOpen()) {
            // Check Channel.isWritable() to prevent OutOfMemoryError,
            // see http://normanmaurer.me/presentations/2014-facebook-eng-netty/slides.html#10.0
            long startTime = System.currentTimeMillis();
            while (!ctx.channel().isWritable() && (System.currentTimeMillis() - startTime < 5000)) {
                // TODO: replace this spinlock (usually channel is immediately writeable;
                // is this even needed?)
            }
            if (ctx.channel().isWritable()) {
                // Write the ByteBuffer returned by httpRes.content() back into the pipeline
                // See http://normanmaurer.me/presentations/2014-facebook-eng-netty/slides.html#4.0
                ChannelFuture future = ctx.channel().writeAndFlush(httpRes);

                // Close the connection after the write operation is done if necessary.
                // TODO: Apache closes KeepAlive connections after a few seconds,
                // see http://en.wikipedia.org/wiki/HTTP_persistent_connection
                // TODO: implement a stale connection tracker
                if (closeAfterWrite || status != HttpResponseStatus.OK) { // FIXME: should I close the channel for redirects? (probably not...)
                    future.addListener(ChannelFutureListener.CLOSE);
                }

            } else {
                // Tried for a period of time but could not send response; close channel
                ctx.channel().close();
                throw new RuntimeException("Could not send response after repeated attempts");
            }
        } else {
            // Client already closed the connection, nothing can be sent
            // Log.info("Channel closed by client before response sent");
        }
    }

    // -----------------------------------------------------------------------------------------------------------------

    /** Websocket handshaker. */
    private WebSocketServerHandshaker handshaker;

    /** The user that was authenticated when the websocket upgrade was requested. */
    private User wsAuthenticatedUser;

    /** Handle a websocket frame. */
    private void handleWebsocketFrame(ChannelHandlerContext ctx, WebSocketFrame frame) {
        // Check for closing frame
        if (frame instanceof CloseWebSocketFrame) {
            handshaker.close(ctx.channel(), (CloseWebSocketFrame) frame.retain());
            return;
        }
        if (frame instanceof PingWebSocketFrame) {
            ctx.channel().write(new PongWebSocketFrame(frame.content().retain()));
            return;
        }
        if (!(frame instanceof TextWebSocketFrame)) {
            throw new UnsupportedOperationException(String.format("%s frame types not supported", frame.getClass()
                    .getName()));
        }

        // TODO: placeholder
        String requestText = ((TextWebSocketFrame) frame).text();
        String responseText =
                requestText.toUpperCase() + " -- "
                        + (wsAuthenticatedUser == null ? "not logged in" : wsAuthenticatedUser.id);

        ctx.channel().writeAndFlush(new TextWebSocketFrame(responseText));
    }

    // -----------------------------------------------------------------------------------------------------------------

    private static void sendHttpErrorResponse(ChannelHandlerContext ctx, HttpRequest req, FullHttpResponse res) {
        // Generate an error page if response getStatus code is not OK (200).
        if (res.status().code() != 200) {
            ByteBuf buf = Unpooled.copiedBuffer(res.status().toString(), CharsetUtil.UTF_8);
            res.content().writeBytes(buf);
            buf.release();
            res.headers().set(CONTENT_TYPE, "text/plain;charset=utf-8");
            HttpHeaderUtil.setContentLength(res, res.content().readableBytes());
        }
        ChannelFuture f = ctx.channel().writeAndFlush(res);
        if (req == null || !HttpHeaderUtil.isKeepAlive(req) || res.status().code() != 200) {
            f.addListener(ChannelFutureListener.CLOSE);
        }
    }

    // -----------------------------------------------------------------------------------------------------------------

    /** Decode an HTTP message. */
    @Override
    public void messageReceived(ChannelHandlerContext ctx, Object msg) throws Exception {

        // ------------------------------------------------------------------------------
        // Handle WebSocket frames
        // ------------------------------------------------------------------------------

        if (msg instanceof WebSocketFrame) {
            handleWebsocketFrame(ctx, (WebSocketFrame) msg);
            return;
        }

        // ------------------------------------------------------------------------------
        // Decode HTTP headers
        // ------------------------------------------------------------------------------

        boolean requestComplete = false;
        try {
            if (msg instanceof HttpRequest) {
                HttpRequest httpReq = (HttpRequest) msg;

                // System.out.println("REQUEST: " + httpReq.getUri());

                // Start a new request
                request = new Request(httpReq);

                // Handle expect-100-continue
                boolean expect100Continue = false;
                List<CharSequence> allExpectHeaders = httpReq.headers().getAll(EXPECT);
                for (int i = 0; i < allExpectHeaders.size(); i++) {
                    String h = allExpectHeaders.get(i).toString();
                    if (h.equalsIgnoreCase("100-continue")) {
                        expect100Continue = true;
                        break;
                    }
                }
                if (expect100Continue) {
                    ctx.writeAndFlush(new DefaultFullHttpResponse(HttpVersion.HTTP_1_1, HttpResponseStatus.CONTINUE,
                            Unpooled.EMPTY_BUFFER));
                    requestComplete = true;
                    return;
                }

                closeAfterWrite = !HttpHeaderUtil.isKeepAlive(httpReq);
                addKeepAliveHeader = !closeAfterWrite && httpReq.protocolVersion().equals(HttpVersion.HTTP_1_0);

                if (httpReq.method() == HttpMethod.POST) {
                    // Start decoding HttpContent chunks
                    destroyDecoder();
                    decoder = new HttpPostRequestDecoder(factory, httpReq);

                } else {
                    // Non-POST (probably GET) -- start handling the request
                    requestComplete = true;
                }

                // TODO: will this return failure before all POST chunks have been received?
                if (!httpReq.decoderResult().isSuccess()) {
                    sendHttpErrorResponse(ctx, httpReq, new DefaultFullHttpResponse(HttpVersion.HTTP_1_1,
                            HttpResponseStatus.BAD_REQUEST));
                    requestComplete = true;
                    return;
                }
            }

            // ------------------------------------------------------------------------------
            // Decode HTTP POST body
            // ------------------------------------------------------------------------------

            if (msg instanceof HttpContent && decoder != null) {
                HttpContent chunk = (HttpContent) msg;
                // Offer chunk to decoder (this decreases refcount of chunk, so it doesn't have to
                // be separately released). Decoder is released after message has been handled.
                decoder.offer(chunk);

                try {
                    while (decoder.hasNext()) {
                        InterfaceHttpData data = decoder.next();
                        if (data != null) {
                            HttpDataType httpDataType = data.getHttpDataType();
                            if (httpDataType == HttpDataType.Attribute) {
                                try {
                                    Attribute attribute = (Attribute) data;
                                    request.setPostParam(attribute.getName(), attribute.getString(attribute
                                            .getCharset() == null ? Charset.forName("UTF-8") : attribute.getCharset()));
                                } finally {
                                    // Decrease refcount, freeing data
                                    data.release();
                                }

                            } else if (httpDataType == HttpDataType.FileUpload) {
                                FileUpload fileUpload = (FileUpload) data;
                                // TODO consider imposing size limit and returning 413 (Request Entity Too
                                // Large) once the amount of data that has been sent hits the limit
                                if (fileUpload.isCompleted()) {
                                    // Save the FileUpload object (which wraps a DiskFileUpload in /tmp).
                                    // Need to release this resource later.
                                    request.setPostFileUploadParam(fileUpload.getName(), fileUpload);
                                }
                            } else {
                                Log.warning("Got unknown data chunk type: " + httpDataType);
                            }
                        }
                    }
                } catch (EndOfDataDecoderException e) {
                    // Apparently decoder.hasNext() doesn't actually work
                }

                if (chunk instanceof LastHttpContent) {
                    requestComplete = true;
                }
            }

            if (!requestComplete) {
                // Wait for more chunks.
                // (Since requestComplete is false, calling return here will not call destroyDecoder()
                // in the finally block, so it will still exist when the next chunk is received.)
                return;
            }

            // ------------------------------------------------------------------------------
            // Figure out how to handle HTTP request
            // ------------------------------------------------------------------------------

            // All POST chunks have been received (or there are no chunks); ready to start handling the request

            String origReqURI = request.getURI();

            // If this is a hash URI, look up original URI whose served resource was hashed to give this hash URI.
            // We only need to serve the resource at a hash URI once per resource per client, since resources served
            // from hash URIs are indefinitely cached in the browser.
            String hashKey = CacheExtension.getHashKey(origReqURI);
            boolean isHashURI = hashKey != null;
            String reqURI = isHashURI ? CacheExtension.getOrigURI(origReqURI) : origReqURI;

            InetSocketAddress requestor = (InetSocketAddress) ctx.channel().remoteAddress();
            if (requestor != null) {
                InetAddress address = requestor.getAddress();
                if (address != null) {
                    request.setRequestor(address.getHostAddress());
                }
            }

            boolean isHEAD = request.getMethod() == HttpMethod.HEAD;

            // Run the GET method if HEAD is requested, just don't return a body.
            HttpMethod origReqMethod = request.getMethod();
            if (isHEAD) {
                request.setMethod(HttpMethod.GET);
            }

            // ------------------------------------------------------------------------------
            // Authenticate user
            // ------------------------------------------------------------------------------

            // The response object generated by a RestHandler
            Response response = null;

            // Call route handlers until one is able to handle the route,
            // or until we run out of handlers
            User user = null;
            RouteInfo authorizedRoute = null;
            ArrayList<RouteInfo> allRoutes = GribbitServer.siteResources.getAllRoutes();
            for (int i = 0, n = allRoutes.size(); i < n; i++) {
                RouteInfo route = allRoutes.get(i);
                // If the request URI matches this route path
                if (route.matches(reqURI)) {
                    Class<? extends RouteHandler> handler = route.getHandler();

                    if (!(request.getMethod() == HttpMethod.GET || request.getMethod() == HttpMethod.POST)) {

                        // We only support GET and POST at this point
                        Log.error("Unsupported HTTP method " + request.getMethod().name() + " for path " + reqURI);
                        response = new ErrorResponse(HttpResponseStatus.METHOD_NOT_ALLOWED, "HTTP method not allowed");

                    } else if ((request.getMethod() == HttpMethod.GET && !route.hasGetMethod())
                            || (request.getMethod() == HttpMethod.POST && !route.hasPostMethod())) {

                        // Tried to call an HTTP method that is not defined for this route
                        Log.error("HTTP method " + request.getMethod().name() + " not implemented in handler "
                                + handler.getName());
                        response = new ErrorResponse(HttpResponseStatus.METHOD_NOT_ALLOWED, "HTTP method not allowed");

                    } else if (RouteHandlerAuthRequired.class.isAssignableFrom(handler)) {

                        // This handler requires authentication -- check if user is logged in
                        user = User.getLoggedInUser(request);
                        if (user == null) {

                            // User is not logged in: handle request with OnUnauthorized handler instead
                            response =
                                    getResponseForErrorHandlerRoute(GribbitServer.siteResources.getUnauthorizedRoute(),
                                            request, user)
                                    // Redirect the user back to the page they were trying to get to once they
                                    // do manage to log in successfully
                                            .setCookie(
                                                    new Cookie(Cookie.REDIRECT_AFTER_LOGIN_COOKIE_NAME, "/", reqURI,
                                                            300));

                        } else if (RouteHandlerAuthAndValidatedEmailRequired.class.isAssignableFrom(handler)
                                && !user.emailIsValidated()) {

                            // User is logged in, but their email address has not been validated:
                            // handle request with EmailNotValidated handler instead
                            response =
                                    getResponseForErrorHandlerRoute(
                                            GribbitServer.siteResources.getEmailNotValidatedRoute(), request, user);

                        } else {

                            // Authorization required and user logged in: OK to handle request
                            // with this route
                            authorizedRoute = route;
                        }
                    } else {

                        // Authorization not required -- OK to handle request with this route
                        authorizedRoute = route;
                    }

                    // URI matches, so don't need to search further URIs
                    break;
                }
            }

            // ------------------------------------------------------------------------------
            // Complete websocket handshake if requested
            // ------------------------------------------------------------------------------

            // FIXME: Make these into class annotations
            String websocketPath = "/websocket";
            boolean isAuthenticatedWebsocket = true;

            if (response == null && authorizedRoute == null && msg instanceof HttpRequest
            // TODO: Read WS routes from class annotations, rather than using hardcoded "/websocket"
                    && reqURI.endsWith(websocketPath)) {
                HttpRequest httpReq = (HttpRequest) msg;

                // Protect against CSWSH: (Cross-Site WebSocket Hijacking)
                // http://www.christian-schneider.net/CrossSiteWebSocketHijacking.html
                // http://tools.ietf.org/html/rfc6455#page-7
                CharSequence origin = request.getOrigin();
                URI originUri = null;
                if (origin != null && origin.length() > 0) {
                    try {
                        // Try parsing origin URI
                        originUri = new URI(origin.toString());
                    } catch (Exception e) {
                    }
                }
                // If port number is set but it is the default for the URI scheme, revert the port number
                // back to -1 (which means unspecified), so that it matches the server port number, 
                // which is unspecified when serving http on port 80 and https on port 443
                int originPort = originUri == null ? -1 //
                        : originUri.getPort() == 80 && "http".equals(originUri.getScheme()) ? -1 //
                                : originUri.getPort() == 443 && "https".equals(originUri.getScheme()) ? -1 //
                                        : originUri.getPort();
                // Scheme, host and port all must match to forbid cross-origin requests
                if (originUri == null //
                        || !GribbitServer.uri.getScheme().equals(originUri.getScheme()) //
                        || !GribbitServer.uri.getHost().equals(originUri.getHost()) //
                        || GribbitServer.uri.getPort() != originPort) { //
                    // Reject scripted requests to open this websocket from a different domain
                    sendHttpErrorResponse(ctx, null, new DefaultFullHttpResponse(HttpVersion.HTTP_1_1,
                            HttpResponseStatus.FORBIDDEN));
                    return;
                }
                // Log.info("Origin: " + origin.toString());

                if (isAuthenticatedWebsocket) {
                    // For authenticated websockets, check if the user is logged in
                    User loggedInUser = User.getLoggedInUser(request);
                    if (loggedInUser == null) {
                        // Not logged in, so can't connect to this websocket
                        sendHttpErrorResponse(ctx, null, new DefaultFullHttpResponse(HttpVersion.HTTP_1_1,
                                HttpResponseStatus.FORBIDDEN));
                        return;
                    }

                    // To further mitigate CSWSH attacks: check for the CSRF token in the URL parameter "_csrf";
                    // the passed token must match the user's CSRF token. This means the websocket URL has to
                    // be dynamically generated and inserted into the webpage that opened the websocket.
                    // TODO: generate this URL an insert into the page somehow
                    String csrfTok = loggedInUser.csrfTok;
                    if (csrfTok == null || csrfTok.isEmpty() || csrfTok.equals(CSRF.CSRF_TOKEN_UNKNOWN)
                            || csrfTok.equals(CSRF.CSRF_TOKEN_PLACEHOLDER)) {
                        // No valid CSRF token in User object
                        sendHttpErrorResponse(ctx, null, new DefaultFullHttpResponse(HttpVersion.HTTP_1_1,
                                HttpResponseStatus.FORBIDDEN));
                        return;
                    }
                    String csrfParam = request.getQueryParam("_csrf");
                    if (csrfParam == null || csrfParam.isEmpty() || !csrfParam.equals(csrfTok)) {
                        // The CSRF URL query parameter is missing, or doesn't match the user's token 
                        sendHttpErrorResponse(ctx, null, new DefaultFullHttpResponse(HttpVersion.HTTP_1_1,
                                HttpResponseStatus.FORBIDDEN));
                        return;                        
                    }

                    // Record which user was authenticated when the websocket upgrade request was made.
                    // TODO: Also provide a means for revoking user's session while WS is still open,
                    // e.g. poll the user table every few seconds to see if user's session token has
                    // changed in the database? (Although this would mean that logging in on a new
                    // device would log you out of all other sessions...)
                    wsAuthenticatedUser = loggedInUser;
                }

                WebSocketServerHandshakerFactory wsFactory =
                        new WebSocketServerHandshakerFactory(GribbitServer.wsUri.toString(), null, true);
                handshaker = wsFactory.newHandshaker(httpReq);
                if (handshaker == null) {
                    WebSocketServerHandshakerFactory.sendUnsupportedVersionResponse(ctx.channel());
                } else {
                    // Attempt websocket handshake, and if it succeeds, upgrade connection to websocket
                    // TODO: filed bug report, handshaker.handshake should take HttpRequest, not FullHttpRequest
                    DefaultFullHttpRequest fullReq =
                            new DefaultFullHttpRequest(httpReq.protocolVersion(), httpReq.method(), httpReq.uri());
                    fullReq.headers().add(httpReq.headers());
                    handshaker.handshake(ctx.channel(), (FullHttpRequest) fullReq);
                }
                return;
            }

            // ------------------------------------------------------------------------------
            // Handle static file requests
            // ------------------------------------------------------------------------------

            // If no error has occurred so far, and no route handler matched the request URI, and this is a
            // GET request, then see if the URI points to a static file resource, and if so, serve the file.
            if (response == null && authorizedRoute == null) {
                // Static file requests can only use GET method
                if (request.getMethod() != HttpMethod.GET) {
                    sendHttpErrorResponse(ctx, null, new DefaultFullHttpResponse(HttpVersion.HTTP_1_1,
                            HttpResponseStatus.FORBIDDEN));
                    return;
                }

                File staticResourceFile = GribbitServer.siteResources.getStaticResource(reqURI);
                if (staticResourceFile == null) {

                    // Neither a route handler nor a static resource matched the request URI.
                    // Return 404 Not Found.
                    response =
                            getResponseForErrorHandlerRoute(GribbitServer.siteResources.getNotFoundRoute(), request,
                                    user);

                } else {

                    // A static resource matched the request URI, check last-modified timestamp
                    // against the If-Modified-Since header timestamp in the request.
                    long lastModifiedEpochSeconds = staticResourceFile.lastModified() / 1000;
                    if (!request.cachedVersionIsOlderThan(lastModifiedEpochSeconds)) {
                        // File has not been modified since it was last cached -- return Not Modified
                        response = new NotModifiedResponse(lastModifiedEpochSeconds);

                    } else {
                        // If file is newer than what is in the browser cache, or is not in cache, serve the file
                        serveStaticFile(reqURI, hashKey, staticResourceFile, lastModifiedEpochSeconds, ctx);

                        Log.fine(request.getRequestor() + "\t" + origReqMethod + "\t" + reqURI + "\tfile://"
                                + staticResourceFile.getPath() + "\t" + HttpResponseStatus.OK + "\t"
                                + (System.currentTimeMillis() - request.getReqReceivedTimeEpochMillis()) + " msec");

                        // Finished request
                        return;
                    }
                }
            }

            // ------------------------------------------------------------------------------
            // Handle GET or POST requests
            // ------------------------------------------------------------------------------

            ZonedDateTime timeNow = null;

            // If an error response hasn't yet been generated and this is a (non-static-file) GET or POST request,
            // then call the get() or post() method for the route handler bound to the request URI to obtain the
            // response object.
            boolean hashTheResponse = false;
            long hashKeyRemainingAgeSeconds = 0;
            if (response == null && authorizedRoute != null) {

                // ----------------------------------
                // See if response should be hashed
                // ----------------------------------

                // For hashed *non-file* URIs, the actual last modified timestamp of dynamically-served
                // content can't be read directly, so read the last modified timestamp stored for the
                // previously hashed version in the CacheExtension class, as long as the max age of the
                // cached version hasn't been exceeded, and see if the last modified timestamp is more
                // recent than the version cached in the browser.
                //
                // The important ramification of this is that when the resource identified by the non-file
                // URI changes, the CacheExtension class must be notified of that change (including in cases
                // where the database is modified by another database client) if the modified version should
                // start being served at a new hash URI immediately, otherwise the web client connected to
                // this web server will continue to serve old resources until the max age of the cached
                // content is exceeded.
                if (isHashURI) {
                    HashInfo hashInfo = CacheExtension.getHashInfo(reqURI);
                    if (hashInfo != null) {
                        long lastModifiedEpochSeconds = hashInfo.getLastModifiedEpochSeconds();
                        timeNow = ZonedDateTime.now();
                        long timeNowEpochSeconds = timeNow.toEpochSecond();

                        long maxAgeSeconds = authorizedRoute.getMaxAgeSeconds();
                        hashKeyRemainingAgeSeconds = lastModifiedEpochSeconds + maxAgeSeconds - timeNowEpochSeconds;

                        if (maxAgeSeconds == 0) {
                            // Content is not hash-cached
                            hashKeyRemainingAgeSeconds = 0;
                        }

                        if (maxAgeSeconds > 0 && hashKeyRemainingAgeSeconds <= 0) {
                            // Resource has expired -- call the route handler to generate a new response rather
                            // than serving a Not Modified response, and schedule the response to be hashed or
                            // re-hashed once the response has been generated.
                            hashTheResponse = true;

                            // Reset the expiry time at the requested number of seconds in the future
                            hashKeyRemainingAgeSeconds = maxAgeSeconds;

                        } else if (!request.cachedVersionIsOlderThan(lastModifiedEpochSeconds)) {
                            // Resource has not expired in cache, but client has requested it anyway.
                            // However, resource has not been modified since it was last hashed --
                            // return Not Modified.
                            response = new NotModifiedResponse(lastModifiedEpochSeconds);

                        } else {
                            // Resource has not expired in cache, but client has requested it anyway.
                            // Resource *has* been modified since it was last hashed -- serve it the
                            // normal way using the route handler, but don't hash the response, since
                            // it has not expired yet.
                        }
                    } else {
                        // There is no original URI matching this hash URI, so the hash key was stale
                        // (i.e. a URI whose hashcode has been spoofed, or a very old hashcode from
                        // the previous time the server was run), but we still got a valid request URI
                        // by stripping away the hash code, so that is served below in the normal way.
                    }
                }

                // If the response wasn't just set to "Not Modified" above, serve the request
                if (response == null) {

                    // -----------------------------------------------------------------
                    // Call the route handler for this request, generating the response
                    // -----------------------------------------------------------------

                    response = getResponseForRoute(authorizedRoute, request, user);

                    if (response == null) {
                        // Should not happen
                        throw new RuntimeException("Didn't generate a response");
                    }

                }

            }
            if (response == null) {
                // Should not happen
                throw new RuntimeException("Didn't generate a response");
            }

            // ------------------------------------------------------------------------------------
            // Serve an HTTP result (except in the case of static files, they were served already)
            // ------------------------------------------------------------------------------------

            // Turn the Response object into an HttpResponse object and serve it to the user over Netty.
            if (timeNow == null) {
                timeNow = ZonedDateTime.now();
            }

            // Serve the response to the client 
            serveHttpResponse(reqURI, response, isHEAD, request.acceptEncodingGzip(),//
                    timeNow, hashTheResponse, hashKeyRemainingAgeSeconds, hashKey, ctx);

            // Log the request and response
            HttpResponseStatus status = response.getStatus();
            String logMsg =
                    request.getRequestor() + "\t" + origReqMethod + "\t" + reqURI
                            + (request.getMethod() == origReqMethod ? "" : "\t" + request.getMethod()) + "\t" + status
                            + "\t" + (System.currentTimeMillis() - request.getReqReceivedTimeEpochMillis()) + " msec";
            if (status == HttpResponseStatus.OK //
                    || status == HttpResponseStatus.NOT_MODIFIED //
                    || status == HttpResponseStatus.FOUND //
                    || (status == HttpResponseStatus.NOT_FOUND //
                    && (reqURI.equals("favicon.ico") || reqURI.endsWith("/favicon.ico")))) {
                // Log at level "fine" for non-errors, or 404 for favicon
                Log.fine(logMsg);
            } else {
                // Log at level "warning" for errors, or 404 for non-favicon
                Log.warningWithoutCallerRef(logMsg);
            }

        } finally {
            if (requestComplete) {
                // Finished request -- destroy the multipart decoder and remove temporary files
                destroyDecoder();
            }
        }
    }

    // -----------------------------------------------------------------------------------------------------------------

    @Override
    public void exceptionCaught(ChannelHandlerContext ctx, Throwable cause) throws Exception {
        // Release HTTP decoder resources, including any file uploads that were received in a POST
        // request and stored in /tmp
        destroyDecoder();

        if (cause instanceof NotSslRecordException) {
            ctx.channel().close();
            return;
        }
        if ("Connection reset by peer".equals(cause.getMessage())) {
            // (No need to log the backtrace in this case)
            // Log.info(cause.getMessage());            
        } else {
            // Log exception with backtrace
            Log.exception("Uncaught exception", cause);
        }
        if (ctx.channel().isActive()) {
            sendHttpErrorResponse(ctx, null, new DefaultFullHttpResponse(HttpVersion.HTTP_1_1,
                    HttpResponseStatus.INTERNAL_SERVER_ERROR));
        }
    }
}
