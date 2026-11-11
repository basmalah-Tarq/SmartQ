package com.example.smartquser.ui;

import android.animation.ValueAnimator;
import android.content.ClipData;
import android.content.ClipboardManager;
import android.content.Context;
import android.graphics.Canvas;
import android.graphics.Color;
import android.graphics.Paint;
import android.graphics.RectF;
import android.graphics.Typeface;
import android.os.Build;
import android.text.Editable;
import android.text.InputFilter;
import android.text.InputType;
import android.text.TextWatcher;
import android.util.AttributeSet;
import android.view.Gravity;
import android.view.View;
import android.view.inputmethod.EditorInfo;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;
import androidx.appcompat.widget.AppCompatEditText;
import androidx.core.content.ContextCompat;
import androidx.core.content.res.ResourcesCompat;

import com.example.smartquser.R;
import com.example.smartquser.auth.CodeInput;
import com.example.smartquser.utils.Ui;

/**
 * Six code boxes backed by ONE EditText, so the keyboard, autofill (one-time-code hints) and
 * paste ("Your SmartQ code: 048608") all work naturally. Digits pop into their box; the active box
 * has a blinking caret; {@link #setError(boolean)} turns the boxes red.
 */
public class CodeInputView extends AppCompatEditText {

    public interface OnCompleteListener {
        void onComplete(String code);
    }

    private static final int N = CodeInput.LENGTH;

    private final Paint boxFill = new Paint(Paint.ANTI_ALIAS_FLAG);
    private final Paint boxStroke = new Paint(Paint.ANTI_ALIAS_FLAG);
    private final Paint digitPaint = new Paint(Paint.ANTI_ALIAS_FLAG);
    private final Paint caretPaint = new Paint(Paint.ANTI_ALIAS_FLAG);
    private final RectF rect = new RectF();
    private final float[] scale = new float[N];
    private final float density;

    private final int cBorder, cBlue, cBlueTint, cSurface, cSurfaceAlt, cNavy, cDanger, cDangerTint;
    private boolean error;
    private boolean caretOn = true;
    private OnCompleteListener listener;
    private String lastText = "";

    private final Runnable blink = new Runnable() {
        @Override
        public void run() {
            caretOn = !caretOn;
            invalidate();
            postDelayed(this, 520);
        }
    };

    public CodeInputView(@NonNull Context context) {
        this(context, null);
    }

    public CodeInputView(@NonNull Context context, @Nullable AttributeSet attrs) {
        super(context, attrs);
        density = getResources().getDisplayMetrics().density;
        cBorder = ContextCompat.getColor(context, R.color.border);
        cBlue = ContextCompat.getColor(context, R.color.blue_600);
        cBlueTint = ContextCompat.getColor(context, R.color.blue_tint);
        cSurface = ContextCompat.getColor(context, R.color.surface);
        cSurfaceAlt = ContextCompat.getColor(context, R.color.surface_alt);
        cNavy = ContextCompat.getColor(context, R.color.ink);
        cDanger = ContextCompat.getColor(context, R.color.danger);
        cDangerTint = ContextCompat.getColor(context, R.color.danger_tint);
        for (int i = 0; i < N; i++) scale[i] = 1f;

        setBackground(null);
        setWillNotDraw(false); // we draw the boxes ourselves even without a background
        setCursorVisible(false);
        setTextColor(Color.TRANSPARENT);
        setHighlightColor(Color.TRANSPARENT);
        setGravity(Gravity.START | Gravity.CENTER_VERTICAL);
        setSingleLine(true);
        setLongClickable(true);
        setInputType(InputType.TYPE_CLASS_NUMBER);
        setImeOptions(EditorInfo.IME_ACTION_DONE | EditorInfo.IME_FLAG_NO_EXTRACT_UI);
        setFilters(new InputFilter[]{
                (source, start, end, dest, dstart, dend) -> {
                    // keep digits only (autofill / IME can deliver "048 608")
                    CharSequence part = source.subSequence(start, end);
                    String digits = CodeInput.digitsOnly(part, N);
                    return digits.contentEquals(part) ? null : digits;
                },
                new InputFilter.LengthFilter(N)});
        if (Build.VERSION.SDK_INT >= 26) {
            setImportantForAutofill(View.IMPORTANT_FOR_AUTOFILL_YES);
            setAutofillHints("emailOTPCode", "smsOTPCode");
        }
        setContentDescription(context.getString(R.string.auth_code_cd));
        // codes are always read left-to-right, also when the app runs in Arabic
        setLayoutDirection(View.LAYOUT_DIRECTION_LTR);
        setTextDirection(View.TEXT_DIRECTION_LTR);

        Typeface tf = isInEditMode() ? Typeface.DEFAULT_BOLD : ResourcesCompat.getFont(context, R.font.jakarta_extrabold);
        digitPaint.setTypeface(tf);
        digitPaint.setTextAlign(Paint.Align.CENTER);
        digitPaint.setTextSize(24 * getResources().getDisplayMetrics().scaledDensity);
        digitPaint.setColor(cNavy);
        boxStroke.setStyle(Paint.Style.STROKE);
        caretPaint.setColor(cBlue);
        caretPaint.setStrokeWidth(2 * density);
        caretPaint.setStrokeCap(Paint.Cap.ROUND);

        addTextChangedListener(new TextWatcher() {
            public void beforeTextChanged(CharSequence s, int a, int b, int c) {}

            public void onTextChanged(CharSequence s, int a, int b, int c) {}

            public void afterTextChanged(Editable s) {
                String now = s.toString();
                if (now.length() > lastText.length()) popBox(now.length() - 1);
                lastText = now;
                if (error) setError(false);
                caretOn = true;
                if (getSelectionEnd() != now.length()) setSelection(now.length());
                if (listener != null && CodeInput.isComplete(now)) listener.onComplete(now);
                invalidate();
            }
        });
    }

    public void setOnCompleteListener(OnCompleteListener l) {
        listener = l;
    }

    public String getCode() {
        return getText() == null ? "" : getText().toString();
    }

    public void setError(boolean error) {
        this.error = error;
        invalidate();
    }

    /** Pastes a code from the clipboard. Returns false when the clipboard has no 6-digit code. */
    public boolean pasteFromClipboard() {
        ClipboardManager cm = (ClipboardManager) getContext().getSystemService(Context.CLIPBOARD_SERVICE);
        if (cm == null || !cm.hasPrimaryClip()) return false;
        ClipData clip = cm.getPrimaryClip();
        if (clip == null || clip.getItemCount() == 0) return false;
        CharSequence text = clip.getItemAt(0).coerceToText(getContext());
        String code = CodeInput.extractCode(text);
        if (code == null) return false;
        setText(code);
        return true;
    }

    @Override
    public boolean onTextContextMenuItem(int id) {
        if (id == android.R.id.paste || id == android.R.id.pasteAsPlainText) {
            if (pasteFromClipboard()) return true;
        }
        return super.onTextContextMenuItem(id);
    }

    @Override
    protected void onSelectionChanged(int selStart, int selEnd) {
        CharSequence t = getText();
        int len = t == null ? 0 : t.length();
        if (selStart != len || selEnd != len) {
            setSelection(len); // the caret always sits after the last digit
            return;
        }
        super.onSelectionChanged(selStart, selEnd);
    }

    @Override
    protected void onFocusChanged(boolean focused, int direction, @Nullable android.graphics.Rect prev) {
        super.onFocusChanged(focused, direction, prev);
        removeCallbacks(blink);
        caretOn = true;
        if (focused && Ui.motionEnabled(getContext())) postDelayed(blink, 520);
        invalidate();
    }

    @Override
    protected void onDetachedFromWindow() {
        removeCallbacks(blink);
        super.onDetachedFromWindow();
    }

    private void popBox(int index) {
        if (index < 0 || index >= N || !Ui.motionEnabled(getContext())) return;
        ValueAnimator a = ValueAnimator.ofFloat(0.82f, 1.06f, 1f);
        a.setDuration(260);
        a.addUpdateListener(an -> {
            scale[index] = (float) an.getAnimatedValue();
            invalidate();
        });
        a.start();
    }

    @Override
    protected void onMeasure(int widthMeasureSpec, int heightMeasureSpec) {
        int w = MeasureSpec.getSize(widthMeasureSpec);
        if (MeasureSpec.getMode(widthMeasureSpec) == MeasureSpec.UNSPECIFIED) w = Math.round(N * 56 * density);
        int h = Math.round(64 * density);
        setMeasuredDimension(w, h);
    }

    @Override
    protected void onDraw(@NonNull Canvas canvas) {
        // the real text is invisible; we draw the boxes ourselves (no super.onDraw).
        // A single-line EditText scrolls its (very wide) text layout, so undo that scroll first.
        canvas.save();
        canvas.translate(getScrollX(), getScrollY());
        String code = getCode();
        float gap = 10 * density;
        float avail = getWidth() - getPaddingLeft() - getPaddingRight();
        float box = Math.min(56 * density, (avail - gap * (N - 1)) / N);
        float total = box * N + gap * (N - 1);
        float left = getPaddingLeft() + (avail - total) / 2f;
        float boxH = Math.min(getHeight() - 4 * density, box * 1.18f);
        float top = (getHeight() - boxH) / 2f;
        float r = 14 * density;
        int active = Math.min(code.length(), N - 1);
        boolean focused = isFocused();

        for (int i = 0; i < N; i++) {
            float x = left + i * (box + gap);
            boolean filled = i < code.length();
            boolean isActive = focused && i == active;
            canvas.save();
            canvas.scale(scale[i], scale[i], x + box / 2f, top + boxH / 2f);
            rect.set(x, top, x + box, top + boxH);

            int fill, stroke;
            float sw;
            if (error) {
                fill = cDangerTint;
                stroke = cDanger;
                sw = 1.5f * density;
            } else if (isActive) {
                fill = cSurface;
                stroke = cBlue;
                sw = 2f * density;
            } else if (filled) {
                fill = cSurfaceAlt;
                stroke = cBlueTint;
                sw = 1.5f * density;
            } else {
                fill = cSurface;
                stroke = cBorder;
                sw = 1.5f * density;
            }
            boxFill.setColor(fill);
            canvas.drawRoundRect(rect, r, r, boxFill);
            if (isActive && !error) {
                // soft focus ring
                boxStroke.setColor(cBlueTint);
                boxStroke.setStrokeWidth(6 * density);
                canvas.drawRoundRect(rect, r, r, boxStroke);
            }
            boxStroke.setColor(stroke);
            boxStroke.setStrokeWidth(sw);
            canvas.drawRoundRect(rect, r, r, boxStroke);

            if (filled) {
                digitPaint.setColor(error ? cDanger : cNavy);
                Paint.FontMetrics fm = digitPaint.getFontMetrics();
                float baseline = top + boxH / 2f - (fm.ascent + fm.descent) / 2f;
                canvas.drawText(code.substring(i, i + 1), x + box / 2f, baseline, digitPaint);
            } else if (isActive && caretOn && !error) {
                float cx = x + box / 2f;
                canvas.drawLine(cx, top + boxH * 0.3f, cx, top + boxH * 0.7f, caretPaint);
            }
            canvas.restore();
        }
        canvas.restore();
    }
}
