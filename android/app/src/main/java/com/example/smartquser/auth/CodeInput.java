package com.example.smartquser.auth;

import java.util.Locale;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

/** Pure helpers for the 6-digit email code (typing, pasting, resend countdown). Unit tested. */
public final class CodeInput {

    public static final int LENGTH = 6;

    /** "048 608" / "048-608" / "048608" surrounded by anything but digits. */
    private static final Pattern GROUPED = Pattern.compile("(?<!\\d)(\\d{3})[ \\-\\u00A0]?(\\d{3})(?!\\d)");
    private static final Pattern EXACT = Pattern.compile("(?<!\\d)(\\d{6})(?!\\d)");

    private CodeInput() {}

    /** Keeps only ASCII digits, at most {@code max} of them. */
    public static String digitsOnly(CharSequence raw, int max) {
        if (raw == null) return "";
        StringBuilder sb = new StringBuilder(Math.min(raw.length(), max));
        for (int i = 0; i < raw.length() && sb.length() < max; i++) {
            char c = raw.charAt(i);
            if (c >= '0' && c <= '9') sb.append(c);
        }
        return sb.toString();
    }

    /**
     * Finds the code in pasted text, e.g. "Your SmartQ code: 048608" or "048 608".
     * Returns null when there is no unambiguous 6-digit code.
     */
    public static String extractCode(CharSequence text) {
        if (text == null) return null;
        String s = text.toString();
        Matcher m = EXACT.matcher(s);
        if (m.find()) return m.group(1);
        m = GROUPED.matcher(s);
        if (m.find()) return m.group(1) + m.group(2);
        String digits = digitsOnly(s, Integer.MAX_VALUE);
        return digits.length() == LENGTH ? digits : null;
    }

    public static boolean isComplete(CharSequence code) {
        return code != null && code.length() == LENGTH && digitsOnly(code, LENGTH).length() == LENGTH;
    }

    /** 75 -> "1:15", 9 -> "0:09". Negative values count as 0. */
    public static String formatCountdown(long seconds) {
        long s = Math.max(0, seconds);
        return String.format(Locale.US, "%d:%02d", s / 60, s % 60);
    }

    /** Seconds left until {@code deadlineMs} (rounded up so "0:00" only shows when it's over). */
    public static long secondsLeft(long nowMs, long deadlineMs) {
        long ms = deadlineMs - nowMs;
        return ms <= 0 ? 0 : (ms + 999) / 1000;
    }
}
