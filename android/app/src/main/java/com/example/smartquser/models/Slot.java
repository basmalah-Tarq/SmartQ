package com.example.smartquser.models;

import org.json.JSONObject;

public class Slot {
    public String time;   // HH:MM:SS, sent back to book_appointment.php
    public String label;  // "9:30 AM"
    public boolean available;
    public String reason; // null | booked | past | full

    public static Slot fromJson(JSONObject o) {
        Slot s = new Slot();
        s.time = Json.str(o, "time");
        s.label = Json.str(o, "label");
        s.available = o.optBoolean("available", false);
        s.reason = o.isNull("reason") ? "" : Json.str(o, "reason");
        return s;
    }
}
