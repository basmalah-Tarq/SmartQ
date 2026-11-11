package com.example.smartquser.net;

import android.annotation.SuppressLint;
import android.content.Context;
import android.os.Handler;
import android.os.Looper;
import android.util.Log;
import android.webkit.CookieManager;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;

import java.util.Locale;
import java.util.concurrent.CountDownLatch;
import java.util.concurrent.TimeUnit;

/**
 * Passes the "security system" of free hosts such as InfinityFree.
 *
 * <p>Those hosts answer requests that do not come from a real browser with a small HTML page that
 * loads aes.js, computes a value in JavaScript, stores it in a "__test" cookie and reloads the page.
 * A native HTTP client cannot run that script, so it receives HTML instead of JSON.
 *
 * <p>Fix: load the API host once in an invisible WebView (JavaScript on, same User-Agent as our API
 * calls), wait until {@link CookieManager} holds the "__test" cookie for that host, then save the
 * cookies + the WebView's User-Agent in {@link NetIdentity}. {@link ApiClient} attaches them to every
 * request and retries the original request. Concurrent callers share one solving attempt.
 */
public final class BotChallengeSolver {

    private static final String TAG = "SmartQ-Net";
    private static final long SOLVE_TIMEOUT_MS = 15_000;
    private static final long POLL_MS = 250;

    private static Handler main;

    private static synchronized Handler mainHandler() {
        if (main == null) main = new Handler(Looper.getMainLooper());
        return main;
    }
    private static final Object LOCK = new Object();
    private static CountDownLatch inFlight;

    private BotChallengeSolver() {}

    /** Does this (non-JSON) body look like the anti-bot JavaScript challenge page? */
    public static boolean looksLikeChallenge(String body) {
        if (body == null) return false;
        String head = body.length() > 6000 ? body.substring(0, 6000) : body;
        String t = head.trim();
        if (t.startsWith("{") || t.startsWith("[")) return false;
        String lower = head.toLowerCase(Locale.US);
        return lower.contains("aes.js") || lower.contains("slowaes") || lower.contains("__test");
    }

    /**
     * Blocks the calling (background) thread until the cookie is obtained or the timeout passes.
     * Must NOT be called on the main thread.
     *
     * @param pageUrl any URL on the API host (we use api/health.php)
     * @return true when a fresh "__test" cookie is now stored in {@link NetIdentity}
     */
    public static boolean solve(Context context, String pageUrl) {
        final Context app = context.getApplicationContext();
        CountDownLatch latch;
        boolean starter = false;
        synchronized (LOCK) {
            if (inFlight == null) {
                inFlight = new CountDownLatch(1);
                starter = true;
            }
            latch = inFlight;
        }
        if (starter) {
            final CountDownLatch mine = latch;
            mainHandler().post(() -> startWebView(app, pageUrl, mine));
        }
        try {
            latch.await(SOLVE_TIMEOUT_MS + 3_000, TimeUnit.MILLISECONDS);
        } catch (InterruptedException e) {
            Thread.currentThread().interrupt();
        }
        return NetIdentity.hasChallengeCookie(app, pageUrl);
    }

    @SuppressLint("SetJavaScriptEnabled")
    private static void startWebView(Context app, String pageUrl, CountDownLatch latch) {
        final CookieManager cm;
        final WebView webView;
        try {
            cm = CookieManager.getInstance();
            cm.setAcceptCookie(true);
            webView = new WebView(app);
        } catch (Throwable t) {
            // No WebView available on this device (very rare): give up gracefully.
            Log.w(TAG, "WebView unavailable for challenge", t);
            finish(latch);
            return;
        }

        // If the cookie we already sent was rejected, expire it so we wait for a NEW one.
        final String previous = extractTestCookie(cm.getCookie(pageUrl));
        final String sent = extractTestCookie(NetIdentity.cookieHeader(app, pageUrl));
        if (previous != null && !previous.equals(sent)) {
            // The WebView already has a newer cookie than the one we used: just adopt it.
            store(app, cm, pageUrl, webView.getSettings().getUserAgentString());
            webView.destroy();
            finish(latch);
            return;
        }
        if (previous != null) {
            cm.setCookie(pageUrl, "__test=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/");
        }

        WebSettings s = webView.getSettings();
        s.setJavaScriptEnabled(true);
        s.setDomStorageEnabled(true);
        s.setLoadsImagesAutomatically(false);
        s.setUserAgentString(NetIdentity.userAgent(app));
        cm.setAcceptThirdPartyCookies(webView, true);
        webView.setWebViewClient(new WebViewClient());

        final long started = System.currentTimeMillis();
        final boolean[] done = {false};
        Runnable poll = new Runnable() {
            @Override
            public void run() {
                if (done[0]) return;
                String now = extractTestCookie(cm.getCookie(pageUrl));
                boolean gotIt = now != null && !now.isEmpty();
                boolean timedOut = System.currentTimeMillis() - started > SOLVE_TIMEOUT_MS;
                if (gotIt || timedOut) {
                    done[0] = true;
                    if (gotIt) {
                        cm.flush();
                        store(app, cm, pageUrl, webView.getSettings().getUserAgentString());
                        Log.i(TAG, "Hosting security check passed");
                    } else {
                        Log.w(TAG, "Hosting security check timed out");
                    }
                    try {
                        webView.stopLoading();
                        webView.destroy();
                    } catch (Throwable ignored) {
                    }
                    finish(latch);
                } else {
                    mainHandler().postDelayed(this, POLL_MS);
                }
            }
        };
        webView.loadUrl(pageUrl);
        mainHandler().postDelayed(poll, POLL_MS);
    }

    private static void store(Context app, CookieManager cm, String url, String ua) {
        NetIdentity.replaceCookies(app, url, cm.getCookie(url));
        NetIdentity.setUserAgent(app, ua);
    }

    private static void finish(CountDownLatch latch) {
        synchronized (LOCK) {
            if (inFlight == latch) inFlight = null;
        }
        latch.countDown();
    }

    private static String extractTestCookie(String cookies) {
        if (cookies == null) return null;
        for (String part : cookies.split(";")) {
            String p = part.trim();
            if (p.startsWith("__test=")) {
                String v = p.substring(7).trim();
                return v.isEmpty() ? null : v;
            }
        }
        return null;
    }
}
