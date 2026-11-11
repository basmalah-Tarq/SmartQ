package com.example.smartquser.net;

import androidx.annotation.NonNull;

import org.json.JSONObject;

/** A failed API call, already translated into a message that can be shown to the user as-is. */
public final class ApiError {

    public enum Kind {
        /** The device has no network connection. */
        NO_INTERNET,
        /** The server did not answer in time (after the automatic retry). */
        TIMEOUT,
        /** DNS / connection refused / TLS problems: the server cannot be reached. */
        UNREACHABLE,
        /** The hosting provider's anti-bot page could not be passed. */
        BLOCKED,
        /** HTTP 5xx or an HTML error page instead of JSON. */
        SERVER,
        /** The body was not the JSON we expected. */
        PARSE,
        /** Guest mode: this needs an account, so the request was not sent (or the token was rejected). */
        SIGN_IN_REQUIRED,
        /** 401 with data.code AUTH_REQUIRED / AUTH_INVALID / AUTH_EXPIRED. */
        AUTH,
        /** A normal API error: {"status":false,"message":"..."} (validation, 409 booking conflicts...). */
        API
    }

    public final Kind kind;
    public final String message;
    public final int httpCode;
    public final JSONObject data;
    /** App-side message (string resource) resolved on the UI thread in the app language; 0 = server text. */
    public final int messageRes;

    public ApiError(Kind kind, String message, int httpCode, JSONObject data) {
        this(kind, message, 0, httpCode, data);
    }

    public ApiError(Kind kind, String message, int messageRes, int httpCode, JSONObject data) {
        this.kind = kind;
        this.message = message;
        this.messageRes = messageRes;
        this.httpCode = httpCode;
        this.data = data != null ? data : new JSONObject();
    }

    /** An app-side error whose text comes from strings.xml (English fallback kept for logs/tests). */
    public static ApiError res(Kind kind, int messageRes, String fallback, int httpCode, JSONObject data) {
        return new ApiError(kind, fallback, messageRes, httpCode, data);
    }

    public ApiError withMessage(String localized) {
        return new ApiError(kind, localized, 0, httpCode, data);
    }

    /** True for problems with the connection itself (show a "retry" state rather than a form error). */
    public boolean isNetworkProblem() {
        return kind == Kind.NO_INTERNET || kind == Kind.TIMEOUT || kind == Kind.UNREACHABLE
                || kind == Kind.BLOCKED || kind == Kind.SERVER || kind == Kind.PARSE;
    }

    @NonNull
    @Override
    public String toString() {
        return kind + "(" + httpCode + "): " + message;
    }
}
