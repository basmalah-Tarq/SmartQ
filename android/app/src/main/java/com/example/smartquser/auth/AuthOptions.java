package com.example.smartquser.auth;

import org.json.JSONException;
import org.json.JSONObject;

/** Which sign-in methods the server has switched on (api/user/auth_options.php), cached by AppPrefs. */
public final class AuthOptions {

    public final boolean emailCode;
    public final boolean google;
    public final String googleClientId;
    public final boolean password;

    public AuthOptions(boolean emailCode, boolean google, String googleClientId, boolean password) {
        this.emailCode = emailCode;
        this.google = google;
        this.googleClientId = googleClientId == null ? "" : googleClientId;
        this.password = password;
    }

    /** Used until the server answered once: email codes on, Google off. */
    public static AuthOptions defaults() {
        return new AuthOptions(true, false, "", true);
    }

    public static AuthOptions fromJson(JSONObject o) {
        if (o == null) return defaults();
        return new AuthOptions(o.optBoolean("email_code", true), o.optBoolean("google", false),
                o.optString("google_client_id", ""), o.optBoolean("password", true));
    }

    public JSONObject toJson() {
        JSONObject o = new JSONObject();
        try {
            o.put("email_code", emailCode);
            o.put("google", google);
            o.put("google_client_id", googleClientId);
            o.put("password", password);
        } catch (JSONException ignored) {
            // keys are constant
        }
        return o;
    }

    /** "Continue with Google" needs the server switch AND a Web client ID compiled into the app. */
    public boolean showGoogle(String appWebClientId) {
        return showGoogle(google, appWebClientId);
    }

    public static boolean showGoogle(boolean serverGoogle, String appWebClientId) {
        return serverGoogle && appWebClientId != null && !appWebClientId.trim().isEmpty();
    }

    /** Without email codes the password login becomes the main way in. */
    public boolean passwordIsPrimary() {
        return !emailCode;
    }
}
