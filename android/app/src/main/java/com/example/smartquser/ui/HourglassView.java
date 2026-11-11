package com.example.smartquser.ui;

import android.animation.ValueAnimator;
import android.content.Context;
import android.graphics.Canvas;
import android.graphics.LinearGradient;
import android.graphics.Paint;
import android.graphics.Path;
import android.graphics.RectF;
import android.graphics.Shader;
import android.util.AttributeSet;
import android.view.View;
import android.view.animation.LinearInterpolator;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;

/**
 * Brand loader: an hourglass (logo colors) whose gold sand flows down, then flips and starts again.
 * Drawn in a 100 x 140 virtual box and scaled to the view.
 */
public class HourglassView extends View {

    private static final float VW = 100f, VH = 140f;
    private static final long CYCLE_MS = 2600;

    private final Paint framePaint = new Paint(Paint.ANTI_ALIAS_FLAG);
    private final Paint capPaint = new Paint(Paint.ANTI_ALIAS_FLAG);
    private final Paint glassPaint = new Paint(Paint.ANTI_ALIAS_FLAG);
    private final Paint sandPaint = new Paint(Paint.ANTI_ALIAS_FLAG);
    private final Paint streamPaint = new Paint(Paint.ANTI_ALIAS_FLAG);
    private final Path glass = new Path();
    private final Path tmp = new Path();
    private final RectF rect = new RectF();
    private ValueAnimator animator;
    private float phase;

    public HourglassView(Context context) {
        this(context, null);
    }

    public HourglassView(Context context, @Nullable AttributeSet attrs) {
        super(context, attrs);
        glass.moveTo(20, 12);
        glass.cubicTo(20, 46, 45, 58, 45, 70);
        glass.cubicTo(45, 82, 20, 94, 20, 128);
        glass.lineTo(80, 128);
        glass.cubicTo(80, 94, 55, 82, 55, 70);
        glass.cubicTo(55, 58, 80, 46, 80, 12);
        glass.close();

        framePaint.setStyle(Paint.Style.STROKE);
        framePaint.setStrokeWidth(5f);
        framePaint.setStrokeJoin(Paint.Join.ROUND);
        framePaint.setShader(new LinearGradient(0, 0, VW, VH, 0xFF4FA3FF, 0xFF12306B, Shader.TileMode.CLAMP));
        capPaint.setShader(new LinearGradient(0, 0, VW, 0, 0xFF3B82F6, 0xFF1F6FE0, Shader.TileMode.CLAMP));
        glassPaint.setColor(0x268EC5FF);
        sandPaint.setShader(new LinearGradient(0, 20, 0, 130, 0xFFFFD479, 0xFFF59E0B, Shader.TileMode.CLAMP));
        streamPaint.setColor(0xFFF5B82E);
    }

    @Override
    protected void onMeasure(int widthMeasureSpec, int heightMeasureSpec) {
        int w = MeasureSpec.getSize(widthMeasureSpec);
        int h = MeasureSpec.getSize(heightMeasureSpec);
        if (MeasureSpec.getMode(heightMeasureSpec) != MeasureSpec.EXACTLY) h = Math.round(w * VH / VW);
        setMeasuredDimension(w, h);
    }

    @Override
    protected void onDraw(@NonNull Canvas canvas) {
        float s = Math.min(getWidth() / VW, getHeight() / VH);
        canvas.save();
        canvas.translate((getWidth() - VW * s) / 2f, (getHeight() - VH * s) / 2f);
        canvas.scale(s, s);

        float flow = Math.min(1f, phase / 0.78f);           // 0..1 sand flowing
        float flip = phase > 0.82f ? (phase - 0.82f) / 0.18f : 0f; // 0..1 rotation
        float eased = (float) (0.5 - Math.cos(Math.PI * flip) / 2);
        canvas.rotate(180f * eased, VW / 2f, VH / 2f);

        canvas.drawPath(glass, glassPaint);

        // sand: top chamber drains, bottom fills
        canvas.save();
        canvas.clipPath(glass);
        float topSurface = 26 + flow * 42;          // falls from y=26 to y=68
        canvas.drawRect(0, topSurface, VW, 70, sandPaint);
        float pile = flow * 38;                       // grows up to 38 units
        if (pile > 0.5f) {
            tmp.reset();
            tmp.moveTo(10, 128);
            tmp.lineTo(10, 128 - pile * 0.55f);
            tmp.quadTo(50, 128 - pile * 1.25f, 90, 128 - pile * 0.55f);
            tmp.lineTo(90, 128);
            tmp.close();
            canvas.drawPath(tmp, sandPaint);
        }
        canvas.restore();

        if (flow > 0.01f && flow < 0.99f) {
            // falling grains (no allocation while drawing)
            float bottom = 126 - pile;
            float offset = (phase * 300f) % 9f;
            for (float y = 70 + offset; y < bottom; y += 9f) {
                canvas.drawCircle(50, y, 1.7f, streamPaint);
            }
        }

        canvas.drawPath(glass, framePaint);
        rect.set(10, 4, 90, 14);
        canvas.drawRoundRect(rect, 5, 5, capPaint);
        rect.set(10, 126, 90, 136);
        canvas.drawRoundRect(rect, 5, 5, capPaint);
        canvas.restore();
    }

    @Override
    protected void onAttachedToWindow() {
        super.onAttachedToWindow();
        animator = ValueAnimator.ofFloat(0f, 1f);
        animator.setDuration(CYCLE_MS);
        animator.setRepeatCount(ValueAnimator.INFINITE);
        animator.setInterpolator(new LinearInterpolator());
        animator.addUpdateListener(a -> {
            phase = (float) a.getAnimatedValue();
            invalidate();
        });
        animator.start();
    }

    @Override
    protected void onDetachedFromWindow() {
        if (animator != null) animator.cancel();
        super.onDetachedFromWindow();
    }
}
