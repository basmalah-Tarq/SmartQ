package com.example.smartquser.models;

import com.example.smartquser.utils.DateUtils;

import org.json.JSONObject;

import java.util.Date;

/** An appointment from appointments.php / next_appointment.php (flat fields + nested fallback). */
public class Appointment {
    public int id;
    public int queueNumber;
    public String queueCode;
    public String status;
    public String time;
    public String timeLabel;
    public String date;
    public String startTime;
    public String endTime;
    public String placeName;
    public String category;
    public String city;
    public String address;
    public String placeImageUrl;
    public boolean canCancel;
    public int placeId;
    public int scheduleId;

    public static Appointment fromJson(JSONObject o) {
        Appointment a = new Appointment();
        JSONObject schedule = o.optJSONObject("schedule");
        JSONObject place = o.optJSONObject("place");
        if (schedule == null) schedule = new JSONObject();
        if (place == null) place = new JSONObject();

        a.id = o.optInt("id");
        a.queueNumber = o.optInt("queue_number");
        a.queueCode = Json.str(o, "queue_code");
        if (a.queueCode.isEmpty() && a.queueNumber > 0) {
            a.queueCode = String.format(java.util.Locale.US, "Q-%03d", a.queueNumber);
        }
        a.status = Json.str(o, "appointment_status");
        if (a.status.isEmpty()) a.status = Json.str(o, "status");
        a.date = first(Json.str(o, "schedule_date"), Json.str(schedule, "schedule_date"));
        a.startTime = first(Json.str(o, "start_time"), Json.str(schedule, "start_time"));
        a.endTime = first(Json.str(o, "end_time"), Json.str(schedule, "end_time"));
        a.time = first(Json.str(o, "appointment_time"), a.startTime);
        a.timeLabel = first(Json.str(o, "appointment_time_label"), DateUtils.timeLabel(a.time));
        a.placeName = first(Json.str(o, "place_name"), Json.str(place, "place_name"));
        a.category = Json.str(o, "category");
        a.city = Json.str(o, "city");
        a.address = Json.str(o, "address");
        a.placeImageUrl = Json.str(o, "place_image_url");
        a.canCancel = o.optBoolean("can_cancel", false);
        a.placeId = place.optInt("id");
        a.scheduleId = schedule.optInt("id");
        return a;
    }

    /** Upcoming = still active (waiting/confirmed) and not over yet. */
    public boolean isUpcoming() {
        if (canCancel) return true;
        boolean active = "waiting".equals(status) || "confirmed".equals(status);
        if (!active) return false;
        Date end = DateUtils.parseDateTime(date, endTime.isEmpty() ? "23:59:59" : endTime);
        return end != null && end.getTime() >= System.currentTimeMillis();
    }

    public String location() {
        if (address.isEmpty()) return city;
        if (city.isEmpty()) return address;
        return address + ", " + city;
    }

    private static String first(String a, String b) {
        return a != null && !a.isEmpty() ? a : (b == null ? "" : b);
    }
}
