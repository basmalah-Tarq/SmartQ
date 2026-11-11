package com.example.smartquser.models;

import org.json.JSONObject;

public class Place {
    public int id;
    public String name;
    public String category;
    public String city;
    public String address;
    public String description;
    public String imageUrl;

    public static Place fromJson(JSONObject o) {
        Place p = new Place();
        p.id = o.optInt("id");
        p.name = Json.str(o, "place_name");
        p.category = Json.str(o, "category");
        p.city = Json.str(o, "city");
        p.address = Json.str(o, "address");
        p.description = Json.str(o, "description");
        p.imageUrl = Json.str(o, "place_image_url");
        return p;
    }

    public String location() {
        if (address.isEmpty()) return city;
        if (city.isEmpty()) return address;
        return address + ", " + city;
    }
}
