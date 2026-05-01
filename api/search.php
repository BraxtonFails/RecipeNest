<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/db.php';

$q    = trim($_GET['q'] ?? '');
$type = $_GET['type'] ?? 'recipes';

if ($q === '') {
    echo json_encode(['results' => []]);
    exit;
}

$like = '%' . $q . '%';
$results = [];

if ($type === 'recipes') {
    // Search recipes by title OR ingredient name
    $stmt = $conn->prepare("
        SELECT DISTINCT r.RecipeID, r.Title, r.Difficulty, r.TotalCookTime,
               m.MediaURL,
               COUNT(DISTINCT l.UserID) AS LikeCount
        FROM recipes r
        LEFT JOIN recipeingredients ri ON ri.RecipeID = r.RecipeID
        LEFT JOIN ingredients i ON i.IngredientID = ri.IngredientID
        LEFT JOIN media m ON m.RecipeID = r.RecipeID
            AND m.MediaType = 'image'
            AND m.OrderIndex = (
                SELECT MIN(m2.OrderIndex) FROM media m2
                WHERE m2.RecipeID = r.RecipeID AND m2.MediaType = 'image'
            )
        LEFT JOIN likes l ON l.RecipeID = r.RecipeID
        WHERE r.Title LIKE ? OR i.IngredientName LIKE ?
        GROUP BY r.RecipeID
        ORDER BY LikeCount DESC, r.DateCreation DESC
        LIMIT 30
    ");
    $stmt->bind_param('ss', $like, $like);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as $r) {
        $results[] = [
            'type'       => 'recipe',
            'id'         => (int)$r['RecipeID'],
            'title'      => $r['Title'],
            'difficulty' => $r['Difficulty'],
            'cookTime'   => $r['TotalCookTime'],
            'likes'      => (int)$r['LikeCount'],
            'image'      => $r['MediaURL'],
        ];
    }

} elseif ($type === 'hashtags') {
    // Search hashtags and return recipes tagged with matching hashtag
    $stmt = $conn->prepare("
        SELECT DISTINCT h.HashtagID, h.TagName,
               COUNT(DISTINCT rh.RecipeID) AS RecipeCount
        FROM hashtags h
        LEFT JOIN recipehashtags rh ON rh.HashtagID = h.HashtagID
        WHERE h.TagName LIKE ?
        GROUP BY h.HashtagID
        ORDER BY RecipeCount DESC
        LIMIT 30
    ");
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as $r) {
        $results[] = [
            'type'        => 'hashtag',
            'id'          => (int)$r['HashtagID'],
            'name'        => $r['TagName'],
            'recipeCount' => (int)$r['RecipeCount'],
        ];
    }

} elseif ($type === 'users') {
    // Search users by username or display name
    $stmt = $conn->prepare("
        SELECT UserID, Username, Name, Bio, ProfilePictureURL
        FROM users
        WHERE Username LIKE ? OR Name LIKE ?
        ORDER BY Username ASC
        LIMIT 30
    ");
    $stmt->bind_param('ss', $like, $like);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as $r) {
        $results[] = [
            'type'    => 'user',
            'id'      => (int)$r['UserID'],
            'username'=> $r['Username'],
            'name'    => $r['Name'],
            'bio'     => $r['Bio'],
            'avatar'  => $r['ProfilePictureURL'],
        ];
    }
}

echo json_encode(['results' => $results]);
