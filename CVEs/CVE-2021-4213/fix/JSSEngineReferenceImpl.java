package org.mozilla.jss.ssl.javax;

import java.io.InputStream;
import java.io.OutputStream;
import java.net.ServerSocket;
import java.net.Socket;
import java.nio.ByteBuffer;
import java.nio.channels.Channels;
import java.nio.channels.WritableByteChannel;
import java.security.PublicKey;

import javax.net.ssl.SSLEngineResult;
import javax.net.ssl.SSLException;
import javax.net.ssl.SSLHandshakeException;
import javax.net.ssl.X509ExtendedTrustManager;
import javax.net.ssl.X509TrustManager;

import org.mozilla.jss.nss.BadCertHandler;
import org.mozilla.jss.nss.Buffer;
import org.mozilla.jss.nss.BufferProxy;
import org.mozilla.jss.nss.Cert;
import org.mozilla.jss.nss.CertAuthHandler;
import org.mozilla.jss.nss.PR;
import org.mozilla.jss.nss.PRErrors;
import org.mozilla.jss.nss.PRFDProxy;
import org.mozilla.jss.nss.SSL;
import org.mozilla.jss.nss.SSLErrors;
import org.mozilla.jss.nss.SSLFDProxy;
import org.mozilla.jss.nss.SSLPreliminaryChannelInfo;
import org.mozilla.jss.nss.SecurityStatusResult;
import org.mozilla.jss.pkcs11.PK11Cert;
import org.mozilla.jss.provider.javax.crypto.JSSNativeTrustManager;
import org.mozilla.jss.ssl.SSLAlertDescription;
import org.mozilla.jss.ssl.SSLAlertEvent;
import org.mozilla.jss.ssl.SSLAlertLevel;
import org.mozilla.jss.ssl.SSLCipher;
import org.mozilla.jss.ssl.SSLHandshakeCompletedEvent;
import org.mozilla.jss.ssl.SSLVersion;
import org.mozilla.jss.ssl.SSLVersionRange;

/**
 * The reference JSSEngine implementation.
 *
 * This JSSEngine implementation is a reference for future JSSEngine
 * implementations, providing a pure-Java overview of what should happen at
 * each step of the init, wrap, and unwrap calls.
 *
 * This implementation allows for extended debug logging, but also debug
 * packet logging. The latter writes out packets sent via wrap(...) and
 * received from unwrap(...) to a port on localhost. This allows one to easily
 * attach Wireshark or tcpdump and inspect the TLS packets, even if errors
 * occur during the test suite (where packets aren't sent over the wire by
 * default). This maintains the client/server relationship, and are logged
 * as being from the appropriate side of the TLS connection.
 */
public class JSSEngineReferenceImpl extends JSSEngine {
    /**
     * Faked peer information that we pass to the underlying BufferPRFD
     * implementation.
     *
     * This is used by NSS for session resumption. However, because we
     * don't have the exact peer information at the JSSEngine level, at
     * best we can guess.
     */
    private String peer_info;

    /**
     * Whether or not the underlying ssl_fd is closed or not.
     *
     * Because the socket isn't open yet, we set it to true, to indicate
     * that no data can be sent or received.
     */
    private boolean closed_fd = true;

    /**
     * Data to be read by the NSS SSL implementation; data from the peer.
     */
    private BufferProxy read_buf;

    /**
     * Data written by the NSS SSL implementation; data sent to the peer.
     */
    private BufferProxy write_buf;

    /**
     * Number of times heuristic has not matched the current state.
     *
     * Because this JSSEngine uses a heuristic for determining when the
     * handshake is completed (or, when we need to switch from WRAP to
     * UNWRAP), and the heuristic is sometimes wrong, we track how many
     * times it is in an unknown state. When we hit some internal
     * threshold, we swap states.
     */
    private int unknown_state_count;

    /**
     * Whether or not to step the handshake.
     */
    private boolean step_handshake;

    /**
     * Whether or not a FINISHED handshake status has been returned to our
     * caller.
     *
     * Because this JSSEngine implementation re-enters the
     * updateHandshakeState() method potentially multiple times during a
     * single call to wrap() or unwrap(), we need to know whether or not
     * the top-level call has returned a FINISHED result. If it hasn't,
     * we want to keep the state on FINISHED until it has been returned,
     * otherwise we'll skip straight to NOT_HANDSHAKING, confusing our
     * peer.
     */
    private boolean returned_finished;

    /**
     * Value of the SSLException we've encountered.
     */
    private SSLException ssl_exception;

    /**
     * Whether or not we've seen an ssl exception.
     *
     * Note that, when the exception ultimately gets thrown to the caller,
     * ssl_exception will be NULLed; this tracks whether or not the connection
     * has failed previously for some reason.
     */
    private boolean seen_exception;

    // In this reference implementation, we allow logging of (encrypted) data
    // to a Socket for ease of testing. By default, this socket is disabled.
    private int debug_port;
    private ServerSocket ss_socket;
    private Socket s_socket;
    private Socket c_socket;
    private InputStream s_istream;
    private OutputStream s_ostream;
    private InputStream c_istream;
    private OutputStream c_ostream;

    /**
     * Internal name for this JSSEngine instance; most commonly used during
     * testing.
     */
    private String name;

    /**
     * Automatically generated prefix for debug information.
     */
    private String prefix = "";

    /**
     * Runnable task; this performs certificate validation against user-provided
     * TrustManager instances, passing the result back to NSS.
     */
    private CertValidationTask task;

    public JSSEngineReferenceImpl() {
        super();

        // We were given no hints about our peer so we have no information
        // to signal to NSS for session resumption.
        peer_info = null;

        debug("JSSEngine: constructor()");
    }

    public JSSEngineReferenceImpl(String peerHost, int peerPort) {
        super(peerHost, peerPort);

        // Signal host and port for session resumption. Only do it when we've
        // been given valid information.
        if (peerHost != null && peerPort != 0) {
            peer_info = peerHost + ":" + peerPort;
        }

        // Massive hack for compatibility. In particular, Java usually
        // specifies the peer information here. NSS uses SSL_SetURL not only
        // for hostname verification, but also for SNI (!!) on the client.
        // This means that there's no way to indicate (to those servers like
        // google.com which require SNI) hostname for the client WITHOUT
        // also validating the hostname at certificate verification time.
        // Because the certificate hostname explicitly isn't provided (per
        // JCA specification) for validation, this might break other clients
        // which don't provide this information. However, the alternative is
        // that we never add SNI indication, ever.
        //
        // Specifically, this breaks a dead-simple Apache HTTP Components
        // client:
        //
        //     CloseableHttpClient client = HttpClients.createDefault();
        //     HttpGet request = new HttpGet("https://google.com/");
        //     HttpResponse response = client.execute(request);
        //     System.out.println(response);
        //
        // Without this, we have no way for the above to work.
        setHostname(peerHost);

        debug("JSSEngine: constructor(" + peerHost + ", " + peerPort + ")");
    }

    public JSSEngineReferenceImpl(String peerHost, int peerPort,
                     org.mozilla.jss.crypto.X509Certificate localCert,
                     org.mozilla.jss.crypto.PrivateKey localKey) {
        super(peerHost, peerPort, localCert, localKey);

        // Signal host and port for session resumption. Only do it when we've
        // been given valid information.
        if (peerHost != null && peerPort != 0) {
            peer_info = peerHost + ":" + peerPort;
        }

        // See above.
        setHostname(peerHost);

        prefix = prefix + "[" + peer_info + "] ";

        debug("JSSEngine: constructor(" + peerHost + ", " + peerPort + ", " + localCert + ", " + localKey + ")");
    }

    private void debug(String msg) {
        logger.debug(prefix + msg);
    }

    private void info(String msg) {
        logger.info(prefix + msg);
    }

    private void warn(String msg) {
        logger.warn(prefix + msg);
    }

    /**
     * Set the name of this JSSEngine instance, to be printed in logging calls.
     *
     * This helps when debugging output from multiple JSSEngine instances at
     * the same time, such as within the JSS test suite.
     */
    public void setName(String name) {
        this.name = name;
        prefix = "[" + this.name + "] " + prefix;
    }

    private void init() throws SSLException {
        debug("JSSEngine: init()");

        // Initialize our JSSEngine when we begin to handshake; otherwise,
        // calls to Set<Option>(...) won't be processed if we initialize it
        // too early; some of these need to be applied at initialization time
        // in order to take affect.

        // Ensure we don't leak ssl_fd if we're called multiple times.
        if (ssl_fd != null && !closed_fd) {
            is_inbound_closed = true;
            is_outbound_closed = true;
            cleanup();
        }

        ssl_fd = null;

        // Create buffers for interacting with NSS.
        createBuffers();
        createBufferFD();

        // Initialize the appropriate end of this connection.
        if (as_server) {
            initServer();
        } else {
            initClient();
        }

        // Apply the requested cipher suites and protocols.
        applyProtocols();
        applyCiphers();
        applyConfig();

        // Apply hostname information (via setURL). Note that this is an
        // extension to SSLEngine for use with NSS; we don't always get this
        // information and so need to work around it sometimes. See
        // initClient() for the workaround.
        applyHosts();

        // Apply TrustManager(s) information for validating the peer's
        // certificate.
        applyTrustManagers();

        // Finally, set up any debug logging necessary.
        createLoggingSocket();
    }

    private void createBuffers() {
        debug("JSSEngine: createBuffers()");

        // If the buffers exist, destroy them and then recreate them.

        if (read_buf != null) {
            Buffer.Free(read_buf);
        }
        read_buf = Buffer.Create(BUFFER_SIZE);

        if (write_buf != null) {
            Buffer.Free(write_buf);
        }
        write_buf = Buffer.Create(BUFFER_SIZE);
    }

    private void createBufferFD() throws SSLException {
        debug("JSSEngine: createBufferFD()");

        // Create the basis for the ssl_fd from the pair of buffers we created
        // above.

        PRFDProxy fd;
        if (peer_info != null && peer_info.length() != 0) {
            // When we have peer information, indicate it via BufferPRFD so
            // that NSS can use it for session resumption.
            fd = PR.NewBufferPRFD(read_buf, write_buf, peer_info.getBytes());
        } else {
            fd = PR.NewBufferPRFD(read_buf, write_buf, null);
        }

        if (fd == null) {
            throw new SSLException("Error creating buffer-backed PRFileDesc.");
        }

        SSLFDProxy model = null;
        if (as_server) {
            // As a performance improvement, we can copy the server template
            // (containing the desired key and certificate) rather than
            // re-creating it from scratch. This saves a significant amount of
            // time during construction. The implementation lives in JSSEngine,
            // to be shared by all other JSSEngine implementations.
            model = getServerTemplate(cert, key);
        }

        // Initialize ssl_fd from the model Buffer-backed PRFileDesc.
        ssl_fd = SSL.ImportFD(model, fd);
        if (ssl_fd == null) {
            PR.Close(fd);
            throw new SSLException("Error creating SSL socket on top of buffer-backed PRFileDesc.");
        }

        fd = null;
        closed_fd = false;

        // Turn on SSL Alert Logging for the ssl_fd object.
        int ret = SSL.EnableAlertLogging(ssl_fd);
        if (ret == SSL.SECFailure) {
            throw new SSLException("Unable to enable SSL Alert Logging on this SSLFDProxy instance.");
        }

        // Turn on notifications of handshake completion. This is the best
        // source of this information, compared to SSL_SecurityStatus().on;
        // the latter can indicate "on" before the final FINISHED method has
        // been sent.
        ret = SSL.EnableHandshakeCallback(ssl_fd);
        if (ret == SSL.SECFailure) {
            throw new SSLException("Unable to enable SSL Handshake Callback on this SSLFDProxy instance.");
        }

        // Pass this ssl_fd to the session object so that we can use
        // SSL methods to invalidate the session.
    }

    private void initClient() throws SSLException {
        debug("JSSEngine: initClient()");

        if (cert != null && key != null) {
            // NSS uses a callback to check for the client certificate; we
            // assume we have knowledge of it ahead of time and set it
            // directly on our SSLFDProxy instance.
            //
            // In the future, we could use a KeyManager for inquiring at
            // selection time which certificate to use.
            debug("JSSEngine.initClient(): Enabling client auth: " + cert);
            ssl_fd.SetClientCert(cert);
            if (SSL.AttachClientCertCallback(ssl_fd) != SSL.SECSuccess) {
                throw new SSLException("Unable to attach client certificate auth callback.");
            }
        }

        if (hostname == null) {
            // When we're a client with no hostname, assume we're running
            // under standard JDK JCA semantics with no hostname available.
            // Bypass NSS's hostname check by adding a BadCertHandler, which
            // check ONLY for the bad hostname error and allows it. This is
            // safe since this is the LAST check in every (NSS, PKIX, and
            // JSS) certificate validation step. And, under JCA semantics, we
            // can assume the caller checks the hostname for us.
            ssl_fd.badCertHandler = new BypassBadHostname(ssl_fd, 0);
            if (SSL.ConfigSyncBadCertCallback(ssl_fd) != SSL.SECSuccess) {
                throw new SSLException("Unable to attach bad cert callback.");
            }
        }
    }

    private void initServer() throws SSLException {
        debug("JSSEngine: initServer()");

        // The only time cert and key are strictly required are when we're
        // creating a server SSLEngine.
        if (cert == null || key == null) {
            throw new IllegalArgumentException("JSSEngine: must be initialized with server certificate and key!");
        }

        debug("JSSEngine.initServer(): " + cert);
        debug("JSSEngine.initServer(): " + key);

        session.setLocalCertificates(new PK11Cert[]{ cert } );

        // Create a small server session cache.
        //
        // TODO: Make this configurable.
        initializeSessionCache(1, 100, null);

        configureClientAuth();
    }

    private void configureClientAuth() throws SSLException {
        debug("SSLFileDesc: " + ssl_fd);

        // Only specify these on the server side as they affect what we
        // want from the remote peer in NSS. In the server case, this is
        // client auth, but if we were to set these on the client, it would
        // affect server auth.
        if (SSL.OptionSet(ssl_fd, SSL.REQUEST_CERTIFICATE, want_client_auth || need_client_auth ? 1 : 0) == SSL.SECFailure) {
            throw new SSLException("Unable to configure SSL_REQUEST_CERTIFICATE option: " + errorText(PR.GetError()));
        }

        if (SSL.OptionSet(ssl_fd, SSL.REQUIRE_CERTIFICATE, need_client_auth ? SSL.REQUIRE_ALWAYS : 0) == SSL.SECFailure) {
            throw new SSLException("Unable to configure SSL_REQUIRE_CERTIFICATE option: " + errorText(PR.GetError()));
        }
    }

    @Override
    protected void reconfigureClientAuth() {
        if (ssl_fd == null || !as_server) {
            return;
        }

        // This method is called by JSSEngine's setNeedClientAuth and
        // setWantClientAuth to inform us of a change in value here. When
        // we've already configured ssl_fd and we're a server, we need to
        // inform NSS of this change; this usually indicates Post-Handshake
        // Authentication is required.

        try {
            configureClientAuth();
        } catch (SSLException se) {
            // We cannot throw SSLException from this helper because it
            // is called from setNeedClientAuth and setWantClientAuth,
            // both of which don't disclose SSLException.
            throw new RuntimeException(se.getMessage(), se);
        }
    }

    private void applyCiphers() throws SSLException {
        debug("JSSEngine: applyCiphers()");
        // Enabled the ciphersuites specified by setEnabledCipherSuites(...).
        // When this isn't called, enabled_ciphers will be null, so we'll just
        // use whatever is enabled by default.
        if (enabled_ciphers == null) {
            return;
        }

        // We need to disable the suite if it isn't present in the list of
        // suites above. Be lazy about it for the time being and disable all
        // cipher suites first.
        for (SSLCipher suite : SSLCipher.values()) {
            if (SSL.CipherPrefSet(ssl_fd, suite.getID(), false) == SSL.SECFailure) {
                // warn("Unable to set cipher suite preference for " + suite.name() + ": " + errorText(PR.GetError()));
            }
        }

        // Only enable these particular suites. When a cipher suite can't be
        // enabled it is most likely due to local policy. Log it. Also log
        // which ciphers were successfully enabled for debugging purposes.
        for (SSLCipher suite : enabled_ciphers) {
            if (suite == null) {
                continue;
            }

            if (SSL.CipherPrefSet(ssl_fd, suite.getID(), true) == SSL.SECFailure) {
                warn("Unable to enable cipher suite " + suite + ": " + errorText(PR.GetError()));
            } else {
                debug("Enabled cipher suite " + suite + ": " + errorText(PR.GetError()));
            }
        }
    }

    private void applyProtocols() throws SSLException {
        debug("JSSEngine: applyProtocols() min_protocol=" + min_protocol + " max_protocol=" + max_protocol);
        // Enable the protocols only when both a maximum and minimum protocol
        // version are specified.
        if (min_protocol == null || max_protocol == null) {
            debug("JSSEngine: applyProtocols() - missing min_protocol or max_protocol; using defaults");
            return;
        }

        // We should bound this range by crypto-policies in the future to
        // match the current behavior. However, Tomcat already bounds
        // what we set in the server.xml config by what the JSSEngine
        // indicates it supports. Because we only indicate we support
        // what is allowed under crypto-policies, it effective does
        // this bounding for us.
        SSLVersionRange vrange = new SSLVersionRange(min_protocol, max_protocol);
        if (SSL.VersionRangeSet(ssl_fd, vrange) == SSL.SECFailure) {
            throw new SSLException("Unable to set version range: " + errorText(PR.GetError()));
        }
    }

    private void applyConfig() throws SSLException {
        debug("JSSEngine: applyConfig()");
        for (Integer key : config.keySet()) {
            Integer value = config.get(key);

            debug("Setting configuration option: " + key + "=" + value);
            if (SSL.OptionSet(ssl_fd, key, value) != SSL.SECSuccess) {
                throw new SSLException("Unable to set configuration value: " + key + "=" + value);
            }
        }
    }

    private void applyHosts() throws SSLException {
        debug("JSSEngine: applyHosts()");

        // This is most useful for the client end of the connection; this
        // specifies what to match the server's certificate against.
        if (hostname != null) {
            if (SSL.SetURL(ssl_fd, hostname) == SSL.SECFailure) {
                throw new SSLException("Unable to configure server hostname: " + errorText(PR.GetError()));
            }
        }
    }

    private void applyTrustManagers() throws SSLException {
        debug("JSSEngine: applyTrustManagers()");

        // If none have been specified, exit early.
        if (trust_managers == null || trust_managers.length == 0) {
            // Use the default NSS certificate authentication handler. We
            // don't need to do anything to use it.
            debug("JSSEngine: no TrustManagers to apply.");
            return;
        }

        // Determine which configuration to use for checking certificates. Our
        // options are a Native trust manager (most performant) or using a set
        // of X509TrustManagers.
        if (trust_managers.length == 1 && trust_managers[0] instanceof JSSNativeTrustManager) {
            // This is a dummy TrustManager. It signifies that we should call
            // SSL.ConfigJSSDefaultCertAuthCallback(...) on this SSL
            // PRFileDesc pointer, letting us utilize the same certificate
            // validation logic that SSLSocket had.
            debug("JSSEngine: applyTrustManagers() - adding Native TrustManager");
            if (SSL.ConfigJSSDefaultCertAuthCallback(ssl_fd) == SSL.SECFailure) {
                throw new SSLException("Unable to configure JSSNativeTrustManager on this JSSengine: " + errorText(PR.GetError()));
            }
            return;
        }

        if (as_server) {
            // We need to manually invoke the async cert auth handler. However,
            // SSLFDProxy makes this easy for us: our CertAuthHandler derives
            // from Runnable, so we can reuse it here as well. We can create
            // it ahead of time though. In this case, checkNeedCertValidation()
            // is never called.
            ssl_fd.certAuthHandler = new CertValidationTask(ssl_fd);

            if (SSL.ConfigSyncTrustManagerCertAuthCallback(ssl_fd) == SSL.SECFailure) {
                throw new SSLException("Unable to configure TrustManager validation on this JSSengine: " + errorText(PR.GetError()));
            }
        } else {
            // Otherwise, we need a hook from NSS into the SSLFDProxy.
            //
            // This hook executes all TrustManagers and if any exception
            // occurs, we'll turn it into the proper response within NSS.
            if (SSL.ConfigAsyncTrustManagerCertAuthCallback(ssl_fd) == SSL.SECFailure) {
                throw new SSLException("Unable to configure TrustManager validation on this JSSengine: " + errorText(PR.GetError()));
            }
        }
    }

    private void createLoggingSocket() throws SSLException {
        if (debug_port == 0) {
            return;
        }

        try {
            ss_socket = new ServerSocket(debug_port);
            ss_socket.setReuseAddress(true);
            ss_socket.setReceiveBufferSize(BUFFER_SIZE);

            c_socket = new Socket(ss_socket.getInetAddress(), ss_socket.getLocalPort());
            c_socket.setReuseAddress(true);
            c_socket.setReceiveBufferSize(BUFFER_SIZE);
            c_socket.setSendBufferSize(BUFFER_SIZE);

            s_socket = ss_socket.accept();
            s_socket.setReuseAddress(true);
            s_socket.setReceiveBufferSize(BUFFER_SIZE);
            s_socket.setSendBufferSize(BUFFER_SIZE);

            s_istream = s_socket.getInputStream();
            s_ostream = s_socket.getOutputStream();

            c_istream = c_socket.getInputStream();
            c_ostream = c_socket.getOutputStream();
        } catch (Exception e) {
            throw new SSLException("Unable to enable debug socket logging! " + e.getMessage(), e);
        }
    }

    private void loggingSocketConsumeAllBytes() {
        try {
            int available = s_istream.available();
            byte[] data = new byte[available];
            s_istream.read(data);
        } catch (Exception e) {}

        try {
            int available = c_istream.available();
            byte[] data = new byte[available];
            c_istream.read(data);
        } catch (Exception e) {}
    }

    @Override
    public void beginHandshake() throws SSLException {
        debug("JSSEngine: beginHandshake()");

        // We assume beginHandshake(...) is the entry point for initializing
        // the buffer. In particular, wrap(...) / unwrap(...) *MUST* call
        // beginHandshake(...) if ssl_fd == null.

        // ssl_fd == null <-> we've not initialized anything yet.

        // TLS begins with the client sending a CLIENT_HELLO to the server;
        // this means that the server needs to unwrap first and the client
        // needs to wrap first; hence unwrap = as_server. However, if we're
        // trying to renegotiate this changes. See when ssl_fd != null below.
        boolean unwrap = as_server;

        if (ssl_fd == null) {
            // Initialize and create ssl_fd. Throws various RuntimeExceptions
            // when creation and configuration fails.
            init();
            assert(ssl_fd != null);

            // Reset the handshake status, using the new socket and
            // configuration which was just created. This ensures that
            // we'll attempt to handshake when ForceHandshake is called.
            if (SSL.ResetHandshake(ssl_fd, as_server) == SSL.SECFailure) {
                throw new RuntimeException("Unable to begin handshake: " + errorText(PR.GetError()));
            }
        } else {
            // When ssl_fd exists, we need to re-handshake. Usually, the
            // server initiates the conversation (especially when we want
            // to upgrade to requiring client auth from not requiring it).
            //
            // This means that when we're a (as_server == true), we should
            // now wrap, rather than unwrap. So, negate unwrap.
            unwrap = !as_server;

            // TLS v1.3 differs from all previous versions in that it removed
            // the ability to completely rehandshake. This makes the first
            // portion more complicated than the latter.
            if (session.getSSLVersion() == SSLVersion.TLS_1_3) {
                // We only send the certificate request as a server when we
                // need client auth. Otherwise, we'll have to issue a rekey
                // request.
                boolean send_certificate_request = as_server && need_client_auth;
                if (send_certificate_request) {
                    if (SSL.SendCertificateRequest(ssl_fd) == SSL.SECFailure) {
                        throw new RuntimeException("Unable to issue certificate request on TLSv1.3: " + errorText(PR.GetError()));
                    }
                } else {
                    // Our best guess at what the user wants is to update
                    // their keys. They don't need client authentication but
                    // they explicitly called beginHandshake() again.
                    if (SSL.KeyUpdate(ssl_fd, false) == SSL.SECFailure) {
                        throw new RuntimeException("Unable to request a new key on TLSv1.3: " + errorText(PR.GetError()));
                    }
                }
            } else {
                // On older protocol versions, this is easier: just issue a
                // new handshake request. This is different from
                // ResetHandshake as for security reasons, the semantics have
                // to differ.
                if (SSL.ReHandshake(ssl_fd, true) == SSL.SECFailure) {
                    throw new RuntimeException("Unable to rehandshake: " + errorText(PR.GetError()));
                }
            }
        }

        // Make sure we reset the handshake completion status in order for the
        // callback to work correctly.
        ssl_fd.handshakeComplete = false;

        // This leaves setting internal variables for HandshakeStatus and
        // the reporting up from SSLEngine.
        if (unwrap) {
            handshake_state = SSLEngineResult.HandshakeStatus.NEED_UNWRAP;
        } else {
            handshake_state = SSLEngineResult.HandshakeStatus.NEED_WRAP;
        }

        // We've begun a new handshake; make sure we step it and reset
        // our unknown state count to zero.
        step_handshake = true;
        unknown_state_count = 0;

        // Lastly, each handshake must return a FINISHED individually,
        // reset returned_finished to false.
        returned_finished = false;
    }

    @Override
    public void closeInbound() {
        debug("JSSEngine: closeInbound()");

        if (!is_inbound_closed && ssl_fd != null && !closed_fd) {
            // Send PR_SHUTDOWN_RCV only once. Additionally, this call
            // crashes when ssl_fd == NULL or when the socket is already
            // closed.
            PR.Shutdown(ssl_fd, PR.SHUTDOWN_RCV);
        }

        is_inbound_closed = true;
    }

    @Override
    public void closeOutbound() {
        debug("JSSEngine: closeOutbound()");

        if (!is_outbound_closed && ssl_fd != null && !closed_fd) {
            // Send PR_SHUTDOWN_SEND only once. Additionally, this call
            // crashes when ssl_fd == NULL or when the socket is already
            // closed.
            PR.Shutdown(ssl_fd, PR.SHUTDOWN_SEND);
        }

        is_outbound_closed = true;
    }

    public String getHostname() {
        return hostname;
    }

    @Override
    public Runnable getDelegatedTask() {
        debug("JSSEngine: getDelegatedTask()");

        // task can either contain a task instance or null; task gets
        // populated also during getHandshakeStatus(), wrap(), and
        // unwrap(). Since wrap()/unwrap() populate the task early (if
        // one is needed) -- but can return NEED_TASK later with null
        // task, this could result in a stall if we didn't also check
        // here. Best to do it (it is cheap if one isn't necessary),
        // so that way we always return up-to-date information.
        if (ssl_fd != null) {
            // Return code is a boolean, whether or not we have a task.
            // We can safely ignore it here.
            checkNeedCertValidation();
        }

        return task;
    }

    private boolean checkNeedCertValidation() {
        debug("JSSEngine: checkNeedCertValidation()");
        if (task != null) {
            if (!task.finished) {
                // Already created runnable task; exit with true status to
                // show it still needs to be run.
                debug("JSSEngine: checkNeedCertValidation() - task not done");
                return true;
            }

            debug("JSSEngine: checkNeedCertValidation() - task done with code " + task.result);

            // Since the task has finished, we now need to inform NSS about
            // the results of our certificate validation step.
            if (SSL.AuthCertificateComplete(ssl_fd, task.result) != SSL.SECSuccess) {
                String msg = "Got unexpected failure finishing cert ";
                msg += "authentication in NSS. Returned code ";
                msg += task.result;
                throw new RuntimeException(msg);
            }

            // After checking certificates, our best guess will be that we
            // need to run wrap again. This is because we either need to
            // inform the caller of an error that occurred, or continue the
            // handshake. Worst case, we'll call updateHandshakeState() and
            // it'll correct our mistake eventually.

            debug("JSSEngine: checkNeedCertValidation() - task done, removing");

            task = null;
            handshake_state = SSLEngineResult.HandshakeStatus.NEED_WRAP;
            ssl_fd.needCertValidation = false;

            return false;
        }

        if (ssl_fd == null) {
            // If we don't have a SSLFDProxy instance, nothing we can do but
            // skip checking if the task exists. Return false to show that
            // we don't yet have a runnable task.
            debug("JSSEngine: checkNeedCertValidation() - no ssl_fd");
            return false;
        }

        if (!ssl_fd.needCertValidation) {
            // We don't yet need certificate validation. Don't create a
            // runnable task for now.
            debug("JSSEngine: checkNeedCertValidation() - no need for cert validation");
            return false;
        }

        debug("JSSEngine: checkNeedCertValidation() - creating task");

        // OK, time to create our runnable task.
        task = new CertValidationTask(ssl_fd);

        // Update our handshake state so we know what to do next.
        handshake_state = SSLEngineResult.HandshakeStatus.NEED_TASK;

        return true;
    }

    @Override
    public SSLEngineResult.HandshakeStatus getHandshakeStatus() {
        debug("JSSEngine: getHandshakeStatus()");

        // If task is NULL, we need to update the state to check if the
        // task has been "run". Even if it isn't, it would be good to
        // update the status here as well. However, we DO NOT want to
        // call updateHandshakeState() in the event we have a task to
        // run: we need to run it still!
        if (checkNeedCertValidation()) {
            return handshake_state;
        }

        // Always update the handshake state; this ensures that we catch
        // looping due to missing data and flip our expected direction.
        updateHandshakeState();

        return handshake_state;
    }

    @Override
    public SecurityStatusResult getStatus() {
        if (ssl_fd == null) {
            return null;
        }

        return SSL.SecurityStatus(ssl_fd);
    }

    /**
     * Enable writing of encrypted TLS traffic to the specified port in a
     * client-server relationship (mirroring the actual role of this
     * SSLEngine) to enable debugging with Wireshark.
     */
    public void enableSafeDebugLogging(int port) {
        debug_port = port;
    }

    private int computeSize(ByteBuffer[] buffers, int offset, int length) throws IllegalArgumentException {
        debug("JSSEngine: computeSize()");
        int result = 0;

        if (buffers == null || buffers.length == 0) {
            debug("JSSEngine.compueSize(): no buffers - result=" + result);
            return result;
        }

        // Semantics of arguments:
        //
        // - buffers is where we're reading/writing application data.
        // - offset is the index of the first buffer we read/write to.
        // - length is the total number of buffers we read/write to.
        //
        // We use a relative index and an absolute index to handle these
        // constraints.
        for (int rel_index = 0; rel_index < length; rel_index++) {
            int index = offset + rel_index;
            if (index >= buffers.length) {
                throw new IllegalArgumentException("offset (" + offset + ") or length (" + length + ") exceeds contract based on number of buffers (" + buffers.length + ")");
            }

            if (rel_index == 0 && buffers[index] == null) {
                // If our first buffer is null, assume the rest are and skip
                // everything else. This commonly happens when null is passed
                // as the src parameter to wrap or when null is passed as the
                // dst parameter to unwrap.
                debug("JSSEngine.computeSize(): null first buffer - result=" + result);
                return result;
            }

            if (buffers[index] == null) {
                throw new IllegalArgumentException("Buffer at index " + index + " is null.");
            }

            result += buffers[index].remaining();
        }

        debug("JSSEngine.computeSize(): result=" + result);

        return result;
    }

    private int putData(byte[] data, ByteBuffer[] buffers, int offset, int length) {
        debug("JSSEngine: putData()");
        // Handle the rather unreasonable task of moving data into the buffers.
        // We assume the buffer parameters have already been checked by
        // computeSize(...); that is, offset/length contracts hold and that
        // each buffer in the range is non-null.
        //
        // We also assume that data.length does not exceed the total number
        // of bytes the buffers can hold; this is what computeSize(...)'s
        // return value should ensure. This directly means that the inner
        // while loop won't exceed the bounds of offset+length.

        int buffer_index = offset;
        int data_index = 0;

        if (data == null || buffers == null) {
            return data_index;
        }

        for (data_index = 0; data_index < data.length;) {
            // Ensure we have have a buffer with capacity.
            while ((buffers[buffer_index] == null || buffers[buffer_index].remaining() <= 0) &&
                    (buffer_index < offset + length)) {
                buffer_index += 1;
            }
            if (buffer_index >= offset + length) {
                break;
            }

            // Compute the size of the put: it is the minimum of the space
            // remaining in this buffer and the bytes remaining in the data
            // array.
            int put_size = buffers[buffer_index].remaining();
            if (put_size > (data.length - data_index)) {
                put_size = data.length - data_index;
            }

            buffers[buffer_index].put(data, data_index, put_size);
            data_index += put_size;
        }

        return data_index;
    }

    private SSLException checkSSLAlerts() {
        debug("JSSEngine: Checking inbound and outbound SSL Alerts. Have " + ssl_fd.inboundAlerts.size() + " inbound and " + ssl_fd.outboundAlerts.size() + " outbound alerts.");

        // Prefer inbound alerts to outbound alerts.
        while (ssl_fd.inboundOffset < ssl_fd.inboundAlerts.size()) {
            SSLAlertEvent event = ssl_fd.inboundAlerts.get(ssl_fd.inboundOffset);
            ssl_fd.inboundOffset += 1;

            if (event.getLevelEnum() == SSLAlertLevel.WARNING && event.getDescriptionEnum() == SSLAlertDescription.CLOSE_NOTIFY) {
                debug("Got inbound CLOSE_NOTIFY alert");
                closeInbound();
            }

            debug("JSSEngine: Got inbound alert: " + event);

            // Fire inbound alert prior to raising any exception.
            fireAlertReceived(event);

            // Not every SSL Alert is fatal; toException() only returns a
            // SSLException on fatal instances. We shouldn't return NULL
            // early without checking all alerts.
            SSLException exception = event.toException();
            if (exception != null) {
                return exception;
            }
        }

        while (ssl_fd.outboundOffset < ssl_fd.outboundAlerts.size()) {
            SSLAlertEvent event = ssl_fd.outboundAlerts.get(ssl_fd.outboundOffset);
            ssl_fd.outboundOffset += 1;

            if (event.getLevelEnum() == SSLAlertLevel.WARNING && event.getDescriptionEnum() == SSLAlertDescription.CLOSE_NOTIFY) {
                debug("Sent outbound CLOSE_NOTIFY alert.");
                closeOutbound();
            }

            debug("JSSEngine: Got outbound alert: " + event);

            // Fire outbound alert prior to raising any exception. Note that
            // this still triggers after this alert is written to the output
            // wire buffer.
            fireAlertSent(event);

            SSLException exception = event.toException();
            if (exception != null) {
                return exception;
            }
        }

        return null;
    }

    private void updateHandshakeState() {
        debug("JSSEngine: updateHandshakeState()");

        // If we've previously seen an exception, we should just return
        // here; there's already an alert on the wire, so there's no point
        // in checking for new ones and/or stepping the handshake: it has
        // already failed.
        if (seen_exception) {
            return;
        }

        // If we're already done, we should check for SSL ALerts.
        if (!step_handshake && handshake_state == SSLEngineResult.HandshakeStatus.NOT_HANDSHAKING) {
            debug("JSSEngine.updateHandshakeState() - not handshaking");
            unknown_state_count = 0;

            ssl_exception = checkSSLAlerts();
            seen_exception = (ssl_exception != null);
            return;
        }

        // If we've previously finished handshaking, then move to
        // NOT_HANDSHAKING. Now is also a good time to check for any
        // alerts.
        if (!step_handshake && handshake_state == SSLEngineResult.HandshakeStatus.FINISHED) {
            debug("JSSEngine.updateHandshakeState() - FINISHED to NOT_HANDSHAKING");

            // Because updateHandshakeState() gets called multiple times within
            // a single wrap/unwrap invocation, we need to wait for the FINISHED
            // message to be returned (from wrap/unwrap) before moving it to
            // NOT_HANDSHAKING. Otherwise, callers will miss that the handshake
            // has completed. We aren't in an unknown state though and we don't
            // need to call SSL.ForceHandshake().
            if (returned_finished) {
                handshake_state = SSLEngineResult.HandshakeStatus.NOT_HANDSHAKING;
            }

            unknown_state_count = 0;

            ssl_exception = checkSSLAlerts();
            seen_exception = (ssl_exception != null);
            return;
        }

        // Since we're not obviously done handshaking, and the last time we
        // were called, we were still handshaking, step the handshake.
        debug("JSSEngine.updateHandshakeState() - forcing handshake");
        if (SSL.ForceHandshake(ssl_fd) == SSL.SECFailure) {
            int error_value = PR.GetError();

            if (error_value != PRErrors.WOULD_BLOCK_ERROR) {
                debug("JSSEngine.updateHandshakeState() - FATAL " + getStatus());

                ssl_exception = new SSLHandshakeException("Error duing SSL.ForceHandshake() :: " + errorText(error_value));
                seen_exception = true;

                handshake_state = SSLEngineResult.HandshakeStatus.NEED_WRAP;
                return;
            }
        }

        // Check if we've just finished handshaking.
        debug("JSSEngine.updateHandshakeState() - read_buf.read=" + Buffer.ReadCapacity(read_buf) + " read_buf.write=" + Buffer.WriteCapacity(read_buf) + " write_buf.read=" + Buffer.ReadCapacity(write_buf) + " write_buf.write=" + Buffer.WriteCapacity(write_buf));

        // Set NEED_WRAP when we have data to send to the client.
        if (Buffer.ReadCapacity(write_buf) > 0 && handshake_state != SSLEngineResult.HandshakeStatus.NEED_WRAP) {
            // Can't write; to read, we need to call wrap to provide more
            // data to write.
            debug("JSSEngine.updateHandshakeState() - can write " + Buffer.ReadCapacity(write_buf) + " bytes, NEED_WRAP to process");
            handshake_state = SSLEngineResult.HandshakeStatus.NEED_WRAP;
            unknown_state_count = 0;
            return;
        }

        // Delay the check to see if the handshake finished until after we
        // send the CLIENT FINISHED message and recieved the SERVER FINISHED
        // message if we're a client. Otherwise, wait to send SERVER FINISHED
        // message. This is because NSS thinks the handshake has finished
        // (according to SecurityStatusResult since it has sent the massage)
        // but we haven't yet gotten around to doing so if we're in a WRAP()
        // call.
        if (ssl_fd.handshakeComplete && Buffer.ReadCapacity(write_buf) == 0) {
            debug("JSSEngine.updateHandshakeState() - handshakeComplete is " + ssl_fd.handshakeComplete + ", so we've just finished handshaking");
            step_handshake = false;
            handshake_state = SSLEngineResult.HandshakeStatus.FINISHED;
            unknown_state_count = 0;

            // Only update peer certificate chain when we've finished
            // handshaking.
            try {
                PK11Cert[] peer_chain = SSL.PeerCertificateChain(ssl_fd);
                session.setPeerCertificates(peer_chain);
            } catch (Exception e) {
                String msg = "Unable to get peer's certificate chain: ";
                msg += e.getMessage();

                seen_exception = true;
                ssl_exception = new SSLException(msg, e);
            }

            // Also update our session information here.
            session.refreshData();

            // Finally, fire any handshake completed event listeners now.
            fireHandshakeComplete(new SSLHandshakeCompletedEvent(this));

            return;
        }

        if (Buffer.ReadCapacity(read_buf) == 0 && handshake_state != SSLEngineResult.HandshakeStatus.NEED_UNWRAP) {
            // Set NEED_UNWRAP when we have no data to read from the client.
            debug("JSSEngine.updateHandshakeState() - can read " + Buffer.ReadCapacity(read_buf) + " bytes, NEED_UNWRAP to give us more");
            handshake_state = SSLEngineResult.HandshakeStatus.NEED_UNWRAP;
            unknown_state_count = 0;
            return;
        }

        unknown_state_count += 1;
        if (unknown_state_count >= 4) {
            if (handshake_state == SSLEngineResult.HandshakeStatus.NEED_WRAP) {
                handshake_state = SSLEngineResult.HandshakeStatus.NEED_UNWRAP;
            } else {
                handshake_state = SSLEngineResult.HandshakeStatus.NEED_WRAP;
            }
            unknown_state_count = 1;
        }
    }

    private void logUnwrap(ByteBuffer src) {
        if (debug_port <= 0 || src == null || src.remaining() == 0) {
            return;
        }

        loggingSocketConsumeAllBytes();

        OutputStream stream = c_ostream;

        if (!as_server) {
            // An unwrap from the client means we write data to the outbound
            // side of the server socket.
            stream = s_ostream;
        }

        WritableByteChannel channel = Channels.newChannel(stream);

        int pos = src.position();
        try {
            debug("JSSEngine: logUnwrap() - writing " + src.remaining() + " bytes.");
            channel.write(src);
            stream.flush();
        } catch (Exception e) {
            throw new RuntimeException("Unable to log contents of unwrap's src to debug socket: " + e.getMessage(), e);
        } finally {
            src.position(pos);
        }
    }

    @Override
    public SSLEngineResult unwrap(ByteBuffer src, ByteBuffer[] dsts, int offset, int length) throws IllegalArgumentException, SSLException {
        debug("JSSEngine: unwrap(ssl_fd=" + ssl_fd + ")");

        // In this method, we're taking the network wire contents of src and
        // passing them as the read side of our buffer. If there's any data
        // for us to read from the remote peer (via ssl_fd), we place it in
        // the various dsts.
        //
        // However, we also need to detect if the handshake is still ongoing;
        // if so, we can't send data (from src) until then.

        if (ssl_fd == null) {
            beginHandshake();
        }

        // Before going much further, check to see if we need to run a
        // delegated task. So far, the only delegated tasks we have are
        // for checking TrustManagers.
        if (checkNeedCertValidation()) {
            return new SSLEngineResult(SSLEngineResult.Status.OK, handshake_state, 0, 0);
        }

        boolean handshake_already_complete = ssl_fd.handshakeComplete;
        int src_capacity = src.remaining();

        logUnwrap(src);

        // Order of operations:
        //  1. Read data from srcs
        //  2. Update handshake status
        //  3. Write data to dsts
        //
        // Since srcs is coming from the data, it could affect our ability to
        // handshake. It could also affect our ability to write data to dsts,
        // as src could provide new data to decrypt. When no new data from src
        // is present, we could have residual steps in handshake(), in which
        // case no data would be written to dsts. Lastly, even if no new data
        // from srcs, could still have residual data in read_buf, so we should
        // attempt to read from the ssl_fd.
        //
        // In order to handle large buffer sizes, wrap everything in a
        // do-while loop.

        // wire_data is the number of bytes from src we've written into
        // read_buf.
        int wire_data = 0;

        // Actual amount of data written to the buffer.
        int app_data = 0;

        int this_src_write;
        int this_dst_write;

        do {
            this_src_write = 0;
            this_dst_write = 0;

            if (src != null) {
                this_src_write = Math.min((int) Buffer.WriteCapacity(read_buf), src.remaining());

                // When we have data from src, write it to read_buf.
                if (this_src_write > 0) {
                    byte[] wire_buffer = new byte[this_src_write];
                    src.get(wire_buffer);

                    this_src_write = (int) Buffer.Write(read_buf, wire_buffer);

                    wire_data += this_src_write;
                    debug("JSSEngine.unwrap(): Wrote " + this_src_write + " bytes to read_buf.");
                }
            }

            // In the above, we should always try to read and write data. Check to
            // see if we need to step our handshake process or not.
            updateHandshakeState();

            int max_dst_size = computeSize(dsts, offset, length);
            byte[] app_buffer = PR.Read(ssl_fd, max_dst_size);
            int error = PR.GetError();
            debug("JSSEngine.unwrap() - " + app_buffer + " error=" + errorText(error));
            if (app_buffer != null) {
                this_dst_write = putData(app_buffer, dsts, offset, length);
                app_data += this_dst_write;
            } else if (max_dst_size > 0) {
                // There are two scenarios we need to ignore here:
                //  1. WOULD_BLOCK_ERRORs are safe, because we're expecting
                //     not to block. Usually this means we don't have space
                //     to write any more data.
                //  2. SOCKET_SHUTDOWN_ERRORs are safe, because if the
                //     underling cause was fatal, we'd catch it after exiting
                //     the do-while loop, in checkSSLAlerts().
                if (error != 0 && error != PRErrors.WOULD_BLOCK_ERROR && error != PRErrors.SOCKET_SHUTDOWN_ERROR) {
                    ssl_exception = new SSLException("Unexpected return from PR.Read(): " + errorText(error));
                    seen_exception = true;
                }
            }
        } while (this_src_write != 0 || this_dst_write != 0);

        if (seen_exception == false && ssl_exception == null) {
            ssl_exception = checkSSLAlerts();
            seen_exception = (ssl_exception != null);
        }

        // Before we return, check if an exception occurred and throw it if
        // one did.
        if (ssl_exception != null) {
            info("JSSEngine.unwrap() - Got SSLException: " + ssl_exception);
            SSLException excpt = ssl_exception;
            ssl_exception = null;
            handshake_state = SSLEngineResult.HandshakeStatus.NEED_WRAP;
            tryCleanup();
            throw excpt;
        }

        SSLEngineResult.Status handshake_status = SSLEngineResult.Status.OK;


        if (is_inbound_closed) {
            debug("Socket is currently closed.");
            handshake_status = SSLEngineResult.Status.CLOSED;
        } else if (handshake_already_complete && src_capacity > 0 && app_data == 0) {
            debug("Underflowed: produced no application data when we expected to.");
            handshake_status = SSLEngineResult.Status.BUFFER_UNDERFLOW;
        }

        // Need a way to introspect the open/closed state of the TLS
        // connection.

        debug("JSSEngine.unwrap() - Finished");
        debug(" - Status: " + handshake_status);
        debug(" - Handshake State: " + handshake_state);
        debug(" - wire_data: " + wire_data);
        debug(" - app_data: " + app_data);

        if (handshake_state == SSLEngineResult.HandshakeStatus.FINISHED) {
            returned_finished = true;
        }

        tryCleanup();
        return new SSLEngineResult(handshake_status, handshake_state, wire_data, app_data);
    }

    public int writeData(ByteBuffer[] srcs, int offset, int length) {
        debug("JSSEngine: writeData()");
        // This is the tough end of reading/writing. There's two potential
        // places buffering could occur:
        //
        //  - Inside the NSS library (unclear if this happens).
        //  - write_buf
        //
        // So when we call PR.Write(ssl_fd, data), it isn't guaranteed that
        // we can write all of data to ssl_fd (unlike with all our other read
        // or write operations where we have a clear bound). In the event that
        // our Write call is truncated, we have to put data back into the
        // buffer from whence it was read.
        //
        // However, we do use Buffer.WriteCapacity(write_buf) as a proxy
        // metric for how much we can write without having to place data back
        // in a src buffer.
        //
        // When we don't perform an actual NSPR write call, make a dummy
        // invocation to ensure we always attempt to flush these buffers.
        int data_length = 0;

        int index = offset;
        int max_index = offset + length;

        boolean attempted_write = false;

        while (index < max_index) {
            // If we don't have any remaining bytes in this buffer, skip it.
            if (srcs[index] == null || srcs[index].remaining() <= 0) {
                index += 1;
                continue;
            }
            debug("JSSEngine.writeData(): index=" + index + " max_index=" + max_index);

            // We expect (i.e., need to construct a buffer) to write up to
            // this much. Note that this is non-zero since we're taking the
            // max here and we guarantee with the previous statement that
            // srcs[index].remaining() > 0. There's no point in getting more
            // than BUFFER_SIZE bytes either; so cap at the minimum of the
            // two sizes.
            int expected_write = Math.min(srcs[index].remaining(), BUFFER_SIZE);
            debug("JSSEngine.writeData(): expected_write=" + expected_write + " write_cap=" + Buffer.WriteCapacity(write_buf) + " read_cap=" + Buffer.ReadCapacity(read_buf));

            // Get data from our current srcs[index] buffer.
            byte[] app_data = new byte[expected_write];
            srcs[index].get(app_data);

            // Actual amount written. Since this is a PR.Write call, mark
            // attempted_write.
            int this_write = PR.Write(ssl_fd, app_data);
            attempted_write = true;

            // Reset our buffer's position in event of sub-optimal write.
            if (this_write < expected_write) {
                int pos = srcs[index].position();

                // When this_write < 0, we want to reset to the beginning
                // because we assume we haven't written any data due to an
                // error before writing.
                int delta = expected_write - Math.max(0, this_write);

                srcs[index].position(pos - delta);
            }

            debug("JSSEngine.writeData(): this_write=" + this_write);
            if (this_write < 0) {
                int error = PR.GetError();
                if (error == PRErrors.SOCKET_SHUTDOWN_ERROR) {
                    debug("NSPR reports outbound socket is shutdown.");
                    is_outbound_closed = true;
                } else if (error != PRErrors.WOULD_BLOCK_ERROR) {
                    throw new RuntimeException("Unable to write to internal ssl_fd: " + errorText(PR.GetError()));
                }

                break;
            }

            data_length += this_write;

            if (this_write < expected_write) {
                // If we didn't get an error but we got less than our expected
                // write, it is best to exit to give us time to drain the
                // buffers before attempting another write. We're guaranteed
                // to be called again because we wrote a non-zero amount here.
                break;
            }
        }

        // When we didn't call PR.Write, invoke a dummy call to PR.Write to
        // ensure we always attempt to write to push data from NSS's internal
        // buffers into our network buffers.
        if (!attempted_write) {
            PR.Write(ssl_fd, null);
        }

        debug("JSSEngine.writeData(): data_length=" + data_length);

        return data_length;
    }

    private void logWrap(ByteBuffer dst) {
        if (debug_port <= 0 || dst == null || dst.remaining() == 0) {
            return;
        }

        loggingSocketConsumeAllBytes();

        OutputStream stream = s_ostream;

        if (!as_server) {
            // A wrap from the client means we write data to the outbound
            // side of the client socket.
            stream = c_ostream;
        }

        WritableByteChannel channel = Channels.newChannel(stream);

        int pos = dst.position();
        try {
            dst.flip();
            debug("JSSEngine: logWrap() - writing " + dst.remaining() + " bytes.");
            channel.write(dst);
            stream.flush();
            dst.flip();
        } catch (Exception e) {
            throw new RuntimeException("Unable to log contents of wrap's dst to debug socket: " + e.getMessage(), e);
        } finally {
            dst.position(pos);
        }
    }

    @Override
    public SSLEngineResult wrap(ByteBuffer[] srcs, int offset, int length, ByteBuffer dst) throws IllegalArgumentException, SSLException {
        debug("JSSEngine: wrap(ssl_fd=" + ssl_fd + ")");
        // In this method, we're taking the application data from the various
        // srcs and writing it to the remote peer (via ssl_fd). If there's any
        // data for us to send to the remote peer, we place it in dst.
        //
        // However, we also need to detect if the handshake is still ongoing;
        // if so, we can't send data (from src) until then.

        if (ssl_fd == null) {
            beginHandshake();
        }

        // Before going much further, check to see if we need to run a
        // delegated task. So far, the only delegated tasks we have are
        // for checking TrustManagers.
        if (checkNeedCertValidation()) {
            return new SSLEngineResult(SSLEngineResult.Status.OK, handshake_state, 0, 0);
        }

        // Order of operations:
        //  1. Step the handshake
        //  2. Write data from srcs to ssl_fd
        //  3. Write data from write_buf to dst
        //
        // This isn't technically locally optimal: it could be that write_buf
        // is full while we're handshaking so step 1 could be a no-op, but
        // we could read from write_buf and step the handshake then. However,
        // on our next call to wrap() would also step the handshake, which
        // two in a row would almost certainly result in one being a no-op.
        // Both steps 1 and 2 could write data to dsts. At best 2 will fail if
        // write_buf is full, however, we'd again end up calling wrap() again
        // anyways.
        //
        // Note that allowances are given for underflow but not overflow: a
        // single call to PR.Write() might not suffice for step 2; we might
        // need to execute step 3 and come back and re-execute steps 2 and 3
        // multiple times in order to send all data. However, since this could
        // technically also be true of the handshake itself, wrap everything
        // in the do-while loop.

        // Actual amount of data read from srcs (and written to ssl_fd). This
        // is determined by the PR.Write(...) call on ssl_fd.
        int app_data = 0;

        // wire_data is the number of bytes written to dst. This is bounded
        // above by two fields: the number of bytes we can read from
        // write_buf, and the size of dst, if present.
        int wire_data = 0;

        if (is_inbound_closed && !is_outbound_closed) {
            closeOutbound();
        }

        int this_src_write;
        int this_dst_write;
        do {
            this_src_write = 0;
            this_dst_write = 0;

            // First we try updating the handshake state.
            updateHandshakeState();
            if (ssl_exception == null && seen_exception) {
                if (handshake_state != SSLEngineResult.HandshakeStatus.NOT_HANDSHAKING) {
                    // In the event that:
                    //
                    //      1. We saw an exception in the past
                    //          --> (seen_exception is true),
                    //      2. We've already thrown it from wrap or unwrap,
                    //          --> (ssl_exception is null),
                    //      3. We were previously handshaking
                    //          --> (handshake_state is a handshaking state),
                    //
                    // we need to make sure wrap is called again to ensure the
                    // alert is actually written to the wire. So here we are,
                    // in wrap and the above hold true; we can mark the handshake
                    // status as "FINISHED" (because well, it is over due to the
                    // alert). That leaves the return state to be anything other
                    // than OK to indicate the error.
                    handshake_state = SSLEngineResult.HandshakeStatus.FINISHED;
                }
            }

            // Try writing data from srcs to the other end of the connection. Note
            // that we always attempt this, even if the handshake isn't yet marked
            // as finished. This is because we need the call to PR.Write(...) to
            // tell if an alert is getting sent.
            this_src_write = writeData(srcs, offset, length);
            if (this_src_write > 0) {
                app_data += this_src_write;
                debug("JSSEngine.wrap(): wrote " + this_src_write + " from srcs to buffer.");
            } else {
                debug("JSSEngine.wrap(): not writing from srcs to buffer: this_src_write=" + this_src_write);
            }

            if (dst != null) {
                // Get an estimate for the expected write to dst; this is
                // the minimum of write_buf read capacity and dst.remaining
                // capacity.
                this_dst_write = Math.min((int) Buffer.ReadCapacity(write_buf), dst.remaining());

                // Try reading data from write_buf to dst; always do this, even
                // if we didn't write.
                if (this_dst_write > 0) {
                    byte[] wire_buffer = Buffer.Read(write_buf, this_dst_write);
                    dst.put(wire_buffer);
                    this_dst_write = wire_buffer.length;
                    wire_data += this_dst_write;

                    debug("JSSEngine.wrap() - Wrote " + wire_buffer.length + " bytes to dst.");
                } else {
                    debug("JSSEngine.wrap(): not writing from write_buf into dst: this_dst_write=0 write_buf.read_capacity=" + Buffer.ReadCapacity(write_buf) + " dst.remaining=" + dst.remaining());
                }
            } else {
                debug("JSSEngine.wrap(): not writing from write_buf into NULL dst");
            }
        } while (this_src_write != 0 || this_dst_write != 0);

        if (seen_exception == false && ssl_exception == null) {
            ssl_exception = checkSSLAlerts();
            seen_exception = (ssl_exception != null);
        }

        logWrap(dst);

        // Before we return, check if an exception occurred and throw it if
        // one did.
        if (ssl_exception != null) {
            info("JSSEngine.wrap() - Got SSLException: " + ssl_exception);
            SSLException excpt = ssl_exception;
            ssl_exception = null;
            cleanup();
            throw excpt;
        }

        // Need a way to introspect the open/closed state of the TLS
        // connection.

        SSLEngineResult.Status handshake_status = SSLEngineResult.Status.OK;

        if (ssl_exception == null && seen_exception) {
            debug("Seen and processed exception; closing inbound and outbound because this was the last wrap(...)");
            closeInbound();
            closeOutbound();
        }

        if (is_outbound_closed) {
            debug("Socket is currently closed.");
            handshake_status = SSLEngineResult.Status.CLOSED;
        }

        debug("JSSEngine.wrap() - Finished");
        debug(" - Status: " + handshake_status);
        debug(" - Handshake State: " + handshake_state);
        debug(" - wire_data: " + wire_data);
        debug(" - app_data: " + app_data);

        if (handshake_state == SSLEngineResult.HandshakeStatus.FINISHED) {
            returned_finished = true;
        }

        tryCleanup();
        return new SSLEngineResult(handshake_status, handshake_state, app_data, wire_data);
    }

    /**
     * Calls cleanup only if both inbound and outbound data streams are
     * closed.
     *
     * This prevents accidental cleanup in the case of a partially open
     * connection.
     */
    @Override
    public void tryCleanup() {
        debug("JSSEngine: tryCleanup()");
        if (is_inbound_closed && is_outbound_closed) {
            // throw new RuntimeException("Probably shouldn't be here!");
            cleanup();
        }
    }

    /**
     * Performs cleanup of internal data, closing both inbound and outbound
     * data streams if still open.
     */
    @Override
    public void cleanup() {
        debug("JSSEngine: cleanup()");

        if (!is_inbound_closed) {
            debug("JSSEngine: cleanup() - closing opened inbound socket");
            closeInbound();
        }

        if (!is_outbound_closed) {
            debug("JSSEngine: cleanup() - closing opened outbound socket");
            closeOutbound();
        }

        // First cleanup any debugging ports, if any.
        cleanupLoggingSocket();

        // Then clean up the NSS state.
        cleanupSSLFD();

        // Clean up the session.
        if (session != null) {
            session.close();
            session = null;
        }
    }

    private void cleanupLoggingSocket() {
        if (debug_port > 0) {
            try {
                s_socket.close();
            } catch (Exception e) {}

            try {
                c_socket.close();
            } catch (Exception e) {}

            try {
                ss_socket.close();
            } catch (Exception e) {}
        }
    }

    private void cleanupSSLFD() {
        if (!closed_fd && ssl_fd != null) {
            try {
                SSL.RemoveCallbacks(ssl_fd);
                ssl_fd.close();
                ssl_fd = null;
            } catch (Exception e) {
                logger.error("Got exception trying to cleanup SSLFD", e);
            } finally {
                closed_fd = true;
            }
        }

        if (read_buf != null) {
            Buffer.Free(read_buf);
            read_buf = null;
        }

        if (write_buf != null) {
            Buffer.Free(write_buf);
            write_buf = null;
        }
    }

    // During testing with Tomcat 8.5, most instances did not call
    // cleanup, so all the JNI resources end up getting leaked: ssl_fd
    // (and its global ref), read_buf, and write_buf.
    @Override
    protected void finalize() {
        cleanup();
    }


    private class CertValidationTask extends CertAuthHandler {
        public CertValidationTask(SSLFDProxy fd) {
            super(fd);
        }

        public String findAuthType(SSLFDProxy ssl_fd, PK11Cert[] chain) throws Exception {
            // Java's CryptoManager is supposed to validate that the auth type
            // chosen by the underlying protocol is compatible with the
            // certificates in the channel. With TLSv1.3, this is less of a
            // concern. However, NSS doesn't directly expose an authType
            // compatible with Java; we're left inquiring for similar
            // information from the channel info.

            SSLPreliminaryChannelInfo info = SSL.GetPreliminaryChannelInfo(ssl_fd);
            if (info == null) {
                String msg = "Expected non-null result from GetPreliminaryChannelInfo!";
                throw new RuntimeException(msg);
            }

            if (!info.haveProtocolVersion()) {
                String msg = "Expected SSLPreliminaryChannelInfo (";
                msg += info + ") to have protocol information.";
                throw new RuntimeException(msg);
            }

            if (!info.haveCipherSuite()) {
                String msg = "Expected SSLPreliminaryChannelInfo (";
                msg += info + ") to have cipher suite information.";
                throw new RuntimeException(msg);
            }

            SSLVersion version = info.getProtocolVersion();
            SSLCipher suite = info.getCipherSuite();

            if (version.value() < SSLVersion.TLS_1_3.value()) {
                // When we're doing a TLSv1.2 or earlier protocol exchange,
                // we can simply check the cipher suite value for the
                // authentication type.
                if (suite.requiresRSACert()) {
                    // Java expects RSA_EXPORT to be handled properly.
                    // However, rather than checking the actual bits in
                    // the RSA certificate, return it purely based on
                    // cipher suite name. In modern reality, these ciphers
                    // should _NEVER_ be negotiated!
                    if (suite.name().contains("RSA_EXPORT")) {
                        return "RSA_EXPORT";
                    }

                    return "RSA";
                } else if (suite.requiresECDSACert()) {
                    return "ECDSA";
                } else if (suite.requiresDSSCert()) {
                    // Notably, DSS is the same as DSA, but the suite names
                    // all use DSS while the JDK uses DSA.
                    return "DSA";
                }
                // Implicit else: authType == null, causing TrustManager
                // check to fail.
            } else {
                // For TLSv1.3 and any later protocols, we can't rely on
                // the above requires() checks, because the actual
                // signature type depends on the type of the certificate
                // provided. This makes the TrustManager field redundant,
                // but yet we still have to provide it.
                if (chain != null && chain.length > 0 && chain[0] != null) {
                    PK11Cert cert = chain[0];
                    PublicKey key = cert.getPublicKey();
                    return key.getAlgorithm();
                }
                // Implicit else here and above: authType == null, which
                // will cause the TrustManager check to fail.
            }

            return null;
        }

        @Override
        public int check(SSLFDProxy fd) {
            // Needs to be available for assignException() below.
            PK11Cert[] chain = null;

            try {
                chain = SSL.PeerCertificateChain(fd);
                String authType = findAuthType(fd, chain);
                debug("CertAuthType: " + authType);

                if (chain == null || chain.length == 0) {
                    // When the chain is NULL, we'd always fail in the
                    // TrustManager calls, beacuse they expect a non-NULL,
                    // non-empty chain. However, this is sometimes desired,
                    // for instance, if we requested the peer to provide a
                    // certificate chain and they didn't.
                    if (as_server == true && !need_client_auth) {
                        // Since we're a server validating the client's
                        // chain (and they didn't provide one), we should
                        // ignore it instead of forcing the problem.
                        debug("No client certificate chain and client cert not needed.");
                        return 0;
                    }
                }

                for (X509TrustManager tm : trust_managers) {
                    // X509ExtendedTrustManager lets the TM access the
                    // SSLEngine while validating certificates. Otherwise,
                    // the X509TrustManager doesn't have access to that
                    // parameter. Facilitate it if possible.
                    if (tm instanceof X509ExtendedTrustManager) {
                        X509ExtendedTrustManager etm = (X509ExtendedTrustManager) tm;
                        if (as_server) {
                            etm.checkClientTrusted(chain, authType, JSSEngineReferenceImpl.this);
                        } else {
                            etm.checkServerTrusted(chain, authType, JSSEngineReferenceImpl.this);
                        }
                    } else {
                        if (as_server) {
                            tm.checkClientTrusted(chain, authType);
                        } else {
                            tm.checkServerTrusted(chain, authType);
                        }
                    }
                }
            } catch (Exception excpt) {
                return assignException(excpt, chain);
            }

            return 0;
        }

        private int assignException(Exception excpt, PK11Cert[] chain) {
            int nss_code = Cert.MatchExceptionToNSSError(excpt);

            if (seen_exception) {
                return nss_code;
            }

            String msg = "Got exception while trying to validate ";
            msg += "peer's certificate chain:\n";
            if (chain == null) {
                msg += " - (null chain)\n";
            } else if (chain.length == 0) {
                msg += " - (0 length chain)\n";
            } else {
                for (PK11Cert cert : chain) {
                    msg += " - " + cert + "\n";
                }
            }
            msg += "with given TrustManagers:\n";
            if (trust_managers == null) {
                msg += " - (null TrustManagers)\n";
            } else if (trust_managers.length == 0) {
                msg += " - (0 length TrustManagers)\n";
            } else {
                for (X509TrustManager tm : trust_managers) {
                    msg += " - " + tm + "\n";
                }
            }
            msg += "exception message: " + excpt.getMessage();

            seen_exception = true;
            ssl_exception = new SSLException(msg, excpt);
            return nss_code;
        }
    }

    private class BypassBadHostname extends BadCertHandler {
        public BypassBadHostname(SSLFDProxy fd, int error) {
            super(fd, error);
        }

        @Override
        public int check(SSLFDProxy fd, int error) {
            // NSS enforces strict hostname verification via the SSL_SetURL
            // function call. Java doesn't pass this information to either
            // SSLSocket or SSLEngine, so we can at best try and infer this
            // information. However, since this only needs to be validated
            // on the client side, we can elide this check (like the JCA
            // suggests and as the SunJSSE implementation does). In order
            // to do so, we need to check for the BAD_CERT_DOMAIN error
            // and tell NSS to ignore it.
            //
            // This makes the assumptions:
            //  1. The hostname check is the very last check in the NSS
            //     certificate validation handler.
            //  2. As a consequence of (1) an otherwise valid certificate
            //     will pass all other checks but fail due to hostname==NULL.
            //  3. As a consequence of (1), all invalid certificates will
            //     fail earlier.
            //  4. No other paths report BAD_CERT_DOMAIN earlier than the
            //     final hostname check.
            if (error == SSLErrors.BAD_CERT_DOMAIN) {
                return 0;
            }

            return error;
        }
    }
}
