package com.example.smartquser;

import android.content.Intent;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.text.Editable;
import android.text.TextWatcher;
import android.view.View;
import android.view.inputmethod.EditorInfo;
import android.view.inputmethod.InputMethodManager;
import android.widget.TextView;

import androidx.recyclerview.widget.GridLayoutManager;
import androidx.recyclerview.widget.RecyclerView;
import androidx.swiperefreshlayout.widget.SwipeRefreshLayout;

import com.example.smartquser.adapters.PlacesAdapter;
import com.example.smartquser.models.Place;
import com.example.smartquser.net.ApiClient;
import com.example.smartquser.net.ApiError;
import com.example.smartquser.net.ApiResponse;
import com.example.smartquser.utils.Lang;
import com.example.smartquser.utils.Ui;
import com.google.android.material.chip.Chip;
import com.google.android.material.chip.ChipGroup;
import com.google.android.material.textfield.TextInputEditText;

import org.json.JSONArray;
import org.json.JSONObject;

import java.util.ArrayList;
import java.util.List;
import java.util.Map;

public class PlacesActivity extends BaseActivity {

    public static final String EXTRA_CATEGORY = "category";
    public static final String EXTRA_FOCUS_SEARCH = "focus_search";
    /** Tag of the "All" chip (an internal key, never shown). */
    private static final String ALL = "";

    private final Handler handler = new Handler(Looper.getMainLooper());
    private SwipeRefreshLayout swipe;
    private RecyclerView recycler;
    private View skeleton, stateScroll;
    private ChipGroup chips;
    private TextInputEditText etSearch;
    private TextView tvCount;
    private PlacesAdapter adapter;
    private ApiClient.Call currentCall;
    private String category = ALL;
    private boolean chipsBuilt;
    private final Runnable searchRunnable = this::load;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        if (!requireEntry()) return;
        setContentView(R.layout.activity_places);
        Ui.padTop(findViewById(R.id.top));
        setupBottomNav(R.id.nav_places);

        swipe = findViewById(R.id.swipe);
        recycler = findViewById(R.id.recycler);
        skeleton = findViewById(R.id.skeleton);
        stateScroll = findViewById(R.id.stateScroll);
        chips = findViewById(R.id.chips);
        etSearch = findViewById(R.id.etSearch);
        tvCount = findViewById(R.id.tvCount);

        int widthDp = (int) (getResources().getDisplayMetrics().widthPixels / getResources().getDisplayMetrics().density);
        int span = Math.max(1, Math.min(3, widthDp / 380));
        recycler.setLayoutManager(new GridLayoutManager(this, span));
        adapter = new PlacesAdapter((place, image) -> {
            Intent i = new Intent(this, PlaceDetailsActivity.class);
            i.putExtra(PlaceDetailsActivity.EXTRA_PLACE_ID, place.id);
            i.putExtra(PlaceDetailsActivity.EXTRA_PLACE_NAME, place.name);
            i.putExtra(PlaceDetailsActivity.EXTRA_PLACE_IMAGE, place.imageUrl);
            startActivity(i);
        });
        recycler.setAdapter(adapter);

        swipe.setColorSchemeResources(R.color.blue_600, R.color.teal_500);
        swipe.setProgressBackgroundColorSchemeResource(R.color.surface);
        swipe.setOnRefreshListener(this::load);

        etSearch.addTextChangedListener(new TextWatcher() {
            public void beforeTextChanged(CharSequence s, int a, int b, int c) {}
            public void onTextChanged(CharSequence s, int a, int b, int c) {}
            public void afterTextChanged(Editable s) {
                handler.removeCallbacks(searchRunnable);
                handler.postDelayed(searchRunnable, 400); // debounce
            }
        });
        etSearch.setOnEditorActionListener((v, actionId, e) -> {
            if (actionId == EditorInfo.IME_ACTION_SEARCH) {
                handler.removeCallbacks(searchRunnable);
                hideKeyboard();
                load();
                return true;
            }
            return false;
        });

        String initial = getIntent().getStringExtra(EXTRA_CATEGORY);
        if (initial != null && !initial.isEmpty()) category = initial;
        if (getIntent().getBooleanExtra(EXTRA_FOCUS_SEARCH, false)) {
            etSearch.requestFocus();
            etSearch.postDelayed(() -> {
                InputMethodManager imm = (InputMethodManager) getSystemService(INPUT_METHOD_SERVICE);
                if (imm != null) imm.showSoftInput(etSearch, InputMethodManager.SHOW_IMPLICIT);
            }, 350);
        }

        Ui.enterAll(findViewById(R.id.title), findViewById(R.id.subtitle), findViewById(R.id.searchCard));
        load();
    }

    @Override
    protected void onNewIntent(Intent intent) {
        super.onNewIntent(intent);
        String c = intent.getStringExtra(EXTRA_CATEGORY);
        if (c != null && !c.isEmpty() && !c.equals(category)) {
            category = c;
            selectChip(c);
            load();
        }
    }

    @Override
    protected void onTabReselected() {
        recycler.smoothScrollToPosition(0);
    }

    private void hideKeyboard() {
        InputMethodManager imm = (InputMethodManager) getSystemService(INPUT_METHOD_SERVICE);
        if (imm != null) imm.hideSoftInputFromWindow(etSearch.getWindowToken(), 0);
    }

    private void load() {
        if (currentCall != null) currentCall.cancel();
        if (!swipe.isRefreshing()) {
            if (adapter.getItemCount() == 0) skeleton.setVisibility(View.VISIBLE);
        }
        stateScroll.setVisibility(View.GONE);
        String search = LoginActivity.text(etSearch);
        Map<String, String> q = ApiClient.params("search", search, "category", ALL.equals(category) ? "" : category);
        currentCall = ApiClient.get(this, "places.php", q, new ApiClient.Callback() {
            @Override
            public void onSuccess(ApiResponse r) {
                swipe.setRefreshing(false);
                skeleton.setVisibility(View.GONE);
                buildChips(r.data.optJSONArray("categories"));
                JSONArray arr = r.data.optJSONArray("places");
                List<Place> list = new ArrayList<>();
                if (arr != null) {
                    for (int i = 0; i < arr.length(); i++) {
                        JSONObject o = arr.optJSONObject(i);
                        if (o != null) list.add(Place.fromJson(o));
                    }
                }
                adapter.submit(list);
                recycler.scheduleLayoutAnimation();
                tvCount.setText(getResources().getQuantityString(R.plurals.places_count, list.size(), Lang.num(list.size())));
                if (list.isEmpty()) {
                    stateScroll.setVisibility(View.VISIBLE);
                    boolean filtered = !search.isEmpty() || !ALL.equals(category);
                    showState(findViewById(R.id.stateView), R.drawable.ic_search,
                            getString(filtered ? R.string.places_no_matches : R.string.places_none),
                            getString(filtered ? R.string.places_no_matches_message : R.string.places_none_message),
                            filtered ? getString(R.string.places_clear_filters) : null, () -> {
                                etSearch.setText("");
                                category = ALL;
                                selectChip(ALL);
                                load();
                            });
                }
            }

            @Override
            public void onError(ApiError e) {
                swipe.setRefreshing(false);
                skeleton.setVisibility(View.GONE);
                if (adapter.getItemCount() > 0) {
                    Ui.snack(PlacesActivity.this, e.message);
                    return;
                }
                stateScroll.setVisibility(View.VISIBLE);
                showError(findViewById(R.id.stateView), e, PlacesActivity.this::load);
            }
        });
    }

    private void buildChips(JSONArray cats) {
        if (chipsBuilt || cats == null) return;
        chipsBuilt = true;
        List<String> names = new ArrayList<>();
        names.add(ALL);
        for (int i = 0; i < cats.length(); i++) {
            String c = cats.optString(i, "");
            if (!c.isEmpty()) names.add(c);
        }
        chips.removeAllViews();
        for (int i = 0; i < names.size(); i++) {
            String name = names.get(i);
            Chip chip = new Chip(this);
            chip.setId(View.generateViewId());
            chip.setText(ALL.equals(name) ? getString(R.string.places_all) : name);
            chip.setTag(name);
            chip.setCheckable(true);
            if (!ALL.equals(name)) {
                chip.setChipIconResource(Ui.categoryIcon(name));
                chip.setChipIconTint(androidx.core.content.ContextCompat.getColorStateList(this, R.color.chip_text));
                chip.setChipIconSize(Ui.dp(this, 20));
                chip.setIconStartPadding(Ui.dp(this, 2));
                chip.setTextStartPadding(Ui.dp(this, 6));
                chip.setChipIconVisible(true);
            }
            chips.addView(chip);
            Ui.enter(chip, i);
        }
        selectChip(category);
        DashboardActivity.scrollToStart((View) chips.getParent());
        chips.setOnCheckedStateChangeListener((group, ids) -> {
            if (ids.isEmpty()) return;
            Chip c = group.findViewById(ids.get(0));
            String picked = c != null ? String.valueOf(c.getTag()) : ALL;
            if (!picked.equals(category)) {
                category = picked;
                load();
            }
        });
    }

    private void selectChip(String name) {
        for (int i = 0; i < chips.getChildCount(); i++) {
            Chip c = (Chip) chips.getChildAt(i);
            if (name.equals(c.getTag())) {
                chips.check(c.getId());
                return;
            }
        }
    }

    @Override
    protected void onDestroy() {
        handler.removeCallbacksAndMessages(null);
        super.onDestroy();
    }
}
