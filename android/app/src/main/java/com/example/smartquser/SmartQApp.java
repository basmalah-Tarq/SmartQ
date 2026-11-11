package com.example.smartquser;

import android.app.Application;

import com.example.smartquser.utils.AppPrefs;
import com.example.smartquser.utils.Lang;

/** Applies the saved Dark / Light / System choice before the first screen is drawn. */
public class SmartQApp extends Application {
    @Override
    public void onCreate() {
        super.onCreate();
        Lang.applyTheme(new AppPrefs(this).themeMode());
    }
}
