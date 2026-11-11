package com.example.smartquser;

import android.content.Intent;
import android.os.Bundle;
import android.view.View;
import android.widget.ImageView;
import android.widget.TextView;

import androidx.recyclerview.widget.LinearLayoutManager;
import androidx.recyclerview.widget.RecyclerView;

import com.example.smartquser.adapters.ScheduleAdapter;
import com.example.smartquser.models.Place;
import com.example.smartquser.models.Schedule;
import com.example.smartquser.net.ApiClient;
import com.example.smartquser.net.ApiError;
import com.example.smartquser.net.ApiResponse;
import com.example.smartquser.utils.ImageLoader;
import com.example.smartquser.utils.Lang;
import com.example.smartquser.utils.Ui;
import com.google.android.material.appbar.CollapsingToolbarLayout;
import com.google.android.material.appbar.MaterialToolbar;

import org.json.JSONArray;
import org.json.JSONObject;

import java.util.ArrayList;
import java.util.List;

public class PlaceDetailsActivity extends BaseActivity {

    public static final String EXTRA_PLACE_ID = "place_id";
    public static final String EXTRA_PLACE_NAME = "place_name";
    public static final String EXTRA_PLACE_IMAGE = "place_image";

    private int placeId;
    private Place place;
    private ScheduleAdapter adapter;
    private View skeleton, stateView;
    private RecyclerView recycler;

    @Override
    protected boolean darkStatusBar() {
        return true;
    }

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        if (!requireEntry()) return;
        setContentView(R.layout.activity_place_details);
        Ui.padBottom(findViewById(R.id.scroll));

        placeId = getIntent().getIntExtra(EXTRA_PLACE_ID, 0);
        MaterialToolbar toolbar = findViewById(R.id.toolbar);
        toolbar.setNavigationOnClickListener(v -> finish());
        CollapsingToolbarLayout collapsing = findViewById(R.id.collapsing);
        collapsing.setTitle(getIntent().getStringExtra(EXTRA_PLACE_NAME));
        ImageLoader.load(findViewById(R.id.ivHero), getIntent().getStringExtra(EXTRA_PLACE_IMAGE), R.drawable.place_placeholder);

        skeleton = findViewById(R.id.skeleton);
        stateView = findViewById(R.id.stateView);
        recycler = findViewById(R.id.recycler);
        recycler.setLayoutManager(new LinearLayoutManager(this));
        adapter = new ScheduleAdapter(this::openBooking);
        recycler.setAdapter(adapter);

        findViewById(R.id.infoCard).setVisibility(View.INVISIBLE);
        load();
    }

    @Override
    protected void onRestart() {
        super.onRestart();
        load(); // seats may have changed after a booking
    }

    private void load() {
        stateView.setVisibility(View.GONE);
        if (adapter.getItemCount() == 0) skeleton.setVisibility(View.VISIBLE);
        ApiClient.get(this, "place_details.php", ApiClient.params("place_id", String.valueOf(placeId)),
                new ApiClient.Callback() {
                    @Override
                    public void onSuccess(ApiResponse r) {
                        skeleton.setVisibility(View.GONE);
                        JSONObject p = r.data.optJSONObject("place");
                        if (p != null) bindPlace(Place.fromJson(p));
                        JSONArray arr = r.data.optJSONArray("schedules");
                        List<Schedule> list = new ArrayList<>();
                        if (arr != null) {
                            for (int i = 0; i < arr.length(); i++) {
                                JSONObject o = arr.optJSONObject(i);
                                if (o != null) list.add(Schedule.fromJson(o));
                            }
                        }
                        boolean first = adapter.getItemCount() == 0;
                        adapter.submit(list);
                        if (first) recycler.scheduleLayoutAnimation();
                        TextView count = findViewById(R.id.tvScheduleCount);
                        count.setText(getResources().getQuantityString(R.plurals.details_days_count, list.size(), Lang.num(list.size())));
                        ((TextView) findViewById(R.id.tvOpenCount)).setText(list.isEmpty() ? R.string.details_no_open : R.string.details_open);
                        if (list.isEmpty()) {
                            showState(stateView, R.drawable.ic_event_busy, getString(R.string.details_none_title),
                                    getString(R.string.details_none_message),
                                    getString(R.string.details_browse_other), () -> finish());
                        }
                    }

                    @Override
                    public void onError(ApiError e) {
                        skeleton.setVisibility(View.GONE);
                        if (adapter.getItemCount() > 0) {
                            Ui.snack(PlaceDetailsActivity.this, e.message);
                        } else {
                            showError(stateView, e, PlaceDetailsActivity.this::load);
                        }
                    }
                });
    }

    private void bindPlace(Place p) {
        boolean first = place == null;
        place = p;
        ((CollapsingToolbarLayout) findViewById(R.id.collapsing)).setTitle(p.name);
        ((TextView) findViewById(R.id.tvCategory)).setText(p.category);
        ((ImageView) findViewById(R.id.ivCategory)).setImageResource(Ui.categoryIcon(p.category));
        findViewById(R.id.categoryPill).setVisibility(p.category.isEmpty() ? View.GONE : View.VISIBLE);
        ((TextView) findViewById(R.id.tvLocation)).setText(p.location());
        TextView desc = findViewById(R.id.tvDescription);
        desc.setText(p.description);
        desc.setVisibility(p.description.isEmpty() ? View.GONE : View.VISIBLE);
        if (first) {
            String passed = getIntent().getStringExtra(EXTRA_PLACE_IMAGE);
            if (passed == null || !passed.equals(p.imageUrl)) {
                ImageLoader.load(findViewById(R.id.ivHero), p.imageUrl, R.drawable.place_placeholder);
            }
            View card = findViewById(R.id.infoCard);
            card.setVisibility(View.VISIBLE);
            Ui.enter(card, 0);
            Ui.enter(findViewById(R.id.scheduleHeader), 1);
        }
    }

    private void openBooking(Schedule s) {
        Intent i = new Intent(this, BookAppointmentActivity.class);
        i.putExtra(BookAppointmentActivity.EXTRA_SCHEDULE_ID, s.id);
        i.putExtra(BookAppointmentActivity.EXTRA_PLACE_NAME, place != null ? place.name : getIntent().getStringExtra(EXTRA_PLACE_NAME));
        i.putExtra(BookAppointmentActivity.EXTRA_PLACE_IMAGE, place != null ? place.imageUrl : getIntent().getStringExtra(EXTRA_PLACE_IMAGE));
        i.putExtra(BookAppointmentActivity.EXTRA_PLACE_LOCATION, place != null ? place.location() : "");
        startActivity(i);
    }
}
