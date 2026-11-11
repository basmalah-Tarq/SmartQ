package com.example.smartquser.utils;

import android.content.Context;

import com.example.smartquser.R;

import java.text.ParseException;
import java.text.SimpleDateFormat;
import java.util.Calendar;
import java.util.Date;
import java.util.Locale;

/** Date/time helpers. The API always uses yyyy-MM-dd and HH:mm:ss. */
public final class DateUtils {
    private static final Locale API_LOCALE = Locale.US;
    private static Locale UI = Locale.ENGLISH;
    private static String today = "Today", tomorrow = "Tomorrow", yesterday = "Yesterday";
    private static String morning = "Good morning", afternoon = "Good afternoon", evening = "Good evening";
    private static String sep = " · ", rangeSep = " – ";

    private DateUtils() {}

    /** Called by every screen: day / month names and words follow the app language. */
    public static void configure(Context c) {
        UI = Lang.dateLocale(c);
        today = c.getString(R.string.date_today);
        tomorrow = c.getString(R.string.date_tomorrow);
        yesterday = c.getString(R.string.date_yesterday);
        morning = c.getString(R.string.greeting_morning);
        afternoon = c.getString(R.string.greeting_afternoon);
        evening = c.getString(R.string.greeting_evening);
    }

    public static Locale uiLocale() {
        return UI;
    }

    public static String toApiDate(int year, int zeroBasedMonth, int dayOfMonth) {
        return String.format(API_LOCALE, "%04d-%02d-%02d", year, zeroBasedMonth + 1, dayOfMonth);
    }

    public static String normalizeDigits(String input) {
        if (input == null) return "";
        return input
                .replace('٠', '0').replace('١', '1').replace('٢', '2')
                .replace('٣', '3').replace('٤', '4').replace('٥', '5')
                .replace('٦', '6').replace('٧', '7').replace('٨', '8')
                .replace('٩', '9');
    }

    public static String sanitizeApiDate(String input) {
        String normalized = normalizeDigits(input).trim().replace('/', '-');
        return normalized.matches("\\d{4}-\\d{2}-\\d{2}") ? normalized : "";
    }

    public static Date parseDate(String apiDate) {
        String d = sanitizeApiDate(apiDate);
        if (d.isEmpty()) return null;
        try {
            SimpleDateFormat f = new SimpleDateFormat("yyyy-MM-dd", API_LOCALE);
            f.setLenient(false);
            return f.parse(d);
        } catch (ParseException e) {
            return null;
        }
    }

    public static Date parseDateTime(String apiDate, String time) {
        String d = sanitizeApiDate(apiDate);
        if (d.isEmpty()) return null;
        String t = time == null ? "00:00:00" : time.trim();
        if (t.matches("\\d{2}:\\d{2}")) t += ":00";
        try {
            return new SimpleDateFormat("yyyy-MM-dd HH:mm:ss", API_LOCALE).parse(d + " " + t);
        } catch (ParseException e) {
            return null;
        }
    }

    private static String fmt(Date d, String pattern) {
        if (d == null) return "";
        String s = new SimpleDateFormat(pattern, UI).format(d);
        return "ar".equals(UI.getLanguage()) ? s.replace(", ", "، ") : s;
    }

    /** "Today", "Tomorrow" or "Thu, 12 Nov". */
    public static String friendlyDate(String apiDate) {
        Date d = parseDate(apiDate);
        if (d == null) return apiDate == null ? "" : apiDate;
        long diff = daysFromToday(d);
        if (diff == 0) return today;
        if (diff == 1) return tomorrow;
        if (diff == -1) return yesterday;
        return fmt(d, "EEE, d MMM");
    }

    /** "Today · 12 Nov" or "Thu, 12 Nov 2026" */
    public static String listDate(String apiDate) {
        Date d = parseDate(apiDate);
        if (d == null) return apiDate == null ? "" : apiDate;
        long diff = daysFromToday(d);
        if (diff >= -1 && diff <= 1) return friendlyDate(apiDate) + sep + fmt(d, "d MMM");
        return fmt(d, "EEE, d MMM yyyy");
    }

    /** "Thursday, 12 November 2026" */
    public static String longDate(String apiDate) {
        Date d = parseDate(apiDate);
        return d == null ? (apiDate == null ? "" : apiDate) : fmt(d, "EEEE, d MMMM yyyy");
    }

    /** "12 Nov 2026" */
    public static String mediumDate(String apiDate) {
        Date d = parseDate(apiDate);
        return d == null ? (apiDate == null ? "" : apiDate) : fmt(d, "d MMM yyyy");
    }

    public static String dayOfMonth(String apiDate) {
        return fmt(parseDate(apiDate), "d");
    }

    public static String monthShort(String apiDate) {
        return fmt(parseDate(apiDate), "MMM").toUpperCase(UI);
    }

    public static String weekdayShort(String apiDate) {
        return fmt(parseDate(apiDate), "EEE");
    }

    /** "09:30:00" -> "9:30 AM" */
    public static String timeLabel(String time) {
        if (time == null || time.trim().isEmpty()) return "";
        String t = time.trim();
        if (t.matches("\\d{2}:\\d{2}")) t += ":00";
        try {
            Date d = new SimpleDateFormat("HH:mm:ss", API_LOCALE).parse(t);
            return fmt(d, "h:mm a");
        } catch (ParseException e) {
            return time;
        }
    }

    public static String timeRange(String start, String end) {
        return timeLabel(start) + rangeSep + timeLabel(end);
    }

    /** Days between today and the date (negative = past). */
    public static long daysFromToday(Date d) {
        Calendar a = Calendar.getInstance();
        Calendar b = Calendar.getInstance();
        b.setTime(d);
        zero(a);
        zero(b);
        return Math.round((b.getTimeInMillis() - a.getTimeInMillis()) / 86_400_000d);
    }

    private static void zero(Calendar c) {
        c.set(Calendar.HOUR_OF_DAY, 0);
        c.set(Calendar.MINUTE, 0);
        c.set(Calendar.SECOND, 0);
        c.set(Calendar.MILLISECOND, 0);
    }

    /** "Good morning" / "Good afternoon" / "Good evening". */
    public static String greeting() {
        int h = Calendar.getInstance().get(Calendar.HOUR_OF_DAY);
        if (h < 12) return morning;
        if (h < 18) return afternoon;
        return evening;
    }
}
