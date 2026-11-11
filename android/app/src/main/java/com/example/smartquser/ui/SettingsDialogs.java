package com.example.smartquser.ui;

import android.app.Activity;

import androidx.annotation.Nullable;

import com.example.smartquser.R;
import com.example.smartquser.utils.AppPrefs;
import com.example.smartquser.utils.Lang;
import com.google.android.material.dialog.MaterialAlertDialogBuilder;

/**
 * Language (Phone / English / العربية) and appearance (System / Light / Dark) pickers, shared by
 * the Welcome screen and Profile settings. Choices apply immediately and persist.
 */
public final class SettingsDialogs {

    private SettingsDialogs() {}

    public static final String[] LANG_TAGS = {Lang.SYSTEM, Lang.EN, Lang.AR};
    public static final int[] THEMES = {AppPrefs.THEME_SYSTEM, AppPrefs.THEME_LIGHT, AppPrefs.THEME_DARK};

    /** Label of the current language choice (for a settings row). */
    public static String languageLabel(Activity a) {
        String tag = Lang.chosen();
        if (Lang.AR.equals(tag)) return a.getString(R.string.language_arabic);
        if (Lang.EN.equals(tag)) return a.getString(R.string.language_english);
        return a.getString(R.string.language_system);
    }

    public static String themeLabel(Activity a) {
        int mode = new AppPrefs(a).themeMode();
        if (mode == AppPrefs.THEME_LIGHT) return a.getString(R.string.theme_light);
        if (mode == AppPrefs.THEME_DARK) return a.getString(R.string.theme_dark);
        return a.getString(R.string.theme_system);
    }

    public static void pickLanguage(Activity a, @Nullable Runnable after) {
        String[] labels = {a.getString(R.string.language_system), a.getString(R.string.language_english),
                a.getString(R.string.language_arabic)};
        String current = Lang.chosen();
        int checked = 0;
        for (int i = 0; i < LANG_TAGS.length; i++) if (LANG_TAGS[i].equals(current)) checked = i;
        new MaterialAlertDialogBuilder(a)
                .setTitle(R.string.settings_language)
                .setSingleChoiceItems(labels, checked, (d, which) -> {
                    d.dismiss();
                    new AppPrefs(a).setLanguageChosen(true);
                    if (!LANG_TAGS[which].equals(Lang.chosen())) Lang.apply(LANG_TAGS[which]);
                    if (after != null) after.run();
                })
                .setNegativeButton(R.string.action_cancel, null)
                .show();
    }

    public static void pickTheme(Activity a, @Nullable Runnable after) {
        String[] labels = {a.getString(R.string.theme_system), a.getString(R.string.theme_light),
                a.getString(R.string.theme_dark)};
        AppPrefs prefs = new AppPrefs(a);
        int current = prefs.themeMode();
        int checked = 0;
        for (int i = 0; i < THEMES.length; i++) if (THEMES[i] == current) checked = i;
        new MaterialAlertDialogBuilder(a)
                .setTitle(R.string.settings_theme)
                .setSingleChoiceItems(labels, checked, (d, which) -> {
                    d.dismiss();
                    prefs.setThemeMode(THEMES[which]);
                    Lang.applyTheme(THEMES[which]);
                    if (after != null) after.run();
                })
                .setNegativeButton(R.string.action_cancel, null)
                .show();
    }
}
