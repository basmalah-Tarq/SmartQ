package com.example.smartquser.utils;

import android.content.Context;
import android.content.SharedPreferences;

import org.json.JSONObject;

/** Logged-in user + API token, stored in private SharedPreferences. */
public class SessionManager {

    private static final String PREF_NAME = "smartq_user";

    private static final String KEY_USER_ID = "user_id";
    private static final String KEY_FULL_NAME = "full_name";
    private static final String KEY_EMAIL = "email";
    private static final String KEY_PHONE = "phone";
    private static final String KEY_BIRTH_DATE = "birth_date";
    private static final String KEY_PROFILE_IMAGE_URL = "profile_image_url";
    private static final String KEY_API_TOKEN = "api_token";

    private final SharedPreferences prefs;

    public SessionManager(Context context) {
        prefs = context.getApplicationContext().getSharedPreferences(PREF_NAME, Context.MODE_PRIVATE);
    }

    /** Save the "user" object returned by login/register/profile (+ the token when given). */
    public void saveUser(JSONObject user, String apiToken) {
        if (user == null) return;
        SharedPreferences.Editor e = prefs.edit();
        e.putInt(KEY_USER_ID, user.optInt("id", getUserId()));
        e.putString(KEY_FULL_NAME, user.optString("full_name", getFullName()));
        e.putString(KEY_EMAIL, user.optString("email", getEmail()));
        e.putString(KEY_PHONE, clean(user.optString("phone", getPhone())));
        e.putString(KEY_BIRTH_DATE, DateUtils.sanitizeApiDate(clean(user.optString("birth_date", getBirthDate()))));
        if (user.has("profile_image_url")) {
            e.putString(KEY_PROFILE_IMAGE_URL, clean(user.optString("profile_image_url", "")));
        }
        if (apiToken != null) e.putString(KEY_API_TOKEN, apiToken);
        e.apply();
    }

    public void saveProfileImageUrl(String url) {
        prefs.edit().putString(KEY_PROFILE_IMAGE_URL, clean(url)).apply();
    }

    public String getProfileImageUrl() {
        return prefs.getString(KEY_PROFILE_IMAGE_URL, "");
    }

    public int getUserId() {
        return prefs.getInt(KEY_USER_ID, 0);
    }

    public String getFullName() {
        return prefs.getString(KEY_FULL_NAME, "");
    }

    public String getFirstName() {
        String fullName = getFullName().trim();
        if (fullName.isEmpty()) return "there";
        return fullName.split("\\s+")[0];
    }

    public String getInitials() {
        String fullName = getFullName().trim();
        if (fullName.isEmpty()) return "?";
        String[] parts = fullName.split("\\s+");
        String s = parts[0].substring(0, 1);
        if (parts.length > 1) s += parts[parts.length - 1].substring(0, 1);
        return s.toUpperCase(java.util.Locale.ROOT);
    }

    public String getEmail() {
        return prefs.getString(KEY_EMAIL, "");
    }

    public String getPhone() {
        return prefs.getString(KEY_PHONE, "");
    }

    public String getBirthDate() {
        return prefs.getString(KEY_BIRTH_DATE, "");
    }

    public String getToken() {
        String t = prefs.getString(KEY_API_TOKEN, "");
        return t != null ? t.trim() : "";
    }

    public boolean isLoggedIn() {
        return !getToken().isEmpty();
    }

    public void logout() {
        prefs.edit().clear().apply();
    }

    private static String clean(String s) {
        if (s == null) return "";
        s = s.trim();
        return "null".equalsIgnoreCase(s) ? "" : s;
    }
}
