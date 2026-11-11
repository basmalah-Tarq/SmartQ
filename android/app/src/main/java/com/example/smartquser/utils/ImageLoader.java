package com.example.smartquser.utils;

import android.app.Activity;
import android.content.Context;
import android.widget.ImageView;

import androidx.annotation.DrawableRes;

import com.bumptech.glide.Glide;
import com.bumptech.glide.load.engine.DiskCacheStrategy;
import com.bumptech.glide.load.model.GlideUrl;
import com.bumptech.glide.load.model.LazyHeaders;
import com.bumptech.glide.load.resource.drawable.DrawableTransitionOptions;
import com.example.smartquser.net.ApiClient;

import java.util.Map;

/**
 * Loads image URLs exactly as the server sends them (place_image_url, profile_image_url).
 * Adds the same User-Agent + cookies as the API so images also pass the hosting anti-bot check.
 */
public final class ImageLoader {

    private ImageLoader() {}

    public static void load(ImageView view, String url, @DrawableRes int placeholder) {
        load(view, url, placeholder, false);
    }

    public static void loadCircle(ImageView view, String url, @DrawableRes int placeholder) {
        load(view, url, placeholder, true);
    }

    private static void load(ImageView view, String url, @DrawableRes int placeholder, boolean circle) {
        Context ctx = view.getContext();
        if (ctx instanceof Activity && (((Activity) ctx).isDestroyed() || ((Activity) ctx).isFinishing())) return;
        if (url == null || url.trim().isEmpty()) {
            try {
                Glide.with(view).clear(view);
            } catch (Throwable ignored) {
                // no Glide context (e.g. layout previews)
            }
            view.setImageResource(placeholder);
            return;
        }
        LazyHeaders.Builder headers = new LazyHeaders.Builder();
        for (Map.Entry<String, String> e : ApiClient.imageHeaders(ctx, url).entrySet()) {
            headers.addHeader(e.getKey(), e.getValue());
        }
        GlideUrl glideUrl = new GlideUrl(url.trim(), headers.build());
        com.bumptech.glide.RequestBuilder<android.graphics.drawable.Drawable> req = Glide.with(view)
                .load(glideUrl)
                .placeholder(placeholder)
                .error(placeholder)
                .diskCacheStrategy(DiskCacheStrategy.AUTOMATIC)
                .transition(DrawableTransitionOptions.withCrossFade(250));
        if (circle) req = req.circleCrop(); else req = req.centerCrop();
        req.into(view);
    }
}
