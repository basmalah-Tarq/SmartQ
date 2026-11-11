package com.example.smartquser;

import android.content.Intent;
import android.graphics.Color;
import android.os.Bundle;
import android.view.View;
import android.widget.TextView;

import androidx.activity.EdgeToEdge;
import androidx.activity.SystemBarStyle;
import androidx.activity.result.ActivityResultLauncher;
import androidx.activity.result.contract.ActivityResultContracts;
import androidx.annotation.Nullable;
import androidx.appcompat.app.AppCompatActivity;
import androidx.fragment.app.Fragment;

import com.example.smartquser.auth.AuthGate;
import com.example.smartquser.auth.GoogleAuth;
import com.example.smartquser.net.ApiClient;
import com.example.smartquser.net.ApiError;
import com.example.smartquser.net.ApiResponse;
import com.example.smartquser.utils.AppPrefs;
import com.example.smartquser.utils.DateUtils;
import com.example.smartquser.utils.SessionManager;
import com.example.smartquser.utils.Ui;
import com.google.android.material.bottomnavigation.BottomNavigationView;
import com.google.android.material.button.MaterialButton;

/**
 * Shared setup: edge-to-edge bars, bottom navigation, error/empty state panel, and the sign-in
 * plumbing used by guest mode (sign-in sheet, Google / email / password, "continue where you left off").
 */
public abstract class BaseActivity extends AppCompatActivity {

    /** Set on Login / Register / EmailSignIn when they were opened from the sign-in sheet. */
    public static final String EXTRA_FOR_RESULT = "for_result";
    public static final String EXTRA_MESSAGE = "message";

    protected SessionManager session;
    protected AppPrefs prefs;

    /** What to do once a guest has signed in from the sheet (e.g. confirm the booking). */
    private Runnable afterSignIn;

    private final ActivityResultLauncher<Intent> signInLauncher = registerForActivityResult(
            new ActivityResultContracts.StartActivityForResult(), result -> {
                if (session != null && session.isLoggedIn()) signedInHere();
            });

    /** Busy / error display for a sign-in started from some UI (Welcome buttons or the sheet). */
    public interface AuthUi {
        void setBusy(boolean busy);

        void showError(String message);
    }

    /** Screens with a dark gradient header at the top want light status-bar icons. */
    protected boolean darkStatusBar() {
        return false;
    }

    @Override
    protected void onCreate(@Nullable Bundle savedInstanceState) {
        EdgeToEdge.enable(this,
                darkStatusBar() ? SystemBarStyle.dark(Color.TRANSPARENT)
                        : SystemBarStyle.auto(Color.TRANSPARENT, Color.TRANSPARENT),
                SystemBarStyle.auto(Color.TRANSPARENT, Color.TRANSPARENT));
        super.onCreate(savedInstanceState);
        DateUtils.configure(this);
        // Server texts (place names, addresses) are often Latin: align them by the app language, not by
        // their first letter, so Arabic screens stay right-aligned. Codes / inputs set ltr explicitly.
        if (getResources().getConfiguration().getLayoutDirection() == View.LAYOUT_DIRECTION_RTL) {
            getWindow().getDecorView().setTextDirection(View.TEXT_DIRECTION_RTL);
        }
        session = new SessionManager(this);
        prefs = new AppPrefs(this);
    }

    // ------------------------------------------------------------ access

    /** Browsing as a guest (no account yet). */
    protected boolean isGuest() {
        return !session.isLoggedIn() && prefs.isGuest();
    }

    /** For screens guests may see (Home, Places, details, slots). Returns false when finishing. */
    protected boolean requireEntry() {
        if (AuthGate.access(session.isLoggedIn(), prefs.isGuest(), AuthGate.Area.HOME) == AuthGate.Access.ALLOW) {
            return true;
        }
        goWelcome(null);
        return false;
    }

    /** For account-only screens (Bookings, Profile). Returns false when finishing. */
    protected boolean requireLogin() {
        if (session.isLoggedIn()) return true;
        if (prefs.isGuest()) {
            // a guest landed on an account screen (old shortcut, notification): go home instead
            Intent i = new Intent(this, DashboardActivity.class);
            i.addFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP | Intent.FLAG_ACTIVITY_SINGLE_TOP);
            startActivity(i);
            finish();
            return false;
        }
        goWelcome(null);
        return false;
    }

    protected void goWelcome(@Nullable String message) {
        Intent i = new Intent(this, WelcomeActivity.class);
        if (message != null) i.putExtra(EXTRA_MESSAGE, message);
        i.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_CLEAR_TASK);
        startActivity(i);
        fadeTransition();
        finish();
    }

    /**
     * Runs {@code then} right away for signed-in users. Guests get the "Sign in to book" sheet; after
     * they sign in (Google, email code or password) {@code then} runs, so they continue where they were.
     */
    protected void requireAccount(String title, String message, @Nullable Runnable then) {
        if (session.isLoggedIn()) {
            if (then != null) then.run();
            return;
        }
        afterSignIn = then;
        if (getSupportFragmentManager().findFragmentByTag(SignInSheet.TAG) != null) return;
        SignInSheet.newInstance(title, message).show(getSupportFragmentManager(), SignInSheet.TAG);
    }

    // ------------------------------------------------------------ sign-in entry points (also used by SignInSheet)

    /** Email code screen. In-place (from the sheet) it returns here; otherwise it goes to Home. */
    public void openEmailSignIn(boolean inPlace) {
        Intent i = new Intent(this, EmailSignInActivity.class);
        if (inPlace) {
            i.putExtra(EXTRA_FOR_RESULT, true);
            signInLauncher.launch(i);
        } else {
            startActivity(i);
        }
    }

    public void openPasswordSignIn(boolean inPlace) {
        Intent i = new Intent(this, LoginActivity.class);
        if (inPlace) {
            i.putExtra(EXTRA_FOR_RESULT, true);
            signInLauncher.launch(i);
        } else {
            startActivity(i);
        }
    }

    /** Credential Manager -> Google ID token -> google_login.php -> {@link #onAuthenticated}. */
    public void signInWithGoogle(AuthUi ui) {
        ui.setBusy(true);
        GoogleAuth.requestIdToken(this, new GoogleAuth.Callback() {
            @Override
            public void onIdToken(String idToken) {
                ApiClient.post(BaseActivity.this, "google_login.php", ApiClient.params("id_token", idToken),
                        new ApiClient.Callback() {
                            @Override
                            public void onSuccess(ApiResponse r) {
                                ui.setBusy(false);
                                onAuthenticated(r);
                            }

                            @Override
                            public void onError(ApiError e) {
                                ui.setBusy(false);
                                ui.showError(e.message);
                            }
                        });
            }

            @Override
            public void onCancelled() {
                ui.setBusy(false);
            }

            @Override
            public void onError(String message) {
                ui.setBusy(false);
                ui.showError(message);
            }
        });
    }

    /** Stores the session from a login / register / email_verify / google_login answer. */
    protected boolean saveAuth(ApiResponse r) {
        String token = r.data.optString("api_token", "");
        if (token.isEmpty()) return false;
        session.saveUser(r.data.optJSONObject("user"), token);
        prefs.setGuest(false);
        return true;
    }

    /**
     * A sign-in finished on this screen. Default (guest screens): stay here and continue.
     * Welcome / EmailSignIn / Login override this to move on.
     */
    protected void onAuthenticated(ApiResponse r) {
        if (!saveAuth(r)) {
            Ui.snack(this, getString(R.string.signin_failed));
            return;
        }
        signedInHere();
    }

    private void signedInHere() {
        Fragment sheet = getSupportFragmentManager().findFragmentByTag(SignInSheet.TAG);
        if (sheet instanceof SignInSheet) ((SignInSheet) sheet).dismissAllowingStateLoss();
        onSignedIn();
        Runnable next = afterSignIn;
        afterSignIn = null;
        String name = session.getFullName().trim();
        Ui.snack(this, name.isEmpty() ? getString(R.string.signed_in) : getString(R.string.signed_in_as, name));
        if (next != null) next.run();
    }

    /** Hook: refresh the screen after a guest signed in here. */
    protected void onSignedIn() {}

    /** After a full-screen sign-in (Welcome, email, password): start fresh on Home. */
    protected void goHomeAfterAuth(boolean newUser) {
        Intent i = new Intent(this, DashboardActivity.class);
        i.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_CLEAR_TASK);
        if (newUser) i.putExtra(DashboardActivity.EXTRA_WELCOME, true);
        startActivity(i);
        fadeTransition();
        finish();
    }

    /** Opened from the sign-in sheet? Then finish with RESULT_OK instead of going Home. */
    protected boolean isForResult() {
        return getIntent().getBooleanExtra(EXTRA_FOR_RESULT, false);
    }

    protected void finishSignedIn(boolean newUser) {
        if (isForResult()) {
            setResult(RESULT_OK);
            finish();
        } else {
            goHomeAfterAuth(newUser);
        }
    }

    /** Logs out locally (and on the server, best effort) and returns to the Welcome screen. */
    protected void logoutToWelcome() {
        if (session.isLoggedIn()) {
            ApiClient.post(this, "logout.php", null, new ApiClient.Callback() {
                public void onSuccess(ApiResponse r) {}

                public void onError(ApiError e) {}
            });
        }
        session.logout();
        prefs.setGuest(false);
        goWelcome(null);
    }

    // ------------------------------------------------------------ bottom navigation

    protected void setupBottomNav(int selectedItemId) {
        BottomNavigationView nav = findViewById(R.id.bottomNav);
        if (nav == null) return;
        nav.setSelectedItemId(selectedItemId);
        nav.setOnItemSelectedListener(item -> {
            int id = item.getItemId();
            if (id == selectedItemId) return true;
            Class<?> target;
            if (id == R.id.nav_home) target = DashboardActivity.class;
            else if (id == R.id.nav_places) target = PlacesActivity.class;
            else if (id == R.id.nav_bookings) target = AppointmentsActivity.class;
            else target = ProfileActivity.class;
            if (isGuest() && (id == R.id.nav_bookings || id == R.id.nav_profile)) {
                boolean bookings = id == R.id.nav_bookings;
                requireAccount(getString(bookings ? R.string.gate_bookings_title : R.string.gate_profile_title),
                        getString(bookings ? R.string.gate_bookings_message : R.string.gate_profile_message),
                        () -> openTab(target));
                return false;
            }
            openTab(target);
            return false; // keep this screen's selection; the new screen selects its own tab
        });
        nav.setOnItemReselectedListener(item -> onTabReselected());
    }

    protected void openTab(Class<?> target) {
        Intent i = new Intent(this, target);
        i.addFlags(Intent.FLAG_ACTIVITY_REORDER_TO_FRONT);
        startActivity(i);
        fadeTransition();
        if (!(this instanceof DashboardActivity)) finish();
    }

    /** Scroll to top etc. */
    protected void onTabReselected() {}

    @SuppressWarnings("deprecation")
    protected void fadeTransition() {
        overridePendingTransition(R.anim.fade_in, R.anim.fade_out);
    }

    // ------------------------------------------------------------ state panel (empty / error)

    /**
     * Fills an included layout_state view.
     *
     * @param action button text, or null to hide the button
     */
    protected void showState(View state, int iconRes, String title, String message, String action, Runnable onAction) {
        if (state == null) return;
        android.widget.ImageView icon = state.findViewById(R.id.stateIcon);
        TextView t = state.findViewById(R.id.stateTitle);
        TextView m = state.findViewById(R.id.stateMessage);
        MaterialButton b = state.findViewById(R.id.stateAction);
        icon.setImageResource(iconRes);
        t.setText(title);
        m.setText(message);
        if (action == null) {
            b.setVisibility(View.GONE);
        } else {
            b.setVisibility(View.VISIBLE);
            b.setText(action);
            b.setOnClickListener(v -> onAction.run());
        }
        if (state.getVisibility() != View.VISIBLE) {
            state.setVisibility(View.VISIBLE);
            Ui.enter(state, 0);
        }
    }

    protected void showError(View state, ApiError e, Runnable retry) {
        int icon = e.kind == ApiError.Kind.NO_INTERNET || e.kind == ApiError.Kind.UNREACHABLE
                || e.kind == ApiError.Kind.TIMEOUT ? R.drawable.ic_cloud_off : R.drawable.ic_info;
        String title = getString(e.kind == ApiError.Kind.NO_INTERNET ? R.string.state_offline_title
                : e.isNetworkProblem() ? R.string.state_unreachable_title : R.string.state_error_title);
        showState(state, icon, title, e.message, getString(R.string.action_try_again), retry);
    }
}
