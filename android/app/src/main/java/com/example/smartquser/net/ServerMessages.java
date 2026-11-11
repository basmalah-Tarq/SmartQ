package com.example.smartquser.net;

import android.content.Context;

import com.example.smartquser.R;
import com.example.smartquser.utils.Lang;

import java.util.HashMap;
import java.util.Locale;
import java.util.Map;

/**
 * The server answers in English. App-side errors carry a string resource and are shown in the app
 * language; the most common booking / sign-in / profile messages from the server are mapped to
 * their Arabic text when the app runs in Arabic. Unknown messages are shown as sent.
 */
public final class ServerMessages {

    private static final Map<String, Integer> KNOWN = new HashMap<>();

    static {
        put("Please log in again.", R.string.srv_log_in_again);
        put("Your session has ended. Please log in again.", R.string.srv_session_ended);
        put("Your session has expired. Please log in again.", R.string.srv_session_ended);
        put("Account not found.", R.string.srv_account_not_found);
        put("Appointment already cancelled.", R.string.srv_already_cancelled);
        put("Appointment not found.", R.string.srv_booking_not_found);
        put("Booking not found.", R.string.srv_booking_not_found);
        put("Booking failed. Please try again.", R.string.srv_booking_failed);
        put("Failed to cancel appointment.", R.string.srv_cancel_failed);
        put("Past appointments cannot be cancelled.", R.string.srv_cannot_cancel);
        put("This appointment can no longer be cancelled.", R.string.srv_cannot_cancel);
        put("No free time slots left.", R.string.srv_no_slots);
        put("This schedule does not exist.", R.string.srv_schedule_missing);
        put("This schedule has already ended.", R.string.srv_schedule_ended);
        put("This schedule is closed.", R.string.srv_schedule_closed);
        put("This schedule is full.", R.string.srv_schedule_full);
        put("This time is not part of the schedule.", R.string.srv_time_not_in_schedule);
        put("This time has already passed. Please choose another time.", R.string.srv_time_passed);
        put("This time was just booked by someone else. Please choose another time.", R.string.srv_time_taken);
        put("You already have a booking in this schedule.", R.string.srv_already_booked);
        put("Invalid time format.", R.string.srv_invalid_time);
        put("Current password is incorrect.", R.string.srv_wrong_current_password);
        put("New password and confirm password do not match.", R.string.srv_password_mismatch);
        put("New password must be at least 6 characters.", R.string.srv_password_short);
        put("Please fill all password fields.", R.string.srv_fill_passwords);
        put("Password updated successfully.", R.string.srv_password_updated);
        put("Failed to update password.", R.string.srv_password_failed);
        put("Profile updated successfully.", R.string.srv_profile_updated);
        put("Failed to update profile.", R.string.srv_profile_failed);
        put("Full name is required.", R.string.srv_name_required);
        put("Full name is too long.", R.string.srv_name_long);
        put("Phone number is too long.", R.string.srv_phone_long);
        put("Please enter a valid birth date.", R.string.srv_birth_date);
        put("Invalid email address.", R.string.srv_invalid_email);
        put("Please enter a valid email address.", R.string.srv_invalid_email);
        put("Please fill in your name and email.", R.string.srv_fill_name_email);
        put("Please choose a JPG, PNG or WEBP image.", R.string.srv_image_type);
        put("The image could not be saved.", R.string.srv_image_failed);
        put("The image could not be uploaded. Please try again.", R.string.srv_image_failed);
        put("The image is too large (max 5 MB).", R.string.srv_image_large);
        put("Registration failed. Please try again.", R.string.srv_register_failed);
        put("Could not create your account. Please try again.", R.string.srv_register_failed);
        put("Enter the 6-digit code from your email.", R.string.srv_enter_code);
        put("This code has expired. Please request a new one.", R.string.srv_code_expired);
        put("Too many wrong attempts. Please request a new code.", R.string.srv_code_attempts);
        put("Too many codes requested. Please try again later.", R.string.srv_code_too_many);
        put("Please wait a minute before requesting a new code.", R.string.srv_code_wait);
        put("We could not send the email. Please try again in a moment.", R.string.srv_email_failed);
        put("Email sign-in is not available right now. Please use your password.", R.string.srv_email_disabled);
        put("Google sign-in failed.", R.string.srv_google_failed);
        put("Google sign-in failed. Please try again.", R.string.srv_google_failed);
        put("Google sign-in expired. Please try again.", R.string.srv_google_failed);
        put("Google sign-in failed (wrong app).", R.string.srv_google_failed);
        put("Google sign-in is not configured yet.", R.string.srv_google_off);
        put("Your Google email is not verified.", R.string.srv_google_unverified);
        put("This app is for customers. Managers and admins please use the website.", R.string.srv_customers_only);
        put("Invalid email or password.", R.string.srv_bad_login);
        put("Invalid email or password", R.string.srv_bad_login);
        put("Email already exists.", R.string.srv_email_exists);
        put("This email is already registered.", R.string.srv_email_exists);
        put("Method not allowed.", R.string.err_generic);
    }

    private ServerMessages() {}

    private static void put(String english, int res) {
        KNOWN.put(key(english), res);
    }

    private static String key(String s) {
        return s == null ? "" : s.trim().toLowerCase(Locale.ROOT);
    }

    /** Resolves app-side errors; maps known server messages when the app language is Arabic. */
    public static ApiError localize(Context ui, ApiError e) {
        if (e.messageRes != 0) {
            String text = e.messageRes == R.string.err_server
                    ? ui.getString(R.string.err_server, Lang.num(e.httpCode))
                    : ui.getString(e.messageRes);
            return e.withMessage(text);
        }
        return e.withMessage(text(ui, e.message));
    }

    /** A server message in the app language when we know it (else unchanged). */
    public static String text(Context ui, String serverMessage) {
        if (serverMessage == null) return "";
        if (!Lang.isArabic(ui)) return serverMessage;
        Integer res = KNOWN.get(key(serverMessage));
        return res != null ? ui.getString(res) : serverMessage;
    }
}
