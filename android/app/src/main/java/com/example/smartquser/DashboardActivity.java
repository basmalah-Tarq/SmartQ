package com.example.smartquser;

import android.animation.ValueAnimator;
import android.content.Intent;
import android.content.res.ColorStateList;
import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.TextView;

import androidx.core.content.ContextCompat;
import androidx.core.widget.NestedScrollView;
import androidx.swiperefreshlayout.widget.SwipeRefreshLayout;

import com.example.smartquser.models.Appointment;
import com.example.smartquser.net.ApiClient;
import com.example.smartquser.net.ApiError;
import com.example.smartquser.net.ApiResponse;
import com.example.smartquser.utils.DateUtils;
import com.example.smartquser.utils.ImageLoader;
import com.example.smartquser.utils.Lang;
import com.example.smartquser.utils.Ui;
import com.google.android.material.button.MaterialButton;
import com.google.android.material.chip.Chip;
import com.google.android.material.chip.ChipGroup;

import org.json.JSONArray;
import org.json.JSONObject;

import java.util.ArrayList;
import java.util.Date;
import java.util.List;
import java.util.Locale;

/**
 * Home. Signed-in users see their live queue ticket at the very top of the brand header (queue
 * code, place, date / time, status, countdown; "Today" badge), then upcoming bookings, stats, quick
 * actions, categories and a tip. Guests see a sign-in card with the queue illustration instead.
 */
public class DashboardActivity extends BaseActivity {

    public static final String EXTRA_WELCOME = "welcome";
    private static final int MAX_UPCOMING_ROWS = 3;

    private SwipeRefreshLayout swipe;
    private View ticketSkeleton, ticket, headerIntro, promoCard;
    private int pending;
    private boolean firstLoad = true;
    private int nextAppointmentId = -1;

    @Override
    protected boolean darkStatusBar() {
        return true;
    }

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        if (!requireEntry()) return;
        setContentView(R.layout.activity_dashboard);
        Ui.padTop(findViewById(R.id.header));
        setupBottomNav(R.id.nav_home);

        swipe = findViewById(R.id.swipe);
        ticketSkeleton = findViewById(R.id.ticketSkeleton);
        ticket = findViewById(R.id.ticket);
        headerIntro = findViewById(R.id.headerIntro);
        promoCard = findViewById(R.id.promoCard);
        swipe.setColorSchemeResources(R.color.blue_600, R.color.teal_500);
        swipe.setProgressBackgroundColorSchemeResource(R.color.surface);
        swipe.setProgressViewOffset(false, Ui.dp(this, 40), Ui.dp(this, 110));
        swipe.setOnRefreshListener(this::loadAll);

        findViewById(R.id.searchBar).setOnClickListener(v -> openPlaces(null, true));
        ticket.setOnClickListener(v -> openTab(AppointmentsActivity.class));
        findViewById(R.id.btnSeeAll).setOnClickListener(v -> openTab(AppointmentsActivity.class));
        findViewById(R.id.avatarBox).setOnClickListener(v ->
                gated(R.string.gate_profile_title, () -> openTab(ProfileActivity.class)));

        setupStat(R.id.statUpcoming, R.drawable.ic_calendar, R.color.accent, R.color.blue_tint, R.string.home_stat_upcoming);
        setupStat(R.id.statDone, R.drawable.ic_check, R.color.success, R.color.success_tint, R.string.home_stat_done);
        setupStat(R.id.statTotal, R.drawable.ic_ticket, R.color.teal_500, R.color.teal_tint, R.string.home_stat_total);

        setupAction(R.id.qaBook, R.drawable.ic_search, R.color.accent, R.color.blue_tint,
                R.string.home_qa_book, R.string.home_qa_book_sub, v -> openPlaces(null, false));
        setupAction(R.id.qaBookings, R.drawable.ic_ticket, R.color.teal_500, R.color.teal_tint,
                R.string.home_qa_bookings, R.string.home_qa_bookings_sub,
                v -> gated(R.string.gate_bookings_title, () -> openTab(AppointmentsActivity.class)));
        setupAction(R.id.qaHistory, R.drawable.ic_history, R.color.accent, R.color.blue_tint,
                R.string.home_qa_history, R.string.home_qa_history_sub, v -> gated(R.string.gate_bookings_title, () -> {
                    Intent i = new Intent(this, AppointmentsActivity.class);
                    i.putExtra(AppointmentsActivity.EXTRA_TAB, 1);
                    i.addFlags(Intent.FLAG_ACTIVITY_REORDER_TO_FRONT);
                    startActivity(i);
                    fadeTransition();
                }));
        setupAction(R.id.qaProfile, R.drawable.ic_person, R.color.teal_500, R.color.teal_tint,
                R.string.home_qa_profile, R.string.home_qa_profile_sub,
                v -> gated(R.string.gate_profile_title, () -> openTab(ProfileActivity.class)));

        bindUser();
        Ui.enterAll(findViewById(R.id.searchBar), findViewById(R.id.statsRow), findViewById(R.id.tvQuickTitle),
                findViewById(R.id.quickGrid), findViewById(R.id.tipCard));
        if (getIntent().getBooleanExtra(EXTRA_WELCOME, false)) {
            String first = session.getFirstName().trim();
            Ui.snack(this, first.isEmpty() ? getString(R.string.home_welcome)
                    : getString(R.string.home_welcome_name, first));
        }
        loadAll();
    }

    @Override
    protected void onRestart() {
        super.onRestart();
        bindUser();
        loadAll(); // back from a booking / cancel: refresh silently
    }

    @Override
    protected void onTabReselected() {
        ((NestedScrollView) findViewById(R.id.scroll)).smoothScrollTo(0, 0);
    }

    /** Account-only actions: guests get the sign-in sheet first, then the action runs. */
    private void gated(int titleRes, Runnable action) {
        requireAccount(getString(titleRes), getString(R.string.gate_generic_message), action);
    }

    @Override
    protected void onSignedIn() {
        bindUser();
        promoCard.setVisibility(View.GONE);
        headerIntro.setVisibility(View.GONE);
        ticketSkeleton.setVisibility(View.VISIBLE);
        firstLoad = true;
        loadAll();
    }

    private void bindUser() {
        ((TextView) findViewById(R.id.tvGreeting)).setText(getString(R.string.home_greeting, DateUtils.greeting()));
        boolean guest = !session.isLoggedIn();
        ((TextView) findViewById(R.id.tvName)).setText(guest ? getString(R.string.home_guest_name) : session.getFirstName());
        ((TextView) findViewById(R.id.tvInitials)).setText(guest ? getString(R.string.home_guest_initial) : session.getInitials());
        ImageView avatar = findViewById(R.id.ivAvatar);
        String url = session.getProfileImageUrl();
        if (url.isEmpty()) {
            avatar.setImageDrawable(null);
        } else {
            ImageLoader.loadCircle(avatar, url, android.R.color.transparent);
        }
    }

    private void setupStat(int id, int icon, int color, int tint, int label) {
        View card = findViewById(id);
        ImageView iv = card.findViewById(R.id.statIcon);
        iv.setImageResource(icon);
        Ui.tint(iv, color);
        card.findViewById(R.id.statIconBg).setBackgroundTintList(ContextCompat.getColorStateList(this, tint));
        ((TextView) card.findViewById(R.id.statLabel)).setText(label);
    }

    private void setupAction(int id, int icon, int color, int tint, int title, int sub, View.OnClickListener l) {
        View card = findViewById(id);
        ImageView iv = card.findViewById(R.id.qaIcon);
        iv.setImageResource(icon);
        Ui.tint(iv, color);
        card.findViewById(R.id.qaIconBg).setBackgroundTintList(ContextCompat.getColorStateList(this, tint));
        ((TextView) card.findViewById(R.id.qaTitle)).setText(title);
        ((TextView) card.findViewById(R.id.qaSubtitle)).setText(sub);
        card.setOnClickListener(l);
    }

    private void openPlaces(String category, boolean focusSearch) {
        Intent i = new Intent(this, PlacesActivity.class);
        if (category != null) i.putExtra(PlacesActivity.EXTRA_CATEGORY, category);
        if (focusSearch) i.putExtra(PlacesActivity.EXTRA_FOCUS_SEARCH, true);
        i.addFlags(Intent.FLAG_ACTIVITY_REORDER_TO_FRONT);
        startActivity(i);
        fadeTransition();
    }

    // ------------------------------------------------------------ data

    private void loadAll() {
        if (!session.isLoggedIn()) {
            // guest: public data only; the header and promo card invite them to sign in
            findViewById(R.id.statsRow).setVisibility(View.GONE);
            findViewById(R.id.upcomingSection).setVisibility(View.GONE);
            showGuest();
            pending = 1;
            loadCategories();
            return;
        }
        findViewById(R.id.statsRow).setVisibility(View.VISIBLE);
        pending = 3;
        loadNext();
        loadAppointments();
        loadCategories();
    }

    private void done() {
        if (--pending <= 0) {
            swipe.setRefreshing(false);
            firstLoad = false;
        }
    }

    private void loadNext() {
        ApiClient.get(this, "next_appointment.php", null, new ApiClient.Callback() {
            @Override
            public void onSuccess(ApiResponse r) {
                JSONObject o = r.data.optJSONObject("appointment");
                if (o == null) showNoTicket(null);
                else showTicket(Appointment.fromJson(o));
                done();
            }

            @Override
            public void onError(ApiError e) {
                showNoTicket(e);
                done();
            }
        });
    }

    // ------------------------------------------------------------ live ticket (header)

    private void showTicket(Appointment a) {
        nextAppointmentId = a.id;
        ((TextView) findViewById(R.id.tvTicketPlace)).setText(a.placeName);
        String loc = a.location();
        if (!a.category.isEmpty()) loc = a.category + (loc.isEmpty() ? "" : " · " + loc);
        TextView location = findViewById(R.id.tvTicketLocation);
        location.setText(loc);
        location.setVisibility(loc.isEmpty() ? View.GONE : View.VISIBLE);
        ((ImageView) findViewById(R.id.ivTicketCategory)).setImageResource(Ui.categoryIcon(a.category));
        ((TextView) findViewById(R.id.tvTicketDate)).setText(DateUtils.friendlyDate(a.date));
        ((TextView) findViewById(R.id.tvTicketTime)).setText(DateUtils.timeLabel(a.time));
        ((TextView) findViewById(R.id.tvTicketCountdown)).setText(countdown(a));

        Date day = DateUtils.parseDate(a.date);
        boolean today = day != null && DateUtils.daysFromToday(day) == 0;
        findViewById(R.id.tvTodayBadge).setVisibility(today ? View.VISIBLE : View.GONE);

        // status pill: translucent white on the brand header, so every status stays readable
        TextView pill = findViewById(R.id.tvTicketStatus);
        Ui.statusPill(pill, a.status);
        pill.setTextColor(ContextCompat.getColor(this, R.color.white));
        pill.setBackgroundTintList(ColorStateList.valueOf(ContextCompat.getColor(this, R.color.white_20)));

        ticket.setContentDescription(getString(R.string.home_ticket_cd, a.queueCode, a.placeName,
                DateUtils.friendlyDate(a.date), DateUtils.timeLabel(a.time)));

        boolean wasVisible = ticket.getVisibility() == View.VISIBLE;
        TextView code = findViewById(R.id.tvQueueCode);
        ticketSkeleton.setVisibility(View.GONE);
        headerIntro.setVisibility(View.GONE);
        promoCard.setVisibility(View.GONE);
        ticket.setVisibility(View.VISIBLE);
        if (!wasVisible) {
            Ui.enter(ticket, 0);
            tickQueueCode(code, a.queueNumber, a.queueCode);
        } else {
            code.setText(a.queueCode);
        }
    }

    /** Signature motion: the queue number ticks up to the real value (Q-000 -> Q-007). */
    private void tickQueueCode(TextView tv, int target, String finalText) {
        if (target <= 0 || !Ui.motionEnabled(this)) {
            tv.setText(finalText);
            return;
        }
        ValueAnimator va = ValueAnimator.ofInt(Math.max(0, target - 12), target);
        va.setDuration(900);
        va.setStartDelay(250);
        va.setInterpolator(Ui.EASE_OUT);
        va.addUpdateListener(an -> tv.setText(String.format(Locale.US, "Q-%03d", (int) an.getAnimatedValue())));
        va.addListener(new android.animation.AnimatorListenerAdapter() {
            @Override
            public void onAnimationEnd(android.animation.Animator animation) {
                tv.setText(finalText);
                Ui.pop(tv);
            }
        });
        va.start();
    }

    private String countdown(Appointment a) {
        Date when = DateUtils.parseDateTime(a.date, a.time);
        if (when == null) return "";
        long mins = (when.getTime() - System.currentTimeMillis()) / 60000;
        if (mins <= 0) return getString(R.string.countdown_now);
        if (mins < 60) return plural(R.plurals.countdown_minutes, mins);
        long hours = mins / 60;
        if (hours < 24) return plural(R.plurals.countdown_hours, hours);
        long days = DateUtils.daysFromToday(when);
        return days == 1 ? getString(R.string.date_tomorrow) : plural(R.plurals.countdown_days, days);
    }

    private String plural(int res, long n) {
        int q = (int) Math.min(Integer.MAX_VALUE, n);
        return getResources().getQuantityString(res, q, Lang.num(n));
    }

    // ------------------------------------------------------------ promo card (guest / empty / error)

    private void showNoTicket(ApiError error) {
        nextAppointmentId = -1;
        ticketSkeleton.setVisibility(View.GONE);
        if (error != null && ticket.getVisibility() == View.VISIBLE) {
            Ui.snack(this, error.message); // keep showing the last known ticket
            return;
        }
        ticket.setVisibility(View.GONE);
        showHeaderIntro();
        MaterialButton secondary = findViewById(R.id.btnPromoSecondary);
        secondary.setVisibility(View.GONE);
        if (error == null) {
            showPromo(getString(R.string.home_empty_title), getString(R.string.home_empty_message),
                    getString(R.string.action_book_now), v -> openPlaces(null, false));
        } else {
            showPromo(getString(error.kind == ApiError.Kind.NO_INTERNET ? R.string.state_offline_title
                            : R.string.home_error_title), error.message,
                    getString(R.string.action_try_again), v -> {
                        swipe.setRefreshing(true);
                        loadAll();
                    });
        }
    }

    private void showGuest() {
        ticketSkeleton.setVisibility(View.GONE);
        ticket.setVisibility(View.GONE);
        showHeaderIntro();
        MaterialButton secondary = findViewById(R.id.btnPromoSecondary);
        secondary.setVisibility(View.VISIBLE);
        secondary.setOnClickListener(v -> openPlaces(null, false));
        showPromo(getString(R.string.home_guest_title), getString(R.string.home_guest_message),
                getString(R.string.action_sign_in), v -> requireAccount(getString(R.string.gate_generic_title),
                        getString(R.string.gate_generic_message), null));
    }

    private void showHeaderIntro() {
        if (headerIntro.getVisibility() != View.VISIBLE) {
            headerIntro.setVisibility(View.VISIBLE);
            Ui.enter(headerIntro, 0);
        }
    }

    private void showPromo(String title, String message, String action, View.OnClickListener onAction) {
        ((TextView) findViewById(R.id.promoTitle)).setText(title);
        ((TextView) findViewById(R.id.promoMessage)).setText(message);
        MaterialButton btn = findViewById(R.id.btnPromo);
        btn.setText(action);
        btn.setOnClickListener(onAction);
        if (promoCard.getVisibility() != View.VISIBLE) {
            promoCard.setVisibility(View.VISIBLE);
            Ui.enter(promoCard, 1);
        }
    }

    // ------------------------------------------------------------ stats + coming up

    private void loadAppointments() {
        ApiClient.get(this, "appointments.php", null, new ApiClient.Callback() {
            @Override
            public void onSuccess(ApiResponse r) {
                JSONObject s = r.data.optJSONObject("stats");
                if (s != null) {
                    setStat(R.id.statUpcoming, s.optInt("upcoming"));
                    setStat(R.id.statDone, s.optInt("done"));
                    setStat(R.id.statTotal, s.optInt("total"));
                }
                List<Appointment> upcoming = new ArrayList<>();
                JSONArray arr = r.data.optJSONArray("appointments");
                if (arr != null) {
                    for (int i = 0; i < arr.length(); i++) {
                        JSONObject o = arr.optJSONObject(i);
                        if (o == null) continue;
                        Appointment a = Appointment.fromJson(o);
                        if (a.isUpcoming()) upcoming.add(a);
                    }
                }
                bindUpcoming(upcoming);
                done();
            }

            @Override
            public void onError(ApiError e) {
                done();
            }
        });
    }

    /** "Coming up": the next bookings after the one shown in the header ticket. */
    private void bindUpcoming(List<Appointment> all) {
        java.util.Collections.sort(all, (x, y) -> {
            Date dx = DateUtils.parseDateTime(x.date, x.time), dy = DateUtils.parseDateTime(y.date, y.time);
            if (dx == null || dy == null) return 0;
            return dx.compareTo(dy);
        });
        LinearLayout list = findViewById(R.id.upcomingList);
        list.removeAllViews();
        LayoutInflater inf = LayoutInflater.from(this);
        int shown = 0;
        for (Appointment a : all) {
            if (a.id == nextAppointmentId) continue;
            if (shown >= MAX_UPCOMING_ROWS) break;
            View row = inf.inflate(R.layout.dash_upcoming_row, list, false);
            ((TextView) row.findViewById(R.id.upDay)).setText(DateUtils.dayOfMonth(a.date));
            ((TextView) row.findViewById(R.id.upMonth)).setText(DateUtils.monthShort(a.date));
            ((TextView) row.findViewById(R.id.upPlace)).setText(a.placeName);
            ((TextView) row.findViewById(R.id.upWhen)).setText(
                    DateUtils.friendlyDate(a.date) + " · " + DateUtils.timeLabel(a.time));
            ((TextView) row.findViewById(R.id.upCode)).setText(a.queueCode);
            row.setOnClickListener(v -> openTab(AppointmentsActivity.class));
            list.addView(row, new ViewGroup.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT,
                    ViewGroup.LayoutParams.WRAP_CONTENT));
            shown++;
        }
        View section = findViewById(R.id.upcomingSection);
        boolean show = shown > 0;
        if (show && section.getVisibility() != View.VISIBLE) {
            section.setVisibility(View.VISIBLE);
            Ui.enter(section, 0);
        } else if (!show) {
            section.setVisibility(View.GONE);
        }
    }

    private void setStat(int id, int value) {
        TextView tv = findViewById(id).findViewById(R.id.statValue);
        if (!firstLoad || !Ui.motionEnabled(this) || value == 0) {
            tv.setText(Lang.num(value));
            return;
        }
        ValueAnimator va = ValueAnimator.ofInt(0, value);
        va.setDuration(800);
        va.setInterpolator(Ui.EASE_OUT);
        va.addUpdateListener(an -> tv.setText(Lang.num((int) an.getAnimatedValue())));
        va.start();
    }

    // ------------------------------------------------------------ categories

    private void loadCategories() {
        ApiClient.get(this, "places.php", null, new ApiClient.Callback() {
            @Override
            public void onSuccess(ApiResponse r) {
                JSONArray cats = r.data.optJSONArray("categories");
                ChipGroup group = findViewById(R.id.categoryChips);
                group.removeAllViews();
                if (cats != null) {
                    for (int i = 0; i < cats.length(); i++) {
                        String c = cats.optString(i, "");
                        if (c.isEmpty()) continue;
                        Chip chip = new Chip(DashboardActivity.this);
                        chip.setText(c);
                        chip.setCheckable(false);
                        chip.setChipIconResource(Ui.categoryIcon(c));
                        chip.setChipIconTintResource(R.color.accent);
                        chip.setChipIconSize(Ui.dp(DashboardActivity.this, 20));
                        chip.setIconStartPadding(Ui.dp(DashboardActivity.this, 2));
                        chip.setTextStartPadding(Ui.dp(DashboardActivity.this, 6));
                        chip.setChipIconVisible(true);
                        chip.setOnClickListener(v -> openPlaces(c, false));
                        group.addView(chip);
                        Ui.enter(chip, i);
                    }
                }
                int vis = group.getChildCount() > 0 ? View.VISIBLE : View.GONE;
                findViewById(R.id.tvCategoriesTitle).setVisibility(vis);
                findViewById(R.id.categoriesScroll).setVisibility(vis);
                scrollToStart(findViewById(R.id.categoriesScroll));
                done();
            }

            @Override
            public void onError(ApiError e) {
                done();
            }
        });
    }

    /** Right-to-left: a horizontal chip row must start scrolled to its start (the right edge). */
    static void scrollToStart(View v) {
        if (!(v instanceof android.widget.HorizontalScrollView)) return;
        android.widget.HorizontalScrollView h = (android.widget.HorizontalScrollView) v;
        h.post(() -> {
            if (h.getChildCount() == 0) return;
            boolean rtl = h.getLayoutDirection() == View.LAYOUT_DIRECTION_RTL;
            h.scrollTo(rtl ? Math.max(0, h.getChildAt(0).getWidth() - h.getWidth()) : 0, 0);
        });
    }
}
