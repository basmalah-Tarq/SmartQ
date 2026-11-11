package com.example.smartquser.ui;

import android.animation.ArgbEvaluator;
import android.content.Context;
import android.graphics.Canvas;
import android.graphics.Paint;
import android.graphics.RectF;
import android.util.AttributeSet;
import android.view.View;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;
import androidx.core.content.ContextCompat;

import com.example.smartquser.R;

/**
 * Onboarding dots: the current page's dot stretches into a blue pill and the stretch follows the
 * finger while swiping ({@link #setProgress(int, float)} from ViewPager2's onPageScrolled).
 */
public class PageIndicatorView extends View {

    private final Paint paint = new Paint(Paint.ANTI_ALIAS_FLAG);
    private final RectF rect = new RectF();
    private final ArgbEvaluator argb = new ArgbEvaluator();
    private final float dot, pill, gap;
    private final int cOff, cOn;
    private int count = 3;
    private int position;
    private float offset;

    public PageIndicatorView(Context context) {
        this(context, null);
    }

    public PageIndicatorView(Context context, @Nullable AttributeSet attrs) {
        super(context, attrs);
        float d = getResources().getDisplayMetrics().density;
        dot = 8 * d;
        pill = 26 * d;
        gap = 8 * d;
        cOff = ContextCompat.getColor(context, R.color.border);
        cOn = ContextCompat.getColor(context, R.color.accent);
    }

    public void setCount(int count) {
        this.count = Math.max(1, count);
        requestLayout();
        invalidate();
    }

    public void setProgress(int position, float offset) {
        this.position = position;
        this.offset = offset;
        invalidate();
    }

    @Override
    protected void onMeasure(int widthMeasureSpec, int heightMeasureSpec) {
        int w = Math.round(pill + (count - 1) * (dot + gap)) + getPaddingLeft() + getPaddingRight();
        int h = Math.round(dot) + getPaddingTop() + getPaddingBottom();
        setMeasuredDimension(resolveSize(w, widthMeasureSpec), resolveSize(h, heightMeasureSpec));
    }

    /** 1 for the current page, falling to 0 for its neighbours as the swipe progresses. */
    private float weight(int i) {
        if (i == position) return 1f - offset;
        if (i == position + 1) return offset;
        return 0f;
    }

    @Override
    protected void onDraw(@NonNull Canvas canvas) {
        float total = pill + (count - 1) * (dot + gap);
        float x = (getWidth() - total) / 2f;
        float top = (getHeight() - dot) / 2f;
        // ViewPager2 pages right-to-left in RTL: mirror the dots so the active one follows the page
        boolean rtl = getLayoutDirection() == LAYOUT_DIRECTION_RTL;
        canvas.save();
        if (rtl) canvas.scale(-1f, 1f, getWidth() / 2f, getHeight() / 2f);
        for (int i = 0; i < count; i++) {
            float w = weight(i);
            float width = dot + (pill - dot) * w;
            paint.setColor((Integer) argb.evaluate(w, cOff, cOn));
            rect.set(x, top, x + width, top + dot);
            canvas.drawRoundRect(rect, dot / 2f, dot / 2f, paint);
            x += width + gap;
        }
        canvas.restore();
    }
}
