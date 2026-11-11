package com.example.smartquser.ui;

import android.animation.ValueAnimator;
import android.content.Context;
import android.graphics.Canvas;
import android.graphics.LinearGradient;
import android.graphics.Matrix;
import android.graphics.Paint;
import android.graphics.PorterDuff;
import android.graphics.PorterDuffXfermode;
import android.graphics.Shader;
import android.util.AttributeSet;
import android.view.View;
import android.view.animation.LinearInterpolator;
import android.widget.FrameLayout;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;

/** Skeleton loader: sweeps a soft highlight across its (skeleton) children while visible. */
public class ShimmerLayout extends FrameLayout {

    private final Paint paint = new Paint(Paint.ANTI_ALIAS_FLAG);
    private final Matrix matrix = new Matrix();
    private ValueAnimator animator;
    private float progress;
    private int bandWidth;

    public ShimmerLayout(@NonNull Context context) {
        this(context, null);
    }

    public ShimmerLayout(@NonNull Context context, @Nullable AttributeSet attrs) {
        super(context, attrs);
        setWillNotDraw(false);
        paint.setXfermode(new PorterDuffXfermode(PorterDuff.Mode.SRC_ATOP));
    }

    @Override
    protected void onSizeChanged(int w, int h, int oldw, int oldh) {
        super.onSizeChanged(w, h, oldw, oldh);
        bandWidth = Math.max(1, (int) (w * 0.45f));
        paint.setShader(new LinearGradient(0, 0, bandWidth, 0,
                new int[]{0x00FFFFFF, com.example.smartquser.utils.Lang.isDark(getContext()) ? 0x1FFFFFFF : 0xB3FFFFFF, 0x00FFFFFF}, new float[]{0f, 0.5f, 1f}, Shader.TileMode.CLAMP));
    }

    @Override
    protected void dispatchDraw(@NonNull Canvas canvas) {
        if (animator == null || getWidth() == 0) {
            super.dispatchDraw(canvas);
            return;
        }
        int save = canvas.saveLayer(0, 0, getWidth(), getHeight(), null);
        super.dispatchDraw(canvas);
        float x = -bandWidth + (getWidth() + bandWidth * 2f) * progress;
        matrix.setTranslate(x, 0);
        matrix.postSkew(-0.3f, 0);
        paint.getShader().setLocalMatrix(matrix);
        canvas.drawRect(0, 0, getWidth(), getHeight(), paint);
        canvas.restoreToCount(save);
    }

    private void start() {
        if (animator != null) return;
        animator = ValueAnimator.ofFloat(0f, 1f);
        animator.setDuration(1300);
        animator.setRepeatCount(ValueAnimator.INFINITE);
        animator.setInterpolator(new LinearInterpolator());
        animator.addUpdateListener(a -> {
            progress = (float) a.getAnimatedValue();
            invalidate();
        });
        animator.start();
    }

    private void stop() {
        if (animator != null) {
            animator.cancel();
            animator = null;
        }
    }

    @Override
    protected void onAttachedToWindow() {
        super.onAttachedToWindow();
        if (getVisibility() == VISIBLE) start();
    }

    @Override
    protected void onDetachedFromWindow() {
        stop();
        super.onDetachedFromWindow();
    }

    @Override
    protected void onVisibilityChanged(@NonNull View changedView, int visibility) {
        super.onVisibilityChanged(changedView, visibility);
        if (!isAttachedToWindow()) return;
        if (visibility == VISIBLE && getVisibility() == VISIBLE) start(); else stop();
    }
}
