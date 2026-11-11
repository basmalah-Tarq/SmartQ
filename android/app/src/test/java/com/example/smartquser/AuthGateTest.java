package com.example.smartquser;

import static org.junit.Assert.assertEquals;
import static org.junit.Assert.assertFalse;
import static org.junit.Assert.assertTrue;

import com.example.smartquser.auth.AuthGate;
import com.example.smartquser.auth.AuthGate.Access;
import com.example.smartquser.auth.AuthGate.Area;
import com.example.smartquser.auth.AuthGate.Route;
import com.example.smartquser.auth.AuthOptions;

import org.junit.Test;

/** Guest mode gating, start routing and sign-in option rules. */
public class AuthGateTest {

    @Test
    public void startRoute() {
        assertEquals(Route.ONBOARDING, AuthGate.startRoute(false, false, false));
        assertEquals(Route.WELCOME, AuthGate.startRoute(true, false, false));
        assertEquals(Route.HOME, AuthGate.startRoute(true, false, true));
        assertEquals(Route.HOME, AuthGate.startRoute(true, true, false));
        // users who were already logged in before the update never see the onboarding
        assertEquals(Route.HOME, AuthGate.startRoute(false, true, false));
    }

    @Test
    public void signedInUsersMayDoEverything() {
        for (Area a : Area.values()) assertEquals(Access.ALLOW, AuthGate.access(true, false, a));
    }

    @Test
    public void guestsBrowseButMustSignInToBook() {
        assertEquals(Access.ALLOW, AuthGate.access(false, true, Area.HOME));
        assertEquals(Access.ALLOW, AuthGate.access(false, true, Area.PLACES));
        assertEquals(Access.ALLOW, AuthGate.access(false, true, Area.PLACE_DETAILS));
        assertEquals(Access.ALLOW, AuthGate.access(false, true, Area.SLOTS));
        assertEquals(Access.ASK_SIGN_IN, AuthGate.access(false, true, Area.BOOK));
        assertEquals(Access.ASK_SIGN_IN, AuthGate.access(false, true, Area.BOOKINGS));
        assertEquals(Access.ASK_SIGN_IN, AuthGate.access(false, true, Area.PROFILE));
    }

    @Test
    public void nobodyGoesToWelcome() {
        for (Area a : Area.values()) assertEquals(Access.WELCOME, AuthGate.access(false, false, a));
    }

    @Test
    public void publicEndpointsNeedNoToken() {
        for (String e : new String[]{"places.php", "place_details.php", "slots.php", "schedules.php",
                "auth_options.php", "email_start.php", "email_verify.php", "google_login.php",
                "login.php", "register.php", "slots.php?schedule_id=3", "/api/user/places.php", "PLACES.PHP"}) {
            assertFalse(e, AuthGate.endpointNeedsAuth(e));
        }
    }

    @Test
    public void accountEndpointsNeedToken() {
        for (String e : new String[]{"appointments.php", "next_appointment.php", "book_appointment.php",
                "cancel_appointment.php", "profile.php", "update_profile.php", "upload_profile_image.php",
                "logout.php", "anything_new.php", null}) {
            assertTrue(String.valueOf(e), AuthGate.endpointNeedsAuth(e));
        }
    }

    @Test
    public void guestRequestsToAccountEndpointsAreSkipped() {
        assertTrue(AuthGate.shouldSkipRequest("next_appointment.php", ""));
        assertTrue(AuthGate.shouldSkipRequest("appointments.php", null));
        assertTrue(AuthGate.shouldSkipRequest("profile.php", "   "));
        assertFalse(AuthGate.shouldSkipRequest("places.php", ""));
        assertFalse(AuthGate.shouldSkipRequest("appointments.php", "abc123"));
    }

    @Test
    public void googleButtonNeedsServerSwitchAndClientId() {
        assertFalse(AuthOptions.showGoogle(true, ""));
        assertFalse(AuthOptions.showGoogle(true, "  "));
        assertFalse(AuthOptions.showGoogle(true, null));
        assertFalse(AuthOptions.showGoogle(false, "123-abc.apps.googleusercontent.com"));
        assertTrue(AuthOptions.showGoogle(true, "123-abc.apps.googleusercontent.com"));
    }

    @Test
    public void passwordBecomesPrimaryWithoutEmailCodes() {
        assertTrue(new AuthOptions(false, false, "", true).passwordIsPrimary());
        assertFalse(new AuthOptions(true, false, "", true).passwordIsPrimary());
        assertFalse(AuthOptions.defaults().passwordIsPrimary());
        assertFalse(AuthOptions.defaults().showGoogle("123-abc.apps.googleusercontent.com"));
    }
}
