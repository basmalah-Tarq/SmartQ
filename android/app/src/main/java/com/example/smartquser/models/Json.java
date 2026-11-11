package com.example.smartquser.models;

import org.json.JSONObject;

/** Null-safe JSON string reading ("null" and missing keys become ""). */
public final class Json {
    private Json() {}

    public static String str(JSONObject o, String key) {
        if (o == null || !o.has(key) || o.isNull(key)) return "";
        String v = o.optString(key, "").trim();
        return "null".equalsIgnoreCase(v) ? "" : v;
    }
}
