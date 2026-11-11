package com.example.smartquser.utils;

import android.content.Context;
import android.content.res.Configuration;

import androidx.appcompat.app.AppCompatDelegate;
import androidx.core.os.LocaleListCompat;

import java.util.Locale;

/**
 * Per-app language (English / Arabic / follow the phone) and Dark / Light / System theme.
 * The language is stored by AndroidX itself (autoStoreLocales in the manifest), the theme in AppPrefs.
 */
public final class Lang {

    public static final String SYSTEM = "";
    public static final String EN = "en";
    public static final String AR = "ar";

    /** Left-to-right isolate: keeps "Q-007", e-mails and times readable inside Arabic sentences. */
    private static final char LRI = '⁦', PDI = '⁩';

    private Lang() {}

    /** "", "en" or "ar" — what the user picked in the app ("" = follow the phone). */
    public static String chosen() {
        LocaleListCompat list = AppCompatDelegate.getApplicationLocales();
        if (list.isEmpty() || list.get(0) == null) return SYSTEM;
        return AR.equals(list.get(0).getLanguage()) ? AR : EN;
    }

    /** Applies the language immediately (activities are recreated by AppCompat). */
    public static void apply(String tag) {
        AppCompatDelegate.setApplicationLocales(tag == null || tag.isEmpty()
                ? LocaleListCompat.getEmptyLocaleList() : LocaleListCompat.forLanguageTags(tag));
    }

    /** The locale the given (activity) context is really showing. */
    public static Locale locale(Context c) {
        Configuration cfg = c.getResources().getConfiguration();
        Locale l = cfg.getLocales().isEmpty() ? null : cfg.getLocales().get(0);
        return l != null ? l : Locale.getDefault();
    }

    public static boolean isArabic(Context c) {
        return AR.equals(locale(c).getLanguage());
    }

    /**
     * Locale for dates and times: localized month / day names but Western digits, so times line up
     * with queue codes like Q-007.
     */
    public static Locale dateLocale(Context c) {
        Locale l = locale(c);
        if (AR.equals(l.getLanguage())) return Locale.forLanguageTag("ar-u-nu-latn");
        return l;
    }

    /** Wraps a code / number / e-mail so it stays left-to-right inside a right-to-left sentence. */
    public static String ltr(String s) {
        if (s == null || s.isEmpty()) return "";
        return LRI + s + PDI;
    }

    /** Integer as Western digits (used in every %s placeholder that shows a number). */
    public static String num(long n) {
        return String.valueOf(n);
    }

    // ------------------------------------------------------------ theme

    public static void applyTheme(int themeMode) {
        int mode;
        if (themeMode == AppPrefs.THEME_LIGHT) mode = AppCompatDelegate.MODE_NIGHT_NO;
        else if (themeMode == AppPrefs.THEME_DARK) mode = AppCompatDelegate.MODE_NIGHT_YES;
        else mode = AppCompatDelegate.MODE_NIGHT_FOLLOW_SYSTEM;
        if (AppCompatDelegate.getDefaultNightMode() != mode) AppCompatDelegate.setDefaultNightMode(mode);
    }

    public static boolean isDark(Context c) {
        return (c.getResources().getConfiguration().uiMode & Configuration.UI_MODE_NIGHT_MASK)
                == Configuration.UI_MODE_NIGHT_YES;
    }
}
