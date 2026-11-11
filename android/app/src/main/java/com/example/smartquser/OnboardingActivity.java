package com.example.smartquser;

import android.animation.Animator;
import android.content.Intent;
import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.FrameLayout;
import android.widget.TextView;

import androidx.activity.OnBackPressedCallback;
import androidx.annotation.NonNull;
import androidx.recyclerview.widget.RecyclerView;
import androidx.viewpager2.widget.ViewPager2;

import com.example.smartquser.ui.OnboardingArt;
import com.example.smartquser.ui.PageIndicatorView;
import com.example.smartquser.utils.Lang;
import com.example.smartquser.utils.Ui;

import java.util.List;

/**
 * First-launch introduction: three colored slides (illustration panel + title + one line) with
 * prev / next arrows (mirrored in Arabic), page dots and Skip; the last slide says "Get started".
 */
public class OnboardingActivity extends BaseActivity {

    static final int[] TITLES = {R.string.onb_title_1, R.string.onb_title_2, R.string.onb_title_3};
    static final int[] BODIES = {R.string.onb_body_1, R.string.onb_body_2, R.string.onb_body_3};
    static final int[] PANELS = {R.drawable.onb_panel_1, R.drawable.onb_panel_2, R.drawable.onb_panel_3};

    private ViewPager2 pager;
    private View btnPrev, btnNext, btnStart, btnSkip;
    private PageIndicatorView indicator;
    private int current = -1;

    @Override
    protected boolean darkStatusBar() {
        return true; // the colored panels sit under the status bar
    }

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_onboarding);
        Ui.padTop(findViewById(R.id.topBar));
        Ui.padBottom(findViewById(R.id.bottomBar));

        pager = findViewById(R.id.pager);
        btnPrev = findViewById(R.id.btnPrev);
        btnNext = findViewById(R.id.btnNext);
        btnStart = findViewById(R.id.btnStart);
        btnSkip = findViewById(R.id.btnSkip);
        indicator = findViewById(R.id.indicator);
        indicator.setCount(OnboardingArt.PAGES);

        pager.setAdapter(new PagesAdapter());
        pager.setOffscreenPageLimit(1);
        final boolean motion = Ui.motionEnabled(this);
        pager.setPageTransformer((page, pos) -> transform(page, pos, motion));
        pager.registerOnPageChangeCallback(new ViewPager2.OnPageChangeCallback() {
            @Override
            public void onPageScrolled(int position, float offset, int px) {
                indicator.setProgress(position, offset);
            }

            @Override
            public void onPageSelected(int position) {
                onPage(position);
            }
        });

        btnSkip.setOnClickListener(v -> finishOnboarding());
        btnNext.setOnClickListener(v -> {
            int p = pager.getCurrentItem();
            if (p >= OnboardingArt.PAGES - 1) finishOnboarding();
            else pager.setCurrentItem(p + 1, true);
        });
        btnStart.setOnClickListener(v -> finishOnboarding());
        btnPrev.setOnClickListener(v -> {
            int p = pager.getCurrentItem();
            if (p > 0) pager.setCurrentItem(p - 1, true);
        });
        getOnBackPressedDispatcher().addCallback(this, new OnBackPressedCallback(true) {
            @Override
            public void handleOnBackPressed() {
                int p = pager.getCurrentItem();
                if (p > 0) {
                    pager.setCurrentItem(p - 1, true);
                } else {
                    setEnabled(false);
                    getOnBackPressedDispatcher().onBackPressed();
                }
            }
        });

        onPage(0);
        Ui.enter(findViewById(R.id.bottomBar), 3);
    }

    /** Parallax: the illustration lags behind the page, the text runs slightly ahead, both fade. */
    private static void transform(View page, float pos, boolean motion) {
        View art = page.findViewById(R.id.art);
        View step = page.findViewById(R.id.step);
        View title = page.findViewById(R.id.title);
        View body = page.findViewById(R.id.body);
        float a = Math.min(1f, Math.abs(pos));
        if (!motion) {
            art.setAlpha(1f - a);
            return;
        }
        // in RTL the pages move the other way: keep the parallax direction consistent
        float dir = page.getLayoutDirection() == View.LAYOUT_DIRECTION_RTL ? -1f : 1f;
        float w = page.getWidth() * dir;
        art.setTranslationX(-pos * w * 0.35f);
        art.setAlpha(1f - a * 0.9f);
        step.setTranslationX(pos * w * 0.10f);
        title.setTranslationX(pos * w * 0.18f);
        body.setTranslationX(pos * w * 0.30f);
        float ta = Math.max(0f, 1f - a * 1.6f);
        step.setAlpha(ta);
        title.setAlpha(ta);
        body.setAlpha(Math.max(0f, 1f - a * 1.9f));
    }

    private void onPage(int position) {
        if (position == current) return;
        current = position;
        boolean last = position == OnboardingArt.PAGES - 1;
        fade(btnPrev, position > 0);
        fade(btnSkip, !last);
        if (last) {
            btnNext.setVisibility(View.GONE);
            if (btnStart.getVisibility() != View.VISIBLE) {
                btnStart.setVisibility(View.VISIBLE);
                Ui.pop(btnStart);
            }
        } else {
            btnStart.setVisibility(View.GONE);
            btnNext.setVisibility(View.VISIBLE);
        }
        RecyclerView rv = (RecyclerView) pager.getChildAt(0);
        RecyclerView.ViewHolder vh = rv.findViewHolderForAdapterPosition(position);
        if (vh instanceof PageHolder) {
            OnboardingArt.playEntrance(((PageHolder) vh).artView, position);
        }
    }

    /** Hidden controls keep their space (INVISIBLE) so the dots never jump. */
    private static void fade(View v, boolean show) {
        v.setEnabled(show);
        v.animate().cancel();
        if (show) {
            v.setVisibility(View.VISIBLE);
            v.animate().alpha(1f).setDuration(200).start();
        } else {
            v.animate().alpha(0f).setDuration(200).withEndAction(() -> v.setVisibility(View.INVISIBLE)).start();
        }
    }

    private void finishOnboarding() {
        prefs.setOnboardingDone(true);
        Intent i = new Intent(this, session.isLoggedIn() ? DashboardActivity.class : WelcomeActivity.class);
        i.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_CLEAR_TASK);
        startActivity(i);
        fadeTransition();
        finish();
    }

    // ------------------------------------------------------------ pages

    static final class PageHolder extends RecyclerView.ViewHolder {
        final FrameLayout artFrame;
        View artView;
        List<Animator> loops;
        int page;

        PageHolder(View v) {
            super(v);
            artFrame = v.findViewById(R.id.art);
        }
    }

    private final class PagesAdapter extends RecyclerView.Adapter<PageHolder> {

        @Override
        public int getItemViewType(int position) {
            return position;
        }

        @NonNull
        @Override
        public PageHolder onCreateViewHolder(@NonNull ViewGroup parent, int viewType) {
            View v = LayoutInflater.from(parent.getContext()).inflate(R.layout.item_onboarding_page, parent, false);
            v.findViewById(R.id.panel).setBackgroundResource(PANELS[viewType]);
            PageHolder h = new PageHolder(v);
            h.page = viewType;
            h.artView = OnboardingArt.inflate(h.artFrame, viewType);
            h.artFrame.addView(h.artView);
            OnboardingArt.fitInto(h.artFrame, h.artView);
            return h;
        }

        @Override
        public void onBindViewHolder(@NonNull PageHolder h, int position) {
            String title = getString(TITLES[position]);
            String body = getString(BODIES[position]);
            ((TextView) h.itemView.findViewById(R.id.step)).setText(getString(R.string.onb_step,
                    Lang.num(position + 1), Lang.num(OnboardingArt.PAGES)));
            ((TextView) h.itemView.findViewById(R.id.title)).setText(title);
            ((TextView) h.itemView.findViewById(R.id.body)).setText(body);
            h.itemView.setContentDescription(title + ". " + body);
        }

        @Override
        public void onViewAttachedToWindow(@NonNull PageHolder h) {
            OnboardingArt.stop(h.loops);
            h.loops = OnboardingArt.startLoops(h.artView, h.page);
            if (h.page == 0 && current <= 0) OnboardingArt.playEntrance(h.artView, 0);
        }

        @Override
        public void onViewDetachedFromWindow(@NonNull PageHolder h) {
            OnboardingArt.stop(h.loops);
        }

        @Override
        public int getItemCount() {
            return OnboardingArt.PAGES;
        }
    }
}
