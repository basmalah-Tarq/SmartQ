package com.example.smartquser;

import android.content.Intent;
import android.os.Bundle;
import android.util.Patterns;
import android.view.View;
import android.view.inputmethod.EditorInfo;
import android.widget.TextView;

import com.example.smartquser.net.ApiClient;
import com.example.smartquser.net.ApiError;
import com.example.smartquser.net.ApiResponse;
import com.example.smartquser.utils.Ui;
import com.google.android.material.button.MaterialButton;
import com.google.android.material.textfield.TextInputEditText;
import com.google.android.material.textfield.TextInputLayout;

public class LoginActivity extends BaseActivity {

    private TextInputLayout tilEmail, tilPassword;
    private TextInputEditText etEmail, etPassword;
    private MaterialButton btnLogin;
    private View progress;
    private TextView tvError;

    @Override
    protected boolean darkStatusBar() {
        return true;
    }

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        if (session.isLoggedIn()) {
            goHome();
            return;
        }
        setContentView(R.layout.activity_login);
        Ui.padTop(findViewById(R.id.header));
        Ui.padBottom(findViewById(R.id.scroll));

        tilEmail = findViewById(R.id.tilEmail);
        tilPassword = findViewById(R.id.tilPassword);
        etEmail = findViewById(R.id.etEmail);
        etPassword = findViewById(R.id.etPassword);
        btnLogin = findViewById(R.id.btnLogin);
        progress = findViewById(R.id.progress);
        tvError = findViewById(R.id.tvError);

        btnLogin.setOnClickListener(v -> attemptLogin());
        etPassword.setOnEditorActionListener((v, actionId, event) -> {
            if (actionId == EditorInfo.IME_ACTION_DONE) {
                attemptLogin();
                return true;
            }
            return false;
        });
        findViewById(R.id.btnGoRegister).setOnClickListener(v -> {
            Intent reg = new Intent(this, RegisterActivity.class);
            reg.putExtra(EXTRA_FOR_RESULT, isForResult());
            startActivity(reg);
        });

        String msg = getIntent().getStringExtra(EXTRA_MESSAGE);
        if (msg != null && !msg.isEmpty()) showError(msg);

        Ui.enterAll(findViewById(R.id.logoPlate), findViewById(R.id.title), findViewById(R.id.subtitle),
                findViewById(R.id.formCard), findViewById(R.id.footer));
    }

    private void attemptLogin() {
        tilEmail.setError(null);
        tilPassword.setError(null);
        tvError.setVisibility(View.GONE);

        String email = text(etEmail);
        String password = etPassword.getText() == null ? "" : etPassword.getText().toString();
        boolean ok = true;
        if (email.isEmpty() || !Patterns.EMAIL_ADDRESS.matcher(email).matches()) {
            tilEmail.setError(getString(R.string.auth_invalid_email));
            ok = false;
        }
        if (password.isEmpty()) {
            tilPassword.setError(getString(R.string.auth_enter_password));
            ok = false;
        }
        if (!ok) {
            Ui.shake(findViewById(R.id.formCard));
            return;
        }

        setLoading(true);
        ApiClient.post(this, "login.php", ApiClient.params("email", email, "password", password),
                new ApiClient.Callback() {
                    @Override
                    public void onSuccess(ApiResponse r) {
                        if (!saveAuth(r)) {
                            setLoading(false);
                            showError(getString(R.string.auth_login_failed));
                            return;
                        }
                        goHome();
                    }

                    @Override
                    public void onError(ApiError e) {
                        setLoading(false);
                        showError(e.message);
                    }
                });
    }

    private void showError(String message) {
        tvError.setText(message);
        tvError.setVisibility(View.VISIBLE);
        Ui.enter(tvError, 0);
        Ui.shake(tvError);
    }

    private void setLoading(boolean loading) {
        btnLogin.setEnabled(!loading);
        btnLogin.setText(loading ? "" : getString(R.string.auth_log_in));
        btnLogin.setIcon(loading ? null : getDrawable(R.drawable.ic_arrow_forward));
        progress.setVisibility(loading ? View.VISIBLE : View.GONE);
    }

    private void goHome() {
        finishSignedIn(false);
    }

    @Override
    protected void onResume() {
        super.onResume();
        // came back from Register (opened from the sign-in sheet) already signed in
        if (isForResult() && session.isLoggedIn()) goHome();
    }

    static String text(TextInputEditText et) {
        return et.getText() == null ? "" : et.getText().toString().trim();
    }
}
