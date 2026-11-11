package com.example.smartquser.net;

import android.content.Context;
import android.content.SharedPreferences;
import android.net.Uri;
import android.webkit.WebSettings;

import java.util.LinkedHashMap;
import java.util.List;
import java.util.Locale;
import java.util.Map;

/**
 * What the app presents to the server on every request: one stable User-Agent (the WebView's, so it
 * matches the browser that solved the hosting provider's anti-bot check) and the cookies collected
 * for the API host (notably InfinityFree's "__test" cookie). Persisted so it survives app restarts.
 */
public final class NetIdentity {

    private static final String PREFS = "smartq_net";
    private static final String KEY_UA = "user_agent";
    private static final String KEY_COOKIES = "cookies_";

    private static volatile String cachedUserAgent;

    private NetIdentity() {}

    private static SharedPreferences prefs(Context c) {
        return c.getApplicationContext().getSharedPreferences(PREFS, Context.MODE_PRIVATE);
    }

    /** The User-Agent used for API calls, image loading and the invisible challenge WebView. */
    public static String userAgent(Context context) {
        String ua = cachedUserAgent;
        if (ua != null) return ua;
        synchronized (NetIdentity.class) {
            if (cachedUserAgent != null) return cachedUserAgent;
            ua = prefs(context).getString(KEY_UA, null);
            if (ua == null || ua.trim().isEmpty()) {
                try {
                    ua = WebSettings.getDefaultUserAgent(context.getApplicationContext());
                } catch (Throwable t) {
                    ua = null; // WebView missing/updating: fall back to the system agent
                }
                if (ua == null || ua.trim().isEmpty()) {
                    String sys = System.getProperty("http.agent");
                    ua = (sys != null ? sys : "Mozilla/5.0 (Linux; Android)") + " SmartQ";
                } else {
                    prefs(context).edit().putString(KEY_UA, ua).apply();
                }
            }
            cachedUserAgent = ua;
            return ua;
        }
    }

    static void setUserAgent(Context context, String ua) {
        if (ua == null || ua.trim().isEmpty()) return;
        cachedUserAgent = ua;
        prefs(context).edit().putString(KEY_UA, ua).apply();
    }

    static String hostOf(String url) {
        try {
            String h = Uri.parse(url).getHost();
            return h != null ? h.toLowerCase(Locale.US) : "";
        } catch (Exception e) {
            return "";
        }
    }

    /** "a=1; b=2" for the given URL's host, or "" when we have none. */
    public static String cookieHeader(Context context, String url) {
        String host = hostOf(url);
        if (host.isEmpty()) return "";
        return prefs(context).getString(KEY_COOKIES + host, "");
    }

    public static boolean hasChallengeCookie(Context context, String url) {
        return cookieHeader(context, url).contains("__test=");
    }

    /** Replace the whole cookie set (used after the WebView solved the challenge). */
    static void replaceCookies(Context context, String url, String cookieString) {
        String host = hostOf(url);
        if (host.isEmpty()) return;
        Map<String, String> map = parse(cookieString);
        prefs(context).edit().putString(KEY_COOKIES + host, join(map)).apply();
    }

    /** Merge Set-Cookie headers from a native response. */
    static void mergeSetCookies(Context context, String url, List<String> setCookies) {
        if (setCookies == null || setCookies.isEmpty()) return;
        String host = hostOf(url);
        if (host.isEmpty()) return;
        Map<String, String> map = parse(prefs(context).getString(KEY_COOKIES + host, ""));
        for (String header : setCookies) {
            if (header == null) continue;
            String lower = header.toLowerCase(Locale.US);
            String first = header.split(";", 2)[0].trim();
            int eq = first.indexOf('=');
            if (eq <= 0) continue;
            String name = first.substring(0, eq).trim();
            String value = first.substring(eq + 1).trim();
            boolean expired = value.isEmpty() || "deleted".equals(value)
                    || lower.contains("max-age=0") || lower.contains("expires=thu, 01 jan 1970");
            if (expired) map.remove(name); else map.put(name, value);
        }
        prefs(context).edit().putString(KEY_COOKIES + host, join(map)).apply();
    }

    static void clearCookies(Context context, String url) {
        String host = hostOf(url);
        if (!host.isEmpty()) prefs(context).edit().remove(KEY_COOKIES + host).apply();
    }

    private static Map<String, String> parse(String cookieString) {
        Map<String, String> map = new LinkedHashMap<>();
        if (cookieString == null) return map;
        for (String part : cookieString.split(";")) {
            String p = part.trim();
            int eq = p.indexOf('=');
            if (eq > 0) map.put(p.substring(0, eq).trim(), p.substring(eq + 1).trim());
        }
        return map;
    }

    private static String join(Map<String, String> map) {
        StringBuilder sb = new StringBuilder();
        for (Map.Entry<String, String> e : map.entrySet()) {
            if (sb.length() > 0) sb.append("; ");
            sb.append(e.getKey()).append('=').append(e.getValue());
        }
        return sb.toString();
    }
}
