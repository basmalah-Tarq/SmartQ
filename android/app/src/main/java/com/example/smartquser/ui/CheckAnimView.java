package com.example.smartquser.ui;

import android.animation.ValueAnimator;
import android.content.Context;
import android.graphics.Canvas;
import android.graphics.Paint;
import android.graphics.Path;
import android.graphics.PathMeasure;
import android.graphics.RectF;
import android.util.AttributeSet;
import android.view.View;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;

import com.example.smartquser.utils.Ui;

/** Success animation: ring sweeps, circle fills, check mark draws itself, confetti dots burst. */
public class CheckAnimView extends View {

    private final Paint ring = new Paint(Paint.ANTI_ALIAS_FLAG);
    private final Paint disc = new Paint(Paint.ANTI_ALIAS_FLAG);
    private final Paint check = new Paint(Paint.ANTI_ALIAS_FLAG);
    private final Paint dot = new Paint(Paint.ANTI_ALIAS_FLAG);
    private final Path checkPath = new Path();
    private final Path partial = new Path();
    private final RectF oval = new RectF();
    private PathMeasure measure;
    private float t = 0f;
    private static final int[] DOT_COLORS = {0xFF1F6FE0, 0xFF14A39A, 0xFFF5B82E, 0xFF16A36B};

    public CheckAnimView(Context context) {
        this(context, null);
    }

    public CheckAnimView(Context context, @Nullable AttributeSet attrs) {
        super(context, attrs);
        ring.setStyle(Paint.Style.STROKE);
        ring.setStrokeCap(Paint.Cap.ROUND);
        int success = androidx.core.content.ContextCompat.getColor(context, com.example.smartquser.R.color.success);
        ring.setColor(success);
        disc.setColor(success);
        check.setStyle(Paint.Style.STROKE);
        check.setStrokeCap(Paint.Cap.ROUND);
        check.setStrokeJoin(Paint.Join.ROUND);
        check.setColor(0xFFFFFFFF);
    }

    @Override
    protected void onSizeChanged(int w, int h, int oldw, int oldh) {
        float size = Math.min(w, h);
        float cx = w / 2f, cy = h / 2f, r = size * 0.32f;
        ring.setStrokeWidth(size * 0.035f);
        check.setStrokeWidth(size * 0.055f);
        oval.set(cx - r, cy - r, cx + r, cy + r);
        checkPath.reset();
        checkPath.moveTo(cx - r * 0.45f, cy + r * 0.02f);
        checkPath.lineTo(cx - r * 0.12f, cy + r * 0.34f);
        checkPath.lineTo(cx + r * 0.48f, cy - r * 0.30f);
        measure = new PathMeasure(checkPath, false);
    }

    /** Jump to the finished state (no animation). */
    public void showFinished() {
        t = 1f;
        invalidate();
    }

    public void play() {
        if (!Ui.motionEnabled(getContext())) {
            t = 1f;
            invalidate();
            return;
        }
        ValueAnimator a = ValueAnimator.ofFloat(0f, 1f);
        a.setDuration(1300);
        a.setInterpolator(Ui.EASE_OUT);
        a.addUpdateListener(v -> {
            t = (float) v.getAnimatedValue();
            invalidate();
        });
        a.start();
    }

    private static float seg(float t, float from, float to) {
        return Math.max(0f, Math.min(1f, (t - from) / (to - from)));
    }

    @Override
    protected void onDraw(@NonNull Canvas canvas) {
        float cx = getWidth() / 2f, cy = getHeight() / 2f, r = oval.width() / 2f;
        float ringP = seg(t, 0f, 0.4f);
        float fillP = seg(t, 0.3f, 0.6f);
        float checkP = seg(t, 0.5f, 0.85f);
        float burst = seg(t, 0.55f, 1f);

        // soft halo
        disc.setAlpha((int) (40 * fillP));
        canvas.drawCircle(cx, cy, r * (1f + 0.35f * fillP), disc);
        disc.setAlpha(255);
        if (ringP > 0) canvas.drawArc(oval, -90, 360 * ringP, false, ring);
        if (fillP > 0) {
            float overshoot = (float) (1 + 0.12 * Math.sin(Math.PI * fillP));
            canvas.drawCircle(cx, cy, r * fillP * overshoot, disc);
        }
        if (checkP > 0 && measure != null) {
            partial.reset();
            measure.getSegment(0, measure.getLength() * checkP, partial, true);
            canvas.drawPath(partial, check);
        }
        if (burst > 0 && burst < 1) {
            for (int i = 0; i < 10; i++) {
                double ang = Math.toRadians(i * 36 + 12);
                float dist = r * (1.15f + 0.65f * burst);
                dot.setColor(DOT_COLORS[i % DOT_COLORS.length]);
                dot.setAlpha((int) (255 * (1 - burst)));
                canvas.drawCircle(cx + (float) Math.cos(ang) * dist, cy + (float) Math.sin(ang) * dist,
                        r * 0.07f * (1 - burst * 0.5f), dot);
            }
        }
    }
}
