package com.example.smartquser;

import android.content.Intent;
import android.graphics.Bitmap;
import android.graphics.BitmapFactory;
import android.graphics.ImageDecoder;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.util.Patterns;
import android.view.View;
import android.widget.ImageView;
import android.widget.TextView;

import androidx.activity.result.ActivityResultLauncher;
import androidx.activity.result.contract.ActivityResultContracts;
import androidx.swiperefreshlayout.widget.SwipeRefreshLayout;

import com.example.smartquser.net.ApiClient;
import com.example.smartquser.net.ApiError;
import com.example.smartquser.net.ApiResponse;
import com.example.smartquser.utils.DateUtils;
import com.example.smartquser.utils.ImageLoader;
import com.example.smartquser.utils.Lang;
import com.example.smartquser.net.ServerMessages;
import com.example.smartquser.ui.SettingsDialogs;
import com.example.smartquser.utils.Ui;
import com.google.android.material.button.MaterialButton;
import com.google.android.material.datepicker.CalendarConstraints;
import com.google.android.material.datepicker.DateValidatorPointBackward;
import com.google.android.material.datepicker.MaterialDatePicker;
import com.google.android.material.dialog.MaterialAlertDialogBuilder;
import com.google.android.material.textfield.TextInputEditText;
import com.google.android.material.textfield.TextInputLayout;

import java.io.ByteArrayOutputStream;
import java.io.InputStream;
import java.util.Calendar;
import java.util.Date;
import java.util.TimeZone;

public class ProfileActivity extends BaseActivity {

    private SwipeRefreshLayout swipe;
    private TextInputLayout tilName, tilEmail;
    private TextInputEditText etName, etEmail, etPhone, etBirth;
    private MaterialButton btnSave;
    private View saveProgress, uploadProgress;
    private String birthApi = "";
    private ActivityResultLauncher<String> pickImage;

    @Override
    protected boolean darkStatusBar() {
        return true;
    }

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        if (!requireLogin()) return;
        setContentView(R.layout.activity_profile);
        Ui.padTop(findViewById(R.id.header));
        setupBottomNav(R.id.nav_profile);

        swipe = findViewById(R.id.swipe);
        tilName = findViewById(R.id.tilName);
        tilEmail = findViewById(R.id.tilEmail);
        etName = findViewById(R.id.etName);
        etEmail = findViewById(R.id.etEmail);
        etPhone = findViewById(R.id.etPhone);
        etBirth = findViewById(R.id.etBirth);
        btnSave = findViewById(R.id.btnSave);
        saveProgress = findViewById(R.id.saveProgress);
        uploadProgress = findViewById(R.id.uploadProgress);

        swipe.setColorSchemeResources(R.color.blue_600, R.color.teal_500);
        swipe.setProgressBackgroundColorSchemeResource(R.color.surface);
        swipe.setProgressViewOffset(false, Ui.dp(this, 40), Ui.dp(this, 110));
        swipe.setOnRefreshListener(this::loadProfile);

        pickImage = registerForActivityResult(new ActivityResultContracts.GetContent(), uri -> {
            if (uri != null) uploadImage(uri);
        });
        findViewById(R.id.btnPhoto).setOnClickListener(v -> pickImage.launch("image/*"));
        findViewById(R.id.avatarWrap).setOnClickListener(v -> pickImage.launch("image/*"));
        etBirth.setOnClickListener(v -> pickBirthDate());
        tilBirthEndIcon();
        btnSave.setOnClickListener(v -> save());
        setupRows();
        ((TextView) findViewById(R.id.tvVersion)).setText(getString(R.string.profile_version,
                Lang.ltr(BuildConfig.VERSION_NAME), Lang.ltr(Uri.parse(ApiClient.BASE_URL).getHost())));

        bindFromSession();
        Ui.enterAll(findViewById(R.id.avatarWrap), findViewById(R.id.tvName), findViewById(R.id.tvEmail),
                findViewById(R.id.formCard), findViewById(R.id.accountCard));
        loadProfile();
    }

    /** Settings (language, appearance) and account rows: icon tile, title, current value, chevron. */
    private void setupRows() {
        row(R.id.rowLanguage, R.drawable.ic_language, R.color.accent, R.color.blue_tint,
                getString(R.string.settings_language), SettingsDialogs.languageLabel(this),
                v -> SettingsDialogs.pickLanguage(this, this::refreshSettingRows));
        row(R.id.rowTheme, R.drawable.ic_contrast, R.color.teal_500, R.color.teal_tint,
                getString(R.string.settings_theme), SettingsDialogs.themeLabel(this),
                v -> SettingsDialogs.pickTheme(this, this::refreshSettingRows));
        row(R.id.rowBookings, R.drawable.ic_ticket, R.color.accent, R.color.blue_tint,
                getString(R.string.profile_my_bookings), getString(R.string.profile_my_bookings_sub),
                v -> openTab(AppointmentsActivity.class));
        row(R.id.rowLogout, R.drawable.ic_logout, R.color.danger, R.color.danger_tint,
                getString(R.string.profile_log_out), null, v -> confirmLogout());
        TextView logoutTitle = findViewById(R.id.rowLogout).findViewById(R.id.rowTitle);
        logoutTitle.setTextColor(getColor(R.color.danger));
        findViewById(R.id.rowLogout).findViewById(R.id.rowChevron).setVisibility(View.GONE);
    }

    private void refreshSettingRows() {
        setRowValue(R.id.rowLanguage, SettingsDialogs.languageLabel(this));
        setRowValue(R.id.rowTheme, SettingsDialogs.themeLabel(this));
    }

    private void row(int id, int icon, int color, int tint, String title, String value, View.OnClickListener l) {
        View row = findViewById(id);
        ImageView iv = row.findViewById(R.id.rowIcon);
        iv.setImageResource(icon);
        Ui.tint(iv, color);
        row.findViewById(R.id.rowIconBg).setBackgroundTintList(
                androidx.core.content.ContextCompat.getColorStateList(this, tint));
        ((TextView) row.findViewById(R.id.rowTitle)).setText(title);
        setRowValue(id, value);
        row.setOnClickListener(l);
        row.setContentDescription(value == null ? title : title + ", " + value);
    }

    private void setRowValue(int id, String value) {
        TextView tv = findViewById(id).findViewById(R.id.rowValue);
        tv.setText(value);
        tv.setVisibility(value == null || value.isEmpty() ? View.GONE : View.VISIBLE);
    }

    private void tilBirthEndIcon() {
        TextInputLayout til = findViewById(R.id.tilBirth);
        til.setEndIconOnClickListener(v -> pickBirthDate());
    }

    private void bindFromSession() {
        ((TextView) findViewById(R.id.tvName)).setText(session.getFullName());
        ((TextView) findViewById(R.id.tvEmail)).setText(Lang.ltr(session.getEmail()));
        ((TextView) findViewById(R.id.tvInitials)).setText(session.getInitials());
        etName.setText(session.getFullName());
        etEmail.setText(session.getEmail());
        etPhone.setText(session.getPhone());
        birthApi = session.getBirthDate();
        etBirth.setText(birthApi.isEmpty() ? "" : DateUtils.mediumDate(birthApi));
        ImageView avatar = findViewById(R.id.ivAvatar);
        String url = session.getProfileImageUrl();
        if (url.isEmpty()) avatar.setImageDrawable(null);
        else ImageLoader.loadCircle(avatar, url, android.R.color.transparent);
    }

    private void loadProfile() {
        ApiClient.get(this, "profile.php", null, new ApiClient.Callback() {
            @Override
            public void onSuccess(ApiResponse r) {
                swipe.setRefreshing(false);
                session.saveUser(r.data.optJSONObject("user"), null);
                bindFromSession();
            }

            @Override
            public void onError(ApiError e) {
                swipe.setRefreshing(false);
                Ui.snack(ProfileActivity.this, e.message);
            }
        });
    }

    private void pickBirthDate() {
        Calendar def = Calendar.getInstance(TimeZone.getTimeZone("UTC"));
        Date current = DateUtils.parseDate(birthApi);
        if (current != null) {
            Calendar local = Calendar.getInstance();
            local.setTime(current);
            def.clear();
            def.set(local.get(Calendar.YEAR), local.get(Calendar.MONTH), local.get(Calendar.DAY_OF_MONTH));
        } else {
            def.add(Calendar.YEAR, -20);
        }
        MaterialDatePicker<Long> picker = MaterialDatePicker.Builder.datePicker()
                .setTitleText(getString(R.string.profile_birth_date))
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

    private void save() {
        tilName.setError(null);
        tilEmail.setError(null);
        String name = LoginActivity.text(etName);
        String email = LoginActivity.text(etEmail);
        boolean ok = true;
        if (name.length() < 2) { tilName.setError(getString(R.string.auth_enter_full_name)); ok = false; }
        if (!Patterns.EMAIL_ADDRESS.matcher(email).matches()) { tilEmail.setError(getString(R.string.auth_invalid_email)); ok = false; }
        if (!ok) {
            Ui.shake(findViewById(R.id.formCard));
            return;
        }
        setSaving(true);
        ApiClient.post(this, "update_profile.php", ApiClient.params(
                "full_name", name, "email", email, "phone", LoginActivity.text(etPhone), "birth_date", birthApi),
                new ApiClient.Callback() {
                    @Override
                    public void onSuccess(ApiResponse r) {
                        setSaving(false);
                        session.saveUser(r.data.optJSONObject("user"), null);
                        bindFromSession();
                        Ui.pop(findViewById(R.id.tvName));
                        Ui.snack(ProfileActivity.this, r.message == null || r.message.isEmpty()
                                ? getString(R.string.profile_saved) : ServerMessages.text(ProfileActivity.this, r.message));
                    }

                    @Override
                    public void onError(ApiError e) {
                        setSaving(false);
                        Ui.snack(ProfileActivity.this, e.message);
                    }
                });
    }

    private void setSaving(boolean saving) {
        btnSave.setEnabled(!saving);
        btnSave.setText(saving ? "" : getString(R.string.profile_save));
        btnSave.setIcon(saving ? null : getDrawable(R.drawable.ic_check));
        saveProgress.setVisibility(saving ? View.VISIBLE : View.GONE);
    }

    // ------------------------------------------------------------ photo

    private void uploadImage(Uri uri) {
        uploadProgress.setVisibility(View.VISIBLE);
        new Thread(() -> {
            byte[] jpeg = compress(uri);
            runOnUiThread(() -> {
                if (isFinishing() || isDestroyed()) return;
                if (jpeg == null) {
                    uploadProgress.setVisibility(View.GONE);
                    Ui.snack(this, getString(R.string.profile_image_read));
                    return;
                }
                ApiClient.upload(this, "upload_profile_image.php", "profile_image", jpeg,
                        "avatar.jpg", "image/jpeg", new ApiClient.Callback() {
                            @Override
                            public void onSuccess(ApiResponse r) {
                                uploadProgress.setVisibility(View.GONE);
                                session.saveProfileImageUrl(r.data.optString("profile_image_url", ""));
                                bindFromSession();
                                Ui.pop(findViewById(R.id.avatarWrap));
                                Ui.snack(ProfileActivity.this, getString(R.string.profile_photo_updated));
                            }

                            @Override
                            public void onError(ApiError e) {
                                uploadProgress.setVisibility(View.GONE);
                                Ui.snack(ProfileActivity.this, e.message);
                            }
                        });
            });
        }).start();
    }

    /** Downscale to max 1080px and encode as JPEG (well under the 5 MB server limit). */
    private byte[] compress(Uri uri) {
        try {
            Bitmap bmp;
            if (Build.VERSION.SDK_INT >= 28) {
                bmp = ImageDecoder.decodeBitmap(ImageDecoder.createSource(getContentResolver(), uri),
                        (decoder, info, src) -> {
                            int w = info.getSize().getWidth(), h = info.getSize().getHeight();
                            float scale = Math.min(1f, 1080f / Math.max(w, h));
                            decoder.setTargetSize(Math.max(1, Math.round(w * scale)), Math.max(1, Math.round(h * scale)));
                            decoder.setAllocator(ImageDecoder.ALLOCATOR_SOFTWARE);
                        });
            } else {
                BitmapFactory.Options o = new BitmapFactory.Options();
                o.inJustDecodeBounds = true;
                try (InputStream in = getContentResolver().openInputStream(uri)) {
                    BitmapFactory.decodeStream(in, null, o);
                }
                int sample = 1;
                while (Math.max(o.outWidth, o.outHeight) / (sample * 2) >= 1080) sample *= 2;
                o = new BitmapFactory.Options();
                o.inSampleSize = sample;
                try (InputStream in = getContentResolver().openInputStream(uri)) {
                    bmp = BitmapFactory.decodeStream(in, null, o);
                }
            }
            if (bmp == null) return null;
            ByteArrayOutputStream out = new ByteArrayOutputStream();
            bmp.compress(Bitmap.CompressFormat.JPEG, 86, out);
            return out.toByteArray();
        } catch (Exception | OutOfMemoryError e) {
            return null;
        }
    }

    // ------------------------------------------------------------ logout

    private void confirmLogout() {
        new MaterialAlertDialogBuilder(this)
                .setTitle(R.string.profile_logout_title)
                .setMessage(R.string.profile_logout_msg)
                .setNegativeButton(R.string.profile_stay, null)
                .setPositiveButton(R.string.profile_log_out, (d, w) -> logout())
                .show();
    }

    private void logout() {
        logoutToWelcome(); // revokes the token on the server (best effort) and returns to Welcome
    }
}
