<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include '../includes/db.php';

$user_id = $_SESSION['user_id'];
$deck_id = $_GET['deck_id'] ?? null;

if (!$deck_id) {
    header("Location: view_decks.php");
    exit();
}

// Check if the deck belongs to the user
$stmt = $pdo->prepare("SELECT id FROM decks WHERE id = ? AND user_id = ?");
$stmt->execute([$deck_id, $user_id]);
$deck = $stmt->fetch();

if (!$deck) {
    header("Location: view_decks.php");
    exit();
}

// Add flashcard logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $question = $_POST['question'];
    $answer = $_POST['answer'];

    // Validate inputs
    if (empty($question) || empty($answer)) {
        $error = "Both question and answer are required.";
    } else {
        // Insert the flashcard
        try {
            $stmt = $pdo->prepare("INSERT INTO flashcards (deck_id, question, answer) VALUES (?, ?, ?)");
            $stmt->execute([$deck_id, $question, $answer]);
            $success = "Flashcard added successfully!";
        } catch (Exception $e) {
            error_log("Error adding flashcard: " . $e->getMessage());
            $error = "Failed to add flashcard. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Flashcards</title>
    <link href="../src/output.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.11.5/gsap.min.js" defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.11.5/ScrollTrigger.min.js" defer></script>
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
        .form-container {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 12px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        .error-message {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid #ef4444;
            border-radius: 8px;
            padding: 0.75rem;
            color: #ef4444;
            font-size: 0.875rem;
        }
        .success-message {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid #10b981;
            border-radius: 8px;
            padding: 0.75rem;
            color: #10b981;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header-bg text-white p-6 sticky top-0 z-20">
        <div class="max-w-6xl mx-auto flex justify-between items-center">
            <h1 class="text-3xl font-bold tracking-wide">Add Flashcards</h1>
            <div class="flex space-x-6">
                <a href="index.php" class="btn-secondary flex items-center"><i class="fas fa-arrow-left mr-2"></i> Back to Dashboard</a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="p-6">
        <div class="max-w-lg mx-auto form-container p-6 animated-section">
            <?php if (isset($error)): ?>
                <p class="error-message mb-4"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <?php if (isset($success)): ?>
                <p class="success-message mb-4"><?php echo htmlspecialchars($success); ?></p>
            <?php endif; ?>
            <form method="POST" action="add_flashcards.php?deck_id=<?php echo $deck_id; ?>">
                <div class="mb-4">
                    <label for="question" class="block text-sm font-medium text-gray-700"><i class="fas fa-question-circle mr-2"></i> Question</label>
                    <input type="text" name="question" id="question" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-gray-900 placeholder-gray-400" required>
                </div>
                <div class="mb-4">
                    <label for="answer" class="block text-sm font-medium text-gray-700"><i class="fas fa-lightbulb mr-2"></i> Answer</label>
                    <textarea name="answer" id="answer" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-gray-900 placeholder-gray-400" required></textarea>
                </div>
                <button type="submit" class="btn-primary"><i class="fas fa-plus mr-2"></i> Add Flashcard</button>
            </form>
        </div>
    </main>

    <!-- GSAP Animations -->
    <script>
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
    </script>
</body>
</html>