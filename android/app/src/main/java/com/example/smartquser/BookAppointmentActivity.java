package com.example.smartquser;

import android.content.Intent;
import android.os.Bundle;
import android.util.Patterns;
import android.view.LayoutInflater;
import android.view.View;
import android.widget.GridLayout;
import android.widget.TextView;

import androidx.recyclerview.widget.GridLayoutManager;
import androidx.recyclerview.widget.RecyclerView;

import com.example.smartquser.adapters.SlotAdapter;
import com.example.smartquser.models.Slot;
import com.example.smartquser.net.ApiClient;
import com.example.smartquser.net.ApiError;
import com.example.smartquser.net.ApiResponse;
import com.example.smartquser.utils.DateUtils;
import com.example.smartquser.utils.ImageLoader;
import com.example.smartquser.utils.Lang;
import com.example.smartquser.utils.Ui;
import com.google.android.material.button.MaterialButton;
import com.google.android.material.dialog.MaterialAlertDialogBuilder;
import com.google.android.material.textfield.TextInputEditText;
import com.google.android.material.textfield.TextInputLayout;

import org.json.JSONArray;
import org.json.JSONObject;

import java.util.ArrayList;
import java.util.List;
import java.util.Map;

/** Step 2: pick a time slot (slots.php), fill details, confirm (book_appointment.php). */
public class BookAppointmentActivity extends BaseActivity {

    public static final String EXTRA_SCHEDULE_ID = "schedule_id";
    public static final String EXTRA_PLACE_NAME = "place_name";
    public static final String EXTRA_PLACE_IMAGE = "place_image";
    public static final String EXTRA_PLACE_LOCATION = "place_location";

    private int scheduleId;
    private String placeName, placeImage, scheduleDate = "", startTime = "", endTime = "";
    private SlotAdapter adapter;
    private RecyclerView recycler;
    private View skeleton, stateView, detailsForm;
    private MaterialButton btnConfirm;
    private View progress;
    private TextView tvSelected, tvFree;
    private TextInputLayout tilName, tilEmail;
    private TextInputEditText etName, etPhone, etEmail, etAge, etNotes;
    private boolean booking;
    /** "Help me choose" pick from slots.php (server heuristic, same as the website); may be empty. */
    private String suggestedTime = "";
    private MaterialButton btnSuggest;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        if (!requireEntry()) return;
        setContentView(R.layout.activity_book_appointment);
        Ui.padTop(findViewById(R.id.topBar));
        Ui.padBottom(findViewById(R.id.bottomBar));

        scheduleId = getIntent().getIntExtra(EXTRA_SCHEDULE_ID, 0);
        placeName = getIntent().getStringExtra(EXTRA_PLACE_NAME);
        placeImage = getIntent().getStringExtra(EXTRA_PLACE_IMAGE);
        if (placeName == null) placeName = "";

        recycler = findViewById(R.id.recycler);
        skeleton = findViewById(R.id.skeleton);
        stateView = findViewById(R.id.stateView);
        detailsForm = findViewById(R.id.detailsForm);
        btnConfirm = findViewById(R.id.btnConfirm);
        progress = findViewById(R.id.progress);
        tvSelected = findViewById(R.id.tvSelected);
        tvFree = findViewById(R.id.tvFree);
        tilName = findViewById(R.id.tilName);
        tilEmail = findViewById(R.id.tilEmail);
        etName = findViewById(R.id.etName);
        etPhone = findViewById(R.id.etPhone);
        etEmail = findViewById(R.id.etEmail);
        etAge = findViewById(R.id.etAge);
        etNotes = findViewById(R.id.etNotes);

        findViewById(R.id.btnBack).setOnClickListener(v -> finish());
        ((TextView) findViewById(R.id.tvPlace)).setText(placeName);
        ImageLoader.load(findViewById(R.id.ivPlace), placeImage, R.drawable.place_placeholder);

        int widthDp = (int) (getResources().getDisplayMetrics().widthPixels / getResources().getDisplayMetrics().density);
        int span = Math.max(3, Math.min(6, (widthDp - 28) / 108));
        recycler.setLayoutManager(new GridLayoutManager(this, span));
        adapter = new SlotAdapter(this::onSlotSelected);
        recycler.setAdapter(adapter);
        buildSkeleton(span);

        // details prefilled from the account (the server also defaults to it)
        prefillFromAccount();
        findViewById(R.id.detailsToggle).setOnClickListener(v -> toggleDetails());
        findViewById(R.id.ivToggle).setRotation(chevronRotation(false));

        btnConfirm.setOnClickListener(v -> confirm());
        btnSuggest = findViewById(R.id.btnSuggest);
        btnSuggest.setOnClickListener(v -> suggest());
        btnConfirm.setText(confirmLabel());

        Ui.enterAll(findViewById(R.id.summaryCard), findViewById(R.id.slotsHeader), findViewById(R.id.legend));
        loadSlots();
    }

    private void buildSkeleton(int span) {
        GridLayout grid = findViewById(R.id.skeletonGrid);
        grid.setColumnCount(span);
        LayoutInflater inf = LayoutInflater.from(this);
        for (int i = 0; i < span * 3; i++) {
            View v = new View(this);
            v.setBackgroundResource(R.drawable.bg_skeleton);
            GridLayout.LayoutParams lp = new GridLayout.LayoutParams(
                    GridLayout.spec(GridLayout.UNDEFINED, 1f), GridLayout.spec(GridLayout.UNDEFINED, 1f));
            lp.width = 0;
            lp.height = Ui.dp(this, 56);
            int m = Ui.dp(this, 5);
            lp.setMargins(m, m, m, m);
            grid.addView(v, lp);
        }
    }

    private void toggleDetails() {
        boolean show = detailsForm.getVisibility() != View.VISIBLE;
        findViewById(R.id.ivToggle).animate().rotation(chevronRotation(show)).setDuration(250).start();
        if (show) {
            detailsForm.setVisibility(View.VISIBLE);
            Ui.enter(detailsForm, 0);
        } else {
            detailsForm.setVisibility(View.GONE);
            updateDetailsSummary();
        }
    }

    /** The chevron is auto-mirrored in RTL, so "down" / "up" need opposite rotations there. */
    private float chevronRotation(boolean open) {
        boolean rtl = getResources().getConfiguration().getLayoutDirection() == View.LAYOUT_DIRECTION_RTL;
        if (open) return rtl ? 90 : 270;
        return rtl ? 270 : 90;
    }

    /** Slot time in the app language ("9:30 AM" / "9:30 ص"); falls back to the server label. */
    static String slotLabel(Slot s) {
        String l = DateUtils.timeLabel(s.time);
        return l.isEmpty() ? s.label : l;
    }

    private void updateDetailsSummary() {
        String s = LoginActivity.text(etName);
        String p = LoginActivity.text(etPhone);
        ((TextView) findViewById(R.id.tvDetailsSummary)).setText(
                (s.isEmpty() ? getString(R.string.book_add_name) : s) + (p.isEmpty() ? "" : " · " + Lang.ltr(p)));
    }

    private void loadSlots() {
        stateView.setVisibility(View.GONE);
        if (adapter.getItemCount() == 0) skeleton.setVisibility(View.VISIBLE);
        ApiClient.get(this, "slots.php", ApiClient.params("schedule_id", String.valueOf(scheduleId)),
                new ApiClient.Callback() {
                    @Override
                    public void onSuccess(ApiResponse r) {
                        skeleton.setVisibility(View.GONE);
                        JSONObject sch = r.data.optJSONObject("schedule");
                        if (sch != null) {
                            scheduleDate = sch.optString("schedule_date", "");
                            startTime = sch.optString("start_time", "");
                            endTime = sch.optString("end_time", "");
                            String pn = sch.optString("place_name", "");
                            if (placeName.isEmpty() && !pn.isEmpty()) {
                                placeName = pn;
                                ((TextView) findViewById(R.id.tvPlace)).setText(pn);
                            }
                            ((TextView) findViewById(R.id.tvDate)).setText(DateUtils.longDate(scheduleDate));
                            ((TextView) findViewById(R.id.tvHours)).setText(
                                    getString(R.string.book_open_hours, DateUtils.timeRange(startTime, endTime),
                                            Lang.num(sch.optInt("slot_duration", 30))));
                        }
                        JSONArray arr = r.data.optJSONArray("slots");
                        List<Slot> slots = new ArrayList<>();
                        if (arr != null) {
                            for (int i = 0; i < arr.length(); i++) {
                                JSONObject o = arr.optJSONObject(i);
                                if (o != null) slots.add(Slot.fromJson(o));
                            }
                        }
                        boolean first = adapter.getItemCount() == 0;
                        adapter.submit(slots);
                        if (first) recycler.scheduleLayoutAnimation();
                        int free = r.data.optInt("free_slots", 0);
                        // null / missing on older servers -> falls back to the first free slot
                        suggestedTime = r.data.isNull("suggested_time") ? "" : r.data.optString("suggested_time", "");
                        btnSuggest.setEnabled(free > 0);
                        tvFree.setText(getResources().getQuantityString(R.plurals.book_free_slots, free, Lang.num(free)));
                        tvFree.setTextColor(getColor(free > 0 ? R.color.teal_500 : R.color.danger));
                        onSlotSelected(adapter.getSelected());
                        if (free == 0) {
                            showState(stateView, R.drawable.ic_event_busy, getString(R.string.book_day_full_title),
                                    getString(R.string.book_day_full_msg),
                                    getString(R.string.book_choose_another_day), () -> finish());
                        }
                    }

                    @Override
                    public void onError(ApiError e) {
                        skeleton.setVisibility(View.GONE);
                        if (adapter.getItemCount() > 0) Ui.snack(BookAppointmentActivity.this, e.message);
                        else showError(stateView, e, BookAppointmentActivity.this::loadSlots);
                    }
                });
    }

    /** Selects the server's suggested slot (earliest calm time >= 15 min from now) and scrolls to it. */
    private void suggest() {
        String time = suggestedTime.isEmpty() ? adapter.firstAvailableTime() : suggestedTime;
        int pos = time == null ? -1 : adapter.select(time);
        if (pos < 0 && time != null && !time.equals(adapter.firstAvailableTime())) {
            pos = adapter.select(adapter.firstAvailableTime());
        }
        if (pos < 0) {
            Ui.snack(this, getString(R.string.book_no_free_left));
            return;
        }
        recycler.smoothScrollToPosition(pos);
        Slot s = adapter.getSelected();
        if (s != null) Ui.snack(this, getString(R.string.book_picked_for_you, Lang.ltr(slotLabel(s))));
    }

    private void onSlotSelected(Slot s) {
        if (s == null) {
            tvSelected.setText(R.string.book_pick_time);
            ((TextView) findViewById(R.id.tvSelectedDate)).setText(R.string.book_tap_free_time);
            btnConfirm.setEnabled(false);
            return;
        }
        tvSelected.setText(slotLabel(s));
        ((TextView) findViewById(R.id.tvSelectedDate)).setText(DateUtils.friendlyDate(scheduleDate) + " · " + placeName);
        Ui.pop(tvSelected);
        btnConfirm.setEnabled(!booking);
    }

    private void confirm() {
        Slot slot = adapter.getSelected();
        if (slot == null) {
            Ui.shake(findViewById(R.id.slotsHeader));
            return;
        }
        if (!session.isLoggedIn()) {
            // guest: sign in first, then come straight back to this confirmation
            requireAccount(getString(R.string.book_sign_in_for, Lang.ltr(slotLabel(slot))),
                    getString(R.string.book_sign_in_msg), this::confirm);
            return;
        }
        String name = LoginActivity.text(etName);
        String email = LoginActivity.text(etEmail);
        tilName.setError(null);
        tilEmail.setError(null);
        boolean ok = true;
        if (name.isEmpty()) { tilName.setError(getString(R.string.book_name_required)); ok = false; }
        if (!Patterns.EMAIL_ADDRESS.matcher(email).matches()) { tilEmail.setError(getString(R.string.auth_invalid_email)); ok = false; }
        if (!ok) {
            if (detailsForm.getVisibility() != View.VISIBLE) toggleDetails();
            Ui.shake(findViewById(R.id.detailsCard));
            return;
        }

        new MaterialAlertDialogBuilder(this)
                .setTitle(R.string.book_confirm_title)
                .setMessage(getString(R.string.book_confirm_msg, placeName, DateUtils.longDate(scheduleDate),
                        Lang.ltr(slotLabel(slot))))
                .setNegativeButton(R.string.action_back, null)
                .setPositiveButton(R.string.book_confirm_booking, (d, w) -> book(slot, name, email))
                .show();
    }

    private void book(Slot slot, String name, String email) {
        setBooking(true);
        Map<String, String> p = ApiClient.params(
                "schedule_id", String.valueOf(scheduleId),
                "appointment_time", slot.time,
                "customer_name", name,
                "customer_phone", LoginActivity.text(etPhone),
                "customer_email", email,
                "customer_age", LoginActivity.text(etAge),
                "notes", LoginActivity.text(etNotes));
        ApiClient.post(this, "book_appointment.php", p, new ApiClient.Callback() {
            @Override
            public void onSuccess(ApiResponse r) {
                Intent i = new Intent(BookAppointmentActivity.this, BookingSuccessActivity.class);
                i.putExtra(BookingSuccessActivity.EXTRA_DATA, r.data.toString());
                i.putExtra(BookingSuccessActivity.EXTRA_PLACE_IMAGE, placeImage);
                i.putExtra(BookingSuccessActivity.EXTRA_PLACE_NAME, placeName);
                startActivity(i);
                finish();
            }

            @Override
            public void onError(ApiError e) {
                setBooking(false);
                if (e.kind == ApiError.Kind.API) {
                    // e.g. 409 "This time was just booked by someone else..." — show as-is, refresh the grid
                    new MaterialAlertDialogBuilder(BookAppointmentActivity.this)
                            .setTitle(R.string.book_failed_title)
                            .setMessage(e.message)
                            .setPositiveButton(R.string.book_pick_another, null)
                            .show();
                    adapter.clearSelection();
                    onSlotSelected(null);
                    loadSlots();
                } else {
                    Ui.snack(BookAppointmentActivity.this, e.message);
                }
            }
        });
    }

    /** Fills empty detail fields from the signed-in account (guests start with an empty form). */
    private void prefillFromAccount() {
        if (LoginActivity.text(etName).isEmpty()) etName.setText(session.getFullName());
        if (LoginActivity.text(etPhone).isEmpty()) etPhone.setText(session.getPhone());
        if (LoginActivity.text(etEmail).isEmpty()) etEmail.setText(session.getEmail());
        updateDetailsSummary();
    }

    @Override
    protected void onSignedIn() {
        prefillFromAccount();
        btnConfirm.setText(confirmLabel());
    }

    private String confirmLabel() {
        return getString(session.isLoggedIn() ? R.string.book_confirm_booking : R.string.gate_book_title);
    }

    private void setBooking(boolean b) {
        booking = b;
        btnConfirm.setEnabled(!b && adapter.getSelected() != null);
        btnConfirm.setText(b ? "" : confirmLabel());
        progress.setVisibility(b ? View.VISIBLE : View.GONE);
    }
}
