<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include '../includes/db.php';

$user_id = $_SESSION['user_id'];
$session_id = $_GET['session_id'] ?? 0;

if ($session_id <= 0) {
    header("Location: custom_study_sessions.php?error=no_session");
    exit();
}

// Fetch the custom study session table
$stmt = $pdo->prepare("SELECT name, time_limit FROM custom_study_sessions WHERE id = ? AND user_id = ?");
$stmt->execute([$session_id, $user_id]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$session) {
    header("Location: custom_study_sessions.php?error=invalid_session");
    exit();
}

// Fetch categories
$stmt = $pdo->prepare("SELECT id, name FROM categories ORDER BY name ASC");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle filters and sorting 
$selected_categories = $_POST['categories'] ?? [];
$sort_by = $_POST['sort_by'] ?? 'none';
$sort_order = $_POST['sort_order'] ?? 'DESC';

$params = [$user_id]; 

// Build WHERE clause
$where_clause = "WHERE (d.user_id = ? OR sd.shared_with = ?)";
array_push($params, $user_id, $user_id); 

// Filter by categories
if (!empty($selected_categories)) {
    $selected_categories = array_map('intval', $selected_categories);
    $placeholders = implode(',', array_fill(0, count($selected_categories), '?'));
    $where_clause .= " AND d.category_id IN ($placeholders)";
    $params = array_merge($params, $selected_categories);
}

// Build ORDER BY clause
$order_clause = '';
if ($sort_by !== 'none' && $sort_by === 'created_at') {
    $sort_direction = $sort_order === 'ASC' ? 'ASC' : 'DESC';
    $order_clause = "ORDER BY d.created_at $sort_direction";
}

$query = "
    SELECT d.*, u.username AS owner_name, 
           COUNT(f.id) AS total_flashcards,
           c.name AS category_name
    FROM decks d
    LEFT JOIN users u ON d.user_id = u.id
    LEFT JOIN flashcards f ON d.id = f.deck_id
    LEFT JOIN shared_decks sd ON d.id = sd.deck_id AND sd.shared_with = ?
    LEFT JOIN categories c ON d.category_id = c.id
    $where_clause
    GROUP BY d.id
    $order_clause
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$decks = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Decks for Custom Study Session</title>
    <link href="../src/output.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-image: url('https://www.transparenttextures.com/patterns/subtle-grey.png');
            background-repeat: repeat;
            background-size: 200px 200px;
        }
        .btn-primary {
            @apply bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-4 py-2 rounded hover:from-purple-700 hover:to-indigo-700 transition duration-300;
        }
        .sticky-session-btn {
            position: fixed;
            bottom: 1rem;
            left: 50%;
            transform: translateX(-50%);
            z-index: 30;
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.11.5/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.11.5/ScrollTrigger.min.js"></script>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-purple-700 to-indigo-700 text-white p-6 shadow-lg sticky top-0 z-20">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <h1 class="text-3xl font-bold tracking-wide">Select Decks: <?php echo htmlspecialchars($session['name'] ?? 'Custom Session'); ?></h1>
            <a href="custom_study_sessions.php" class="text-sm hover:underline transition duration-300 flex items-center">
                <i class="fas fa-arrow-left mr-2"></i> Back
            </a>
        </div>
    </header>

    <div class="flex flex-1 max-w-7xl mx-auto">
        <!-- Sidebar Filters -->
        <aside class="w-64 bg-white p-6 border-r border-gray-200 hidden lg:block animated-section">
            <h2 class="text-2xl font-semibold text-gray-800 mb-6 flex items-center">
                <i class="fas fa-filter mr-2 text-purple-600"></i> Filters
            </h2>
            <form method="POST" action="select_decks_for_session.php?session_id=<?php echo $session_id; ?>" class="space-y-6">
                <!-- Categories Filter -->
                <div>
                    <h3 class="text-lg font-medium text-gray-700 mb-2 flex items-center">
                        <i class="fas fa-folder mr-2 text-gray-500"></i> Categories
                    </h3>
                    <div class="space-y-2 max-h-60 overflow-y-auto p-2 border border-gray-200 rounded-lg">
                        <?php foreach ($categories as $category): ?>
                            <label class="flex items-center p-2 hover:bg-gray-50 rounded">
                                <input type="checkbox" name="categories[]" value="<?php echo htmlspecialchars($category['id']); ?>"
                                    <?php echo in_array($category['id'], $selected_categories) ? 'checked' : ''; ?>
                                    class="form-checkbox h-4 w-4 text-purple-600">
                                <span class="ml-2 text-gray-700"><?php echo htmlspecialchars($category['name']); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Sort Options -->
                <div>
                    <h3 class="text-lg font-medium text-gray-700 mb-2 flex items-center">
                        <i class="fas fa-sort mr-2 text-gray-500"></i> Sort Options
                    </h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Sort By</label>
                            <select name="sort_by" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-purple-500 transition duration-300">
                                <option value="none" <?php echo $sort_by === 'none' ? 'selected' : ''; ?>>Default</option>
                                <option value="created_at" <?php echo $sort_by === 'created_at' ? 'selected' : ''; ?>>Creation Date</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Order</label>
                            <div class="space-y-2">
                                <label class="flex items-center">
                                    <input type="radio" name="sort_order" value="ASC" <?php echo $sort_order === 'ASC' ? 'checked' : ''; ?> class="form-radio h-4 w-4 text-purple-600">
                                    <span class="ml-2 text-gray-700">Ascending</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="radio" name="sort_order" value="DESC" <?php echo $sort_order === 'DESC' ? 'checked' : ''; ?> class="form-radio h-4 w-4 text-purple-600">
                                    <span class="ml-2 text-gray-700">Descending</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex space-x-3">
                    <button type="submit" class="btn-primary flex-1 flex items-center justify-center">
                        <i class="fas fa-check mr-2"></i> Apply
                    </button>
                    <a href="select_decks_for_session.php?session_id=<?php echo htmlspecialchars($session_id); ?>" class="flex-1 bg-gray-200 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-300 flex items-center justify-center">
                        <i class="fas fa-undo mr-2"></i> Reset
                    </a>
                </div>
            </form>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-6">
            <?php if (isset($_GET['error'])): ?>
                <div class="mb-6 animated-section">
                    <?php if ($_GET['error'] === 'no_decks_selected'): ?>
                        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4" role="alert">
                            <p>Please select at least one deck to start the session.</p>
                        </div>
                    <?php elseif ($_GET['error'] === 'database_error'): ?>
                        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4" role="alert">
                            <p>An error occurred while starting the session. Please try again later.</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Deck Selection Form -->
            <form id="deck-selection-form" action="start_custom_study.php" method="POST">
                <input type="hidden" name="session_id" value="<?php echo htmlspecialchars($session_id); ?>">
                <input type="hidden" name="start_session" value="1">

                <?php if (empty($decks)): ?>
                    <div class="bg-white p-8 rounded-lg shadow-md text-center animated-section">
                        <i class="fas fa-folder-open text-4xl text-gray-400 mb-4"></i>
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">No Decks Found</h3>
                        <p class="text-gray-600 mb-6">Try adjusting your filters or create a new deck.</p>
                        <a href="create_deck.php" class="btn-primary inline-flex items-center">
                            <i class="fas fa-plus-circle mr-2"></i> Create New Deck
                        </a>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($decks as $deck): ?>
                            <div class="deck-card bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300 border border-gray-200 cursor-pointer animated-card" data-deck-id="<?php echo htmlspecialchars($deck['id']); ?>">
                                <div class="absolute top-3 right-3">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php echo $deck['user_id'] == $user_id ? 'bg-purple-100 text-purple-800' : 'bg-indigo-100 text-indigo-800'; ?>">
                                        <?php echo $deck['user_id'] == $user_id ? 'Owned' : 'Shared'; ?>
                                    </span>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-800 mb-2 pr-6"><?php echo htmlspecialchars($deck['title']); ?></h3>
                                <?php if (!empty($deck['description'])): ?>
                                    <p class="text-gray-600 text-sm mb-4 line-clamp-2"><?php echo htmlspecialchars($deck['description']); ?></p>
                                <?php endif; ?>
                                <div class="grid grid-cols-2 gap-2 text-sm text-gray-500 mb-4">
                                    <div class="flex items-center">
                                        <i class="fas fa-book mr-1"></i> <?php echo htmlspecialchars($deck['total_flashcards']); ?> cards
                                    </div>
                                    <div class="flex items-center">
                                        <i class="fas fa-calendar-alt mr-1"></i> <?php echo date('M j, Y', strtotime($deck['created_at'])); ?>
                                    </div>
                                    <div class="flex items-center">
                                        <i class="fas fa-user mr-1"></i> <?php echo htmlspecialchars($deck['owner_name']); ?>
                                    </div>
                                    <?php if (!empty($deck['category_name'])): ?>
                                        <div class="flex items-center">
                                            <i class="fas fa-folder-open mr-1"></i> <?php echo htmlspecialchars($deck['category_name']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <button type="button" class="select-deck-btn w-full py-2 px-4 rounded-md text-white bg-purple-500 hover:bg-purple-600 transition-colors duration-200">
                                    Select
                                </button>
                                <input type="hidden" name="decks[]" value="<?php echo htmlspecialchars($deck['id']); ?>" disabled>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="sticky-session-btn">
                        <button type="submit" class="btn-primary px-6 py-3 flex items-center shadow-md" id="start-session-btn" disabled>
                            <i class="fas fa-play mr-2"></i> Start Study Session
                        </button>
                    </div>
                <?php endif; ?>
            </form>
        </main>
    </div>

    <!-- Scripts -->
    <script>
        gsap.registerPlugin(ScrollTrigger);

        gsap.utils.toArray('.animated-section').forEach(section => {
            gsap.from(section, {
                scrollTrigger: {
                    trigger: section,
                    start: "top 80%",
                    toggleActions: "play none none none",
                    once: true
                },
                duration: 1,
                y: 50,
                opacity: 0,
                ease: "power2.out"
            });
        });

        gsap.utils.toArray('.animated-card').forEach(card => {
            gsap.from(card, {
                scrollTrigger: {
                    trigger: card,
                    start: "top 85%",
                    toggleActions: "play none none none",
                    once: true
                },
                duration: 0.8,
                x: -50,
                opacity: 0,
                ease: "power2.out"
            });
        });
        
        // Handling the start session button...
        document.addEventListener('DOMContentLoaded', () => {
            const deckCards = document.querySelectorAll('.deck-card');
            const startSessionBtn = document.getElementById('start-session-btn');
            let selectedDecks = new Set();

            deckCards.forEach(card => {
                const selectBtn = card.querySelector('.select-deck-btn');
                const input = card.querySelector('input[name="decks[]"]');

                card.addEventListener('click', (e) => {
                    if (!e.target.closest('button') && !e.target.closest('a')) {
                        selectBtn.click();
                    }
                });

                selectBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const deckId = card.getAttribute('data-deck-id');

                    if (selectedDecks.has(deckId)) {
                        selectedDecks.delete(deckId);
                        card.classList.remove('ring-2', 'ring-purple-500');
                        selectBtn.textContent = 'Select';
                        selectBtn.classList.remove('bg-red-500', 'hover:bg-red-600');
                        selectBtn.classList.add('bg-purple-500', 'hover:bg-purple-600');
                        input.disabled = true;
                    } else {
                        selectedDecks.add(deckId);
                        card.classList.add('ring-2', 'ring-purple-500');
                        selectBtn.textContent = 'Deselect';
                        selectBtn.classList.remove('bg-purple-500', 'hover:bg-purple-600');
                        selectBtn.classList.add('bg-red-500', 'hover:bg-red-600');
                        input.disabled = false;
                    }

                    startSessionBtn.disabled = selectedDecks.size === 0;
                });
            });
        });
    </script>
</body>
</html>