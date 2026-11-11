package com.example.smartquser.utils;

import android.app.Activity;
import android.content.Context;
import android.content.res.ColorStateList;
import android.graphics.drawable.GradientDrawable;
import android.provider.Settings;
import android.view.View;
import android.view.ViewGroup;
import android.view.animation.PathInterpolator;
import android.widget.TextView;

import androidx.core.content.ContextCompat;
import androidx.core.graphics.Insets;
import androidx.core.view.ViewCompat;
import androidx.core.view.WindowInsetsCompat;

import com.example.smartquser.R;
import com.google.android.material.snackbar.Snackbar;

/** Small shared UI helpers: insets (edge-to-edge), motion, snackbars, status pills. */
public final class Ui {

    /** DESIGN_SYSTEM easing cubic-bezier(.22,1,.36,1). */
    public static final PathInterpolator EASE_OUT = new PathInterpolator(0.22f, 1f, 0.36f, 1f);

    private Ui() {}

    public static int dp(Context c, float dp) {
        return Math.round(dp * c.getResources().getDisplayMetrics().density);
    }

    // ------------------------------------------------------------ insets (Android 15+ is edge-to-edge)

    /** Adds the status-bar height to the view's top padding. */
    public static void padTop(View v) {
        applyInsets(v, true, false);
    }

    /** Adds the navigation-bar (or keyboard, whichever is taller) height to the bottom padding. */
    public static void padBottom(View v) {
        applyInsets(v, false, true);
    }

    public static void padTopAndBottom(View v) {
        applyInsets(v, true, true);
    }

    private static void applyInsets(View v, boolean top, boolean bottom) {
        final int pl = v.getPaddingLeft(), pt = v.getPaddingTop(), pr = v.getPaddingRight(), pb = v.getPaddingBottom();
        ViewCompat.setOnApplyWindowInsetsListener(v, (view, insets) -> {
            Insets bars = insets.getInsets(WindowInsetsCompat.Type.systemBars() | WindowInsetsCompat.Type.displayCutout());
            Insets ime = insets.getInsets(WindowInsetsCompat.Type.ime());
            int b = Math.max(bars.bottom, ime.bottom);
            view.setPadding(pl + bars.left, top ? pt + bars.top : pt, pr + bars.right, bottom ? pb + b : pb);
            return insets;
        });
        ViewCompat.requestApplyInsets(v);
    }

    /** Adds the status bar height to a view's top margin (for floating buttons over images). */
    public static void marginTop(View v) {
        ViewGroup.MarginLayoutParams lp = (ViewGroup.MarginLayoutParams) v.getLayoutParams();
        final int base = lp.topMargin;
        ViewCompat.setOnApplyWindowInsetsListener(v, (view, insets) -> {
            Insets bars = insets.getInsets(WindowInsetsCompat.Type.systemBars());
            ViewGroup.MarginLayoutParams p = (ViewGroup.MarginLayoutParams) view.getLayoutParams();
            p.topMargin = base + bars.top;
            view.setLayoutParams(p);
            return insets;
        });
    }

    // ------------------------------------------------------------ motion

    /** False when the user disabled animations (Developer options / accessibility). */
    public static boolean motionEnabled(Context c) {
        try {
            return Settings.Global.getFloat(c.getContentResolver(), Settings.Global.ANIMATOR_DURATION_SCALE, 1f) > 0f;
        } catch (Exception e) {
            return true;
        }
    }

    /** Fade + slide-up entrance, staggered by index (DESIGN_SYSTEM: 16dp, 60-80ms). */
    public static void enter(View v, int index) {
        if (v == null) return;
        if (!motionEnabled(v.getContext())) {
            v.setAlpha(1f);
            v.setTranslationY(0);
            return;
        }
        v.setAlpha(0f);
        v.setTranslationY(dp(v.getContext(), 18));
        v.animate().alpha(1f).translationY(0).setStartDelay(70L * index)
                .setDuration(520).setInterpolator(EASE_OUT).start();
    }

    public static void enterAll(View... views) {
        int i = 0;
        for (View v : views) if (v != null && v.getVisibility() == View.VISIBLE) enter(v, i++);
    }

    /** Small "pop" used when a value changes. */
    public static void pop(View v) {
        if (!motionEnabled(v.getContext())) return;
        v.animate().cancel();
        v.setScaleX(0.9f);
        v.setScaleY(0.9f);
        v.animate().scaleX(1f).scaleY(1f).setDuration(380)
                .setInterpolator(new android.view.animation.OvershootInterpolator(2.2f)).start();
    }

    public static void shake(View v) {
        if (!motionEnabled(v.getContext())) return;
        float d = dp(v.getContext(), 8);
        android.animation.ObjectAnimator.ofFloat(v, View.TRANSLATION_X, 0, d, -d, d * .6f, -d * .6f, d * .3f, 0)
                .setDuration(420).start();
    }

    public static void crossfade(View show, View... hide) {
        for (View h : hide) {
            if (h != null && h.getVisibility() == View.VISIBLE) {
                h.animate().alpha(0f).setDuration(150).withEndAction(() -> {
                    h.setVisibility(View.GONE);
                    h.setAlpha(1f);
                }).start();
            }
        }
        if (show != null && show.getVisibility() != View.VISIBLE) {
            show.setAlpha(0f);
            show.setVisibility(View.VISIBLE);
            show.animate().alpha(1f).setDuration(250).start();
        }
    }

    // ------------------------------------------------------------ messages

    public static void snack(Activity a, String message) {
        View root = a.findViewById(android.R.id.content);
        if (root == null || message == null || message.isEmpty()) return;
        Snackbar sb = Snackbar.make(root, message, Snackbar.LENGTH_LONG);
        View anchor = a.findViewById(R.id.bottomNav);
        if (anchor == null) anchor = a.findViewById(R.id.bottomBar);
        if (anchor != null && anchor.getVisibility() == View.VISIBLE) sb.setAnchorView(anchor);
        sb.setTextColor(ContextCompat.getColor(a, R.color.white));
        sb.setActionTextColor(ContextCompat.getColor(a, R.color.gold_400));
        sb.show();
    }

    // ------------------------------------------------------------ status pills

    /** Styles a TextView as a status pill (DESIGN_SYSTEM status colors). */
    public static void statusPill(TextView tv, String status) {
        Context c = tv.getContext();
        String s = status == null ? "" : status.trim().toLowerCase(java.util.Locale.ROOT);
        int fg, bg;
        String label;
        switch (s) {
            case "waiting":
                fg = R.color.warning; bg = R.color.warning_tint; label = c.getString(R.string.status_waiting); break;
            case "confirmed":
                fg = R.color.accent; bg = R.color.blue_tint; label = c.getString(R.string.status_confirmed); break;
            case "done":
                fg = R.color.success; bg = R.color.success_tint; label = c.getString(R.string.status_done); break;
            case "cancelled":
                fg = R.color.danger; bg = R.color.danger_tint; label = c.getString(R.string.status_cancelled); break;
            case "missed":
                fg = R.color.muted; bg = R.color.muted_tint; label = c.getString(R.string.status_missed); break;
            default:
                fg = R.color.muted; bg = R.color.muted_tint;
                label = s.isEmpty() ? "" : Character.toUpperCase(s.charAt(0)) + s.substring(1);
        }
        tv.setText(label);
        tv.setTextColor(ContextCompat.getColor(c, fg));
        GradientDrawable d = new GradientDrawable();
        d.setCornerRadius(dp(c, 999));
        d.setColor(ContextCompat.getColor(c, bg));
        tv.setBackground(d);
    }

    /** Icon for a place category (used on chips and placeholders). */
    public static int categoryIcon(String category) {
        String c = category == null ? "" : category.toLowerCase(java.util.Locale.ROOT);
        if (c.contains("dent") || c.contains("أسنان")) return R.drawable.ic_dental;
        if (c.contains("pharm") || c.contains("صيدل")) return R.drawable.ic_pharmacy;
        if (c.contains("lab") || c.contains("مختبر")) return R.drawable.ic_lab;
        if (c.contains("hospital") || c.contains("مستشف")) return R.drawable.ic_hospital;
        if (c.contains("clinic") || c.contains("health") || c.contains("medic") || c.contains("doctor")
                || c.contains("عياد")) return R.drawable.ic_clinic;
        if (c.contains("bank") || c.contains("finan") || c.contains("بنك")) return R.drawable.ic_bank;
        if (c.contains("gov") || c.contains("municip") || c.contains("office") || c.contains("حكوم")
                || c.contains("بلدي")) return R.drawable.ic_government;
        if (c.contains("barber") || c.contains("hair") || c.contains("حلاق")) return R.drawable.ic_barber;
        if (c.contains("beauty") || c.contains("salon") || c.contains("spa") || c.contains("تجميل")) return R.drawable.ic_spa;
        if (c.contains("gym") || c.contains("fitness") || c.contains("sport") || c.contains("رياض")) return R.drawable.ic_gym;
        if (c.contains("shop") || c.contains("store") || c.contains("متجر")) return R.drawable.ic_store;
        return R.drawable.ic_category;
    }

    public static void tint(android.widget.ImageView iv, int colorRes) {
        iv.setImageTintList(ColorStateList.valueOf(ContextCompat.getColor(iv.getContext(), colorRes)));
    }
}
