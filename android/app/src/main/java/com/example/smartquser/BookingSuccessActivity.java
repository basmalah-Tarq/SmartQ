package com.example.smartquser;

import android.Manifest;
import android.animation.ValueAnimator;
import android.app.AlarmManager;
import android.app.PendingIntent;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.os.Build;
import android.os.Bundle;
import android.view.View;
import android.widget.TextView;

import androidx.activity.result.ActivityResultLauncher;
import androidx.activity.result.contract.ActivityResultContracts;
import androidx.core.content.ContextCompat;

import com.example.smartquser.ui.CheckAnimView;
import com.example.smartquser.utils.DateUtils;
import com.example.smartquser.utils.ImageLoader;
import com.example.smartquser.utils.Lang;
import com.example.smartquser.utils.Ui;

import org.json.JSONException;
import org.json.JSONObject;

import java.util.Date;
import java.util.Locale;

/** Animated check + queue ticket after a successful booking. Also schedules a reminder. */
public class BookingSuccessActivity extends BaseActivity {

    public static final String EXTRA_DATA = "data";
    public static final String EXTRA_PLACE_IMAGE = "place_image";
    public static final String EXTRA_PLACE_NAME = "place_name";

    private ActivityResultLauncher<String> notifPermission;
    private String placeName, date, time, timeLabel, queueCode;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_booking_success);
        Ui.padTopAndBottom(findViewById(R.id.scroll));

        JSONObject d;
        try {
            d = new JSONObject(getIntent().getStringExtra(EXTRA_DATA));
        } catch (JSONException | NullPointerException e) {
            d = new JSONObject();
        }
        placeName = d.optString("place_name", getIntent().getStringExtra(EXTRA_PLACE_NAME));
        date = d.optString("schedule_date", "");
        time = d.optString("appointment_time", d.optString("start_time", ""));
        timeLabel = DateUtils.timeLabel(time);
        if (timeLabel.isEmpty()) timeLabel = d.optString("appointment_time_label", "");
        queueCode = d.optString("queue_code", "");
        int queueNumber = d.optInt("queue_number", 0);

        ((TextView) findViewById(R.id.tvPlace)).setText(placeName);
        ((TextView) findViewById(R.id.tvDate)).setText(DateUtils.friendlyDate(date) + "\n" + DateUtils.mediumDate(date));
        ((TextView) findViewById(R.id.tvTime)).setText(timeLabel);
        Ui.statusPill(findViewById(R.id.tvStatus), d.optString("status", "waiting"));
        ImageLoader.load(findViewById(R.id.ivPlace), getIntent().getStringExtra(EXTRA_PLACE_IMAGE), R.drawable.place_placeholder);

        findViewById(R.id.btnBookings).setOnClickListener(v -> {
            Intent home = new Intent(this, DashboardActivity.class);
            home.addFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP | Intent.FLAG_ACTIVITY_SINGLE_TOP);
            startActivities(new Intent[]{home, new Intent(this, AppointmentsActivity.class)});
            finish();
        });
        findViewById(R.id.btnHome).setOnClickListener(v -> goHome());

        // motion: check draws, then title, then the ticket slides up and its number ticks
        ((CheckAnimView) findViewById(R.id.check)).play();
        View ticket = findViewById(R.id.ticket);
        Ui.enter(findViewById(R.id.title), 5);
        Ui.enter(findViewById(R.id.subtitle), 6);
        if (Ui.motionEnabled(this)) {
            ticket.setAlpha(0f);
            ticket.setTranslationY(Ui.dp(this, 60));
            ticket.setRotation(-3f);
            ticket.animate().alpha(1f).translationY(0).rotation(0).setStartDelay(650).setDuration(700)
                    .setInterpolator(Ui.EASE_OUT).start();
        }
        tickQueue(findViewById(R.id.tvQueueCode), queueNumber, queueCode);
        Ui.enter(findViewById(R.id.btnBookings), 12);
        Ui.enter(findViewById(R.id.btnHome), 13);

        getOnBackPressedDispatcher().addCallback(this, new androidx.activity.OnBackPressedCallback(true) {
            @Override
            public void handleOnBackPressed() {
                goHome();
            }
        });

        notifPermission = registerForActivityResult(new ActivityResultContracts.RequestPermission(),
                granted -> scheduleReminder());
        if (Build.VERSION.SDK_INT >= 33 && ContextCompat.checkSelfPermission(this,
                Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED) {
            notifPermission.launch(Manifest.permission.POST_NOTIFICATIONS);
        } else {
            scheduleReminder();
        }
    }

    private void tickQueue(TextView tv, int target, String finalText) {
        if (finalText.isEmpty()) finalText = String.format(Locale.US, "Q-%03d", target);
        final String text = finalText;
        if (target <= 0 || !Ui.motionEnabled(this)) {
            tv.setText(text);
            return;
        }
        ValueAnimator va = ValueAnimator.ofInt(0, target);
        va.setStartDelay(900);
        va.setDuration(Math.min(1200, 300 + target * 60L));
        va.setInterpolator(Ui.EASE_OUT);
        va.addUpdateListener(a -> tv.setText(String.format(Locale.US, "Q-%03d", (int) a.getAnimatedValue())));
        va.addListener(new android.animation.AnimatorListenerAdapter() {
            @Override
            public void onAnimationEnd(android.animation.Animator animation) {
                tv.setText(text);
                Ui.pop(tv);
            }
        });
        va.start();
    }

    /** Local reminder one hour before the turn (inexact alarm: no special permission needed). */
    private void scheduleReminder() {
        TextView info = findViewById(R.id.tvReminder);
        Date when = DateUtils.parseDateTime(date, time);
        if (when == null) return;
        long at = when.getTime() - 60 * 60 * 1000L;
        if (at <= System.currentTimeMillis()) return;
        boolean canNotify = Build.VERSION.SDK_INT < 33 || ContextCompat.checkSelfPermission(this,
                Manifest.permission.POST_NOTIFICATIONS) == PackageManager.PERMISSION_GRANTED;
        if (!canNotify) return;
        try {
            Intent intent = new Intent(this, ReminderReceiver.class);
            intent.putExtra(ReminderReceiver.EXTRA_TITLE, getString(R.string.success_reminder_title));
            intent.putExtra(ReminderReceiver.EXTRA_MESSAGE,
                    getString(R.string.success_reminder_msg, Lang.ltr(queueCode), placeName, Lang.ltr(timeLabel)));
            PendingIntent pi = PendingIntent.getBroadcast(this, queueCode.hashCode() ^ date.hashCode(), intent,
                    PendingIntent.FLAG_UPDATE_CURRENT | PendingIntent.FLAG_IMMUTABLE);
            AlarmManager am = (AlarmManager) getSystemService(ALARM_SERVICE);
            if (am != null) {
                am.setAndAllowWhileIdle(AlarmManager.RTC_WAKEUP, at, pi);
                info.setText(R.string.success_reminder_info);
                Ui.enter(info, 0);
            }
        } catch (Exception ignored) {
            // reminders are a nice-to-have; never crash the success screen
        }
    }

    private void goHome() {
        Intent i = new Intent(this, DashboardActivity.class);
        i.addFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP | Intent.FLAG_ACTIVITY_SINGLE_TOP);
        startActivity(i);
        fadeTransition();
        finish();
    }

}
