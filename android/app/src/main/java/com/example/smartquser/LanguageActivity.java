package com.example.smartquser;

import android.content.Intent;
import android.content.res.Resources;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.view.View;

import com.example.smartquser.utils.Lang;
import com.example.smartquser.utils.Ui;
import com.google.android.material.card.MaterialCardView;

import java.util.Locale;

/**
 * First launch only: English / العربية (or the phone language), then the onboarding continues in
 * that language. The choice is stored by AndroidX (per-app language) and can be changed later in
 * Profile or on the Welcome screen.
 */
public class LanguageActivity extends BaseActivity {

    /** If the locale change does not recreate this screen (same language), continue ourselves. */
    private static final long FALLBACK_MS = 450;

    private final Handler handler = new Handler(Looper.getMainLooper());
    private boolean navigated;

    @Override
    protected boolean darkStatusBar() {
        return true;
    }

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        if (prefs.isLanguageChosen()) {
            // recreated by the language change (or opened again by mistake): go on
            next();
            return;
        }
        setContentView(R.layout.activity_language);
        Ui.padTop(findViewById(R.id.hero));
        Ui.padBottom(findViewById(R.id.cards));

        MaterialCardView en = findViewById(R.id.cardEnglish);
        MaterialCardView ar = findViewById(R.id.cardArabic);
        en.setOnClickListener(v -> choose(Lang.EN));
        ar.setOnClickListener(v -> choose(Lang.AR));
        findViewById(R.id.btnPhoneLanguage).setOnClickListener(v -> choose(Lang.SYSTEM));

        // highlight the card that matches the phone language
        boolean phoneArabic = "ar".equals(phoneLocale().getLanguage());
        MaterialCardView suggested = phoneArabic ? ar : en;
        suggested.setStrokeWidth(Ui.dp(this, 2));
        findViewById(phoneArabic ? R.id.suggestArabic : R.id.suggestEnglish).setVisibility(View.VISIBLE);

        Ui.enterAll(findViewById(R.id.langPlate), findViewById(R.id.langBilingual), findViewById(R.id.langTitle),
                findViewById(R.id.langSubtitle), en, ar, findViewById(R.id.btnPhoneLanguage));
    }

    private static Locale phoneLocale() {
        android.os.LocaleList list = Resources.getSystem().getConfiguration().getLocales();
        return list.isEmpty() ? Locale.getDefault() : list.get(0);
    }

    private void choose(String tag) {
        if (navigated) return;
        prefs.setLanguageChosen(true);
        if (!tag.equals(Lang.chosen())) {
            Lang.apply(tag); // AppCompat recreates this screen in the new language -> onCreate -> next()
            handler.postDelayed(this::next, FALLBACK_MS);
        } else {
            next();
        }
    }

    private void next() {
        if (navigated || isFinishing()) return;
        navigated = true;
        Class<?> target = session.isLoggedIn() ? DashboardActivity.class
                : prefs.isOnboardingDone() ? WelcomeActivity.class : OnboardingActivity.class;
        Intent i = new Intent(this, target);
        i.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_CLEAR_TASK);
        startActivity(i);
        fadeTransition();
        finish();
    }

    @Override
    protected void onDestroy() {
        handler.removeCallbacksAndMessages(null);
        super.onDestroy();
    }
}
