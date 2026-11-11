package com.example.smartquser;

import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.TextView;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;

import com.example.smartquser.auth.AuthOptions;
import com.example.smartquser.utils.AppPrefs;
import com.example.smartquser.utils.Ui;
import com.google.android.material.bottomsheet.BottomSheetDialogFragment;
import com.google.android.material.button.MaterialButton;

/**
 * "Sign in to book" bottom sheet for guests: Google / email code / password. The host activity
 * ({@link BaseActivity}) does the actual sign-in and then continues what the guest was doing.
 */
public class SignInSheet extends BottomSheetDialogFragment {

    public static final String TAG = "sign_in_sheet";
    private static final String ARG_TITLE = "title";
    private static final String ARG_MESSAGE = "message";

    public static SignInSheet newInstance(String title, String message) {
        SignInSheet s = new SignInSheet();
        Bundle b = new Bundle();
        b.putString(ARG_TITLE, title);
        b.putString(ARG_MESSAGE, message);
        s.setArguments(b);
        return s;
    }

    @Nullable
    @Override
    public View onCreateView(@NonNull LayoutInflater inflater, @Nullable ViewGroup container,
                             @Nullable Bundle savedInstanceState) {
        return inflater.inflate(R.layout.sheet_sign_in, container, false);
    }

    @Override
    public void onViewCreated(@NonNull View v, @Nullable Bundle savedInstanceState) {
        Bundle args = getArguments() != null ? getArguments() : new Bundle();
        ((TextView) v.findViewById(R.id.sheetTitle)).setText(args.getString(ARG_TITLE, getString(R.string.gate_book_title)));
        ((TextView) v.findViewById(R.id.sheetMessage)).setText(
                args.getString(ARG_MESSAGE, getString(R.string.gate_book_message)));

        AuthOptions options = new AppPrefs(requireContext()).authOptions();
        MaterialButton google = v.findViewById(R.id.sheetGoogle);
        MaterialButton email = v.findViewById(R.id.sheetEmail);
        MaterialButton password = v.findViewById(R.id.sheetPassword);
        View progress = v.findViewById(R.id.sheetGoogleProgress);
        TextView error = v.findViewById(R.id.sheetError);

        v.findViewById(R.id.sheetGoogleBox).setVisibility(
                options.showGoogle(BuildConfig.GOOGLE_WEB_CLIENT_ID) ? View.VISIBLE : View.GONE);
        if (options.passwordIsPrimary()) {
            // email codes are switched off on the server: password login becomes the main button
            email.setText(R.string.sign_in_with_password);
            email.setIconResource(R.drawable.ic_lock);
            email.setOnClickListener(x -> openPassword());
            password.setVisibility(View.GONE);
        } else {
            email.setOnClickListener(x -> {
                BaseActivity h = host();
                dismissAllowingStateLoss();
                h.openEmailSignIn(true);
            });
            password.setOnClickListener(x -> openPassword());
        }

        google.setOnClickListener(x -> {
            error.setVisibility(View.GONE);
            host().signInWithGoogle(new BaseActivity.AuthUi() {
                @Override
                public void setBusy(boolean busy) {
                    if (!isAdded()) return;
                    google.setEnabled(!busy);
                    google.setText(busy ? "" : getString(R.string.continue_with_google));
                    google.setIcon(busy ? null : androidx.core.content.ContextCompat.getDrawable(requireContext(), R.drawable.ic_google));
                    progress.setVisibility(busy ? View.VISIBLE : View.GONE);
                    setCancelable(!busy);
                }

                @Override
                public void showError(String message) {
                    if (!isAdded()) return;
                    error.setText(message);
                    error.setVisibility(View.VISIBLE);
                    Ui.shake(error);
                }
            });
        });
        v.findViewById(R.id.sheetNotNow).setOnClickListener(x -> dismiss());

        Ui.enterAll(v.findViewById(R.id.sheetIcon), v.findViewById(R.id.sheetTitle), v.findViewById(R.id.sheetMessage));
    }

    private void openPassword() {
        BaseActivity h = host();
        dismissAllowingStateLoss();
        h.openPasswordSignIn(true);
    }

    private BaseActivity host() {
        return (BaseActivity) requireActivity();
    }
}
