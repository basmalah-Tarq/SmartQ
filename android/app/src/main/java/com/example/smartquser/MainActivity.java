package com.example.smartquser;

import android.animation.ObjectAnimator;
import android.animation.PropertyValuesHolder;
import android.animation.ValueAnimator;
import android.content.Intent;
import android.net.Uri;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.os.SystemClock;
import android.view.View;
import android.view.animation.OvershootInterpolator;
import android.widget.TextView;

import com.example.smartquser.auth.AuthGate;
import com.example.smartquser.net.ApiClient;
import com.example.smartquser.net.ApiError;
import com.example.smartquser.net.ApiResponse;
import com.example.smartquser.utils.Ui;

import java.util.Locale;

/**
 * Animated splash (brand gradient, logo mark, queue-ticket reveal) + server health check, which also
 * passes the hosting anti-bot check up-front. First launch continues to the language picker.
 */
public class MainActivity extends BaseActivity {

    private static final long MIN_SPLASH_MS = 1500;
    private static final long SHOW_PROGRESS_AFTER_MS = 1400;
    private static final long AUTH_OPTIONS_TIMEOUT_MS = 2500;

    private final Handler handler = new Handler(Looper.getMainLooper());
    private View errorCard, loadingBlock, brandBlock, ticketPill;
    private TextView status;
    private long startedAt;
    private boolean leaving;
    private ValueAnimator ringPulse;

    @Override
    protected boolean darkStatusBar() {
        return true;
    }

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);
        Ui.padTopAndBottom(findViewById(R.id.root));

        errorCard = findViewById(R.id.errorCard);
        loadingBlock = findViewById(R.id.loadingBlock);
        brandBlock = findViewById(R.id.brandBlock);
        ticketPill = findViewById(R.id.ticketPill);
        status = findViewById(R.id.status);
        String host = Uri.parse(ApiClient.BASE_URL).getHost();
        ((TextView) findViewById(R.id.serverHost)).setText(getString(R.string.splash_server, host == null ? "" : host));
        findViewById(R.id.btnRetry).setOnClickListener(v -> {
            Ui.crossfade(ticketPill, errorCard);
            startedAt = SystemClock.elapsedRealtime();
            scheduleProgress();
            checkServer();
        });

        startedAt = SystemClock.elapsedRealtime();
        playIntro();
        scheduleProgress();
        checkServer();
    }

    // ------------------------------------------------------------ motion

    private void playIntro() {
        if (!Ui.motionEnabled(this)) return;
        View ring = findViewById(R.id.ring);
        View plate = findViewById(R.id.logoPlate);
        View logo = findViewById(R.id.logo);
        View name = findViewById(R.id.appName);
        View tagline = findViewById(R.id.tagline);

        ring.setAlpha(0f);
        ring.setScaleX(0.55f);
        ring.setScaleY(0.55f);
        ring.animate().alpha(1f).scaleX(1f).scaleY(1f).setDuration(750).setInterpolator(Ui.EASE_OUT)
                .withEndAction(this::pulseRing).start();

        plate.setAlpha(0f);
        plate.setScaleX(0.4f);
        plate.setScaleY(0.4f);
        plate.animate().alpha(1f).scaleX(1f).scaleY(1f).setDuration(650)
                .setInterpolator(new OvershootInterpolator(1.6f)).start();
        logo.setTranslationY(Ui.dp(this, 14));
        logo.setAlpha(0f);
        logo.animate().translationY(0).alpha(1f).setStartDelay(180).setDuration(520)
                .setInterpolator(Ui.EASE_OUT).start();

        Ui.enter(name, 3);
        Ui.enter(tagline, 5);

        // ticket reveal: the pill unrolls from the centre, then the number counts up to Q-007
        ticketPill.setAlpha(0f);
        ticketPill.setScaleX(0.3f);
        ticketPill.animate().alpha(1f).scaleX(1f).setStartDelay(560).setDuration(480)
                .setInterpolator(Ui.EASE_OUT).start();
        TextView number = findViewById(R.id.ticketNumber);
        ValueAnimator count = ValueAnimator.ofInt(1, 7);
        count.setStartDelay(700);
        count.setDuration(620);
        count.addUpdateListener(a -> number.setText(String.format(Locale.US, "Q-%03d", (int) a.getAnimatedValue())));
        count.addListener(new android.animation.AnimatorListenerAdapter() {
            @Override
            public void onAnimationEnd(android.animation.Animator animation) {
                Ui.pop(number);
            }
        });
        count.start();
    }

    /** Gentle "breathing" ring while we wait for the server. */
    private void pulseRing() {
        if (isFinishing() || leaving) return;
        View ring = findViewById(R.id.ring);
        ObjectAnimator a = ObjectAnimator.ofPropertyValuesHolder(ring,
                PropertyValuesHolder.ofFloat(View.SCALE_X, 1f, 1.07f, 1f),
                PropertyValuesHolder.ofFloat(View.SCALE_Y, 1f, 1.07f, 1f));
        a.setDuration(1800);
        a.setRepeatCount(ValueAnimator.INFINITE);
        a.start();
        ringPulse = a;
    }

    /** Only a slow server gets the progress line + status text. */
    private void scheduleProgress() {
        handler.postDelayed(() -> {
            if (isFinishing() || leaving || errorCard.getVisibility() == View.VISIBLE) return;
            loadingBlock.setAlpha(0f);
            loadingBlock.setVisibility(View.VISIBLE);
            loadingBlock.animate().alpha(1f).setDuration(300).start();
            handler.postDelayed(() -> {
                if (!isFinishing() && !leaving) status.setText(R.string.splash_slow);
            }, 4000);
        }, SHOW_PROGRESS_AFTER_MS);
    }

    // ------------------------------------------------------------ server

    private void checkServer() {
        status.setText(R.string.splash_connecting);
        ApiClient.health(this, new ApiClient.Callback() {
            @Override
            public void onSuccess(ApiResponse response) {
                loadAuthOptions();
            }

            @Override
            public void onError(ApiError error) {
                long wait = Math.max(0, 900 - (SystemClock.elapsedRealtime() - startedAt));
                handler.postDelayed(() -> showError(error), wait);
            }
        });
    }

    /** Caches which sign-in methods are on (Welcome / sign-in sheet read it). Never blocks for long. */
    private void loadAuthOptions() {
        final boolean[] done = {false};
        Runnable next = () -> {
            if (done[0]) return;
            done[0] = true;
            long wait = Math.max(0, MIN_SPLASH_MS - (SystemClock.elapsedRealtime() - startedAt));
            handler.postDelayed(this::exitAndProceed, wait);
        };
        handler.postDelayed(next, AUTH_OPTIONS_TIMEOUT_MS); // slow server: go on with the cached options
        ApiClient.get(this, "auth_options.php", null, new ApiClient.Callback() {
            @Override
            public void onSuccess(ApiResponse r) {
                prefs.saveAuthOptions(r.data);
                next.run();
            }

            @Override
            public void onError(ApiError e) {
                next.run();
            }
        });
    }

    private void showError(ApiError error) {
        handler.removeCallbacksAndMessages(null);
        ((TextView) findViewById(R.id.errorTitle)).setText(error.kind == ApiError.Kind.NO_INTERNET
                ? R.string.state_offline_title : R.string.state_unreachable_title);
        ((TextView) findViewById(R.id.errorMessage)).setText(error.message);
        loadingBlock.setVisibility(View.INVISIBLE);
        Ui.crossfade(errorCard, ticketPill);
        Ui.enter(errorCard, 0);
    }

    // ------------------------------------------------------------ leave

    private void exitAndProceed() {
        if (isFinishing() || leaving) return;
        leaving = true;
        if (ringPulse != null) ringPulse.cancel();
        if (!Ui.motionEnabled(this)) {
            proceed();
            return;
        }
        loadingBlock.animate().alpha(0f).setDuration(180).start();
        brandBlock.animate().alpha(0f).scaleX(1.08f).scaleY(1.08f).setDuration(300)
                .setInterpolator(Ui.EASE_OUT).withEndAction(this::proceed).start();
    }

    private void proceed() {
        if (isFinishing()) return;
        boolean loggedIn = session.isLoggedIn();
        if (loggedIn) {
            // existing users skip the first-launch steps
            if (!prefs.isOnboardingDone()) prefs.setOnboardingDone(true);
            if (!prefs.isLanguageChosen()) prefs.setLanguageChosen(true);
        }
        Class<?> target;
        if (!loggedIn && !prefs.isLanguageChosen()) {
            target = LanguageActivity.class;
        } else {
            switch (AuthGate.startRoute(prefs.isOnboardingDone(), loggedIn, prefs.isGuest())) {
                case ONBOARDING:
                    target = OnboardingActivity.class;
                    break;
                case WELCOME:
                    target = WelcomeActivity.class;
                    break;
                default:
                    target = DashboardActivity.class;
            }
        }
        startActivity(new Intent(this, target));
        fadeTransition();
        finish();
    }

    @Override
    protected void onDestroy() {
        handler.removeCallbacksAndMessages(null);
        if (ringPulse != null) ringPulse.cancel();
        super.onDestroy();
    }
}
