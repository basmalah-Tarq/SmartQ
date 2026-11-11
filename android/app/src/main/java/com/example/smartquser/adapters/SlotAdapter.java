package com.example.smartquser.adapters;

import android.content.Context;
import android.graphics.Paint;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.TextView;

import androidx.annotation.NonNull;
import androidx.core.content.ContextCompat;
import androidx.recyclerview.widget.RecyclerView;

import com.example.smartquser.R;
import com.example.smartquser.models.Slot;
import com.example.smartquser.utils.DateUtils;
import com.example.smartquser.utils.Ui;
import com.google.android.material.card.MaterialCardView;

import java.util.ArrayList;
import java.util.List;

/** Time-slot grid with available / selected / booked / passed states. */
public class SlotAdapter extends RecyclerView.Adapter<SlotAdapter.VH> {

    public interface Listener {
        void onSlotSelected(Slot slot);
    }

    private final List<Slot> items = new ArrayList<>();
    private final Listener listener;
    private String selectedTime;

    public SlotAdapter(Listener listener) {
        this.listener = listener;
    }

    public void submit(List<Slot> slots) {
        items.clear();
        items.addAll(slots);
        // keep the selection only if that time is still free
        if (selectedTime != null) {
            boolean stillFree = false;
            for (Slot s : slots) if (s.time.equals(selectedTime) && s.available) stillFree = true;
            if (!stillFree) selectedTime = null;
        }
        notifyDataSetChanged();
    }

    public Slot getSelected() {
        for (Slot s : items) if (s.time.equals(selectedTime)) return s;
        return null;
    }

    /**
     * Selects a free slot by its "HH:MM:SS" time (used by "Help me choose").
     * Returns its adapter position, or -1 when that time is not free.
     */
    public int select(String time) {
        int pos = indexOf(time);
        if (pos < 0 || !items.get(pos).available) return -1;
        int old = indexOf(selectedTime);
        selectedTime = time;
        if (old >= 0) notifyItemChanged(old);
        notifyItemChanged(pos);
        listener.onSlotSelected(items.get(pos));
        return pos;
    }

    /** First free slot time, or null. */
    public String firstAvailableTime() {
        for (Slot s : items) if (s.available) return s.time;
        return null;
    }

    public void clearSelection() {
        selectedTime = null;
        notifyDataSetChanged();
    }

    @NonNull
    @Override
    public VH onCreateViewHolder(@NonNull ViewGroup parent, int viewType) {
        return new VH(LayoutInflater.from(parent.getContext()).inflate(R.layout.item_slot, parent, false));
    }

    @Override
    public void onBindViewHolder(@NonNull VH h, int position) {
        Slot s = items.get(position);
        Context c = h.itemView.getContext();
        boolean selected = s.time.equals(selectedTime);
        h.time.setText(label(s));
        h.time.setPaintFlags(h.time.getPaintFlags() & ~Paint.STRIKE_THRU_TEXT_FLAG);
        h.card.setAlpha(1f);

        if (selected) {
            h.card.setCardBackgroundColor(ContextCompat.getColor(c, R.color.blue_600));
            h.card.setStrokeColor(ContextCompat.getColor(c, R.color.blue_600));
            h.card.setCardElevation(Ui.dp(c, 6));
            h.time.setTextColor(ContextCompat.getColor(c, R.color.white));
            h.state.setTextColor(ContextCompat.getColor(c, R.color.white_80));
            h.state.setText(R.string.book_slot_selected);
        } else if (s.available) {
            h.card.setCardBackgroundColor(ContextCompat.getColor(c, R.color.surface));
            h.card.setStrokeColor(ContextCompat.getColor(c, R.color.border));
            h.card.setCardElevation(0);
            h.time.setTextColor(ContextCompat.getColor(c, R.color.ink));
            h.state.setTextColor(ContextCompat.getColor(c, R.color.teal_500));
            h.state.setText(R.string.book_slot_free);
        } else {
            boolean past = "past".equals(s.reason);
            h.card.setCardBackgroundColor(ContextCompat.getColor(c, past ? R.color.bg : R.color.surface_alt));
            h.card.setStrokeColor(ContextCompat.getColor(c, R.color.border));
            h.card.setCardElevation(0);
            h.time.setTextColor(ContextCompat.getColor(c, R.color.muted));
            h.time.setPaintFlags(h.time.getPaintFlags() | Paint.STRIKE_THRU_TEXT_FLAG);
            h.state.setTextColor(ContextCompat.getColor(c, R.color.muted));
            h.state.setText(past ? R.string.book_slot_passed
                    : "full".equals(s.reason) ? R.string.book_slot_full : R.string.book_slot_booked);
            if (past) h.card.setAlpha(0.6f);
        }

        h.card.setEnabled(s.available);
        h.card.setOnClickListener(s.available ? v -> {
            int old = indexOf(selectedTime);
            selectedTime = s.time;
            if (old >= 0) notifyItemChanged(old);
            notifyItemChanged(h.getBindingAdapterPosition());
            Ui.pop(h.card);
            listener.onSlotSelected(s);
        } : null);
        h.card.setContentDescription(label(s) + ", " + h.state.getText());
    }

    /** Time in the app language; the server label is the fallback. */
    private static String label(Slot s) {
        String l = DateUtils.timeLabel(s.time);
        return l.isEmpty() ? s.label : l;
    }

    private int indexOf(String time) {
        if (time == null) return -1;
        for (int i = 0; i < items.size(); i++) if (items.get(i).time.equals(time)) return i;
        return -1;
    }

    @Override
    public int getItemCount() {
        return items.size();
    }

    static class VH extends RecyclerView.ViewHolder {
        final MaterialCardView card;
        final TextView time, state;

        VH(View v) {
            super(v);
            card = (MaterialCardView) v;
            time = v.findViewById(R.id.tvSlotTime);
            state = v.findViewById(R.id.tvSlotState);
        }
    }
}
