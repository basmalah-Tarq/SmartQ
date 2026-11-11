package com.example.smartquser.net;

import org.json.JSONObject;

/** A successful API call: {"status":true,"message":"...","data":{...}}. data is never null. */
public final class ApiResponse {
    public final JSONObject data;
    public final String message;
    public final int httpCode;

    ApiResponse(JSONObject data, String message, int httpCode) {
        this.data = data != null ? data : new JSONObject();
        this.message = message != null ? message : "";
        this.httpCode = httpCode;
    }
}
