package com.example.smartquser.ui;

import android.animation.ValueAnimator;
import android.content.Context;
import android.graphics.Canvas;
import android.graphics.Paint;
import android.graphics.RectF;
import android.graphics.Typeface;
import android.util.AttributeSet;
import android.view.View;
import android.view.animation.LinearInterpolator;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;
import androidx.core.content.ContextCompat;
import androidx.core.content.res.ResourcesCompat;

import com.example.smartquser.R;
import com.example.smartquser.utils.Lang;
import com.example.smartquser.utils.Ui;

import java.util.Locale;

/**
 * Flat, geometric "people waiting in a queue" scene: a service counter with a small "now serving"
 * display and a short line of people. Idle motion: the line advances one place and the counter
 * number ticks up. Drawn in a 320 x 150 virtual box, scaled to fit (never clipped), mirrored for
 * right-to-left layouts (the counter is always on the start side), colors follow light / dark.
 */
public class QueueSceneView extends View {

    private static final float VW = 320f, VH = 150f;
    private static final long CYCLE_MS = 2800;
    private static final int PEOPLE = 4;
    private static final float SPACING = 46f;
    private static final float FIRST_X = 118f;   // centre of the person at the counter
    private static final float GROUND = 132f;

    private final Paint fill = new Paint(Paint.ANTI_ALIAS_FLAG);
    private final Paint text = new Paint(Paint.ANTI_ALIAS_FLAG);
    private final RectF r = new RectF();
    private final int cGround, cDesk, cDeskTop, cPanel, cPanelText, cTeal, cGold, cSoft;
    private final int[] people;
    private ValueAnimator animator;
    private float phase;
    private int served = 41;
    private int cycleCount;

    public QueueSceneView(Context context) {
        this(context, null);
    }

    public QueueSceneView(Context context, @Nullable AttributeSet attrs) {
        super(context, attrs);
        boolean dark = Lang.isDark(context);
        cGround = color(R.color.border);
        cDesk = color(R.color.surface_alt);
        cDeskTop = dark ? color(R.color.blue_600) : color(R.color.navy_700);
        cPanel = dark ? color(R.color.navy_700) : color(R.color.navy_900);
        cPanelText = color(R.color.white);
        cTeal = color(R.color.teal_500);
        cGold = color(R.color.gold_400);
        cSoft = color(R.color.skeleton_strong);
        people = dark
                ? new int[]{color(R.color.blue_500), color(R.color.sky_300), color(R.color.teal_500), color(R.color.sky_300)}
                : new int[]{color(R.color.blue_600), color(R.color.navy_700), color(R.color.teal_500), color(R.color.navy_700)};
        text.setTextAlign(Paint.Align.CENTER);
        Typeface tf = ResourcesCompat.getFont(context, R.font.jakarta_bold);
        if (tf != null) text.setTypeface(tf);
        setImportantForAccessibility(IMPORTANT_FOR_ACCESSIBILITY_NO);
    }

    private int color(int res) {
        return ContextCompat.getColor(getContext(), res);
    }

    @Override
    protected void onMeasure(int widthMeasureSpec, int heightMeasureSpec) {
        int w = MeasureSpec.getSize(widthMeasureSpec);
        int h = MeasureSpec.getSize(heightMeasureSpec);
        if (MeasureSpec.getMode(heightMeasureSpec) != MeasureSpec.EXACTLY) {
            int want = Math.round(w * VH / VW);
            h = MeasureSpec.getMode(heightMeasureSpec) == MeasureSpec.AT_MOST ? Math.min(want, h) : want;
        }
        setMeasuredDimension(w, h);
    }

    @Override
    protected void onDraw(@NonNull Canvas canvas) {
        int pw = getWidth() - getPaddingLeft() - getPaddingRight();
        int ph = getHeight() - getPaddingTop() - getPaddingBottom();
        if (pw <= 0 || ph <= 0) return;
        float s = Math.min(pw / VW, ph / VH);
        float ox = getPaddingLeft() + (pw - VW * s) / 2f;
        float oy = getPaddingTop() + (ph - VH * s) / 2f;
        boolean rtl = getLayoutDirection() == LAYOUT_DIRECTION_RTL;

        canvas.save();
        canvas.translate(ox, oy);
        canvas.scale(s, s);
        if (rtl) {
            canvas.translate(VW, 0);
            canvas.scale(-1f, 1f);
        }

        // ground
        fill.setColor(cGround);
        r.set(12, GROUND, VW - 12, GROUND + 3);
        canvas.drawRoundRect(r, 1.5f, 1.5f, fill);

        // counter desk
        fill.setColor(cDesk);
        r.set(16, 86, 86, GROUND);
        canvas.drawRoundRect(r, 6, 6, fill);
        fill.setColor(cDeskTop);
        r.set(10, 80, 92, 89);
        canvas.drawRoundRect(r, 4.5f, 4.5f, fill);
        // display pole + panel
        fill.setColor(cSoft);
        r.set(48, 50, 52, 80);
        canvas.drawRect(r, fill);
        fill.setColor(cPanel);
        r.set(18, 18, 82, 54);
        canvas.drawRoundRect(r, 8, 8, fill);
        // live light (teal) + gold highlight
        fill.setColor(cTeal);
        canvas.drawCircle(28, 27, 3.2f, fill);
        fill.setColor(cGold);
        canvas.drawCircle(73, 27, 2.2f, fill);

        // the line of people (moves one place towards the counter each cycle)
        float t = ease(Math.min(1f, phase / 0.55f));
        for (int i = 0; i <= PEOPLE; i++) {
            float x = FIRST_X + i * SPACING - t * SPACING;
            float alpha = 1f;
            if (i == 0) alpha = 1f - t;                 // the served person steps away
            if (i == PEOPLE) alpha = t;                 // a new person joins the end
            if (alpha <= 0.01f) continue;
            int c = people[(i + cycle()) % people.length];
            person(canvas, x - (i == 0 ? t * 14f : 0f), c, alpha, i == 0);
        }
        canvas.restore();

        // number on the display (drawn unmirrored)
        float cx = 50f, cy = 41f;
        if (rtl) cx = VW - cx;
        text.setColor(cPanelText);
        text.setTextSize(15f * s);
        canvas.drawText(String.format(Locale.US, "Q-%03d", served), ox + cx * s, oy + (cy + 5) * s, text);
    }

    private int cycle() {
        return cycleCount;
    }

    /** Head + rounded body; the person at the counter holds a small ticket. */
    private void person(Canvas c, float x, int color, float alpha, boolean front) {
        fill.setColor(color);
        fill.setAlpha(Math.round(255 * alpha));
        c.drawCircle(x, 62, 9.5f, fill);
        r.set(x - 13, 75, x + 13, GROUND);
        c.drawRoundRect(r, 12, 12, fill);
        if (front) {
            fill.setColor(cGold);
            fill.setAlpha(Math.round(255 * alpha));
            r.set(x - 20, 92, x - 10, 99);
            c.drawRoundRect(r, 2, 2, fill);
        }
        fill.setAlpha(255);
    }

    private static float ease(float x) {
        return (float) (0.5 - Math.cos(Math.PI * x) / 2);
    }

    @Override
    protected void onAttachedToWindow() {
        super.onAttachedToWindow();
        if (!Ui.motionEnabled(getContext())) return;
        animator = ValueAnimator.ofFloat(0f, 1f);
        animator.setDuration(CYCLE_MS);
        animator.setRepeatCount(ValueAnimator.INFINITE);
        animator.setInterpolator(new LinearInterpolator());
        animator.addUpdateListener(a -> {
            phase = (float) a.getAnimatedValue();
            invalidate();
        });
        animator.addListener(new android.animation.AnimatorListenerAdapter() {
            @Override
            public void onAnimationRepeat(android.animation.Animator animation) {
                cycleCount++;
                served = served >= 99 ? 41 : served + 1;
            }
        });
        animator.start();
    }

    @Override
    protected void onDetachedFromWindow() {
        if (animator != null) animator.cancel();
        animator = null;
        super.onDetachedFromWindow();
    }
}
