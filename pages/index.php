<?php
session_start();

if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit();
}

include '../includes/db.php';

$language = $_SESSION['language'] ?? 'en';
$lang = require __DIR__ . "/../lang/$language.php";

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Fetch a random motivational message
$stmt = $pdo->prepare("SELECT message FROM motivational_messages ORDER BY RAND() LIMIT 1");
$stmt->execute();
$motivational_message = $stmt->fetchColumn();

// Fetch the user's current study goal
$stmt = $pdo->prepare("SELECT id, target_decks, period, created_at FROM study_goals WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$user_id]);
$study_goal = $stmt->fetch();

$progress = 0;
$mastered_titles = [];
$goal_completed = false;

if ($study_goal && $study_goal['target_decks'] > 0) {
  $stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM user_deck_mastery udm
    LEFT JOIN shared_decks sd ON udm.deck_id = sd.deck_id AND sd.shared_with = ?
    WHERE (udm.user_id = ? OR sd.shared_with = ?) AND udm.is_mastered = 1
  ");
  $stmt->execute([$user_id, $user_id, $user_id]);
  $progress = $stmt->fetchColumn();

  $stmt = $pdo->prepare("
    SELECT d.title 
    FROM decks d
    LEFT JOIN user_deck_mastery udm ON d.id = udm.deck_id AND udm.user_id = ?
    LEFT JOIN shared_decks sd ON d.id = sd.deck_id AND sd.shared_with = ?
    WHERE (udm.user_id = ? OR sd.shared_with = ?) AND udm.is_mastered = 1
  ");
  $stmt->execute([$user_id, $user_id, $user_id, $user_id]);
  $mastered_decks = $stmt->fetchAll();
  $mastered_titles = array_column($mastered_decks, 'title');

  if ($progress >= $study_goal['target_decks']) {
    $goal_completed = true;
    $stmt = $pdo->prepare("INSERT INTO study_goal_history (user_id, target_decks, period, completed_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$user_id, $study_goal['target_decks'], $study_goal['period']]);
    $stmt = $pdo->prepare("
      UPDATE user_deck_mastery udm
      LEFT JOIN shared_decks sd ON udm.deck_id = sd.deck_id AND sd.shared_with = ?
      SET udm.is_mastered = 0, udm.mastered_at = NULL
      WHERE (udm.user_id = ? OR sd.shared_with = ?) AND udm.is_mastered = 1
    ");
    $stmt->execute([$user_id, $user_id, $user_id]);
    $stmt = $pdo->prepare("DELETE FROM study_goals WHERE id = ? AND user_id = ?");
    $stmt->execute([$study_goal['id'], $user_id]);
    $stmt = $pdo->prepare("SELECT id, target_decks, period, created_at FROM study_goals WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$user_id]);
    $study_goal = $stmt->fetch();
    $progress = 0;
    $mastered_titles = [];
  }
}

// Fetch recently studied decks with cards to review
try {
  $stmt = $pdo->prepare("
      SELECT d.*, 
             COUNT(DISTINCT f.id) AS total_flashcards
      FROM decks d
      LEFT JOIN flashcards f ON d.id = f.deck_id
      LEFT JOIN user_deck_mastery udm ON d.id = udm.deck_id AND udm.user_id = ?
      WHERE d.user_id = ? AND COALESCE(udm.is_mastered, 0) = 0 AND d.last_studied_at IS NOT NULL
      GROUP BY d.id
      ORDER BY d.last_studied_at DESC
      LIMIT 4
  ");
  $stmt->execute([$user_id, $user_id]);
  $decks_to_review = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  echo "Error: " . $e->getMessage();
}

// Fetch recently created decks (all users)
$stmt = $pdo->prepare("
  SELECT d.*, COUNT(f.id) AS total_flashcards
  FROM decks d
  LEFT JOIN flashcards f ON d.id = f.deck_id
  GROUP BY d.id
  ORDER BY d.created_at DESC
  LIMIT 8
");
$stmt->execute();
$recent_decks = $stmt->fetchAll();

// Define daily challenges
$challenges = [
  "Review 10 flashcards",
  "Master a new deck",
  "Study for 15 minutes",
  "Create a new deck",
  "Share a deck with a friend",
  "Complete a custom study session",
  "Review a deck you haven’t studied in a week"
];
$today = date('w'); // 0 (Sunday) to 6 (Saturday)
$daily_challenge = $challenges[$today];

// Placeholder for challenge completion check
$challenge_completed = false; // Replace with actual logic later
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link href="../src/output.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.11.5/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.11.5/ScrollTrigger.min.js"></script>
</head>
<body class="min-h-screen bg-gray-50 font-poppins">
    <!-- Header -->
    <header class="bg-gradient-to-r from-purple-700 to-indigo-700 text-white p-6 shadow-lg">
        <div class="max-w-6xl mx-auto flex justify-between items-center">
            <h1 class="text-3xl font-bold tracking-wide">Welcome, <?php echo htmlspecialchars($username); ?>!</h1>
            <div class="flex items-center space-x-6">
                <form method="GET" action="search.php" class="flex items-center">
                    <input type="text" name="q" placeholder="Search flashcards..." class="px-4 py-2 border border-gray-300 rounded-l-md shadow-sm focus:outline-none focus:ring-2 focus:ring-purple-500">
                    <button type="submit" class="bg-purple-600 text-white px-4 py-2 rounded-r-md hover:bg-purple-700 transition duration-300">Search</button>
                </form>
                <a href="index.php" class="text-sm text-white hover:underline transition duration-300">Dashboard</a>
                <a href="about.php" class="text-sm text-white hover:underline transition duration-300">About Us</a>
                <a href="profile.php" class="text-sm text-white hover:underline transition duration-300">Profile</a>
                <a href="logout.php" class="text-sm text-white hover:underline transition duration-300">Logout</a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="p-6">
        <div class="max-w-6xl mx-auto grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Left Column -->
            <div class="space-y-6">
                <!-- Motivational Message -->
                <div class="text-center text-gray-600 italic text-lg animated-section"><?php echo htmlspecialchars($motivational_message); ?></div>

                <!-- Daily Challenge -->
                <div class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300 animated-section">
                    <h2 class="text-2xl font-semibold mb-4 text-gray-800">Daily Challenge</h2>
                    <p class="text-gray-600 mb-4"><?php echo $daily_challenge; ?>
                        <?php if ($challenge_completed): ?>
                            <span class="text-green-600">(Completed!)</span>
                        <?php endif; ?>
                    </p>
                    <a href="view_decks.php" class="text-purple-600 hover:underline">Start Challenge</a>
                </div>

                <!-- Quick Actions -->
                <div class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300 animated-section">
                    <h2 class="text-2xl font-semibold mb-4 text-gray-800">Quick Actions</h2>
                    <div class="flex flex-wrap gap-4">
                        <a href="custom_study_sessions.php" class="btn-primary"><i class="fas fa-book-open mr-2"></i>Start Studying</a>
                        <a href="view_decks.php" class="btn-primary"><i class="fas fa-folder-open mr-2"></i>View Decks</a>
                        <a href="create_deck.php" class="btn-primary"><i class="fas fa-plus-circle mr-2"></i>Create Deck</a>
                    </div>
                </div>

                <!-- Study Goal -->
                <div class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300 animated-section">
                    <h2 class="text-2xl font-semibold mb-4 text-gray-800">Study Goal</h2>
                    <?php if ($study_goal && $study_goal['target_decks'] > 0 && !$goal_completed): ?>
                        <p class="text-gray-600">Target: <?php echo $study_goal['target_decks']; ?> decks to master <?php echo $study_goal['period'] === 'daily' ? 'today' : 'this week'; ?></p>
                        <p class="text-gray-600">Progress: <?php echo $progress; ?>/<?php echo $study_goal['target_decks']; ?> decks</p>
                        <p class="text-gray-500 text-sm">Mastered Decks: <?php echo implode(', ', $mastered_titles) ?: 'None'; ?></p>
                        <div class="w-full bg-gray-200 rounded-full h-2.5 mt-2">
                            <div class="bg-purple-600 h-2.5 rounded-full transition-all duration-500" style="width: <?php echo $study_goal['target_decks'] > 0 ? min(($progress / $study_goal['target_decks']) * 100, 100) : 0; ?>%"></div>
                        </div>
                    <?php else: ?>
                        <?php if ($goal_completed): ?>
                            <p class="text-green-500 font-semibold mb-4">Congratulations! You've completed your study goal!</p>
                        <?php endif; ?>
                        <p class="text-gray-600 mb-4">Set a new goal to stay on track.</p>
                        <form action="set_study_goal.php" method="POST" class="space-y-4">
                            <div class="flex space-x-4">
                                <div class="flex-1">
                                    <label for="target_decks" class="block text-sm font-medium text-gray-700">Target Decks</label>
                                    <input type="number" id="target_decks" name="target_decks" min="1" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-purple-500" required>
                                </div>
                                <div class="flex-1">
                                    <label for="period" class="block text-sm font-medium text-gray-700">Period</label>
                                    <select id="period" name="period" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-purple-500">
                                        <option value="daily">Daily</option>
                                        <option value="weekly">Weekly</option>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="btn-primary">Set Goal</button>
                        </form>
                    <?php endif; ?>
                    <h3 class="text-lg font-semibold mt-6 mb-2 text-gray-800">Recent Goals</h3>
                    <?php
                    $stmt = $pdo->prepare("SELECT target_decks, period, completed_at FROM study_goal_history WHERE user_id = ? ORDER BY completed_at DESC LIMIT 3");
                    $stmt->execute([$user_id]);
                    $goal_history = $stmt->fetchAll();
                    ?>
                    <?php if (empty($goal_history)): ?>
                        <p class="text-gray-600">No completed goals yet.</p>
                    <?php else: ?>
                        <ul class="list-disc list-inside text-gray-600">
                            <?php foreach ($goal_history as $goal): ?>
                                <li>Mastered <?php echo $goal['target_decks']; ?> decks (<?php echo $goal['period']; ?>) on <?php echo date('F j, Y', strtotime($goal['completed_at'])); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <!-- Tip of the Day -->
                <div class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300 animated-section">
                    <h2 class="text-2xl font-semibold mb-4 text-gray-800">Tip of the Day</h2>
                    <p class="text-gray-600">Did you know? Studying in short bursts can boost retention. Try 25-minute sessions! Click on Start Studying to create Custom Study Sessions </p>
                </div>
            </div>

            <!-- Right Column -->
            <div class="space-y-6">
                <!-- Decks to Review -->
                <div class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300 animated-section">
                    <h2 class="text-2xl font-semibold mb-4 text-gray-800">Decks to Review</h2>
                    <?php if (empty($decks_to_review)): ?>
                        <p class="text-gray-600">No decks to review. Start studying to see decks here!</p>
                    <?php else: ?>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <?php foreach ($decks_to_review as $deck): ?>
                                <div class="bg-white p-4 rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300 border border-gray-200 animated-card">
                                    <h3 class="text-lg font-semibold text-gray-800 mb-2"><?php echo htmlspecialchars($deck['title']); ?></h3>
                                    <p class="text-gray-600 text-sm mb-4 line-clamp-2"><?php echo htmlspecialchars($deck['description']); ?></p>
                                    <p class="text-sm text-gray-500">Flashcards: <?php echo $deck['total_flashcards']; ?></p>
                                    <div class="mt-4">
                                        <a href="study.php?deck_id=<?php echo $deck['id']; ?>" class="btn-primary">Study</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recently Created Decks -->
                <div class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300 animated-section">
                    <h2 class="text-2xl font-semibold mb-4 text-gray-800">Recently Created Decks</h2>
                    <?php if (empty($recent_decks)): ?>
                        <p class="text-gray-600">No recently created decks. Create some to see them here!</p>
                    <?php else: ?>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <?php foreach ($recent_decks as $deck): ?>
                                <div class="bg-white p-4 rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300 border border-gray-200 animated-card">
                                    <h3 class="text-lg font-semibold text-gray-800 mb-2"><?php echo htmlspecialchars($deck['title']); ?></h3>
                                    <p class="text-gray-600 text-sm mb-4 line-clamp-2"><?php echo htmlspecialchars($deck['description']); ?></p>
                                    <p class="text-sm text-gray-500">Flashcards: <?php echo $deck['total_flashcards']; ?></p>
                                    <div class="mt-4">
                                        <a href="study.php?deck_id=<?php echo $deck['id']; ?>" class="btn-primary">Study</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white p-4 mt-6">
        <div class="max-w-6xl mx-auto flex flex-col sm:flex-row justify-between items-center">
            <p class="text-sm">© 2025 Flashcard Project. All rights reserved.</p>
            <div class="mt-2 sm:mt-0 space-x-4">
                <a href="about.php" class="text-sm text-white hover:underline transition duration-300">About Us</a>
                <a href="contact.php" class="text-sm text-white hover:underline transition duration-300">Contact</a>
            </div>
        </div>
    </footer>

    <!-- GSAP ScrollTrigger Animations -->
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
                ease: "power2.out",
                immediateRender: false
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
                ease: "power2.out",
                immediateRender: false
            });
        });
        gsap.utils.toArray('.btn-primary').forEach(btn => {
            gsap.from(btn, {
                scrollTrigger: {
                    trigger: btn,
                    start: "top 90%",
                    toggleActions: "play none none none",
                    once: true
                },
                duration: 0.8,
                scale: 0,
                opacity: 0,
                ease: "back.out(1.7)",
                immediateRender: false
            });
        });
    </script>
</body>
</html>