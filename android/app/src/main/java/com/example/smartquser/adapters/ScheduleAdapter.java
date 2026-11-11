package com.example.smartquser.adapters;

import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.TextView;

import androidx.annotation.NonNull;
import androidx.core.content.ContextCompat;
import androidx.recyclerview.widget.RecyclerView;

import com.example.smartquser.R;
import com.example.smartquser.models.Schedule;
import com.example.smartquser.utils.DateUtils;
import com.example.smartquser.utils.Lang;
import com.google.android.material.progressindicator.LinearProgressIndicator;

import java.util.ArrayList;
import java.util.List;

public class ScheduleAdapter extends RecyclerView.Adapter<ScheduleAdapter.VH> {

    public interface Listener {
        void onScheduleClicked(Schedule schedule);
    }

    private final List<Schedule> items = new ArrayList<>();
    private final Listener listener;

    public ScheduleAdapter(Listener listener) {
        this.listener = listener;
    }

    public void submit(List<Schedule> list) {
        items.clear();
        items.addAll(list);
        notifyDataSetChanged();
    }

    @NonNull
    @Override
    public VH onCreateViewHolder(@NonNull ViewGroup parent, int viewType) {
        return new VH(LayoutInflater.from(parent.getContext()).inflate(R.layout.item_schedule, parent, false));
    }

    @Override
    public void onBindViewHolder(@NonNull VH h, int position) {
        Schedule s = items.get(position);
        h.weekday.setText(DateUtils.weekdayShort(s.date).toUpperCase(DateUtils.uiLocale()));
        h.day.setText(DateUtils.dayOfMonth(s.date));
        h.month.setText(DateUtils.monthShort(s.date));
        h.date.setText(DateUtils.friendlyDate(s.date));
        h.time.setText(DateUtils.timeRange(s.startTime, s.endTime));

        android.content.Context c = h.itemView.getContext();
        int colorRes;
        String label;
        if (s.capacity > 0 && s.seatsLeft * 5 <= s.capacity) {
            colorRes = s.seatsLeft <= 2 ? R.color.danger : R.color.warning;
            label = s.seatsLeft == 1 ? c.getString(R.string.details_last_seat)
                    : c.getString(R.string.details_few_seats, Lang.num(s.seatsLeft));
        } else {
            colorRes = R.color.teal_500;
            label = c.getString(R.string.details_seats_of, Lang.num(s.seatsLeft), Lang.num(s.capacity));
        }
        int color = ContextCompat.getColor(c, colorRes);
        h.seats.setText(label);
        h.seats.setTextColor(color);
        h.bar.setIndicatorColor(color);
        h.bar.setProgressCompat(0, false);
        int fill = s.fillPercent();
        h.bar.postDelayed(() -> h.bar.setProgressCompat(Math.max(4, fill), true), 250 + position * 70L);
        h.itemView.setOnClickListener(v -> listener.onScheduleClicked(s));
    }

    @Override
    public int getItemCount() {
        return items.size();
    }

    static class VH extends RecyclerView.ViewHolder {
        final TextView weekday, day, month, date, time, seats;
        final LinearProgressIndicator bar;

        VH(View v) {
            super(v);
            weekday = v.findViewById(R.id.tvWeekday);
            day = v.findViewById(R.id.tvDay);
            month = v.findViewById(R.id.tvMonth);
            date = v.findViewById(R.id.tvDate);
            time = v.findViewById(R.id.tvTime);
            seats = v.findViewById(R.id.tvSeats);
            bar = v.findViewById(R.id.seatsBar);
        }
    }
}
