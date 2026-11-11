package com.example.smartquser.ui;

import android.animation.Animator;
import android.animation.ObjectAnimator;
import android.animation.PropertyValuesHolder;
import android.animation.ValueAnimator;
import android.content.Context;
import android.graphics.drawable.GradientDrawable;
import android.view.Gravity;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.view.animation.OvershootInterpolator;
import android.widget.GridLayout;
import android.widget.TextView;

import androidx.core.content.ContextCompat;
import androidx.core.content.res.ResourcesCompat;

import com.example.smartquser.R;
import com.example.smartquser.utils.Ui;

import java.util.ArrayList;
import java.util.List;
import java.util.Locale;

/**
 * The three onboarding illustrations, built from the app's own pieces (TicketView, HourglassView,
 * pills, brand gradient) instead of heavy image assets. {@link #inflate} builds a page's art,
 * {@link #playEntrance} runs its one-shot entrance, {@link #startLoops} its gentle idle motion.
 * Every animation is skipped when the user turned animations off.
 */
public final class OnboardingArt {

    public static final int PAGES = 3;

    private static final int[] LAYOUTS = {
            R.layout.art_onboarding_find, R.layout.art_onboarding_time, R.layout.art_onboarding_turn};

    private static final String[] SLOTS = {"09:00", "09:30", "10:00", "10:30", "11:00", "11:30", "12:00", "12:30", "13:00"};
    /** Slots shown as already taken. */
    private static final boolean[] TAKEN = {true, false, true, false, false, true, false, false, true};
    /** Free slots the selection hops across (ends on 10:30, the "Yours" chip). */
    private static final int[] HOPS = {6, 1, 4, 3};

    private OnboardingArt() {}

    /** Shrinks the 300dp illustration when its frame is smaller (short screens), keeping it centred. */
    public static void fitInto(ViewGroup frame, View art) {
        frame.addOnLayoutChangeListener((view, l, t, r, b, ol, ot, or, ob) -> {
            float need = Ui.dp(view.getContext(), 300);
            float h = (b - t) - view.getPaddingTop() - view.getPaddingBottom();
            float w = (r - l) - view.getPaddingLeft() - view.getPaddingRight() - Ui.dp(view.getContext(), 16);
            float fit = Math.max(0.5f, Math.min(1.15f, Math.min(h / need, w / need)));
            art.setScaleX(fit);
            art.setScaleY(fit);
        });
    }

    public static View inflate(ViewGroup parent, int page) {
        View art = LayoutInflater.from(parent.getContext()).inflate(LAYOUTS[page], parent, false);
        if (page == 1) buildSlots(art, 3);
        if (page == 2) showServing(art, 5); // still frame (also used when animations are off)
        return art;
    }

    // ------------------------------------------------------------ page 2 slot grid

    private static void buildSlots(View art, int selected) {
        GridLayout grid = art.findViewById(R.id.slotGrid);
        Context c = art.getContext();
        grid.removeAllViews();
        for (int i = 0; i < SLOTS.length; i++) {
            TextView tv = new TextView(c);
            tv.setText(SLOTS[i]);
            tv.setGravity(Gravity.CENTER);
            tv.setTextSize(13);
            tv.setTypeface(ResourcesCompat.getFont(c, R.font.inter_semibold));
            tv.setFontFeatureSettings("tnum");
            GridLayout.LayoutParams lp = new GridLayout.LayoutParams(
                    GridLayout.spec(GridLayout.UNDEFINED, 1f), GridLayout.spec(GridLayout.UNDEFINED, 1f));
            lp.width = 0;
            lp.height = Ui.dp(c, 38);
            int m = Ui.dp(c, 4);
            lp.setMargins(m, m, m, m);
            grid.addView(tv, lp);
            styleSlot(tv, i, i == selected);
        }
        TextView picked = art.findViewById(R.id.pickedText);
        picked.setText(c.getString(R.string.onb_art_yours, com.example.smartquser.utils.Lang.ltr(SLOTS[selected])));
    }

    private static void styleSlot(TextView tv, int index, boolean selected) {
        Context c = tv.getContext();
        GradientDrawable bg = new GradientDrawable();
        bg.setCornerRadius(Ui.dp(c, 12));
        if (selected) {
            bg.setColor(ContextCompat.getColor(c, R.color.blue_600));
            tv.setTextColor(ContextCompat.getColor(c, R.color.white));
            tv.setPaintFlags(tv.getPaintFlags() & ~android.graphics.Paint.STRIKE_THRU_TEXT_FLAG);
        } else if (TAKEN[index]) {
            bg.setColor(ContextCompat.getColor(c, R.color.surface_alt));
            tv.setTextColor(ContextCompat.getColor(c, R.color.muted));
            tv.setAlpha(0.6f);
            tv.setPaintFlags(tv.getPaintFlags() | android.graphics.Paint.STRIKE_THRU_TEXT_FLAG);
        } else {
            bg.setColor(ContextCompat.getColor(c, R.color.surface));
            bg.setStroke(Ui.dp(c, 1.5f), ContextCompat.getColor(c, R.color.border));
            tv.setTextColor(ContextCompat.getColor(c, R.color.ink_soft));
        }
        tv.setBackground(bg);
    }

    private static void select(View art, int index) {
        GridLayout grid = art.findViewById(R.id.slotGrid);
        for (int i = 0; i < grid.getChildCount(); i++) styleSlot((TextView) grid.getChildAt(i), i, i == index);
        Ui.pop(grid.getChildAt(index));
        TextView picked = art.findViewById(R.id.pickedText);
        picked.setText(art.getContext().getString(R.string.onb_art_yours, com.example.smartquser.utils.Lang.ltr(SLOTS[index])));
    }

    // ------------------------------------------------------------ motion

    /** One-shot entrance when the page becomes visible. */
    public static void playEntrance(View art, int page) {
        if (!Ui.motionEnabled(art.getContext())) return;
        float d = art.getResources().getDisplayMetrics().density;
        if (page == 0) {
            View pin = art.findViewById(R.id.pin);
            pin.setTranslationY(-60 * d);
            pin.setAlpha(0f);
            pin.animate().translationY(0).alpha(1f).setDuration(650)
                    .setInterpolator(new OvershootInterpolator(2f)).start();
            popIn(art.findViewById(R.id.bubble1), 250);
            popIn(art.findViewById(R.id.bubble2), 350);
            popIn(art.findViewById(R.id.bubble3), 450);
            slideIn(art.findViewById(R.id.searchCard), -16 * d, 100);
            slideIn(art.findViewById(R.id.nearbyChip), 16 * d, 550);
        } else if (page == 1) {
            View card = art.findViewById(R.id.dayCard);
            card.setAlpha(0f);
            card.setScaleX(0.92f);
            card.setScaleY(0.92f);
            card.animate().alpha(1f).scaleX(1f).scaleY(1f).setDuration(520).setInterpolator(Ui.EASE_OUT).start();
            popIn(art.findViewById(R.id.hourglassBubble), 300);
            popIn(art.findViewById(R.id.pickedChip), 450);
        } else {
            View ticket = art.findViewById(R.id.artTicket);
            ticket.setAlpha(0f);
            ticket.setTranslationY(50 * d);
            ticket.setRotation(-10f);
            ticket.animate().alpha(1f).translationY(0).rotation(-4f).setDuration(700)
                    .setInterpolator(Ui.EASE_OUT).start();
            popIn(art.findViewById(R.id.servingChip), 350);
            popIn(art.findViewById(R.id.nextChip), 500);
        }
    }

    private static void popIn(View v, long delay) {
        v.setScaleX(0f);
        v.setScaleY(0f);
        v.animate().scaleX(1f).scaleY(1f).setStartDelay(delay).setDuration(480)
                .setInterpolator(new OvershootInterpolator(2.2f)).start();
    }

    private static void slideIn(View v, float fromY, long delay) {
        v.setAlpha(0f);
        v.setTranslationY(fromY);
        v.animate().alpha(1f).translationY(0).setStartDelay(delay).setDuration(520)
                .setInterpolator(Ui.EASE_OUT).start();
    }

    /** Idle loops (bobbing chips, pulse ring, slot hopping, ticking queue). Cancel them on detach. */
    public static List<Animator> startLoops(View art, int page) {
        List<Animator> list = new ArrayList<>();
        if (!Ui.motionEnabled(art.getContext())) return list;
        float d = art.getResources().getDisplayMetrics().density;
        if (page == 0) {
            View ring = art.findViewById(R.id.ring);
            ObjectAnimator pulse = ObjectAnimator.ofPropertyValuesHolder(ring,
                    PropertyValuesHolder.ofFloat(View.SCALE_X, 1f, 1.9f),
                    PropertyValuesHolder.ofFloat(View.SCALE_Y, 1f, 1.9f),
                    PropertyValuesHolder.ofFloat(View.ALPHA, 0.9f, 0f));
            pulse.setDuration(1800);
            pulse.setRepeatCount(ValueAnimator.INFINITE);
            list.add(pulse);
            list.add(bob(art.findViewById(R.id.bubble1), 6 * d, 2600, 0));
            list.add(bob(art.findViewById(R.id.bubble2), 7 * d, 3000, 400));
            list.add(bob(art.findViewById(R.id.bubble3), 5 * d, 2400, 800));
            list.add(blink(art.findViewById(R.id.liveDot1)));
        } else if (page == 1) {
            list.add(bob(art.findViewById(R.id.hourglassBubble), 6 * d, 2800, 0));
            list.add(bob(art.findViewById(R.id.pickedChip), 5 * d, 2600, 500));
            ValueAnimator hop = ValueAnimator.ofInt(0, HOPS.length);
            hop.setDuration(1100L * HOPS.length);
            hop.setStartDelay(900);
            hop.setRepeatCount(ValueAnimator.INFINITE);
            final int[] last = {-1};
            hop.addUpdateListener(a -> {
                int step = Math.min(HOPS.length - 1, (int) a.getAnimatedValue());
                if (step != last[0]) {
                    last[0] = step;
                    select(art, HOPS[step]);
                }
            });
            list.add(hop);
        } else {
            list.add(bob(art.findViewById(R.id.servingChip), 6 * d, 2600, 0));
            list.add(bob(art.findViewById(R.id.nextChip), 5 * d, 3000, 600));
            list.add(blink(art.findViewById(R.id.liveDot3)));
            TextView serving = art.findViewById(R.id.servingNumber);
            ValueAnimator tick = ValueAnimator.ofInt(3, 8);
            tick.setDuration(5200);
            tick.setStartDelay(800);
            tick.setRepeatCount(ValueAnimator.INFINITE);
            final int[] last = {-1};
            tick.addUpdateListener(a -> {
                int n = Math.min(7, (int) a.getAnimatedValue());
                if (n == last[0]) return;
                last[0] = n;
                showServing(art, n);
                Ui.pop(serving);
            });
            list.add(tick);
        }
        for (Animator a : list) a.start();
        return list;
    }

    private static Animator bob(View v, float by, long duration, long delay) {
        ObjectAnimator a = ObjectAnimator.ofFloat(v, View.TRANSLATION_Y, 0, -by, 0);
        a.setDuration(duration);
        a.setStartDelay(delay);
        a.setRepeatCount(ValueAnimator.INFINITE);
        return a;
    }

    private static Animator blink(View v) {
        ObjectAnimator a = ObjectAnimator.ofFloat(v, View.ALPHA, 1f, 0.25f, 1f);
        a.setDuration(1200);
        a.setRepeatCount(ValueAnimator.INFINITE);
        return a;
    }

    /** "Now serving Q-00n" chip, the ticket ETA and the hint, for the ticket Q-007. */
    private static void showServing(View art, int n) {
        TextView serving = art.findViewById(R.id.servingNumber);
        TextView eta = art.findViewById(R.id.artEta);
        TextView next = art.findViewById(R.id.nextText);
        serving.setText(String.format(Locale.US, "Q-%03d", n));
        int left = 7 - n;
        eta.setText(left == 0 ? art.getContext().getString(R.string.countdown_now)
                : art.getResources().getQuantityString(R.plurals.countdown_minutes, left * 4,
                com.example.smartquser.utils.Lang.num(left * 4L)));
        next.setText(left == 0 ? R.string.onb_art_turn_now : left == 1 ? R.string.onb_art_next : R.string.onb_art_no_wait);
    }

    public static void stop(List<Animator> list) {
        if (list == null) return;
        for (Animator a : list) a.cancel();
        list.clear();
    }

}
