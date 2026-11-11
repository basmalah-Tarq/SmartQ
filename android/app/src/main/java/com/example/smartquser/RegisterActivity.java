package com.example.smartquser;

import android.content.Intent;
import android.os.Bundle;
import android.util.Patterns;
import android.view.View;
import android.widget.TextView;

import com.example.smartquser.net.ApiClient;
import com.example.smartquser.net.ApiError;
import com.example.smartquser.net.ApiResponse;
import com.example.smartquser.utils.DateUtils;
import com.example.smartquser.utils.Ui;
import com.google.android.material.button.MaterialButton;
import com.google.android.material.datepicker.CalendarConstraints;
import com.google.android.material.datepicker.DateValidatorPointBackward;
import com.google.android.material.datepicker.MaterialDatePicker;
import com.google.android.material.textfield.TextInputEditText;
import com.google.android.material.textfield.TextInputLayout;

import java.util.Calendar;
import java.util.TimeZone;

public class RegisterActivity extends BaseActivity {

    private TextInputLayout tilName, tilEmail, tilPassword, tilConfirm;
    private TextInputEditText etName, etEmail, etPhone, etBirth, etPassword, etConfirm;
    private MaterialButton btnRegister;
    private View progress;
    private TextView tvError;
    private String birthApi = "";

    @Override
    protected boolean darkStatusBar() {
        return true;
    }

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_register);
        Ui.padTop(findViewById(R.id.header));
        Ui.padBottom(findViewById(R.id.scroll));

        tilName = findViewById(R.id.tilName);
        tilEmail = findViewById(R.id.tilEmail);
        tilPassword = findViewById(R.id.tilPassword);
        tilConfirm = findViewById(R.id.tilConfirm);
        etName = findViewById(R.id.etName);
        etEmail = findViewById(R.id.etEmail);
        etPhone = findViewById(R.id.etPhone);
        etBirth = findViewById(R.id.etBirth);
        etPassword = findViewById(R.id.etPassword);
        etConfirm = findViewById(R.id.etConfirm);
        btnRegister = findViewById(R.id.btnRegister);
        progress = findViewById(R.id.progress);
        tvError = findViewById(R.id.tvError);

        findViewById(R.id.btnBack).setOnClickListener(v -> finish());
        findViewById(R.id.btnGoLogin).setOnClickListener(v -> finish());
        etBirth.setOnClickListener(v -> pickBirthDate());
        btnRegister.setOnClickListener(v -> attemptRegister());

        Ui.enterAll(findViewById(R.id.title), findViewById(R.id.subtitle), findViewById(R.id.formCard),
                findViewById(R.id.footer));
    }

    private void pickBirthDate() {
        Calendar def = Calendar.getInstance(TimeZone.getTimeZone("UTC"));
        def.add(Calendar.YEAR, -20);
        MaterialDatePicker<Long> picker = MaterialDatePicker.Builder.datePicker()
                .setTitleText(getString(R.string.auth_birth_date))
                .setSelection(def.getTimeInMillis())
                .setCalendarConstraints(new CalendarConstraints.Builder()
                        .setValidator(DateValidatorPointBackward.now()).build())
                .build();
        picker.addOnPositiveButtonClickListener(sel -> {
            Calendar c = Calendar.getInstance(TimeZone.getTimeZone("UTC"));
            c.setTimeInMillis(sel);
            birthApi = DateUtils.toApiDate(c.get(Calendar.YEAR), c.get(Calendar.MONTH), c.get(Calendar.DAY_OF_MONTH));
            etBirth.setText(DateUtils.mediumDate(birthApi));
        });
        picker.show(getSupportFragmentManager(), "birth");
    }

    private void attemptRegister() {
        tilName.setError(null);
        tilEmail.setError(null);
        tilPassword.setError(null);
        tilConfirm.setError(null);
        tvError.setVisibility(View.GONE);

        String name = LoginActivity.text(etName);
        String email = LoginActivity.text(etEmail);
        String phone = LoginActivity.text(etPhone);
        String pass = etPassword.getText() == null ? "" : etPassword.getText().toString();
        String confirm = etConfirm.getText() == null ? "" : etConfirm.getText().toString();

        boolean ok = true;
        if (name.length() < 2) { tilName.setError(getString(R.string.auth_enter_full_name)); ok = false; }
        if (!Patterns.EMAIL_ADDRESS.matcher(email).matches()) { tilEmail.setError(getString(R.string.auth_invalid_email)); ok = false; }
        if (pass.length() < 6) { tilPassword.setError(getString(R.string.auth_min_6)); ok = false; }
        if (!pass.equals(confirm)) { tilConfirm.setError(getString(R.string.auth_passwords_mismatch)); ok = false; }
        if (!ok) {
            Ui.shake(findViewById(R.id.formCard));
            return;
        }

        setLoading(true);
        ApiClient.post(this, "register.php", ApiClient.params(
                "full_name", name, "email", email, "phone", phone,
                "birth_date", birthApi, "password", pass), new ApiClient.Callback() {
            @Override
            public void onSuccess(ApiResponse r) {
                if (!saveAuth(r)) {
                    setLoading(false);
                    tvError.setText(R.string.auth_created_login);
                    tvError.setVisibility(View.VISIBLE);
                    return;
                }
                finishSignedIn(true);
            }

            @Override
            public void onError(ApiError e) {
                setLoading(false);
                tvError.setText(e.message);
                tvError.setVisibility(View.VISIBLE);
                Ui.shake(tvError);
            }
        });
    }

    private void setLoading(boolean loading) {
        btnRegister.setEnabled(!loading);
        btnRegister.setText(loading ? "" : getString(R.string.auth_create_account));
        progress.setVisibility(loading ? View.VISIBLE : View.GONE);
    }
}
