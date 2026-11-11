<?php
/**
 * Place helpers shared by manager "My place", manager signup and admin "Add manager".
 */
require_once __DIR__ . '/categories.php';

/**
 * Read + validate place fields from a form. $prefix lets signup.php use "manager_place_name" etc.
 * Returns [array $data, string $error] ($error === '' when valid).
 */
function smartq_place_input(mysqli $conn, array $post, string $prefix = '', array $names = []): array
{
    $field = fn(string $key) => trim((string)($post[$names[$key] ?? ($prefix . $key)] ?? ''));
    $data = [
        'place_name' => $field('place_name'),
        'category' => smartq_clean_category($field('category')),
        'city' => $field('city'),
        'address' => $field('address'),
        'description' => $field('description'),
    ];
    if ($data['place_name'] === '' || $data['category'] === '' || $data['city'] === '') {
        return [$data, 'Please fill place name, category, and city.'];
    }
    if (!in_array($data['category'], smartq_categories($conn), true)) {
        return [$data, 'Please choose a category from the list.'];
    }
    if (mb_strlen($data['place_name']) > 150 || mb_strlen($data['city']) > 80 || mb_strlen($data['address']) > 255) {
        return [$data, 'Place name, city or address is too long.'];
    }
    return [$data, ''];
}

/** Insert a place for a manager (caller handles the transaction). Returns the new place id. */
function smartq_insert_place(mysqli $conn, int $managerId, array $d, ?string $image = null): int
{
    $stmt = $conn->prepare("INSERT INTO places (manager_id, place_name, category, city, address, description, place_image, active)
                            VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
    $stmt->bind_param('issssss', $managerId, $d['place_name'], $d['category'], $d['city'], $d['address'], $d['description'], $image);
    if (!$stmt->execute()) {
        throw new RuntimeException('Failed to save the place.');
    }
    return (int)$stmt->insert_id;
}

/** The (single) place a manager owns, or null. */
function smartq_manager_place(mysqli $conn, int $managerId): ?array
{
    $stmt = $conn->prepare("SELECT * FROM places WHERE manager_id = ? ORDER BY id ASC LIMIT 1");
    $stmt->bind_param('i', $managerId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}
