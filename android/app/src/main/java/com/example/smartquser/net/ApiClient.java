package com.example.smartquser.net;

import android.app.Activity;
import android.content.Context;
import android.content.Intent;
import android.net.ConnectivityManager;
import android.net.Network;
import android.net.NetworkCapabilities;
import android.net.Uri;
import android.os.Handler;
import android.os.Looper;
import android.os.SystemClock;
import android.util.Log;

import com.example.smartquser.BuildConfig;
import com.example.smartquser.R;
import com.example.smartquser.BaseActivity;
import com.example.smartquser.WelcomeActivity;
import com.example.smartquser.auth.AuthGate;
import com.example.smartquser.utils.AppPrefs;
import com.example.smartquser.utils.SessionManager;

import org.json.JSONException;
import org.json.JSONObject;

import java.io.ByteArrayOutputStream;
import java.io.IOException;
import java.io.InputStream;
import java.io.OutputStream;
import java.net.ConnectException;
import java.net.HttpURLConnection;
import java.net.NoRouteToHostException;
import java.net.SocketTimeoutException;
import java.net.URL;
import java.net.URLEncoder;
import java.net.UnknownHostException;
import java.nio.charset.StandardCharsets;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;
import java.util.UUID;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

import javax.net.ssl.SSLException;

/**
 * The single networking layer of the app. Every screen talks to the server through this class.
 *
 * <ul>
 *   <li>Base URL comes from {@code BuildConfig.API_BASE_URL} (set in app/build.gradle.kts).</li>
 *   <li>20 s connect/read timeouts + one automatic retry (GET always; POST only when the request
 *       never reached the server, so a booking is never sent twice).</li>
 *   <li>Auth: sends both {@code Authorization: Bearer} and {@code X-Api-Token} (some hosts strip the
 *       first one) and also {@code api_token} in POST bodies.</li>
 *   <li>Answers are parsed as {"status","message","data"}; 401 AUTH_* clears the session and opens
 *       the Welcome screen with a friendly message.</li>
 *   <li>Guest mode: calls to endpoints that need a token are never sent without one; they fail
 *       locally with {@link ApiError.Kind#SIGN_IN_REQUIRED} (no 401 / redirect loops).</li>
 *   <li>If a hosting anti-bot page (InfinityFree aes.js / __test cookie) comes back instead of JSON,
 *       {@link BotChallengeSolver} gets the cookie through an invisible WebView and the request is
 *       retried transparently.</li>
 *   <li>Callbacks run on the main thread and are dropped if the calling Activity is gone.</li>
 * </ul>
 */
public final class ApiClient {

    private static final String TAG = "SmartQ-Net";
    public static final String BASE_URL = withSlash(BuildConfig.API_BASE_URL);

    private static final int TIMEOUT_MS = 20_000;
    private static final int MAX_BODY_BYTES = 8 * 1024 * 1024;

    private static final ExecutorService EXECUTOR = Executors.newFixedThreadPool(4);
    private static final Handler MAIN = new Handler(Looper.getMainLooper());
    private static long lastAuthRedirect;

    public interface Callback {
        void onSuccess(ApiResponse response);

        void onError(ApiError error);
    }

    /** Handle to ignore the result of a request (e.g. a stale search). */
    public static final class Call {
        volatile boolean cancelled;

        public void cancel() {
            cancelled = true;
        }
    }

    private ApiClient() {}

    // ---------------------------------------------------------------- public API

    public static String url(String endpoint) {
        return BASE_URL + endpoint;
    }

    /** ".../api/user/" -> ".../api/health.php" */
    public static String healthUrl() {
        String api = BASE_URL.endsWith("user/") ? BASE_URL.substring(0, BASE_URL.length() - 5) : BASE_URL;
        return api + "health.php";
    }

    public static Call health(Activity owner, Callback cb) {
        return enqueue(owner, new Req("GET", healthUrl(), null, null, false), cb);
    }

    public static Call get(Activity owner, String endpoint, Map<String, String> query, Callback cb) {
        Call skipped = skipWithoutToken(owner, endpoint, cb);
        if (skipped != null) return skipped;
        Uri.Builder b = Uri.parse(url(endpoint)).buildUpon();
        if (query != null) {
            for (Map.Entry<String, String> e : query.entrySet()) {
                if (e.getValue() != null) b.appendQueryParameter(e.getKey(), e.getValue());
            }
        }
        return enqueue(owner, new Req("GET", b.build().toString(), null, null, true), cb);
    }

    public static Call post(Activity owner, String endpoint, Map<String, String> form, Callback cb) {
        Call skipped = skipWithoutToken(owner, endpoint, cb);
        if (skipped != null) return skipped;
        Map<String, String> body = form != null ? new LinkedHashMap<>(form) : new LinkedHashMap<>();
        String token = new SessionManager(owner).getToken();
        if (!token.isEmpty() && !body.containsKey("api_token")) body.put("api_token", token);
        byte[] bytes = encodeForm(body).getBytes(StandardCharsets.UTF_8);
        return enqueue(owner, new Req("POST", url(endpoint), bytes,
                "application/x-www-form-urlencoded; charset=UTF-8", true), cb);
    }

    public static Call upload(Activity owner, String endpoint, String fieldName, byte[] file,
                              String fileName, String mime, Callback cb) {
        Call skipped = skipWithoutToken(owner, endpoint, cb);
        if (skipped != null) return skipped;
        String boundary = "----SmartQ" + UUID.randomUUID().toString().replace("-", "");
        ByteArrayOutputStream out = new ByteArrayOutputStream(file.length + 1024);
        try {
            String token = new SessionManager(owner).getToken();
            if (!token.isEmpty()) {
                out.write(("--" + boundary + "\r\nContent-Disposition: form-data; name=\"api_token\"\r\n\r\n"
                        + token + "\r\n").getBytes(StandardCharsets.UTF_8));
            }
            out.write(("--" + boundary + "\r\nContent-Disposition: form-data; name=\"" + fieldName
                    + "\"; filename=\"" + fileName + "\"\r\nContent-Type: " + mime + "\r\n\r\n")
                    .getBytes(StandardCharsets.UTF_8));
            out.write(file);
            out.write(("\r\n--" + boundary + "--\r\n").getBytes(StandardCharsets.UTF_8));
        } catch (IOException ignored) {
            // ByteArrayOutputStream does not throw
        }
        return enqueue(owner, new Req("POST", url(endpoint), out.toByteArray(),
                "multipart/form-data; boundary=" + boundary, true), cb);
    }

    /** Guest / signed-out: an endpoint that needs a token fails locally instead of hitting the server. */
    private static Call skipWithoutToken(Activity owner, String endpoint, Callback cb) {
        if (!AuthGate.shouldSkipRequest(endpoint, new SessionManager(owner).getToken())) return null;
        final Call call = new Call();
        final ApiError error = ApiError.res(ApiError.Kind.SIGN_IN_REQUIRED, R.string.err_sign_in_required, "Please sign in to continue.", 401, null);
        MAIN.post(() -> {
            if (call.cancelled || owner.isFinishing() || owner.isDestroyed()) return;
            cb.onError(error);
        });
        return call;
    }

    public static Map<String, String> params(String... kv) {
        Map<String, String> m = new LinkedHashMap<>();
        for (int i = 0; i + 1 < kv.length; i += 2) m.put(kv[i], kv[i + 1]);
        return m;
    }

    /** Headers to use for images served by our own host (Glide), so they also pass the anti-bot check. */
    public static Map<String, String> imageHeaders(Context context, String imageUrl) {
        Map<String, String> h = new LinkedHashMap<>();
        h.put("User-Agent", NetIdentity.userAgent(context));
        String cookie = NetIdentity.cookieHeader(context, imageUrl);
        if (!cookie.isEmpty()) h.put("Cookie", cookie);
        return h;
    }

    public static boolean isOnline(Context context) {
        try {
            ConnectivityManager cm = (ConnectivityManager) context.getSystemService(Context.CONNECTIVITY_SERVICE);
            if (cm == null) return true;
            Network n = cm.getActiveNetwork();
            if (n == null) return false;
            NetworkCapabilities caps = cm.getNetworkCapabilities(n);
            return caps != null && caps.hasCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET);
        } catch (Exception e) {
            return true; // never block a request because the check itself failed
        }
    }

    // ---------------------------------------------------------------- internals

    private static final class Req {
        final String method;
        final String url;
        final byte[] body;
        final String contentType;
        final boolean auth;

        Req(String method, String url, byte[] body, String contentType, boolean auth) {
            this.method = method;
            this.url = url;
            this.body = body;
            this.contentType = contentType;
            this.auth = auth;
        }
    }

    private static final class Raw {
        int code;
        String body;
        String contentType;
    }

    /** Thrown when the connection could not even be opened (safe to retry a POST). */
    private static final class ConnectPhaseException extends IOException {
        ConnectPhaseException(IOException cause) {
            super(cause);
        }
    }

    private static Call enqueue(Activity owner, Req req, Callback cb) {
        final Call call = new Call();
        final Context app = owner.getApplicationContext();
        final String token = new SessionManager(owner).getToken();
        EXECUTOR.execute(() -> {
            Object result = execute(app, req, token);
            MAIN.post(() -> deliver(owner, call, cb, result));
        });
        return call;
    }

    private static void deliver(Activity owner, Call call, Callback cb, Object result) {
        if (call.cancelled) return;
        if (owner.isFinishing() || owner.isDestroyed()) return;
        if (result instanceof ApiError) result = ServerMessages.localize(owner, (ApiError) result);
        if (result instanceof ApiError && ((ApiError) result).kind == ApiError.Kind.AUTH) {
            if (handleAuthFailure(owner, (ApiError) result)) return;
            result = new ApiError(ApiError.Kind.SIGN_IN_REQUIRED, ((ApiError) result).message, 401, null);
        }
        if (result instanceof ApiResponse) cb.onSuccess((ApiResponse) result);
        else cb.onError((ApiError) result);
    }

    /**
     * Token rejected. Signed-in user: clear the session and open Welcome with the message (returns
     * true). Guest: nothing to log out of, so the caller just gets an error (returns false).
     */
    private static boolean handleAuthFailure(Activity owner, ApiError error) {
        SessionManager session = new SessionManager(owner);
        if (!session.isLoggedIn() && new AppPrefs(owner).isGuest()) return false;
        session.logout();
        long now = SystemClock.elapsedRealtime();
        if (now - lastAuthRedirect < 2_000) return true;
        lastAuthRedirect = now;
        Intent i = new Intent(owner, WelcomeActivity.class);
        i.putExtra(BaseActivity.EXTRA_MESSAGE, error.message);
        i.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_CLEAR_TASK);
        owner.startActivity(i);
        return true;
    }

    /** Runs on a worker thread. Returns ApiResponse or ApiError. */
    private static Object execute(Context app, Req req, String token) {
        boolean challengeTried = false;
        int attempt = 0;
        while (true) {
            try {
                Raw raw = perform(app, req, token);
                if (!looksLikeJson(raw.body) && BotChallengeSolver.looksLikeChallenge(raw.body)) {
                    if (challengeTried) {
                        return ApiError.res(ApiError.Kind.BLOCKED, R.string.err_blocked,
                                "The server's security check blocked the app. Please try again in a moment.",
                                raw.code, null);
                    }
                    challengeTried = true;
                    Log.i(TAG, "Anti-bot page received, solving it in a WebView");
                    if (!BotChallengeSolver.solve(app, healthUrl())) {
                        return ApiError.res(ApiError.Kind.BLOCKED, R.string.err_blocked_solve,
                                "Could not pass the server's security check. Check your connection and try again.",
                                raw.code, null);
                    }
                    continue; // retry the same request with the new cookie
                }
                return parse(raw);
            } catch (IOException e) {
                Log.w(TAG, req.method + " " + req.url + " failed: " + e);
                boolean retryable = "GET".equals(req.method) || e instanceof ConnectPhaseException;
                if (attempt == 0 && retryable && isOnline(app)) {
                    attempt++;
                    SystemClock.sleep(700);
                    continue;
                }
                return classify(app, e);
            }
        }
    }

    private static Raw perform(Context app, Req req, String token) throws IOException {
        HttpURLConnection conn = (HttpURLConnection) new URL(req.url).openConnection();
        try {
            conn.setConnectTimeout(TIMEOUT_MS);
            conn.setReadTimeout(TIMEOUT_MS);
            conn.setUseCaches(false);
            conn.setInstanceFollowRedirects(true);
            conn.setRequestMethod(req.method);
            conn.setRequestProperty("Accept", "application/json");
            conn.setRequestProperty("User-Agent", NetIdentity.userAgent(app));
            String cookie = NetIdentity.cookieHeader(app, req.url);
            if (!cookie.isEmpty()) conn.setRequestProperty("Cookie", cookie);
            if (req.auth && token != null && !token.isEmpty()) {
                conn.setRequestProperty("Authorization", "Bearer " + token);
                conn.setRequestProperty("X-Api-Token", token);
            }
            if (req.body != null) {
                conn.setDoOutput(true);
                conn.setRequestProperty("Content-Type", req.contentType);
                conn.setFixedLengthStreamingMode(req.body.length);
            }
            try {
                conn.connect();
            } catch (IOException e) {
                throw new ConnectPhaseException(e);
            }
            if (req.body != null) {
                try (OutputStream os = conn.getOutputStream()) {
                    os.write(req.body);
                }
            }
            Raw raw = new Raw();
            raw.code = conn.getResponseCode();
            raw.contentType = conn.getContentType();
            InputStream is = raw.code >= 400 ? conn.getErrorStream() : conn.getInputStream();
            raw.body = is != null ? readAll(is) : "";
            List<String> setCookies = conn.getHeaderFields().get("Set-Cookie");
            if (setCookies == null) setCookies = conn.getHeaderFields().get("set-cookie");
            NetIdentity.mergeSetCookies(app, req.url, setCookies);
            return raw;
        } finally {
            conn.disconnect();
        }
    }

    private static String readAll(InputStream is) throws IOException {
        try (InputStream in = is) {
            ByteArrayOutputStream bos = new ByteArrayOutputStream();
            byte[] buf = new byte[8192];
            int n;
            int total = 0;
            while ((n = in.read(buf)) != -1) {
                total += n;
                if (total > MAX_BODY_BYTES) throw new IOException("Response too large");
                bos.write(buf, 0, n);
            }
            return new String(bos.toByteArray(), StandardCharsets.UTF_8);
        }
    }

    private static boolean looksLikeJson(String body) {
        if (body == null) return false;
        String t = stripBom(body).trim();
        return t.startsWith("{");
    }

    private static String stripBom(String s) {
        return s != null && !s.isEmpty() && s.charAt(0) == (char) 0xFEFF ? s.substring(1) : s;
    }

    private static Object parse(Raw raw) {
        String body = stripBom(raw.body == null ? "" : raw.body).trim();
        JSONObject json = null;
        if (body.startsWith("{")) {
            try {
                json = new JSONObject(body);
            } catch (JSONException ignored) {
            }
        }
        // PHP notices printed before the JSON ("<br /><b>Warning</b>...{...}"): recover the JSON part.
        if (json == null && body.contains("\"status\"")) {
            int start = body.indexOf('{');
            int end = body.lastIndexOf('}');
            if (start >= 0 && end > start) {
                try {
                    json = new JSONObject(body.substring(start, end + 1));
                } catch (JSONException ignored) {
                }
            }
        }
        if (json == null) {
            if (raw.code >= 500) {
                return ApiError.res(ApiError.Kind.SERVER, R.string.err_server,
                        "The server had a problem (error " + raw.code + "). Please try again shortly.", raw.code, null);
            }
            if (raw.code == 404) {
                return ApiError.res(ApiError.Kind.SERVER, R.string.err_not_found,
                        "The service was not found on the server (404). Please check the server address.", 404, null);
            }
            return ApiError.res(ApiError.Kind.PARSE, R.string.err_parse,
                    "The server sent an unexpected response. Please try again.", raw.code, null);
        }

        boolean status = json.optBoolean("status", false);
        String message = json.optString("message", "");
        JSONObject data = json.optJSONObject("data");
        if (data == null) data = new JSONObject();

        String code = data.optString("code", "");
        if (raw.code == 401 && code.startsWith("AUTH_")) {
            return new ApiError(ApiError.Kind.AUTH,
                    message.isEmpty() ? "Please log in again." : message, 401, data);
        }
        if (status && raw.code < 400) return new ApiResponse(data, message, raw.code);

        if (message.isEmpty()) {
            boolean server = raw.code >= 500;
            return ApiError.res(server ? ApiError.Kind.SERVER : ApiError.Kind.API,
                    server ? R.string.err_server_generic : R.string.err_generic,
                    server ? "The server had a problem. Please try again shortly."
                            : "Something went wrong. Please try again.", raw.code, data);
        }
        return new ApiError(raw.code >= 500 ? ApiError.Kind.SERVER : ApiError.Kind.API, message, raw.code, data);
    }

    private static ApiError classify(Context app, IOException e) {
        Throwable cause = e instanceof ConnectPhaseException && e.getCause() != null ? e.getCause() : e;
        if (!isOnline(app)) {
            return ApiError.res(ApiError.Kind.NO_INTERNET, R.string.err_no_internet,
                    "No internet connection. Check your Wi-Fi or mobile data and try again.", 0, null);
        }
        if (cause instanceof SocketTimeoutException) {
            return ApiError.res(ApiError.Kind.TIMEOUT, R.string.err_timeout,
                    "The server is taking too long to respond. Please try again.", 0, null);
        }
        if (cause instanceof SSLException) {
            return ApiError.res(ApiError.Kind.UNREACHABLE, R.string.err_ssl,
                    "Secure connection to the server failed. Please try again later.", 0, null);
        }
        if (cause instanceof UnknownHostException || cause instanceof ConnectException
                || cause instanceof NoRouteToHostException) {
            return ApiError.res(ApiError.Kind.UNREACHABLE, R.string.err_unreachable,
                    "Server unreachable. Please try again in a moment.", 0, null);
        }
        return ApiError.res(ApiError.Kind.UNREACHABLE, R.string.err_unreachable_check,
                "Server unreachable. Please check your connection and try again.", 0, null);
    }

    private static String encodeForm(Map<String, String> form) {
        StringBuilder sb = new StringBuilder();
        try {
            for (Map.Entry<String, String> e : form.entrySet()) {
                if (e.getValue() == null) continue;
                if (sb.length() > 0) sb.append('&');
                sb.append(URLEncoder.encode(e.getKey(), "UTF-8")).append('=')
                        .append(URLEncoder.encode(e.getValue(), "UTF-8"));
            }
        } catch (java.io.UnsupportedEncodingException ignored) {
            // UTF-8 is always supported
        }
        return sb.toString();
    }

    private static String withSlash(String s) {
        return s.endsWith("/") ? s : s + "/";
    }
}
