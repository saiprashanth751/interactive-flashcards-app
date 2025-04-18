<?php
session_start();

if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit();
}

// By doing this we are able to import pdo...
include '../includes/db.php';
$user_id = $_SESSION['user_id'];

// We are fetching the categories here...
try {
  $stmt = $pdo->prepare("SELECT id, name FROM categories ORDER BY name ASC");
  $stmt->execute();
  $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  error_log("Error fetching categories: " . $e->getMessage());
  $categories = [];
  $error = "Failed to load categories. Please try again later.";
}

// The below represents the usage of null coalescing operator.
$selected_categories = $_POST['categories'] ?? [];
$selected_status = $_POST['status'] ?? 'all';
$sort_by = $_POST['sort_by'] ?? 'none';
$sort_order = $_POST['sort_order'] ?? 'DESC';

//Note: Here, we are creating the SQL query dynamically considering the user filters.
$where_clause = "";
$params = [];

if (!empty($selected_categories)) {
  $selected_categories = array_map('intval', $selected_categories);
  $placeholders = implode(',', array_fill(0, count($selected_categories), '?'));
  $where_clause .= " WHERE d.category_id IN ($placeholders)";
  $params = array_merge($params, $selected_categories);
}

if ($selected_status !== 'all') {
  $where_clause .= empty($where_clause) ? " WHERE " : " AND ";
  $where_clause .= "udm.is_mastered = ?";
  $params[] = $selected_status === 'completed' ? 1 : 0;
}

$order_clause = '';
if ($sort_by !== 'none') {
  $sort_column = $sort_by === 'last_studied' ? 'd.last_studied' : 'd.created_at';
  $sort_direction = $sort_order === 'ASC' ? 'ASC' : 'DESC';
  $order_clause = "ORDER BY $sort_column $sort_direction";
  if ($sort_by === 'last_studied') {
    $order_clause = "ORDER BY d.last_studied IS NULL, $sort_column $sort_direction";
  }
}

// The dynamic query is used here to fetch the decks...
try {
  $stmt = $pdo->prepare("
        SELECT d.id, d.title, d.description, d.created_at, d.last_studied, d.reminder_interval, 
               COALESCE(udm.is_mastered, 0) AS is_mastered, c.name AS category_name
        FROM decks d
        LEFT JOIN categories c ON d.category_id = c.id
        LEFT JOIN user_deck_mastery udm ON d.id = udm.deck_id AND udm.user_id = ?
        $where_clause
        $order_clause
    ");
  $params = array_merge([$user_id], $params);
  $stmt->execute($params);
  $decks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  error_log("Error fetching decks: " . $e->getMessage());
  $decks = [];
  $error = "Failed to load decks. Please try again later.";
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>View Decks</title>
  <link href="../src/output.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    body {
      font-family: 'Poppins', sans-serif;
      background-color: #f3f4f6;
      color: #374151;
      min-height: 100vh;
    }

    .header-bg {
      background: linear-gradient(120deg, #3b82f6, #8b5cf6);
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .btn-primary {
      background: #8b5cf6;
      color: white;
      padding: 0.5rem 1rem;
      border-radius: 0.5rem;
      transition: background 0.3s ease;
      display: inline-block;
    }

    .btn-primary:hover {
      background: #7c3aed;
    }

    .btn-secondary {
      @apply text-blue-600 hover:text-blue-800 transition-all duration-300;
    }

    .sidebar {
      background: #ffffff;
      border: 1px solid #e5e7eb;
      max-width: 300px;
      height: fit-content;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
      padding: 1.5rem;
    }

    .deck-card {
      perspective: 1000px;
      height: 250px;
    }

    .deck-inner {
      position: relative;
      width: 100%;
      height: 100%;
      transition: transform 0.6s;
      transform-style: preserve-3d;
      border-radius: 12px;
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
      border: 1px solid rgba(0, 0, 0, 0.05);
    }

    .deck-card:hover .deck-inner {
      transform: rotateY(180deg);
    }

    .deck-front,
    .deck-back {
      position: absolute;
      width: 100%;
      height: 100%;
      backface-visibility: hidden;
      border-radius: 12px;
      padding: 1.5rem;
    }

    .deck-front {
      background: rgba(255, 255, 255, 0.9);
      color: #1f2937;
    }

    .deck-back {
      background: #e5e7eb;
      color: #4b5563;
      transform: rotateY(180deg);
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      text-align: center;
    }

    .deck-front h3 {
      color: #1f2937;
      font-size: 1.125rem;
      font-weight: 600;
    }

    .deck-front p,
    .deck-back p {
      color: #4b5563;
      font-size: 0.875rem;
    }

    .badge-completed {
      position: absolute;
      top: 0.5rem;
      right: 1rem;
      background: #10b981;
      color: white;
      padding: 0.25rem 0.75rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 600;
      z-index: 10;
    }
  </style>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.11.5/gsap.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.11.5/ScrollTrigger.min.js"></script>
</head>

<body>
  <!-- Header -->
  <header class="header-bg text-white p-6 sticky top-0 z-20">
    <div class="max-w-6xl mx-auto flex justify-between items-center">
      <h1 class="text-3xl font-bold tracking-wide">View Decks</h1>
      <div class="flex space-x-6">
        <a href="create_deck.php" class="btn-primary flex items-center"><i class="fas fa-plus mr-2"></i> Create Deck</a>
        <a href="index.php" class="btn-secondary flex items-center"><i class="fas fa-arrow-left mr-2"></i> Dashboard</a>
      </div>
    </div>
  </header>

  <!-- Main Content -->
  <main class="p-6">
    <div class="max-w-6xl mx-auto flex flex-col md:flex-row gap-6">
      <!-- Sidebar: Filters -->
      <aside class="sidebar w-full md:w-auto rounded-lg">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">Filters</h2>
        <?php if (isset($error)): ?>
          <p class="text-red-500 text-sm mb-4"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <form method="POST" action="view_decks.php">
          <div class="mb-4">
            <h3 class="text-lg font-medium text-gray-700 mb-2">Category</h3>
            <div class="space-y-2 max-h-40 overflow-y-auto">
              <!-- The fetched categories will loop here to appear on ui-->
              <?php foreach ($categories as $category): ?>
                <label class="flex items-center">
                  <input type="checkbox" name="categories[]" value="<?php echo $category['id']; ?>"
                    <?php echo in_array($category['id'], $selected_categories) ? 'checked' : ''; ?>
                    class="form-checkbox h-4 w-4 text-blue-600 rounded">
                  <span class="ml-2 text-gray-600 text-sm"><?php echo htmlspecialchars($category['name']); ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="mb-4">
            <h3 class="text-lg font-medium text-gray-700 mb-2">Status</h3>
            <div class="space-y-2">
              <label class="flex items-center">
                <input type="radio" name="status" value="all" <?php echo $selected_status === 'all' ? 'checked' : ''; ?>
                  class="form-radio h-4 w-4 text-blue-600">
                <span class="ml-2 text-gray-600 text-sm">All</span>
              </label>
              <label class="flex items-center">
                <input type="radio" name="status" value="completed" <?php echo $selected_status === 'completed' ? 'checked' : ''; ?>
                  class="form-radio h-4 w-4 text-blue-600">
                <span class="ml-2 text-gray-600 text-sm">Completed</span>
              </label>
              <label class="flex items-center">
                <input type="radio" name="status" value="incomplete" <?php echo $selected_status === 'incomplete' ? 'checked' : ''; ?>
                  class="form-radio h-4 w-4 text-blue-600">
                <span class="ml-2 text-gray-600 text-sm">Incomplete</span>
              </label>
            </div>
          </div>
          <div class="mb-4">
            <h3 class="text-lg font-medium text-gray-700 mb-2">Sort By</h3>
            <select name="sort_by" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md text-gray-600 focus:ring-blue-500 focus:border-blue-500 text-sm">
              <option value="none" <?php echo $sort_by === 'none' ? 'selected' : ''; ?>>None</option>
              <option value="created_at" <?php echo $sort_by === 'created_at' ? 'selected' : ''; ?>>Created Date</option>
              <option value="last_studied" <?php echo $sort_by === 'last_studied' ? 'selected' : ''; ?>>Last Studied</option>
            </select>
          </div>
          <div class="mb-4">
            <h3 class="text-lg font-medium text-gray-700 mb-2">Sort Order</h3>
            <div class="space-y-2">
              <label class="flex items-center">
                <input type="radio" name="sort_order" value="ASC" <?php echo $sort_order === 'ASC' ? 'checked' : ''; ?>
                  class="form-radio h-4 w-4 text-blue-600">
                <span class="ml-2 text-gray-600 text-sm">Ascending</span>
              </label>
              <label class="flex items-center">
                <input type="radio" name="sort_order" value="DESC" <?php echo $sort_order === 'DESC' ? 'checked' : ''; ?>
                  class="form-radio h-4 w-4 text-blue-600">
                <span class="ml-2 text-gray-600 text-sm">Descending</span>
              </label>
            </div>
          </div>
          <div class="flex space-x-4">
            <button type="submit" class="btn-primary w-full">Apply Filters</button>
            <a href="view_decks.php" class="btn-secondary text-sm self-center">Clear</a>
          </div>
        </form>
      </aside>

      <!-- Decks List -->
      <div class="w-full md:w-3/4">
        <div class="bg-white p-6 rounded-lg shadow-md">
          <h2 class="text-2xl font-semibold text-gray-800 mb-6">All Decks</h2>
          <?php if (empty($decks)): ?>
            <p class="text-gray-600 text-lg">No decks found. Create one to get started!</p>
          <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-10" style="gap: 40px;">
              <?php foreach ($decks as $deck): ?>
                <div class="deck-card animated-card">
                  <div class="deck-inner">
                    <!-- Front Side -->
                    <div class="deck-front">
                      <?php if ($deck['is_mastered']): ?>
                        <span class="badge-completed">Completed</span>
                      <?php endif; ?>
                      <h3 class="text-lg font-semibold mt-6"><?php echo htmlspecialchars($deck['title']); ?></h3>
                      <p class="mt-2"><?php echo htmlspecialchars($deck['description'] ?? 'No description'); ?></p>
                      <p class="mt-2">Category: <?php echo htmlspecialchars($deck['category_name'] ?? 'None'); ?></p>
                      <p class="mt-1">Created: <?php echo date('M j, Y', strtotime($deck['created_at'])); ?></p>
                      <p class="mt-1">Last Studied: <?php echo $deck['last_studied'] ? date('M j, Y', strtotime($deck['last_studied'])) : 'Never'; ?></p>
                    </div>
                    <!-- Back Side -->
                    <div class="deck-back">
                      <p class="text-lg font-semibold">Ready to Study?</p>
                      <p class="mt-2">Click to dive into "<?php echo htmlspecialchars($deck['title']); ?>"!</p>
                      <a href="study.php?deck_id=<?php echo $deck['id']; ?>" class="btn-primary mt-4">Study Now</a>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>

  <!-- GSAP Animations -->
  <script>
    gsap.registerPlugin(ScrollTrigger);

    gsap.utils.toArray('.animated-card').forEach(card => {
      gsap.from(card, {
        scrollTrigger: {
          trigger: card,
          start: 'top 85%',
          toggleActions: 'play none none none',
          once: true
        },
        y: 30,
        opacity: 0,
        duration: 0.6,
        ease: 'power2.out'
      });
    });
  </script>
</body>

</html>