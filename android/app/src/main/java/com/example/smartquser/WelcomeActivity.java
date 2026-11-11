package com.example.smartquser;

import android.animation.ObjectAnimator;
import android.animation.ValueAnimator;
import android.content.Intent;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.view.View;
import android.widget.ImageView;
import android.widget.TextView;

import androidx.core.content.ContextCompat;

import com.example.smartquser.auth.AuthOptions;
import com.example.smartquser.net.ApiClient;
import com.example.smartquser.net.ApiError;
import com.example.smartquser.net.ApiResponse;
import com.example.smartquser.ui.SettingsDialogs;
import com.example.smartquser.utils.AppPrefs;
import com.example.smartquser.utils.DateUtils;
import com.example.smartquser.utils.Lang;
import com.example.smartquser.utils.Ui;
import com.google.android.material.button.MaterialButton;

import java.util.Locale;

/**
 * Entry screen when nobody is signed in: Continue with email (primary), Continue with Google
 * (when configured), Browse as guest, and a small link to the classic password login. The top
 * corner switches language and appearance.
 */
public class WelcomeActivity extends BaseActivity {

    private final Handler handler = new Handler(Looper.getMainLooper());
    private MaterialButton btnGoogle;
    private View googleProgress;
    private TextView tvMessage, liveNumber;
    private int serving = 5;

    @Override
    protected boolean darkStatusBar() {
        return true;
    }

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        if (session.isLoggedIn()) {
            goHomeAfterAuth(false);
            return;
        }
        setContentView(R.layout.activity_welcome);
        Ui.padTop(findViewById(R.id.hero));
        Ui.padBottom(findViewById(R.id.footer));

        btnGoogle = findViewById(R.id.btnGoogle);
        googleProgress = findViewById(R.id.googleProgress);
        tvMessage = findViewById(R.id.tvMessage);
        liveNumber = findViewById(R.id.liveNumber);
        liveNumber.setText(code(serving));
        ((TextView) findViewById(R.id.heroPlace)).setText(getString(R.string.welcome_sample_line,
                Lang.ltr(DateUtils.timeLabel("10:30")), getString(R.string.welcome_sample_place)));

        findViewById(R.id.btnEmail).setOnClickListener(v -> openEmailSignIn(false));
        findViewById(R.id.btnPasswordPrimary).setOnClickListener(v -> openPasswordSignIn(false));
        btnGoogle.setOnClickListener(v -> {
            tvMessage.setVisibility(View.GONE);
            signInWithGoogle(welcomeUi());
        });
        findViewById(R.id.btnGuest).setOnClickListener(v -> browseAsGuest());

        // language + appearance (AppCompat recreates the screen after a change)
        findViewById(R.id.btnLanguage).setOnClickListener(v -> SettingsDialogs.pickLanguage(this, null));
        findViewById(R.id.btnTheme).setOnClickListener(v -> SettingsDialogs.pickTheme(this, this::bindSettings));
        bindSettings();

        applyOptions(prefs.authOptions());
        refreshOptions();

        String msg = getIntent().getStringExtra(EXTRA_MESSAGE);
        if (msg != null && !msg.isEmpty()) showMessage(msg);

        playIntro();
    }

    private static String code(int n) {
        return String.format(Locale.US, "Q-%03d", n);
    }

    private void bindSettings() {
        ((TextView) findViewById(R.id.languageLabel)).setText(Lang.isArabic(this)
                ? getString(R.string.language_arabic) : getString(R.string.language_english));
        int mode = prefs.themeMode();
        ((ImageView) findViewById(R.id.themeIcon)).setImageResource(mode == AppPrefs.THEME_DARK ? R.drawable.ic_dark_mode
                : mode == AppPrefs.THEME_LIGHT ? R.drawable.ic_light_mode : R.drawable.ic_contrast);
    }

    /** Show / hide sign-in methods according to the server's switches. */
    private void applyOptions(AuthOptions o) {
        boolean google = o.showGoogle(BuildConfig.GOOGLE_WEB_CLIENT_ID);
        findViewById(R.id.googleBox).setVisibility(google ? View.VISIBLE : View.GONE);
        boolean passwordFirst = o.passwordIsPrimary();
        findViewById(R.id.btnEmail).setVisibility(passwordFirst ? View.GONE : View.VISIBLE);
        findViewById(R.id.btnPasswordPrimary).setVisibility(passwordFirst ? View.VISIBLE : View.GONE);
        TextView footerText = findViewById(R.id.footerText);
        MaterialButton footerBtn = findViewById(R.id.btnPassword);
        if (passwordFirst) {
            footerText.setText(R.string.welcome_new);
            footerBtn.setText(R.string.welcome_create_account);
            footerBtn.setOnClickListener(v -> startActivity(new Intent(this, RegisterActivity.class)));
        } else {
            footerText.setText(R.string.welcome_have_password);
            footerBtn.setText(R.string.sign_in_with_password);
            footerBtn.setOnClickListener(v -> openPasswordSignIn(false));
        }
    }

    /** The splash already cached the options; refresh quietly in case they changed. */
    private void refreshOptions() {
        ApiClient.get(this, "auth_options.php", null, new ApiClient.Callback() {
            @Override
            public void onSuccess(ApiResponse r) {
                prefs.saveAuthOptions(r.data);
                applyOptions(prefs.authOptions());
            }

            @Override
            public void onError(ApiError e) {
                // keep the cached options
            }
        });
    }

    private AuthUi welcomeUi() {
        return new AuthUi() {
            @Override
            public void setBusy(boolean busy) {
                btnGoogle.setEnabled(!busy);
                btnGoogle.setText(busy ? "" : getString(R.string.continue_with_google));
                btnGoogle.setIcon(busy ? null : ContextCompat.getDrawable(WelcomeActivity.this, R.drawable.ic_google));
                googleProgress.setVisibility(busy ? View.VISIBLE : View.GONE);
            }

            @Override
            public void showError(String message) {
                showMessage(message);
            }
        };
    }

    private void showMessage(String message) {
        tvMessage.setText(message);
        tvMessage.setVisibility(View.VISIBLE);
        Ui.enter(tvMessage, 0);
    }

    @Override
    protected void onAuthenticated(ApiResponse r) {
        if (!saveAuth(r)) {
            showMessage(getString(R.string.signin_failed));
            return;
        }
        goHomeAfterAuth(r.data.optBoolean("is_new_user", false));
    }

    private void browseAsGuest() {
        prefs.setGuest(true);
        Intent i = new Intent(this, DashboardActivity.class);
        i.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_CLEAR_TASK);
        startActivity(i);
        fadeTransition();
        finish();
    }

    // ------------------------------------------------------------ motion

    private void playIntro() {
        Ui.enterAll(findViewById(R.id.title), findViewById(R.id.subtitle),
                findViewById(R.id.buttons), findViewById(R.id.footer));
        if (!Ui.motionEnabled(this)) return;
        float d = getResources().getDisplayMetrics().density;
        View ticket = findViewById(R.id.heroTicket);
        ticket.setAlpha(0f);
        ticket.setTranslationY(40 * d);
        ticket.setRotation(-9f);
        ticket.animate().alpha(1f).translationY(0).rotation(-3f).setStartDelay(150).setDuration(700)
                .setInterpolator(Ui.EASE_OUT).start();
        View live = findViewById(R.id.liveChip);
        live.setScaleX(0f);
        live.setScaleY(0f);
        live.animate().scaleX(1f).scaleY(1f).setStartDelay(450).setDuration(480)
                .setInterpolator(new android.view.animation.OvershootInterpolator(2f)).start();
        ObjectAnimator bob = ObjectAnimator.ofFloat(live, View.TRANSLATION_Y, 0, -6 * d, 0);
        bob.setStartDelay(1200);
        bob.setDuration(2600);
        bob.setRepeatCount(ValueAnimator.INFINITE);
        bob.start();
        ObjectAnimator pulse = ObjectAnimator.ofFloat(findViewById(R.id.liveDot), View.ALPHA, 1f, 0.25f, 1f);
        pulse.setDuration(1200);
        pulse.setRepeatCount(ValueAnimator.INFINITE);
        pulse.start();
        tick();
    }

    /** "Now serving Q-005 -> Q-006", then starts again (the sample ticket is Q-007). */
    private void tick() {
        handler.postDelayed(() -> {
            if (isFinishing()) return;
            serving = serving >= 6 ? 4 : serving + 1;
            liveNumber.setText(code(serving));
            Ui.pop(liveNumber);
            tick();
        }, 1600);
    }

    @Override
    protected void onDestroy() {
        handler.removeCallbacksAndMessages(null);
        super.onDestroy();
    }
}
