package com.example.smartquser;

import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.os.Build;

import androidx.core.app.NotificationCompat;
import androidx.core.content.ContextCompat;

/** Shows the "your turn is soon" notification scheduled by BookingSuccessActivity. */
public class ReminderReceiver extends BroadcastReceiver {

    public static final String EXTRA_TITLE = "title";
    public static final String EXTRA_MESSAGE = "message";
    private static final String CHANNEL_ID = "smartq_reminders";

    @Override
    public void onReceive(Context context, Intent intent) {
        NotificationManager manager = (NotificationManager) context.getSystemService(Context.NOTIFICATION_SERVICE);
        if (manager == null) return;

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            NotificationChannel channel = new NotificationChannel(CHANNEL_ID, context.getString(R.string.reminder_channel),
                    NotificationManager.IMPORTANCE_HIGH);
            channel.setDescription(context.getString(R.string.reminder_channel_desc));
            manager.createNotificationChannel(channel);
        }

        Intent open = new Intent(context, MainActivity.class);
        open.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_CLEAR_TASK);
        PendingIntent content = PendingIntent.getActivity(context, 0, open,
                PendingIntent.FLAG_UPDATE_CURRENT | PendingIntent.FLAG_IMMUTABLE);

        String title = intent.getStringExtra(EXTRA_TITLE);
        String message = intent.getStringExtra(EXTRA_MESSAGE);
        NotificationCompat.Builder b = new NotificationCompat.Builder(context, CHANNEL_ID)
                .setSmallIcon(R.drawable.ic_hourglass)
                .setColor(ContextCompat.getColor(context, R.color.blue_600))
                .setContentTitle(title != null ? title : context.getString(R.string.reminder_default_title))
                .setContentText(message != null ? message : context.getString(R.string.reminder_default_text))
                .setStyle(new NotificationCompat.BigTextStyle().bigText(message))
                .setPriority(NotificationCompat.PRIORITY_HIGH)
                .setContentIntent(content)
                .setAutoCancel(true);
        try {
            manager.notify((int) (System.currentTimeMillis() & 0xFFFFFFF), b.build());
        } catch (SecurityException ignored) {
            // notification permission was revoked
        }
    }
}
