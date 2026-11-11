package com.example.smartquser.auth;

import java.util.Arrays;
import java.util.HashSet;
import java.util.Locale;
import java.util.Set;

/**
 * Pure rules for "who may see what" (no Android dependencies, unit tested).
 *
 * <ul>
 *   <li>Signed-in users can use everything.</li>
 *   <li>Guests ("Browse as guest") can browse Home, Places, place details and the free slots of a day.
 *       Booking, My bookings and Profile ask them to sign in first.</li>
 *   <li>Somebody who is neither signed in nor a guest is sent to the Welcome screen.</li>
 * </ul>
 */
public final class AuthGate {

    public enum Area { HOME, PLACES, PLACE_DETAILS, SLOTS, BOOK, BOOKINGS, PROFILE }

    public enum Access {
        /** Show the screen / run the action. */
        ALLOW,
        /** Guest: show the "Sign in to book" sheet, then continue. */
        ASK_SIGN_IN,
        /** Nobody is in yet: go to the Welcome screen. */
        WELCOME
    }

    public enum Route { ONBOARDING, WELCOME, HOME }

    /** Endpoints that work without an API token. Everything else needs one. */
    private static final Set<String> PUBLIC_ENDPOINTS = new HashSet<>(Arrays.asList(
            "auth_options.php", "email_start.php", "email_verify.php", "google_login.php",
            "login.php", "register.php",
            "places.php", "place_details.php", "schedules.php", "slots.php"));

    private AuthGate() {}

    /** Where the splash screen sends the user. */
    public static Route startRoute(boolean onboardingDone, boolean loggedIn, boolean guest) {
        if (loggedIn) return Route.HOME; // existing users never see the onboarding again
        if (!onboardingDone) return Route.ONBOARDING;
        return guest ? Route.HOME : Route.WELCOME;
    }

    public static boolean isPublic(Area area) {
        switch (area) {
            case HOME:
            case PLACES:
            case PLACE_DETAILS:
            case SLOTS:
                return true;
            default:
                return false;
        }
    }

    public static Access access(boolean loggedIn, boolean guest, Area area) {
        if (loggedIn) return Access.ALLOW;
        if (!guest) return Access.WELCOME;
        return isPublic(area) ? Access.ALLOW : Access.ASK_SIGN_IN;
    }

    /**
     * True when the endpoint needs an API token. "slots.php?schedule_id=3" and "/api/user/slots.php"
     * are accepted too.
     */
    public static boolean endpointNeedsAuth(String endpoint) {
        if (endpoint == null) return true;
        String e = endpoint.trim();
        int q = e.indexOf('?');
        if (q >= 0) e = e.substring(0, q);
        int slash = e.lastIndexOf('/');
        if (slash >= 0) e = e.substring(slash + 1);
        return !PUBLIC_ENDPOINTS.contains(e.toLowerCase(Locale.ROOT));
    }

    /** A request is skipped locally (never sent) when it needs a token and there is none. */
    public static boolean shouldSkipRequest(String endpoint, String token) {
        return (token == null || token.trim().isEmpty()) && endpointNeedsAuth(endpoint);
    }
}
