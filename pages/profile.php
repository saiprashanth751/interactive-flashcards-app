<?php
session_start();

// Redirect to login if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include '../includes/db.php';

$user_id = $_SESSION['user_id'];

// Fetch user details
try {
    $stmt = $pdo->prepare("SELECT email, language FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    if (!$user) {
        throw new Exception("User not found.");
    }
} catch (Exception $e) {
    error_log("Error fetching user: " . $e->getMessage());
    header("Location: index.php?error=database_error");
    exit();
}

// Fetch user stats
try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT d.id) AS total_decks,
            DATEDIFF(MAX(ss.studied_at), MIN(ss.studied_at)) AS study_streak,
            up.points
        FROM users u
        LEFT JOIN decks d ON d.user_id = u.id
        LEFT JOIN study_sessions ss ON ss.user_id = u.id
        LEFT JOIN (
            SELECT points FROM user_points WHERE user_id = ? ORDER BY id DESC LIMIT 1
        ) up ON 1=1
        WHERE u.id = ?
        GROUP BY u.id, up.points
    ");
    $stmt->execute([$user_id, $user_id]);
    $stats = $stmt->fetch();
} catch (Exception $e) {
    error_log("Error fetching stats: " . $e->getMessage());
    $stats = ['total_decks' => 0, 'study_streak' => 0, 'points' => 0];
}

// Fetch user achievements
try {
    $stmt = $pdo->prepare("
        SELECT a.name, a.description, a.badge_icon 
        FROM user_achievements ua
        JOIN achievements a ON ua.achievement_id = a.id
        WHERE ua.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $achievements = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching achievements: " . $e->getMessage());
    $achievements = [];
}

// Fetch study activity for the last 30 days
try {
    $stmt = $pdo->prepare("
        SELECT DATE(studied_at) AS date, COUNT(*) AS count 
        FROM study_sessions 
        WHERE user_id = ? 
          AND studied_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(studied_at)
    ");
    $stmt->execute([$user_id]);
    $study_activity = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {
    error_log("Error fetching study activity: " . $e->getMessage());
    $study_activity = [];
}

// Fetch study progress for the last 7 days
try {
    $stmt = $pdo->prepare("
        SELECT DATE(studied_at) AS date, COUNT(*) AS count 
        FROM study_sessions 
        WHERE user_id = ? 
          AND studied_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(studied_at)
        ORDER BY date ASC
    ");
    $stmt->execute([$user_id]);
    $study_data = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {
    error_log("Error fetching study progress: " . $e->getMessage());
    $study_data = [];
}

// Fetch user's decks
try {
    $stmt = $pdo->prepare("
        SELECT id, title, description 
        FROM decks 
        WHERE user_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$user_id]);
    $decks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching decks: " . $e->getMessage());
    $decks = [];
}

// Prepare labels and data points for the graph
$labels = [];
$data_points = [];
$current_date = new DateTime('-6 days');
for ($i = 0; $i < 7; $i++) {
    $date_str = $current_date->format('Y-m-d');
    $labels[] = $current_date->format('D, M j');
    $data_points[] = $study_data[$date_str] ?? 0;
    $current_date->modify('+1 day');
}

// Fetch categories for the filter
try {
    $stmt = $pdo->prepare("SELECT id, name FROM categories ORDER BY name ASC");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching categories: " . $e->getMessage());
    $categories = [];
}

// Fetch user's decks with category filter
$selected_category = $_GET['category'] ?? '';
$decks_query = "SELECT id, title, description FROM decks WHERE user_id = ? ";
$params = [$user_id];

if ($selected_category !== '') {
    $decks_query .= "AND category_id = ? ";
    $params[] = $selected_category;
}

$decks_query .= "ORDER BY created_at DESC";

try {
    $stmt = $pdo->prepare($decks_query);
    $stmt->execute($params);
    $decks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching decks: " . $e->getMessage());
    $decks = [];
}

// Handle form submissions -- User information.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_email'])) {
        $new_email = $_POST['email'];
        if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $email_error = "Invalid email format.";
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
                $stmt->execute([$new_email, $user_id]);
                header("Location: profile.php?section=personal");
                exit();
            } catch (Exception $e) {
                error_log("Error updating email: " . $e->getMessage());
                $email_error = "Failed to update email.";
            }
        }
    } elseif (isset($_POST['update_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        try {
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user_password = $stmt->fetchColumn();

            if (!password_verify($current_password, $user_password)) {
                $password_error = "Current password is incorrect.";
            } elseif ($new_password !== $confirm_password) {
                $password_error = "New passwords do not match.";
            } else {
                $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $user_id]);
                header("Location: profile.php?section=personal");
                exit();
            }
        } catch (Exception $e) {
            error_log("Error updating password: " . $e->getMessage());
            $password_error = "Failed to update password.";
        }
    } elseif (isset($_POST['update_language'])) {
        $language = $_POST['language'];
        if (!in_array($language, ['en', 'es', 'fr'])) {
            $language_error = "Invalid language selected.";
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE users SET language = ? WHERE id = ?");
                $stmt->execute([$language, $user_id]);
                $_SESSION['language'] = $language;
                header("Location: profile.php?section=personal");
                exit();
            } catch (Exception $e) {
                error_log("Error updating language: " . $e->getMessage());
                $language_error = "Failed to update language.";
            }
        }
    }
}

// Determine active section
$section = $_GET['section'] ?? 'stats';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile</title>
    <link href="../src/output.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Load Chart.js and GSAP synchronously to avoid script loading issues -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.11.5/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.11.5/ScrollTrigger.min.js"></script>
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
            transition: background 0.3s ease, transform 0.3s ease;
            display: inline-block;
        }

        .btn-primary:hover {
            background: #7c3aed;
            transform: translateY(-2px);
        }

        .btn-secondary {
            @apply text-blue-600 hover:text-blue-800 transition-all duration-300;
        }

        .btn-action {
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            transition: background 0.3s ease, transform 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filter-bar {
            background: linear-gradient(120deg, #3b82f6, #8b5cf6);
            border-radius: 12px;
            padding: 1rem;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
        }

        .filter-bar label {
            color: white;
            font-weight: 500;
            margin-right: 1rem;
        }

        .filter-bar select {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            background: white;
            color: #374151;
            font-size: 0.875rem;
            transition: border-color 0.3s ease;
        }

        .filter-bar select:focus {
            outline: none;
            border-color: #8b5cf6;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.2);
        }

        .deck-card {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .deck-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.15);
        }

        .btn-edit {
            background: #3b82f6;
            color: white;
        }

        .btn-edit:hover {
            background: #2563eb;
            transform: translateY(-2px);
        }

        .btn-add {
            background: #10b981;
            color: white;
        }

        .btn-add:hover {
            background: #059669;
            transform: translateY(-2px);
        }

        .btn-delete {
            background: っきり#ef4444;
            color: white;
        }

        .btn-delete:hover {
            background: #dc2626;
            transform: translateY(-2px);
        }

        .sidebar {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            max-width: 300px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            padding: 1.5rem;
        }

        .sidebar a {
            display: block;
            padding: 0.75rem 1rem;
            margin-bottom: 0.5rem;
            border-radius: 8px;
            font-weight: 500;
            color: #4b5563;
            transition: background 0.3s ease, color 0.3s ease;
        }

        .sidebar a:hover {
            background: #f3f4f6;
            color: #1f2937;
        }

        .sidebar a.active {
            background: #8b5cf6;
            color: white;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.15);
        }

        .achievement-card {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 12px;
            padding: 1rem;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .achievement-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.15);
        }

        .calendar-day-label {
            text-align: center;
            font-size: 0.75rem;
            color: #4b5563;
            margin-bottom: 0.5rem;
        }

        .calendar-legend {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 1rem;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.75rem;
            color: #4b5563;
        }

        .legend-item div {
            width: 1rem;
            height: 1rem;
            border-radius: 0.25rem;
        }

        /* Custom Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 50;
        }

        .modal {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
            text-align: center;
        }

        .modal-header {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
        }

        .modal-header i {
            color: #ef4444;
            font-size: 2rem;
            margin-right: 0.5rem;
        }

        .modal h2 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1f2937;
            margin: 0;
        }

        .modal p {
            color: #4b5563;
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
        }

        .modal-buttons {
            display: flex;
            justify-content: center;
            gap: 1rem;
        }

        .modal-btn {
            padding: 0.5rem 1.5rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            cursor: pointer;
            transition: background 0.3s ease, transform 0.3s ease;
        }

        .modal-btn-confirm {
            background: #ef4444;
            color: white;
        }

        .modal-btn-confirm:hover {
            background: #dc2626;
            transform: translateY(-2px);
        }

        .modal-btn-cancel {
            background: #e5e7eb;
            color: #374151;
        }

        .modal-btn-cancel:hover {
            background: #d1d5db;
            transform: translateY(-2px);
        }
    </style>
</head>

<body>
    <!-- Header -->
    <header class="header-bg text-white p-6 sticky top-0 z-20">
        <div class="max-w-6xl mx-auto flex justify-between items-center">
            <h1 class="text-3xl font-bold tracking-wide">Profile</h1>
            <div class="flex space-x-6">
                <a href="index.php" class="btn-secondary flex items-center"><i class="fas fa-arrow-left mr-2"></i> Dashboard</a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="p-6">
        <div class="max-w-6xl mx-auto flex flex-col md:flex-row gap-6">
            <!-- Sidebar -->
            <aside class="sidebar w-full md:w-auto rounded-lg">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Profile Menu</h2>
                <nav>
                    <a href="profile.php?section=stats" class="<?php echo $section === 'stats' ? 'active' : ''; ?>">
                        <i class="fas fa-chart-bar mr-2"></i> Stats
                    </a>
                    <a href="profile.php?section=decks" class="<?php echo $section === 'decks' ? 'active' : ''; ?>">
                        <i class="fas fa-layer-group mr-2"></i> My Decks
                    </a>
                    <a href="profile.php?section=personal" class="<?php echo $section === 'personal' ? 'active' : ''; ?>">
                        <i class="fas fa-user mr-2"></i> Personal Info
                    </a>
                </nav>
            </aside>

            <!-- Main Content Area -->
            <div class="w-full md:w-3/4">
                <?php if ($section === 'stats'): ?>
                    <!-- Quick Stats -->
                    <div class="bg-white p-6 rounded-lg shadow-md mb-8 animated-section">
                        <h2 class="text-2xl font-semibold text-gray-800 mb-6">Quick Stats</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div class="stat-card">
                                <div class="flex items-center space-x-3">
                                    <i class="fas fa-layer-group text-blue-600 text-2xl"></i>
                                    <div>
                                        <p class="text-sm text-gray-600">Total Decks</p>
                                        <p class="text-2xl font-bold text-blue-600"><?php echo $stats['total_decks'] ?? 0; ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="stat-card">
                                <div class="flex items-center space-x-3">
                                    <i class="fas fa-fire text-blue-600 text-2xl"></i>
                                    <div>
                                        <p class="text-sm text-gray-600">Study Streak</p>
                                        <p class="text-2xl font-bold text-blue-600"><?php echo $stats['study_streak'] ?? 0; ?> days</p>
                                    </div>
                                </div>
                            </div>
                            <div class="stat-card">
                                <div class="flex items-center space-x-3">
                                    <i class="fas fa-star text-blue-600 text-2xl"></i>
                                    <div>
                                        <p class="text-sm text-gray-600">Points</p>
                                        <p class="text-2xl font-bold text-blue-600"><?php echo $stats['points'] ?? 0; ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Study Activity Calendar -->
                    <div class="bg-white p-6 rounded-lg shadow-md mb-8 animated-section">
                        <h2 class="text-2xl font-semibold text-gray-800 mb-6">Study Activity</h2>
                        <div class="grid grid-cols-7 gap-1">
                            <!-- Day Labels -->
                            <div class="calendar-day-label">Mon</div>
                            <div class="calendar-day-label">Tue</div>
                            <div class="calendar-day-label">Wed</div>
                            <div class="calendar-day-label">Thu</div>
                            <div class="calendar-day-label">Fri</div>
                            <div class="calendar-day-label">Sat</div>
                            <div class="calendar-day-label">Sun</div>
                            <!-- Calendar Days -->
                            <?php
                            $today = new DateTime();
                            for ($i = 29; $i >= 0; $i--) {
                                $date = clone $today;
                                $date->modify("-$i days");
                                $date_str = $date->format('Y-m-d');
                                $activity = $study_activity[$date_str] ?? 0;

                                $color_class = match (true) {
                                    $activity >= 5 => 'bg-green-600',
                                    $activity >= 3 => 'bg-green-400',
                                    $activity >= 1 => 'bg-green-200',
                                    default => 'bg-gray-200'
                                };
                            ?>
                                <div class="h-8 w-full <?php echo $color_class; ?> rounded-sm"
                                    title="<?php echo $date->format('M j, Y') . ': ' . $activity . ' cards studied'; ?>"></div>
                            <?php } ?>
                        </div>
                        <!-- Legend -->
                        <div class="calendar-legend">
                            <div class="legend-item">
                                <div class="bg-gray-200"></div> 0
                            </div>
                            <div class="legend-item">
                                <div class="bg-green-200"></div> 1-2
                            </div>
                            <div class="legend-item">
                                <div class="bg-green-400"></div> 3-4
                            </div>
                            <div class="legend-item">
                                <div class="bg-green-600"></div> 5+
                            </div>
                        </div>
                        <p class="text-sm text-gray-500 mt-2">Study activity over the last 30 days</p>
                    </div>

                    <!-- Study Progress Graph -->
                    <div class="bg-white p-6 rounded-lg shadow-md mb-8 animated-section">
                        <h2 class="text-2xl font-semibold text-gray-800 mb-6">Study Progress</h2>
                        <div class="w-full h-64">
                            <canvas id="studyChart"></canvas>
                        </div>
                        <p class="text-sm text-gray-500 mt-2">Cards studied per day over the last week</p>
                    </div>

                    <!-- Achievements -->
                    <div class="bg-white p-6 rounded-lg shadow-md mb-8 animated-section">
                        <h2 class="text-2xl font-semibold text-gray-800 mb-6">Achievements</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php if (empty($achievements)): ?>
                                <p class="text-gray-600">No achievements unlocked yet. Keep studying!</p>
                            <?php else: ?>
                                <?php foreach ($achievements as $achievement): ?>
                                    <div class="achievement-card">
                                        <div class="flex items-center space-x-4">
                                            <img src="../assets//star_badge.png" alt="Star Achievement Badge" class="w-12 h-12 object-cover rounded-full">
                                            <div>
                                                <h3 class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($achievement['name']); ?></h3>
                                                <p class="text-gray-600 text-sm"><?php echo htmlspecialchars($achievement['description']); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php elseif ($section === 'personal'): ?>
                    <!-- Profile Settings -->
                    <div class="bg-white p-6 rounded-lg shadow-md animated-section">
                        <h2 class="text-2xl font-semibold text-gray-800 mb-6">Personal Info</h2>
                        <!-- Update Email -->
                        <form method="POST" action="profile.php?section=personal" class="mb-6">
                            <div class="mb-4">
                                <label for="email" class="block text-sm font-medium text-gray-700"><i class="fas fa-envelope mr-2"></i> Email</label>
                                <input type="email" name="email" id="email" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-gray-900 placeholder-gray-400" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            <?php if (isset($email_error)): ?>
                                <p class="text-red-500 text-sm mb-4"><?php echo $email_error; ?></p>
                            <?php endif; ?>
                            <button type="submit" name="update_email" class="btn-primary">Update Email</button>
                        </form>

                        <!-- Change Password -->
                        <form method="POST" action="profile.php?section=personal" class="mb-6">
                            <div class="mb-4">
                                <label for="current_password" class="block text-sm font-medium text-gray-700"><i class="fas fa-lock mr-2"></i> Current Password</label>
                                <input type="password" name="current_password" id="current_password" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-gray-900 placeholder-gray-400" required>
                            </div>
                            <div class="mb-4">
                                <label for="new_password" class="block text-sm font-medium text-gray-700"><i class="fas fa-lock mr-2"></i> New Password</label>
                                <input type="password" name="new_password" id="new_password" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-gray-900 placeholder-gray-400" required>
                            </div>
                            <div class="mb-4">
                                <label for="confirm_password" class="block text-sm font-medium text-gray-700"><i class="fas fa-lock mr-2"></i> Confirm New Password</label>
                                <input type="password" name="confirm_password" id="confirm_password" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-gray-900 placeholder-gray-400" required>
                            </div>
                            <?php if (isset($password_error)): ?>
                                <p class="text-red-500 text-sm mb-4"><?php echo $password_error; ?></p>
                            <?php endif; ?>
                            <button type="submit" name="update_password" class="btn-primary">Change Password</button>
                        </form>

                        <!-- Update Language -->
                        <form method="POST" action="profile.php?section=personal">
                            <div class="mb-4">
                                <label for="language" class="block text-sm font-medium text-gray-700"><i class="fas fa-globe mr-2"></i> Language</label>
                                <select name="language" id="language" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-gray-900">
                                    <option value="en" <?php echo $user['language'] === 'en' ? 'selected' : ''; ?>>English</option>
                                    <option value="es" <?php echo $user['language'] === 'es' ? 'selected' : ''; ?>>Español</option>
                                    <option value="fr" <?php echo $user['language'] === 'fr' ? 'selected' : ''; ?>>Français</option>
                                </select>
                            </div>
                            <?php if (isset($language_error)): ?>
                                <p class="text-red-500 text-sm mb-4"><?php echo $language_error; ?></p>
                            <?php endif; ?>
                            <button type="submit" name="update_language" class="btn-primary">Update Language</button>
                        </form>
                    </div>
                <?php elseif ($section === 'decks'): ?>
                    <!-- My Decks -->
                    <div class="bg-white p-6 rounded-lg shadow-md animated-section">
                        <h2 class="text-2xl font-semibold text-gray-800 mb-6">My Decks</h2>
                        <!-- Category Filter -->
                        <div class="filter-bar flex items-center">
                            <label for="category-filter"><i class="fas fa-filter mr-2"></i> Filter by Category:</label>
                            <select id="category-filter" onchange="location = this.value;" class="w-48">
                                <option value="profile.php?section=decks" <?php echo $selected_category === '' ? 'selected' : ''; ?>>All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="profile.php?section=decks&category=<?php echo $category['id']; ?>" <?php echo $selected_category == $category['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if (empty($decks)): ?>
                            <p class="text-gray-600">You haven't created any decks yet. <a href="create_deck.php" class="text-blue-600 hover:underline">Create one now!</a></p>
                        <?php else: ?>
                            <!-- Cards -->
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                <?php foreach ($decks as $deck): ?>
                                    <div class="deck-card">
                                        <h3 class="text-lg font-semibold text-gray-800 mb-2"><?php echo htmlspecialchars($deck['title']); ?></h3>
                                        <p class="text-gray-600 text-sm mb-4"><?php echo htmlspecialchars($deck['description'] ?: 'No description'); ?></p>
                                        <div class="flex flex-wrap gap-2">
                                            <a href="edit_deck.php?deck_id=<?php echo $deck['id']; ?>" class="btn-action btn-edit">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <a href="add_flashcards.php?deck_id=<?php echo $deck['id']; ?>" class="btn-action btn-add">
                                                <i class="fas fa-plus"></i> Add Flashcards
                                            </a>
                                            <button class="btn-action btn-delete" data-deck-id="<?php echo $deck['id']; ?>">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Delete Confirmation Modal -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal">
            <div class="modal-header">
                <i class="fas fa-trash"></i>
                <h2>Confirm Deletion</h2>
            </div>
            <p>Are you sure you want to delete this deck? This action cannot be undone.</p>
            <div class="modal-buttons">
                <button class="modal-btn modal-btn-cancel" id="cancelDelete">Cancel</button>
                <button class="modal-btn modal-btn-confirm" id="confirmDelete">Delete</button>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        // Study Progress Graph (only load if Stats section is active)
        <?php if ($section === 'stats'): ?>
            document.addEventListener('DOMContentLoaded', function() {
                const ctx = document.getElementById('studyChart')?.getContext('2d');
                if (ctx) {
                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: <?php echo json_encode($labels); ?>,
                            datasets: [{
                                label: 'Cards Studied',
                                data: <?php echo json_encode($data_points); ?>,
                                borderColor: '#8b5cf6',
                                backgroundColor: 'rgba(139, 92, 246, 0.2)',
                                tension: 0.4,
                                fill: true
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        stepSize: 1,
                                        color: '#4b5563'
                                    },
                                    grid: {
                                        color: 'rgba(0, 0, 0, 0.05)'
                                    }
                                },
                                x: {
                                    ticks: {
                                        color: '#4b5563'
                                    },
                                    grid: {
                                        display: false
                                    }
                                }
                            },
                            plugins: {
                                legend: {
                                    labels: {
                                        color: '#4b5563'
                                    }
                                }
                            }
                        }
                    });
                }
            });
        <?php endif; ?>

        // GSAP Animations
        gsap.registerPlugin(ScrollTrigger);
        gsap.utils.toArray('.animated-section').forEach(section => {
            gsap.from(section, {
                scrollTrigger: {
                    trigger: section,
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

        // Delete Modal Logic
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('deleteModal');
            const confirmBtn = document.getElementById('confirmDelete');
            const cancelBtn = document.getElementById('cancelDelete');
            let currentDeckId = null;

            // Open modal when delete button is clicked
            document.querySelectorAll('.btn-delete').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    currentDeckId = this.getAttribute('data-deck-id');
                    modal.style.display = 'flex';
                    gsap.fromTo(modal.querySelector('.modal'), {
                        scale: 0.8,
                        opacity: 0
                    }, {
                        scale: 1,
                        opacity: 1,
                        duration: 0.3,
                        ease: 'power2.out'
                    });
                });
            });

            // Close modal on cancel
            cancelBtn.addEventListener('click', function() {
                gsap.to(modal.querySelector('.modal'), {
                    scale: 0.8,
                    opacity: 0,
                    duration: 0.3,
                    ease: 'power2.in',
                    onComplete: () => {
                        modal.style.display = 'none';
                        currentDeckId = null;
                    }
                });
            });

            // Confirm deletion
            confirmBtn.addEventListener('click', function() {
                if (currentDeckId) {
                    window.location.href = `delete_deck.php?deck_id=${currentDeckId}`;
                }
            });

            // Close modal on overlay click
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    gsap.to(modal.querySelector('.modal'), {
                        scale: 0.8,
                        opacity: 0,
                        duration: 0.3,
                        ease: 'power2.in',
                        onComplete: () => {
                            modal.style.display = 'none';
                            currentDeckId = null;
                        }
                    });
                }
            });

            // Close modal on Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && modal.style.display === 'flex') {
                    gsap.to(modal.querySelector('.modal'), {
                        scale: 0.8,
                        opacity: 0,
                        duration: 0.3,
                        ease: 'power2.in',
                        onComplete: () => {
                            modal.style.display = 'none';
                            currentDeckId = null;
                        }
                    });
                }
            });
        });
    </script>
</body>

</html>