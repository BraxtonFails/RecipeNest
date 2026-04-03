<?php
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

require_once '../config/db.php';

// Stats
$total_recipes  = $conn->query('SELECT COUNT(*) FROM recipes')->fetch_row()[0];
$total_users    = $conn->query('SELECT COUNT(*) FROM users')->fetch_row()[0];
$new_today      = $conn->query('SELECT COUNT(*) FROM recipes WHERE DATE(DateCreation) = CURDATE()')->fetch_row()[0];

// Recent recipes
$recipes = $conn->query('
    SELECT r.RecipeID, r.Title, r.DateCreation, r.Difficulty, u.Name AS Author
    FROM recipes r
    JOIN users u ON u.UserID = r.UserID
    ORDER BY r.DateCreation DESC
    LIMIT 20
')->fetch_all(MYSQLI_ASSOC);

// All users
$users = $conn->query('
    SELECT u.UserID, u.Name, u.Username, u.Email, u.DateCreation,
           COUNT(r.RecipeID) AS RecipeCount
    FROM users u
    LEFT JOIN recipes r ON r.UserID = u.UserID
    GROUP BY u.UserID
    ORDER BY u.DateCreation DESC
')->fetch_all(MYSQLI_ASSOC);

$reports = 0;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin Dashboard – RecipeNest</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../style/recipenest.css">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
  <style>
    body { background: var(--rn-bg); color: var(--rn-ink); }
    .admin-shell { min-height: 100vh; }
    .sidebar { width: 240px; background: #3d3632; color: #e8e4df; flex-shrink: 0; }
    .sidebar a { color: #e8e4df; text-decoration: none; }
    .sidebar .nav-link { border-radius: 12px; padding: 10px 12px; color: #e8e4df; }
    .sidebar .nav-link:hover { background: rgba(255,255,255,.1); color: #fff; }
    .sidebar .nav-link.active { background: var(--rn-accent); color: var(--rn-ink); }
    .sidebar .border-secondary { border-color: rgba(255,255,255,.2) !important; }
    .topbar { background: var(--rn-panel); border-bottom: 1px solid rgba(0,0,0,.08); }
    .card { background: #f8f6f2; border: 1px solid rgba(0,0,0,.06); border-radius: var(--rn-radius); box-shadow: 0 4px 12px rgba(0,0,0,.06); }
    .table { color: var(--rn-ink); }
    .table thead { background: var(--rn-panel); }
    .table > :not(caption) > * > * { vertical-align: middle; }
    .badge.bg-success { background: rgba(125,191,119,.5) !important; color: var(--rn-ink); }
    .badge.bg-danger  { background: rgba(220,80,80,.2) !important; color: #a00; }
    .section-tab { display: none; }
    .section-tab.active { display: block; }
  </style>
</head>
<body>
<div class="d-flex admin-shell">

  <!-- Sidebar -->
  <aside class="sidebar p-3 d-none d-md-flex flex-column">
    <div class="d-flex align-items-center gap-2 mb-4">
      <i class="bi bi-shield-lock fs-4"></i>
      <span class="fw-bold">RecipeNest Admin</span>
    </div>
    <nav class="nav nav-pills flex-column gap-1">
      <a class="nav-link active" href="#" onclick="showTab('overview')"><i class="bi bi-speedometer2 me-2"></i>Overview</a>
      <a class="nav-link" href="#" onclick="showTab('recipes')"><i class="bi bi-journal-text me-2"></i>Recipes</a>
      <a class="nav-link" href="#" onclick="showTab('users')"><i class="bi bi-people me-2"></i>Users</a>
      <a class="nav-link" href="#" onclick="showTab('reports')"><i class="bi bi-flag me-2"></i>Reports</a>
    </nav>
    <div class="mt-auto pt-3 border-top border-secondary d-flex flex-column gap-1">
      <a class="nav-link" href="../index.php" target="_blank"><i class="bi bi-eye me-2"></i>View as user</a>
      <a class="nav-link" href="../api/auth/logout.php"><i class="bi bi-box-arrow-left me-2"></i>Logout</a>
    </div>
  </aside>

  <div class="flex-grow-1 d-flex flex-column">

    <!-- Topbar -->
    <header class="topbar px-4 py-2 d-flex align-items-center justify-content-between">
      <span class="fw-semibold">Admin Dashboard</span>
      <div class="d-flex align-items-center gap-3">
        <span class="text-muted small">Logged in as <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
        <a href="upload.php" class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-plus-lg me-1"></i>Add Recipe
        </a>
        <a href="../index.php" target="_blank" class="btn btn-rn-primary btn-sm">
          <i class="bi bi-eye me-1"></i>View as user
        </a>
      </div>
    </header>

    <main class="container-fluid py-4 px-4">

      <?php if (isset($_GET['msg'])): ?>
      <div class="alert alert-success"><?php echo htmlspecialchars($_GET['msg']); ?></div>
      <?php endif; ?>

      <!-- OVERVIEW TAB -->
      <div id="tab-overview" class="section-tab active">
        <h1 class="h4 mb-4">Overview</h1>

        <div class="row g-3 mb-4">
          <div class="col-6 col-md-3">
            <div class="card p-3">
              <div class="text-muted small">Total Recipes</div>
              <div class="fs-3 fw-bold"><?php echo $total_recipes; ?></div>
              <i class="bi bi-journal-text text-muted"></i>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="card p-3">
              <div class="text-muted small">Users</div>
              <div class="fs-3 fw-bold"><?php echo $total_users; ?></div>
              <i class="bi bi-people text-muted"></i>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="card p-3">
              <div class="text-muted small">Pending Reports</div>
              <div class="fs-3 fw-bold"><?php echo $reports; ?></div>
              <i class="bi bi-flag text-muted"></i>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="card p-3">
              <div class="text-muted small">New Today</div>
              <div class="fs-3 fw-bold"><?php echo $new_today; ?></div>
              <i class="bi bi-graph-up text-muted"></i>
            </div>
          </div>
        </div>

        <!-- Recent recipes preview -->
        <div class="card">
          <div class="p-3 border-bottom fw-semibold">Recent Recipes</div>
          <div class="table-responsive">
            <table class="table mb-0">
              <thead><tr><th>Title</th><th>Author</th><th>Difficulty</th><th>Date</th></tr></thead>
              <tbody>
                <?php foreach (array_slice($recipes, 0, 5) as $r): ?>
                <tr>
                  <td><a href="recipe.php?id=<?php echo $r['RecipeID']; ?>"><?php echo htmlspecialchars($r['Title']); ?></a></td>
                  <td><?php echo htmlspecialchars($r['Author']); ?></td>
                  <td><?php echo htmlspecialchars($r['Difficulty'] ?? '—'); ?></td>
                  <td class="text-muted small"><?php echo date('M j, Y', strtotime($r['DateCreation'])); ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- RECIPES TAB -->
      <div id="tab-recipes" class="section-tab">
        <h1 class="h4 mb-4">All Recipes</h1>
        <div class="card">
          <div class="table-responsive">
            <table class="table mb-0">
              <thead><tr><th>Title</th><th>Author</th><th>Difficulty</th><th>Date</th><th class="text-end">Actions</th></tr></thead>
              <tbody>
                <?php foreach ($recipes as $r): ?>
                <tr>
                  <td><a href="recipe.php?id=<?php echo $r['RecipeID']; ?>"><?php echo htmlspecialchars($r['Title']); ?></a></td>
                  <td><?php echo htmlspecialchars($r['Author']); ?></td>
                  <td><?php echo htmlspecialchars($r['Difficulty'] ?? '—'); ?></td>
                  <td class="text-muted small"><?php echo date('M j, Y', strtotime($r['DateCreation'])); ?></td>
                  <td class="text-end">
                    <a href="edit_recipe.php?id=<?php echo $r['RecipeID']; ?>&from=admin" class="btn btn-sm btn-outline-secondary">Edit</a>
                    <form method="POST" action="../api/admin/delete_recipe.php" class="d-inline" onsubmit="return confirm('Delete this recipe?')">
                      <input type="hidden" name="recipe_id" value="<?php echo $r['RecipeID']; ?>">
                      <button class="btn btn-sm btn-outline-danger">Delete</button>
                    </form>
                  </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$recipes): ?>
                <tr><td colspan="5" class="text-muted text-center py-3">No recipes yet.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- USERS TAB -->
      <div id="tab-users" class="section-tab">
        <h1 class="h4 mb-4">All Users</h1>
        <div class="card">
          <div class="table-responsive">
            <table class="table mb-0">
              <thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Recipes</th><th>Joined</th><th class="text-end">Actions</th></tr></thead>
              <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                  <td><?php echo htmlspecialchars($u['Name'] ?? '—'); ?></td>
                  <td class="text-muted">@<?php echo htmlspecialchars($u['Username']); ?></td>
                  <td><?php echo htmlspecialchars($u['Email']); ?></td>
                  <td><?php echo $u['RecipeCount']; ?></td>
                  <td class="text-muted small"><?php echo date('M j, Y', strtotime($u['DateCreation'])); ?></td>
                  <td class="text-end">
                    <form method="POST" action="../api/admin/delete_user.php" class="d-inline" onsubmit="return confirm('Delete this user and all their recipes?')">
                      <input type="hidden" name="user_id" value="<?php echo $u['UserID']; ?>">
                      <button class="btn btn-sm btn-outline-danger">Delete</button>
                    </form>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- REPORTS TAB -->
      <div id="tab-reports" class="section-tab">
        <h1 class="h4 mb-4">Reports</h1>
        <div class="card p-4 text-muted text-center">
          No reports functionality yet — coming soon.
        </div>
      </div>

    </main>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  function showTab(name) {
    document.querySelectorAll('.section-tab').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.sidebar .nav-link').forEach(el => el.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    event.target.classList.add('active');
  }
</script>
</body>
</html>
