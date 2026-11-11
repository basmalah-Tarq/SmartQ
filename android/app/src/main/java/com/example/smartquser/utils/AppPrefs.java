package com.example.smartquser.utils;

import android.content.Context;
import android.content.SharedPreferences;

import com.example.smartquser.auth.AuthOptions;

import org.json.JSONObject;

/**
 * App-level flags that must survive a logout (kept apart from SessionManager, whose logout clears
 * everything): onboarding seen, guest mode, cached sign-in options.
 */
public final class AppPrefs {

    private static final String PREF_NAME = "smartq_app";
    private static final String KEY_ONBOARDING_DONE = "onboarding_done";
    private static final String KEY_GUEST = "guest";
    private static final String KEY_AUTH_OPTIONS = "auth_options";
    private static final String KEY_LANGUAGE_CHOSEN = "language_chosen";
    private static final String KEY_THEME = "theme_mode";

    /** Theme choices (stored values). */
    public static final int THEME_SYSTEM = 0, THEME_LIGHT = 1, THEME_DARK = 2;

    private final SharedPreferences prefs;

    public AppPrefs(Context context) {
        prefs = context.getApplicationContext().getSharedPreferences(PREF_NAME, Context.MODE_PRIVATE);
    }

    public boolean isOnboardingDone() {
        return prefs.getBoolean(KEY_ONBOARDING_DONE, false);
    }

    public void setOnboardingDone(boolean done) {
        prefs.edit().putBoolean(KEY_ONBOARDING_DONE, done).apply();
    }

    public boolean isGuest() {
        return prefs.getBoolean(KEY_GUEST, false);
    }

    public void setGuest(boolean guest) {
        prefs.edit().putBoolean(KEY_GUEST, guest).apply();
    }

    public AuthOptions authOptions() {
        String raw = prefs.getString(KEY_AUTH_OPTIONS, "");
        if (raw == null || raw.isEmpty()) return AuthOptions.defaults();
        try {
            return AuthOptions.fromJson(new JSONObject(raw));
        } catch (Exception e) {
            return AuthOptions.defaults();
        }
    }

    public void saveAuthOptions(JSONObject data) {
        if (data == null) return;
        prefs.edit().putString(KEY_AUTH_OPTIONS, AuthOptions.fromJson(data).toJson().toString()).apply();
    }

    /** The first-launch language picker was answered (later launches skip it). */
    public boolean isLanguageChosen() {
        return prefs.getBoolean(KEY_LANGUAGE_CHOSEN, false);
    }

    public void setLanguageChosen(boolean chosen) {
        prefs.edit().putBoolean(KEY_LANGUAGE_CHOSEN, chosen).apply();
    }

    public int themeMode() {
        return prefs.getInt(KEY_THEME, THEME_SYSTEM);
    }

    public void setThemeMode(int mode) {
        prefs.edit().putInt(KEY_THEME, mode).apply();
    }
}
