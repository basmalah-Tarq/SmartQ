package com.example.smartquser.models;

import org.json.JSONObject;

public class Schedule {
    public int id;
    public String date;
    public String startTime;
    public String endTime;
    public int capacity;
    public int booked;
    public int seatsLeft;
    public int slotDuration;

    public static Schedule fromJson(JSONObject o) {
        Schedule s = new Schedule();
        s.id = o.optInt("id");
        s.date = Json.str(o, "schedule_date");
        s.startTime = Json.str(o, "start_time");
        s.endTime = Json.str(o, "end_time");
        s.capacity = o.optInt("capacity");
        s.booked = o.optInt("booked_count");
        s.seatsLeft = o.optInt("available_slots", Math.max(0, s.capacity - s.booked));
        s.slotDuration = o.optInt("slot_duration", 30);
        return s;
    }

    /** 0..100: how full the schedule is. */
    public int fillPercent() {
        if (capacity <= 0) return 0;
        return Math.min(100, Math.round(booked * 100f / capacity));
    }
}
