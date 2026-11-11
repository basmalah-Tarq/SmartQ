package com.example.smartquser.adapters;

import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.ImageView;
import android.widget.TextView;

import androidx.annotation.NonNull;
import androidx.recyclerview.widget.RecyclerView;

import com.example.smartquser.R;
import com.example.smartquser.models.Appointment;
import com.example.smartquser.utils.DateUtils;
import com.example.smartquser.utils.ImageLoader;
import com.example.smartquser.utils.Ui;

import java.util.ArrayList;
import java.util.List;

/** The single appointments adapter (RecyclerView). */
public class AppointmentsAdapter extends RecyclerView.Adapter<AppointmentsAdapter.VH> {

    public interface Listener {
        void onCancel(Appointment appointment);
    }

    private final List<Appointment> items = new ArrayList<>();
    private final Listener listener;

    public AppointmentsAdapter(Listener listener) {
        this.listener = listener;
        setHasStableIds(true);
    }

    public void submit(List<Appointment> list) {
        items.clear();
        items.addAll(list);
        notifyDataSetChanged();
    }

    @Override
    public long getItemId(int position) {
        return items.get(position).id;
    }

    @NonNull
    @Override
    public VH onCreateViewHolder(@NonNull ViewGroup parent, int viewType) {
        return new VH(LayoutInflater.from(parent.getContext()).inflate(R.layout.item_appointment, parent, false));
    }

    @Override
    public void onBindViewHolder(@NonNull VH h, int position) {
        Appointment a = items.get(position);
        h.place.setText(a.placeName);
        String loc = a.location();
        if (!a.category.isEmpty()) loc = a.category + (loc.isEmpty() ? "" : " · " + loc);
        h.location.setText(loc);
        h.location.setVisibility(loc.isEmpty() ? View.GONE : View.VISIBLE);
        h.queue.setText(a.queueCode);
        h.date.setText(DateUtils.listDate(a.date));
        h.time.setText(timeOf(a));
        Ui.statusPill(h.status, a.status);
        ImageLoader.load(h.image, a.placeImageUrl, R.drawable.place_placeholder);
        boolean muted = "cancelled".equals(a.status) || "missed".equals(a.status);
        h.itemView.setAlpha(muted ? 0.78f : 1f);
        if (a.canCancel) {
            h.cancel.setVisibility(View.VISIBLE);
            h.cancel.setOnClickListener(v -> listener.onCancel(a));
        } else {
            h.cancel.setVisibility(View.GONE);
            h.cancel.setOnClickListener(null);
        }
    }

    /** Appointment time in the app language (server label as fallback). */
    public static String timeOf(Appointment a) {
        String t = DateUtils.timeLabel(a.time);
        return t.isEmpty() ? a.timeLabel : t;
    }

    @Override
    public int getItemCount() {
        return items.size();
    }

    static class VH extends RecyclerView.ViewHolder {
        final ImageView image;
        final TextView place, location, status, queue, date, time;
        final View cancel;

        VH(View v) {
            super(v);
            image = v.findViewById(R.id.ivPlace);
            place = v.findViewById(R.id.tvPlace);
            location = v.findViewById(R.id.tvLocation);
            status = v.findViewById(R.id.tvStatus);
            queue = v.findViewById(R.id.tvQueue);
            date = v.findViewById(R.id.tvDate);
            time = v.findViewById(R.id.tvTime);
            cancel = v.findViewById(R.id.btnCancel);
        }
    }
}
