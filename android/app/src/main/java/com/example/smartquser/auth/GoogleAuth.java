package com.example.smartquser.auth;

import android.app.Activity;
import android.os.CancellationSignal;
import android.util.Log;

import androidx.annotation.NonNull;
import androidx.core.content.ContextCompat;
import androidx.credentials.Credential;
import androidx.credentials.CredentialManager;
import androidx.credentials.CredentialManagerCallback;
import androidx.credentials.CustomCredential;
import androidx.credentials.GetCredentialRequest;
import androidx.credentials.GetCredentialResponse;
import androidx.credentials.exceptions.GetCredentialCancellationException;
import androidx.credentials.exceptions.GetCredentialException;
import androidx.credentials.exceptions.NoCredentialException;

import com.example.smartquser.BuildConfig;
import com.example.smartquser.R;
import com.google.android.libraries.identity.googleid.GetSignInWithGoogleOption;
import com.google.android.libraries.identity.googleid.GoogleIdTokenCredential;

/**
 * "Continue with Google" through AndroidX Credential Manager. Returns a Google ID token for the
 * Web client ID in {@code BuildConfig.GOOGLE_WEB_CLIENT_ID}; the server checks it in google_login.php.
 */
public final class GoogleAuth {

    private static final String TAG = "SmartQ-Google";

    public interface Callback {
        void onIdToken(String idToken);

        void onCancelled();

        void onError(String message);
    }

    private GoogleAuth() {}

    public static boolean isConfiguredInApp() {
        return !BuildConfig.GOOGLE_WEB_CLIENT_ID.trim().isEmpty();
    }

    public static void requestIdToken(Activity activity, Callback cb) {
        if (!isConfiguredInApp()) {
            cb.onError(activity.getString(R.string.google_not_setup));
            return;
        }
        GetSignInWithGoogleOption option =
                new GetSignInWithGoogleOption.Builder(BuildConfig.GOOGLE_WEB_CLIENT_ID.trim()).build();
        GetCredentialRequest request = new GetCredentialRequest.Builder()
                .addCredentialOption(option)
                .build();
        CredentialManager manager = CredentialManager.create(activity);
        manager.getCredentialAsync(activity, request, new CancellationSignal(),
                ContextCompat.getMainExecutor(activity),
                new CredentialManagerCallback<GetCredentialResponse, GetCredentialException>() {
                    @Override
                    public void onResult(GetCredentialResponse response) {
                        if (activity.isFinishing() || activity.isDestroyed()) return;
                        handle(activity, response.getCredential(), cb);
                    }

                    @Override
                    public void onError(@NonNull GetCredentialException e) {
                        if (activity.isFinishing() || activity.isDestroyed()) return;
                        Log.w(TAG, "Credential Manager error: " + e.getClass().getSimpleName() + " " + e.getMessage());
                        if (e instanceof GetCredentialCancellationException) {
                            cb.onCancelled();
                        } else if (e instanceof NoCredentialException) {
                            cb.onError(activity.getString(R.string.google_no_account));
                        } else {
                            cb.onError(activity.getString(R.string.google_failed));
                        }
                    }
                });
    }

    private static void handle(Activity activity, Credential credential, Callback cb) {
        if (credential instanceof CustomCredential
                && GoogleIdTokenCredential.TYPE_GOOGLE_ID_TOKEN_CREDENTIAL.equals(credential.getType())) {
            try {
                GoogleIdTokenCredential google = GoogleIdTokenCredential.createFrom(credential.getData());
                cb.onIdToken(google.getIdToken());
                return;
            } catch (Exception e) {
                Log.w(TAG, "Bad Google ID token credential", e);
            }
        }
        cb.onError(activity.getString(R.string.google_failed));
    }
}
