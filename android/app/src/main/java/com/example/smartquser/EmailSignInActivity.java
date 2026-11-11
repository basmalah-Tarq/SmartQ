package com.example.smartquser;

import android.content.Context;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.os.SystemClock;
import android.util.Patterns;
import android.view.View;
import android.view.inputmethod.EditorInfo;
import android.view.inputmethod.InputMethodManager;
import android.widget.TextView;

import androidx.activity.OnBackPressedCallback;

import com.example.smartquser.auth.CodeInput;
import com.example.smartquser.net.ApiClient;
import com.example.smartquser.net.ApiError;
import com.example.smartquser.net.ApiResponse;
import com.example.smartquser.ui.CodeInputView;
import com.example.smartquser.utils.Lang;
import com.example.smartquser.utils.Ui;
import com.google.android.material.button.MaterialButton;
import com.google.android.material.textfield.TextInputEditText;
import com.google.android.material.textfield.TextInputLayout;

/**
 * Passwordless sign-in: email -> 6-digit code (email_start.php / email_verify.php).
 * New emails get an account automatically; the name is optional.
 */
public class EmailSignInActivity extends BaseActivity {

    public static final String EXTRA_EMAIL = "email";

    private static final String S_STEP = "step", S_EMAIL = "email", S_NEW = "new", S_RESEND_AT = "resend_at";

    private final Handler handler = new Handler(Looper.getMainLooper());
    private View stepEmail, stepCode;
    private TextInputLayout tilEmail, tilName;
    private TextInputEditText etEmail, etName;
    private TextView emailError, codeError, stepLabel;
    private MaterialButton btnSend, btnVerify, btnResend;
    private View sendProgress, verifyProgress;
    private CodeInputView codeInput;

    private boolean onCodeStep, isNewUser, busy;
    private String email = "";
    /** SystemClock.elapsedRealtime() when "Resend" becomes available. */
    private long resendAt;

    private final Runnable resendTicker = new Runnable() {
        @Override
        public void run() {
            updateResend();
            if (CodeInput.secondsLeft(SystemClock.elapsedRealtime(), resendAt) > 0) handler.postDelayed(this, 500);
        }
    };

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_email_sign_in);
        Ui.padTopAndBottom(findViewById(R.id.root));

        stepEmail = findViewById(R.id.stepEmail);
        stepCode = findViewById(R.id.stepCode);
        stepLabel = findViewById(R.id.stepLabel);
        tilEmail = findViewById(R.id.tilEmail);
        tilName = findViewById(R.id.tilName);
        etEmail = findViewById(R.id.etEmail);
        etName = findViewById(R.id.etName);
        emailError = findViewById(R.id.emailError);
        codeError = findViewById(R.id.codeError);
        btnSend = findViewById(R.id.btnSend);
        btnVerify = findViewById(R.id.btnVerify);
        btnResend = findViewById(R.id.btnResend);
        sendProgress = findViewById(R.id.sendProgress);
        verifyProgress = findViewById(R.id.verifyProgress);
        codeInput = findViewById(R.id.codeInput);

        findViewById(R.id.btnBack).setOnClickListener(v -> getOnBackPressedDispatcher().onBackPressed());
        btnSend.setOnClickListener(v -> sendCode(false));
        etEmail.setOnEditorActionListener((v, actionId, e) -> {
            if (actionId == EditorInfo.IME_ACTION_GO || actionId == EditorInfo.IME_ACTION_DONE) {
                sendCode(false);
                return true;
            }
            return false;
        });
        findViewById(R.id.btnChangeEmail).setOnClickListener(v -> showEmailStep(true));
        btnResend.setOnClickListener(v -> sendCode(true));
        findViewById(R.id.btnPaste).setOnClickListener(v -> {
            if (!codeInput.pasteFromClipboard()) Ui.snack(this, getString(R.string.auth_no_code_clipboard));
        });
        btnVerify.setOnClickListener(v -> verify());
        codeInput.setOnCompleteListener(code -> {
            btnVerify.setEnabled(!busy);
            // returning users go straight in; new users may still want to add their name
            if (!isNewUser) verify();
            else etName.requestFocus();
        });
        codeInput.addTextChangedListener(new android.text.TextWatcher() {
            public void beforeTextChanged(CharSequence s, int a, int b, int c) {}

            public void onTextChanged(CharSequence s, int a, int b, int c) {}

            public void afterTextChanged(android.text.Editable s) {
                btnVerify.setEnabled(!busy && CodeInput.isComplete(s));
                if (s.length() > 0) codeError.setVisibility(View.GONE);
            }
        });
        etName.setOnEditorActionListener((v, actionId, e) -> {
            if (actionId == EditorInfo.IME_ACTION_DONE && CodeInput.isComplete(codeInput.getCode())) {
                verify();
                return true;
            }
            return false;
        });

        getOnBackPressedDispatcher().addCallback(this, new OnBackPressedCallback(true) {
            @Override
            public void handleOnBackPressed() {
                if (onCodeStep && !busy) {
                    showEmailStep(true);
                } else {
                    setEnabled(false);
                    getOnBackPressedDispatcher().onBackPressed();
                }
            }
        });

        if (savedInstanceState != null) {
            email = savedInstanceState.getString(S_EMAIL, "");
            isNewUser = savedInstanceState.getBoolean(S_NEW, false);
            resendAt = savedInstanceState.getLong(S_RESEND_AT, 0);
            if (savedInstanceState.getBoolean(S_STEP, false)) {
                showCodeStep(false);
                return;
            }
        } else {
            String pre = getIntent().getStringExtra(EXTRA_EMAIL);
            if (pre == null || pre.isEmpty()) pre = session.getEmail();
            if (pre != null && !pre.isEmpty()) etEmail.setText(pre);
        }
        showEmailStep(false);
        Ui.enterAll(findViewById(R.id.emailIcon), findViewById(R.id.emailTitle), findViewById(R.id.emailSubtitle),
                tilEmail, btnSend, findViewById(R.id.emailTrust));
        etEmail.requestFocus();
    }

    @Override
    protected void onSaveInstanceState(Bundle out) {
        super.onSaveInstanceState(out);
        out.putBoolean(S_STEP, onCodeStep);
        out.putString(S_EMAIL, email);
        out.putBoolean(S_NEW, isNewUser);
        out.putLong(S_RESEND_AT, resendAt);
    }

    // ------------------------------------------------------------ steps

    private void showEmailStep(boolean animate) {
        onCodeStep = false;
        stepLabel.setText(R.string.auth_step_1);
        handler.removeCallbacks(resendTicker);
        swap(stepEmail, stepCode, animate, false);
        etEmail.requestFocus();
    }

    private void showCodeStep(boolean animate) {
        onCodeStep = true;
        stepLabel.setText(R.string.auth_step_2);
        ((TextView) findViewById(R.id.codeEmail)).setText(Lang.ltr(email));
        tilName.setVisibility(isNewUser ? View.VISIBLE : View.GONE);
        ((TextView) findViewById(R.id.codeTitle)).setText(isNewUser ? R.string.auth_code_title_new : R.string.auth_code_title);
        btnVerify.setText(isNewUser ? R.string.auth_create_my_account : R.string.auth_continue);
        btnVerify.setEnabled(CodeInput.isComplete(codeInput.getCode()));
        swap(stepCode, stepEmail, animate, true);
        handler.removeCallbacks(resendTicker);
        handler.post(resendTicker);
        codeInput.requestFocus();
        showKeyboard(codeInput);
    }

    /** Slides the new step in from the side it logically comes from. */
    private void swap(View show, View hide, boolean animate, boolean forward) {
        if (!animate || !Ui.motionEnabled(this)) {
            hide.setVisibility(View.GONE);
            show.setVisibility(View.VISIBLE);
            show.setAlpha(1f);
            show.setTranslationX(0);
            return;
        }
        float dx = Ui.dp(this, 48) * (forward ? 1 : -1);
        hide.animate().alpha(0f).translationX(-dx).setDuration(180).withEndAction(() -> {
            hide.setVisibility(View.GONE);
            hide.setAlpha(1f);
            hide.setTranslationX(0);
        }).start();
        show.setVisibility(View.VISIBLE);
        show.setAlpha(0f);
        show.setTranslationX(dx);
        show.animate().alpha(1f).translationX(0).setStartDelay(120).setDuration(380)
                .setInterpolator(Ui.EASE_OUT).start();
    }

    // ------------------------------------------------------------ email_start

    private void sendCode(boolean resend) {
        if (busy) return;
        String value = resend ? email : LoginActivity.text(etEmail).toLowerCase(java.util.Locale.ROOT);
        tilEmail.setError(null);
        emailError.setVisibility(View.GONE);
        codeError.setVisibility(View.GONE);
        if (!resend && (value.isEmpty() || !Patterns.EMAIL_ADDRESS.matcher(value).matches())) {
            tilEmail.setError(getString(R.string.auth_invalid_email));
            Ui.shake(tilEmail);
            return;
        }
        if (resend && CodeInput.secondsLeft(SystemClock.elapsedRealtime(), resendAt) > 0) return;
        // same email again within the resend window: the code we already sent is still good
        if (!resend && value.equals(email) && CodeInput.secondsLeft(SystemClock.elapsedRealtime(), resendAt) > 0) {
            showCodeStep(true);
            return;
        }

        setSending(true, resend);
        ApiClient.post(this, "email_start.php", ApiClient.params("email", value), new ApiClient.Callback() {
            @Override
            public void onSuccess(ApiResponse r) {
                setSending(false, resend);
                boolean changed = !value.equals(email);
                email = r.data.optString("email", value);
                isNewUser = r.data.optBoolean("is_new_user", false);
                int wait = r.data.optInt("resend_in", 60);
                resendAt = SystemClock.elapsedRealtime() + wait * 1000L;
                if (changed) codeInput.setText("");
                if (resend) {
                    codeInput.setText("");
                    Ui.snack(EmailSignInActivity.this, getString(R.string.auth_new_code_sent, Lang.ltr(email)));
                    handler.removeCallbacks(resendTicker);
                    handler.post(resendTicker);
                    codeInput.requestFocus();
                } else {
                    showCodeStep(true);
                }
            }

            @Override
            public void onError(ApiError e) {
                setSending(false, resend);
                if (resend) {
                    showCodeError(e.message);
                } else if (e.httpCode == 429 && value.equals(email)) {
                    // rate-limited, but a code for this email is already on its way
                    showCodeStep(true);
                    showCodeError(e.message);
                } else {
                    emailError.setText(e.message);
                    emailError.setVisibility(View.VISIBLE);
                    Ui.enter(emailError, 0);
                    Ui.shake(emailError);
                }
            }
        });
    }

    private void setSending(boolean sending, boolean resend) {
        busy = sending;
        if (resend) {
            btnResend.setEnabled(!sending);
            if (sending) btnResend.setText(R.string.auth_sending);
            else updateResend();
            return;
        }
        btnSend.setEnabled(!sending);
        btnSend.setText(sending ? "" : getString(R.string.auth_send_code));
        btnSend.setIcon(sending ? null : getDrawable(R.drawable.ic_arrow_forward));
        sendProgress.setVisibility(sending ? View.VISIBLE : View.GONE);
        etEmail.setEnabled(!sending);
    }

    private void updateResend() {
        if (busy) return;
        long left = CodeInput.secondsLeft(SystemClock.elapsedRealtime(), resendAt);
        btnResend.setEnabled(left == 0);
        btnResend.setText(left == 0 ? getString(R.string.auth_resend_code)
                : getString(R.string.auth_resend_in, Lang.ltr(CodeInput.formatCountdown(left))));
    }

    // ------------------------------------------------------------ email_verify

    private void verify() {
        String code = codeInput.getCode();
        if (busy) return;
        if (!CodeInput.isComplete(code)) {
            codeInput.setError(true);
            Ui.shake(codeInput);
            return;
        }
        setVerifying(true);
        String name = LoginActivity.text(etName);
        ApiClient.post(this, "email_verify.php",
                ApiClient.params("email", email, "code", code, "full_name", isNewUser ? name : null),
                new ApiClient.Callback() {
                    @Override
                    public void onSuccess(ApiResponse r) {
                        if (!saveAuth(r)) {
                            setVerifying(false);
                            showCodeError(getString(R.string.signin_failed));
                            return;
                        }
                        hideKeyboard();
                        finishSignedIn(r.data.optBoolean("is_new_user", isNewUser));
                    }

                    @Override
                    public void onError(ApiError e) {
                        setVerifying(false);
                        showCodeError(e.message);
                        if (!e.isNetworkProblem()) {
                            // wrong / expired code: clear the boxes so the next try starts fresh
                            codeInput.setError(true);
                            Ui.shake(codeInput);
                            handler.postDelayed(() -> {
                                codeInput.setText("");
                                codeInput.setError(false);
                                codeInput.requestFocus();
                            }, 650);
                        }
                    }
                });
    }

    private void setVerifying(boolean verifying) {
        busy = verifying;
        btnVerify.setEnabled(!verifying && CodeInput.isComplete(codeInput.getCode()));
        btnVerify.setText(verifying ? "" : getString(isNewUser ? R.string.auth_create_my_account : R.string.auth_continue));
        btnVerify.setIcon(verifying ? null : getDrawable(R.drawable.ic_arrow_forward));
        verifyProgress.setVisibility(verifying ? View.VISIBLE : View.GONE);
        codeInput.setEnabled(!verifying);
        btnResend.setEnabled(!verifying && CodeInput.secondsLeft(SystemClock.elapsedRealtime(), resendAt) == 0);
    }

    private void showCodeError(String message) {
        codeError.setText(message);
        codeError.setVisibility(View.VISIBLE);
        Ui.enter(codeError, 0);
    }

    // ------------------------------------------------------------ keyboard

    private void showKeyboard(View v) {
        v.post(() -> {
            InputMethodManager imm = (InputMethodManager) getSystemService(Context.INPUT_METHOD_SERVICE);
            if (imm != null) imm.showSoftInput(v, InputMethodManager.SHOW_IMPLICIT);
        });
    }

    private void hideKeyboard() {
        InputMethodManager imm = (InputMethodManager) getSystemService(Context.INPUT_METHOD_SERVICE);
        View f = getCurrentFocus();
        if (imm != null && f != null) imm.hideSoftInputFromWindow(f.getWindowToken(), 0);
    }

    @Override
    protected void onDestroy() {
        handler.removeCallbacksAndMessages(null);
        super.onDestroy();
    }
}
