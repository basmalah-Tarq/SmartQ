package com.example.smartquser;

import android.content.Intent;
import android.os.Bundle;
import android.view.View;
import android.widget.TextView;

import androidx.recyclerview.widget.GridLayoutManager;
import androidx.recyclerview.widget.RecyclerView;
import androidx.swiperefreshlayout.widget.SwipeRefreshLayout;

import com.example.smartquser.adapters.AppointmentsAdapter;
import com.example.smartquser.models.Appointment;
import com.example.smartquser.net.ApiClient;
import com.example.smartquser.net.ApiError;
import com.example.smartquser.net.ApiResponse;
import com.example.smartquser.utils.DateUtils;
import com.example.smartquser.utils.Lang;
import com.example.smartquser.utils.Ui;
import com.google.android.material.dialog.MaterialAlertDialogBuilder;
import com.google.android.material.tabs.TabLayout;

import org.json.JSONArray;
import org.json.JSONObject;

import java.util.ArrayList;
import java.util.Collections;
import java.util.List;

public class AppointmentsActivity extends BaseActivity {

    public static final String EXTRA_TAB = "tab";

    private SwipeRefreshLayout swipe;
    private RecyclerView recycler;
    private View skeleton, stateScroll;
    private TabLayout tabs;
    private AppointmentsAdapter adapter;
    private final List<Appointment> upcoming = new ArrayList<>();
    private final List<Appointment> past = new ArrayList<>();
    private boolean loaded;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        if (!requireLogin()) return;
        setContentView(R.layout.activity_appointments);
        Ui.padTop(findViewById(R.id.top));
        setupBottomNav(R.id.nav_bookings);

        swipe = findViewById(R.id.swipe);
        recycler = findViewById(R.id.recycler);
        skeleton = findViewById(R.id.skeleton);
        stateScroll = findViewById(R.id.stateScroll);
        tabs = findViewById(R.id.tabs);

        int widthDp = (int) (getResources().getDisplayMetrics().widthPixels / getResources().getDisplayMetrics().density);
        recycler.setLayoutManager(new GridLayoutManager(this, Math.max(1, Math.min(2, widthDp / 420))));
        adapter = new AppointmentsAdapter(this::confirmCancel);
        recycler.setAdapter(adapter);

        tabs.addTab(tabs.newTab().setText(R.string.appt_tab_upcoming));
        tabs.addTab(tabs.newTab().setText(R.string.appt_tab_past));
        tabs.addOnTabSelectedListener(new TabLayout.OnTabSelectedListener() {
            public void onTabSelected(TabLayout.Tab tab) {
                render(true);
            }
            public void onTabUnselected(TabLayout.Tab tab) {}
            public void onTabReselected(TabLayout.Tab tab) {}
        });
        selectTab(getIntent().getIntExtra(EXTRA_TAB, 0));

        swipe.setColorSchemeResources(R.color.blue_600, R.color.teal_500);
        swipe.setProgressBackgroundColorSchemeResource(R.color.surface);
        swipe.setOnRefreshListener(this::load);
        Ui.enterAll(findViewById(R.id.title), findViewById(R.id.subtitle), tabs);
        load();
    }

    @Override
    protected void onNewIntent(Intent intent) {
        super.onNewIntent(intent);
        if (intent.hasExtra(EXTRA_TAB)) selectTab(intent.getIntExtra(EXTRA_TAB, 0));
        load();
    }

    @Override
    protected void onTabReselected() {
        recycler.smoothScrollToPosition(0);
    }

    private void selectTab(int index) {
        TabLayout.Tab t = tabs.getTabAt(Math.max(0, Math.min(1, index)));
        if (t != null) t.select();
    }

    private void load() {
        stateScroll.setVisibility(View.GONE);
        if (!loaded && !swipe.isRefreshing()) skeleton.setVisibility(View.VISIBLE);
        ApiClient.get(this, "appointments.php", null, new ApiClient.Callback() {
            @Override
            public void onSuccess(ApiResponse r) {
                swipe.setRefreshing(false);
                skeleton.setVisibility(View.GONE);
                upcoming.clear();
                past.clear();
                JSONArray arr = r.data.optJSONArray("appointments");
                if (arr != null) {
                    for (int i = 0; i < arr.length(); i++) {
                        JSONObject o = arr.optJSONObject(i);
                        if (o == null) continue;
                        Appointment a = Appointment.fromJson(o);
                        if (a.isUpcoming()) upcoming.add(a); else past.add(a);
                    }
                }
                Collections.reverse(upcoming); // server sorts newest first; show the soonest first
                boolean first = !loaded;
                loaded = true;
                updateTabTitles();
                render(first);
            }

            @Override
            public void onError(ApiError e) {
                swipe.setRefreshing(false);
                skeleton.setVisibility(View.GONE);
                if (loaded) {
                    Ui.snack(AppointmentsActivity.this, e.message);
                } else {
                    adapter.submit(new ArrayList<>());
                    stateScroll.setVisibility(View.VISIBLE);
                    showError(findViewById(R.id.stateView), e, AppointmentsActivity.this::load);
                }
            }
        });
    }

    private void updateTabTitles() {
        TabLayout.Tab t0 = tabs.getTabAt(0), t1 = tabs.getTabAt(1);
        if (t0 != null) t0.setText(tabTitle(R.string.appt_tab_upcoming, upcoming.size()));
        if (t1 != null) t1.setText(tabTitle(R.string.appt_tab_past, past.size()));
        ((TextView) findViewById(R.id.subtitle)).setText(upcoming.isEmpty()
                ? getString(R.string.appt_none_upcoming)
                : getResources().getQuantityString(R.plurals.appt_upcoming_count, upcoming.size(),
                        Lang.num(upcoming.size())));
    }

    private String tabTitle(int label, int count) {
        return count == 0 ? getString(label) : getString(R.string.appt_tab_count, getString(label), Lang.num(count));
    }

    private void render(boolean animate) {
        if (!loaded) return;
        boolean showUpcoming = tabs.getSelectedTabPosition() == 0;
        List<Appointment> list = showUpcoming ? upcoming : past;
        adapter.submit(list);
        if (animate) recycler.scheduleLayoutAnimation();
        if (list.isEmpty()) {
            stateScroll.setVisibility(View.VISIBLE);
            if (showUpcoming) {
                showState(findViewById(R.id.stateView), R.drawable.ic_ticket, getString(R.string.appt_empty_upcoming_title),
                        getString(R.string.appt_empty_upcoming_msg), getString(R.string.appt_find_place),
                        () -> openTab(PlacesActivity.class));
            } else {
                showState(findViewById(R.id.stateView), R.drawable.ic_history, getString(R.string.appt_empty_past_title),
                        getString(R.string.appt_empty_past_msg), null, null);
            }
        } else {
            stateScroll.setVisibility(View.GONE);
        }
    }

    private void confirmCancel(Appointment a) {
        new MaterialAlertDialogBuilder(this)
                .setTitle(R.string.appt_cancel_title)
                .setMessage(getString(R.string.appt_cancel_msg, Lang.ltr(a.queueCode), a.placeName,
                        DateUtils.longDate(a.date), Lang.ltr(AppointmentsAdapter.timeOf(a))))
                .setNegativeButton(R.string.appt_keep, null)
                .setPositiveButton(R.string.appt_cancel_booking, (d, w) -> cancel(a))
                .show();
    }

    private void cancel(Appointment a) {
        swipe.setRefreshing(true);
        ApiClient.post(this, "cancel_appointment.php",
                ApiClient.params("appointment_id", String.valueOf(a.id), "status", "cancelled"),
                new ApiClient.Callback() {
                    @Override
                    public void onSuccess(ApiResponse r) {
                        Ui.snack(AppointmentsActivity.this, getString(R.string.appt_cancelled, Lang.ltr(a.queueCode)));
                        load();
                    }

                    @Override
                    public void onError(ApiError e) {
                        swipe.setRefreshing(false);
                        new MaterialAlertDialogBuilder(AppointmentsActivity.this)
                                .setTitle(R.string.appt_cancel_failed)
                                .setMessage(e.message)
                                .setPositiveButton(R.string.action_ok, null)
                                .show();
                        load();
                    }
                });
    }
}
