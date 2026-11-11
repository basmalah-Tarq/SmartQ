package com.example.smartquser.ui;

import android.content.Context;
import android.content.res.TypedArray;
import android.graphics.Canvas;
import android.graphics.DashPathEffect;
import android.graphics.LinearGradient;
import android.graphics.Paint;
import android.graphics.Path;
import android.graphics.Rect;
import android.graphics.RectF;
import android.graphics.Shader;
import android.util.AttributeSet;
import android.view.View;
import android.widget.FrameLayout;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;
import androidx.core.content.ContextCompat;

import com.example.smartquser.R;

/**
 * QueueTicket container: a rounded card with two half-circle notches and a dashed tear line,
 * like a real queue ticket. Style "brand" = brand gradient with white text, "light" = white card.
 * The notches line up with the child given in app:notchAnchor (or app:notchFraction).
 */
public class TicketView extends FrameLayout {

    private final Paint fill = new Paint(Paint.ANTI_ALIAS_FLAG);
    private final Paint border = new Paint(Paint.ANTI_ALIAS_FLAG);
    private final Paint dash = new Paint(Paint.ANTI_ALIAS_FLAG);
    private final Path shape = new Path();
    private final Path notches = new Path();
    private final RectF box = new RectF();
    private final Rect tmp = new Rect();

    private final boolean brand;
    private final int anchorId;
    private final float notchFraction;
    private final float radius;
    private final float notchR;
    private final float shadowPad;
    private final int surfaceColor;
    private float notchY = -1;

    public TicketView(@NonNull Context context) {
        this(context, null);
    }

    public TicketView(@NonNull Context context, @Nullable AttributeSet attrs) {
        super(context, attrs);
        setWillNotDraw(false);
        float d = getResources().getDisplayMetrics().density;
        TypedArray a = context.obtainStyledAttributes(attrs, R.styleable.TicketView);
        brand = a.getInt(R.styleable.TicketView_ticketStyle, 0) == 0;
        anchorId = a.getResourceId(R.styleable.TicketView_notchAnchor, View.NO_ID);
        notchFraction = a.getFloat(R.styleable.TicketView_notchFraction, 0.62f);
        a.recycle();

        radius = 22 * d;
        notchR = 12 * d;
        shadowPad = 10 * d;

        setLayerType(LAYER_TYPE_SOFTWARE, null); // needed for the soft shadow on a custom path
        fill.setStyle(Paint.Style.FILL);
        fill.setShadowLayer(14 * d, 0, 6 * d, brand ? 0x552456B8 : 0x260B1F45);
        border.setStyle(Paint.Style.STROKE);
        border.setStrokeWidth(d);
        border.setColor(ContextCompat.getColor(context, R.color.border));
        surfaceColor = ContextCompat.getColor(context, R.color.surface);
        dash.setStyle(Paint.Style.STROKE);
        dash.setStrokeWidth(1.5f * d);
        dash.setColor(brand ? 0x59FFFFFF : ContextCompat.getColor(context, R.color.ticket_dash));
        dash.setPathEffect(new DashPathEffect(new float[]{7 * d, 6 * d}, 0));

        int p = (int) shadowPad;
        setPadding(getPaddingLeft() + p, getPaddingTop() + p, getPaddingRight() + p, getPaddingBottom() + p);
        setClipToPadding(false);
    }

    @Override
    protected void onLayout(boolean changed, int left, int top, int right, int bottom) {
        super.onLayout(changed, left, top, right, bottom);
        float newY = -1;
        if (anchorId != View.NO_ID) {
            View anchor = findViewById(anchorId);
            if (anchor != null && anchor.getVisibility() != GONE) {
                anchor.getDrawingRect(tmp);
                offsetDescendantRectToMyCoords(anchor, tmp);
                newY = tmp.exactCenterY();
            }
        }
        if (newY < 0) newY = shadowPad + (getHeight() - 2 * shadowPad) * notchFraction;
        if (newY != notchY || changed) {
            notchY = newY;
            buildShape();
            invalidate();
        }
    }

    private void buildShape() {
        box.set(shadowPad, shadowPad, getWidth() - shadowPad, getHeight() - shadowPad);
        shape.reset();
        shape.addRoundRect(box, radius, radius, Path.Direction.CW);
        notches.reset();
        notches.addCircle(box.left, notchY, notchR, Path.Direction.CW);
        notches.addCircle(box.right, notchY, notchR, Path.Direction.CW);
        shape.op(notches, Path.Op.DIFFERENCE);
        if (brand) {
            fill.setShader(new LinearGradient(box.left, box.top, box.right, box.bottom,
                    new int[]{0xFF0B1F45, 0xFF1F6FE0, 0xFF14A39A}, new float[]{0f, 0.58f, 1f}, Shader.TileMode.CLAMP));
        } else {
            fill.setShader(null);
            fill.setColor(surfaceColor);
        }
    }

    @Override
    protected void onDraw(@NonNull Canvas canvas) {
        if (getWidth() == 0) return;
        if (notchY < 0) buildShape();
        canvas.drawPath(shape, fill);
        if (!brand) canvas.drawPath(shape, border);
        canvas.drawLine(box.left + notchR + 8, notchY, box.right - notchR - 8, notchY, dash);
        super.onDraw(canvas);
    }
}
