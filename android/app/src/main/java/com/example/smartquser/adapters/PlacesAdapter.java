package com.example.smartquser.adapters;

import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.ImageView;
import android.widget.TextView;

import androidx.annotation.NonNull;
import androidx.recyclerview.widget.RecyclerView;

import com.example.smartquser.R;
import com.example.smartquser.models.Place;
import com.example.smartquser.utils.ImageLoader;
import com.example.smartquser.utils.Ui;

import java.util.ArrayList;
import java.util.List;

public class PlacesAdapter extends RecyclerView.Adapter<PlacesAdapter.VH> {

    public interface Listener {
        void onPlaceClicked(Place place, View sharedImage);
    }

    private final List<Place> items = new ArrayList<>();
    private final Listener listener;

    public PlacesAdapter(Listener listener) {
        this.listener = listener;
        setHasStableIds(true);
    }

    public void submit(List<Place> places) {
        items.clear();
        items.addAll(places);
        notifyDataSetChanged();
    }

    @Override
    public long getItemId(int position) {
        return items.get(position).id;
    }

    @NonNull
    @Override
    public VH onCreateViewHolder(@NonNull ViewGroup parent, int viewType) {
        return new VH(LayoutInflater.from(parent.getContext()).inflate(R.layout.item_place, parent, false));
    }

    @Override
    public void onBindViewHolder(@NonNull VH h, int position) {
        Place p = items.get(position);
        h.name.setText(p.name);
        h.location.setText(p.location());
        h.description.setText(p.description);
        h.description.setVisibility(p.description.isEmpty() ? View.GONE : View.VISIBLE);
        h.category.setText(p.category);
        h.categoryIcon.setImageResource(Ui.categoryIcon(p.category));
        h.categoryPill.setVisibility(p.category.isEmpty() ? View.GONE : View.VISIBLE);
        ImageLoader.load(h.image, p.imageUrl, R.drawable.place_placeholder);
        View.OnClickListener click = v -> listener.onPlaceClicked(p, h.image);
        h.itemView.setOnClickListener(click);
        h.book.setOnClickListener(click);
    }

    @Override
    public int getItemCount() {
        return items.size();
    }

    public static class VH extends RecyclerView.ViewHolder {
        final ImageView image, categoryIcon;
        final TextView name, location, description, category;
        final View categoryPill, book;

        VH(View v) {
            super(v);
            image = v.findViewById(R.id.ivPlace);
            categoryIcon = v.findViewById(R.id.ivCategory);
            name = v.findViewById(R.id.tvName);
            location = v.findViewById(R.id.tvLocation);
            description = v.findViewById(R.id.tvDescription);
            category = v.findViewById(R.id.tvCategory);
            categoryPill = v.findViewById(R.id.categoryPill);
            book = v.findViewById(R.id.btnBook);
        }
    }
}
